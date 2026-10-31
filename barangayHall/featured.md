
You are a senior full-stack team in one person. Act as all four roles below, and say which role is speaking when you switch:

1. SOFTWARE ENGINEER / SYSTEM ARCHITECT – owns requirements, architecture, security, and code quality.
2. DATABASE ARCHITECT / BACKEND DEVELOPER – owns the database design, business logic, validation, scheduled jobs, notifications, and data protection.
3. FRONTEND DESIGNER – owns UI/UX, shadcn/ui customization, accessibility, and visual consistency.
4. WEB DEVELOPER – owns the working Inertia + React pages, integration with Laravel, and deployment.

==================================================
PROJECT
=======

Name: Barangay Health Resident Profiling and Health Record Management System
Location: Barangay Health Center, Barangay Kalawag II, Isulan, Sultan Kudarat, Philippines
Type: Web-based system (Systems Analysis and Design project)

Problem: The health center relies on manual resident profiling and paper-based health records (notebooks, forms, folders). This causes:

- Slow record searching (several minutes per resident)
- Outdated information that must be updated by hand
- Duplicate or misplaced records
- Difficult, manual reporting and counting
- No early warning for disease outbreaks, expiring medicines, or missed check-ups
- Privacy and security risks for sensitive health data

Goal: A computerized system that lets Barangay Health Workers (BHWs) and health staff store, search, update, and retrieve resident profiles and health records quickly and securely, track maternal/child health and community programs, and receive automatic alerts.

==================================================
USERS AND ROLES (role-based access control)
===========================================

- Admin (Barangay Health Center Admin): manages user accounts and roles, oversees all records, configures alert thresholds, generates reports, views audit logs, runs backups.
- BHW (main user): registers residents and households, geo-tags households, searches records, updates profiles, records health information, schedules appointments, records program attendance.
- Midwife / Health Staff: views and updates authorized health information, manages maternal and child health records, creates referrals, manages medicine inventory, reports disease cases.
- Barangay Official: read-only access to AGGREGATED reports, the outbreak alert summary, the aggregated disease map (by purok, never individual houses), and the community program schedule. No individual health details.
- Residents: are data subjects, not system users in version 1 (they provide information and give consent).

Apply least privilege: every action must be checked against the user's role on the backend, not only hidden in the UI.

==================================================
REQUIRED FEATURES
=================

CORE MODULES

1. Login & user roles – secure login, password hashing, session timeout, account lockout after repeated failed attempts, forced password change on first login.
2. Resident registration – create a digital resident profile linked to a household and purok; detect possible duplicates (same name + birthdate) before saving.
3. Quick search – search by name, resident ID, household number, purok, birthdate; partial name matching; results in under 1 second for ~10,000 residents; filters and pagination.
4. Profile updating – edit resident details with validation; keep history of changes.
5. Health record history – consultations/visits, vital signs (BP, weight, height, temperature), medical conditions, immunizations, and notes; chronological timeline per resident.
6. Report generation – population per purok, age/sex distribution, seniors, PWDs, pregnant women, children due for immunization, common conditions, disease cases, referrals, program attendance, medicine stock; filter by date range and purok; export to PDF and Excel/CSV; printable.
7. Data backup – scheduled automatic database backups plus a manual "Back up now" for Admin; documented restore procedure.
8. Audit / activity log – who did what, when, to which record (create, update, delete, view of sensitive data, login/logout, export), with old and new values. Logs cannot be edited or deleted from the app.

ADDITIONAL MODULES (added from teacher's evaluation)
9. Geo-tagging of households – save each household's latitude/longitude by tapping "Use my location" on a tablet/phone (browser Geolocation API) or by clicking on a map. Show all households on an interactive map, filterable by purok and by flags (e.g., has pregnant member, has senior, has active disease case). Access to the exact household map is restricted to health staff.
10. Disease surveillance & outbreak alerts – record disease cases (resident, disease, onset date, status: suspected/confirmed/recovered/died, reported by). Admin sets alert thresholds per disease (e.g., "3 or more dengue cases in the same purok within 14 days"). A scheduled job checks thresholds and creates an outbreak alert shown on the dashboard and as a notification. Show case trends (chart) and a cases-per-purok map. Use a notifiable disease list that follows the DOH PIDSR categories, editable by Admin.
11. Maternal and child health tracker –
    • Maternal: pregnancy registration (LMP, EDD auto-computed, gravida/para, risk factors), prenatal visit schedule and records, delivery record (date, place, attendant, outcome), postnatal visits. Flag high-risk pregnancies and overdue visits.
    • Child: link newborn to mother, growth monitoring (weight, height, nutritional status), immunization schedule with due/overdue status, deworming and vitamin A.
    • Visit schedules and immunization schedules are configurable by the midwife/Admin, not hard-coded.
12. Family health profile – household-level view showing all members, their health flags, and household data (water source, toilet/sanitation type, 4Ps membership, family planning method if consented). Printable family health summary.
13. Referral system – create referrals from the barangay health center to the RHU or hospital (resident, reason, urgency, referred-to facility, referred by, date). Track status: pending → sent → received → completed, with a return/feedback note. Printable referral slip (PDF).
14. Medicine inventory & expiry alerts – medicines list, stock by batch (batch no., expiry date, quantity), stock in/out, dispensing to residents. Automatic alerts for batches expiring in 90/60/30 days, already expired, and low stock (reorder level per medicine). Use FEFO (first-expire, first-out) when dispensing.
15. Emergency contacts & medical alerts – each resident can have emergency contacts (name, relationship, phone) and medical alerts (allergies, blood type, chronic conditions, disability, severity). Critical alerts appear as a red banner at the top of the resident's profile and on any health visit form.
16. Appointment schedule – book appointments (resident, type: prenatal, immunization, check-up, follow-up, etc.; date/time; assigned staff; status: scheduled/completed/missed/cancelled). Calendar view (day/week/month) and list view. Auto-create follow-up appointments from maternal and immunization schedules. Dashboard shows today's appointments and missed appointments. SMS reminders are OPTIONAL (design an interface so an SMS gateway like Semaphore can be added later).
17. Community health programs – schedule barangay health activities (immunization days, feeding programs, medical missions, health seminars, clean-up drives): title, type, date/time, venue, target puroks/groups, assigned staff. Record attendance by resident. Show on the shared calendar with appointments.

NOTIFICATIONS

- Use Laravel database notifications with a bell icon in the header (unread count). Sources: outbreak alerts, medicine expiry/low stock, overdue prenatal visits/immunizations, missed appointments, referral status updates, upcoming programs.
- A Laravel scheduler runs daily checks (thresholds, expiry, overdue items) and creates notifications for the right roles only.

==================================================
COMPLIANCE: REPUBLIC ACT No. 10173 (DATA PRIVACY ACT OF 2012)
=============================================================

Health information is SENSITIVE PERSONAL INFORMATION. The system must:

- Record resident consent (date, who obtained it, purpose), including consent for household geo-tagging.
- Encrypt sensitive fields at rest (e.g., PhilHealth number, diagnosis/notes) and use HTTPS in transit.
- Restrict access by role; mask sensitive data where full view is not needed.
- Treat household GPS coordinates as personal data: exact locations only for health staff; Barangay Officials see aggregated purok-level maps only.
- Log every access to and export of sensitive data.
- Never hard-delete residents casually: use soft delete + archive, with Admin-only permanent deletion following a retention policy.
- Show a short privacy notice on login.

==================================================
TECH STACK (fixed – do not change)
===================================

- Backend: Laravel 12 (PHP 8.2+), using the Laravel React Starter Kit
- Frontend: Inertia.js 2 + React 19 + TypeScript
- UI: shadcn/ui components and shadcn blocks (customized), Tailwind CSS v4, lucide-react icons
- Tables: shadcn Data Table pattern (TanStack Table) with SERVER-SIDE pagination, sorting, and filtering
- Forms: Inertia useForm (or the <Form></form> component) with Laravel Form Request validation; show errors under each field using shadcn form styling
- Charts: shadcn Chart component (Recharts)
- Maps: Leaflet + react-leaflet with OpenStreetMap tiles (import Leaflet's CSS; note that map tiles need internet, so the rest of the system must still work offline on the local network)
- Calendar: FullCalendar React (or a custom calendar built with shadcn components) styled to match the shadcn theme
- Notifications: Laravel database notifications + shadcn Sonner toasts for flash messages
- Scheduled jobs: Laravel Scheduler + queued jobs (database queue driver)
- Routing: named Laravel routes used in React through Ziggy's route() helper (or Wayfinder if the starter kit includes it)
- Database: MySQL 8 (InnoDB, utf8mb4)
- Reports: DomPDF (PDF, referral slips, family health summary), Laravel Excel (XLSX/CSV)
- Testing: Pest (feature tests with Inertia assertions)
- Must run on a modest barangay computer and a basic local network; work well on desktop and tablet.
- There is NO separate REST API. Controllers return Inertia::render() pages and handle form submissions with redirects. Only add JSON endpoints if truly needed (e.g., live duplicate check, map markers, calendar events).

==================================================
INERTIA-SPECIFIC ARCHITECTURE RULES
===================================

- SECURITY: Inertia page props are visible in the browser (page source / dev tools). Never pass raw Eloquent models to Inertia::render(). Always shape data with Laravel API Resources and include sensitive fields (PhilHealth no., diagnosis, notes, exact coordinates) ONLY when the user's policy allows it. Barangay Officials receive aggregated data only.
- Shared props (HandleInertiaRequests middleware): auth user (id, name, role), permission flags (e.g., can.manageUsers, can.viewHealthRecords, can.manageInventory, can.viewHouseholdMap, can.exportReports), unread notification count, flash messages. Keep shared props small.
- Authorization: Laravel Policies + Gates on every controller action. Pass "can" flags to pages so React hides buttons, but the backend must still enforce access.
- Search and tables: filters, sort, and page live in the URL query string; use router.get() with preserveState and preserveScroll, debounced search (300 ms). Paginate on the server.
- Performance: Inertia partial reloads and deferred props for heavy dashboard stats, map markers, and charts; eager load relationships to avoid N+1 queries.
- File downloads (PDF/Excel exports, referral slips, backups): use normal links/window.location, NOT Inertia visits.
- Business logic for alerts, schedules, and inventory goes in Service classes (e.g., OutbreakDetectionService, ImmunizationScheduleService, InventoryService), not in controllers.
- Use TypeScript types for every page's props (types/models.d.ts) matching the API Resources.
- Folder structure:
  resources/js/pages/{dashboard,residents,households,map,health,maternal,child-health,diseases,referrals,inventory,appointments,programs,reports,notifications,users,audit-logs,backups}/...
  resources/js/components/ui (shadcn – do not edit heavily)
  resources/js/components/app (custom components built from shadcn)
  resources/js/layouts (app sidebar layout, auth layout)
  app/Http/Controllers, app/Http/Requests, app/Http/Resources, app/Policies, app/Services, app/Observers, app/Notifications, app/Console/Commands

==================================================
DATABASE ARCHITECTURE (most important – be thorough)
=====================================================

As the Database Architect, design a normalized schema (at least 3NF) and justify each decision. At minimum consider these entities:

ACCESS & AUDIT

- users (id, username, password, full_name, role_id, is_active, last_login_at, failed_attempts, must_change_password, timestamps)
- roles, permissions, role_permission (RBAC)
- audit_logs (id, user_id, action, table_name, record_id, old_values JSON, new_values JSON, ip_address, created_at) – append-only
- notifications (Laravel default table)
- backups (id, file_name, created_by, size, status, created_at)

RESIDENTS & HOUSEHOLDS

- puroks (id, name)
- households (id, household_no UNIQUE, purok_id FK, address, head_resident_id FK nullable, latitude DECIMAL(10,7), longitude DECIMAL(10,7), geotagged_at, geotagged_by, water_source_id FK, toilet_type_id FK, is_4ps)
- residents (id, resident_code UNIQUE, household_id FK, last_name, first_name, middle_name, suffix, sex, birthdate, civil_status, contact_no, philhealth_no [encrypted], blood_type, is_pwd, relationship_to_head, mother_id FK nullable (self-reference for child tracking), status [active/moved/deceased], created_by, updated_by, timestamps, deleted_at)
- consents (resident_id FK, consent_type, consent_date, obtained_by, purpose)
- emergency_contacts (resident_id FK, name, relationship, phone, is_primary)
- medical_alerts (resident_id FK, alert_type [allergy/chronic/disability/other], description, severity [info/warning/critical], is_active)
- lookups: water_sources, toilet_types, family_planning_methods

HEALTH RECORDS

- health_visits (id, resident_id FK, visit_date, attended_by, visit_type_id FK, chief_complaint, diagnosis/notes [encrypted], timestamps, deleted_at)
- vital_signs (id, visit_id FK, bp_systolic, bp_diastolic, weight_kg, height_cm, temperature_c)
- conditions (lookup), resident_conditions (resident_id, condition_id, diagnosed_date, status)
- vaccines (lookup), vaccine_schedule_rules (vaccine_id, dose_no, age_in_days/weeks), immunizations (resident_id, vaccine_id, dose_no, date_given, given_by)

MATERNAL & CHILD

- pregnancies (id, resident_id FK, lmp, edd, gravida, para, risk_level, risk_factors, status [ongoing/delivered/terminated])
- prenatal_visits (pregnancy_id FK, visit_date, age_of_gestation_weeks, bp, weight, findings, next_visit_date)
- deliveries (pregnancy_id FK, delivery_date, place, attendant_type, outcome, child_resident_id FK nullable)
- postnatal_visits (pregnancy_id FK, visit_date, findings)
- growth_measurements (resident_id FK, measured_at, weight_kg, height_cm, nutritional_status)
- micronutrient_records (resident_id FK, type [vitamin A/deworming/iron], date_given)

DISEASE SURVEILLANCE

- diseases (id, name, category, is_notifiable)
- disease_cases (id, resident_id FK, disease_id FK, onset_date, reported_date, case_status, outcome, reported_by)
- outbreak_thresholds (disease_id FK, min_cases, days_window, scope [purok/barangay])
- outbreak_alerts (id, disease_id FK, purok_id FK nullable, case_count, period_start, period_end, status [active/monitoring/closed], acknowledged_by, created_at)

REFERRALS

- facilities (id, name, type [RHU/hospital/other], address, contact)
- referrals (id, resident_id FK, referred_by, facility_id FK, reason, urgency, status, referred_at, feedback_note, completed_at)

MEDICINE INVENTORY

- medicines (id, generic_name, brand_name, form, strength, unit, reorder_level)
- medicine_batches (id, medicine_id FK, batch_no, expiry_date, quantity_received, quantity_on_hand, received_at)
- stock_movements (id, batch_id FK, type [in/out/dispensed/expired/adjustment], quantity, resident_id FK nullable, user_id, reason, created_at)

SCHEDULING & PROGRAMS

- appointments (id, resident_id FK, appointment_type_id FK, scheduled_at, assigned_to, status, source_type/source_id (polymorphic, e.g., pregnancy or immunization schedule), notes)
- health_programs (id, title, program_type, description, start_at, end_at, venue, target_group, created_by)
- program_puroks (program_id, purok_id)
- program_attendance (program_id, resident_id, attended_at)

Database requirements:

- Primary keys, foreign keys with explicit ON DELETE/ON UPDATE rules, UNIQUE and CHECK constraints, NOT NULL where required.
- Lookup tables instead of free text for repeated values.
- Do not store derived values like age, senior status, or total stock if they can be computed reliably (explain any exception, e.g., quantity_on_hand kept in sync by stock_movements inside a DB transaction).
- Indexes for search and alerts: (last_name, first_name), birthdate, resident_code, household_id, purok_id, (disease_id, onset_date), expiry_date, scheduled_at, (latitude, longitude) or a SPATIAL index if POINT is used. Explain each index.
- Encrypted columns cannot be searched or indexed; do not encrypt fields that must be searched.
- Inventory changes and dispensing must run inside database transactions with row locking to prevent negative stock.
- Soft deletes for residents and health records; created_by / updated_by on key tables.
- Correct data types (DATE vs DATETIME, DECIMAL for measurements and coordinates, ENUM vs lookup table – justify).
- Deliver: (a) an ERD in Mermaid syntax (split into modules if large), (b) a data dictionary, (c) Laravel migrations, (d) seeders with realistic Filipino sample data (puroks of Kalawag II, households with coordinates around Isulan, Sultan Kudarat, ~50 residents, sample pregnancies, children, disease cases, medicines with varied expiry dates, programs), (e) the main report and alert queries with EXPLAIN reasoning.

==================================================
FRONTEND / UI REQUIREMENTS (shadcn/ui)
======================================

- Build on shadcn blocks and customize them for the health center:
  • Login: shadcn login block with the barangay name/logo and a short Data Privacy Act notice.
  • Layout: shadcn Sidebar block (collapsible) with grouped menus shown by role: Overview (Dashboard, Calendar), Records (Residents, Households, Map), Health (Visits, Maternal, Child Health, Diseases, Referrals), Services (Appointments, Programs, Inventory), Reports, Admin (Users, Audit Logs, Backups, Settings). Breadcrumbs in the header; notification bell with unread count (Popover list); user dropdown.
  • Dashboard: stat Cards (residents, households, pregnant women, children due for immunization, active outbreak alerts, expiring medicines, today's appointments), active outbreak Alert banner, disease trend Chart, residents per purok Chart, upcoming programs, recent activity.
- Residents list: Data Table with search, filters (purok, sex, age group, status, flags), sorting, pagination, row actions (View, Edit, Archive).
- Resident profile: critical medical alert red banner at the top; Tabs (Personal Info, Emergency Contacts & Alerts, Health Timeline, Immunizations, Maternal / Child Growth, Referrals, Appointments); Badges for flags (Senior, PWD, 4Ps, Pregnant, High-risk).
- Household / Family health profile: members table, household data, mini map with the geo-tag, "Print family health summary".
- Map page: full-width Leaflet map, purok filter, marker colors by flag, marker popup with household summary and link; "Use my location" and click-to-place when geo-tagging.
- Maternal tracker: list of ongoing pregnancies with EDD, risk level, next visit, overdue indicator; pregnancy detail with visit timeline.
- Child health: growth chart (weight/height over time), immunization checklist with due/overdue/done badges.
- Diseases: case list Data Table, case form, outbreak alerts list with acknowledge/close actions, cases-per-purok map.
- Referrals: Data Table with status badges, create form, status update, print referral slip.
- Inventory: medicines table with stock and nearest expiry, color-coded expiry badges (red expired, orange ≤30 days, yellow ≤90 days), receive stock and dispense dialogs.
- Appointments & Programs: shared calendar (month/week/day) with color by type, appointment form in a Sheet, program detail with attendance checklist.
- Forms: Card + Input, Select, Combobox (resident/household search), Date Picker (Calendar + Popover), RadioGroup, Textarea; long forms split into sections or steps; duplicate-warning Dialog on resident registration.
- Confirmations: AlertDialog before archiving/deleting.
- Reports: filter bar (date range, purok, module), preview Table + Chart, Export buttons (PDF, Excel).
- Admin: User Management, Audit Logs (read-only), Backups, Settings (outbreak thresholds, immunization/prenatal schedules, reorder levels, facilities).
- Feedback: Sonner toasts, Skeleton loaders, empty states with helpful text.
- Design: health-center theme as shadcn CSS variables in light and dark mode; large readable text and simple wording for BHWs; responsive for desktop and tablet (BHWs may geo-tag in the field using a tablet); WCAG 2.1 AA.

==================================================
HOW TO WORK (deliver in phases; wait for my "continue" after each)
==================================================================

Phase 1 – Software Engineer: requirements summary, use cases per role, module list, system architecture diagram (Mermaid) showing Browser → Inertia/React → Laravel (controllers, services, scheduler, queue) → MySQL, folder structure, security plan.
Phase 2 – Database Architect: full ERD (by module), data dictionary, migrations, seeders, index plan.
Phase 3 – Backend (Core): auth & roles, residents, households, health visits, search, audit logging, backups.
Phase 4 – Backend (Additional modules): geo-tagging, disease surveillance + outbreak detection job, maternal & child tracker + schedule generation, family health profile, referrals, inventory + expiry job, emergency contacts & medical alerts, appointments, community programs, notifications.
Phase 5 – Frontend Designer: shadcn theme variables, which shadcn components/blocks each page uses, page layouts, reusable components (DataTable, PageHeader, SearchFilters, StatCard, ConfirmDialog, HealthTimeline, MedicalAlertBanner, HouseholdMap, CalendarView, ExpiryBadge, NotificationBell).
Phase 6 – Web Developer: build every Inertia page in React + TypeScript for core modules first, then additional modules.
Phase 7 – Reports & exports: all reports, PDF referral slip, family health summary, Excel exports.
Phase 8 – Testing & Deployment: Pest tests (login, role access, sensitive fields hidden from unauthorized roles, duplicate detection, search, outbreak threshold detection, FEFO dispensing and no negative stock, expiry alerts, immunization due dates, appointment generation), scheduler/queue setup (cron or Windows Task Scheduler), npm run build, deployment on a local barangay server, BHW user manual outline.

Rules:

- Write complete, working code (no "..." placeholders). Include file paths above each file.
- Explain key design decisions briefly, especially database and security choices.
- If anything in these requirements is unclear or conflicting, ask me before assuming.
