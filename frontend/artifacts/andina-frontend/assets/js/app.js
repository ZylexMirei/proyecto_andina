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
    if (redirect) window.location.href = getRoot() + 'login.html';
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
    window.location.href = getRoot() + 'login.html';
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
      <button class="navbar-btn position-relative" title="Notificaciones" data-bs-toggle="dropdown" id="btnNotificaciones">
        <i class="bi bi-bell"></i>
        <span class="navbar-badge" id="badgeNotificaciones" style="display:none; position:absolute; top:-2px; right:-2px; background:#ef4444; color:white; font-size:10px; width:16px; height:16px; border-radius:50%; align-items:center; justify-content:center;">0</span>
      </button>
      <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg" style="width: 320px; border: 1px solid var(--border); border-radius: 12px; overflow:hidden; z-index: 1050;">
        <div class="p-2 bg-light border-bottom text-center fw-bold" style="font-size: 13px; color: var(--text-muted);">Notificaciones de Sistema</div>
        <div id="notifContent" style="max-height: 320px; overflow-y: auto;">
          <div class="p-3 text-center text-muted" style="font-size: 13px;">Cargando...</div>
        </div>
      </div>
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

async function cargarNotificaciones() {

  const res = await apiRequest('obtener_notificaciones', {}, 'GET');
  const badge = document.getElementById('badgeNotificaciones');
  const content = document.getElementById('notifContent');
  
  if (res && res.exito && res.notificaciones) {
    const notifs = res.notificaciones;
    if (notifs.length > 0) {
      if(badge) { badge.style.display = 'flex'; badge.textContent = notifs.length; }
      if(content) {
        content.innerHTML = notifs.map(n => `
          <a href="#" class="dropdown-item d-flex align-items-start p-3 border-bottom" style="white-space: normal;">
            <div class="text-${n.tipo} me-3"><i class="bi ${n.icono} fs-5"></i></div>
            <div>
              <div style="font-size: 13px; color: var(--text); line-height: 1.4;">${n.mensaje}</div>
              <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">${n.tiempo}</div>
            </div>
          </a>`).join('');
      }
    } else {
      if(badge) badge.style.display = 'none';
      if(content) content.innerHTML = '<div class="p-4 text-center text-muted" style="font-size: 13px;"><i class="bi bi-bell-slash fs-3 d-block mb-2"></i>Todo en orden, sin alertas</div>';
    }
  }
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
  }).then(async result => {
    if (result.isConfirmed) {
      await apiRequest('logout', {}, 'POST');
      clearSession();
      window.location.href = getRoot() + 'login.html';
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
    const userId = session ? session.id_usuario || '' : '';
    const url = `${API_BASE}?accion=${accion}${userId ? '&id_usuario=' + userId : ''}`;
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
    'Confirmado': 'badge-aprobado', 'confirmado': 'badge-aprobado',
    'Enviado': 'badge-alerta', 'enviado': 'badge-alerta',
    'Entregado': 'badge-completado', 'entregado': 'badge-completado',
    'Cancelado': 'badge-rechazado', 'cancelado': 'badge-rechazado',
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

// ===================== IMAGE PREVIEW =====================
function setupImagePreview(inputId, previewContainerId) {
  const input = document.getElementById(inputId);
  const container = document.getElementById(previewContainerId);
  if (!input || !container) return;
  
  input.addEventListener('input', function() {
    const url = this.value.trim();
    if (url) {
      container.innerHTML = `<img src="${url}" style="width:100%; height:100%; object-fit:contain; border-radius:8px;" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'text-muted text-center p-4\\'><i class=\\'bi bi-image-alt fs-1\\'></i><br>Imagen no encontrada</div>'">`;
    } else {
      container.innerHTML = '<div class="text-muted text-center p-4"><i class="bi bi-image fs-1"></i><br>Vista previa</div>';
    }
  });
  if(input.value) input.dispatchEvent(new Event('input'));
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
  if (session && session.rol !== 'Cliente') setTimeout(cargarNotificaciones, 500);

  // --- FIX VISUAL GLOBAL: Evitar que la barra superior tape el contenido ---
  if (!document.getElementById('andina-layout-fix')) {
    const s = document.createElement('style');
    s.id = 'andina-layout-fix';
    s.textContent = `
      /* Asegurar que la página siempre ocupe toda la pantalla */
      body {
        min-height: 100vh !important;
        margin: 0 !important;
      }
      /* El cuadro del costado (menú lateral) ocupará siempre el 100% de la altura */
      #sidebar {
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        height: 100vh !important;
        z-index: 1000 !important;
      }
      /* Empujar el contenido principal y forzarlo a estirarse */
      #main-content {
        padding-top: 95px !important; 
        min-height: 100vh !important;
        display: flex !important;
        flex-direction: column !important;
      }
      /* El footer siempre se irá abajo del todo para no dejar huecos */
      .page-footer {
        margin-top: auto !important;
      }
      /* Darle un respiro a los controles de la tabla (Buscar, Mostrar registros) */
      .dataTables_wrapper .row:first-child {
        margin-bottom: 16px !important;
      }
      /* Asegurar que el buscador y el selector se vean bien en modo oscuro */
      .dataTables_filter input, .dataTables_length select {
        background-color: var(--surface) !important;
        color: var(--text) !important;
        border: 1px solid var(--border) !important;
      }
      /* Paginación elegante y acorde al sistema (Estilo Dash Button) */
      .dataTables_wrapper .dataTables_paginate {
        margin-top: 18px !important;
        display: flex;
        justify-content: flex-end;
        gap: 4px;
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button {
        background: transparent !important;
        border: 1px solid transparent !important;
        color: var(--text-muted) !important;
        padding: 5px 12px !important;
        border-radius: 6px !important;
        font-size: 12.5px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all .2s !important;
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--surface-2) !important;
        border-color: var(--border) !important;
        color: var(--text) !important;
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button.current,
      .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--primary) !important;
        color: #fff !important;
        border-color: var(--primary) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        opacity: 0.3 !important;
        cursor: not-allowed !important;
        background: var(--surface) !important;
        border-color: transparent !important;
        box-shadow: none !important;
      }
    `;
    document.head.appendChild(s);
  }

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

// ===================== SIMULACIÓN DE PAGO (QR / TARJETA / EFECTIVO) =====================
function procesarPagoSimulado(monto, callback) {
  const montoFormat = formatBs(monto);
  
  Swal.fire({
    title: 'Selecciona tu método de pago',
    html: `
      <div style="margin-bottom: 15px; font-weight: bold; color: var(--primary); font-size: 1.2rem;">
        Total a pagar: ${montoFormat}
      </div>
      <div class="d-flex flex-column gap-3" id="payment-options">
        <button class="btn btn-outline-primary payment-btn py-3" data-method="QR">
          <i class="bi bi-qr-code-scan me-2 fs-5"></i> Pago con QR Simple
        </button>
        <button class="btn btn-outline-info payment-btn py-3" data-method="Tarjeta">
          <i class="bi bi-credit-card me-2 fs-5"></i> Tarjeta de Crédito / Débito
        </button>
        <button class="btn btn-outline-success payment-btn py-3" data-method="Efectivo">
          <i class="bi bi-cash-coin me-2 fs-5"></i> Pago en Efectivo (Al entregar)
        </button>
      </div>
    `,
    showConfirmButton: false,
    showCancelButton: true,
    cancelButtonText: 'Cancelar compra',
    didOpen: () => {
      const btns = Swal.getHtmlContainer().querySelectorAll('.payment-btn');
      btns.forEach(btn => {
        btn.addEventListener('click', () => {
          const method = btn.getAttribute('data-method');
          Swal.close();
          mostrarSimulacionPago(method, monto, callback);
        });
      });
    }
  });
}

function mostrarSimulacionPago(metodo, monto, callback) {
  const montoFormat = formatBs(monto);
  
  if (metodo === 'QR') {
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PagoAndina${monto}`;
    Swal.fire({
      title: 'Escanea el código QR',
      html: `
        <p>Abre tu aplicación bancaria y escanea para pagar <strong>${montoFormat}</strong></p>
        <img src="${qrUrl}" alt="QR Code" style="width: 200px; height: 200px; border-radius: 10px; border: 1px solid var(--border); padding: 10px;">
        <div class="mt-3 text-muted" style="font-size: 0.85rem;"><i class="bi bi-arrow-repeat spin d-inline-block"></i> Esperando confirmación del banco...</div>
      `,
      showCancelButton: true,
      showConfirmButton: true,
      confirmButtonText: '<i class="bi bi-check-circle"></i> Simular que ya pagué',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#28a745'
    }).then((result) => {
      if (result.isConfirmed) finalizaPago(metodo, callback);
    });

  } else if (metodo === 'Tarjeta') {
    Swal.fire({
      title: 'Pago con Tarjeta',
      html: `
        <div style="text-align: left; margin-top: 10px;">
          <label class="form-label text-muted" style="font-size: 13px;">Número de Tarjeta</label>
          <input type="text" class="form-control mb-3" placeholder="0000 0000 0000 0000" value="4111 1111 1111 1111">
          <div class="row">
            <div class="col-6">
              <label class="form-label text-muted" style="font-size: 13px;">Vencimiento</label>
              <input type="text" class="form-control" placeholder="MM/AA" value="12/28">
            </div>
            <div class="col-6">
              <label class="form-label text-muted" style="font-size: 13px;">CVC</label>
              <input type="text" class="form-control" placeholder="123" value="123">
            </div>
          </div>
          <div class="mt-4 text-center fw-bold text-success fs-5">Monto a cobrar: ${montoFormat}</div>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: '<i class="bi bi-shield-lock"></i> Procesar Pago',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#28a745'
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({ title: 'Procesando pago...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        setTimeout(() => finalizaPago(metodo, callback), 1500);
      }
    });

  } else if (metodo === 'Efectivo') {
    Swal.fire({
      icon: 'info',
      title: 'Pago contra entrega',
      text: `Prepararemos tu pedido y cobraremos ${montoFormat} al momento de entregártelo.`,
      showCancelButton: true,
      confirmButtonText: 'Confirmar Pedido',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#28a745'
    }).then((result) => {
      if (result.isConfirmed) finalizaPago(metodo, callback);
    });
  }
}

function finalizaPago(metodo, callback) {
  // Lluvia de confeti
  const duration = 3000;
  const end = Date.now() + duration;

  (function frame() {
    if (typeof confetti === 'undefined') {
      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js';
      script.onload = () => frame();
      document.head.appendChild(script);
      return;
    }
    confetti({
      particleCount: 5,
      angle: 60,
      spread: 55,
      origin: { x: 0 },
      colors: ['#00c6ff', '#38ef7d']
    });
    confetti({
      particleCount: 5,
      angle: 120,
      spread: 55,
      origin: { x: 1 },
      colors: ['#00c6ff', '#38ef7d']
    });

    if (Date.now() < end) {
      requestAnimationFrame(frame);
    }
  }());

  Swal.fire({
    icon: 'success',
    title: '¡Pago Confirmado!',
    text: `El pago mediante ${metodo} ha sido verificado con éxito.`,
    timer: 2500,
    showConfirmButton: false
  }).then(() => {
    callback(metodo);
  });
}

// ===================== RESCATE DE EMERGENCIA (DATOS REALES) =====================
// Carga síncrona para inyectar MySQL en toda la app antes de que carguen las pantallas.
// ¡Esto repara la Tienda y cualquier otra pantalla automáticamente!
let productosMySQL = [];
let clientesMySQL = [];
let usuariosMySQL = [];
try {
  const xhr = new XMLHttpRequest();
  xhr.open('GET', API_BASE + '?accion=listar_productos', false); // false = síncrono
  xhr.send(null);
  if (xhr.status === 200) {
    const res = JSON.parse(xhr.responseText);
    if (res.exito && res.productos) {
      productosMySQL = res.productos.map(p => ({
        ...p, 
        id: parseInt(p.id_producto || p.id), 
        codigo: p.codigo || `PROD-${p.id_producto}`, 
        nombre: p.nombre,
        descripcion: p.descripcion || '', 
        // Atrapamos el precio sin importar cómo se llame tu columna en MySQL
        precio: parseFloat(p.precio_referencia || p.precio || p.price) || 0,
        imagen: p.imagen_principal || p.imagen || '', 
        stock: parseInt(p.stock_total || p.stock || p.cantidad) || 0,
        stock_min: 20, stock_max: 2000, estado: p.estado || 'Activo',
        // Atrapamos tu categoría real
        categoria: p.categoria || p.nombre_categoria || p.id_categoria || 'Sin categoría'
      }));
      window.productosGlobales = productosMySQL;
    }
  }

  // Rescate de Clientes Reales
  const xhrC = new XMLHttpRequest();
  xhrC.open('GET', API_BASE + '?accion=listar_clientes', false);
  xhrC.send(null);
  if (xhrC.status === 200) {
    const resC = JSON.parse(xhrC.responseText);
    if (resC.exito && resC.clientes) {
      clientesMySQL = resC.clientes.map(c => ({
        ...c, 
        id: parseInt(c.id_cliente || c.id)
      }));
    }
  }

  // Rescate de Usuarios Reales
  const xhrU = new XMLHttpRequest();
  xhrU.open('GET', API_BASE + '?accion=listar_usuarios', false);
  xhrU.send(null);
  if (xhrU.status === 200) {
    const resU = JSON.parse(xhrU.responseText);
    if (resU.exito && resU.usuarios) {
      usuariosMySQL = resU.usuarios.map(u => ({
        ...u, 
        id: parseInt(u.id_usuario || u.id)
      }));
    }
  }
} catch(e) { console.warn("Error cargando BD", e); }

// ===================== DATOS MOCK =====================
const MOCK_DATA = {
  productos: productosMySQL,
  clientes: clientesMySQL.length > 0 ? clientesMySQL : [
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
  usuarios: usuariosMySQL.length > 0 ? usuariosMySQL : [
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
  formatBs, formatFecha, formatFechaCorta, formatDateTime, procesarPagoSimulado,
  getBadgeEstado, getBadgeRol, getRoot,
  applyRolePermissions, toggleTheme, initTheme, setupImagePreview,
  MOCK_DATA,
};



