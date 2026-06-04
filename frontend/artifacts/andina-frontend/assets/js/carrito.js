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

  // ===================== PROCESAR PAGO CON SIMULADOR =====================
  procesarCompra() {
    const items = this.obtener();
    if (items.length === 0) {
      if (window.Andina) Andina.showToast('Tu carrito está vacío. Agrega productos primero.', 'warning');
      return;
    }
    
    const total = this.subtotal();
    const session = window.Andina ? Andina.getSession() : null;
    const id_usuario = session ? session.id_usuario : 0;
    
    if (!id_usuario) {
      if (window.Andina) Andina.showToast('Debes iniciar sesión para comprar', 'error');
      return;
    }

    // 1. Llamar a la ventana interactiva de SweetAlert en app.js
    if (window.Andina && typeof Andina.procesarPagoSimulado === 'function') {
      Andina.procesarPagoSimulado(total, (metodoElegido) => {
        
        // 2. Si el cliente completó el pago, guardamos el pedido en la Base de Datos
        const datosPedido = { id_usuario, items, total, metodo_pago: metodoElegido };
        
        Andina.apiRequest('crear_pedido_cliente', datosPedido).then(res => {
          if (res.exito) {
            this.vaciar(); // Limpiar localStorage
            Swal.fire({
              icon: 'success', title: '¡Compra Exitosa!',
              text: `Tu pedido ha sido registrado pagando con ${metodoElegido}.`,
              timer: 3000, showConfirmButton: false
            }).then(() => { window.location.reload(); });
          } else {
            Andina.showToast(res.error || 'Error al guardar el pedido', 'error');
          }
        });
      });
    } else {
      console.error("El simulador de pago no está cargado. Revisa app.js");
    }
  }
};

window.Carrito = Carrito;
