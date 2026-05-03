/**
 * DISTRIBUIDORA ANDINA SRL — carrito.js
 * Gestión del carrito de compras para clientes
 */
'use strict';

const CARRITO_KEY = 'andina_carrito';

const Carrito = {
  obtener() {
    try { return JSON.parse(localStorage.getItem(CARRITO_KEY)) || []; } catch { return []; }
  },
  guardar(items) {
    localStorage.setItem(CARRITO_KEY, JSON.stringify(items));
  },
  agregar(producto) {
    const items = this.obtener();
    const idx = items.findIndex(i => i.id === producto.id);
    if (idx >= 0) {
      items[idx].cantidad = Math.min(
        items[idx].cantidad + (producto.cantidad || 1),
        producto.stock || 999
      );
    } else {
      items.push({ ...producto, cantidad: producto.cantidad || 1 });
    }
    this.guardar(items);
  },
  quitar(id) {
    const items = this.obtener().filter(i => i.id !== id);
    this.guardar(items);
  },
  cambiarCantidad(id, delta) {
    const items = this.obtener();
    const idx = items.findIndex(i => i.id === id);
    if (idx < 0) return;
    items[idx].cantidad = Math.max(1, items[idx].cantidad + delta);
    if (items[idx].cantidad <= 0) items.splice(idx, 1);
    this.guardar(items);
  },
  setCantidad(id, cantidad) {
    const items = this.obtener();
    const idx = items.findIndex(i => i.id === id);
    if (idx < 0) return;
    if (cantidad <= 0) { items.splice(idx, 1); }
    else { items[idx].cantidad = cantidad; }
    this.guardar(items);
  },
  vaciar() {
    localStorage.removeItem(CARRITO_KEY);
  },
  totalItems() {
    return this.obtener().reduce((a, b) => a + b.cantidad, 0);
  },
  subtotal() {
    return this.obtener().reduce((a, b) => a + (b.precio * b.cantidad), 0);
  },
};

window.Carrito = Carrito;
