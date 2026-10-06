-- =========================================================
-- DATOS INICIALES Y SEMILLA (seed_data.sql)
-- Base de datos: db_control_stock
-- Adaptado a Requerimientos Funcionales y Sedes del Poder Judicial de Corrientes
-- =========================================================

-- 1. Roles del Sistema
-- Basado en RF-02, simplificado a tres roles: admin, pañolero y vista (solo lectura).
INSERT INTO rol (id_rol, rol, descripcion) VALUES
(1, 'admin', 'Acceso total, gestión de usuarios, OTs e inventario'),
(2, 'pañolero', 'Supervisión de pañol, entregas, recepciones y auditoría'),
(3, 'vista', 'Acceso exclusivo de lectura y generación de reportes')
ON CONFLICT (id_rol) DO UPDATE SET rol = EXCLUDED.rol, descripcion = EXCLUDED.descripcion;

-- 2. Tipos de Insumo
INSERT INTO tipo (id_tipo, tipo, detalle) VALUES
(1, 'Material', 'Insumos y materiales consumibles'),
(2, 'Herramienta', 'Equipos y herramientas de uso y devolución individual')
ON CONFLICT (id_tipo) DO UPDATE SET tipo = EXCLUDED.tipo, detalle = EXCLUDED.detalle;

-- 3. Unidades de Medida
-- Basado en RF-11: unidad, litro, kilogramo, metro, plancha, bolsa, etc.
INSERT INTO unidad_medida (id_unidad_medida, unidad_medida) VALUES
(1, 'Unidad'),
(2, 'Metros'),
(3, 'Litros'),
(4, 'Kilogramos'),
(5, 'Plancha'),
(6, 'Bolsa'),
(7, 'Caja'),
(8, 'Rollo')
ON CONFLICT (id_unidad_medida) DO UPDATE SET unidad_medida = EXCLUDED.unidad_medida;

-- 4. Ubicaciones en Pañol / Depósito
-- Basado en RF-12: ubicación física dentro del depósito/pañol
INSERT INTO ubicacion (id_ubicacion, ubicacion) VALUES
(1, 'Depósito Central - Estante A1'),
(2, 'Depósito Central - Estante B2'),
(3, 'Pañol Sector Herramientas - Armario 1'),
(4, 'Pañol Sector Pintura y Químicos')
ON CONFLICT (id_ubicacion) DO UPDATE SET ubicacion = EXCLUDED.ubicacion;

-- 5. Estados de Herramienta
-- Basado en RF-15: disponible, prestada, en reparación, dada de baja
INSERT INTO estado_herramienta (id_estado_herramienta, estado) VALUES
(1, 'Disponible'),
(2, 'Prestada'),
(3, 'En Reparación'),
(4, 'Dada de baja')
ON CONFLICT (id_estado_herramienta) DO UPDATE SET estado = EXCLUDED.estado;

-- 6. Tipos de Movimiento
-- Basado en RF-21, RF-25, RF-32, RF-33 (Ingresos, Entregas, Devoluciones, Ajustes)
INSERT INTO tipo_movimiento (id_tipo_mov, tipo) VALUES
(1, 'Entrega'),
(2, 'Devolución'),
(3, 'Ingreso'),
(4, 'Ajuste de Inventario')
ON CONFLICT (id_tipo_mov) DO UPDATE SET tipo = EXCLUDED.tipo;

-- 7. Localidades y Jurisdicciones / Sedes de la Provincia de Corrientes
-- Basado en la división por Circunscripciones Judiciales y sedes reales del Poder Judicial de Corrientes
INSERT INTO localidad (id_localidad, localidad) VALUES
(1, 'Corrientes'),
(2, 'Goya'),
(3, 'Curuzú Cuatiá'),
(4, 'Paso de los Libres'),
(5, 'Santo Tomé')
ON CONFLICT (id_localidad) DO UPDATE SET localidad = EXCLUDED.localidad;

INSERT INTO jurisdiccion (id_jurisdiccion, jurisdiccion, direccion) VALUES
(1, '1ª Circunscripción - Casa Central (Edificio Histórico)', 'Carlos Pellegrini 917, Corrientes'),
(2, '1ª Circunscripción - Edificio Juan Pujol', 'San Juan 435, Corrientes'),
(3, '2ª Circunscripción - Sede Goya', 'Ejército Argentino 450, Goya'),
(4, '3ª Circunscripción - Sede Curuzú Cuatiá', 'Gral. Ramírez 638, Curuzú Cuatiá'),
(5, '4ª Circunscripción - Sede Paso de los Libres', 'Sitja Nin 988, Paso de los Libres'),
(6, '5ª Circunscripción - Sede Santo Tomé', 'San Martín 1071, Santo Tomé')
ON CONFLICT (id_jurisdiccion) DO UPDATE SET jurisdiccion = EXCLUDED.jurisdiccion;

-- 8. Rubros iniciales
-- Basado en RF-06: Carpintería, Albañilería, Pintura, Herrería 
INSERT INTO rubro (id_rubro, nombre, descripcion, activo) VALUES
(1, 'Carpintería', 'Maderas, herrajes, placas y herramientas de corte', TRUE),
(2, 'Albañilería', 'Cemento, ladrillos, mezclas, cal y agregados', TRUE),
(3, 'Pintura', 'Látex, esmaltes, brochas, rodillos y solventes', TRUE),
(4, 'Herrería', 'Electrodos, discos de corte, perfiles y soldadura', TRUE)
ON CONFLICT (id_rubro) DO UPDATE SET nombre = EXCLUDED.nombre;

-- 9. Reajuste de secuencias
-- Los datos de arriba se insertaron con ID explícito: se alinean las secuencias para que los
-- registros que se creen desde el sistema no choquen con estos IDs.
SELECT setval(pg_get_serial_sequence('rol', 'id_rol'), (SELECT MAX(id_rol) FROM rol));
SELECT setval(pg_get_serial_sequence('tipo', 'id_tipo'), (SELECT MAX(id_tipo) FROM tipo));
SELECT setval(pg_get_serial_sequence('unidad_medida', 'id_unidad_medida'), (SELECT MAX(id_unidad_medida) FROM unidad_medida));
SELECT setval(pg_get_serial_sequence('ubicacion', 'id_ubicacion'), (SELECT MAX(id_ubicacion) FROM ubicacion));
SELECT setval(pg_get_serial_sequence('estado_herramienta', 'id_estado_herramienta'), (SELECT MAX(id_estado_herramienta) FROM estado_herramienta));
SELECT setval(pg_get_serial_sequence('tipo_movimiento', 'id_tipo_mov'), (SELECT MAX(id_tipo_mov) FROM tipo_movimiento));
SELECT setval(pg_get_serial_sequence('localidad', 'id_localidad'), (SELECT MAX(id_localidad) FROM localidad));
SELECT setval(pg_get_serial_sequence('jurisdiccion', 'id_jurisdiccion'), (SELECT MAX(id_jurisdiccion) FROM jurisdiccion));
SELECT setval(pg_get_serial_sequence('rubro', 'id_rubro'), (SELECT MAX(id_rubro) FROM rubro));
