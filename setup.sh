#!/bin/bash
# =========================================
# Script de Configuración Inicial - Linux/Mac
# =========================================
# Termina el script si un comando falla
set -e

# Este script prepara el proyecto para desarrollo

echo ""
echo "========================================"
echo "Distribuidora Andina - Configuracion"
echo "========================================"
echo ""

# Verificar si .env existe
if [ ! -f ".env" ]; then
    echo "[1/3] Creando archivo .env desde .env.example..."
    cp .env.example .env
    echo "Archivo .env creado. EDITA este archivo con tus credenciales."
    echo ""
    read -p "Presiona ENTER para continuar..."
else
    echo "[1/3] Archivo .env ya existe."
fi

# Instalar dependencias PHP (backend/)
if [ -d "backend/vendor" ]; then
    echo "[2/3] Dependencias PHP ya instaladas."
else
    echo "[2/3] Instalando dependencias PHP con Composer..."
    (cd backend && composer install)
fi

# Instalar dependencias Frontend
if [ -d "frontend/artifacts/andina-frontend/node_modules" ]; then
    echo "[3/3] Dependencias Frontend ya instaladas."
else
    echo "[3/3] Instalando dependencias Frontend..."
    (cd frontend/artifacts/andina-frontend && npm install)
fi

echo ""
echo "========================================"
echo "Proximos pasos:"
echo "========================================"
echo ""
echo "1. Edita el archivo .env con tus credenciales:"
echo "   - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD"
echo "   - MAIL_HOST, MAIL_PORT, MAIL_USERNAME y MAIL_PASSWORD de tu proveedor de correo"
echo ""
echo "2. Verifica la configuracion visitando:"
echo "   http://localhost/proyecto_andina/verify_setup.php"
echo ""
echo "3. Crea la base de datos:"
echo "   - (Con phpMyAdmin) Entra a http://localhost/phpmyadmin e importa schema.sql"
echo "   mysql -u root -p < schema.sql"
echo ""
echo "4. Compila el frontend:"
echo "   cd frontend/artifacts/andina-frontend"
echo "   npm run build"
echo ""
echo "========================================"
echo "Configuracion lista!"
echo "========================================"
echo ""
