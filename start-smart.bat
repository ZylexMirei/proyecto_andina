@echo off
REM =========================================
REM ULTRA SMART - Verifica WAMP antes de iniciar
REM =========================================

setlocal enabledelayedexpansion
set "WAMP_PATH=C:\wamp64"
set "PROJECT_PATH=%~dp0"
set "MAX_WAIT=30"
set "WAIT_COUNT=0"

cls
echo.
echo ╔════════════════════════════════════════╗
echo ║   DISTRIBUIDORA ANDINA - INICIANDO     ║
echo ╚════════════════════════════════════════╝
echo.

REM VERIFICAR WAMP
if not exist "%WAMP_PATH%\wampmanager.exe" (
    echo [ERROR] WAMP no encontrado en %WAMP_PATH%
    pause
    exit /b 1
)

REM INICIAR WAMP
echo [PASO 1] Lanzando WAMP (Apache + MySQL)...
start "WAMP Backend" "%WAMP_PATH%\wampmanager.exe"
echo      ✓ Ventana WAMP abierta
echo.
echo [PASO 2] Esperando a que Apache esté listo...

REM ESPERAR A QUE APACHE ESTÉ DISPONIBLE
:WAIT_FOR_APACHE
timeout /t 1 /nobreak >nul
set /a WAIT_COUNT+=1

REM Intentar conectar a Apache (usando curl o un simple test)
powershell -Command "try { $null = Invoke-WebRequest -Uri 'http://localhost' -TimeoutSec 1 -ErrorAction Stop; exit 0 } catch { exit 1 }" >nul 2>&1

if %ERRORLEVEL% EQU 0 (
    echo      ✓ Apache está LISTO
    goto APACHE_READY
) else (
    if %WAIT_COUNT% LSS %MAX_WAIT% (
        <nul set /p =.
        goto WAIT_FOR_APACHE
    ) else (
        echo.
        echo [ERROR] Apache no respondió en %MAX_WAIT% segundos
        echo Verifica que WAMP esté bien instalado
        pause
        exit /b 1
    )
)

:APACHE_READY
echo.
echo [PASO 3] Apache está corriendo. Iniciando Frontend...
cd /d "%PROJECT_PATH%frontend\artifacts\andina-frontend"

if not exist "node_modules" (
    echo Instalando dependencias npm...
    call npm install
    echo.
)

cls
echo.
echo ╔════════════════════════════════════════╗
echo ║    ✓ SISTEMA COMPLETAMENTE INICIADO   ║
echo ╚════════════════════════════════════════╝
echo.
echo ✓ WAMP (Apache + MySQL):  CORRIENDO
echo ✓ Frontend (Vite):        INICIANDO
echo.
echo Abriendo navegador en 2 segundos...
echo.
echo ════════════════════════════════════════
echo.

timeout /t 2 /nobreak >nul
start "" "http://localhost:3000"

echo Vite está corriendo en ESTA ventana:
echo.
npm run dev
