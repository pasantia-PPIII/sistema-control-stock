# Sistema de Control de Stock – Taller ONABE

Sistema web para controlar el stock de insumos y herramientas del taller: movimientos de entrega, devolución, ingreso y ajuste, órdenes de trabajo con su comisión de operarios, rubros, operarios, usuarios y reportes.

Desarrollado en **PHP** (páginas procedurales + clases en `clases/`) con **PostgreSQL** (PDO).

## Requisitos

- PHP 8.0 o superior con las extensiones `pdo_pgsql` y `pgsql`
- PostgreSQL
- Servidor web local (WAMP, XAMPP) o Apache/Nginx

## Instalación rápida

La guía completa paso a paso (WAMP + PostgreSQL local) está en [INSTALACION_LOCAL.txt](INSTALACION_LOCAL.txt). En resumen:

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
- **Stock:** una Entrega descuenta, una Devolución y un Ingreso suman. Un **Ajuste de Inventario** fija el stock en la cantidad contada físicamente (solo materiales; no se anula, se corrige con otro ajuste). El stock nunca queda negativo.
- **Herramientas:** no tienen cantidad de stock; cambian de estado (Disponible ↔ Prestada). La cantidad del movimiento debe ser 1.
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

- Recuperación de contraseña (`auth/forgot_password.php`) y edición de movimientos (`movimiento/editar_movimiento.php`): archivos vacíos.
- Pantallas para registrar **Ingreso** de stock y **Ajuste de Inventario** (el backend ya los soporta).
- Auditoría de cambios (se implementará más adelante).
- Al deshabilitar un operario no se deshabilita su usuario vinculado.
