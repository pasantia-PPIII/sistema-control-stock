# Reglas de Migración del Proyecto

## Origen
- Proyecto viejo: `practica/` (o ruta relativa correspondiente).

## Destino
- Proyecto nuevo: `Sistema DeControl/`.

## Directivas Técnicas Obligatorias
1. **Base de Datos:**
   - Motor: **PostgreSQL** (usar PDO con driver `pgsql`).
   - Reemplazar sintaxis SQL Server / MySQL (`IDENTITY`, `AUTO_INCREMENT`, `TOP`, etc.) por sintaxis PostgreSQL (`SERIAL`, `LIMIT/OFFSET`, cast explícito como `::estado_registro`).
   - El archivo de conexión es `config/database.php`.

2. **Sin Iconos:**
   - No usar emojis ni iconos en el sidebar (`layouts/header.php`), ni en botones de acción (usar texto claro: "Editar", "Deshabilitar", "Ver", etc.).

3. **Arquitectura sin recarga de página (Single Page / AJAX):**
   - En cada módulo (`usuarios/`, `insumo/`, etc.), el archivo `dashboard.php` contiene la interfaz y la grilla visual.
   - Los archivos `crear_*.php`, `editar_*.php` y `deshabilitar_*.php` actúan como **endpoints que reciben peticiones POST y responden en JSON**.
   - Toda interacción desde `dashboard.php` debe realizarse mediante modales y JavaScript (`fetch()`).

4. **Estilos:**
   - Utilizar las hojas de estilo existentes en `assets/css/` de forma desacoplada; no incrustar CSS en las vistas PHP.