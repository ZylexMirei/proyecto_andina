/**
 * DISTRIBUIDORA ANDINA SRL — pedidos.js
 */
'use strict';

let pedidosData = []; // Guardará los pedidos reales o simulados

document.addEventListener('DOMContentLoaded', async () => {
  const session = Andina.initPage({ activeKey:'pedidos', pageTitle:'Pedidos' });
  if (!session) return;

  const rol = session.rol;

  // Intentar traer pedidos reales de la BD
  const res = await Andina.apiRequest('listar_pedidos', { limite: 1000 }, 'GET');
  let pedidosReales = [];
  if (res.exito && res.pedidos) {
    pedidosReales = res.pedidos.map(p => ({
      id: parseInt(p.id_pedido || p.id),
      codigo: p.codigo_pedido || p.codigo,
      cliente: p.cliente_nombre || p.cliente,
      fecha: p.fecha_pedido || p.fecha,
      estado: p.estado,
      total: parseFloat(p.total_pedido || p.total) || 0,
      creado_por: p.creador_nombre || p.creado_por,
      id_usuario: parseInt(p.id_usuario_creador || p.id_usuario) || 0,
      comprobante: p.comprobante || null,
      detalles: Array.isArray(p.detalles) ? p.detalles : []
    }));
  }
  
  // Si hay datos reales, no mezclamos pedidos de muestra para evitar que oculten los pedidos nuevos.
  const pedidosBase = pedidosReales.length ? pedidosReales : Andina.MOCK_DATA.pedidos;
  pedidosData = pedidosBase.sort((a,b) => {
    const fechaCmp = String(b.fecha || '').localeCompare(String(a.fecha || ''));
    return fechaCmp || (Number(b.id || 0) - Number(a.id || 0));
  });

  let pedidos = pedidosData;
  // Empleado solo ve sus pedidos
  if (rol === 'Empleado') {
    pedidos = pedidos.filter(p => p.id_usuario === session.id_usuario);
    document.getElementById('subtitlePedidos').textContent = `Mis pedidos · ${pedidos.length} registro(s)`;
  }

  renderPedidos(pedidos, rol);

  document.getElementById('filtroEstado').addEventListener('change', function() {
    const v = this.value;
    let filtrado = rol==='Empleado' ? pedidosData.filter(p=>p.id_usuario===session.id_usuario) : pedidosData;
    if(v) filtrado = filtrado.filter(p=>p.estado===v);
    if($.fn.DataTable.isDataTable('#tablaPedidos')) $('#tablaPedidos').DataTable().destroy();
    renderPedidos(filtrado, rol);
  });
});

function renderPedidos(pedidos, rol) {
  const tbody = document.getElementById('tbodyPedidos');
  tbody.innerHTML = pedidos.map(p => {
    let stateActions = '';
    if (rol === 'Administrador' || rol === 'Gerente') {
      if (p.estado === 'Pendiente') {
        stateActions += `<button class="btn btn-outline-success btn-xs" onclick="cambiarEstadoPedido(${p.id}, '${p.codigo}', 'Confirmado')" title="Confirmar Pedido"><i class="bi bi-check-lg"></i></button>`;
      } else if (p.estado === 'Confirmado') {
        stateActions += `<button class="btn btn-outline-info btn-xs" onclick="cambiarEstadoPedido(${p.id}, '${p.codigo}', 'Enviado')" title="Marcar como Enviado"><i class="bi bi-truck"></i></button>`;
      } else if (p.estado === 'Enviado') {
        stateActions += `<button class="btn btn-outline-success btn-xs" onclick="cambiarEstadoPedido(${p.id}, '${p.codigo}', 'Entregado')" title="Marcar como Entregado"><i class="bi bi-box-seam"></i></button>`;
      }
    }

    const comprobanteBadge = p.comprobante
      ? `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-receipt-cutoff me-1"></i>Comprobante</span>`
      : `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-dash-circle me-1"></i>Sin comprobante</span>`;

    const acciones = `<div class="d-flex gap-1">
      <button class="btn btn-outline-primary btn-xs" onclick="verDetallePedido(${p.id})" title="Ver detalle"><i class="bi bi-eye"></i></button>
      ${stateActions}
      ${(rol==='Administrador'||rol==='Gerente') && p.estado !== 'Cancelado' ? `<button class="btn btn-outline-danger btn-xs" onclick="cancelarPedido(${p.id},'${p.codigo}')" title="Cancelar"><i class="bi bi-x-circle"></i></button>` : ''}
    </div>`;
    return `<tr>
      <td><code style="font-size:12px;color:var(--primary);">${p.codigo}</code></td>
      <td><strong>${p.cliente}</strong></td>
      <td data-order="${String(p.fecha || '')}-${String(p.id || 0).padStart(10, '0')}">${Andina.formatFechaCorta(p.fecha)}</td>
      <td>${Andina.getBadgeEstado(p.estado)}</td>
      <td><strong>${Andina.formatBs(p.total)}</strong></td>
      <td><div style="font-size:12.5px;color:#718096;">${p.creado_por}</div>${comprobanteBadge}</td>
      <td>${acciones}</td>
    </tr>`;
  }).join('');

  if(!$.fn.DataTable.isDataTable('#tablaPedidos')) {
    $('#tablaPedidos').DataTable({
      language: { url:'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
      order:[[2,'desc']], pageLength:10, destroy:true,
      dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
      buttons: [
        {
          extend: 'csvHtml5',
          text: '<i class="bi bi-filetype-csv me-1"></i> Descargar Reporte CSV',
          className: 'btn btn-success btn-sm',
          filename: 'Reporte_Pedidos_' + new Date().toISOString().split('T')[0],
          extension: '.csv',
          title: '',
          charset: 'utf-8',
          bom: true,
          exportOptions: {
            columns: [0, 1, 2, 3, 4, 5], // Excluye la columna de acciones (índice 6)
            format: {
              body: function (data, row, column, node) {
                return data.replace(/<[^>]*>?/gm, ' ').replace(/\s\s+/g, ' ').trim();
              }
            }
          }
        },
        {
          text: '<i class="bi bi-file-earmark-pdf me-1"></i> Exportar a PDF',
          className: 'btn btn-danger btn-sm ms-2',
        action: function() { window.print(); }
        }
      ]
    });
  }
}

function cambiarEstadoPedido(id, codigo, nuevoEstado) {
  let mensaje = '';
  if (nuevoEstado === 'Confirmado') mensaje = `¿Deseas confirmar el pedido <strong>${codigo}</strong>?`;
  else if (nuevoEstado === 'Enviado') mensaje = `¿El pedido <strong>${codigo}</strong> ha sido enviado / despachado?`;
  else if (nuevoEstado === 'Entregado') mensaje = `¿El pedido <strong>${codigo}</strong> fue entregado con éxito al cliente?`;

  Swal.fire({
    title: 'Actualizar Estado',
    html: mensaje,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Sí, continuar',
    cancelButtonText: 'No',
    confirmButtonColor: '#00a859',
  }).then(async r => {
    if(r.isConfirmed) {
      await Andina.apiRequest('cambiar_estado_pedido', { id, estado: nuevoEstado });
      Andina.showToast(`El pedido pasó a estado: ${nuevoEstado}`, 'success');
      setTimeout(() => location.reload(), 800);
    }
  });
}

function verDetallePedido(id) {
  const p = pedidosData.find(x=>x.id===id);
  if(!p) return;
  const detalles = Array.isArray(p.detalles) && p.detalles.length
    ? p.detalles
    : Andina.MOCK_DATA.productos.slice(0,3).map(prod => ({
        producto_nombre: prod.nombre,
        cantidad: Math.ceil(Math.random()*10),
        precio_unitario: prod.precio
      }));

  const items = detalles.map(det => {
    const nombre = det.producto_nombre || det.nombre || 'Producto';
    const cantidad = parseInt(det.cantidad) || 0;
    const precio = parseFloat(det.precio_unitario || det.precio) || 0;
    return `
    <tr>
      <td>${nombre}</td>
      <td class="text-center">${cantidad}</td>
      <td>${Andina.formatBs(precio)}</td>
      <td><strong>${Andina.formatBs(precio * cantidad)}</strong></td>
    </tr>`;
  }).join('');

  const comprobanteHtml = p.comprobante
    ? `<div class="mt-3 p-3 rounded" style="border:1px solid rgba(0,198,255,.18);background:rgba(0,198,255,.06);">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <div class="fw-semibold" style="color:var(--primary);"><i class="bi bi-receipt-cutoff me-1"></i>Comprobante de pago</div>
            <div style="font-size:12.5px;color:#64748b;">Referencia: <strong>${p.comprobante.referencia || 'Sin referencia'}</strong></div>
            <div style="font-size:12.5px;color:#64748b;">Estado: <strong>${p.comprobante.estado || 'Pendiente'}</strong></div>
            <div style="font-size:12.5px;color:#64748b;">Subido: <strong>${Andina.formatDateTime(p.comprobante.fecha_subida)}</strong></div>
          </div>
          <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="/test_api.php?accion=ver_comprobante_pago&id_comprobante=${p.comprobante.id_comprobante}">
            <i class="bi bi-box-arrow-up-right me-1"></i>Ver archivo
          </a>
        </div>
      </div>`
    : `<div class="mt-3 p-3 rounded text-muted" style="border:1px dashed rgba(100,116,139,.35);background:rgba(100,116,139,.06);font-size:13px;">
        <i class="bi bi-info-circle me-1"></i>Este pedido todavia no tiene comprobante subido.
      </div>`;

  Swal.fire({
    title: p.codigo,
    width: 600,
    html: `
      <div class="text-start">
        <div class="row mb-3">
          <div class="col-6"><span class="text-muted" style="font-size:12px;">Cliente</span><div class="fw-semibold">${p.cliente}</div></div>
          <div class="col-6"><span class="text-muted" style="font-size:12px;">Fecha</span><div class="fw-semibold">${Andina.formatFecha(p.fecha)}</div></div>
          <div class="col-6 mt-2"><span class="text-muted" style="font-size:12px;">Estado</span><div>${Andina.getBadgeEstado(p.estado)}</div></div>
          <div class="col-6 mt-2"><span class="text-muted" style="font-size:12px;">Creado por</span><div class="fw-semibold">${p.creado_por}</div></div>
        </div>
        <table class="table table-sm">
          <thead style="background:var(--primary);color:#fff;"><tr><th>Producto</th><th class="text-center">Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead>
          <tbody>${items}</tbody>
          <tfoot><tr><td colspan="3" class="text-end fw-bold">Total</td><td><strong style="color:var(--accent);font-size:15px;">${Andina.formatBs(p.total)}</strong></td></tr></tfoot>
        </table>
        ${comprobanteHtml}
      </div>`,
    showDenyButton: true,
    confirmButtonText: '<i class="bi bi-printer me-1"></i> Imprimir Recibo',
    denyButtonText: 'Cerrar',
    confirmButtonColor: '#1a3a5c',
    denyButtonColor: '#6c757d'
  }).then((result) => {
    if (result.isConfirmed) {
      window.print(); // Activa la ventana de impresión del navegador
    }
  });
}

function cancelarPedido(id, codigo) {
  Swal.fire({
    title: '¿Cancelar pedido?',
    html: `El pedido <strong>${codigo}</strong> será cancelado.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cancelar',
    cancelButtonText: 'No',
    confirmButtonColor: '#dc3545',
  }).then(async r => {
    if(r.isConfirmed) {
      await Andina.apiRequest('cancelar_pedido',{id});
      Andina.showToast('Pedido cancelado','success');
      setTimeout(()=>location.reload(),800);
    }
  });
}
