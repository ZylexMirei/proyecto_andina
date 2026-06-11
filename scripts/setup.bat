@echo off
REM =========================================
REM Script de Configuración Inicial - Windows
REM =========================================
REM Este script prepara el proyecto para desarrollo
set "PROJECT_PATH=%~dp0..\\"
pushd "%PROJECT_PATH%"

echo.
echo ========================================
echo Distribuidora Andina - Configuracion
echo ========================================
echo.

REM 1. Verificar si .env existe
if not exist .env (
    echo [1/3] Creando archivo .env desde .env.example...
    copy .env.example .env
    echo Archivo .env creado. EDITA este archivo con tus credenciales.
    echo.
    pause
) else (
    echo [1/3] Archivo .env ya existe.
)

REM 2. Instalar dependencias PHP (backend/)
if exist backend\vendor\ (
    echo [2/3] Dependencias PHP ya instaladas.
) else (
    where composer >nul 2>nul
    if %ERRORLEVEL% NEQ 0 (
        echo [2/3] ADVERTENCIA: Composer no esta instalado o no esta en el PATH.
    ) else (
        echo [2/3] Instalando dependencias PHP con Composer...
        pushd backend
        call composer install
        popd
    )
)

REM 3. Instalar dependencias Frontend
pushd frontend\artifacts\andina-frontend
if exist node_modules\ (
    echo [3/3] Dependencias Frontend ya instaladas.
) else (
    where npm >nul 2>nul
    if %ERRORLEVEL% NEQ 0 (
        echo [3/3] ADVERTENCIA: npm no esta instalado o no esta en el PATH.
    ) else (
        echo [3/3] Instalando dependencias Frontend...
        call npm install
    )
)
popd

echo.
echo ========================================
echo Proximos pasos:
echo ========================================
echo.
echo 1. Edita el archivo .env con tus credenciales:
echo    - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
echo    - MAIL_HOST, MAIL_PORT, MAIL_USERNAME y MAIL_PASSWORD de tu proveedor de correo
echo.
echo 2. Verifica la configuracion visitando:
echo    http://localhost/proyecto_andina/
echo.
echo 3. Crea la base de datos:
echo    - (Con phpMyAdmin) Entra a http://localhost/phpmyadmin e importa schema.sql
echo    (En CMD)  mysql -u root -p ^< schema.sql
echo    (En PowerShell) Get-Content schema.sql ^| mysql -u root -p
echo.
echo 4. Compila el frontend:
echo    npm run build
echo.
echo ========================================
echo Configuracion lista!
echo ========================================
echo.
pause
popd
