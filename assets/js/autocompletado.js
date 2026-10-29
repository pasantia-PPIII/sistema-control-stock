/**
 * assets/js/autocompletado.js
 * Autocompletado por AJAX: consulta al servidor de a 10 resultados y permite "Cargar más".
 * Se maneja con el mouse o con el teclado (↑ ↓ para moverse, Enter para elegir, Esc para cerrar).
 *
 * crearAutocompletado({
 *   input:           elemento <input> donde se escribe,
 *   lista:           elemento contenedor del desplegable (.autocomplete-list),
 *   url:             endpoint GET que devuelve { success, items, hay_mas },
 *   parametros:      objeto con parámetros de la consulta, o function que lo devuelve (opcional; se evalúa en cada consulta),
 *   alSeleccionar:   function(item) al elegir una opción,
 *   alLimpiar:       function() cuando el usuario edita el texto y la selección anterior deja de valer
 * })
 */
function crearAutocompletado(opciones) {
    const input = opciones.input;
    const lista = opciones.lista;
    if (!input || !lista) return;

    let termino = '';
    let pagina = 1;
    let temporizador = null;
    let numeroConsulta = 0;
    let indiceActivo = -1; // posición resaltada entre las opciones elegibles (-1 = ninguna)

    function estaAbierta() {
        return lista.style.display === 'block';
    }

    function cerrar() {
        lista.style.display = 'none';
        indiceActivo = -1;
    }

    // Opciones que se pueden elegir (las deshabilitadas se saltean); "Cargar más..." cuenta como una
    function opcionesElegibles() {
        return Array.prototype.slice.call(lista.querySelectorAll('.autocomplete-item:not(.autocomplete-item--off)'));
    }

    function resaltar(indice) {
        const elegibles = opcionesElegibles();
        elegibles.forEach(function (el) { el.classList.remove('autocomplete-item--activo'); });
        if (elegibles.length === 0) {
            indiceActivo = -1;
            return;
        }
        // Al pasar del último vuelve al primero y viceversa
        indiceActivo = (indice + elegibles.length) % elegibles.length;
        const actual = elegibles[indiceActivo];
        actual.classList.add('autocomplete-item--activo');
        actual.scrollIntoView({ block: 'nearest' });
    }

    function agregarMensaje(texto) {
        const div = document.createElement('div');
        div.className = 'autocomplete-empty';
        div.textContent = texto;
        lista.appendChild(div);
    }

    function agregarItems(items) {
        items.forEach(function (item) {
            const div = document.createElement('div');
            div.className = 'autocomplete-item' + (item.deshabilitado ? ' autocomplete-item--off' : '');
            div.textContent = item.texto;
            if (!item.deshabilitado) {
                div.addEventListener('click', function () {
                    input.value = item.texto;
                    opciones.alSeleccionar(item);
                    cerrar();
                });
            }
            lista.appendChild(div);
        });
    }

    function agregarCargarMas() {
        const mas = document.createElement('div');
        mas.className = 'autocomplete-item autocomplete-more';
        mas.textContent = 'Cargar más...';
        mas.addEventListener('click', function (e) {
            e.stopPropagation();
            const primeraNueva = opcionesElegibles().length - 1; // la nueva primera opción ocupará el lugar de "Cargar más..."
            mas.remove();
            pagina++;
            consultar(false, primeraNueva);
        });
        lista.appendChild(mas);
    }

    function consultar(reiniciar, resaltarDesde) {
        const consulta = ++numeroConsulta;
        const fijos = typeof opciones.parametros === 'function' ? opciones.parametros() : opciones.parametros;
        const params = new URLSearchParams(Object.assign({}, fijos || {}, { q: termino, pagina: pagina }));

        fetch(opciones.url + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (consulta !== numeroConsulta) return; // llegó una respuesta vieja: se descarta
                if (reiniciar) {
                    lista.innerHTML = '';
                    indiceActivo = -1;
                }
                if (!res.success) {
                    agregarMensaje(res.error || 'No se pudo buscar.');
                } else {
                    agregarItems(res.items);
                    if (res.hay_mas) agregarCargarMas();
                    if (lista.children.length === 0) agregarMensaje('Sin resultados.');
                }
                lista.style.display = 'block';
                // Con el teclado, al cargar más se sigue desde la primera opción nueva
                if (typeof resaltarDesde === 'number') resaltar(resaltarDesde);
            })
            .catch(function () {
                if (consulta !== numeroConsulta) return;
                if (reiniciar) lista.innerHTML = '';
                agregarMensaje('No se pudo conectar con el servidor.');
                lista.style.display = 'block';
            });
    }

    function buscarDesdeCero() {
        termino = input.value.trim();
        pagina = 1;
        consultar(true);
    }

    // Al enfocar o hacer clic (el campo puede seguir enfocado después de elegir una opción) se abre la lista
    input.addEventListener('focus', function () {
        if (!estaAbierta()) buscarDesdeCero();
    });
    input.addEventListener('click', function () {
        if (!estaAbierta()) buscarDesdeCero();
    });

    input.addEventListener('input', function () {
        // Si se edita el texto, la opción elegida antes ya no corresponde
        if (opciones.alLimpiar) opciones.alLimpiar();
        clearTimeout(temporizador);
        temporizador = setTimeout(buscarDesdeCero, 250);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            // Si la lista estaba abierta, Esc solo la cierra (no cierra además el modal que la contiene)
            if (estaAbierta()) e.stopPropagation();
            cerrar();
            return;
        }

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!estaAbierta()) {
                buscarDesdeCero();
                return;
            }
            resaltar(indiceActivo + (e.key === 'ArrowDown' ? 1 : (indiceActivo < 0 ? 0 : -1)));
            return;
        }

        if (e.key === 'Enter' && estaAbierta()) {
            // Con la lista abierta, Enter elige la opción resaltada y nunca envía el formulario
            e.preventDefault();
            const elegibles = opcionesElegibles();
            if (indiceActivo >= 0 && elegibles[indiceActivo]) elegibles[indiceActivo].click();
        }
    });

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !lista.contains(e.target)) cerrar();
    });
}


/**
 * Autocompletado de selección múltiple: cada opción elegida queda como una etiqueta con una "x" para quitarla,
 * y se envía con el formulario como un campo oculto <nombreCampo>=<id> (por ejemplo operarios[]).
 *
 * crearAutocompletadoMultiple({
 *   input, lista, url, parametros: igual que crearAutocompletado,
 *   etiquetas:    elemento donde se muestran las etiquetas (debe estar dentro del <form>),
 *   nombreCampo:  nombre del campo oculto de cada elegido, ej. 'operarios[]'
 * })
 * Devuelve { agregar(id, texto), limpiar() } para precargar o vaciar la selección (por ejemplo al abrir un modal).
 */
function crearAutocompletadoMultiple(opciones) {
    const etiquetas = opciones.etiquetas;
    if (!etiquetas) return null;

    const elegidos = new Map(); // id -> texto

    function dibujar() {
        etiquetas.innerHTML = '';
        elegidos.forEach(function (texto, id) {
            const chip = document.createElement('span');
            chip.className = 'chip-seleccion';

            const nombre = document.createElement('span');
            nombre.textContent = texto;

            const quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'chip-seleccion__quitar';
            quitar.setAttribute('aria-label', 'Quitar ' + texto);
            quitar.textContent = '×';
            quitar.addEventListener('click', function () {
                elegidos.delete(id);
                dibujar();
            });

            const campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = opciones.nombreCampo;
            campo.value = id;

            chip.appendChild(nombre);
            chip.appendChild(quitar);
            chip.appendChild(campo);
            etiquetas.appendChild(chip);
        });
    }

    crearAutocompletado({
        input: opciones.input,
        lista: opciones.lista,
        url: opciones.url,
        // Los ya elegidos no vuelven a aparecer en la lista: se piden al servidor sin ellos
        parametros: function () {
            const fijos = typeof opciones.parametros === 'function' ? opciones.parametros() : opciones.parametros;
            return Object.assign({}, fijos || {}, { excluir: Array.from(elegidos.keys()).join(',') });
        },
        alSeleccionar: function (item) {
            elegidos.set(String(item.id), item.texto);
            opciones.input.value = ''; // el texto queda en la etiqueta, el campo se libera para buscar a otro
            dibujar();
        }
    });

    return {
        agregar: function (id, texto) {
            elegidos.set(String(id), texto);
            dibujar();
        },
        limpiar: function () {
            elegidos.clear();
            dibujar();
        }
    };
}
