# Proyecto Andina

Sistema web para Distribuidora Andina: productos, inventario, clientes, pedidos, compras y reportes.

## Iniciar

En Windows, usa uno de estos archivos:

```bash
start-simple.bat
```

o

```bash
start-smart.bat
```

Luego abre:

```text
http://localhost:3000
```

## Estructura

```text
backend/   API PHP y conexion a base de datos
frontend/  Frontend Vite
docs/      Documentos de apoyo
scripts/   Scripts secundarios
assets/    Imagenes usadas por la landing
```

## Comandos

```bash
cd frontend/artifacts/andina-frontend
npm run dev
npm run build
npm run typecheck
```

## Importante

- No borrar `.env`.
- No subir `.env` real a repositorios publicos.
- `test_api.php` conecta el frontend con el backend.

