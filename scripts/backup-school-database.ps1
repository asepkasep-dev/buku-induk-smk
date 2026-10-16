param(
    [string] $Destination = 'backups'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$envPath = Join-Path $projectRoot '.env.school'
if (-not (Test-Path $envPath)) { throw 'The server has not been installed yet (.env.school is missing).' }
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop is unavailable.' }

$destinationPath = if ([System.IO.Path]::IsPathRooted($Destination)) {
    [System.IO.Path]::GetFullPath($Destination)
} else {
    [System.IO.Path]::GetFullPath((Join-Path $projectRoot $Destination))
}
New-Item -ItemType Directory -Path $destinationPath -Force | Out-Null

$timestamp = "$(Get-Date -Format 'yyyyMMdd-HHmmss')-$([Guid]::NewGuid().ToString('N').Substring(0, 8))"
$containerPath = "/tmp/buku-induk-$timestamp.dump"
$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$workDirectory = Join-Path $tempRoot "buku-induk-backup-$([Guid]::NewGuid().ToString('N'))"
$documentsDirectory = Join-Path $workDirectory 'student-documents'
$databasePath = Join-Path $workDirectory 'database.dump'
$manifestPath = Join-Path $workDirectory 'manifest.json'
$outputPath = Join-Path $destinationPath "BukuIndukSMK-$timestamp.zip"
$hashPath = "$outputPath.sha256"
$webStopped = $false
$databaseContainer = ''
$webContainer = ''
$backupLock = $null
$lockPath = Join-Path $destinationPath '.BukuIndukSMK.backup.lock'

if ((Test-Path -LiteralPath $outputPath) -or (Test-Path -LiteralPath $hashPath)) {
    throw "Backup already exists: $outputPath"
}

New-Item -ItemType Directory -Path $documentsDirectory -Force | Out-Null
Add-Type -AssemblyName System.IO.Compression.FileSystem

try {
    try {
        $backupLock = [IO.File]::Open($lockPath, [IO.FileMode]::OpenOrCreate, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
    } catch [IO.IOException] {
        throw 'Another backup is already running for this destination.'
    }

    $databaseContainer = (docker compose --env-file $envPath ps -q db).Trim()
    if ($LASTEXITCODE -ne 0 -or -not $databaseContainer) { throw 'The school database container is not running.' }

    $webContainer = (docker compose --env-file $envPath ps -q web).Trim()
    if ($LASTEXITCODE -ne 0 -or -not $webContainer) { throw 'The school web container is not running.' }

    docker compose --env-file $envPath stop web | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Could not stop the application for a consistent backup.' }
    $webStopped = $true

    $dumpCommand = 'pg_dump --format=custom --no-owner -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f "{0}"' -f $containerPath
    docker compose --env-file $envPath exec -T db sh -lc $dumpCommand
    if ($LASTEXITCODE -ne 0) { throw 'Database backup failed.' }

    docker cp "${databaseContainer}:$containerPath" $databasePath
    if ($LASTEXITCODE -ne 0) { throw 'Could not copy the database backup to this PC.' }

    docker cp "${webContainer}:/var/www/html/storage/app/private/." $documentsDirectory
    if ($LASTEXITCODE -ne 0) { throw 'Could not copy the private student documents to this PC.' }

    $manifest = [ordered] @{
        application = 'Buku Induk SMK'
        format_version = 1
        created_at = (Get-Date).ToString('o')
        contents = @('database.dump', 'student-documents/')
    }
    [IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json -Depth 4), [Text.UTF8Encoding]::new($false))

    [IO.Compression.ZipFile]::CreateFromDirectory(
        $workDirectory,
        $outputPath,
        [IO.Compression.CompressionLevel]::Optimal,
        $false
    )
    $hash = (Get-FileHash -LiteralPath $outputPath -Algorithm SHA256).Hash.ToLowerInvariant()
    [IO.File]::WriteAllText($hashPath, "$hash`n", [Text.UTF8Encoding]::new($false))
    Write-Host "Complete application backup saved to $outputPath"
    Write-Host "SHA-256: $hash"
} catch {
    if (Test-Path -LiteralPath $outputPath) {
        Remove-Item -LiteralPath $outputPath -Force
    }
    if (Test-Path -LiteralPath $hashPath) { Remove-Item -LiteralPath $hashPath -Force }
    throw
} finally {
    if ($databaseContainer) {
        docker compose --env-file $envPath exec -T db rm -f $containerPath 2>$null | Out-Null
    }

    if ($webStopped) {
        docker compose --env-file $envPath start web | Out-Null
        if ($LASTEXITCODE -ne 0) {
            Write-Warning 'Could not restart the application. Run docker compose --env-file .env.school up -d web.'
        }
    }

    if ($backupLock) { $backupLock.Dispose() }

    if ($workDirectory.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -and
        (Split-Path -Leaf $workDirectory) -like 'buku-induk-backup-*' -and
        (Test-Path -LiteralPath $workDirectory)) {
        Remove-Item -LiteralPath $workDirectory -Recurse -Force
    }
}
