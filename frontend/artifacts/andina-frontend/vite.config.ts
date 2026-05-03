import { defineConfig } from "vite";
import path from "path";

const rawPort = process.env.PORT || "3000";

const port = Number(rawPort);

if (Number.isNaN(port) || port <= 0) {
  throw new Error(`Invalid PORT value: "${rawPort}"`);
}

const basePath = process.env.BASE_PATH || "/";

export default defineConfig({
  base: basePath,
  root: path.resolve(import.meta.dirname),
  publicDir: false,
  build: {
    outDir: path.resolve(import.meta.dirname, "dist/public"),
    emptyOutDir: true,
    rollupOptions: {
      input: {
        index: path.resolve(import.meta.dirname, "index.html"),
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
    // PHP en otro proceso: php -S 127.0.0.1:8080 -t <raíz repo proyecto_andina>
    proxy: {
      // Redirige CUALQUIER petición que empiece con /backend/ al servidor PHP
      "/backend": {
        target: process.env.VITE_PHP_ORIGIN ?? "http://127.0.0.1:8080",
        changeOrigin: true,
        secure: false,
      },
      // Redirige la petición específica a /test_api.php al servidor PHP
      "/test_api.php": {
        target: process.env.VITE_PHP_ORIGIN ?? "http://127.0.0.1:8080",
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
