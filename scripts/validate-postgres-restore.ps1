param(
    [Parameter(Mandatory = $true)]
    [string] $BackupFile,
    [string] $PostgresImage = 'postgres:16-alpine'
)

$ErrorActionPreference = 'Stop'
$resolvedBackup = (Resolve-Path -LiteralPath $BackupFile).Path
$containerName = 'lumenix-restore-check-'+[guid]::NewGuid().ToString('N').Substring(0, 12)
$mount = "${resolvedBackup}:/tmp/lumenix.dump:ro"

& docker run --detach --rm --network none --name $containerName --env POSTGRES_HOST_AUTH_METHOD=trust --volume $mount $PostgresImage | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Could not start the isolated PostgreSQL restore-check container.'
}

try {
    $ready = $false
    for ($attempt = 0; $attempt -lt 45; $attempt++) {
        & docker exec $containerName pg_isready -U postgres | Out-Null
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) {
        throw 'The isolated PostgreSQL instance did not become ready.'
    }

    & docker exec $containerName pg_restore --no-owner --no-acl -U postgres -d postgres /tmp/lumenix.dump
    if ($LASTEXITCODE -ne 0) {
        throw 'PostgreSQL could not restore the backup into the isolated instance.'
    }

    $tableCount = (& docker exec $containerName psql -U postgres -d postgres -Atc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'").Trim()
    if ($LASTEXITCODE -ne 0 -or [int]$tableCount -lt 1) {
        throw 'The restored database did not contain application tables.'
    }

    Write-Output "Restore validation passed: $tableCount public tables were restored into an isolated temporary database."
} finally {
    & docker rm --force $containerName | Out-Null
}
