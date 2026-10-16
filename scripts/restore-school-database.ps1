param(
    [Parameter(Mandatory = $true)]
    [string] $BackupPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$envPath = Join-Path $projectRoot '.env.school'
if (-not (Test-Path $envPath)) { throw 'File .env.school tidak ditemukan. Server sekolah belum dipasang.' }
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop tidak tersedia.' }

$resolvedBackup = (Resolve-Path -LiteralPath $BackupPath -ErrorAction Stop).Path
$backupExtension = [IO.Path]::GetExtension($resolvedBackup).ToLowerInvariant()
$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$workDirectory = Join-Path $tempRoot "buku-induk-restore-$([Guid]::NewGuid().ToString('N'))"
$databasePath = $resolvedBackup
$documentsDirectory = $null
$isBundle = $backupExtension -eq '.zip'
$containerBackup = "/tmp/buku-induk-restore-$([Guid]::NewGuid().ToString('N')).dump"
$restoreId = [Guid]::NewGuid().ToString('N')
$stagingName = "restore-documents-$restoreId"
$previousName = "private-before-restore-$restoreId"
$restoreStarted = $false
$restoreSucceeded = $false
$webStopped = $false
$stagingCreated = $false
$databaseContainer = ''
$webContainer = ''
$compose = @('compose', '--env-file', $envPath)

function Expand-BackupBundle([string] $ArchivePath, [string] $TargetDirectory) {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    New-Item -ItemType Directory -Path $TargetDirectory -Force | Out-Null
    $root = [IO.Path]::GetFullPath($TargetDirectory).TrimEnd([IO.Path]::DirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
    $seenEntries = [Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
    $archive = [IO.Compression.ZipFile]::OpenRead($ArchivePath)
    try {
        foreach ($entry in $archive.Entries) {
            $relativePath = $entry.FullName.Replace([char] 92, [char] 47)
            if (-not $relativePath -or $relativePath.StartsWith('/') -or $relativePath.Contains(':') -or
                $relativePath -match '(^|/)\.\.?(/|$)') {
                throw 'Backup contains an unsafe file path.'
            }
            if (-not $seenEntries.Add($relativePath)) { throw 'Backup contains duplicate file paths.' }
            if ($relativePath -notin @('database.dump', 'manifest.json') -and
                -not $relativePath.StartsWith('student-documents/', [StringComparison]::Ordinal)) {
                throw "Backup contains an unexpected entry: $relativePath"
            }

            $destination = [IO.Path]::GetFullPath((Join-Path $TargetDirectory ($relativePath.Replace('/', [IO.Path]::DirectorySeparatorChar))))
            if (-not $destination.StartsWith($root, [StringComparison]::OrdinalIgnoreCase)) {
                throw 'Backup entry escapes the extraction folder.'
            }
            if ($relativePath.EndsWith('/')) {
                New-Item -ItemType Directory -Path $destination -Force | Out-Null
                continue
            }

            $parent = Split-Path -Parent $destination
            New-Item -ItemType Directory -Path $parent -Force | Out-Null
            $inputStream = $null
            $outputStream = $null
            try {
                $inputStream = $entry.Open()
                $outputStream = [IO.File]::Open($destination, [IO.FileMode]::CreateNew, [IO.FileAccess]::Write, [IO.FileShare]::None)
                $inputStream.CopyTo($outputStream)
            } finally {
                if ($outputStream) { $outputStream.Dispose() }
                if ($inputStream) { $inputStream.Dispose() }
            }
        }
    } finally {
        $archive.Dispose()
    }
}

if ($isBundle) {
    $sidecarPath = "$resolvedBackup.sha256"
    if (Test-Path -LiteralPath $sidecarPath) {
        $expectedHash = (Get-Content -LiteralPath $sidecarPath -Raw).Trim().ToLowerInvariant()
        $actualHash = (Get-FileHash -LiteralPath $resolvedBackup -Algorithm SHA256).Hash.ToLowerInvariant()
        if ($expectedHash -ne $actualHash) { throw 'SHA-256 verification failed for the backup bundle.' }
    } else {
        Write-Warning 'No SHA-256 sidecar found; the backup will be validated during extraction and restore.'
    }

    try {
        Expand-BackupBundle -ArchivePath $resolvedBackup -TargetDirectory $workDirectory
        $databasePath = Join-Path $workDirectory 'database.dump'
        $documentsDirectory = Join-Path $workDirectory 'student-documents'
        $manifestPath = Join-Path $workDirectory 'manifest.json'
        if (-not (Test-Path -LiteralPath $databasePath) -or (Get-Item -LiteralPath $databasePath).Length -eq 0) {
            throw 'Backup bundle does not contain a valid database dump.'
        }
        if (-not (Test-Path -LiteralPath $manifestPath)) { throw 'Backup bundle manifest is missing.' }
        $manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
        if ($manifest.application -ne 'Buku Induk SMK' -or $manifest.format_version -ne 1) {
            throw 'Backup bundle format is not supported.'
        }
        if (-not (Test-Path -LiteralPath $documentsDirectory)) {
            New-Item -ItemType Directory -Path $documentsDirectory -Force | Out-Null
        }
    } catch {
        if ((Test-Path -LiteralPath $workDirectory) -and
            (Split-Path -Leaf $workDirectory) -like 'buku-induk-restore-*') {
            Remove-Item -LiteralPath $workDirectory -Recurse -Force
        }
        throw
    }
} elseif ($backupExtension -ne '.dump') {
    throw 'Choose a complete .zip backup or a legacy PostgreSQL .dump file.'
} elseif ((Get-Item -LiteralPath $resolvedBackup).Length -eq 0) {
    throw 'Backup file is empty.'
} else {
    Write-Warning 'This legacy .dump contains only database data; private student documents are not included.'
}

$confirmationMessage = if ($isBundle) {
    'Pemulihan akan mengganti database dan dokumen privat siswa saat ini. Ketik RESTORE untuk melanjutkan'
} else {
    'Pemulihan akan mengganti data database saat ini. Ketik RESTORE untuk melanjutkan'
}
$confirmation = Read-Host $confirmationMessage
if ($confirmation -cne 'RESTORE') {
    if ($isBundle -and (Test-Path -LiteralPath $workDirectory) -and
        (Split-Path -Leaf $workDirectory) -like 'buku-induk-restore-*') {
        Remove-Item -LiteralPath $workDirectory -Recurse -Force
    }
    throw 'Pemulihan dibatalkan.'
}

try {
    $databaseContainer = (docker @compose ps -q db).Trim()
    if ($LASTEXITCODE -ne 0 -or -not $databaseContainer) { throw 'Container database sekolah tidak sedang berjalan.' }
    $webContainer = (docker @compose ps -q web).Trim()
    $webWasRunning = $LASTEXITCODE -eq 0 -and -not [string]::IsNullOrWhiteSpace($webContainer)
    if ($isBundle -and -not $webWasRunning) { throw 'Complete bundle restore requires the school web container to be running.' }

    docker cp $databasePath "${databaseContainer}:$containerBackup"
    if ($LASTEXITCODE -ne 0) { throw 'File backup tidak dapat disalin ke container database.' }
    $listCommand = 'pg_restore --list "{0}"' -f $containerBackup
    docker @compose exec -T db sh -lc $listCommand | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Database dump validation failed; the active database was not changed.' }

    if ($webWasRunning) {
        docker @compose stop web | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Aplikasi web tidak dapat dihentikan sementara.' }
        $webStopped = $true
    }

    if ($isBundle) {
        $prepareStage = "mkdir -p /var/www/html/storage/app/$stagingName"
        docker @compose run --rm --no-deps -T web sh -lc $prepareStage | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Could not prepare temporary document restore storage.' }
        $stagingCreated = $true

        docker cp "$documentsDirectory\." "${webContainer}:/var/www/html/storage/app/$stagingName"
        if ($LASTEXITCODE -ne 0) { throw 'Student documents could not be copied into restore staging.' }
    }

    $restoreCommand = 'pg_restore --clean --if-exists --no-owner --exit-on-error -U "$POSTGRES_USER" -d "$POSTGRES_DB" "{0}"' -f $containerBackup
    $restoreStarted = $true
    docker @compose exec -T db sh -lc $restoreCommand
    if ($LASTEXITCODE -ne 0) { throw 'Database restore failed. Check logs before starting the application.' }

    if ($isBundle) {
        $swapCommand = 'set -eu; mkdir -p storage/app/private; old=storage/app/{0}; staged=storage/app/{1}; chown -R www-data:www-data "$staged"; mv storage/app/private "$old"; if mv "$staged" storage/app/private; then rm -rf "$old"; else mv "$old" storage/app/private; exit 1; fi' -f $previousName, $stagingName
        docker @compose run --rm --no-deps -T web sh -lc $swapCommand
        if ($LASTEXITCODE -ne 0) { throw 'Database restored, but student documents were not replaced; the web application remains stopped.' }
        $stagingCreated = $false
    }

    $restoreSucceeded = $true
    Write-Host 'Backup restoration completed.'
} finally {
    if ($databaseContainer) { docker @compose exec -T db rm -f $containerBackup 2>$null | Out-Null }

    if ($stagingCreated -and $webWasRunning) {
        $cleanupStage = "rm -rf /var/www/html/storage/app/$stagingName"
        docker @compose run --rm --no-deps -T web sh -lc $cleanupStage 2>$null | Out-Null
    }

    if ($webWasRunning -and ($restoreSucceeded -or -not $restoreStarted)) {
        docker @compose start web | Out-Null
        if ($LASTEXITCODE -ne 0) { Write-Warning 'Aplikasi web belum menyala kembali. Jalankan docker compose --env-file .env.school up -d web.' }
    } elseif ($webWasRunning) {
        Write-Warning 'Aplikasi web dibiarkan berhenti karena proses pemulihan tidak selesai.'
    }

    if ($workDirectory -and (Test-Path -LiteralPath $workDirectory) -and
        (Split-Path -Leaf $workDirectory) -like 'buku-induk-restore-*') {
        Remove-Item -LiteralPath $workDirectory -Recurse -Force
    }
}
