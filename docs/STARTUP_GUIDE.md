# 🚀 INICIO RÁPIDO - Distribuidora Andina

## ✅ Respuesta a tu Pregunta

**Sí, cuando ejecutas `start-dev.bat`, ambos se inician automáticamente:**

1. ✅ **WAMP** se inicia (Apache + MySQL) - en una ventana separada
2. ✅ **Frontend Vite** se inicia (puerto 3000) - en la misma terminal

**No tienes que hacer nada más. Todo es automático.**

---

## ⚡ Opción 1: RECOMENDADO - Script Ultra Simple

### Windows

```bash
# Solo doble-clic en:
start-simple.bat
```

**Esto hará automáticamente:**
1. Inicia WAMP en otra ventana ← No cierres esta
2. Espera 8 segundos a que Apache esté listo
3. Inicia Vite en ESTA ventana ← Aquí ves todo
4. Abre automáticamente http://localhost:3000

**Resultado:**
- 2 ventanas abiertas: WAMP + Terminal con Vite
- 1 pestaña de navegador: Tu app en puerto 3000
- Presiona `Ctrl+C` en la terminal de Vite para parar solo el frontend
- Cierra WAMP manualmente si quieres apagar todo

---

## ⚡ Opción 2: Script Completo (Con más opciones)

```bash
start-dev.bat
```

Similar a `start-simple.bat` pero con más funcionalidades (manejo de errores, opción de PHP integrado, etc).

---

## 📺 Guía Visual - Qué Verás

```
Paso 1: Haces doble-clic en start-simple.bat
        ↓
Ventana 1 se abre: "WAMP Backend"
        ↓ (WAMP se está inicializando)
Paso 2: Esperando 8 segundos...
        ↓
Paso 3: Vite inicia EN LA MISMA VENTANA
        ↓
        "  ➜  Local:   http://localhost:3000/"
        ↓
Navegador se abre automáticamente
        ↓
        ✓ VES TU APP EN EL NAVEGADOR
```

---

## 🎯 Qué Significa Cada Ventana

### Ventana 1: "WAMP Backend"
```
Distribuidora Andina - Distribuidora Andina [  C  ][ _][ □][X]
────────────────────────────────────────────────────────────────
Apache: GREEN ✓
MySQL:  GREEN ✓
```
- **NO cierres esta ventana** mientras desarrollas
- No interactúas con ella, solo debe estar abierta
- Si Apache se pone ROJA, significa hay error

### Ventana 2: "PowerShell" (donde corre Vite)
```
C:\wamp64\www\proyecto_andina\frontend\artifacts\andina-frontend>
...
  ➜  Local:   http://localhost:3000/
  ➜  press h + enter to show help
```
- **Esta es donde paras TODO** (Ctrl+C)
- Aquí ves errores del frontend
- Si editas código, aquí ves los cambios

### Navegador
```
http://localhost:3000

[Iniciar Sesión]
Usuario: _______________
Contraseña: _______________
```
- Esta es la app que usas
- Todo funciona aquí

---

## 🔧 Si Algo Falla

### Error: "Cannot GET /test_api.php"
→ Apache no está listo aún. Espera otros 5 segundos y recarga (F5).

### Error: "ERR_CONNECTION_REFUSED"
→ WAMP no se abrió. Verifica que esté instalado en `C:\wamp64`.

### Error: "npm: no se encontró"
→ Node.js no está instalado. Descarga de: https://nodejs.org

**→ Lee:** [TROUBLESHOOTING.md](TROUBLESHOOTING.md) para más soluciones.

---

## 🛠️ Opción 3: VSCode Tasks (Alternativa)

Si prefieres iniciar desde VSCode:

1. Abre VSCode
2. Presiona: **Ctrl+Shift+B**
3. Selecciona: `🚀 Iniciar Desarrollo Completo`

---

## 📋 Checklist Antes de Empezar

- [ ] WAMP instalado en `C:\wamp64`
- [ ] Node.js + npm instalados
- [ ] Archivo `.env` existe (copia de `.env.example` si no)
- [ ] Base de datos `distribuidora_andina` creada

### Si falta algo:
```bash
setup.bat
```

---

## 💡 Tips Útiles

### Para Reiniciar TODO
```bash
# Ctrl+C en ventana de Vite
# Cierra WAMP
# Doble-clic en start-simple.bat nuevamente
```

### Para Editar el Código
Solo edita, guarda (Ctrl+S), y recarga navegador (F5).
Vite se actualiza automáticamente.

### Para Debuggear
Presiona F12 en navegador para abrir consola.
Busca errores en rojo.

### Para Ver Logs del Backend PHP
```bash
http://localhost/phpmyadmin/  # Para ver la BD
```

---

## ✨ ¿Qué Está Pasando Detrás?

```
Mi App (http://localhost:3000)
       ↓
    Vite (puerto 3000)
       ↓ (redirige automáticamente)
    Apache (puerto 80)
       ↓
    PHP → base de datos MySQL
```

El frontend redirige todas las llamadas `/test_api.php` automáticamente al backend PHP que corre en Apache.

**Tú solo ves:** http://localhost:3000

**Todo lo demás es transparente.**

---

## 🎉 ¡Listo!

Simplemente haz doble-clic en `start-simple.bat` y listo.

Todo inicia automáticamente. No tienes que hacer nada más.

¿Preguntas? Lee [TROUBLESHOOTING.md](TROUBLESHOOTING.md) o [README.md](README.md).

