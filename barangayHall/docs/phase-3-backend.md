# Phase 3 — Backend

> **Role speaking: BACKEND DEVELOPER** (with the Software Engineer reviewing security)

Everything the React pages need is now served and enforced by Laravel. Pages other than
`auth/change-password` are placeholders until Phase 5; their props, authorization and validation
are final and covered by tests.

---

## 1. Request pipeline

```
Request → web middleware: EncryptCookies → StartSession → CSRF
        → EnforceAccountStatus   (deactivated → logout; must_change_password → /password/change)
        → HandleInertiaRequests  (small shared props: auth.user, can.*, healthCenter)
        → SecurityHeaders        (X-Frame-Options, nosniff, Referrer-Policy, Cache-Control: no-store)
        → route `can:` middleware (2nd line)  → Controller: Gate/Policy (1st line) → Form Request
        → Service → Eloquent (encrypted casts, SoftDeletes, TracksAuthors)
        → AuditableObserver → audit_logs
        → API Resource (role-based fields) → Inertia::render / redirect + toast
```

## 2. Authentication & accounts

| Requirement            | Implementation                                                                                                                                                                                                                                             |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Username login         | `config/fortify.php` `username => 'username'`; `Fortify::authenticateUsing()` in `FortifyServiceProvider`                                                                                                                                                  |
| Lockout                | 5 consecutive wrong passwords → `locked_until = now + 15 min` (`config/barangay.php`, env `LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES`). Locked accounts are refused even with the right password. Fortify's 5/min IP throttle remains as an extra layer. |
| Deactivated accounts   | Refused at login (after the password check, to avoid account probing) and logged out mid-session by `EnforceAccountStatus`.                                                                                                                                |
| Forced password change | `must_change_password` on new accounts and admin resets → every request redirected to `password.change` until changed.                                                                                                                                     |
| Password policy        | min 10, letters, mixed case, numbers. `uncompromised()` removed: the LAN has no internet.                                                                                                                                                                  |
| Temporary passwords    | Generated server-side (12 chars, no ambiguous 0/O/1/l), flashed **once** to the admin (`temporaryPassword`), never typed or emailed.                                                                                                                       |
| Session                | 30-min idle timeout, encrypted DB sessions, regenerated on login and password change.                                                                                                                                                                      |
| Login/logout audit     | `App\Listeners\RecordAuthenticationActivity` (auto-discovered) + `login_failed` / `locked` entries from the authenticator.                                                                                                                                 |

## 3. Authorization (least privilege)

- **Gates:** one per `PermissionName` (e.g. `Gate::allows('reports.export')`), defined in `AppServiceProvider` from the DB-driven role → permission matrix.
- **Policies:** `ResidentPolicy` (view, create, update, archive, restore, `forceDelete` with retention rule, `viewSensitive`, `recordHealth`, `recordConsent`), `HouseholdPolicy` (can't delete with members), `UserPolicy`, and a shared `HealthRecordPolicy` for visits, immunizations, conditions and maternal records.
- **Route middleware** `can:residents.view`, `can:health.record`, `can:users.manage`, … as a second line.
- **Admins can't lock themselves out** (own role / active flag change refused in `UserRequest`).
- **Barangay Officials** are blocked from every resident/health route and the duplicate-check JSON; reports give them aggregates only (`lists` prop is `null`, exports say "aggregates only").

## 4. Data protection in responses (API Resources)

| Field                                                                    | List pages                                     | Profile (role with `health.view_sensitive`)                      | Profile (role without)             |
| ------------------------------------------------------------------------ | ---------------------------------------------- | ---------------------------------------------------------------- | ---------------------------------- |
| PhilHealth no.                                                           | `philhealth_no_masked` only (`••-•••••••67-1`) | full `philhealth_no`                                             | masked only                        |
| Chief complaint, diagnosis, visit notes, condition notes, maternal notes | never                                          | included                                                         | **key absent**, `restricted: true` |
| Health tabs (`health` prop)                                              | —                                              | **deferred**; loading it writes a `viewed_sensitive` audit entry | `null` (never sent)                |

No Eloquent model is passed to `Inertia::render()`. Shared `auth.user` is an explicit array (no password/2FA/remember token). `JsonResource::withoutWrapping()`: single resources are plain objects; paginated collections keep `data / links / meta`. TS mirrors: `resources/js/types/models.d.ts`.

## 5. Audit logging

`AuditableObserver` on Resident, Household, Consent, HealthVisit, VitalSign, ResidentCondition, Immunization, MaternalRecord, MaternalVisit, User and all lookups:

| Event        | Action                 | Stored                         |
| ------------ | ---------------------- | ------------------------------ |
| created      | `created`              | sanitized attributes           |
| updated      | `updated`              | only changed fields, old + new |
| soft delete  | `deleted` ("Archived") | —                              |
| restore      | `restored`             | —                              |
| force delete | `force_deleted`        | sanitized old attributes       |

**Sanitizing:** hidden attributes (password, 2FA secrets, remember token) are dropped; **encrypted fields are stored as `"[encrypted]"`**, so the log never holds plaintext health data. Also logged: `viewed_sensitive`, `exported` (every PDF/XLSX/CSV and backup download, with filters), `login`, `logout`, `login_failed`, `locked`, `password_changed`, `password_reset`, `backup`.
`AuditLog` throws on update/delete; there are only `GET` routes for audit logs (tested).
`TracksAuthors` fills `created_by` / `updated_by` automatically.

## 6. Services

| Service                 | Purpose                                                                                                                                                                                                                                                                   |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `ResidentSearch`        | Server-side search/filter/sort/paginate. Detects resident code (`KII-…`), household no. (`HH-…`), birthdate (`YYYY-MM-DD`), `"Last, First"`, else prefix match on last/first name **OR** FULLTEXT word match (MySQL). Sort columns whitelisted; `LIKE` wildcards escaped. |
| `DuplicateDetector`     | Same last + first name + birthdate (index-only lookup); used on save and by the live JSON check.                                                                                                                                                                          |
| `ResidentCodeGenerator` | `KII-YYYY-NNNNN` with a row lock inside the registration transaction.                                                                                                                                                                                                     |
| `ImmunizationSchedule`  | Per-child EPI status: given / due / overdue (>28 days late) / upcoming.                                                                                                                                                                                                   |
| `ReportService`         | `summary()` aggregates (safe for Officials) and `lists()` named line lists (health workers only). Age math done in PHP, bound as dates → sargable and SQLite-compatible.                                                                                                  |
| `LookupOptions`         | `{value, label}` lists; household Combobox search (served via Inertia **partial reload** of an `optional` prop, not a JSON API).                                                                                                                                          |
| `AuditLogger`           | Single writer for `audit_logs`; adds IP + user agent.                                                                                                                                                                                                                     |
| `Backup\BackupService`  | `mysqldump` → gzip → `Crypt::encryptString` → `storage/app/private/backups`; rotation 14 daily / 8 weekly (Sundays) / 10 manual; `decrypt()`. Dumper is an interface (`MysqlDumper`, `SqliteDumper`, fake in tests).                                                      |

## 7. Controllers & routes (`php artisan route:list --except-vendor`)

| Area               | Routes                                                                                                                                                                                                                                                                                                                      |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Account            | `GET/PUT password/change`                                                                                                                                                                                                                                                                                                   |
| Dashboard          | `GET dashboard` — deferred `stats`, `residentsPerPurok`, `ageSex`; `recentActivity` (health workers only)                                                                                                                                                                                                                   |
| Residents          | `resource residents` (show includes archived), `POST residents/{id}/restore`, `DELETE residents/{id}/permanent`, `GET residents/duplicate-check` (JSON, throttled)                                                                                                                                                          |
| Households         | `GET/POST households`, `GET/PUT/DELETE households/{id}`                                                                                                                                                                                                                                                                     |
| Health quick entry | `POST residents/{id}/visits`, `PUT/DELETE visits/{id}`, `POST residents/{id}/immunizations`, `DELETE immunizations/{id}`, `POST residents/{id}/conditions`, `PUT/DELETE conditions/{id}`, `POST residents/{id}/maternal-records`, `PUT maternal-records/{id}`, `POST residents/{id}/consents`, `PATCH consents/{id}/revoke` |
| Reports            | `GET reports` (deferred `summary`, `lists`), `GET reports/export?format=pdf                                                                                                                                                                                                                                                 | xlsx | csv[&inline=1]` (plain download link) |
| Admin              | `GET/POST users`, `PUT users/{id}`, `POST users/{id}/reset-password`, `POST users/{id}/unlock`, `GET audit-logs`, `GET/POST backups`, `GET backups/{id}/download`                                                                                                                                                           |

48 routes total. Flash keys (typed in `global.d.ts`): `toast`, `duplicates`, `temporaryPassword`, `createdHousehold`.

## 8. Validation highlights (Form Requests)

- Residents: names letters/ñ/accents/space/`.'-` only; PhilHealth normalized to `NN-NNNNNNNNN-N`; mobile normalized to `09XXXXXXXXX`; birthdate not in the future; **consent checkbox + date + purpose required** to register.
- Visits: diastolic < systolic, temperature 30–45 °C, etc. (mirrors DB CHECKs); visit date not before birth.
- Immunizations: dose ≤ vaccine's dose count; same dose can't be recorded twice.
- Maternal: female aged 10–55, one ongoing pregnancy, para ≤ gravida, EDD defaults to LMP + 280 days.
- Users: lowercase usernames, unique; can't change own role or deactivate self.

## 9. Backups & schedule

`php artisan backup:run [--manual] [--no-prune]` · `php artisan backup:decrypt <file> [--output=]` · scheduled daily at `BACKUP_TIME` (23:00) in `routes/console.php`.
Full procedure, Task Scheduler setup and troubleshooting: **[docs/restore.md](restore.md)**.
**Verified on the XAMPP MariaDB:** real backup (65 KB encrypted) → decrypt → import into a scratch DB → 51 residents, 57 visits, all CHECK constraints restored.

## 10. Frontend touched in this phase

- `resources/js/types/models.d.ts` (all Resource shapes), `auth.ts` (shared user + `Can`), `global.d.ts` (shared props + flash types).
- `pages/auth/change-password.tsx` — real page (needed for first login).
- Sidebar shows menu items by role (`can.*`).
- Placeholder pages for residents, households, reports, users, audit logs, backups (replaced in Phase 5).

## 11. Tests — 104 passing

New in this phase: `Auth/AccountSecurityTest` (lockout, deactivation, forced change, headers), `Access/RoleAccessTest` (every page × every role), `Residents/ResidentRegistrationTest` (consent, code, normalization, duplicates, JSON check, history), `Residents/ResidentSearchTest` (name/code/household/birthdate, filters, pagination, sort whitelist, archive), `Residents/SensitiveDataTest` (masking, deferred health + `viewed_sensitive` log, restricted role, Officials blocked, nothing sensitive in page source), `Residents/ArchiveTest` (reason, restore, retention), `Health/HealthRecordTest` (vitals/BMI, encryption, CHECK-like validation, doses, maternal), `Reports/ReportTest` (totals, lists vs aggregates, purok filter, PDF/XLSX/CSV + audit), `Admin/UserManagementTest`, `Admin/BackupTest` (encrypt/decrypt, failure, download, rotation, schedule).

Also passing: Larastan level 7, Pint, `tsc`, `vp check`, `npm run build`.

## 12. Decisions to confirm

1. **Archive with status "active"** is allowed (for duplicate/erroneous entries) — reason still required.
2. **Officials may export** aggregate reports (PDF/Excel) — matches `reports.export` in the permission matrix.
3. **Recent activity** on the dashboard is hidden from Officials (it names records even without health details).
4. **Lookup management UI** (add a purok/vaccine/condition) is not built — Admin has the `lookups.manage` permission; I can add a small page in Phase 5 if wanted.
