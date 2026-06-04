/**
 * DISTRIBUIDORA ANDINA SRL — compras.js
 */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const session = Andina.initPage({ activeKey:'compras', pageTitle:'Compras' });
  if (!session) return;

  renderCompras(Andina.MOCK_DATA.compras, session.rol);

  document.getElementById('filtroEstado').addEventListener('change', function() {
    const v = this.value;
    const filtrado = v ? Andina.MOCK_DATA.compras.filter(c=>c.estado===v) : Andina.MOCK_DATA.compras;
    if($.fn.DataTable.isDataTable('#tablaCompras')) $('#tablaCompras').DataTable().destroy();
    renderCompras(filtrado, session.rol);
  });

  document.getElementById('filtroProveedor').addEventListener('change', function() {
    const v = this.value;
    const filtrado = v ? Andina.MOCK_DATA.compras.filter(c=>c.proveedor===v) : Andina.MOCK_DATA.compras;
    if($.fn.DataTable.isDataTable('#tablaCompras')) $('#tablaCompras').DataTable().destroy();
    renderCompras(filtrado, session.rol);
  });
});

function renderCompras(compras, rol) {
  const tbody = document.getElementById('tbodyCompras');
  tbody.innerHTML = compras.map(c => {
    let acciones = `<div class="d-flex gap-1 flex-wrap">`;
    if(c.estado==='Pendiente') {
      acciones += `<button class="btn btn-success btn-xs" onclick="cambiarEstado(${c.id},'Aprobado')"><i class="bi bi-check-lg me-1"></i>Aprobar</button>`;
      acciones += `<button class="btn btn-danger btn-xs" onclick="cambiarEstado(${c.id},'Rechazado')"><i class="bi bi-x-lg me-1"></i>Rechazar</button>`;
    }
    if(c.estado==='Aprobado') {
      acciones += `<button class="btn btn-primary btn-xs" onclick="cambiarEstado(${c.id},'Recibido')"><i class="bi bi-box-arrow-in-down me-1"></i>Recibir</button>`;
    }
    acciones += `<button class="btn btn-outline-secondary btn-xs" onclick="verDetalleOC(${c.id})" title="Ver detalle"><i class="bi bi-eye"></i></button>`;
    acciones += `</div>`;

    return `<tr>
      <td><code style="font-size:12px;color:var(--primary);">${c.codigo}</code></td>
      <td><i class="bi bi-truck me-1 text-muted"></i><strong>${c.proveedor}</strong></td>
      <td>${Andina.formatFechaCorta(c.fecha)}</td>
      <td>${Andina.getBadgeEstado(c.estado)}</td>
      <td><strong>${Andina.formatBs(c.total)}</strong></td>
      <td>${acciones}</td>
    </tr>`;
  }).join('');

  if(!$.fn.DataTable.isDataTable('#tablaCompras')) {
    $('#tablaCompras').DataTable({
      language: { url:'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
      order:[[2,'desc']], pageLength:10, destroy:true,
      dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
      buttons: [
        {
          extend: 'csvHtml5',
          text: '<i class="bi bi-filetype-csv me-1"></i> Descargar Reporte CSV',
          className: 'btn btn-success btn-sm',
          title: 'Reporte_Compras_' + new Date().toISOString().split('T')[0],
          charset: 'utf-8',
          bom: true,
          exportOptions: {
            columns: [0, 1, 2, 3, 4], // Las compras tienen solo 5 columnas de datos
            format: {
              body: function (data, row, column, node) {
                return data.replace(/<[^>]*>?/gm, ' ').replace(/\s\s+/g, ' ').trim();
              }
            }
          }
        }
      ]
    });
  }
}

function cambiarEstado(id, nuevoEstado) {
  const iconMap = { Aprobado:'success', Rechazado:'warning', Recibido:'info' };
  const oc = Andina.MOCK_DATA.compras.find(c=>c.id===id);
  Swal.fire({
    title:`¿${nuevoEstado === 'Aprobado' ? 'Aprobar' : nuevoEstado === 'Rechazado' ? 'Rechazar' : 'Marcar como recibida'} esta orden?`,
    html:`<strong>${oc?.codigo}</strong> — ${oc?.proveedor}`,
    icon: iconMap[nuevoEstado] || 'question',
    showCancelButton: true,
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
  }).then(async r => {
    if(r.isConfirmed) {
      await Andina.apiRequest('cambiar_estado_compra', { id, estado: nuevoEstado });
      Andina.showToast(`Orden marcada como ${nuevoEstado}`, 'success');
      // Actualizar estado localmente
      const idx = Andina.MOCK_DATA.compras.findIndex(c=>c.id===id);
      if(idx>=0) Andina.MOCK_DATA.compras[idx].estado = nuevoEstado;
      if($.fn.DataTable.isDataTable('#tablaCompras')) $('#tablaCompras').DataTable().destroy();
      renderCompras(Andina.MOCK_DATA.compras, Andina.getSession().rol);
    }
  });
}

function verDetalleOC(id) {
  const oc = Andina.MOCK_DATA.compras.find(c=>c.id===id);
  if(!oc) return;
  Swal.fire({
    title: oc.codigo,
    html: `
      <table class="table table-sm text-start">
        <tr><td class="fw-semibold">Proveedor</td><td>${oc.proveedor}</td></tr>
        <tr><td class="fw-semibold">Fecha</td><td>${Andina.formatFecha(oc.fecha)}</td></tr>
        <tr><td class="fw-semibold">Estado</td><td>${Andina.getBadgeEstado(oc.estado)}</td></tr>
        <tr><td class="fw-semibold">Total</td><td><strong style="color:var(--accent)">${Andina.formatBs(oc.total)}</strong></td></tr>
      </table>`,
    confirmButtonText: 'Cerrar',
  });
}
