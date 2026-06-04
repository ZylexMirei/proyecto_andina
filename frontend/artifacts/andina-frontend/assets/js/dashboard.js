/**
 * DISTRIBUIDORA ANDINA SRL — dashboard.js
 * Dashboard visual avanzado: gauges circulares, gráficas con gradiente, barras neon
 */
'use strict';

document.addEventListener('DOMContentLoaded', async () => {
  const session = Andina.initPage({ activeKey:'dashboard', pageTitle:'Dashboard' });
  if (!session) return;
  
  // Cargar datos reales de la BD para que las gráficas funcionen
  const resProd = await Andina.apiRequest('listar_productos', {}, 'GET');
  const resPed = await Andina.apiRequest('listar_pedidos', {}, 'GET');
  
  if (resProd.exito && resProd.productos) Andina.MOCK_DATA.productos = resProd.productos.map(p => ({...p, id: p.id_producto, precio: p.precio_referencia, stock: p.stock_total || 0, stock_min: 20, stock_max: 2000, estado: p.estado || 'Activo'}));
  if (resPed.exito && resPed.pedidos) Andina.MOCK_DATA.pedidos = resPed.pedidos.map(p => ({...p, total: parseFloat(p.total) || 0}));

  buildDashboard(session.rol, session);
});

function isDark() {
  return document.documentElement.getAttribute('data-theme') === 'dark';
}

// Helper: SVG circular gauge
function svgGauge(pct, color1, color2, size=100, stroke=10) {
  const r = (size - stroke * 2) / 2;
  const circ = 2 * Math.PI * r;
  const offset = circ * (1 - pct / 100);
  const cx = size / 2, cy = size / 2;
  const id = 'g' + Math.random().toString(36).slice(2,7);
  return `
    <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="transform:rotate(-90deg);">
      <defs>
        <linearGradient id="${id}" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stop-color="${color1}"/>
          <stop offset="100%" stop-color="${color2}"/>
        </linearGradient>
      </defs>
      <circle cx="${cx}" cy="${cy}" r="${r}" fill="none"
        stroke="rgba(255,255,255,0.08)" stroke-width="${stroke}"/>
      <circle cx="${cx}" cy="${cy}" r="${r}" fill="none"
        stroke="url(#${id})" stroke-width="${stroke}"
        stroke-linecap="round"
        stroke-dasharray="${circ}"
        stroke-dashoffset="${offset}"
        style="transition:stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1);"/>
    </svg>`;
}

// Helper: neon progress bar
function progressBar(pct, color1, color2, label, value) {
  return `
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:12px;">
        <span style="color:var(--text-muted);font-weight:500;">${label}</span>
        <span style="font-family:'Poppins',sans-serif;font-weight:700;color:var(--text);">${value}</span>
      </div>
      <div style="height:8px;background:rgba(255,255,255,0.06);border-radius:4px;overflow:hidden;box-shadow:inset 0 1px 2px rgba(0,0,0,0.2);">
        <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,${color1},${color2});border-radius:4px;transition:width 1.2s ease;box-shadow:0 0 8px ${color2}55;"></div>
      </div>
    </div>`;
}

function buildDashboard(rol, session) {
  const mc = document.getElementById('main-content');
  const productos = Andina.MOCK_DATA.productos;
  const pedidos = Andina.MOCK_DATA.pedidos;
  const clientes = Andina.MOCK_DATA.clientes;
  const compras = Andina.MOCK_DATA.compras;
  const alertasStock = productos.filter(p => p.estado === 'Crítico' || p.estado === 'Agotado').length;
  const totalVentas = pedidos.reduce((a,b) => a + b.total, 0);
  const pedPendientes = pedidos.filter(p => p.estado === 'Pendiente').length;
  const pedCompletados = pedidos.filter(p => p.estado === 'Completado').length;
  const pedTransito = pedidos.filter(p => p.estado === 'En tránsito').length;
  const metaMensual = 20000;
  const avancePct = Math.min(100, Math.round((totalVentas / (metaMensual * 4)) * 100));
  const clientesActivos = clientes.filter(c => c.estado === 'Activo').length;
  const stockNormal = productos.filter(p => p.estado === 'Normal').length;

  if (rol === 'Empleado') { renderEmpleadoDashboard(mc, session, pedidos, productos); return; }

  const hora = new Date().getHours();
  const saludo = hora < 12 ? 'Buenos días' : hora < 18 ? 'Buenas tardes' : 'Buenas noches';

  mc.innerHTML = `
    <!-- HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title">
          <i class="bi bi-speedometer2 me-2" style="color:var(--accent);font-size:20px;"></i>
          ${saludo}, ${session.nombre.split(' ')[0]}
        </h1>
        <p class="page-subtitle">${Andina.formatFecha(new Date())} &nbsp;·&nbsp; ${session.rol}</p>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <span class="live-dot-badge">
          <span class="live-dot"></span> Tiempo real
        </span>
        <a href="reportes/dashboard.html" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-bar-chart-line me-1"></i>Reportes
        </a>
      </div>
    </div>

    <!-- FILA 1: GAUGES KPI -->
    <div class="row g-3 mb-4">
      ${[
        { label:'Ventas acumuladas', pct: avancePct, val: Andina.formatBs(totalVentas), sub:'Meta: Bs. 80,000', c1:'#00c6ff', c2:'#0072ff' },
        { label:'Pedidos completados', pct: Math.round((pedCompletados/pedidos.length)*100), val: pedCompletados+'/'+pedidos.length, sub:'Total de pedidos', c1:'#00f260', c2:'#0575e6' },
        { label:'Clientes activos', pct: Math.round((clientesActivos/clientes.length)*100), val: clientesActivos+'/'+clientes.length, sub:'Base de clientes', c1:'#f7971e', c2:'#ffd200' },
        { label:'Stock en orden', pct: Math.round((stockNormal/productos.length)*100), val: stockNormal+'/'+productos.length, sub:'Productos ok', c1:'#11998e', c2:'#38ef7d' },
      ].map(({label,pct,val,sub,c1,c2})=>`
        <div class="col-6 col-lg-3">
          <div class="gauge-card animate-card">
            <div class="gauge-ring">
              ${svgGauge(pct, c1, c2, 110, 11)}
              <div class="gauge-center">
                <div class="gauge-pct" style="background:linear-gradient(135deg,${c1},${c2});-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">${pct}%</div>
              </div>
            </div>
            <div class="gauge-info">
              <div class="gauge-val">${val}</div>
              <div class="gauge-label">${label}</div>
              <div class="gauge-sub">${sub}</div>
            </div>
          </div>
        </div>`).join('')}
    </div>

    <!-- FILA 2: CHART + PROGRESS BARS -->
    <div class="row g-3 mb-4">
      <!-- Gráfica ventas -->
      <div class="col-lg-8">
        <div class="dash-card h-100">
          <div class="dash-card-header">
            <span><i class="bi bi-trending-up me-2" style="color:#00c6ff;"></i>Ventas mensuales 2026</span>
            <div class="d-flex gap-1">
              <button class="dash-btn-sm active" onclick="setChartType('line',this)">Línea</button>
              <button class="dash-btn-sm" onclick="setChartType('bar',this)">Barras</button>
            </div>
          </div>
          <div class="dash-card-body"><div style="position:relative;height:260px;"><canvas id="chartVentas"></canvas></div></div>
        </div>
      </div>

      <!-- Progress bars por categoría -->
      <div class="col-lg-4">
        <div class="dash-card h-100">
          <div class="dash-card-header">
            <span><i class="bi bi-layers me-2" style="color:#38ef7d;"></i>Stock por categoría</span>
          </div>
          <div class="dash-card-body pt-2">
            ${progressBar(68,'#00c6ff','#0072ff','Lácteos','231 u.')}
            ${progressBar(12,'#f7971e','#ffd200','Bebidas','12 u.')}
            ${progressBar(85,'#11998e','#38ef7d','Granos','85 u.')}
            ${progressBar(24,'#f7971e','#ff4e50','Aceites','24 u.')}
            ${progressBar(100,'#11998e','#38ef7d','Condimentos','210 u.')}
            ${progressBar(95,'#667eea','#764ba2','Pastas','95 u.')}
            <div style="height:1px;background:var(--border);margin:16px 0;"></div>
            <div class="d-flex justify-content-between" style="font-size:12px;color:var(--text-muted);">
              <span><i class="bi bi-circle-fill me-1" style="color:#00c6ff;font-size:8px;"></i>Normal</span>
              <span><i class="bi bi-circle-fill me-1" style="color:#f7971e;font-size:8px;"></i>Alerta</span>
              <span><i class="bi bi-circle-fill me-1" style="color:#11998e;font-size:8px;"></i>Alto</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- FILA 3: MINI STATS + DONA + BARRA ESTADO -->
    <div class="row g-3 mb-4">
      <!-- Mini KPI boxes -->
      <div class="col-lg-3">
        <div class="row g-3 h-100">
          ${[
            { icon:'bi-cart3', val: compras.length, lbl:'Órdenes de compra', c:'#00c6ff' },
            { icon:'bi-truck', val: Andina.MOCK_DATA.proveedores.length, lbl:'Proveedores activos', c:'#38ef7d' },
            { icon:'bi-person-exclamation', val: pedPendientes, lbl:'Pedidos urgentes', c:'#ffd200' },
            { icon:'bi-exclamation-diamond', val: alertasStock, lbl:'Alertas inventario', c:'#ff4e50' },
          ].map(({icon,val,lbl,c})=>`
            <div class="col-6">
              <div class="mini-kpi-card animate-card">
                <div class="mini-kpi-icon" style="color:${c};background:${c}1a;">
                  <i class="bi ${icon}"></i>
                </div>
                <div class="mini-kpi-val" style="color:${c};">${val}</div>
                <div class="mini-kpi-lbl">${lbl}</div>
              </div>
            </div>`).join('')}
        </div>
      </div>

      <!-- Dona estado pedidos -->
      <div class="col-lg-3">
        <div class="dash-card h-100">
          <div class="dash-card-header"><i class="bi bi-pie-chart me-2" style="color:#764ba2;"></i>Estado de pedidos</div>
          <div class="dash-card-body d-flex flex-column align-items-center justify-content-center">
            <div style="position:relative;height:160px;width:160px;">
              <canvas id="chartDona"></canvas>
              <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                <div style="font-family:'Poppins',sans-serif;font-weight:800;font-size:22px;color:var(--text);">${pedidos.length}</div>
                <div style="font-size:10px;color:var(--text-muted);">TOTAL</div>
              </div>
            </div>
            <div class="w-100 mt-3">
              ${[['Completados',pedCompletados,'#38ef7d'],['Pendientes',pedPendientes,'#ffd200'],['En tránsito',pedTransito,'#00c6ff']].map(([l,v,c])=>`
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span style="display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text-muted);">
                    <span style="width:9px;height:9px;border-radius:50%;background:${c};box-shadow:0 0 6px ${c}88;flex-shrink:0;"></span>${l}
                  </span>
                  <span style="font-family:'Poppins',sans-serif;font-weight:700;font-size:14px;color:var(--text);">${v}</span>
                </div>`).join('')}
            </div>
          </div>
        </div>
      </div>

      <!-- Gráfica barras categorías -->
      <div class="col-lg-6">
        <div class="dash-card h-100">
          <div class="dash-card-header">
            <span><i class="bi bi-bar-chart-steps me-2" style="color:#f7971e;"></i>Productos por categoría</span>
          </div>
          <div class="dash-card-body"><div style="position:relative;height:200px;"><canvas id="chartCategorias"></canvas></div></div>
        </div>
      </div>
    </div>

    <!-- FILA 4: TABLA + ALERTAS -->
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="dash-card">
          <div class="dash-card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt me-2" style="color:#00c6ff;"></i>Últimos pedidos</span>
            <a href="pedidos/lista.html" class="dash-btn-sm">Ver todos <i class="bi bi-arrow-right ms-1"></i></a>
          </div>
          <div class="dash-card-body p-0">
            <div class="table-responsive">
              <table class="table dash-table mb-0">
                <thead><tr><th>Código</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th>Total</th></tr></thead>
                <tbody>
                  ${pedidos.slice(0,5).map(p=>`<tr>
                    <td><code style="font-size:11.5px;color:#00c6ff;">${p.codigo}</code></td>
                    <td style="font-size:13px;font-weight:500;">${p.cliente}</td>
                    <td style="font-size:12px;color:var(--text-muted);">${Andina.formatFechaCorta(p.fecha)}</td>
                    <td>${Andina.getBadgeEstado(p.estado)}</td>
                    <td><strong style="color:#38ef7d;">${Andina.formatBs(p.total)}</strong></td>
                  </tr>`).join('')}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <!-- Banner ventas total -->
        <div class="dash-banner mb-3">
          <div style="position:absolute;right:-20px;top:-20px;width:120px;height:120px;border-radius:50%;background:rgba(0,198,255,0.08);pointer-events:none;"></div>
          <div style="font-size:11px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px;">Total ventas · 2026</div>
          <div style="font-family:'Poppins',sans-serif;font-weight:800;font-size:28px;color:#fff;line-height:1;">${Andina.formatBs(totalVentas)}</div>
          <div class="mt-2" style="font-size:12px;color:rgba(255,255,255,.55);">
            ${pedidos.length} pedidos &nbsp;·&nbsp; Meta: Bs. 80,000
          </div>
          <div style="margin-top:12px;height:6px;background:rgba(255,255,255,.1);border-radius:3px;overflow:hidden;">
            <div style="height:100%;width:${avancePct}%;background:linear-gradient(90deg,#00c6ff,#38ef7d);border-radius:3px;box-shadow:0 0 10px #00c6ff66;"></div>
          </div>
          <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px;">${avancePct}% de la meta anual</div>
        </div>

        <!-- Alertas stock -->
        <div class="dash-card">
          <div class="dash-card-header d-flex justify-content-between align-items-center">
            <span style="color:#ff4e50;"><i class="bi bi-exclamation-triangle-fill me-2"></i>Alertas inventario</span>
            <a href="inventario/ver.html" class="dash-btn-sm">Ver todo</a>
          </div>
          <div class="dash-card-body p-0">
            ${productos.filter(p=>p.estado!=='Normal').map(p=>{
              const pct=Math.max(0,Math.min(100,Math.round((p.stock/p.stock_max)*100)));
              const c=p.estado==='Agotado'?'#ff4e50':p.estado==='Crítico'?'#f7971e':'#ffd200';
              return `<div style="padding:10px 16px;border-bottom:1px solid var(--border);">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span style="font-size:12.5px;font-weight:600;color:var(--text);">${p.nombre}</span>
                  ${Andina.getBadgeEstado(p.estado)}
                </div>
                <div style="height:5px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden;">
                  <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,${c}88,${c});border-radius:3px;box-shadow:0 0 6px ${c}55;transition:width 1s;"></div>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Stock: <strong style="color:${c};">${p.stock}</strong> / Mín: ${p.stock_min}</div>
              </div>`;
            }).join('')}
          </div>
        </div>
      </div>
    </div>
  `;

  // --- STYLES inline para nuevos componentes ---
  if (!document.getElementById('dash-extra-styles')) {
    const s = document.createElement('style');
    s.id = 'dash-extra-styles';
    s.textContent = `
      /* GAUGE CARD */
      .gauge-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 20px 16px;
        display: flex; flex-direction: column; align-items: center;
        box-shadow: var(--card-shadow);
        transition: all .25s ease;
        position: relative; overflow: hidden;
      }
      .gauge-card::after {
        content:''; position:absolute; inset:0; pointer-events:none;
        background: linear-gradient(180deg, rgba(255,255,255,.03) 0%, transparent 60%);
        border-radius:16px;
      }
      .gauge-card:hover { transform:translateY(-4px); box-shadow:var(--card-shadow-hover); }
      .gauge-ring { position:relative; width:110px; height:110px; flex-shrink:0; }
      .gauge-center { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
      .gauge-pct { font-family:'Poppins',sans-serif; font-weight:800; font-size:19px; line-height:1; }
      .gauge-info { margin-top:12px; text-align:center; }
      .gauge-val { font-family:'Poppins',sans-serif; font-weight:700; font-size:15px; color:var(--text); }
      .gauge-label { font-size:11.5px; color:var(--text-muted); margin-top:2px; font-weight:500; }
      .gauge-sub { font-size:10.5px; color:var(--text-muted); opacity:.6; }

      /* DASH CARD */
      .dash-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        transition: box-shadow .2s, transform .2s;
        position: relative;
      }
      .dash-card:hover { transform:translateY(-2px); box-shadow:var(--card-shadow-hover); }
      .dash-card-header {
        display: flex; align-items:center; justify-content:space-between;
        padding: 14px 18px;
        border-bottom: 1px solid var(--border);
        font-family: 'Poppins', sans-serif; font-weight:600; font-size:13px;
        color:var(--text);
        background: linear-gradient(180deg, var(--surface) 0%, var(--surface-2) 100%);
      }
      .dash-card-body { padding: 16px 18px; }

      /* MINI KPI */
      .mini-kpi-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 14px;
        text-align: center;
        box-shadow: var(--card-shadow);
        transition: all .2s ease;
      }
      .mini-kpi-card:hover { transform:translateY(-3px); box-shadow:var(--card-shadow-hover); }
      .mini-kpi-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items:center; justify-content:center;
        font-size: 18px; margin: 0 auto 8px;
      }
      .mini-kpi-val { font-family:'Poppins',sans-serif; font-weight:800; font-size:22px; line-height:1; }
      .mini-kpi-lbl { font-size:10.5px; color:var(--text-muted); margin-top:4px; font-weight:500; }

      /* LIVE DOT BADGE */
      .live-dot-badge {
        display:inline-flex; align-items:center; gap:7px;
        font-size:12px; color:var(--accent);
        background:rgba(0,168,89,0.1); padding:6px 14px;
        border-radius:20px; border:1px solid rgba(0,168,89,0.2);
        font-weight:600;
      }
      .live-dot {
        width:7px; height:7px; border-radius:50%;
        background:var(--accent); flex-shrink:0;
        animation:pulse-badge 1.5s ease-in-out infinite;
        box-shadow:0 0 6px var(--accent);
      }

      /* DASH BTN SM */
      .dash-btn-sm {
        background:var(--surface-2); border:1px solid var(--border);
        color:var(--text-muted); padding:4px 11px;
        border-radius:6px; font-size:11.5px; font-weight:600;
        cursor:pointer; transition:all .2s; text-decoration:none;
        display:inline-flex; align-items:center; gap:4px;
      }
      .dash-btn-sm:hover, .dash-btn-sm.active {
        background:var(--primary); color:#fff; border-color:var(--primary);
      }

      /* DASH BANNER (ventas) */
      .dash-banner {
        background: linear-gradient(135deg, #0a1628 0%, #1a3a5c 50%, #1e4d8c 100%);
        border: 1px solid rgba(0,198,255,0.2);
        border-radius: 14px;
        padding: 20px;
        position: relative; overflow: hidden;
        box-shadow: 0 4px 24px rgba(0,198,255,0.1);
      }

      /* DASH TABLE */
      .dash-table { font-size:13px; }
      .dash-table thead th {
        background: linear-gradient(135deg,#0f172a,#1a3a5c) !important;
        color:#fff !important;
        font-size:10.5px !important;
        padding:10px 14px !important;
        text-transform:uppercase; letter-spacing:.5px;
        border:none !important;
      }
      .dash-table tbody td {
        padding:10px 14px;
        border-color:var(--border) !important;
        color:var(--text);
      }
      .dash-table tbody tr:hover td { background:rgba(0,198,255,0.04) !important; }
    `;
    document.head.appendChild(s);
  }

  initCharts(pedidos, productos);
}

let chartVentasInst = null;

function initCharts(pedidos, productos) {
  const cc = isDark()
    ? { grid:'rgba(255,255,255,0.05)', text:'#64748b' }
    : { grid:'rgba(0,0,0,0.04)', text:'#94a3b8' };

  /* ---- VENTAS LINE ---- */
  const ctxV = document.getElementById('chartVentas');
  if (ctxV) {
    const ctx = ctxV.getContext('2d');
    const gradFill = ctx.createLinearGradient(0, 0, 0, 260);
    gradFill.addColorStop(0, 'rgba(0,198,255,0.35)');
    gradFill.addColorStop(1, 'rgba(0,198,255,0)');

    chartVentasInst = new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
        datasets: [
          {
            label: 'Ventas (Bs.)',
            data: [18500,22300,19800,25600,4210,0,0,0,0,0,0,0],
            borderColor: '#00c6ff',
            borderWidth: 3,
            backgroundColor: gradFill,
            fill: true,
            tension: 0.45,
            pointRadius: 5,
            pointBackgroundColor: '#00c6ff',
            pointBorderColor: 'var(--surface)',
            pointBorderWidth: 2,
            pointHoverRadius: 7,
          },
          {
            label: 'Meta',
            data: Array(12).fill(20000),
            borderColor: '#38ef7d',
            borderWidth: 2,
            borderDash: [6,5],
            fill: false,
            tension: 0,
            pointRadius: 0,
          }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position:'top', labels:{ color:cc.text, usePointStyle:true, boxWidth:9, font:{size:11} } },
          tooltip: {
            backgroundColor: isDark() ? '#1e293b' : '#fff',
            titleColor: isDark() ? '#f1f5f9' : '#1e293b',
            bodyColor: isDark() ? '#94a3b8' : '#64748b',
            borderColor: isDark() ? 'rgba(0,198,255,0.2)' : '#e2e8f0',
            borderWidth:1, cornerRadius:10, padding:12,
            callbacks: { label: c => 'Bs. ' + c.parsed.y.toLocaleString('es-BO') },
          }
        },
        scales: {
          y: { beginAtZero:true, grid:{color:cc.grid}, ticks:{color:cc.text, callback:v=>'Bs.'+Math.round(v/1000)+'k'} },
          x: { grid:{display:false}, ticks:{color:cc.text} }
        }
      }
    });
  }

  /* ---- DONA PEDIDOS ---- */
  const ctxD = document.getElementById('chartDona');
  if (ctxD) {
    const ped = Andina.MOCK_DATA.pedidos;
    new Chart(ctxD.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Completados','Pendientes','En tránsito'],
        datasets: [{
          data: [ped.filter(p=>p.estado==='Completado').length, ped.filter(p=>p.estado==='Pendiente').length, ped.filter(p=>p.estado==='En tránsito').length],
          backgroundColor: ['#38ef7d','#ffd200','#00c6ff'],
          borderWidth: 0, hoverOffset: 8,
        }]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins: {
          legend:{ display:false },
          tooltip: {
            backgroundColor: isDark()?'#1e293b':'#fff',
            borderColor:'rgba(0,198,255,0.2)', borderWidth:1,
            cornerRadius:10, padding:10,
            titleColor:isDark()?'#f1f5f9':'#1e293b',
            bodyColor:isDark()?'#94a3b8':'#64748b',
          }
        },
        cutout:'72%',
      }
    });
  }

  /* ---- BARRAS CATEGORIAS ---- */
  const ctxC = document.getElementById('chartCategorias');
  if (ctxC) {
    const cats = {};
    Andina.MOCK_DATA.productos.forEach(p => { cats[p.categoria] = (cats[p.categoria] || 0) + 1; });
    const gradBars = ['#00c6ff','#38ef7d','#f7971e','#764ba2','#ff4e50','#ffd200'];
    new Chart(ctxC.getContext('2d'), {
      type: 'bar',
      data: {
        labels: Object.keys(cats),
        datasets: [{
          label: 'Productos',
          data: Object.values(cats),
          backgroundColor: gradBars.slice(0, Object.keys(cats).length),
          borderRadius: 8, borderSkipped: false,
          borderWidth:0,
        }]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins: {
          legend:{display:false},
          tooltip:{
            backgroundColor:isDark()?'#1e293b':'#fff',
            borderColor:'rgba(0,198,255,0.2)', borderWidth:1,
            cornerRadius:10, padding:10,
            titleColor:isDark()?'#f1f5f9':'#1e293b',
            bodyColor:isDark()?'#94a3b8':'#64748b',
          }
        },
        scales: {
          y:{ beginAtZero:true, grid:{color:cc.grid}, ticks:{color:cc.text, stepSize:1} },
          x:{ grid:{display:false}, ticks:{color:cc.text} }
        }
      }
    });
  }
}

function setChartType(tipo, btn) {
  if (!chartVentasInst) return;
  chartVentasInst.config.type = tipo;
  chartVentasInst.update();
  document.querySelectorAll('.dash-btn-sm').forEach(b => {
    if (b.textContent.trim() === (tipo==='line'?'Línea':'Barras')) b.classList.add('active');
    else b.classList.remove('active');
  });
}

function renderEmpleadoDashboard(container, session, pedidos, productos) {
  const misPedidos = pedidos.filter(p => p.id_usuario === session.id_usuario);
  const stockBajo = productos.filter(p => p.estado !== 'Normal');

  container.innerHTML = `
    <div class="page-header">
      <div class="page-header-left">
        <h1 class="page-title"><i class="bi bi-person-check me-2" style="color:var(--accent);font-size:20px;"></i>Hola, ${session.nombre.split(' ')[0]}</h1>
        <p class="page-subtitle">Tu resumen del día · ${Andina.formatFecha(new Date())}</p>
      </div>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-6"><div class="stat-card kpi-blue animate-card" data-icon="&#xF1AB;">
        <div class="stat-label">Productos disponibles</div>
        <div class="stat-number mt-1">${productos.filter(p=>p.estado!=='Agotado').length}</div>
        <div class="stat-change"><i class="bi bi-check-circle"></i>En stock</div>
      </div></div>
      <div class="col-6"><div class="stat-card kpi-green animate-card" data-icon="&#xF4F6;">
        <div class="stat-label">Mis pedidos</div>
        <div class="stat-number mt-1">${misPedidos.length}</div>
        <div class="stat-change"><i class="bi bi-receipt"></i>Este mes</div>
      </div></div>
    </div>
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="dash-card">
          <div class="dash-card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt me-2" style="color:#00c6ff;"></i>Mis últimos pedidos</span>
            <a href="pedidos/crear.html" class="btn btn-success btn-sm"><i class="bi bi-plus me-1"></i>Nuevo pedido</a>
          </div>
          <div class="dash-card-body p-0">
            ${misPedidos.length === 0
              ? '<div class="p-5 text-center"><i class="bi bi-inbox" style="font-size:40px;color:var(--text-muted);"></i><p class="mt-3" style="color:var(--text-muted);">No tienes pedidos aún</p></div>'
              : `<div class="table-responsive"><table class="table dash-table mb-0"><thead><tr><th>Código</th><th>Cliente</th><th>Fecha</th><th>Estado</th><th>Total</th></tr></thead><tbody>${misPedidos.map(p=>`<tr><td><code style="font-size:12px;color:#00c6ff;">${p.codigo}</code></td><td>${p.cliente}</td><td>${Andina.formatFechaCorta(p.fecha)}</td><td>${Andina.getBadgeEstado(p.estado)}</td><td><strong style="color:#38ef7d;">${Andina.formatBs(p.total)}</strong></td></tr>`).join('')}</tbody></table></div>`}
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="dash-card mb-3">
          <div class="dash-card-header"><i class="bi bi-lightning-charge me-2" style="color:#ffd200;"></i>Acciones rápidas</div>
          <div class="dash-card-body">
            <div class="row g-2">
              <div class="col-6"><a href="pedidos/crear.html" class="quick-action"><i class="bi bi-plus-circle"></i><span>Nuevo pedido</span></a></div>
              <div class="col-6"><a href="clientes/lista.html" class="quick-action"><i class="bi bi-person-plus"></i><span>Clientes</span></a></div>
              <div class="col-6"><a href="productos/lista.html" class="quick-action"><i class="bi bi-box-seam"></i><span>Productos</span></a></div>
              <div class="col-6"><a href="inventario/ver.html" class="quick-action"><i class="bi bi-clipboard2-data"></i><span>Inventario</span></a></div>
            </div>
          </div>
        </div>
        <div class="dash-card">
          <div class="dash-card-header"><i class="bi bi-exclamation-triangle me-2" style="color:#ff4e50;"></i>Stock bajo</div>
          <div class="dash-card-body p-0">
            ${stockBajo.slice(0,4).map(p=>`<div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;"><div style="font-size:12.5px;font-weight:600;color:var(--text);">${p.nombre}</div>${Andina.getBadgeEstado(p.estado)}</div>`).join('')}
          </div>
        </div>
      </div>
    </div>
  `;

  if (!document.getElementById('dash-extra-styles')) {
    const s = document.createElement('style');
    s.id = 'dash-extra-styles';
    s.textContent = `.dash-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;box-shadow:var(--card-shadow);overflow:hidden;transition:box-shadow .2s,transform .2s;}.dash-card-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);font-family:'Poppins',sans-serif;font-weight:600;font-size:13px;color:var(--text);background:linear-gradient(180deg,var(--surface) 0%,var(--surface-2) 100%);}.dash-card-body{padding:16px 18px;}.dash-table thead th{background:linear-gradient(135deg,#0f172a,#1a3a5c)!important;color:#fff!important;font-size:10.5px!important;padding:10px 14px!important;text-transform:uppercase;letter-spacing:.5px;border:none!important;}.dash-table tbody td{padding:10px 14px;border-color:var(--border)!important;color:var(--text);}`;
    document.head.appendChild(s);
  }
}

function aprobarOC(id, nuevoEstado) {
  Swal.fire({
    title: `¿${nuevoEstado === 'Aprobado' ? 'Aprobar' : 'Rechazar'} orden?`,
    text: `La orden OC-2026-00${id} será marcada como ${nuevoEstado}.`,
    icon: nuevoEstado === 'Aprobado' ? 'success' : 'warning',
    showCancelButton: true, confirmButtonText: 'Confirmar', cancelButtonText: 'Cancelar',
  }).then(r => {
    if (r.isConfirmed) {
      Andina.showToast(`Orden ${nuevoEstado.toLowerCase()} correctamente`, nuevoEstado === 'Aprobado' ? 'success' : 'warning');
      setTimeout(() => location.reload(), 1000);
    }
  });
}
