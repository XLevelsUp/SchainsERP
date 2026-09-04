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
    $dockerExe = "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    if (-not (Test-Path $dockerExe)) {
        $dockerExe = "$env:LOCALAPPDATA\Programs\DockerDesktop\Docker Desktop.exe"
    }
    Start-Process $dockerExe

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
# Native commands are run through this helper rather than called directly.
# With $ErrorActionPreference = 'Stop', PowerShell 5.1 turns ANY stderr line
# from a native exe into a terminating NativeCommandError - so a harmless
# docker warning used to kill this script before it ever reached the backend
# and frontend below. Here stderr is captured as text and only a non-zero
# exit code counts as failure.
function Invoke-Native {
    param([string]$Exe, [string[]]$Arguments)
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & $Exe @Arguments 2>&1 | Out-String
        return [pscustomobject]@{ ExitCode = $LASTEXITCODE; Output = $output.Trim() }
    } finally {
        $ErrorActionPreference = $previous
    }
}

# The container carries `--restart unless-stopped`, so Docker Desktop starts
# it automatically. Calling `docker start` on a container that is already
# coming up races its port bind and fails with "ports are not available:
# ... Only one usage of each socket address" - which is not a real conflict.
# Check the state first and only start it when it is genuinely stopped.
$running = (Invoke-Native docker @('inspect', '-f', '{{.State.Running}}', 'schainserp-postgres')).Output

if ($running -eq 'true') {
    Write-Host "Postgres (schainserp-postgres) was already running." -ForegroundColor Green
} else {
    Write-Host "Starting Postgres (schainserp-postgres)..." -ForegroundColor Yellow
    $start = Invoke-Native docker @('start', 'schainserp-postgres')
    if ($start.ExitCode -ne 0) {
        Write-Host "Could not start the Postgres container:" -ForegroundColor Red
        Write-Host $start.Output -ForegroundColor Red
        exit 1
    }
}

# Keep it surviving future Docker restarts. Idempotent.
Invoke-Native docker @('update', '--restart', 'unless-stopped', 'schainserp-postgres') | Out-Null

# Being "running" is not the same as accepting connections - Laravel will
# fail its first query if we race it.
$pgReady = $false
$deadline = (Get-Date).AddSeconds(60)
while (-not $pgReady -and (Get-Date) -lt $deadline) {
    $probe = Invoke-Native docker @('exec', 'schainserp-postgres', 'pg_isready', '-U', 'postgres')
    if ($probe.ExitCode -eq 0) { $pgReady = $true } else { Start-Sleep -Seconds 2 }
}

if (-not $pgReady) {
    Write-Host "Postgres did not start accepting connections in time." -ForegroundColor Red
    exit 1
}
Write-Host "Postgres is accepting connections." -ForegroundColor Green

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
