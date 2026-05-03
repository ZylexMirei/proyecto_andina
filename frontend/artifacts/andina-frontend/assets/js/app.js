/**
 * DISTRIBUIDORA ANDINA SRL — app.js
 * Lógica central: sesión, roles, sidebar, utilidades
 */

'use strict';

// ===================== CONSTANTES =====================
const API_BASE = '/test_api.php';
const SESSION_KEY = 'andina_session';


// ===================== MENÚ POR ROL =====================
const MENU_CONFIG = {
  Administrador: [
    { section: 'Principal' },
    { icon: 'bi-speedometer2', label: 'Dashboard', href: getRoot() + 'dashboard.html', key: 'dashboard' },
    { section: 'Operaciones' },
    { icon: 'bi-box-seam', label: 'Productos', href: getRoot() + 'productos/lista.html', key: 'productos' },
    { icon: 'bi-clipboard2-data', label: 'Inventario', href: getRoot() + 'inventario/ver.html', key: 'inventario' },
    { icon: 'bi-cart3', label: 'Compras', href: getRoot() + 'compras/lista.html', key: 'compras' },
    { icon: 'bi-receipt', label: 'Pedidos', href: getRoot() + 'pedidos/lista.html', key: 'pedidos' },
    { section: 'Comercial' },
    { icon: 'bi-people', label: 'Clientes', href: getRoot() + 'clientes/lista.html', key: 'clientes' },
    { icon: 'bi-truck', label: 'Proveedores', href: getRoot() + 'proveedores/lista.html', key: 'proveedores' },
    { icon: 'bi-bar-chart-line', label: 'Reportes', href: getRoot() + 'reportes/dashboard.html', key: 'reportes' },
    { section: 'Administración' },
    { icon: 'bi-person-gear', label: 'Usuarios', href: getRoot() + 'usuarios/lista.html', key: 'usuarios' },
    { icon: 'bi-shield-check', label: 'Seguridad', href: getRoot() + 'seguridad/logs.html', key: 'seguridad' },
  ],
  Gerente: [
    { section: 'Principal' },
    { icon: 'bi-speedometer2', label: 'Dashboard', href: getRoot() + 'dashboard.html', key: 'dashboard' },
    { section: 'Operaciones' },
    { icon: 'bi-box-seam', label: 'Productos', href: getRoot() + 'productos/lista.html', key: 'productos' },
    { icon: 'bi-clipboard2-data', label: 'Inventario', href: getRoot() + 'inventario/ver.html', key: 'inventario' },
    { icon: 'bi-cart3', label: 'Compras', href: getRoot() + 'compras/lista.html', key: 'compras' },
    { icon: 'bi-receipt', label: 'Pedidos', href: getRoot() + 'pedidos/lista.html', key: 'pedidos' },
    { section: 'Comercial' },
    { icon: 'bi-people', label: 'Clientes', href: getRoot() + 'clientes/lista.html', key: 'clientes' },
    { icon: 'bi-truck', label: 'Proveedores', href: getRoot() + 'proveedores/lista.html', key: 'proveedores' },
    { icon: 'bi-bar-chart-line', label: 'Reportes', href: getRoot() + 'reportes/dashboard.html', key: 'reportes' },
  ],
  Empleado: [
    { section: 'Mi espacio' },
    { icon: 'bi-speedometer2', label: 'Dashboard', href: getRoot() + 'dashboard.html', key: 'dashboard' },
    { icon: 'bi-box-seam', label: 'Productos', href: getRoot() + 'productos/lista.html', key: 'productos' },
    { icon: 'bi-clipboard2-data', label: 'Inventario', href: getRoot() + 'inventario/ver.html', key: 'inventario' },
    { icon: 'bi-receipt', label: 'Pedidos', href: getRoot() + 'pedidos/lista.html', key: 'pedidos' },
    { icon: 'bi-people', label: 'Clientes', href: getRoot() + 'clientes/lista.html', key: 'clientes' },
  ],
};

function getRoot() {
  const depth = location.pathname.split('/').filter(Boolean).length;
  if (depth <= 1) return './';
  return '../'.repeat(depth - 1);
}

// ===================== SESIÓN =====================
function getSession() {
  try {
    const s = localStorage.getItem(SESSION_KEY);
    return s ? JSON.parse(s) : null;
  } catch { return null; }
}

function setSession(data) {
  localStorage.setItem(SESSION_KEY, JSON.stringify(data));
}

function clearSession() {
  localStorage.removeItem(SESSION_KEY);
}

function requireAuth(redirect = true) {
  const session = getSession();
  if (!session) {
    if (redirect) window.location.href = getRoot() + 'index.html';
    return null;
  }
  if (session.rol === 'Cliente') {
    window.location.href = getRoot() + 'cliente/tienda.html';
    return null;
  }
  return session;
}

function requireClienteAuth() {
  const session = getSession();
  if (!session) {
    window.location.href = getRoot() + 'index.html';
    return null;
  }
  if (session.rol !== 'Cliente') {
    window.location.href = getRoot() + 'dashboard.html';
    return null;
  }
  return session;
}

function hasPermission(accion) {
  const session = getSession();
  if (!session) return false;
  const permisos = {
    Administrador: ['ver', 'crear', 'editar', 'eliminar', 'aprobar', 'usuarios', 'seguridad', 'reportes', 'movimientos'],
    Gerente: ['ver', 'crear', 'editar', 'aprobar', 'reportes', 'movimientos'],
    Empleado: ['ver', 'crear'],
    Cliente: ['ver_tienda', 'crear_pedido'],
  };
  return (permisos[session.rol] || []).includes(accion);
}

// ===================== SIDEBAR =====================
function buildSidebar(activeKey) {
  const session = getSession();
  if (!session) return;

  const menu = MENU_CONFIG[session.rol];
  if (!menu) return;

  const initials = session.nombre.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
  const rolBadgeMap = { Administrador: 'danger', Gerente: 'primary', Empleado: 'success', Cliente: 'secondary' };

  let html = `
    <a class="sidebar-brand" href="${getRoot()}dashboard.html">
      <div class="sidebar-logo">
        <span class="sidebar-logo-letters">DA</span>
        <div class="sidebar-logo-ring"></div>
      </div>
      <div class="sidebar-brand-text">
        <div class="sidebar-brand-name">Distribuidora <span class="brand-accent">Andina</span></div>
        <div class="sidebar-brand-sub">SRL · Sistema de Gestión</div>
      </div>
    </a>
    <nav class="sidebar-nav">
  `;

  menu.forEach(item => {
    if (item.section) {
      html += `<div class="sidebar-section-label">${item.section}</div>`;
    } else {
      const isActive = item.key === activeKey ? 'active' : '';
      html += `
        <a class="sidebar-item ${isActive}" href="${item.href}">
          <i class="bi ${item.icon}"></i>
          <span>${item.label}</span>
        </a>
      `;
    }
  });

  html += `</nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-user-avatar">${initials}</div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name">${session.nombre}</div>
          <div class="sidebar-user-role">${session.rol}</div>
        </div>
      </div>
    </div>`;

  const sidebar = document.getElementById('sidebar');
  if (sidebar) sidebar.innerHTML = html;
}

function buildNavbar(pageTitle) {
  const session = getSession();
  if (!session) return;
  const initials = session.nombre.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
  const navbar = document.getElementById('navbar');
  if (!navbar) return;

  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';

  navbar.innerHTML = `
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <div class="navbar-title">${pageTitle}</div>
    <div class="navbar-actions">
      <button class="theme-toggle" id="themeToggleBtn" onclick="toggleTheme()" title="${isDark ? 'Modo claro' : 'Modo oscuro'}">
        <i class="bi bi-${isDark ? 'sun' : 'moon'}"></i>
      </button>
      <button class="navbar-btn" title="Notificaciones" onclick="showNotifications()">
        <i class="bi bi-bell"></i>
        <span class="navbar-badge"></span>
      </button>
      <div class="navbar-user dropdown" data-bs-toggle="dropdown">
        <div class="navbar-user-avatar">${initials}</div>
        <div>
          <div class="navbar-user-name">${session.nombre}</div>
          <div class="navbar-user-role">${session.rol}</div>
        </div>
        <i class="bi bi-chevron-down" style="font-size:11px;color:var(--text-muted);margin-left:4px;"></i>
      </div>
      <ul class="dropdown-menu dropdown-menu-end" style="min-width:200px;margin-top:6px;">
        <li><h6 class="dropdown-header" style="font-family:'Poppins',sans-serif;">${session.email}</h6></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="toggleTheme()"><i class="bi bi-moon me-2"></i>Cambiar tema</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="logout()"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Cerrar sesión</a></li>
      </ul>
    </div>
  `;

  const overlay = document.createElement('div');
  overlay.className = 'sidebar-overlay';
  overlay.id = 'sidebarOverlay';
  overlay.onclick = closeSidebar;
  document.body.appendChild(overlay);
}

function toggleSidebar() {
  document.getElementById('sidebar')?.classList.toggle('open');
  document.getElementById('sidebarOverlay')?.classList.toggle('show');
}

function closeSidebar() {
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('show');
}

function showNotifications() {
  showToast('No hay notificaciones nuevas', 'info');
}

// ===================== DARK MODE =====================
function toggleTheme() {
  const html = document.documentElement;
  const current = html.getAttribute('data-theme') || 'light';
  const next = current === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', next);
  localStorage.setItem('andina_theme', next);

  const btn = document.getElementById('themeToggleBtn');
  if (btn) {
    btn.title = next === 'dark' ? 'Modo claro' : 'Modo oscuro';
    btn.innerHTML = `<i class="bi bi-${next === 'dark' ? 'sun' : 'moon'}"></i>`;
  }

  const menuItem = document.querySelector('.dropdown-menu .bi-moon, .dropdown-menu .bi-sun');
  if (menuItem) {
    menuItem.className = `bi bi-${next === 'dark' ? 'sun' : 'moon'} me-2`;
  }

  showToast(next === 'dark' ? 'Modo oscuro activado' : 'Modo claro activado', 'info', 2000);
}

function initTheme() {
  const saved = localStorage.getItem('andina_theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
}

// ===================== LOGOUT =====================
function logout() {
  Swal.fire({
    title: '¿Cerrar sesión?',
    text: 'Se cerrará tu sesión actual.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, salir',
    cancelButtonText: 'Cancelar',
    reverseButtons: true,
  }).then(result => {
    if (result.isConfirmed) {
      clearSession();
      window.location.href = getRoot() + 'index.html';
    }
  });
}

// ===================== FETCH UTILITIES =====================
function showLoader() {
  let loader = document.getElementById('pageLoader');
  if (!loader) {
    loader = document.createElement('div');
    loader.id = 'pageLoader';
    loader.className = 'page-loader';
    loader.innerHTML = '<div class="loader-spinner"></div>';
    document.body.appendChild(loader);
  }
  loader.style.display = 'flex';
}

function hideLoader() {
  const loader = document.getElementById('pageLoader');
  if (loader) loader.style.display = 'none';
}

async function apiRequest(accion, data = {}, method = 'POST') {
  showLoader();
  try {
    const session = getSession();
    const token = session ? session.token || '' : '';
    const url = `${API_BASE}?accion=${accion}`;
    const options = {
      method,
      headers: { 'Content-Type': 'application/json', 'X-Token': token },
    };
    if (method !== 'GET') options.body = JSON.stringify(data);

    const res = await fetch(url, options);
    const json = await res.json();
    hideLoader();
    return json;
  } catch (e) {
    hideLoader();
    return { exito: false, mensaje: 'Error de conexión con el servidor', _offline: true };
  }
}

// ===================== TOAST =====================
function showToast(mensaje, tipo = 'success', duracion = 3500) {
  const iconMap = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  const colorMap = { success: '#28a745', error: '#dc3545', warning: '#ffc107', info: '#1a3a5c' };

  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = 'position:fixed;top:80px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:320px;';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.style.cssText = `background:#fff;border-radius:10px;padding:12px 16px;box-shadow:0 4px 20px rgba(0,0,0,0.12);display:flex;align-items:center;gap:10px;animation:fadeInUp 0.3s ease;border-left:4px solid ${colorMap[tipo]};min-width:260px;`;
  toast.innerHTML = `
    <i class="bi ${iconMap[tipo]}" style="color:${colorMap[tipo]};font-size:18px;flex-shrink:0;"></i>
    <span style="font-size:13.5px;color:#2d3748;flex:1;">${mensaje}</span>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;color:#a0aec0;font-size:16px;cursor:pointer;padding:0;line-height:1;">&times;</button>
  `;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), duracion);
}

// ===================== FORMATO BOLIVIANO =====================
function formatBs(valor) {
  return 'Bs. ' + Number(valor || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatFecha(fecha) {
  if (!fecha) return '—';
  const d = new Date(fecha);
  const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  return `${d.getDate()} de ${meses[d.getMonth()]}, ${d.getFullYear()}`;
}

function formatFechaCorta(fecha) {
  if (!fecha) return '—';
  const d = new Date(fecha);
  return d.toLocaleDateString('es-BO');
}

function formatDateTime(fecha) {
  if (!fecha) return '—';
  const d = new Date(fecha);
  return d.toLocaleString('es-BO');
}

// ===================== BADGE STATUS =====================
function getBadgeEstado(estado) {
  const map = {
    'Completado': 'badge-completado', 'completado': 'badge-completado',
    'Pendiente': 'badge-pendiente', 'pendiente': 'badge-pendiente',
    'Aprobado': 'badge-aprobado', 'aprobado': 'badge-aprobado',
    'Rechazado': 'badge-rechazado', 'rechazado': 'badge-rechazado',
    'Crítico': 'badge-critico', 'critico': 'badge-critico',
    'Normal': 'badge-normal', 'normal': 'badge-normal',
    'Alerta': 'badge-alerta', 'alerta': 'badge-alerta',
    'Agotado': 'badge-agotado', 'agotado': 'badge-agotado',
    'Recibido': 'badge-recibido', 'recibido': 'badge-recibido',
    'En tránsito': 'badge-pendiente',
    'Activo': 'badge-completado', 'activo': 'badge-completado',
    'Inactivo': 'badge-agotado', 'inactivo': 'badge-agotado',
  };
  const cls = map[estado] || 'bg-secondary';
  return `<span class="badge ${cls}">${estado}</span>`;
}

function getBadgeRol(rol) {
  const map = { Administrador: 'badge-admin', Gerente: 'badge-gerente', Empleado: 'badge-empleado', Cliente: 'badge-cliente' };
  return `<span class="badge ${map[rol] || 'bg-secondary'}">${rol}</span>`;
}

// ===================== INICIALIZAR PÁGINA =====================
function initPage(config = {}) {
  const { activeKey = '', pageTitle = 'Dashboard', requireLogin = true } = config;
  let session = null;
  if (requireLogin) {
    session = requireAuth();
    if (!session) return null;
  }
  buildSidebar(activeKey);
  buildNavbar(pageTitle);
  applyRolePermissions();
  return session;
}

function applyRolePermissions() {
  const session = getSession();
  if (!session) return;
  const rol = session.rol;

  // Ocultar botones de eliminar si no es admin
  if (rol !== 'Administrador') {
    document.querySelectorAll('[data-role-admin]').forEach(el => el.style.display = 'none');
  }
  // Ocultar acciones de admin y gerente para empleado
  if (rol === 'Empleado') {
    document.querySelectorAll('[data-role-manager]').forEach(el => el.style.display = 'none');
  }
  // Mostrar según rol
  document.querySelectorAll('[data-role]').forEach(el => {
    const roles = el.dataset.role.split(',').map(r => r.trim());
    if (!roles.includes(rol)) el.style.display = 'none';
  });
}

// ===================== DATOS MOCK =====================
const MOCK_DATA = {
  productos: [
    { id: 1, codigo: 'PIL-001', nombre: 'Leche Entera PIL 1L', categoria: 'Lácteos', precio: 5.50, stock: 340, stock_min: 50, stock_max: 500, estado: 'Normal', imagen: 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=300&q=80', descripcion: 'Leche entera pasteurizada de alta calidad' },
    { id: 2, codigo: 'NES-002', nombre: 'Café Nescafé 200g', categoria: 'Bebidas', precio: 15.00, stock: 12, stock_min: 20, stock_max: 200, estado: 'Crítico', imagen: 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=300&q=80', descripcion: 'Café soluble instantáneo premium' },
    { id: 3, codigo: 'VEN-003', nombre: 'Arroz Grano de Oro 1kg', categoria: 'Granos', precio: 8.00, stock: 85, stock_min: 30, stock_max: 300, estado: 'Normal', imagen: 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=300&q=80', descripcion: 'Arroz de grano largo seleccionado' },
    { id: 4, codigo: 'COC-004', nombre: 'Aceite Cocinero 900ml', categoria: 'Aceites', precio: 12.50, stock: 24, stock_min: 25, stock_max: 200, estado: 'Alerta', imagen: 'https://images.unsplash.com/photo-1474979266404-7eaacbcd87c5?w=300&q=80', descripcion: 'Aceite vegetal refinado para cocinar' },
    { id: 5, codigo: 'AZU-005', nombre: 'Azúcar Blanca 1kg', categoria: 'Dulces', precio: 6.00, stock: 180, stock_min: 40, stock_max: 400, estado: 'Normal', imagen: 'https://images.unsplash.com/photo-1558560699-2e620c5e54d0?w=300&q=80', descripcion: 'Azúcar blanca refinada de caña' },
    { id: 6, codigo: 'PIL-006', nombre: 'Yogurt PIL Frutado 200g', categoria: 'Lácteos', precio: 4.50, stock: 0, stock_min: 30, stock_max: 200, estado: 'Agotado', imagen: 'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=300&q=80', descripcion: 'Yogurt cremoso con frutas tropicales' },
    { id: 7, codigo: 'SAL-007', nombre: 'Sal de Mesa 1kg', categoria: 'Condimentos', precio: 2.50, stock: 210, stock_min: 50, stock_max: 500, estado: 'Normal', imagen: 'https://images.unsplash.com/photo-1518110925495-5fe2fda5f761?w=300&q=80', descripcion: 'Sal yodada de mesa fina' },
    { id: 8, codigo: 'FID-008', nombre: 'Fideo Lucchetti 500g', categoria: 'Pastas', precio: 7.50, stock: 95, stock_min: 30, stock_max: 300, estado: 'Normal', imagen: 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=300&q=80', descripcion: 'Fideos de trigo durum premium' },
  ],
  clientes: [
    { id: 1, razon_social: 'Tienda Doña María', nit_ci: '1234567', contacto: 'María Gutiérrez', telefono: '77812345', email: 'donamaria@gmail.com', direccion: 'Av. Monseñor Rivero 234', estado: 'Activo' },
    { id: 2, razon_social: 'Supermercado La Economía', nit_ci: '9876543', contacto: 'Pedro Vásquez', telefono: '76543210', email: 'laeconomia@gmail.com', direccion: 'Av. Cristo Redentor 1200', estado: 'Activo' },
    { id: 3, razon_social: 'Abarrotes El Sol', nit_ci: '5551234', contacto: 'Rosa Mamani', telefono: '78965412', email: 'elsol@outlook.com', direccion: 'Calle Libertad 456', estado: 'Activo' },
    { id: 4, razon_social: 'Minimarket Familiar', nit_ci: '3330987', contacto: 'Juan Pérez', telefono: '71234567', email: 'minimarket@gmail.com', direccion: 'Barrio Las Palmas s/n', estado: 'Inactivo' },
  ],
  proveedores: [
    { id: 1, razon_social: 'PIL Andina SA', nit: '1000001', contacto: 'Álvaro Meneses', telefono: '33445566', email: 'ventas@pilandina.com.bo', ciudad: 'Santa Cruz', estado: 'Activo' },
    { id: 2, razon_social: 'Nestlé Bolivia', nit: '1000002', contacto: 'Laura Quiroga', telefono: '33556677', email: 'ventas@nestle.com.bo', ciudad: 'La Paz', estado: 'Activo' },
    { id: 3, razon_social: 'Industrias Venado', nit: '1000003', contacto: 'Carlos Soto', telefono: '33667788', email: 'pedidos@venado.com.bo', ciudad: 'Cochabamba', estado: 'Activo' },
    { id: 4, razon_social: 'Cocinero SA', nit: '1000004', contacto: 'Mario Flores', telefono: '33778899', email: 'ventas@cocinero.com.bo', ciudad: 'Santa Cruz', estado: 'Activo' },
  ],
  pedidos: [
    { id: 1, codigo: 'PED-2026-001', id_cliente: 1, cliente: 'Tienda Doña María', fecha: '2026-04-28', estado: 'Completado', total: 245.50, creado_por: 'Ana Torres', id_usuario: 3 },
    { id: 2, codigo: 'PED-2026-002', id_cliente: 2, cliente: 'Supermercado La Economía', fecha: '2026-04-29', estado: 'Pendiente', total: 1320.00, creado_por: 'Carlos Rodríguez', id_usuario: 2 },
    { id: 3, codigo: 'PED-2026-003', id_cliente: 3, cliente: 'Abarrotes El Sol', fecha: '2026-04-30', estado: 'Completado', total: 87.25, creado_por: 'Ana Torres', id_usuario: 3 },
    { id: 4, codigo: 'PED-2026-004', id_cliente: 1, cliente: 'Tienda Doña María', fecha: '2026-05-01', estado: 'Pendiente', total: 456.75, creado_por: 'Monse Salcedo', id_usuario: 1 },
    { id: 5, codigo: 'PED-2026-005', id_cliente: 2, cliente: 'Supermercado La Economía', fecha: '2026-05-02', estado: 'En tránsito', total: 2100.00, creado_por: 'Carlos Rodríguez', id_usuario: 2 },
  ],
  compras: [
    { id: 1, codigo: 'OC-2026-001', id_proveedor: 1, proveedor: 'PIL Andina SA', fecha: '2026-04-25', estado: 'Aprobado', total: 3500.00 },
    { id: 2, codigo: 'OC-2026-002', id_proveedor: 2, proveedor: 'Nestlé Bolivia', fecha: '2026-04-28', estado: 'Pendiente', total: 1850.00 },
    { id: 3, codigo: 'OC-2026-003', id_proveedor: 3, proveedor: 'Industrias Venado', fecha: '2026-04-30', estado: 'Recibido', total: 2200.00 },
    { id: 4, codigo: 'OC-2026-004', id_proveedor: 4, proveedor: 'Cocinero SA', fecha: '2026-05-01', estado: 'Pendiente', total: 980.00 },
  ],
  usuarios: [
    { id: 1, username: 'admin', nombre: 'Administrador', email: 'admin@andina.bo', rol: 'Administrador', estado: 'Activo', ultimo_acceso: '2026-05-02 08:30', verificado: true },
    { id: 2, username: 'gerente', nombre: 'Gerente', email: 'gerente@andina.bo', rol: 'Gerente', estado: 'Activo', ultimo_acceso: '2026-05-02 09:15', verificado: true },
    { id: 3, username: 'empleado', nombre: 'Empleado', email: 'empleado@andina.bo', rol: 'Empleado', estado: 'Activo', ultimo_acceso: '2026-05-02 07:45', verificado: true },
    { id: 4, username: 'cliente', nombre: 'Cliente Demo', email: 'cliente@demo.com', rol: 'Cliente', estado: 'Activo', ultimo_acceso: '2026-05-01 14:20', verificado: true },
  ],
  logs_acceso: [
    { id: 1, usuario: 'admin', ip: '192.168.1.10', fecha: '2026-05-02 08:30:12', resultado: 'Éxito' },
    { id: 2, usuario: 'gerente', ip: '192.168.1.15', fecha: '2026-05-02 09:15:03', resultado: 'Éxito' },
    { id: 3, usuario: 'empleado', ip: '192.168.1.22', fecha: '2026-05-02 07:45:55', resultado: 'Éxito' },
    { id: 4, usuario: 'intruso', ip: '45.62.134.200', fecha: '2026-05-01 23:12:44', resultado: 'Fallido' },
    { id: 5, usuario: 'admin', ip: '192.168.1.10', fecha: '2026-05-01 18:00:11', resultado: 'Éxito' },
  ],
  otps: [
    { id: 1, usuario: 'admin', codigo: '••••••', tipo: 'Registro', estado: 'Usado', creado: '2026-04-20 10:00', expira: '2026-04-20 10:10' },
    { id: 2, usuario: 'gerente', codigo: '••••••', tipo: 'Registro', estado: 'Usado', creado: '2026-04-21 11:00', expira: '2026-04-21 11:10' },
    { id: 3, usuario: 'empleado', codigo: '••••••', tipo: 'Registro', estado: 'Usado', creado: '2026-04-22 09:30', expira: '2026-04-22 09:40' },
  ],
};

// Apply theme as early as possible (before any render)
(function() {
  const saved = localStorage.getItem('andina_theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
})();

// Make accessible globally
window.Andina = {
  getSession, setSession, clearSession, requireAuth, requireClienteAuth,
  hasPermission, initPage, buildSidebar, buildNavbar,
  showToast, showLoader, hideLoader, apiRequest, logout,
  formatBs, formatFecha, formatFechaCorta, formatDateTime,
  getBadgeEstado, getBadgeRol, MOCK_DATA, getRoot,
  applyRolePermissions, toggleTheme, initTheme,
};
