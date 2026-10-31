<#
.SYNOPSIS
    Registers the Windows Task Scheduler task that drives Laravel's scheduler.

.DESCRIPTION
    Run once on the server, in an Administrator PowerShell. The task runs
    `php artisan schedule:run` every minute as SYSTEM, whether or not anyone
    is logged on. That one task covers everything in routes/console.php:
    the nightly backup, the daily health checks (outbreaks, medicine expiry,
    overdue care, missed appointments, program reminders), the queue worker
    for notifications, and cleanup. Running it again replaces the task.

.EXAMPLE
    .\register-scheduler.ps1 -AppPath C:\BarangayHealth\barangayHall
#>
param(
    [string] $AppPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [string] $Php = 'C:\php-8.5.6\php.exe',
    [string] $TaskName = 'Barangay Health Scheduler'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $Php)) { throw "PHP not found at $Php (use -Php)." }
if (-not (Test-Path (Join-Path $AppPath 'artisan'))) { throw "No Laravel app at $AppPath (use -AppPath)." }

$action = New-ScheduledTaskAction -Execute $Php -Argument 'artisan schedule:run' -WorkingDirectory $AppPath
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable `
    -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10)

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description 'Runs php artisan schedule:run every minute (backups, health alerts, notification queue).' -Force | Out-Null

Write-Host "Task '$TaskName' registered: $Php artisan schedule:run in $AppPath, every minute." -ForegroundColor Green
Write-Host 'Check it in a few minutes with:  Get-ScheduledTaskInfo -TaskName ''Barangay Health Scheduler'''
