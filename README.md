# Distribuidora Andina - ERP interno

Sistema de gestion para operaciones, inventario, clientes, pedidos, compras y ventas.

## Inicio Rapido

Windows:

```bash
start-simple.bat
```

Alternativa con verificacion de Apache:

```bash
start-smart.bat
```

Linux/Mac:

```bash
bash scripts/start-dev.sh
```

Abre en navegador:

```text
http://localhost:3000
```

La landing de WAMP queda disponible en:

```text
http://localhost/proyecto_andina/
```

## Estructura

```text
proyecto_andina/
|-- .env                         Configuracion local
|-- index.html                   Landing para WAMP
|-- test_api.php                 Puente hacia backend/test_api.php
|-- start-simple.bat             Inicio rapido WAMP + Vite
|-- start-smart.bat              Inicio con verificacion de Apache
|-- backend/                     API PHP, modulos, config y vendor
|-- frontend/artifacts/andina-frontend/
|   |-- index.html               Landing de Vite
|   |-- login.html               Inicio de sesion
|   |-- assets/                  CSS, JS e imagenes del frontend
|   |-- cliente/                 Tienda, carrito, perfil y pedidos cliente
|   |-- productos/               Gestion de productos
|   |-- pedidos/                 Gestion de pedidos
|   |-- compras/                 Gestion de compras
|   `-- vite.config.ts
|-- docs/                        Guias y notas
|-- scripts/                     Scripts secundarios
`-- tools/diagnostics/           Pruebas y diagnosticos manuales
```

## Desarrollo

Frontend:

```bash
cd frontend/artifacts/andina-frontend
npm run dev
```

Build:

```bash
cd frontend/artifacts/andina-frontend
npm run build
```

Typecheck:

```bash
cd frontend/artifacts/andina-frontend
npm run typecheck
```

Backend/API:

```text
test_api.php?accion=login
test_api.php?accion=listar_productos
test_api.php?accion=listar_pedidos
```

`test_api.php` en la raiz solo incluye el backend real: `backend/test_api.php`.

## Documentacion

- Guia de arranque: `docs/STARTUP_GUIDE.md`
- Inicio rapido anterior: `docs/QUICK_START.txt`
- Problemas frecuentes: `docs/TROUBLESHOOTING.md`
- Notas de arreglo: `docs/FIXED.txt`

## Seguridad

- No subir `.env` real.
- Mantener credenciales solo en `.env`.
- No publicar scripts de diagnostico sin revisar.
- En produccion, usar `ENVIRONMENT=production` y `DEBUG_MODE=false`.

