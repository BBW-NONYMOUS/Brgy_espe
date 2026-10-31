# Phase 8 (Additional Modules) — Testing & Deployment

> **Role speaking: WEB DEVELOPER**

This phase closes the project. It also covers the core spec's own Phase 6 (testing and deployment), which was never
delivered. It has four parts:

- the remaining tests from the spec list;
- the deployment files and guide for a local barangay server;
- the scheduler and queue on Windows Task Scheduler;
- the BHW user manual outline.

| Deliverable                   | Where                                                                                                             |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Deployment guide (IT support) | [`docs/deployment.md`](../deployment.md)                                                                          |
| Backup & restore (updated)    | [`docs/restore.md`](../restore.md)                                                                                |
| BHW user manual outline       | [`docs/bhw-manual-outline.md`](../bhw-manual-outline.md)                                                          |
| Server files                  | `deploy/windows/apache/barangay-health.conf`, `make-certificate.ps1`, `register-scheduler.ps1`, `update.ps1`      |
| Production `.env` template    | `.env.production.example`                                                                                         |
| First Admin / reference data  | `php artisan user:create-admin`, `Database\Seeders\ProductionSeeder`                                              |
| New tests                     | `tests/Feature/Deployment/DeploymentReadinessTest.php`, plus additions to `InventoryTest` and `MaternalChildTest` |

---

## 1. Test coverage against the spec

Every item the spec lists for Phase 8, and where it is tested:

| Spec item                                       | Tests                                                                                                                                                                                                                                                                        |
| ----------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Login                                           | `Auth/AuthenticationTest` (login, wrong password, rate limit, 2FA, passkey, logout), `Auth/AccountSecurityTest` (lockout after 5, deactivated accounts, forced password change, policy, audit entry)                                                                         |
| Role access                                     | `Access/RoleAccessTest` (every core page × role), `Modules/ModuleAccessTest` (every module page × role, settings tabs), `Access/InertiaErrorHandlingTest`, `Admin/UserManagementTest`                                                                                        |
| Sensitive fields hidden from unauthorized roles | `Residents/SensitiveDataTest` (masked PhilHealth, page source), `Modules/GeotagMapTest` (no coordinates for Officials), `Modules/ReferralSafetyTest`, `Modules/VisitListTest`, `Modules/ReportsExportsTest`, `Reports/ReportTest`, `Database/SchemaTest` (encrypted at rest) |
| Duplicate detection                             | `Residents/ResidentRegistrationTest` (flagged before saving, live JSON check)                                                                                                                                                                                                |
| Search                                          | `Residents/ResidentSearchTest` (partial name, ID / household / birthdate, filters, paging, unsafe sort rejected, archived hidden)                                                                                                                                            |
| Outbreak threshold detection                    | `Modules/DiseaseSurveillanceTest` (purok threshold in window, exclusions, idempotent, barangay-wide threshold, notifications without names)                                                                                                                                  |
| FEFO dispensing, no negative stock              | `Modules/InventoryTest`: FEFO and never expired; over-asking changes nothing; ledger reconciles; **new:** dispense, transfer and adjust can never go below zero                                                                                                              |
| Expiry alerts                                   | `Modules/InventoryTest` (once per band per batch; low stock once a day to managers)                                                                                                                                                                                          |
| Immunization due dates                          | `Modules/MaternalChildTest`: **new:** a newborn's 15 EPI doses fall due on the DOH schedule, and turn overdue after 4 weeks; a late dose shifts the next one by the minimum interval; vitamin A every 6 months                                                               |
| Appointment generation                          | `Modules/MaternalChildTest` (ANC contacts once, linked visits, postnatal + EPI after delivery, overdue EPI → next EPI day, re-booking), `Modules/SchedulingNotificationsTest`, `Database/AdditionalModulesSchemaTest`                                                        |
| Scheduler / queue                               | **new** `Deployment/DeploymentReadinessTest`: backup, daily checks, queue worker and pruning scheduled with the right times and overlap locks; every notification is queued                                                                                                  |
| Deployment safety                               | **new** `Deployment/DeploymentReadinessTest`: production forces HTTPS and refuses `migrate:fresh` / `db:wipe`; production template; `ProductionSeeder` + `user:create-admin`; sample seeder refuses production; GPS allowed by Permissions-Policy                            |

Core spec items (registration, Inertia component and props assertions, reports) are covered by
`Residents/*`, the Inertia assertions in nearly every feature test, and `Reports/ReportTest` / `Modules/ReportsExportsTest`.

## 2. Bugs found while preparing deployment

| Bug                                                                                                                                                                                                            | Fix                                                                                                                                                      |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **GPS could never work.** `SecurityHeaders` sent `Permissions-Policy: geolocation=()`, which disables the Geolocation API on every page, even over HTTPS. "Use my location" would have failed on every tablet. | `geolocation=(self)` (this site only); test added.                                                                                                       |
| **No safe way to set up a real server.** The only seeder loads 50 sample residents and five accounts that share a known password; there was no way to create the first Admin.                                  | `ProductionSeeder` (reference data only), `user:create-admin` (one-time temporary password, audited), and `DatabaseSeeder` refuses to run in production. |
| **PDFs were ~880 KB per page** (the whole DejaVu font embedded in each file).                                                                                                                                  | `config/dompdf.php` with font subsetting on: ~30 KB, with glyphs checked visually.                                                                       |
| My first Apache site file hid `/dashboard`, which is also the app's home page (caught by the test server).                                                                                                     | Only `/phpmyadmin` is hidden.                                                                                                                            |
| The temporary-password generator shuffled with the non-cryptographic `shuffle()`.                                                                                                                              | Moved to `App\Support\TemporaryPassword` using `Random\Randomizer` (CSPRNG); used by Users and the new command.                                          |

## 3. Deployment decisions

- **Apache from XAMPP with PHP 8.5.** This was chosen in Phase 1. XAMPP's `httpd-xampp.conf` on this PC already
  loads PHP 8.5.6 from `C:\php-8.5.6`. The guide uses that one PHP for the web, the scheduler and the update script,
  so they share the same `php.ini`. There are currently two copies of PHP 8.5.6, and only the CLI one (`C:\Program
  Files\…`) loads `gd`.
- **HTTPS with mkcert.** This was also a Phase 1 decision. Tablets need a secure origin for GPS. The certificate
  covers the host name, the LAN IP, `localhost` and `127.0.0.1`. Tablets trust the health center's own CA once.
  The guide recommends typing the IP, because Android cannot use a hosts file.
- **One Task Scheduler task.** It runs `schedule:run` every minute as SYSTEM. The queue runs as a short-lived worker
  (`--stop-when-empty --max-time=55`), so no Supervisor-style service is needed on Windows. It survives reboots and
  uses no memory when idle.
- **Least privilege.** The app uses its own `barangay_app` MySQL account and never `root`. MariaDB listens on
  `127.0.0.1` only. The firewall opens 80/443 on the private profile only. Apache serves only `public\`.
- **App folder outside the user profile** (`C:\BarangayHealth`), so OneDrive cannot sync health data.
- **`update.ps1` backs up before migrating** and leaves maintenance mode on if a step fails.

## 4. Verification

| Check                                                                                                                                                                                            | Result                                                                                                                                                                                                                                                                                                                                                                                                                             |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Full Pest suite                                                                                                                                                                                  | **195 / 195** passed                                                                                                                                                                                                                                                                                                                                                                                                               |
| Pint, Larastan (level 7)                                                                                                                                                                         | Clean                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `pnpm run build`                                                                                                                                                                                 | Built                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `php artisan optimize` (config, routes, events, views)                                                                                                                                           | All cache. There are no closure routes, so `route:cache` works.                                                                                                                                                                                                                                                                                                                                                                    |
| `php artisan schedule:list`                                                                                                                                                                      | backup 23:00, model:prune, hourly daily checks, queue worker every minute, notification pruning                                                                                                                                                                                                                                                                                                                                    |
| PowerShell scripts                                                                                                                                                                               | Parse without errors (not run here: they change the system)                                                                                                                                                                                                                                                                                                                                                                        |
| **Real Apache test.** A second, temporary XAMPP Apache ran from a scratch config that includes the real `barangay-health.conf` (ports changed, localhost only), PHP 8.5 module, test certificate | `httpd -t` OK. HTTP → HTTPS 301 (port stripped). TLS 1.1 refused. `Server: Apache` with no version. No `X-Powered-By`. Secure, HttpOnly cookies. `/.env`, `/composer.json`, `/artisan`, `/storage/...`, `/phpmyadmin`, `/build/` not served. Assets cached for a year. Login as the Midwife; dashboard, residents, map and reports 200 (0.4–0.9 s with caches). Family summary and report PDFs, XLSX export OK, even without `gd`. |

The test Apache, its certificate and its config lived in the session scratchpad. It was stopped afterwards; your XAMPP
configuration, services and scheduled tasks were not changed.
