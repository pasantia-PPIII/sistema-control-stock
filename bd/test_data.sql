-- =========================================================
-- DATOS DE PRUEBA (test_data.sql)
-- Base de datos: db_control_stock
-- Incluye operarios, usuarios, stock, insumos, movimientos, OTs y comisiones
-- =========================================================

-- ---------------------------------------------------------
-- 1. USUARIOS Y OPERARIOS
-- ---------------------------------------------------------

-- 1. Operarios de prueba
INSERT INTO operario (id_operario, dni, apellido, nombre, legajo, id_rubro, activo) VALUES
(1, '35123456', 'Pérez', 'Juan Carlos', 'OP-101', 1, TRUE), -- Carpintería
(2, '38987654', 'González', 'Martín', 'OP-102', 3, TRUE), -- Pintura
(3, '40111222', 'López', 'Ramiro', 'OP-103', 4, TRUE), -- Herrería
(4, '36555444', 'Ramírez', 'Carlos', 'OP-104', 2, TRUE)  -- Albañilería
ON CONFLICT (dni) DO NOTHING;

-- 2. Usuarios del Sistema
-- Contraseña para todos: admin123 (hasheada con BCRYPT)
INSERT INTO usuario (dni, contrasena, nombre, apellido, id_rol, id_operario, activo) VALUES
('12345678', '$2y$10$ffZTGT0/fLMszdyuZPErXu3e7Oi1rvi4qazCtkqxYLYNmTD3wM79a', 'Administrador', 'General', 1, NULL, TRUE),
('87654321', '$2y$10$ffZTGT0/fLMszdyuZPErXu3e7Oi1rvi4qazCtkqxYLYNmTD3wM79a', 'Pañolero', 'Encargado', 2, NULL, TRUE),
('10101010', '$2y$10$ffZTGT0/fLMszdyuZPErXu3e7Oi1rvi4qazCtkqxYLYNmTD3wM79a', 'Usuario', 'Consulta', 3, NULL, TRUE)
ON CONFLICT (dni) DO UPDATE 
SET contrasena = EXCLUDED.contrasena, 
    nombre = EXCLUDED.nombre, 
    apellido = EXCLUDED.apellido,
    id_rol = EXCLUDED.id_rol;

-- ---------------------------------------------------------
-- 2. INSUMOS Y REGISTRO DE STOCK
-- ---------------------------------------------------------

-- 3. Registros de Stock Actual
INSERT INTO stock_actual (id_stock_actual, stock_actual, fecha) VALUES
(1, 15.00, CURRENT_DATE),
(2, 40.00, CURRENT_DATE),
(3, 25.00, CURRENT_DATE),
(4, 12.00, CURRENT_DATE),
(5, 18.00, CURRENT_DATE),
(6, 10.00, CURRENT_DATE),
(7, 28.00, CURRENT_DATE),
(8, 15.00, CURRENT_DATE),
(9, 30.00, CURRENT_DATE),
(10, 25.00, CURRENT_DATE),
(11, 50.00, CURRENT_DATE),
(12, 45.00, CURRENT_DATE),
(13, 8.00, CURRENT_DATE),
(14, 12.00, CURRENT_DATE),
(15, 5.00, CURRENT_DATE),
(16, 60.00, CURRENT_DATE),
(17, 95.00, CURRENT_DATE),
(18, 40.00, CURRENT_DATE),
(19, 15.00, CURRENT_DATE),
(20, 8.00, CURRENT_DATE);

-- 4. Insumos (Materiales consumibles y Herramientas)
INSERT INTO insumos (id_insumo, codigo, nombre, id_unidad_medida, id_tipo, id_rubro, id_ubicacion, id_stock_actual, stock_minimo, es_perecedero, fecha_vencimiento, serie_modelo, fecha_registro, activo) VALUES 
-- Pinturas y Esmaltes (Litros)
(1, '24027', 'Pintura látex acrílico antihongo negro', 3, 1, 3, 4, 1, 5.00, TRUE, '2026-12-31', NULL, CURRENT_DATE, TRUE),
(2, '24000', 'Pintura látex interior mate base blanca', 3, 1, 3, 4, 2, 10.00, TRUE, '2026-10-15', NULL, CURRENT_DATE, TRUE),
(3, '24001', 'Pintura látex antihongo interior-exterior mate base blanca', 3, 1, 3, 4, 3, 10.00, TRUE, '2026-11-20', NULL, CURRENT_DATE, TRUE),
(4, '24004', 'Esmalte satinado al aceite negro', 3, 1, 3, 4, 4, 4.00, TRUE, '2025-08-10', NULL, CURRENT_DATE, TRUE),
(5, '24006', 'Esmalte satinado al agua blanco', 3, 1, 3, 4, 5, 5.00, TRUE, '2026-05-05', NULL, CURRENT_DATE, TRUE),
(6, '24532', 'Pintura para piso', 3, 1, 3, 4, 6, 5.00, TRUE, '2027-01-01', NULL, CURRENT_DATE, TRUE),

-- Accesorios de Pintura (Unidades, Rollos, Paquetes)
(7, '24018', 'Pincel Nº 30 cerda blanca', 1, 1, 3, 1, 7, 10.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(8, '24019', 'Pincel nº 25 cerda blanca', 1, 1, 3, 1, 8, 10.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(9, '24013', 'Cinta papel de pintor para enmascarar de 36mm x 50m', 8, 1, 3, 1, 9, 15.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(10, '24014', 'Cinta papel de pintor para enmascarar de 24mm x 50m', 8, 1, 3, 1, 10, 15.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(11, '24007', 'Viruta grosor mediano', 7, 1, 3, 4, 11, 20.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(12, '24008', 'Viruta grosor fino', 7, 1, 3, 4, 12, 20.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),

-- Herramientas Eléctricas / Manuales (Trazables/Consumibles)
(13, '24024', 'Lija circular con velcro p/ máquina roto orbital (125 mm) Nº 80', 1, 1, 3, 3, 13, 5.00, FALSE, NULL, 'Bosch GEX 125', CURRENT_DATE, TRUE),
(14, '24025', 'Lija circular con velcro p/ máquina roto orbital (125 mm) Nº 100', 1, 1, 3, 3, 14, 5.00, FALSE, NULL, 'Bosch GEX 125', CURRENT_DATE, TRUE),
(15, '24751', 'Pistola para pintar', 1, 2, 3, 3, 15, 2.00, FALSE, NULL, 'Sata Jet 1000', CURRENT_DATE, TRUE),
(16, '24503', 'Lija al agua pliego', 1, 1, 3, 1, 16, 20.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),

-- Insumos de Preparación (Litros y Bolsas)
(17, '24002', 'Diluyente aguarrás', 3, 1, 3, 4, 17, 20.00, TRUE, '2026-06-30', NULL, CURRENT_DATE, TRUE),
(18, '24003', 'Thinner Diluyente', 3, 1, 3, 4, 18, 15.00, TRUE, '2026-07-15', NULL, CURRENT_DATE, TRUE),
(19, '24010', 'Impermeabilizante acrílico elástico frentes/muros transparente', 3, 1, 3, 4, 19, 5.00, TRUE, '2027-03-01', NULL, CURRENT_DATE, TRUE),
(20, '24016', 'Mezcla adhesiva a base de yeso para interior', 6, 1, 2, 4, 20, 5.00, TRUE, '2025-12-01', NULL, CURRENT_DATE, TRUE)
ON CONFLICT (codigo) DO UPDATE SET 
    nombre = EXCLUDED.nombre,
    id_stock_actual = EXCLUDED.id_stock_actual,
    stock_minimo = EXCLUDED.stock_minimo;

-- Las herramientas arrancan en estado Disponible (las entregas exigen ese estado)
UPDATE insumos SET id_estado_herramienta = 1 WHERE id_tipo = 2 AND id_estado_herramienta IS NULL;

-- ---------------------------------------------------------
-- 3. ASIGNACIONES Y MOVIMIENTOS
-- ---------------------------------------------------------

-- 5. Herramientas (Prestadas / Asignaciones individuales)
INSERT INTO herramienta (id_operario, fecha_entrega, fecha_devolucion, id_estado_herramienta, observaciones, activo) VALUES 
(1, CURRENT_DATE - INTERVAL '2 days', NULL, 2, 'Préstamo para pintar oficina del Juzgado Civil N°3', TRUE),
(2, CURRENT_DATE - INTERVAL '10 days', CURRENT_DATE - INTERVAL '8 days', 1, 'Lijado de paredes en Mantenimiento', TRUE),
(3, CURRENT_DATE - INTERVAL '5 days', CURRENT_DATE - INTERVAL '4 days', 1, 'Pintura de rejas en Familia N°1', TRUE),
(4, CURRENT_DATE - INTERVAL '1 day', NULL, 2, 'Uso de pistola para pintar en Depósito', TRUE),
(1, NULL, NULL, 3, 'Extensor telescópico con falla mecánica', TRUE),
(2, NULL, NULL, 1, 'Rodillo para pintar epoxi N°5', TRUE);

-- 6. Movimientos (Cabeceras)
INSERT INTO movimiento (id_movimiento, id_tipo_mov, id_operario, dni_usuario, fecha, hora, observaciones, activo) VALUES 
-- Entradas por compra (Tipo 3 = Ingreso)
(1, 3, 4, '12345678', CURRENT_DATE - INTERVAL '30 days', '09:00:00', 'Compra inicial de insumos de pinturería', TRUE),
(2, 3, 4, '87654321', CURRENT_DATE - INTERVAL '30 days', '09:30:00', 'Compra de herramientas manuales', TRUE),
-- Salidas por consumo (Tipo 1 = Entrega)
(3, 1, 1, '87654321', CURRENT_DATE - INTERVAL '2 days', '10:00:00', 'Salida para ODT de pintura en Juzgado Civil', TRUE),
(4, 1, 2, '87654321', CURRENT_DATE - INTERVAL '5 days', '11:00:00', 'Salida para mantenimiento de rejas', TRUE),
(5, 1, 3, '12345678', CURRENT_DATE - INTERVAL '1 day', '14:00:00', 'Consumo de thinner para limpieza', TRUE),
-- Ajustes de inventario (Tipo 4 = Ajuste)
(6, 4, 4, '12345678', CURRENT_DATE - INTERVAL '15 days', '16:00:00', 'Ajuste por recuento físico de stock', TRUE);

-- 7. Detalles de Movimientos (Vincular id_insumo con id_movimiento)
INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, id_estado_herramienta) VALUES 
-- Detalle Movimiento 1 (Entrada por compra de pintura)
(1, 1, 20.00, NULL),
(1, 2, 50.00, NULL),
(1, 7, 30.00, NULL),
(1, 17, 100.00, NULL),
-- Detalle Movimiento 2 (Entrada herramientas)
(2, 13, 10.00, NULL),
(2, 15, 1.00, NULL),
-- Detalle Movimiento 3 (Salida por consumo ODT)
(3, 1, 5.00, NULL),
(3, 7, 2.00, NULL),
-- Detalle Movimiento 4 (Salida mantenimiento)
(4, 2, 10.00, NULL),
(4, 17, 5.00, NULL),
-- Detalle Movimiento 5 (Salida limpieza)
(5, 18, 3.00, NULL),
-- Detalle Movimiento 6 (Ajuste)
(6, 13, 2.00, NULL);

-- ---------------------------------------------------------
-- 4. ÓRDENES DE TRABAJO Y COMISIONES
-- ---------------------------------------------------------

-- 8. Órdenes de Trabajo (ODT)
INSERT INTO orden_de_trabajo (id_odt, numero_ot, id_mov_egreso, id_mov_devolucion, id_localidad, id_jurisdiccion, pa, trabajo_a_realizar, fecha_inicio, fecha_final, hora_inicio, hora_final, estado, observaciones, activo) VALUES 
(1, 'OT-2024-001', 3, NULL, 1, 1, 'PA-2024-001', 'Pintura de oficina y pasillos del Juzgado Civil N°3', CURRENT_DATE - INTERVAL '2 days', CURRENT_DATE + INTERVAL '2 days', '08:00:00', '17:00:00', 'En Proceso', 'Se utilizó pintura antihongo negra', TRUE),
(2, 'OT-2024-002', 4, NULL, 2, 3, 'PA-2024-002', 'Mantenimiento y pintura de rejas perimetrales', CURRENT_DATE - INTERVAL '5 days', CURRENT_DATE - INTERVAL '3 days', '09:00:00', '16:00:00', 'Finalizada', 'Trabajo completado satisfactoriamente', TRUE),
(3, 'OT-2024-003', NULL, NULL, 1, 2, 'PA-2024-003', 'Reparación de paredes en Depósito Central', CURRENT_DATE, CURRENT_DATE + INTERVAL '1 day', '10:00:00', '15:00:00', 'Pendiente', 'Falta material de mezcla', TRUE);

-- 9. Comisiones (operarios asignados a cada ODT, por id_operario)
INSERT INTO comision (id_odt, id_operario) VALUES 
(1, 1),
(1, 2),
(2, 3),
(2, 4),
(3, 2);

-- Vincula las entregas con la orden de trabajo que las usa (igual que hace el sistema al vincular)
UPDATE movimiento SET id_odt = 1 WHERE id_movimiento = 3;
UPDATE movimiento SET id_odt = 2 WHERE id_movimiento = 4;

-- ---------------------------------------------------------
-- 5. REAJUSTE DE SECUENCIAS
-- ---------------------------------------------------------

SELECT setval('operario_id_operario_seq', (SELECT COALESCE(MAX(id_operario), 1) FROM operario));
SELECT setval('stock_actual_id_stock_actual_seq', (SELECT COALESCE(MAX(id_stock_actual), 1) FROM stock_actual));
SELECT setval('insumos_id_insumo_seq', (SELECT COALESCE(MAX(id_insumo), 1) FROM insumos));
SELECT setval('herramienta_id_herramienta_seq', (SELECT COALESCE(MAX(id_herramienta), 1) FROM herramienta));
SELECT setval('movimiento_id_movimiento_seq', (SELECT COALESCE(MAX(id_movimiento), 1) FROM movimiento));
SELECT setval('movimiento_detalle_id_detalle_seq', (SELECT COALESCE(MAX(id_detalle), 1) FROM movimiento_detalle));
SELECT setval('orden_de_trabajo_id_odt_seq', (SELECT COALESCE(MAX(id_odt), 1) FROM orden_de_trabajo));
SELECT setval('comision_id_comision_seq', (SELECT COALESCE(MAX(id_comision), 1) FROM comision));