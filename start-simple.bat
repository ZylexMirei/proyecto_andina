@echo off
REM =========================================
REM SUPER SIMPLE - Iniciar Desarrollo
REM =========================================
REM Este script INICIA AMBOS AUTOMÁTICAMENTE:
REM  1. WAMP (Apache + MySQL)
REM  2. Vite Frontend (puerto 3000)

setlocal enabledelayedexpansion
set "WAMP_PATH=C:\wamp64"
set "PROJECT_PATH=%~dp0"

cls
echo.
echo ╔════════════════════════════════════════╗
echo ║   DISTRIBUIDORA ANDINA - INICIANDO     ║
echo ║        (Espera 10 segundos)            ║
echo ╚════════════════════════════════════════╝
echo.

REM VERIFICAR WAMP
if not exist "%WAMP_PATH%\wampmanager.exe" (
    echo [ERROR] WAMP no encontrado en %WAMP_PATH%
    pause
    exit /b 1
)

REM INICIAR WAMP EN VENTANA SEPARADA
echo [1/2] Lanzando WAMP (Apache + MySQL)...
start "WAMP Backend" "%WAMP_PATH%\wampmanager.exe"
echo      ✓ Ventana WAMP abierta
echo.

REM ESPERAR A QUE WAMP ESTÉ LISTO
echo [2/2] Esperando 8 segundos (WAMP se está inicializando)...
for /L %%i in (8,-1,1) do (
    <nul set /p =.
    timeout /t 1 /nobreak >nul
)
echo.
echo.

REM INICIAR FRONTEND EN ESTA MISMA VENTANA
cd /d "%PROJECT_PATH%frontend\artifacts\andina-frontend"

if not exist "node_modules" (
    echo [PRIMERA VEZ] Instalando dependencias npm...
    call npm install
    echo.
)

cls
echo.
echo ╔════════════════════════════════════════╗
echo ║    ✓ TODO ESTÁ INICIADO                ║
echo ╚════════════════════════════════════════╝
echo.
echo [BACKEND]  → http://localhost/proyecto_andina/
echo [FRONTEND] → http://localhost:3000  ← ABRE ESTA
echo.
echo [WAMP]     ← En otra ventana (no cierres)
echo [VITE]     ← Ejecutándose en ESTA ventana
echo.
echo Presiona Ctrl+C AQUI para detener Vite
echo (WAMP seguirá corriendo si quieres cerrar esta terminal)
echo.
echo ════════════════════════════════════════
echo.

timeout /t 2 /nobreak
start "" "http://localhost:3000"

npm run dev
