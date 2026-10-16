$ErrorActionPreference = 'SilentlyContinue'

$checks = [System.Collections.Generic.List[object]]::new()

function Add-Check([string] $Status, [string] $Item, [string] $Detail) {
    $checks.Add([pscustomobject]@{ Status = $Status; Item = $Item; Detail = $Detail })
}

$os = Get-CimInstance Win32_OperatingSystem
$is64Bit = [Environment]::Is64BitOperatingSystem
if ($os) {
    $osName = $os.Caption
    $architecture = $os.OSArchitecture
} else {
    $windowsRegistry = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion'
    $osName = $windowsRegistry.ProductName
    if ($windowsRegistry.CurrentBuild -and [int] $windowsRegistry.CurrentBuild -ge 22000) {
        $osName = if ($windowsRegistry.DisplayVersion) { "Windows 11 $($windowsRegistry.DisplayVersion)" } else { 'Windows 11' }
    }
    $architecture = if ($is64Bit) { '64-bit' } else { '32-bit' }
}
if ($osName -match 'Windows 11' -and $is64Bit) {
    Add-Check 'PASS' 'Windows' "$osName, $architecture"
} elseif ($osName -match 'Windows 10' -and $is64Bit) {
    Add-Check 'WARN' 'Windows' "$osName, $architecture. Gunakan hanya jika edisi ini masih menerima pembaruan keamanan (contoh: ESU atau LTSC); Windows 11 disarankan untuk server baru."
} elseif ($osName) {
    Add-Check 'FAIL' 'Windows' "Diperlukan Windows 11 64-bit atau edisi Windows 10 yang masih didukung; terdeteksi $osName, $architecture."
} else {
    Add-Check 'WARN' 'Windows' 'Informasi edisi Windows tidak dapat dibaca. Pastikan PC memakai Windows 11 64-bit atau edisi Windows 10 yang masih didukung.'
}

$processor = Get-CimInstance Win32_Processor | Select-Object -First 1
if ($processor.VirtualizationFirmwareEnabled -eq $true) {
    Add-Check 'PASS' 'Virtualisasi' 'Virtualisasi firmware aktif.'
} elseif ($processor.VirtualizationFirmwareEnabled -eq $false) {
    Add-Check 'FAIL' 'Virtualisasi' 'Virtualisasi firmware tidak aktif. Periksa pengaturan BIOS/UEFI dan kebijakan sekolah.'
} else {
    Add-Check 'WARN' 'Virtualisasi' 'Status tidak dapat dibaca. Pastikan virtualisasi aktif di BIOS/UEFI.'
}

$memoryKb = if ($os) { $os.TotalVisibleMemorySize } else { $null }
$memoryGb = if ($memoryKb) { [Math]::Round($memoryKb / 1MB, 1) } else { 0 }
if ($memoryGb -ge 8) {
    Add-Check 'PASS' 'RAM' "$memoryGb GB terdeteksi; memenuhi rekomendasi 8 GB."
} elseif ($memoryGb -gt 0) {
    Add-Check 'WARN' 'RAM' "$memoryGb GB terdeteksi; 8 GB atau lebih disarankan."
} else {
    Add-Check 'WARN' 'RAM' 'Kapasitas RAM tidak dapat dibaca; 8 GB atau lebih disarankan.'
}

$docker = Get-Command docker -ErrorAction SilentlyContinue
if (-not $docker) {
    Add-Check 'FAIL' 'Docker Desktop' 'Docker CLI tidak ditemukan; instalasi aplikasi server memerlukannya.'
} else {
    $composeVersion = docker compose version 2>&1 | Out-String
    if ($LASTEXITCODE -eq 0) {
        Add-Check 'PASS' 'Docker Compose' $composeVersion.Trim()
    } else {
        Add-Check 'FAIL' 'Docker Compose' 'Docker Compose v2 tidak tersedia.'
    }

    $null = docker info 2>&1
    if ($LASTEXITCODE -eq 0) {
        $dockerOs = docker info --format '{{.OSType}}' 2>&1 | Out-String
        if ($LASTEXITCODE -eq 0 -and $dockerOs.Trim() -eq 'linux') {
            Add-Check 'PASS' 'Docker engine' 'Docker berjalan dengan Linux containers dan dapat diakses oleh akun ini.'
        } elseif ($LASTEXITCODE -eq 0) {
            Add-Check 'FAIL' 'Docker engine' "Docker memakai mode $($dockerOs.Trim()); pilih Linux containers."
        } else {
            Add-Check 'WARN' 'Docker engine' 'Mode container Docker tidak dapat dibaca.'
        }
    } else {
        Add-Check 'FAIL' 'Docker engine' 'Docker tidak berjalan atau akun ini tidak diizinkan mengaksesnya.'
    }
}

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
if ($addresses.Count -gt 0) {
    Add-Check 'PASS' 'Jaringan sekolah' ($addresses -join ', ')
} else {
    Add-Check 'FAIL' 'Jaringan sekolah' 'Alamat IPv4 jaringan sekolah tidak ditemukan.'
}

$profiles = @(Get-NetConnectionProfile -ErrorAction SilentlyContinue | Select-Object -ExpandProperty NetworkCategory -Unique)
if ($profiles -contains 'Private' -or $profiles -contains 'DomainAuthenticated') {
    Add-Check 'PASS' 'Profil jaringan' ($profiles -join ', ')
} elseif ($profiles.Count -gt 0) {
    Add-Check 'WARN' 'Profil jaringan' "Profil saat ini: $($profiles -join ', '). Firewall pemasangan hanya membuka akses untuk profil Private/Domain."
} else {
    Add-Check 'WARN' 'Profil jaringan' 'Profil jaringan tidak dapat dibaca.'
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = [Security.Principal.WindowsPrincipal]::new($identity)
if ($principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Add-Check 'PASS' 'Administrator' 'PowerShell berjalan dengan hak Administrator.'
} else {
    Add-Check 'WARN' 'Administrator' 'Untuk membuat aturan firewall, jalankan pemasang sebagai Administrator atau minta bantuan administrator sekolah.'
}

$checks | Format-Table -AutoSize -Wrap
Write-Host ''
if ($checks.Status -contains 'FAIL') {
    Write-Host 'PC ini belum siap dipakai sebagai server aplikasi.' -ForegroundColor Red
    exit 1
}

Write-Host 'Prasyarat teknis dasar terdeteksi. Konfirmasikan izin TI sekolah, alamat IP tetap/DHCP reservation, dan PC dapat selalu menyala sebelum pemasangan.' -ForegroundColor Green


