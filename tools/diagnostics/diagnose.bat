@echo off
REM =========================================
REM DIAGNÓSTICO - Verifica si todo está bien
REM =========================================

cls
echo.
echo ╔═══════════════════════════════════════════════════════╗
echo ║     DIAGNÓSTICO - Distribuidora Andina             ║
echo ╚═══════════════════════════════════════════════════════╝
echo.

setlocal enabledelayedexpansion

REM TEST 1: Verificar WAMP instalado
echo [TEST 1] Verificando WAMP instalado...
if exist "C:\wamp64\wampmanager.exe" (
    echo      ✓ WAMP encontrado en C:\wamp64
) else (
    echo      ✗ WAMP NO encontrado en C:\wamp64
    echo        Instala WAMP desde: https://wampserver.com
)

REM TEST 2: Verificar Node.js
echo.
echo [TEST 2] Verificando Node.js...
where node >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    for /f "tokens=*" %%i in ('node --version') do set NODE_VER=%%i
    echo      ✓ Node.js !NODE_VER! encontrado
) else (
    echo      ✗ Node.js NO encontrado
    echo        Instala Node.js desde: https://nodejs.org
)

REM TEST 3: Verificar npm
echo.
echo [TEST 3] Verificando npm...
where npm >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    for /f "tokens=*" %%i in ('npm --version') do set NPM_VER=%%i
    echo      ✓ npm !NPM_VER! encontrado
) else (
    echo      ✗ npm NO encontrado
    echo        Node.js se instala con npm incluido
)

REM TEST 4: Verificar archivos del proyecto
echo.
echo [TEST 4] Verificando archivos del proyecto...
if exist "frontend\artifacts\andina-frontend\package.json" (
    echo      ✓ Frontend encontrado
) else (
    echo      ✗ Frontend NO encontrado en: frontend\artifacts\andina-frontend
)

if exist "backend\composer.json" (
    echo      ✓ Backend encontrado
) else (
    echo      ✗ Backend NO encontrado en: backend
)

if exist ".env" (
    echo      ✓ Archivo .env encontrado
) else (
    echo      ✗ Archivo .env NO encontrado
    echo        Copia de .env.example si no existe
)

if exist "test_api.php" (
    echo      ✓ test_api.php encontrado
) else (
    echo      ✗ test_api.php NO encontrado en raíz
)

REM TEST 5: Verificar Apache (si WAMP está corriendo)
echo.
echo [TEST 5] Intentando conectar a Apache...
powershell -Command "try { $null = Invoke-WebRequest -Uri 'http://localhost' -TimeoutSec 2 -ErrorAction Stop; Write-Host '      ✓ Apache respondiendo en http://localhost'; exit 0 } catch { Write-Host '      ✗ Apache NO respondiendo'; exit 1 }"

if %ERRORLEVEL% NEQ 0 (
    echo        (Esto es normal si WAMP no está corriendo aún)
    echo        Inicia WAMP manualmente: start-simple.bat
)

REM TEST 6: Verificar MySQL (si WAMP está corriendo)
echo.
echo [TEST 6] Intentando conectar a MySQL...
powershell -Command "try { $null = Invoke-WebRequest -Uri 'http://localhost/phpmyadmin' -TimeoutSec 2 -ErrorAction Stop; Write-Host '      ✓ MySQL accesible en http://localhost/phpmyadmin'; exit 0 } catch { Write-Host '      ✗ MySQL NO accesible'; exit 1 }"

if %ERRORLEVEL% NEQ 0 (
    echo        (Esto es normal si WAMP no está corriendo aún)
)

echo.
echo ╔═══════════════════════════════════════════════════════╗
echo ║              REPORTE COMPLETO                         ║
echo ╚═══════════════════════════════════════════════════════╝
echo.
echo Próximos pasos:
echo.
echo 1. Si todos los TEST pasaron ✓:
echo    - Ejecuta: start-simple.bat
echo.
echo 2. Si hay TEST fallidos ✗:
echo    - Instala los programas faltantes
echo    - Luego vuelve a ejecutar este diagnóstico
echo.
echo Para más ayuda: Lee TROUBLESHOOTING.md
echo.
pause
