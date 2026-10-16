param(
    [string] $OutputDirectory
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
if (-not $OutputDirectory) {
    $OutputDirectory = Join-Path $projectRoot 'dist'
}
$outputDirectory = [IO.Path]::GetFullPath($OutputDirectory)
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$archivePath = Join-Path $outputDirectory "BukuIndukSMK-$timestamp.zip"
$hashPath = "$archivePath.sha256"

if ((Test-Path -LiteralPath $archivePath) -or (Test-Path -LiteralPath $hashPath)) {
    throw "Package already exists: $archivePath"
}

New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
Add-Type -AssemblyName System.IO.Compression

$archive = $null
$fileCount = 0

function Add-PackageFiles([string] $SourceDirectory, [string] $RelativeDirectory) {
    foreach ($item in Get-ChildItem -LiteralPath $SourceDirectory -Force) {
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            continue
        }

        $relativePath = if ($RelativeDirectory) {
            Join-Path $RelativeDirectory $item.Name
        } else {
            $item.Name
        }
        $portablePath = $relativePath.Replace('\', '/')

        if ($item.PSIsContainer) {
            if ($item.Name -in @('.git', '.aws', '.codex', '.composer', '.cursor', '.idea', '.nova', '.npm', '.pki', '.phpunit.cache', '.vscode', '.zed', 'node_modules', 'vendor', 'work', 'dist', 'backups', 'certs')) {
                continue
            }
            if ($portablePath -in @('public/build', 'public/storage')) {
                continue
            }

            Add-PackageFiles -SourceDirectory $item.FullName -RelativeDirectory $relativePath
            continue
        }

        if ($item.Name -in @('.env', 'auth.json', 'composer-auth.json', '.npmrc', '.pypirc', '.netrc', '.git-credentials', 'id_rsa', 'id_ed25519', '.phpunit.result.cache') -or ($item.Name -like '.env.*' -and $item.Name -ne '.env.example')) {
            continue
        }
        if ($portablePath.StartsWith('storage/', [StringComparison]::OrdinalIgnoreCase) -and $item.Name -ne '.gitignore') {
            continue
        }
        if ($portablePath.StartsWith('database/', [StringComparison]::OrdinalIgnoreCase) -and $item.Extension -in @('.sqlite', '.db', '.dump', '.backup')) {
            continue
        }
        if ($item.Extension -in @('.dump', '.backup', '.bak', '.key', '.pem', '.p8', '.crt', '.p12', '.pfx', '.jks', '.keystore', '.zip')) {
            continue
        }
        if ($item.Name -like '*.log' -or $item.Name -eq '.phpunit.result.cache') {
            continue
        }

        $entry = $archive.CreateEntry($portablePath, [IO.Compression.CompressionLevel]::Optimal)
        $sourceStream = $null
        $entryStream = $null
        try {
            $sourceStream = [IO.File]::OpenRead($item.FullName)
            $entryStream = $entry.Open()
            $sourceStream.CopyTo($entryStream)
        } finally {
            if ($entryStream) { $entryStream.Dispose() }
            if ($sourceStream) { $sourceStream.Dispose() }
        }

        $script:fileCount++
    }
}

try {
    $archiveStream = [IO.File]::Open($archivePath, [IO.FileMode]::CreateNew, [IO.FileAccess]::ReadWrite, [IO.FileShare]::None)
    try {
        $archive = [IO.Compression.ZipArchive]::new($archiveStream, [IO.Compression.ZipArchiveMode]::Create, $false)
        Add-PackageFiles -SourceDirectory $projectRoot -RelativeDirectory ''
    } finally {
        if ($archive) { $archive.Dispose() }
        $archiveStream.Dispose()
    }
} catch {
    if ($archive) { $archive.Dispose() }
    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force
    }
    throw
}

$package = Get-Item -LiteralPath $archivePath
$hash = (Get-FileHash -LiteralPath $archivePath -Algorithm SHA256).Hash.ToLowerInvariant()
[IO.File]::WriteAllText($hashPath, "$hash`n", [Text.UTF8Encoding]::new($false))
Write-Host "Deployment package created: $archivePath"
Write-Host "Files included: $fileCount; size: $([Math]::Round($package.Length / 1MB, 1)) MB"
Write-Host "SHA-256: $hash (also saved to $hashPath)"
Write-Host 'Secrets, dependencies, generated assets, caches, student documents, local certificates, and backups are excluded.'
