<#
.SYNOPSIS
    Creates the HTTPS certificate for the health-center server with mkcert.

.DESCRIPTION
    Run once on the server, in an Administrator PowerShell. mkcert makes a
    small certificate authority (CA) for this health center, trusts it on this
    PC, and signs a certificate for the server's name and LAN address.
    Tablets and other PCs must trust the same CA: the script copies it to
    <OutDir>\barangay-health-ca.crt for that (docs/deployment.md §5).

    Install mkcert first:  winget install FiloSottile.mkcert

.EXAMPLE
    .\make-certificate.ps1 -HostName kalawag2-health.local -IpAddress 192.168.1.108
#>
param(
    [Parameter(Mandatory)] [string] $HostName,
    [Parameter(Mandatory)] [string] $IpAddress,
    [string] $OutDir = 'C:\xampp\apache\conf\barangay'
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command mkcert -ErrorAction SilentlyContinue)) {
    throw 'mkcert was not found. Install it with: winget install FiloSottile.mkcert (then open a new PowerShell).'
}

New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

# Trust the local CA on this PC (creates it the first time).
mkcert -install

mkcert -cert-file (Join-Path $OutDir 'server.pem') -key-file (Join-Path $OutDir 'server-key.pem') `
    $HostName $IpAddress localhost 127.0.0.1
if ($LASTEXITCODE -ne 0) { throw 'mkcert could not create the certificate.' }

# The CA certificate (public part only) for tablets and other PCs.
$caRoot = (mkcert -CAROOT).Trim()
Copy-Item (Join-Path $caRoot 'rootCA.pem') (Join-Path $OutDir 'barangay-health-ca.crt') -Force

Write-Host ''
Write-Host "Certificate for $HostName and $IpAddress saved in $OutDir" -ForegroundColor Green
Write-Host "Install $OutDir\barangay-health-ca.crt on each tablet and PC (docs/deployment.md section 5)."
Write-Host "NEVER copy rootCA-key.pem from $caRoot to another device." -ForegroundColor Yellow
