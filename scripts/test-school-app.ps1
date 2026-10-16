$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot
$envPath = Join-Path $projectRoot '.env.school'

if (-not (Test-Path $envPath)) { throw 'Copy .env.example to .env.school or run the school installer first.' }
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) { throw 'Docker Desktop is unavailable.' }

docker compose --env-file $envPath --profile testing config --quiet
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose test configuration is invalid. Check .env.school and docker-compose.yml.' }

docker compose --env-file $envPath --profile testing run --build --rm test
$testExitCode = $LASTEXITCODE

docker compose --env-file $envPath --profile testing rm --stop -f test-db | Out-Null
exit $testExitCode
