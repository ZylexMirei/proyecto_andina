/**
 * DISTRIBUIDORA ANDINA SRL — pedidos.js
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const session = Andina.initPage({ activeKey:'pedidos', pageTitle:'Pedidos' });
  if (!session) return;

  const rol = session.rol;
  let pedidos = Andina.MOCK_DATA.pedidos;

  // Empleado solo ve sus pedidos
  if (rol === 'Empleado') {
    pedidos = pedidos.filter(p => p.id_usuario === session.id_usuario);
    document.getElementById('subtitlePedidos').textContent = `Mis pedidos · ${pedidos.length} registro(s)`;
  }

  renderPedidos(pedidos, rol);

  document.getElementById('filtroEstado').addEventListener('change', function() {
    const v = this.value;
    let filtrado = rol==='Empleado' ? Andina.MOCK_DATA.pedidos.filter(p=>p.id_usuario===session.id_usuario) : Andina.MOCK_DATA.pedidos;
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
    });
  }
}

function verDetallePedido(id) {
  const p = Andina.MOCK_DATA.pedidos.find(x=>x.id===id);
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
    confirmButtonText: 'Cerrar',
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
