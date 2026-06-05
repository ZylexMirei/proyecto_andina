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
  const res = await Andina.apiRequest('listar_pedidos', {}, 'GET');
  if (res.exito && res.pedidos && res.pedidos.length > 0) {
    pedidosData = res.pedidos.map(p => ({
      id: p.id,
      codigo: p.codigo,
      cliente: p.cliente,
      fecha: p.fecha,
      estado: p.estado,
      total: parseFloat(p.total),
      creado_por: p.creado_por,
      id_usuario: p.id_usuario
    }));
  } else {
    // Fallback a datos imaginarios
    pedidosData = Andina.MOCK_DATA.pedidos;
  }

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
    const acciones = `<div class="d-flex gap-1">
      <button class="btn btn-outline-primary btn-xs" onclick="verDetallePedido(${p.id})" title="Ver detalle"><i class="bi bi-eye"></i></button>
      ${(rol==='Administrador'||rol==='Gerente') ? `<button class="btn btn-outline-danger btn-xs" onclick="cancelarPedido(${p.id},'${p.codigo}')" title="Cancelar"><i class="bi bi-x-circle"></i></button>` : ''}
    </div>`;
    return `<tr>
      <td><code style="font-size:12px;color:var(--primary);">${p.codigo}</code></td>
      <td><strong>${p.cliente}</strong></td>
      <td>${Andina.formatFechaCorta(p.fecha)}</td>
      <td>${Andina.getBadgeEstado(p.estado)}</td>
      <td><strong>${Andina.formatBs(p.total)}</strong></td>
      <td><span style="font-size:12.5px;color:#718096;">${p.creado_por}</span></td>
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

function verDetallePedido(id) {
  const p = pedidosData.find(x=>x.id===id);
  if(!p) return;
  // Generar detalles simulados
  const items = Andina.MOCK_DATA.productos.slice(0,3).map(prod => `
    <tr>
      <td>${prod.nombre}</td>
      <td class="text-center">${Math.ceil(Math.random()*10)}</td>
      <td>${Andina.formatBs(prod.precio)}</td>
      <td><strong>${Andina.formatBs(prod.precio * Math.ceil(Math.random()*10))}</strong></td>
    </tr>`).join('');

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
