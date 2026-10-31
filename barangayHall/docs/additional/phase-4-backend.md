# Phase 4 (Additional Modules) — Backend

> **Role speaking: DATABASE ARCHITECT / BACKEND DEVELOPER** (Software Engineer reviewing security)

Server side of modules 9–17 and notifications: services, Form Requests, Policies, API Resources, controllers,
queued jobs, the daily alert run, notifications and routes. Pages render **placeholders** (`ModulePlaceholder`)
until Phase 6 builds the screens; their props, authorization and validation are final and covered by tests.

---

## 1. Request pipeline (unchanged, extended)

```
Browser → auth/session/CSRF → EnforceAccountStatus → HandleInertiaRequests
       (can.* + 18 new flags, unreadNotifications, lazy notificationsLatest)
  → route `can:` middleware (2nd line) → Controller → Policy / Gate / Form Request (1st line)
  → Service (business rules, transactions, row locks) → Eloquent (encrypted casts, audit observer)
  → API Resource (role-based fields) → Inertia::render / redirect + toast
Service ─dispatch(afterCommit)→ jobs table → queue:work (every minute) → DetectOutbreaks / notifications
Scheduler (hourly 06:00–18:00) → health:daily-checks
```

## 2. Services (business logic lives here, not in controllers)

| Service                        | Responsibility                                                                                                                                                                                                                                                                                                                                  |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `OutbreakDetectionService`     | Counts suspected + probable + confirmed cases (ruled-out excluded) per purok or barangay within each active threshold's window; creates an alert or escalates an open one; notifies `outbreaks.view_summary` (Officials included — counts only). `Cache::lock` prevents twin alerts from a burst of saved cases.                                |
| `DiseaseCaseService`           | Records a case with the purok **snapshot**; dispatches `DetectOutbreaks` after commit; re-checks when a classification changes.                                                                                                                                                                                                                 |
| `InventoryService`             | The only writer of `quantity_on_hand`: receive, **FEFO dispense** (`SELECT … FOR UPDATE` on usable batches ordered by expiry), count adjustment, transfer out, expired write-off. Each change = one transaction + one ledger row. Insufficient stock → `InsufficientStock`, nothing changed.                                                    |
| `InventoryAlertService`        | Expiry bands 90 / 60 / 30 / expired, each **once per batch** (`expiry_alert_band`); daily digest to `inventory.manage`. Low stock (usable stock ≤ reorder level), once per medicine per day; also checked right after each dispense.                                                                                                            |
| `PregnancyScheduleService`     | Prenatal/postnatal contacts from `maternal_visit_rules`: due date, status (done / due / overdue / upcoming / missed / not applicable), next contact, which rule a new visit fulfils.                                                                                                                                                            |
| `ImmunizationSchedule`         | EPI checklist, now honouring `min_interval_days` (a late dose 1 pushes dose 2 back).                                                                                                                                                                                                                                                            |
| `MicronutrientSchedule`        | Vitamin A every 6 months from 6 months; deworming every 6 months from 12 months (to 59 months).                                                                                                                                                                                                                                                 |
| `AppointmentGenerator`         | Idempotent appointments from the schedules (unique `source + source_key`), within a 60-day horizon; completes them when the visit/dose is recorded; cancels ANC once a pregnancy ends; re-books missed EPI doses on the next EPI day (Wednesday, configurable); never touches `edited_manually` appointments. `markMissed()` for the daily run. |
| `PregnancyService`             | Registration (risk factor ⇒ high-risk; books ANC), updates, **delivery** (delivery record, status + outcome date, optional newborn registration with `mother_id`, birth weight as first growth record, consent from the mother; then PNC + newborn EPI appointments).                                                                           |
| `ReferralService`              | `REF-YYYY-NNNNN` numbering under a row lock; forward-only transitions (pending → sent → received → completed; pending/sent → cancelled) with an append-only trail; notifies the referrer.                                                                                                                                                       |
| `ProgramService`               | Eligible residents per target group and target puroks (ages as of the program date); duplicate-safe attendance.                                                                                                                                                                                                                                 |
| `HouseholdMapService`          | Exact markers (compact arrays, 4 grouped flag queries), "not yet geo-tagged" to-do list, purok-level summary at the Admin-set centre.                                                                                                                                                                                                           |
| `OverdueCareService`           | Daily digests: overdue maternal contacts → `maternal.manage`; children with overdue doses → `health.record`; missed appointments → assigned staff (unassigned → `appointments.manage`).                                                                                                                                                         |
| `NotificationRouter`           | Recipients **by permission** (active users only) or explicit users; **dedupe key** claimed in `notification_dispatches` before sending, so re-runs never repeat a notification.                                                                                                                                                                 |
| `SmsGateway` / `LogSmsGateway` | Interface for a future Semaphore driver; v1 writes masked numbers to the log.                                                                                                                                                                                                                                                                   |

## 3. Daily alert run and queue

`php artisan health:daily-checks [--force]` — scheduled **hourly 06:00–18:00**; once a run completes, the rest of the
day is skipped (cache key `daily-checks:Y-m-d`), so a PC switched on at 8 AM still gets its checks. A failing step is
reported and the others continue; the next hourly run retries.

| Step                 | Result on the sample data (run on MariaDB, 2026-10-31)                                               |
| -------------------- | ---------------------------------------------------------------------------------------------------- |
| Missed appointments  | 0 (yesterday's was already seeded as missed)                                                         |
| Outbreak thresholds  | 0 new (the Purok 3 dengue alert already exists with 3 cases)                                         |
| Medicine expiry      | 1 expired, 1 ≤ 30 days, 1 ≤ 60 days, 2 ≤ 90 days → one digest                                        |
| Low stock            | Losartan (120/200), Cotrimoxazole (0), Amoxicillin suspension (0 usable — its only batch is expired) |
| Overdue care         | 1 prenatal contact (Rhealyn, 26 weeks); 3 children with overdue doses                                |
| Program reminders    | 2 programs within 3 days                                                                             |
| Appointment planning | up to date                                                                                           |

After `queue:work` processed the 22 queued notifications, inboxes were: **Admin 8, Midwife 8** (inventory, maternal,
EPI, programs) · **BHWs 3 each** (EPI, programs) · **Barangay Official 2 (program reminders only)**.

Scheduler entries (`routes/console.php`): `health:daily-checks` hourly in the window, `queue:work --stop-when-empty
--max-time=55` every minute (no Supervisor on Windows), read notifications pruned after 90 days, plus the existing backup
and `model:prune`.

## 4. Authorization

| Policy (auto-discovered)        | Rules                                                                                                                       |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `PregnancyPolicy`               | view: `health.view_sensitive`; create/update: `maternal.manage` (BHWs can no longer register pregnancies — Phase 1 default) |
| `DiseaseCasePolicy`             | viewAny: `diseases.view`; create: `diseases.record`; update/delete: `diseases.classify`                                     |
| `OutbreakAlertPolicy`           | viewAny: `outbreaks.view_summary`; update: `outbreaks.manage`                                                               |
| `ReferralPolicy`                | view: `health.view_sensitive`; create/update: `referrals.manage`                                                            |
| `MedicinePolicy`                | view: `inventory.view`; manage: `inventory.manage`; `dispense`: `inventory.dispense`                                        |
| `AppointmentPolicy`             | `appointments.manage`                                                                                                       |
| `HealthProgramPolicy`           | view: `programs.view`; manage + attendance: `programs.manage`                                                               |
| `HouseholdPolicy::geotag`       | `households.geotag` (+ consent check in `GeotagRequest`)                                                                    |
| `HealthRecordPolicy` (now also) | EmergencyContact, MedicalAlert, GrowthMeasurement, MicronutrientRecord                                                      |

Extra rules enforced in requests/services: geo-tag needs an active **geo-tagging consent** of a current member and a
point inside the Isulan bounding box; BHW case reports are forced to _suspected_; "delivered" can only be set by
recording a delivery; one primary emergency contact; attendance only after a program starts; same-site links only
when opening a notification.

## 5. Data protection in responses

| Data                                                                                        | Who receives it                                                                                                                                                |
| ------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Household latitude/longitude                                                                | `map.view_exact` only — keys **absent** for others (`HouseholdResource`, markers). Loading the map writes a `viewed_sensitive` audit entry.                    |
| Officials' map                                                                              | `purokSummary`: counts per purok at the Admin-set centre; `markers` / `notGeotagged` are `null`.                                                               |
| Disease case notes, referral reason/feedback, appointment notes, prenatal/postnatal remarks | `health.view_sensitive` only; referral reason/feedback only on the detail page (`withDetails()`), never in lists.                                              |
| Dispensing ledger resident names                                                            | `health.view_sensitive` only.                                                                                                                                  |
| Program attendee names                                                                      | `programs.manage` only; Officials get `attendance_count` and `attendanceByPurok`.                                                                              |
| Calendar feed (JSON)                                                                        | Appointments (resident names) only for `appointments.manage`; Officials receive programs only; range capped at 62 days; throttled.                             |
| Family planning method                                                                      | Only for a member with an active **family_planning** consent.                                                                                                  |
| Notifications                                                                               | Payload = title, message with counts, relative link, level. Tests assert no resident names or diagnoses.                                                       |
| Critical medical alerts                                                                     | `criticalAlerts` prop (not deferred) on the resident profile, pregnancy, child-health and referral pages; dispense dialog via `residentAlerts` partial reload. |

Withdrawing the last geo-tagging consent in a household **removes its coordinates** (audit-logged).

## 6. Pages and props (placeholders until Phase 6)

| Page component                    | Route (name)                            | Main props                                                                                                              |
| --------------------------------- | --------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `map/index`                       | `GET /map` (`map.index`)                | `mode` exact/aggregate, `map` centre/zoom, deferred `markers`, `notGeotagged`, `purokSummary`, `canGeotag`              |
| `diseases/index`                  | `GET /diseases` (`disease-cases.index`) | paginated `cases`, `openAlerts`, deferred `trend` (26 weeks, top 5 diseases) and `perPurok`, optional `residentOptions` |
| `diseases/alerts`                 | `GET /outbreak-alerts`                  | `alerts` (open first), `canManage`                                                                                      |
| `maternal/index`                  | `GET /maternal` (`pregnancies.index`)   | pregnancies with `next_contact`, `overdue_contacts`; filters purok / risk / overdue / due soon / postnatal              |
| `maternal/show`                   | `GET /maternal/{pregnancy}`             | pregnancy, `criticalAlerts`, `contacts` timeline, `visits` (with vitals), `appointments`, `newborns`, options           |
| `child-health/index`              | `GET /child-health`                     | under-5s with EPI counts, latest nutritional status, vitamin A due; filters overdue / due / malnourished / vitamin A    |
| `child-health/show`               | `GET /child-health/{resident}`          | child, mother, `immunizationSchedule`, `growth` series, `micronutrients`, `micronutrientStatus`                         |
| `referrals/index`                 | `GET /referrals`                        | paginated referrals (no reason), filters, facilities, `residentOptions`                                                 |
| `referrals/show`                  | `GET /referrals/{referral}`             | referral with reason, feedback and status history, linked visit vitals, `nextStatuses`                                  |
| `inventory/index`                 | `GET /inventory` (`medicines.index`)    | medicines with usable/expired on hand and nearest expiry; filters expiring / low; `residentOptions`, `residentAlerts`   |
| `inventory/show`                  | `GET /inventory/{medicine}`             | batches with `expiry_band`/`days_to_expiry`, paginated ledger                                                           |
| `appointments/index`              | `GET /appointments`                     | paginated list (default today + 7 days), `counts` today / missed, options                                               |
| `programs/index`, `programs/show` | `GET /programs[/{program}]`             | programs, `eligibleCount`, `attendance` (managers), `attendanceByPurok`, optional `eligible` search                     |
| `notifications/index`             | `GET /notifications`                    | own notifications, unread filter                                                                                        |
| `health-settings/index`           | `GET /health-settings`                  | thresholds, diseases, facilities, purok centres (Admin); maternal & vaccine rules (Midwife/Admin)                       |
| existing `households/show`        | —                                       | + `memberHealth` flags, `geotag` status, water/toilet options (family health profile)                                   |
| existing `residents/show`         | —                                       | + `criticalAlerts`; deferred `health` adds contacts, alerts, growth, micronutrients, cases, referrals, appointments     |
| existing `dashboard`              | —                                       | + `outbreaks` banner, `diseaseTrend`, `serviceStats`, `todaysAppointments`, `upcomingPrograms`                          |

Full route list: `routes/modules.php` (67 routes; 144 in the app — `php artisan route:list`). The only new JSON endpoint is
`GET /calendar/events` (FullCalendar needs it); combobox searches and the bell list use Inertia partial reloads.

### 6.1 Prop changes made in Phase 6

`child-health/index` prop `children` → **`childrenUnderFive`** (React reserves `children`); `child-health/show` and
`maternal/show` options gained `vaccines` / `visitTypes`; `health-settings/index` permission prop `can` → **`allowed`**
(a page prop named `can` replaced the shared `can` flags and emptied the sidebar); `diseases/index` gained `map`
(centre/zoom from config); `HouseholdResource` gained `water_source_id` / `toilet_type_id`; `appointments/index`
gained optional `selectedAppointment` (`?appointment=ID`); new page `health/visits` (`GET /visits`, `visits.index`).

## 7. Bugs found while testing (fixed)

| Found by      | Problem                                                                                                                     | Fix                                                                                   |
| ------------- | --------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| Outbreak test | A case with onset **today** was outside the window on SQLite (dates stored as `Y-m-d 00:00:00`, compared with `<= 'Y-m-d'`) | Exclusive next-day upper bounds everywhere (`< tomorrow`); `whereDate()` for equality |
| Delivery test | ANC contacts never attended before delivery stayed "overdue" forever and their appointments stayed open                     | New status **missed** for ended pregnancies; generator cancels those appointments     |
| Growth test   | Duplicate-date check missed on SQLite → database error instead of a message                                                 | Date-part comparison rule with a friendly message                                     |
| Larastan      | 25 type issues (lists vs arrays, untyped `find()`, service input shapes)                                                    | Fixed at the source; 0 errors                                                         |

## 8. Tests

New: `tests/Feature/Modules/` — 7 files, **52 tests**: outbreak detection (threshold, exclusions, idempotency,
escalation, barangay scope, BHW report path, classification rights, officials' view), inventory (FEFO, expired stock,
insufficient stock rollback, ledger reconciliation, roles, expiry bands once each, low stock once a day), maternal & child
(registration rights, high-risk, ANC booking + idempotency + manual edits kept, visit → contact → appointment completed,
overdue contacts, delivery + newborn + PNC/EPI + ANC cancelled, minimum EPI interval, EPI re-booking, vitamin A, growth
uniqueness), geo-tagging (consent, bounds, consent withdrawal, audited exact markers, purok-level for Officials,
coordinates hidden), referrals & safety (numbering, forward-only transitions, notifications without PII, list vs detail,
critical banner, one primary contact), scheduling & notifications (calendar per role, eligibility, attendance, daily run
idempotent + role routing, missed digest, own-notifications only, notes visibility), and a **role × page access matrix**.

See §9 for the totals.

## 9. Verification

| Check              | Result                                                                                                                                   |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------- |
| Pest               | **168 / 168 pass** (1,112 assertions): 116 existing + 52 new                                                                             |
| Larastan (level 7) | 0 errors                                                                                                                                 |
| Pint               | clean                                                                                                                                    |
| `tsc --noEmit`     | clean (Wayfinder regenerated for the new routes)                                                                                         |
| `vp check`         | clean except `featured.md` (your spec file — left untouched)                                                                             |
| `npm run build`    | succeeds                                                                                                                                 |
| MariaDB            | `health:daily-checks` and the map/schedule/inventory services run on the real database; 22 queued notifications processed, 0 failed jobs |

## 10. Notes for the next phases

- Phase 5/6: sidebar regrouping, the 16 placeholder pages, bell popover (partial reload of `notificationsLatest`),
  Leaflet map + geo-tag picker, FullCalendar against `calendar.events`, dispense dialog showing `residentAlerts`.
- The existing pregnancy dialog on the resident profile still offers "Delivered"; the server now refuses it there
  (use the delivery form) — Phase 6 replaces that dialog.
- Phase 7: referral slip PDF and family health summary PDF (data is ready in `referrals.show` / `households.show`).
- SMS stays off; adding Semaphore = one class implementing `SmsGateway` + an `sms_reminders` consent check.
