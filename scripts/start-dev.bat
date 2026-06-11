@echo off
REM =========================================
REM Script para iniciar desarrollo completo
REM =========================================
REM Este script inicia tanto el backend (PHP) como el frontend (Vite)

setlocal enabledelayedexpansion
set "PROJECT_PATH=%~dp0..\\"
set "WAMP_PATH=C:\wamp64"
set "PHP_MODE=wamp"

echo.
echo ========================================
echo Distribuidora Andina - INICIO COMPLETO
echo ========================================
echo.

REM Verificar si WAMP existe
if not exist "%WAMP_PATH%\wampmanager.exe" (
    echo [ADVERTENCIA] WAMP no encontrado en %WAMP_PATH%
    echo.
    echo Opciones:
    echo 1. Inicia WAMP manualmente y vuelve a ejecutar este script
    echo 2. Usa el servidor integrado de PHP (mas lento, solo desarrollo)
    echo.
    set /p CHOICE="Elige opcion (1 o 2, por defecto 1): "
    if "!CHOICE!"=="2" (
        set "PHP_MODE=builtin"
        echo Usaremos el servidor integrado de PHP...
    ) else (
        echo Abre WAMP manualmente y vuelve a ejecutar este script.
        pause
        exit /b 1
    )
)

REM ========================================
REM PASO 1: Iniciar BACKEND (WAMP o PHP integrado)
REM ========================================
if "%PHP_MODE%"=="wamp" (
    echo.
    echo [PASO 1/2] Iniciando WAMP (Apache + MySQL)...
    echo.
    start "WAMP - Backend" "%WAMP_PATH%\wampmanager.exe"
    echo ✓ WAMP se está iniciando en otra ventana...
    timeout /t 8 /nobreak
) else (
    echo.
    echo [PASO 1/2] Iniciando servidor PHP integrado en puerto 8080...
    echo.
    start "PHP Server - Backend" cmd /k "cd /d %PROJECT_PATH% && php -S localhost:8080"
    echo ✓ PHP server iniciado en otra ventana...
    timeout /t 3 /nobreak
)

REM ========================================
REM PASO 2: Iniciar FRONTEND (Vite)
REM ========================================
echo.
echo [PASO 2/2] Iniciando Frontend (Vite en puerto 3000)...
echo.
cd /d "%PROJECT_PATH%frontend\artifacts\andina-frontend"

if not exist package.json (
    echo [ERROR] package.json no encontrado
    pause
    exit /b 1
)

if not exist node_modules (
    echo Instalando dependencias npm (primera vez, esto toma 1-2 minutos)...
    call npm install
)

if "%PHP_MODE%"=="wamp" (
    set "BACKEND_URL=http://localhost/proyecto_andina/"
) else (
    set "BACKEND_URL=http://localhost:8080/"
)

set "VITE_PHP_ORIGIN=%BACKEND_URL%"

REM ========================================
REM TODO LISTO - Iniciar Vite en ESTA ventana
REM ========================================
cls
echo.
echo ========================================
echo   ✓ DISTRIBUIDORA ANDINA - ¡INICIADO!
echo ========================================
echo.
echo [BACKEND]  %BACKEND_URL%
echo [FRONTEND] http://localhost:3000
echo.
echo Abriendo navegador en 3 segundos...
echo.
timeout /t 3 /nobreak

REM Abrir navegador automáticamente
start "" "http://localhost:3000"

echo.
echo ========================================
echo Presiona Ctrl+C aqui para detener TODO
echo ========================================
echo.

npm run dev
