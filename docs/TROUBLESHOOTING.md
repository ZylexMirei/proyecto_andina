# 🔧 Solución de Problemas - Distribuidora Andina

## Error: "Cannot GET /test_api.php"

**Causa:** Apache/WAMP no está corriendo.

**Solución:**
```bash
# Windows - Abre WAMP manualmente
C:\wamp64\wampmanager.exe

# O si usas el script:
start-dev.bat
```

Verifica que el ícono de WAMP en la bandeja del sistema esté **verde**.

---

## Error: "ERR_CONNECTION_REFUSED"

**Causa:** El puerto 3000 o Apache está ocupado, o WAMP no está correctamente inicializado.

**Soluciones:**

### 1. Verificar WAMP está realmente corriendo
```bash
# Abre en navegador
http://localhost
```

Debes ver la página de WAMP. Si no, reinicia WAMP.

### 2. Esperar más tiempo
A veces Apache tarda 5-10 segundos en iniciar. Espera y recarga (F5).

### 3. Reiniciar todo
```bash
# En PowerShell (como administrador):
taskkill /F /IM wampmanager.exe
taskkill /F /IM node.exe
taskkill /F /IM php.exe

# Luego ejecuta start-dev.bat nuevamente
```

---

## Error: "npm: no se encontró el comando"

**Causa:** Node.js/npm no está instalado o no está en PATH.

**Solución:**
1. Descarga Node.js: https://nodejs.org
2. Instala (elige LTS)
3. Reinicia la terminal
4. Verifica:
   ```bash
   node --version
   npm --version
   ```

---

## Error: "EADDRINUSE: address already in use :::3000"

**Causa:** Otro proceso está usando el puerto 3000.

**Soluciones:**

### Opción A: Matar el proceso
```bash
# Windows - PowerShell (como admin)
taskkill /F /IM node.exe

# Linux/Mac
killall node
```

Luego ejecuta `start-dev.bat` nuevamente.

### Opción B: Usar otro puerto
```bash
# Windows
set PORT=3001
npm run dev

# Linux/Mac
export PORT=3001
npm run dev
```

Accede a: `http://localhost:3001`

---

## Error: "Cannot find module" o dependencias faltantes

**Causa:** Las dependencias npm no están instaladas.

**Solución:**
```bash
cd frontend/artifacts/andina-frontend
rm -rf node_modules  # O en Windows: rmdir /s node_modules
npm install
npm run dev
```

---

## "El login no funciona" / "Credenciales no aceptadas"

**Verificar:**

1. **Base de datos existe:**
   ```bash
   # Abre en navegador
   http://localhost/phpmyadmin
   
   # Verifica que exista: distribuidora_andina
   ```

2. **Las credenciales en `.env` son correctas:**
   ```bash
   DB_HOST=127.0.0.1
   DB_PORT=3308       # Verifica este puerto en WAMP
   DB_NAME=distribuidora_andina
   DB_USER=root
   DB_PASSWORD=       # (vacío por defecto en WAMP)
   ```

3. **Hay al menos un usuario en la BD:**
   ```bash
   # En phpMyAdmin, ve a:
   distribuidora_andina > usuarios > (debe haber registros)
   ```

4. **Revisa los logs:**
   ```bash
   # Si tienes errores, mira WAMP logs
   C:\wamp64\logs\
   ```

---

## Error: "Mixed Content" o "Blocked by CORS"

**Causa:** El navegador bloquea peticiones inseguras (HTTP vs HTTPS).

**Solución:**
Por defecto en desarrollo todo es HTTP, así que no debería haber problema. Si ves este error:

1. Borra cookies del navegador
2. Abre en una pestaña privada/incógnito
3. Limpia el cache: Ctrl+Shift+Delete

---

## Frontend Vite no se recarga automáticamente

**Causa:** Cambios de archivos no se detectan.

**Soluciones:**

1. Reinicia `npm run dev`:
   ```bash
   # Ctrl+C en la terminal donde corre Vite
   # Luego npm run dev nuevamente
   ```

2. Verifica que estés editando en `frontend/artifacts/andina-frontend/`
   ```bash
   # ✅ Correcto
   frontend/artifacts/andina-frontend/src/
   frontend/artifacts/andina-frontend/assets/
   
   # ❌ Incorrecto (cambios no se sincronizan)
   frontend/
   ```

---

## Puerto 3308 vs 3306 en MySQL

**Verificar puerto correcto:**
```bash
# Abre phpMyAdmin
http://localhost/phpmyadmin

# Verifica arriba a la derecha qué puerto muestra
# Luego actualiza .env:
DB_PORT=3308   # (o el que corresponda)
```

---

## Archivo `.env` no existe o tiene valores vacios

**Solución:**
```bash
# Copia el template:
# Windows
copy .env.example .env

# Linux/Mac
cp .env.example .env
```

Luego edita `.env` con tus credenciales.

---

## ¿Aún no funciona?

1. **Reinicia COMPLETAMENTE:**
   - Cierra todas las terminales
   - Cierra WAMP
   - Cierra el navegador
   - Ejecuta `start-dev.bat` nuevamente
   - Espera 10 segundos completos

2. **Verifica URLs:**
   - Frontend: `http://localhost:3000` ✅
   - Backend: `http://localhost/proyecto_andina` ✅
   - phpMyAdmin: `http://localhost/phpmyadmin` ✅

3. **Revisa logs:**
   - Abre la consola del navegador: **F12**
   - Ve a la pestaña **Network** o **Console**
   - Intenta hacer login
   - Busca errores en rojo

4. **Captura de pantalla y contacta:**
   - Comparte qué error específico ves
   - Incluye output de la terminal
   - Describe qué URLs intentaste abrir

---

**¡El equipo está aquí para ayudarte!** 🎉
