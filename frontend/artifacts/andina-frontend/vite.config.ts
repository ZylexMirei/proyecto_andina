import { defineConfig } from "vite";
import fs from "fs";
import path from "path";

const rawPort = process.env.PORT || "3000";

const port = Number(rawPort);

if (Number.isNaN(port) || port <= 0) {
  throw new Error(`Invalid PORT value: "${rawPort}"`);
}

const basePath = process.env.BASE_PATH || "/";
const projectRoot = path.resolve(import.meta.dirname);
const buildOutDir = path.resolve(projectRoot, "dist/public");

function copyLegacyAssets() {
  return {
    name: "copy-legacy-assets",
    closeBundle() {
      const source = path.resolve(projectRoot, "assets");
      const target = path.resolve(buildOutDir, "assets");
      if (fs.existsSync(source)) {
        fs.cpSync(source, target, { recursive: true });
      }
    },
  };
}

export default defineConfig({
  base: basePath,
  root: projectRoot,
  publicDir: false,
  plugins: [copyLegacyAssets()],
  build: {
    outDir: buildOutDir,
    emptyOutDir: true,
    rollupOptions: {
      input: {
        index: path.resolve(import.meta.dirname, "index.html"),
        login: path.resolve(import.meta.dirname, "login.html"),
        registro: path.resolve(import.meta.dirname, "registro.html"),
        verificar: path.resolve(import.meta.dirname, "verificar.html"),
        dashboard: path.resolve(import.meta.dirname, "dashboard.html"),
        "productos-lista": path.resolve(import.meta.dirname, "productos/lista.html"),
        "productos-crear": path.resolve(import.meta.dirname, "productos/crear.html"),
        "inventario-ver": path.resolve(import.meta.dirname, "inventario/ver.html"),
        "compras-lista": path.resolve(import.meta.dirname, "compras/lista.html"),
        "compras-crear": path.resolve(import.meta.dirname, "compras/crear.html"),
        "pedidos-lista": path.resolve(import.meta.dirname, "pedidos/lista.html"),
        "pedidos-crear": path.resolve(import.meta.dirname, "pedidos/crear.html"),
        "clientes-lista": path.resolve(import.meta.dirname, "clientes/lista.html"),
        "proveedores-lista": path.resolve(import.meta.dirname, "proveedores/lista.html"),
        "reportes-dashboard": path.resolve(import.meta.dirname, "reportes/dashboard.html"),
        "usuarios-lista": path.resolve(import.meta.dirname, "usuarios/lista.html"),
        "seguridad-logs": path.resolve(import.meta.dirname, "seguridad/logs.html"),
        "cliente-registro": path.resolve(import.meta.dirname, "cliente/registro.html"),
        "cliente-tienda": path.resolve(import.meta.dirname, "cliente/tienda.html"),
        "cliente-carrito": path.resolve(import.meta.dirname, "cliente/carrito.html"),
        "cliente-mis-pedidos": path.resolve(import.meta.dirname, "cliente/mis_pedidos.html"),
        "cliente-perfil": path.resolve(import.meta.dirname, "cliente/perfil.html"),
      },
    },
  },
  server: {
    port,
    strictPort: true,
    host: "0.0.0.0",
    allowedHosts: true,
    proxy: {
      // Redirige peticiones a /test_api.php al servidor PHP (WAMP/Apache)
      "^/test_api\\.php": {
        target: "http://localhost/proyecto_andina",
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path,
      },
      // Redirige peticiones a /backend/ al servidor PHP
      "^/backend": {
        target: "http://localhost/proyecto_andina",
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/backend/, ""),
      },
      // Fallback si VITE_PHP_ORIGIN está definida (variable de entorno)
      "^/api": {
        target: process.env.VITE_PHP_ORIGIN ?? "http://localhost/proyecto_andina",
        changeOrigin: true,
        secure: false,
      },
    },
  },
  preview: {
    port,
    host: "0.0.0.0",
    allowedHosts: true,
  },
});
