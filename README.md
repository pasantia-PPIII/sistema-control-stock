# Sistema de Control de Stock – Taller ONABE

Sistema web para controlar el stock de insumos y herramientas del taller: movimientos de entrega y devolución, órdenes de trabajo con su comisión de operarios, rubros, operarios, usuarios y reportes.

Desarrollado en **PHP** (páginas procedurales + clases en `clases/`) con **PostgreSQL** (PDO).

## Requisitos

- PHP 8.0 o superior con las extensiones `pdo_pgsql` y `pgsql`
- PostgreSQL
- Servidor web local (WAMP, XAMPP) o Apache/Nginx

## Instalación rápida

La guía de instalación resumida (WAMP + PostgreSQL local):

1. Clonar el repositorio dentro de la carpeta pública del servidor (`www` en WAMP).
2. Crear la base `db_control_stock` y ejecutar, en orden, `bd/db_control_stock.sql`, `bd/seed_data.sql` y (solo desarrollo) `bd/test_data.sql`.
3. Copiar `config/env.example.php` como `config/env.php` y completar las credenciales propias.
4. Abrir `http://localhost/sistema-control-stock/`.

## Configuración (`config/env.php`)

Este archivo es **personal y no se sube al repositorio**. Se crea copiando `config/env.example.php`:

| Constante | Descripción |
|---|---|
| `DB_HOST`, `DB_PORT` | Servidor y puerto de PostgreSQL (por defecto `localhost` y `5432`) |
| `DB_NAME` | Nombre de la base (`db_control_stock`) |
| `DB_USER`, `DB_PASS` | Credenciales de PostgreSQL de cada persona |
| `APP_DEBUG` | `true` solo en desarrollo (muestra errores). En producción, `false` |
| `BASE_URL` | Opcional. Si se omite se deduce automáticamente de la petición |

## Roles y permisos

| Rol | Permisos |
|---|---|
| **admin** | Acceso total: usuarios, operarios, rubros, insumos, movimientos, órdenes de trabajo y reportes |
| **pañolero** | Crea, edita y deshabilita insumos, movimientos (incluye anularlos) y órdenes de trabajo; ve reportes |
| **vista** | Solo lectura en todo el sistema |

- Los botones se muestran según el rol y el servidor valida el rol en cada endpoint (`requireRole()`).
- Un administrador no puede deshabilitar su propio usuario.
- Primer ítem del menú: **Panel de Control**, con un resumen distinto por rol.

## Reglas del sistema

- **Nada se borra de la base de datos.** Operarios, usuarios, insumos, rubros, movimientos y órdenes se *deshabilitan* (`activo = FALSE`). La aplicación no tiene métodos `DELETE`.
- **Stock de materiales:** una Entrega descuenta y una Devolución suma. El stock nunca queda negativo.
- **Cargar o corregir stock** se hace desde el módulo **Insumos**, no con movimientos: al **crear** un insumo se indica el *Stock Inicial* (obligatorio en los materiales; si no hay existencias se carga 0; las herramientas nacen con 1) y al **editarlo** se cambia el *Stock Actual* (carga de mercadería, recuento físico o corrección). El valor ingresado pasa a ser el stock del insumo. Hoy ese cambio no deja registro del valor anterior.
- **Herramientas:** son unidades enteras. Stock **1** = está en el pañol (estado Disponible); stock **0** = no está (Prestada, En Reparación o Dada de baja). Al crearla nace con stock 1. Una Entrega exige que esté Disponible con stock 1 y pasa el stock a 0; una Devolución lo vuelve a 1 solo si regresa Disponible. La cantidad del movimiento debe ser 1 y el stock de una herramienta no se edita a mano. Las herramientas no se cuentan en el "stock bajo" del Panel ni de Reportes.
- **Anular un movimiento** revierte su efecto sobre el stock.
- **Orden de trabajo:** puede vincularse a un movimiento de entrega y a uno de devolución (ambos opcionales) y a una comisión de operarios.

## Seguridad

- Token **CSRF** en todos los formularios y peticiones `fetch` no-GET (incluido el cierre de sesión, que solo acepta POST).
- **Límite de intentos de login:** 5 intentos fallidos por DNI en 15 minutos; luego se bloquea temporalmente (tabla `intento_login`).
- Cookie de sesión `HttpOnly` + `SameSite=Lax` (y `Secure` bajo HTTPS), con regeneración del ID al iniciar sesión.
- Los errores de base de datos se registran en el log de PHP y al usuario se le muestra un mensaje genérico.
- Contraseñas guardadas con `password_hash`.

## Usuarios de prueba (solo si se cargó `bd/test_data.sql`)

| DNI | Rol | Contraseña |
|---|---|---|
| 12345678 | admin | admin123 |
| 87654321 | pañolero | admin123 |
| 10101010 | vista | admin123 |

> No cargar `test_data.sql` en producción.

## Estructura

Ver [estructura.txt](estructura.txt).

## Pendientes conocidos

- Recuperación de contraseña (`auth/forgot_password.php`): archivo vacío, todavía no implementado.
- **Auditoría completa** (accesos al sistema y cambios en registros): la pestaña **Historial** de Reportes ya muestra la auditoría de movimientos y stock para los roles Administrador y Vista. La auditoría de accesos y cambios de registros sigue pendiente; el diseño está en [AUDITORIA_PASO_A_PASO.txt](AUDITORIA_PASO_A_PASO.txt).
- Al deshabilitar un operario no se deshabilita su usuario vinculado.
