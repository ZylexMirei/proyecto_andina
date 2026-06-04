# Distribuidora Andina — ERP interno

Sistema de gestión para operaciones, inventario y ventas. Backend en PHP (MySQL, Composer), interfaz principal en HTML/CSS/JS con build Vite en `frontend/artifacts/andina-frontend`.

## Requisitos

- PHP 7.4+ (extensiones: `pdo_mysql`, `json`, `mbstring`)
- MySQL 5.7+ / MySQL Workbench
- Composer
- Node.js 18+ y npm (solo para compilar el frontend)

## Puesta en marcha

**Windows:** `setup.bat`  
**Linux / macOS:** `chmod +x setup.sh` y `./setup.sh`

**Manual**

1. Copiar `.env.example` a `.env` y completar credenciales.
2. PHP: `cd backend` y `composer install`.
3. Frontend: `cd frontend/artifacts/andina-frontend`, luego `npm install` y `npm run build`.
4. Crear la base de datos. Puedes usar phpMyAdmin, MySQL Workbench o la terminal:
   - **phpMyAdmin (WAMP/XAMPP):** Entra a `http://localhost/phpmyadmin`, ve a "Importar" y sube el archivo SQL de esquema proporcionado.
   - **CMD (Command Prompt):** `mysql -u root -p < schema.sql`
   - **PowerShell:** `Get-Content schema.sql | mysql -u root -p`
   - Si usas WAMP y tu servidor MySQL corre en puerto `3308`, actualiza `DB_PORT=3308` en `.env`.
   - Si el repositorio no incluye `schema.sql`, crea la base de datos usando el dump o la estructura compartida por el equipo.
5. Revisar en el navegador: `verify_setup.php` (misma raíz del proyecto que sirve el servidor web).

## Cómo Ejecutar (Desarrollo)

Para trabajar en el proyecto, necesitas tener tanto el backend (PHP) como el frontend (Vite) corriendo simultáneamente.

1.  **Iniciar el Backend (Servidor Web):**
    -   Asegúrate de que tu servidor web (XAMPP, WAMP, etc.) esté en ejecución con **Apache y MySQL iniciados**.
    -   El backend estará disponible en `http://localhost/proyecto_andina/`.

2.  **Iniciar el Frontend (Servidor de Desarrollo):**
    -   Abre una **nueva terminal**.
    -   Navega a la carpeta del frontend: `cd frontend/artifacts/andina-frontend`
    -   Ejecuta el comando: `npm run dev`
    -   La terminal te mostrará una URL local, generalmente `http://localhost:5173/`.

3.  **¡Usa esta última URL (`http://localhost:5173/`) en tu navegador para ver y probar la aplicación!**

El servidor de desarrollo de Vite reenviará automáticamente las peticiones de la API al backend de PHP, por lo que todo funcionará en conjunto.

**Nota sobre `php -S` (Método sin XAMPP/Apache):** Puedes correr el backend con el servidor integrado de PHP (`php -S localhost:8080` desde la raíz). Para que esto funcione, el frontend necesita saber a dónde enviar las peticiones de la API. Abre el archivo `frontend/artifacts/andina-frontend/vite.config.ts` y asegúrate de que la sección `server.proxy` redirige todas las rutas de la API (como `/backend/` y `/test_api.php`) a tu servidor PHP, por ejemplo: `target: 'http://localhost:8080'`.

## Variables de entorno (`.env` en la raíz)

| Uso | Variables |
|-----|-----------|
| Base de datos | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` |
| Correo (SMTP) | `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` (tls/ssl), `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_*` |
| Entorno | `ENVIRONMENT`, `DEBUG_MODE`, `APP_URL`, `ALLOWED_ORIGINS` |

No subas nunca el `.env` real al repositorio. Para Gmail, se recomienda usar una "contraseña de aplicación". Para otros proveedores, usa la contraseña normal de la cuenta.

## Estructura del repositorio

```
proyecto_andina/
├── .env                 (local, no versionar)
├── test_api.php         → incluye backend/test_api.php
├── verify_setup.php
├── backend/             API PHP, módulos, auth, config, vendor (Composer)
└── frontend/artifacts/andina-frontend/   Vite, HTML, assets, dist tras build
```

## Desarrollo

- API JSON: `GET/POST` a `test_api.php?accion=...` (DocumentRoot = carpeta del proyecto).
- Módulos PHP sueltos: bajo `backend/modules/...`.
- Frontend: `npm run dev` en `frontend/artifacts/andina-frontend`. Si el front y el PHP no comparten el mismo origen, levanta PHP en otro puerto y usa la variable `VITE_PHP_ORIGIN` o el proxy ya definido en `vite.config.ts` hacia ese origen.
- Comprobar tipos: `npm run typecheck`.

## Endpoints de referencia

- `test_api.php` — capa de pruebas / acciones `?accion=login`, etc.
- `verify_setup.php` — comprobación de entorno
- Módulos: `backend/modules/...` (registro, productos, etc. según el archivo)

## Seguridad (resumen)

- Credenciales solo en `.env`; en código usar `getEnv()` / la capa que ya expone el proyecto.
- HTTPS en producción; ajusta `ALLOWED_ORIGINS` a dominios reales.
- Contraseñas de usuario con `password_hash` / BCRYPT; formularios con CSRF donde corresponda.
- **¡Riesgo de exposición!** Si la raíz del proyecto es pública (DocumentRoot = raíz), asegúrate de bloquear el acceso web al archivo `.env` (p. ej., mediante un archivo `.htaccess` en Apache que deniegue el acceso).
- CORS restringido en `includes/cors.php` según entorno.

## Despliegue (checklist breve)

- `.env` de producción con `ENVIRONMENT=production` y `DEBUG_MODE=false`.
- `composer install --no-dev` en `backend/`.
- `npm run build` en el frontend y publicar el contenido de `dist/public` según tu hosting.
- Revisar permisos de archivos, logs y que no queden scripts de prueba expuestos sin necesidad.

## Problemas frecuentes

- **MySQL:** Comprobar puerto (p. ej. 3308) y que el servicio esté en marcha. El comando de importación `mysql ... < schema.sql` es para `cmd.exe`; en **PowerShell** el equivalente es `Get-Content schema.sql | mysql ...`.
- **Correo:** Si usas Gmail, necesitas una "contraseña de aplicación", no la clave normal de la cuenta. Para otros proveedores, la contraseña normal suele funcionar. Verifica el Host, Puerto y tipo de cifrado (TLS/SSL) de tu proveedor.
- **CORS:** el origen del front debe figurar en `ALLOWED_ORIGINS`.

## Licencia

Uso interno — Distribuidora Andina SRL. Todos los derechos reservados.

---

*Documentación del proyecto, mayo 2026.*
