#!/bin/bash

# =========================================
# Script para iniciar desarrollo completo (Linux/Mac)
# =========================================
# Este script inicia el frontend (Vite)

WAMP_PATH="/opt/wamp"
PROJECT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo ""
echo "========================================"
echo "Distribuidora Andina - INICIO COMPLETO"
echo "========================================"
echo ""

# Navegar a la carpeta del frontend
cd "$PROJECT_PATH/frontend/artifacts/andina-frontend"

# Verificar que package.json existe
if [ ! -f "package.json" ]; then
    echo "[ERROR] package.json no encontrado en frontend/artifacts/andina-frontend"
    exit 1
fi

echo "[1/2] Asegurando que las dependencias estén instaladas..."
if [ ! -d "node_modules" ]; then
    npm install
fi

echo "[2/2] Iniciando Frontend (Vite en puerto 3000)..."
echo ""
echo "========================================"
echo "Inicio completado!"
echo "========================================"
echo ""
echo "El sistema estará listo en 5-10 segundos."
echo ""
echo "[IMPORTANTE] Asegúrate de que Apache/MySQL estén corriendo."
echo "[FRONTEND]   Abre tu navegador: http://localhost:3000"
echo "[BACKEND]    Debe estar accesible en: http://localhost/proyecto_andina/"
echo "[API]        Proxy automático:  /test_api.php → PHP"
echo ""
echo "Presiona Ctrl+C para detener."
echo ""

npm run dev
