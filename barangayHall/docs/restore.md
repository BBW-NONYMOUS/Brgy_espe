# Backup & Restore Procedure

Barangay Health Resident Profiling System — Barangay Health Center, Kalawag II, Isulan, Sultan Kudarat.
For the **Admin** and the barangay IT support person.

---

## 1. How backups work

|                       |                                                                                                                                                                                                                          |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **When**              | Every night at **11:00 PM** (scheduled), plus any time an Admin clicks **Backups → Back up now**.                                                                                                                        |
| **What**              | A full copy of the database (`mysqldump --single-transaction`), compressed (gzip) and **encrypted** with the system's `APP_KEY` (AES-256).                                                                               |
| **Where**             | `barangayHall\storage\app\private\backups\` — file names look like `backup-2026-10-30_230000-scheduled.sql.gz.enc`. Never inside `public\`.                                                                              |
| **How many are kept** | Last **14** nightly backups, one **Sunday** backup per week for **8** weeks, and the last **10** manual backups. Older files are deleted automatically; their history stays on the Backups page as "Removed (rotation)". |
| **Logged**            | Every backup, failed backup, download and rotation is written to the audit log.                                                                                                                                          |

> ⚠️ **The `APP_KEY` is required to restore.** It is the `APP_KEY=base64:…` line in `barangayHall\.env`.
> Keep a printed or USB copy of the `.env` file in a sealed envelope in the Barangay Hall safe,
> **separate from the backup files**. Without it, backups and encrypted fields (PhilHealth numbers,
> diagnoses, notes) cannot be recovered.

### 1.1 Turning on the nightly schedule (one time)

Laravel's scheduler must be called every minute by Windows Task Scheduler. In an **Administrator** PowerShell:

```powershell
cd C:\BarangayHealth\barangayHall
.\deploy\windows\register-scheduler.ps1
```

This registers the task **Barangay Health Scheduler** (`C:\php-8.5.6\php.exe artisan schedule:run`, every minute,
as SYSTEM). The same task also runs the daily health checks and the notification queue; see
[deployment.md §7](deployment.md#7-scheduler-and-queue).
Check it works the next morning: **Backups** page → a _Scheduled_ backup dated last night with status _Success_.

`.env` settings used (already set on the health-center PC):

```dotenv
BACKUP_MYSQLDUMP_PATH="C:/xampp/mysql/bin/mysqldump.exe"   # use forward slashes
BACKUP_TIME=23:00
```

### 1.2 Off-site copy (weekly, recommended)

Every Friday, on the **Backups** page click **Download** on the newest _Success_ backup and copy the
`.enc` file to the health center's USB drive kept at the Barangay Hall. The file is encrypted, so a
lost USB drive does not expose resident data — but it is useless without the `APP_KEY` (see above).

---

## 2. Restoring the database

Do this only when the database is lost or damaged, or when moving to a new computer.
**Restoring replaces all current data with the data in the backup.**

### Step 1 — Stop people from using the system

```powershell
cd C:\BarangayHealth\barangayHall
php artisan down
```

### Step 2 — Make sure the `.env` has the original `APP_KEY`

On a new computer, copy the saved `.env` file into `barangayHall\` first.

### Step 3 — Decrypt the backup into a `.sql` file

```powershell
php artisan backup:decrypt "D:\backup-2026-10-30_230000-scheduled.sql.gz.enc" --output="C:\restore\restore.sql"
```

If you see _"Could not decrypt the backup. Is APP_KEY the same…"_, the `.env` is not the one that made the backup.

### Step 4 — Import it into MySQL

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS barangay_health; CREATE DATABASE barangay_health CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cmd /c "C:\xampp\mysql\bin\mysql.exe -u root barangay_health < C:\restore\restore.sql"
```

### Step 5 — Check, then delete the plain `.sql` file

```powershell
C:\xampp\mysql\bin\mysql.exe -u root barangay_health -e "SELECT COUNT(*) AS residents FROM residents;"
Remove-Item C:\restore\restore.sql
```

> The `.sql` file contains **unencrypted** health information. Delete it as soon as the restore is verified.

### Step 6 — Bring the system back up

```powershell
php artisan migrate --force      # applies any newer database changes, if the app was updated
php artisan optimize:clear
php artisan up
```

Log in as Admin, open a few resident profiles, and check the **Audit logs** page.
Write in the health center logbook: date, who restored, which backup file, why.

---

## 3. Troubleshooting

| Problem                                                  | Fix                                                                                |
| -------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Backup status **Failed: … Access denied**                | DB username/password in `.env` are wrong.                                          |
| Backup status **Failed: … not recognized / cannot find** | `BACKUP_MYSQLDUMP_PATH` is wrong — check the file exists; use forward slashes.     |
| No nightly backup appears                                | Task Scheduler task is disabled or the PC was off at 11 PM. Click **Back up now**. |
| `The environment file is invalid`                        | A Windows path in `.env` uses backslashes inside quotes — change `\` to `/`.       |

_Restore procedure tested on 2026-10-30: backup → decrypt → import into a scratch database → 51 residents, 57 visits and all CHECK constraints present._
