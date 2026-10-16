$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker Desktop is not installed. Install and start Docker Desktop, then run this script again.'
}

docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose is unavailable. Update Docker Desktop and try again.'
}
docker info | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Desktop is not running. Start Docker Desktop and retry.'
}
$dockerOs = docker info --format '{{.OSType}}'
if ($LASTEXITCODE -ne 0 -or $dockerOs.Trim() -ne 'linux') {
    throw 'Docker is not using Linux containers. Switch Docker Desktop to Linux containers and retry.'
}

function ConvertTo-IPv4Range([string] $Cidr) {
    $parts = $Cidr.Split('/')
    if ($parts.Count -ne 2) { throw "Invalid network: $Cidr" }
    $octets = $parts[0].Split('.') | ForEach-Object { [long] $_ }
    if ($octets.Count -ne 4 -or $octets.Where({ $_ -lt 0 -or $_ -gt 255 }).Count -gt 0) {
        throw "Invalid IPv4 network: $Cidr"
    }
    $prefix = [int] $parts[1]
    if ($prefix -lt 0 -or $prefix -gt 32) { throw "Invalid network prefix: $Cidr" }
    $address = $octets[0] * 16777216 + $octets[1] * 65536 + $octets[2] * 256 + $octets[3]
    $blockSize = [long] [Math]::Pow(2, 32 - $prefix)
    $networkStart = [long] ([Math]::Floor($address / $blockSize) * $blockSize)
    return [pscustomobject] @{ Start = $networkStart; End = ($networkStart + $blockSize - 1) }
}

function Test-IPv4RangeOverlap([string] $FirstCidr, [string] $SecondCidr) {
    $firstRange = ConvertTo-IPv4Range $FirstCidr
    $secondRange = ConvertTo-IPv4Range $SecondCidr
    return $firstRange.Start -le $secondRange.End -and $secondRange.Start -le $firstRange.End
}

$envPath = Join-Path $projectRoot '.env.school'
$firstInstall = -not (Test-Path $envPath)
if ($firstInstall) {
    $content = [System.IO.File]::ReadAllText((Join-Path $projectRoot '.env.example'))

    $randomBytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $generator.GetBytes($randomBytes)
    $databasePassword = [System.BitConverter]::ToString($randomBytes).Replace('-', '').ToLowerInvariant()
    $generator.GetBytes($randomBytes)
    $appKey = 'base64:' + [System.Convert]::ToBase64String($randomBytes)
    $generator.Dispose()

    $addresses = @(
        [Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces() |
            Where-Object {
                $_.OperationalStatus -eq [Net.NetworkInformation.OperationalStatus]::Up -and
                $_.NetworkInterfaceType -in @([Net.NetworkInformation.NetworkInterfaceType]::Ethernet, [Net.NetworkInformation.NetworkInterfaceType]::Wireless80211) -and
                $_.Name -notmatch 'Loopback|Bluetooth|vEthernet|Docker|WSL|Hyper-V|VMware|VirtualBox' -and
                $_.Description -notmatch 'Loopback|Bluetooth|vEthernet|Docker|WSL|Hyper-V|VMware|VirtualBox'
            } |
            ForEach-Object { $_.GetIPProperties().UnicastAddresses } |
            Where-Object {
                $_.Address.AddressFamily -eq [Net.Sockets.AddressFamily]::InterNetwork -and
                $_.Address.IPAddressToString -notmatch '^(127\.|169\.254\.)'
            } |
            ForEach-Object { $_.Address.IPAddressToString } |
            Select-Object -Unique
    )
    $defaultAddress = $addresses | Select-Object -First 1

    if (-not $defaultAddress) {
        throw 'No school-network IPv4 address was found. Connect this PC to the school network and retry.'
    }

    Write-Host "Detected IPv4 addresses: $($addresses -join ', ')"
    $serverAddress = Read-Host "Enter the PC server's school-network IPv4 address (press Enter for $defaultAddress)"
    if (-not $serverAddress) { $serverAddress = $defaultAddress }
    if ($serverAddress -notin $addresses) { throw 'Choose one of the IPv4 addresses detected on this PC.' }

    $occupiedNetworks = @(Get-NetRoute -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.DestinationPrefix -and $_.DestinationPrefix -notmatch '^0\.0\.0\.0/0$' } |
        Select-Object -ExpandProperty DestinationPrefix)
    $networkIds = @(docker network ls -q)
    if ($LASTEXITCODE -eq 0 -and $networkIds.Count -gt 0) {
        $existingNetworks = docker network inspect $networkIds | ConvertFrom-Json
        foreach ($network in $existingNetworks) {
            foreach ($ipam in $network.IPAM.Config) {
                if ($ipam.Subnet) { $occupiedNetworks += $ipam.Subnet }
            }
        }
    }
    $dockerSubnet = @('172.29.240.0/24', '172.30.240.0/24', '172.28.240.0/24', '172.27.240.0/24', '10.240.0.0/24', '192.168.240.0/24') |
        Where-Object {
            $candidate = $_
            -not ($occupiedNetworks | Where-Object { Test-IPv4RangeOverlap $candidate $_ } | Select-Object -First 1)
        } | Select-Object -First 1
    if (-not $dockerSubnet) {
        throw 'Could not find a Docker subnet that avoids this PC and its current Docker networks. Set a free DOCKER_SUBNET in .env.school.'
    }
    if (Test-IPv4RangeOverlap "$serverAddress/32" $dockerSubnet) {
        throw 'The selected school-network address conflicts with the internal Docker network.'
    }
    $proxyAddress = ($dockerSubnet.Split('/')[0] -replace '\.\d+$', '.254')
    $dockerIpRange = "$($dockerSubnet.Split('/')[0])/25"

    $replacements = @{
        'APP_ENV' = 'production'
        'APP_DEBUG' = 'false'
        'APP_KEY' = $appKey
        'APP_URL' = "https://${serverAddress}:8443"
        'APP_HOST' = $serverAddress
        'APP_TLS_PORT' = '8443'
        'APP_HTTP_PORT' = '8080'
        'DOCKER_SUBNET' = $dockerSubnet
        'DOCKER_IP_RANGE' = $dockerIpRange
        'PROXY_INTERNAL_IP' = $proxyAddress
        'DB_CONNECTION' = 'pgsql'
        'DB_HOST' = 'db'
        'DB_PORT' = '5432'
        'DB_DATABASE' = 'buku_induk_smk'
        'DB_USERNAME' = 'buku_induk_app'
        'DB_PASSWORD' = $databasePassword
        'TRUSTED_PROXIES' = $proxyAddress
        'SESSION_ENCRYPT' = 'true'
        'SESSION_SECURE_COOKIE' = 'true'
    }

    foreach ($entry in $replacements.GetEnumerator()) {
        $pattern = '(?m)^' + [System.Text.RegularExpressions.Regex]::Escape($entry.Key) + '=.*$'
        $replacement = $entry.Key + '=' + $entry.Value
        $content = [System.Text.RegularExpressions.Regex]::Replace($content, $pattern, $replacement)
    }

    [System.IO.File]::WriteAllText($envPath, $content, [System.Text.UTF8Encoding]::new($false))
}

$settings = @{}
foreach ($line in [System.IO.File]::ReadAllLines($envPath)) {
    if ($line -match '^([A-Z_]+)=(.*)$') {
        $settings[$matches[1]] = $matches[2].Trim('"')
    }
}

foreach ($required in @('APP_KEY', 'APP_HOST', 'APP_URL', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD')) {
    if (-not $settings[$required]) {
        throw "Set $required in .env.school before installing the school server."
    }
}

docker compose --env-file $envPath config --quiet
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose configuration is invalid. Check .env.school and docker-compose.yml.' }

docker compose --env-file $envPath build web
if ($LASTEXITCODE -ne 0) { throw 'The application image could not be built.' }

docker compose --env-file $envPath up -d --wait db
if ($LASTEXITCODE -ne 0) { throw 'The database did not become ready.' }

docker compose --env-file $envPath run --rm web php artisan migrate --seed --force
if ($LASTEXITCODE -ne 0) { throw 'Database setup failed.' }

if ($firstInstall) {
    $adminEmail = Read-Host 'Initial school administrator email'
    docker compose --env-file $envPath run --rm web php artisan school:user-add $adminEmail ADMIN
    if ($LASTEXITCODE -ne 0) {
        Write-Warning 'Administrator setup did not finish. Retry with: docker compose --env-file .env.school run --rm web php artisan school:user-add <email> ADMIN'
        throw 'The initial administrator account was not created.'
    }
}

docker compose --env-file $envPath up -d
if ($LASTEXITCODE -ne 0) { throw 'The school application could not be started.' }

New-Item -ItemType Directory -Path (Join-Path $projectRoot 'certs') -Force | Out-Null
$certificatePath = Join-Path $projectRoot 'certs\school-root-ca.crt'
$proxyContainer = (docker compose --env-file $envPath ps -q proxy).Trim()
$healthUrl = $settings['APP_URL'].TrimEnd('/') + '/up'
$healthStatus = '000'
for ($attempt = 0; $attempt -lt 10; $attempt++) {
    $healthStatus = (& curl.exe --insecure --silent --show-error --output NUL --write-out '%{http_code}' --max-time 3 $healthUrl 2>$null).Trim()
    if ($LASTEXITCODE -eq 0 -and $healthStatus -eq '200') { break }
    Start-Sleep -Seconds 2
}
if ($LASTEXITCODE -ne 0 -or $healthStatus -ne '200') {
    throw "The HTTPS health check did not return HTTP 200 (received $healthStatus). Check Docker logs before retrying."
}
docker cp "${proxyContainer}:/data/caddy/pki/authorities/local/root.crt" $certificatePath
if ($LASTEXITCODE -ne 0) { throw 'Could not export the public HTTPS certificate.' }

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)
if ($principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    $firewallRule = Get-NetFirewallRule -Name 'BukuIndukSMKHTTPS8443' -ErrorAction SilentlyContinue
    if ($firewallRule) {
        Remove-NetFirewallRule -Name 'BukuIndukSMKHTTPS8443'
    }
    New-NetFirewallRule -Name 'BukuIndukSMKHTTPS8443' -DisplayName 'Buku Induk SMK HTTPS' `
        -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8443,8080 `
        -RemoteAddress LocalSubnet -Profile Domain,Private | Out-Null
} else {
    Write-Warning 'Run this PowerShell as Administrator to allow HTTPS from the school local network through Windows Firewall.'
}

Write-Host ''
Write-Host "School server started at $($settings['APP_URL'])"
Write-Host "Before opening the app on client PCs, copy $certificatePath to each PC and trust it as described in README.md."
Write-Host 'Keep this server PC powered on while staff use the application.'

