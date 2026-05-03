/**
 * DISTRIBUIDORA ANDINA SRL — productos.js
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const session = Andina.initPage({ activeKey:'productos', pageTitle:'Productos' });
  if (!session) return;

  const rol = session.rol;

  if (rol === 'Empleado') {
    document.getElementById('vistaTabla').classList.add('d-none');
    document.getElementById('vistaGrid').classList.remove('d-none');
    renderGrid(Andina.MOCK_DATA.productos);
  } else {
    renderTabla(Andina.MOCK_DATA.productos, rol);
  }

  // Buscador
  const buscador = document.getElementById('buscador');
  const filtroCategoria = document.getElementById('filtroCategoria');

  function filtrar() {
    const q = buscador.value.toLowerCase();
    const cat = filtroCategoria.value;
    const filtrados = Andina.MOCK_DATA.productos.filter(p =>
      (p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)) &&
      (!cat || p.categoria === cat)
    );
    if (rol === 'Empleado') renderGrid(filtrados);
  }

  buscador.addEventListener('input', filtrar);
  filtroCategoria.addEventListener('change', filtrar);
});

function renderTabla(productos, rol) {
  const tbody = document.getElementById('tbodyProductos');
  tbody.innerHTML = productos.map(p => {
    const imgHtml = p.imagen
      ? `<img src="${p.imagen}" alt="${p.nombre}" style="width:36px;height:36px;border-radius:8px;object-fit:cover;margin-right:10px;">`
      : `<div style="width:36px;height:36px;border-radius:8px;background:var(--secondary);display:inline-flex;align-items:center;justify-content:center;margin-right:10px;color:var(--primary);font-weight:700;font-size:13px;">${p.nombre.charAt(0)}</div>`;

    const acciones = `
      <div class="d-flex gap-1">
        <a href="crear.html?id=${p.id}" class="btn btn-outline-primary btn-xs" title="Editar" data-role-manager><i class="bi bi-pencil"></i></a>
        <button class="btn btn-outline-danger btn-xs" title="Desactivar" onclick="desactivarProducto(${p.id},'${p.nombre}')" data-role-admin><i class="bi bi-trash3"></i></button>
        <button class="btn btn-outline-secondary btn-xs" title="Ver detalle" onclick="verDetalle(${p.id})"><i class="bi bi-eye"></i></button>
      </div>`;

    return `<tr>
      <td><code style="font-size:12px;color:var(--primary);">${p.codigo}</code></td>
      <td><div class="d-flex align-items-center">${imgHtml}<div><div style="font-weight:600;font-size:13px;">${p.nombre}</div></div></div></td>
      <td><span class="badge" style="background:var(--secondary);color:var(--primary);">${p.categoria}</span></td>
      <td><strong>${Andina.formatBs(p.precio)}</strong></td>
      <td>
        <div>${p.stock} uds.</div>
        <div class="stock-bar mt-1"><div class="stock-fill ${p.stock===0?'critico':p.stock<p.stock_min?'alerta':'normal'}" style="width:${Math.min(100,(p.stock/p.stock_max)*100)}%;"></div></div>
      </td>
      <td>${Andina.getBadgeEstado(p.estado)}</td>
      <td>${acciones}</td>
    </tr>`;
  }).join('');

  $('#tablaProductos').DataTable({
    language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
    order: [[0, 'asc']],
    pageLength: 10,
    destroy: true,
  });

  // Aplicar permisos
  Andina.applyRolePermissions && setTimeout(() => Andina.applyRolePermissions(), 100);
}

function renderGrid(productos) {
  const grid = document.getElementById('gridProductos');
  if (productos.length === 0) {
    grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><i class="bi bi-search" style="font-size:40px;"></i><p class="mt-2">No se encontraron productos</p></div>';
    return;
  }
  grid.innerHTML = productos.map((p, i) => `
    <div class="col-md-4 col-lg-3 animate-card" style="animation-delay:${i*0.05}s;">
      <div class="product-card">
        <div class="product-card-img" style="height:160px;overflow:hidden;">
          ${p.imagen
            ? `<img src="${p.imagen}" alt="${p.nombre}" style="width:100%;height:100%;object-fit:cover;">`
            : `<div style="width:100%;height:100%;background:linear-gradient(135deg,var(--secondary),#c3d4f0);display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;color:var(--primary);">${p.nombre.charAt(0)}</div>`}
        </div>
        <div class="product-card-body">
          <span class="badge mb-1" style="background:var(--secondary);color:var(--primary);font-size:10.5px;">${p.categoria}</span>
          <div class="product-card-name">${p.nombre}</div>
          <div style="font-size:12px;color:#718096;margin:4px 0 8px;">${p.descripcion || 'Sin descripción'}</div>
          <div class="product-card-price">${Andina.formatBs(p.precio)}</div>
          <div class="mt-2">${Andina.getBadgeEstado(p.estado)}</div>
          <button class="btn btn-outline-primary btn-sm w-100 mt-3" onclick="verDetalle(${p.id})">
            <i class="bi bi-eye me-1"></i>Ver detalle
          </button>
        </div>
      </div>
    </div>
  `).join('');
}

function verDetalle(id) {
  const p = Andina.MOCK_DATA.productos.find(x => x.id === id);
  if (!p) return;
  const session = Andina.getSession();
  document.getElementById('detalleBody').innerHTML = `
    <div class="row">
      <div class="col-md-4 text-center">
        ${p.imagen ? `<img src="${p.imagen}" alt="${p.nombre}" style="width:100%;border-radius:10px;max-height:200px;object-fit:cover;">` : `<div style="width:100%;height:180px;background:var(--secondary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:60px;font-weight:800;color:var(--primary);">${p.nombre.charAt(0)}</div>`}
        <div class="mt-3">${Andina.getBadgeEstado(p.estado)}</div>
      </div>
      <div class="col-md-8">
        <h5 style="color:var(--primary);font-family:'Poppins',sans-serif;">${p.nombre}</h5>
        <p style="color:#718096;font-size:13.5px;">${p.descripcion || 'Sin descripción'}</p>
        <table class="table table-sm">
          <tr><td class="fw-semibold" style="width:40%">Código</td><td><code>${p.codigo}</code></td></tr>
          <tr><td class="fw-semibold">Categoría</td><td><span class="badge" style="background:var(--secondary);color:var(--primary);">${p.categoria}</span></td></tr>
          <tr><td class="fw-semibold">Precio de venta</td><td><strong style="color:var(--accent);font-size:16px;">${Andina.formatBs(p.precio)}</strong></td></tr>
          <tr><td class="fw-semibold">Stock actual</td><td><strong>${p.stock}</strong> unidades</td></tr>
          <tr><td class="fw-semibold">Stock mínimo</td><td>${p.stock_min} unidades</td></tr>
          <tr><td class="fw-semibold">Stock máximo</td><td>${p.stock_max} unidades</td></tr>
        </table>
        <div class="d-flex gap-2 mt-3">
          ${(session.rol === 'Administrador' || session.rol === 'Gerente') ? `<a href="crear.html?id=${p.id}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>` : ''}
          <a href="../pedidos/crear.html?producto=${p.id}" class="btn btn-success btn-sm"><i class="bi bi-cart-plus me-1"></i>Usar en pedido</a>
        </div>
      </div>
    </div>`;
  new bootstrap.Modal(document.getElementById('modalDetalle')).show();
}

function desactivarProducto(id, nombre) {
  Swal.fire({
    title: '¿Desactivar producto?',
    html: `El producto <strong>${nombre}</strong> será desactivado y no aparecerá en pedidos ni en la tienda.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, desactivar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#dc3545',
  }).then(async r => {
    if (r.isConfirmed) {
      await Andina.apiRequest('desactivar_producto', { id });
      Andina.showToast('Producto desactivado correctamente', 'success');
      setTimeout(() => location.reload(), 800);
    }
  });
}
