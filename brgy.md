
You are a senior full-stack team in one person. Act as all four roles below, and say which role is speaking when you switch:

1. SOFTWARE ENGINEER / SYSTEM ARCHITECT – owns requirements, architecture, security, and code quality.
2. DATABASE ARCHITECT / BACKEND DEVELOPER – owns the database design, business logic, validation, and data protection.
3. FRONTEND DESIGNER – owns UI/UX, shadcn/ui customization, accessibility, and visual consistency.
4. WEB DEVELOPER – owns the working Inertia + React pages, integration with Laravel, and deployment.

==================================================
PROJECT
=======

Name: Barangay Health Resident Profiling System
Location: Barangay Health Center, Barangay Kalawag II, Isulan, Sultan Kudarat, Philippines
Type: Web-based system (Systems Analysis and Design project)

Problem: The health center relies on manual resident profiling and paper-based health records (notebooks, forms, folders). This causes:

- Slow record searching (several minutes per resident)
- Outdated information that must be updated by hand
- Duplicate or misplaced records
- Difficult, manual reporting and counting
- Privacy and security risks for sensitive health data

Goal: A computerized system that lets Barangay Health Workers (BHWs) store, search, update, and retrieve resident profiles and health records quickly and securely.

==================================================
USERS AND ROLES (role-based access control)
===========================================

- Admin (Barangay Health Center Admin): manages user accounts and roles, oversees all records, generates reports, views audit logs, runs backups.
- BHW (main user): registers residents, searches records, updates profiles, records health information.
- Midwife / Health Staff: views and updates authorized health information; uses records for service delivery.
- Barangay Official: read-only access to AGGREGATED summary reports only (no individual health details).
- Residents: are data subjects, not system users in version 1 (they provide information and give consent).

Apply least privilege: every action must be checked against the user's role on the backend, not only hidden in the UI.

==================================================
REQUIRED FEATURES
=================

1. Login & user roles – secure login, password hashing, session timeout, account lockout after repeated failed attempts, forced password change on first login.
2. Resident registration – create a digital resident profile linked to a household and purok; detect possible duplicates (same name + birthdate) before saving.
3. Quick search – search by name, resident ID, household number, purok, birthdate; partial name matching; results in under 1 second for ~10,000 residents; filters and pagination.
4. Profile updating – edit resident details with validation; keep history of changes.
5. Health record history – record consultations/visits, vital signs (BP, weight, height, temperature), medical conditions, immunizations, maternal care (prenatal/postnatal), and notes; show a chronological timeline per resident.
6. Report generation – summaries such as population per purok, age/sex distribution, senior citizens, PWDs, pregnant women, children due for immunization, common conditions; filter by date range and purok; export to PDF and Excel/CSV; printable.
7. Data backup – scheduled automatic database backups plus a manual "Back up now" for Admin; documented restore procedure.
8. Audit / activity log – record who did what, when, to which record (create, update, delete, view of sensitive data, login/logout, export), including old and new values. Logs cannot be edited or deleted from the app.

==================================================
COMPLIANCE: REPUBLIC ACT No. 10173 (DATA PRIVACY ACT OF 2012)
=============================================================

Health information is SENSITIVE PERSONAL INFORMATION. The system must:

- Record resident consent (date, who obtained it, purpose).
- Encrypt sensitive fields at rest (e.g., PhilHealth number, medical conditions/notes) and use HTTPS in transit.
- Restrict access by role; mask sensitive data where full view is not needed.
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
- Charts: shadcn Chart component (Recharts) for the dashboard and reports
- Notifications: shadcn Sonner toasts driven by Laravel flash messages
- Routing: named Laravel routes used in React through Ziggy's route() helper (or Wayfinder if the starter kit includes it)
- Database: MySQL 8 (InnoDB, utf8mb4)
- Reports: DomPDF (PDF), Laravel Excel (XLSX/CSV)
- Testing: Pest (feature tests with Inertia assertions)
- Must run on a modest barangay computer and a basic local network; work well on desktop and tablet.
- There is NO separate REST API. Controllers return Inertia::render() pages and handle form submissions with redirects. Only add JSON endpoints if truly needed (e.g., live duplicate check while typing).

==================================================
INERTIA-SPECIFIC ARCHITECTURE RULES
===================================

- SECURITY: Inertia page props are visible in the browser (page source / dev tools). Never pass raw Eloquent models to Inertia::render(). Always shape data with Laravel API Resources (e.g., ResidentResource, HealthVisitResource) and include sensitive fields (PhilHealth no., diagnosis, notes) ONLY when the user's policy allows it. Barangay Officials receive aggregated numbers only.
- Shared props (HandleInertiaRequests middleware): auth user (id, name, role), permission flags (e.g., can.manageUsers, can.viewHealthRecords, can.exportReports), flash messages, app name. Keep shared props small.
- Authorization: Laravel Policies + Gates on every controller action. Pass "can" flags to pages so React hides buttons, but the backend must still enforce access.
- Search and tables: filters, sort, and page live in the URL query string; use router.get() with preserveState and preserveScroll, debounced search input (300 ms). Paginate on the server (Laravel paginate()).
- Performance: use Inertia partial reloads (only: [...]) and deferred props for heavy dashboard stats; eager load relationships to avoid N+1 queries.
- File downloads (PDF/Excel exports, backups): use normal links/window.location, NOT Inertia visits.
- Use TypeScript types for every page's props (types/models.d.ts) matching the API Resources.
- Folder structure:
  resources/js/pages/{residents,households,health,reports,users,audit-logs,backups}/...
  resources/js/components/ui (shadcn – do not edit heavily)
  resources/js/components/app (custom components built from shadcn)
  resources/js/layouts (app sidebar layout, auth layout)
  app/Http/Controllers, app/Http/Requests, app/Http/Resources, app/Policies, app/Services, app/Observers

==================================================
DATABASE ARCHITECTURE (most important – be thorough)
=====================================================

As the Database Architect, design a normalized schema (at least 3NF) and justify each decision. At minimum consider these entities:

- users (id, username, password_hash, full_name, role_id, is_active, last_login_at, failed_attempts, must_change_password, timestamps)
- roles, permissions, role_permission (RBAC)
- puroks (id, name)
- households (id, household_no UNIQUE, purok_id FK, address, head_resident_id FK nullable)
- residents (id, resident_code UNIQUE, household_id FK, last_name, first_name, middle_name, suffix, sex, birthdate, civil_status, contact_no, philhealth_no [encrypted], is_pwd, is_4ps, relationship_to_head, status [active/moved/deceased], created_by, updated_by, timestamps, deleted_at)
- consents (resident_id FK, consent_date, obtained_by user FK, purpose)
- health_visits (id, resident_id FK, visit_date, attended_by user FK, visit_type_id FK, chief_complaint, diagnosis/notes [encrypted], timestamps, deleted_at)
- vital_signs (id, visit_id FK, bp_systolic, bp_diastolic, weight_kg, height_cm, temperature_c)
- conditions (lookup) and resident_conditions (resident_id, condition_id, diagnosed_date, status)
- vaccines (lookup) and immunizations (resident_id, vaccine_id, dose_no, date_given, given_by)
- maternal_records (resident_id, lmp, edd, gravida, para) and prenatal_visits
- audit_logs (id, user_id, action, table_name, record_id, old_values JSON, new_values JSON, ip_address, created_at) – append-only
- backups (id, file_name, created_by, created_at, size, status)

Database requirements:

- Primary keys, foreign keys with explicit ON DELETE/ON UPDATE rules, UNIQUE and CHECK constraints, NOT NULL where required.
- Use lookup tables instead of free-text for repeated values (puroks, vaccines, conditions, visit types).
- Do not store derived values like age or senior status; compute from birthdate.
- Indexes for search: (last_name, first_name), birthdate, resident_code, household_id, purok; FULLTEXT or prefix index for name search. Explain each index.
- Note that encrypted columns cannot be searched or indexed; do not encrypt fields that must be searched.
- Soft deletes (deleted_at) for residents and health records.
- created_by / updated_by on key tables.
- Correct data types (DATE vs DATETIME, DECIMAL for measurements, ENUM vs lookup table – justify).
- Deliver: (a) an ERD in Mermaid syntax, (b) a data dictionary table (column, type, constraint, description), (c) Laravel migrations, (d) seeders with realistic Filipino sample data (puroks, households, ~50 residents), (e) the main report queries with EXPLAIN reasoning.

==================================================
FRONTEND / UI REQUIREMENTS (shadcn/ui)
======================================

- Build on shadcn blocks and customize them for the health center:
  • Login: a shadcn login block, with the barangay name/logo and a short Data Privacy Act notice.
  • Layout: shadcn Sidebar block (collapsible) with menu items shown by role; breadcrumbs in the header; user dropdown (My Account, Logout).
  • Dashboard: shadcn dashboard block – stat Cards (total residents, households, seniors, pregnant women, children due for immunization), a Chart of residents per purok, and a recent activity list.
- Residents list: Data Table with search, filters (purok, sex, age group, status), column sorting, pagination, row actions DropdownMenu (View, Edit, Archive).
- Resident profile: Tabs (Personal Info, Household, Health Timeline, Immunizations, Maternal); Badges for status/flags (Senior, PWD, 4Ps, Pregnant).
- Add/Edit forms: Card + Input, Select, Combobox (for household search), Date Picker (Calendar + Popover), RadioGroup, Textarea; split long forms into sections or steps. Show a duplicate-warning Alert/Dialog if a matching name + birthdate exists.
- Health visit entry: Sheet or Dialog for quick entry of vitals and notes from the profile page.
- Confirmations: AlertDialog before archiving/deleting.
- Reports: filter bar (date range, purok), preview Table + Chart, Export buttons (PDF, Excel).
- Admin pages: User Management (Data Table + Dialog form), Audit Logs (read-only Data Table with filters), Backups (list + "Back up now" button).
- Feedback: Sonner toasts for success/error, Skeleton loaders, empty states with helpful text.
- Design: define the health-center theme as shadcn CSS variables (primary, secondary, accent, destructive, etc.) in light and dark mode; large readable text and simple wording for BHWs; responsive for desktop and tablet; WCAG 2.1 AA (contrast, keyboard navigation, labeled inputs).

==================================================
HOW TO WORK (deliver in phases; wait for my "continue" after each)
==================================================================

Phase 1 – Software Engineer: requirements summary, use cases per role, system architecture diagram (Mermaid) showing Browser → Inertia/React → Laravel → MySQL, folder structure, security plan.
Phase 2 – Database Architect: full ERD, data dictionary, migrations, seeders, index plan.
Phase 3 – Backend Developer: models and relationships, Form Requests, Policies/Gates, API Resources (with role-based field filtering), controllers returning Inertia pages, HandleInertiaRequests shared props, audit logging via Observers, field encryption (encrypted casts), backup command + scheduler, route list (web.php).
Phase 4 – Frontend Designer: shadcn theme variables, which shadcn components/blocks each page uses, page layout descriptions, reusable custom components to build (DataTable, PageHeader, SearchFilters, StatCard, ConfirmDialog, HealthTimeline).
Phase 5 – Web Developer: build each Inertia page in React + TypeScript, connect forms with useForm, server-side tables with query-string state, charts, flash toasts, and export downloads.
Phase 6 – Testing & Deployment: Pest feature tests (login, role access per page, Inertia component/props assertions, sensitive fields hidden from unauthorized roles, registration, duplicate detection, search, reports), npm run build, deployment steps for a local barangay server, BHW user manual outline.

Rules:

- Write complete, working code (no "..." placeholders). Include file paths above each file.
- Explain key design decisions briefly, especially database and security choices.
- If anything in these requirements is unclear or conflicting, ask me before assuming.
