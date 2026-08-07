# Starts everything SchainsERP needs for local dev: Docker (Postgres),
# the Laravel backend, and the Vite frontend - each in its own window so
# they keep running independently of this script and of any other tool.
#
# Usage: right-click > Run with PowerShell, or from a terminal:
#   powershell -ExecutionPolicy Bypass -File start-dev.ps1

$ErrorActionPreference = 'Stop'
$repoRoot = $PSScriptRoot
$backendDir = Join-Path $repoRoot 'schainbackend'
$frontendDir = Join-Path $repoRoot 'frontend'

Write-Host "== SchainsERP dev startup ==" -ForegroundColor Cyan

# --- 1. Docker Desktop ---------------------------------------------------
$dockerReady = $false
try { docker info *> $null; $dockerReady = $true } catch { $dockerReady = $false }

if (-not $dockerReady) {
    Write-Host "Starting Docker Desktop..." -ForegroundColor Yellow
    Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"

    $deadline = (Get-Date).AddSeconds(180)
    while (-not $dockerReady -and (Get-Date) -lt $deadline) {
        Start-Sleep -Seconds 3
        try { docker info *> $null; $dockerReady = $true } catch { $dockerReady = $false }
    }
}

if (-not $dockerReady) {
    Write-Host "Docker Desktop did not become ready in time. Start it manually and re-run this script." -ForegroundColor Red
    exit 1
}
Write-Host "Docker is ready." -ForegroundColor Green

# --- 2. Postgres container -------------------------------------------------
docker start schainserp-postgres *> $null
# Make sure it survives future Docker restarts without needing `docker start` again.
docker update --restart unless-stopped schainserp-postgres *> $null
Write-Host "Postgres (schainserp-postgres) is up." -ForegroundColor Green

# --- 3. Clear any stale PHP dev servers from earlier crashed runs ---------
# A killed "php artisan serve" sometimes leaves its child `php -S ...` process
# alive, silently squatting on port 8000 and pushing new servers to 8001+.
Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
    Where-Object { $_.CommandLine -match 'artisan serve' -or $_.CommandLine -match 'resources/server\.php' } |
    ForEach-Object {
        Write-Host "Stopping stale php process (PID $($_.ProcessId))..." -ForegroundColor Yellow
        Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
    }
Start-Sleep -Milliseconds 500

# --- 4. Backend (Laravel) --------------------------------------------------
Write-Host "Starting backend (php artisan serve) in a new window..." -ForegroundColor Cyan
Start-Process powershell -ArgumentList @(
    '-NoExit', '-Command',
    "cd '$backendDir'; php artisan serve"
)

# --- 5. Frontend (Vite) -----------------------------------------------------
Write-Host "Starting frontend (npm run dev) in a new window..." -ForegroundColor Cyan
Start-Process powershell -ArgumentList @(
    '-NoExit', '-Command',
    "cd '$frontendDir'; npm run dev"
)

Write-Host ""
Write-Host "All set. Backend and frontend are running in their own windows -" -ForegroundColor Green
Write-Host "closing this window will not stop them. Close those windows (or Ctrl+C in them) to stop the servers." -ForegroundColor Green
Write-Host "Frontend: http://localhost:5173/"
Write-Host "Backend:  http://127.0.0.1:8000/"
