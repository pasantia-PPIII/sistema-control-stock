/**
 * assets/js/main.js
 * Funcionalidades globales del sistema de control de stock:
 * Control de modales y comportamiento común de la interfaz.
 */

function abrirModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function cerrarModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Cierre de modal al hacer clic en el fondo oscuro
window.addEventListener('click', function(event) {
    if (event.target && event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
});
