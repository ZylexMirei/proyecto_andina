@echo off
REM =========================================
REM Script de Configuración Inicial - Windows
REM =========================================
REM Este script prepara el proyecto para desarrollo

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
    echo [2/3] Instalando dependencias PHP con Composer...
    pushd backend
    call composer install
    popd
)

REM 3. Instalar dependencias Frontend
cd frontend\artifacts\andina-frontend
if exist node_modules\ (
    echo [3/3] Dependencias Frontend ya instaladas.
) else (
    echo [3/3] Instalando dependencias Frontend...
    call npm install
)

echo.
echo ========================================
echo Proximos pasos:
echo ========================================
echo.
echo 1. Edita el archivo .env con tus credenciales:
echo    - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
echo    - MAIL_USERNAME y MAIL_PASSWORD de Gmail
echo.
echo 2. Verifica la configuracion visitando:
echo    http://localhost/proyecto_andina/verify_setup.php
echo.
echo 3. Crea la base de datos:
echo    mysql -u root -p < schema.sql
echo.
echo 4. Compila el frontend:
echo    npm run build
echo.
echo ========================================
echo Configuracion lista!
echo ========================================
echo.
pause
