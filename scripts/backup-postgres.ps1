param(
    [string] $BackupDirectory = (Join-Path (Split-Path -Parent $PSScriptRoot) 'backups')
)

$ErrorActionPreference = 'Stop'
if ($PSVersionTable.PSVersion.Major -lt 7) {
    throw 'Run this script with PowerShell 7 or later so pg_dump output is written as binary data.'
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$resolvedBackupDirectory = [System.IO.Path]::GetFullPath($BackupDirectory)
New-Item -ItemType Directory -Force -Path $resolvedBackupDirectory | Out-Null

$identity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls $resolvedBackupDirectory /inheritance:r /grant:r "${identity}:(OI)(CI)F" 'NT AUTHORITY\SYSTEM:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Could not restrict access to the backup directory.'
}

$stamp = (Get-Date).ToUniversalTime().ToString('yyyyMMdd-HHmmss')
$fileName = "lumenix-$stamp.dump"
$backupPath = Join-Path $resolvedBackupDirectory $fileName
$containerPath = "/tmp/$fileName"

Push-Location $projectRoot
try {
    & docker compose exec -T postgres sh -c 'pg_dump -Fc -U "$POSTGRES_USER" "$POSTGRES_DB"' > $backupPath
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $backupPath) -or (Get-Item -LiteralPath $backupPath).Length -eq 0) {
        Remove-Item -LiteralPath $backupPath -Force -ErrorAction SilentlyContinue
        throw 'PostgreSQL backup failed; no usable archive was kept.'
    }

    & docker compose cp $backupPath "postgres:$containerPath"
    if ($LASTEXITCODE -ne 0) {
        throw 'Could not copy the backup into the PostgreSQL container for archive validation.'
    }
    & docker compose exec -T postgres pg_restore --list $containerPath | Out-Null
    $validationExitCode = $LASTEXITCODE
    & docker compose exec -T postgres rm -f $containerPath | Out-Null
    if ($validationExitCode -ne 0) {
        Remove-Item -LiteralPath $backupPath -Force -ErrorAction SilentlyContinue
        throw 'The backup archive failed pg_restore validation and was removed.'
    }

    Write-Output "Validated PostgreSQL backup: $backupPath"
    Write-Warning 'Encrypt this archive with your approved key management before copying it off this host. Back up APP_KEY separately.'
} finally {
    Pop-Location
}
