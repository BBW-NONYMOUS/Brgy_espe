<#
.SYNOPSIS
    Installs or updates the application code on the server.

.DESCRIPTION
    Run from the app folder after copying in a new version (and once during
    the first installation, after .env is ready). It puts the site in
    maintenance mode, installs PHP and JavaScript dependencies, builds the
    frontend, migrates the database, rebuilds Laravel's caches and brings the
    site back. A backup is taken before migrating. Stops at the first error;
    the site then stays in maintenance mode until the problem is fixed and
    the script is run again (or `php artisan up`).

.EXAMPLE
    .\deploy\windows\update.ps1
#>
param(
    [string] $Php = 'C:\php-8.5.6\php.exe',
    [string] $Composer = 'C:\ProgramData\ComposerSetup\bin\composer.phar'
)

$ErrorActionPreference = 'Stop'
$app = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location $app

function Step([string] $title, [scriptblock] $command) {
    Write-Host "`n==> $title" -ForegroundColor Cyan
    & $command
    if ($LASTEXITCODE -ne 0) { throw "Failed: $title (exit code $LASTEXITCODE)" }
}

if (-not (Test-Path .env)) { throw '.env is missing. Copy .env.production.example to .env first (docs/deployment.md section 6).' }
if (-not (Select-String -Path .env -Pattern '^APP_KEY=base64:' -Quiet)) { throw 'APP_KEY is empty. Run php artisan key:generate once (docs/deployment.md section 6).' }

$firstInstall = -not (Test-Path vendor)

if (-not $firstInstall) {
    Step 'Maintenance mode on' { & $Php artisan down --retry=60 }
}

Step 'PHP dependencies' { & $Php $Composer install --no-dev --optimize-autoloader --no-interaction }
Step 'JavaScript dependencies' { pnpm install --frozen-lockfile }
Step 'Build the frontend' { pnpm run build }

if (-not $firstInstall) {
    Step 'Backup before migrating' { & $Php artisan backup:run }
}

Step 'Database migrations' { & $Php artisan migrate --force }
Step 'Reference data (roles, lookups)' { & $Php artisan db:seed --class=ProductionSeeder --force }
Step 'Laravel caches' { & $Php artisan optimize }
Step 'Restart the queue worker' { & $Php artisan queue:restart }
Step 'Maintenance mode off' { & $Php artisan up }

Write-Host "`nDone. Open the site and log in to check." -ForegroundColor Green
