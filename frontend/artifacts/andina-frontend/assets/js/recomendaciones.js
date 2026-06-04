/**
 * DISTRIBUIDORA ANDINA SRL — recomendaciones.js
 * Motor de recomendaciones basado en historial del cliente
 */
'use strict';

const Recomendaciones = {
  // Matriz de correlación entre productos (comprados juntos frecuentemente)
  correlacion: {
    1: [5, 8, 7],  // Leche -> Azúcar, Fideos, Sal
    2: [1, 5, 8],  // Café -> Leche, Azúcar, Fideos
    3: [4, 7, 5],  // Arroz -> Aceite, Sal, Azúcar
    4: [3, 7, 1],  // Aceite -> Arroz, Sal, Leche
    5: [1, 2, 8],  // Azúcar -> Leche, Café, Fideos
    6: [1, 5, 2],  // Yogurt -> Leche, Azúcar, Café
    7: [3, 4, 8],  // Sal -> Arroz, Aceite, Fideos
    8: [3, 7, 4],  // Fideos -> Arroz, Sal, Aceite
  },

  // Obtener recomendaciones para un producto
  parProducto(prodId, productosData = null, limit = 3) {
    const ids = this.correlacion[prodId] || [];
    const todos = productosData || window.productosGlobales || [];
    
    let recomendados = ids
      .map(id => todos.find(p => p.id == id || p.id_producto == id))
      .filter(Boolean)
      .filter(p => p.estado !== 'Agotado');

    // MAGIA: Si el producto es nuevo de la BD y no tiene regla en el código de arriba
    if (recomendados.length === 0 && todos.length > 0) {
      const otros = todos.filter(p => (p.id || p.id_producto) != prodId && p.estado !== 'Agotado');
      // Mezclamos matemáticamente para que siempre recomiende los mismos para ese producto
      otros.sort((a, b) => (((a.id || a.id_producto) * prodId) % 7) - (((b.id || b.id_producto) * prodId) % 7));
      recomendados = otros;
    }

    return recomendados.slice(0, limit);
  },

  // Recomendaciones para el carrito actual
  parCarrito(carrito, productosData = null, limit = 4) {
    const idsEnCarrito = carrito.map(c => c.id || c.id_producto);
    const candidatos = new Map();

    idsEnCarrito.forEach(id => {
      const recs = this.parProducto(id, productosData);
      recs.forEach(p => {
        const recId = p.id || p.id_producto;
        if (!idsEnCarrito.includes(recId)) {
          candidatos.set(recId, (candidatos.get(recId) || 0) + 1);
        }
      });
    });

    const todos = productosData || window.productosGlobales || [];
    // Ordenar por frecuencia de aparición
    const sorted = [...candidatos.entries()]
      .sort((a, b) => b[1] - a[1])
      .map(([id]) => todos.find(p => p.id == id || p.id_producto == id))
      .filter(Boolean)
      .filter(p => p.estado !== 'Agotado')
      .slice(0, limit);

    return sorted;
  },

  // Recomendaciones basadas en historial de compras (simulado)
  parCliente(clienteId, limit = 6) {
    // En producción esto vendría del backend
    const historial = [1, 3, 5, 7]; // IDs de productos comprados anteriormente
    const candidatos = new Set();

    historial.forEach(id => {
      const recs = this.parProducto(id);
      recs.forEach(p => {
        if (!historial.includes(p.id)) candidatos.add(p.id);
      });
    });

    return [...candidatos]
      .map(id => (window.productosGlobales || []).find(p => p.id === id))
      .filter(Boolean)
      .filter(p => p.estado !== 'Agotado')
      .slice(0, limit);
  },

  // Productos más vendidos (simulado)
  masVendidos(limit = 4) {
    const ventas = { 1: 320, 3: 280, 5: 210, 8: 190, 4: 160, 7: 140, 2: 95, 6: 80 };
    return (window.productosGlobales || [])
      .filter(p => p.estado !== 'Agotado')
      .sort((a, b) => (ventas[b.id] || 0) - (ventas[a.id] || 0))
      .slice(0, limit);
  },

  // Generar el "Globito" HTML para la tienda
  generarGlobito(prodId, productosData = null) {
    const recs = this.parProducto(prodId, productosData, 1);
    if (recs.length === 0) return '';
    
    const rec = recs[0];
    const nombreCorto = rec.nombre.split(' ').slice(0, 2).join(' '); // Tomar solo 2 primeras palabras
    return `
      <div class="recomendacion-bubble" title="Basado en compras de otros clientes">
        <i class="bi bi-stars"></i> Ideal con: <strong>${nombreCorto}</strong>
      </div>
    `;
  },

  // Renderizar widget de recomendaciones
  renderWidget(containerId, productos, onAgregar) {
    const container = document.getElementById(containerId);
    if (!container || !productos.length) return;

    container.innerHTML = productos.map(p => `
      <div class="col-6 col-md-3">
        <div class="card p-2 text-center h-100" style="cursor:pointer;border:1.5px solid #edf2f7;transition:all .2s;"
          onmouseover="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)'"
          onmouseout="this.style.borderColor='#edf2f7';this.style.transform='translateY(0)'">
          ${p.imagen
            ? `<img src="${p.imagen}" alt="${p.nombre}" style="width:100%;height:70px;object-fit:cover;border-radius:8px;margin-bottom:8px;">`
            : `<div style="width:100%;height:70px;background:var(--secondary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:var(--primary);margin-bottom:8px;">${p.nombre.charAt(0)}</div>`}
          <div style="font-size:11.5px;font-weight:600;color:var(--primary);line-height:1.3;margin-bottom:4px;">${p.nombre}</div>
          <div style="font-size:13px;font-weight:700;color:var(--accent);">${Andina.formatBs(p.precio)}</div>
          ${typeof onAgregar === 'function'
            ? `<button class="btn btn-success btn-xs w-100 mt-2" onclick="event.stopPropagation();(${onAgregar.toString()})(${p.id})">
                <i class="bi bi-cart-plus me-1"></i>Agregar
              </button>`
            : ''}
        </div>
      </div>`).join('');
  },
};

window.Recomendaciones = Recomendaciones;
