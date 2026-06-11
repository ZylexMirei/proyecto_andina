# =========================================
# Script para iniciar desarrollo completo (PowerShell)
# =========================================
# Este script inicia tanto el backend (PHP) como el frontend (Vite)

param(
    [switch]$UseBuiltinPHP = $false
)

$WAMP_PATH = "C:\wamp64"
$PROJECT_PATH = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Distribuidora Andina - INICIO COMPLETO" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Verificar si WAMP existe
$wampExists = Test-Path "$WAMP_PATH\wampmanager.exe"

if (-not $wampExists -and -not $UseBuiltinPHP) {
    Write-Host "[ADVERTENCIA] WAMP no encontrado en $WAMP_PATH" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Opciones:" -ForegroundColor Yellow
    Write-Host "1. Inicia WAMP manualmente y vuelve a ejecutar este script"
    Write-Host "2. Usa el servidor integrado de PHP (más lento, solo desarrollo)"
    Write-Host ""
    $choice = Read-Host "Elige opción (1 o 2, por defecto 1)"
    
    if ($choice -eq "2") {
        $UseBuiltinPHP = $true
    } else {
        Write-Host "Abre WAMP manualmente y vuelve a ejecutar este script." -ForegroundColor Yellow
        exit 1
    }
}

if (-not $UseBuiltinPHP) {
    Write-Host "[1/3] Iniciando WAMP (Apache + MySQL)..." -ForegroundColor Green
    Start-Process "$WAMP_PATH\wampmanager.exe"
    Start-Sleep -Seconds 3
    Write-Host "[2/3] Esperando a que Apache esté listo..." -ForegroundColor Green
    Start-Sleep -Seconds 5
} else {
    Write-Host "[1/2] Iniciando servidor PHP integrado en puerto 8080..." -ForegroundColor Green
    $phpPath = (Get-Command php -ErrorAction SilentlyContinue).Source
    if ($phpPath) {
        Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$PROJECT_PATH'; php -S localhost:8080"
        Start-Sleep -Seconds 2
    } else {
        Write-Host "[ERROR] PHP no encontrado en PATH" -ForegroundColor Red
        exit 1
    }
}

Write-Host "[3/3] Iniciando Frontend (Vite en puerto 3000)..." -ForegroundColor Green
$frontendPath = "$PROJECT_PATH\frontend\artifacts\andina-frontend"

if (-not (Test-Path "$frontendPath\package.json")) {
    Write-Host "[ERROR] package.json no encontrado en $frontendPath" -ForegroundColor Red
    exit 1
}

Push-Location $frontendPath

Write-Host ""
Write-Host "Verificando dependencias npm..." -ForegroundColor Cyan

if (-not (Test-Path "node_modules")) {
    Write-Host "Instalando dependencias..." -ForegroundColor Yellow
    npm install
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Inicio completado!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "El sistema estará listo en 5-10 segundos." -ForegroundColor Green
Write-Host ""

if (-not $UseBuiltinPHP) {
    Write-Host "[BACKEND]  Apache/WAMP en:    http://localhost/proyecto_andina/" -ForegroundColor Cyan
} else {
    Write-Host "[BACKEND]  PHP built-in en:   http://localhost:8080/" -ForegroundColor Cyan
}

Write-Host "[FRONTEND] Abre tu navegador: http://localhost:3000" -ForegroundColor Cyan
Write-Host "[API]      Proxy automático:  /test_api.php -> Backend" -ForegroundColor Cyan
Write-Host ""
Write-Host "Presiona Ctrl+C para detener." -ForegroundColor Yellow
Write-Host ""

npm run dev

Pop-Location
