/**
 * DISTRIBUIDORA ANDINA SRL — productos.js
 */
'use strict';

let productosData = []; // Guardará los datos reales de MySQL

document.addEventListener('DOMContentLoaded', async () => {
  const session = Andina.initPage({ activeKey:'productos', pageTitle:'Productos' });
  if (!session) return;

  const rol = session.rol;

  const tbody = document.getElementById('tbodyProductos');
  if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Cargando base de datos...</td></tr>';

  // Traer los productos reales desde el backend PHP
  const res = await Andina.apiRequest('listar_productos', {}, 'GET');
  if (res.exito && res.productos) {
    // Adaptar los nombres de la BD (ej. precio_referencia) a los que usa tu diseño
    productosData = res.productos.map(p => ({
      ...p,
      id: p.id_producto,
      precio: p.precio_referencia,
      imagen: p.imagen_principal,
      stock: p.stock_total || 0,
      stock_min: 20,
      stock_max: 2000,
      estado: p.estado || 'Activo',
      categoria: p.categoria || 'Sin categoría'
   
    }));
    
    if (productosData.length === 0) {
      Andina.showToast('La BD está vacía. Añade tu primer producto.', 'info');
    }
  } else {
      Andina.showToast('Error cargando BD. Verifica conexión.', 'error');
      productosData = [];
  }

  // Poblar el filtro de categorías en la parte de arriba dinámicamente con tu BD
  const filtroCategoria = document.getElementById('filtroCategoria');
  if (filtroCategoria && productosData.length > 0) {
    const categoriasUnicas = [...new Set(productosData.map(p => p.categoria))].sort();
    const primeraOpcion = filtroCategoria.options[0] ? filtroCategoria.options[0].outerHTML : '<option value="">Todas las categorías</option>';
    filtroCategoria.innerHTML = primeraOpcion + categoriasUnicas.map(c => `<option value="${c}">${c}</option>`).join('');
  }

  if (rol === 'Empleado') {
    document.getElementById('vistaTabla').classList.add('d-none');
    document.getElementById('vistaGrid').classList.remove('d-none');
    renderGrid(productosData);
  } else {
    renderTabla(productosData, rol);
  }

  // Buscador
  const buscador = document.getElementById('buscador');

  function filtrar() {
    const q = buscador ? buscador.value.toLowerCase() : '';
    const cat = filtroCategoria ? filtroCategoria.value : '';
    const filtrados = productosData.filter(p =>
      (p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)) &&
      (!cat || p.categoria === cat)
    );
    if (rol === 'Empleado') renderGrid(filtrados);
    else renderTabla(filtrados, rol);
  }

  if (buscador) buscador.addEventListener('input', filtrar);
  if (filtroCategoria) filtroCategoria.addEventListener('change', filtrar);
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
        <button class="btn btn-outline-success btn-xs" title="Cambiar Imagen" onclick="cambiarImagenProducto(${p.id})" data-role-manager><i class="bi bi-image"></i></button>
        <button class="btn btn-outline-danger btn-xs" title="Desactivar" onclick="desactivarProducto(${p.id},'${p.nombre}')" data-role-admin><i class="bi bi-trash3"></i></button>
        <button class="btn btn-outline-secondary btn-xs" title="Ver detalle" onclick="verDetalle(${p.id})"><i class="bi bi-eye"></i></button>
      </div>`;

    return `<tr>
      <td><code style="font-size:12px;color:var(--primary);">${p.codigo}</code></td>
      <td><div class="d-flex align-items-center">${imgHtml}<div><div style="font-weight:600;font-size:13px;">${p.nombre}</div></div></div></td>
      <td><span class="badge" style="${getCategoriaStyle(p.categoria)}">${p.categoria}</span></td>
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
    dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
    buttons: [
      {
        extend: 'csvHtml5',
        text: '<i class="bi bi-filetype-csv me-1"></i> Descargar Reporte CSV',
        className: 'btn btn-success btn-sm',
        filename: 'Reporte_Productos_' + new Date().toISOString().split('T')[0],
        extension: '.csv',
        title: '',
        charset: 'utf-8',
        bom: true, // Hace que Excel reconozca las tildes y las 'ñ'
        exportOptions: {
          columns: [0, 1, 2, 3, 4, 5], // Selecciona solo las columnas de datos (excluye acciones)
          format: {
            body: function (data, row, column, node) {
              // Limpia todo el HTML (etiquetas, colores, divs) dejando solo texto puro
              return data.replace(/<[^>]*>?/gm, ' ').replace(/\s\s+/g, ' ').trim();
            }
          }
      },
      action: function (e, dt, node, config) {
        $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, node, config);
        setTimeout(() => {
          document.body.classList.remove('page-exit');
          const overlay = document.getElementById('page-overlay');
          if (overlay) overlay.classList.add('hidden');
        }, 100);
        }
    },
    {
      text: '<i class="bi bi-file-earmark-pdf me-1"></i> Exportar a PDF',
      className: 'btn btn-danger btn-sm ms-2',
      action: function() { window.print(); }
      }
    ]
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
          <span class="badge mb-1" style="${getCategoriaStyle(p.categoria)} font-size:10.5px;">${p.categoria}</span>
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
  const p = productosData.find(x => x.id === id);
  if (!p) return;
  const session = Andina.getSession();
  document.getElementById('detalleBody').innerHTML = `
    <div class="row">
      <div class="col-md-4 text-center">
        ${p.imagen ? `<img src="${p.imagen}" alt="${p.nombre}" style="width:100%;border-radius:10px;max-height:250px;object-fit:contain;background:rgba(255,255,255,0.03);padding:8px;">` : `<div style="width:100%;height:180px;background:var(--secondary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:60px;font-weight:800;color:var(--primary);">${p.nombre.charAt(0)}</div>`}
        <div class="mt-3">${Andina.getBadgeEstado(p.estado)}</div>
      </div>
      <div class="col-md-8">
        <h5 style="color:var(--primary);font-family:'Poppins',sans-serif;">${p.nombre}</h5>
        <p style="color:#718096;font-size:13.5px;">${p.descripcion || 'Sin descripción'}</p>
        <table class="table table-sm">
          <tr><td class="fw-semibold" style="width:40%">Código</td><td><code>${p.codigo}</code></td></tr>
          <tr><td class="fw-semibold">Categoría</td><td><span class="badge" style="${getCategoriaStyle(p.categoria)}">${p.categoria}</span></td></tr>
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

function cambiarImagenProducto(id) {
  const p = productosData.find(x => x.id === id);
  if (!p) return;

  Swal.fire({
    title: 'Actualizar Imagen',
    text: 'Pega el enlace (URL) de la nueva imagen para: ' + p.nombre,
    input: 'url',
    inputPlaceholder: 'https://ejemplo.com/imagen.jpg',
    inputValue: p.imagen || '',
    showCancelButton: true,
    confirmButtonText: '<i class="bi bi-cloud-arrow-up me-1"></i> Guardar Imagen',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#00a859'
  }).then(async (result) => {
    if (result.isConfirmed) {
      const nuevaImg = result.value;
      
      const res = await Andina.apiRequest('editar_producto', {
        id_producto: p.id,
        codigo: p.codigo,
        nombre: p.nombre,
        descripcion: p.descripcion || '',
        precio: p.precio,
        imagen_principal: nuevaImg,
        id_categoria: p.id_categoria || 0,
        estado: p.estado
      });

      if (res.exito) {
        Andina.showToast('Imagen actualizada correctamente', 'success');
        p.imagen = nuevaImg; // Actualizamos el dato localmente
        // Truco de magia: Forzamos al buscador a re-dibujar la tabla al instante
        const buscador = document.getElementById('buscador');
        if (buscador) buscador.dispatchEvent(new Event('input'));
      } else {
        Andina.showToast(res.error || 'Error al actualizar imagen', 'error');
      }
    }
  });
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

function getCategoriaStyle(cat) {
  if (!cat) return 'background:var(--secondary); color:var(--primary);';
  let hash = 0;
  for (let i = 0; i < cat.length; i++) hash = cat.charCodeAt(i) + ((hash << 5) - hash);
  const colores = [
    { bg: 'rgba(96, 165, 250, 0.15)', text: '#93c5fd' },   // Azul brillante
    { bg: 'rgba(52, 211, 153, 0.15)', text: '#6ee7b7' },   // Esmeralda
    { bg: 'rgba(251, 191, 36, 0.15)', text: '#fcd34d' },   // Oro/Ambar
    { bg: 'rgba(248, 113, 113, 0.15)', text: '#fca5a5' },  // Rojo suave
    { bg: 'rgba(167, 139, 250, 0.15)', text: '#c4b5fd' },  // Violeta/Púrpura
    { bg: 'rgba(45, 212, 191, 0.15)', text: '#5eead4' },   // Turquesa
    { bg: 'rgba(251, 146, 60, 0.15)', text: '#fdba74' }    // Naranja
  ];
  const index = Math.abs(hash) % colores.length;
  return `background:${colores[index].bg}; color:${colores[index].text}; border: 1px solid ${colores[index].text}35;`;
}
