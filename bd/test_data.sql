-- =========================================================
-- DATOS DE PRUEBA (test_data.sql)
-- Base de datos: db_control_stock
-- Incluye operarios, usuario inicial, insumos, movimientos, OTs y comisiones
-- =========================================================

-- ---------------------------------------------------------
-- 1. USUARIOS, OPERARIOS E INSUMOS
-- ---------------------------------------------------------

-- 1. Operarios de prueba
-- Basado en RF-43: listado de operarios con nombre, legajo y rubro/especialidad
INSERT INTO operario (id_operario, dni, apellido, nombre, legajo, id_rubro, activo) VALUES
(1, '35123456', 'Pérez', 'Juan Carlos', 'OP-101', 1, TRUE), -- Carpintería
(2, '38987654', 'González', 'Martín', 'OP-102', 3, TRUE), -- Pintura
(3, '40111222', 'López', 'Ramiro', 'OP-103', 4, TRUE), -- Herrería
(4, '36555444', 'Ramírez', 'Carlos', 'OP-104', 2, TRUE)  -- Albañilería
ON CONFLICT (dni) DO NOTHING;

-- 2. Usuario Administrador Inicial
-- DNI: 12345678 (se usa como usuario para iniciar sesión)
-- Contraseña: admin123 (hasheada con BCRYPT)
INSERT INTO usuario (dni, contrasena, nombre, apellido, id_rol, id_operario, activo) VALUES
('12345678', '$2y$10$ffZTGT0/fLMszdyuZPErXu3e7Oi1rvi4qazCtkqxYLYNmTD3wM79a', 'Administrador', 'General', 1, NULL, TRUE)
ON CONFLICT (dni) DO UPDATE 
SET contrasena = EXCLUDED.contrasena, 
    nombre = EXCLUDED.nombre, 
    apellido = EXCLUDED.apellido;

-- 3. Insumos (Materiales consumibles y Herramientas)
INSERT INTO insumos (id_insumo, codigo, nombre, id_unidad_medida, id_tipo, id_rubro, id_ubicacion, stock_actual, stock_minimo, es_perecedero, fecha_vencimiento, serie_modelo, fecha_registro, activo) VALUES 
-- Pinturas y Esmaltes (Litros)
(1, '24027', 'Pintura látex acrílico antihongo negro', 3, 1, 3, 4, 15.00, 5.00, TRUE, '2026-12-31', NULL, CURRENT_DATE, TRUE),
(2, '24000', 'Pintura látex interior mate base blanca', 3, 1, 3, 4, 40.00, 10.00, TRUE, '2026-10-15', NULL, CURRENT_DATE, TRUE),
(3, '24001', 'Pintura látex antihongo interior-exterior mate base blanca', 3, 1, 3, 4, 25.00, 10.00, TRUE, '2026-11-20', NULL, CURRENT_DATE, TRUE),
(4, '24004', 'Esmalte satinado al aceite negro', 3, 1, 3, 4, 12.00, 4.00, TRUE, '2025-08-10', NULL, CURRENT_DATE, TRUE),
(5, '24006', 'Esmalte satinado al agua blanco', 3, 1, 3, 4, 18.00, 5.00, TRUE, '2026-05-05', NULL, CURRENT_DATE, TRUE),
(6, '24532', 'Pintura para piso', 3, 1, 3, 4, 10.00, 5.00, TRUE, '2027-01-01', NULL, CURRENT_DATE, TRUE),

-- Accesorios de Pintura (Unidades, Rollos, Paquetes)
(7, '24018', 'Pincel Nº 30 cerda blanca', 1, 1, 3, 1, 28.00, 10.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(8, '24019', 'Pincel nº 25 cerda blanca', 1, 1, 3, 1, 15.00, 10.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(9, '24013', 'Cinta papel de pintor para enmascarar de 36mm x 50m', 8, 1, 3, 1, 30.00, 15.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(10, '24014', 'Cinta papel de pintor para enmascarar de 24mm x 50m', 8, 1, 3, 1, 25.00, 15.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(11, '24007', 'Viruta grosor mediano', 7, 1, 3, 4, 50.00, 20.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),
(12, '24008', 'Viruta grosor fino', 7, 1, 3, 4, 45.00, 20.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),

-- Herramientas Eléctricas / Manuales (Trazables/Consumibles)
(13, '24024', 'Lija circular con velcro p/ máquina roto orbital (125 mm) Nº 80', 1, 1, 3, 3, 8.00, 5.00, FALSE, NULL, 'Bosch GEX 125', CURRENT_DATE, TRUE),
(14, '24025', 'Lija circular con velcro p/ máquina roto orbital (125 mm) Nº 100', 1, 1, 3, 3, 12.00, 5.00, FALSE, NULL, 'Bosch GEX 125', CURRENT_DATE, TRUE),
(15, '24751', 'Pistola para pintar', 1, 2, 3, 3, 5.00, 2.00, FALSE, NULL, 'Sata Jet 1000', CURRENT_DATE, TRUE),
(16, '24503', 'Lija al agua pliego', 1, 1, 3, 1, 60.00, 20.00, FALSE, NULL, NULL, CURRENT_DATE, TRUE),

-- Insumos de Preparación (Litros y Bolsas)
(17, '24002', 'Diluyente aguarrás', 3, 1, 3, 4, 95.00, 20.00, TRUE, '2026-06-30', NULL, CURRENT_DATE, TRUE),
(18, '24003', 'Thinner Diluyente', 3, 1, 3, 4, 40.00, 15.00, TRUE, '2026-07-15', NULL, CURRENT_DATE, TRUE),
(19, '24010', 'Impermeabilizante acrílico elástico frentes/muros transparente', 3, 1, 3, 4, 15.00, 5.00, TRUE, '2027-03-01', NULL, CURRENT_DATE, TRUE),
(20, '24016', 'Mezcla adhesiva a base de yeso para interior', 6, 1, 2, 4, 8.00, 5.00, TRUE, '2025-12-01', NULL, CURRENT_DATE, TRUE)
ON CONFLICT (codigo) DO UPDATE SET 
    nombre = EXCLUDED.nombre,
    stock_actual = EXCLUDED.stock_actual,
    stock_minimo = EXCLUDED.stock_minimo;

-- 4. Herramientas (Prestadas / Asignaciones individuales)
INSERT INTO herramienta (id_operario, fecha_entrega, fecha_devolucion, id_estado_herramienta, observaciones, activo) VALUES 
('35123456', CURRENT_DATE - INTERVAL '2 days', NULL, 2, 'Préstamo para pintar oficina del Juzgado Civil N°3', TRUE),
('38987654', CURRENT_DATE - INTERVAL '10 days', CURRENT_DATE - INTERVAL '8 days', 1, 'Lijado de paredes en Mantenimiento', TRUE),
('40111222', CURRENT_DATE - INTERVAL '5 days', CURRENT_DATE - INTERVAL '4 days', 1, 'Pintura de rejas en Familia N°1', TRUE),
('36555444', CURRENT_DATE - INTERVAL '1 day', NULL, 2, 'Uso de pistola para pintar en Depósito', TRUE),
('35123456', NULL, NULL, 3, 'Extensor telescópico con falla mecánica', TRUE),
('38987654', NULL, NULL, 1, 'Rodillo para pintar epoxi N°5', TRUE);

-- 5. Movimientos (Cabeceras - Autenticación con DNI de Usuario)
INSERT INTO movimiento (id_movimiento, id_tipo_mov, id_operario, dni_usuario, fecha, hora, observaciones, activo) VALUES 
-- Entradas por compra (Tipo 3 = Ingreso)
(1, 3, 4, '12345678', CURRENT_DATE - INTERVAL '30 days', '09:00:00', 'Compra inicial de insumos de pinturería', TRUE),
(2, 3, 4, '12345678', CURRENT_DATE - INTERVAL '30 days', '09:30:00', 'Compra de herramientas manuales', TRUE),
-- Salidas por consumo (Tipo 1 = Entrega)
(3, 1, 1, '12345678', CURRENT_DATE - INTERVAL '2 days', '10:00:00', 'Salida para ODT de pintura en Juzgado Civil', TRUE),
(4, 1, 2, '12345678', CURRENT_DATE - INTERVAL '5 days', '11:00:00', 'Salida para mantenimiento de rejas', TRUE),
(5, 1, 3, '12345678', CURRENT_DATE - INTERVAL '1 day', '14:00:00', 'Consumo de thinner para limpieza', TRUE),
-- Ajustes de inventario (Tipo 4 = Ajuste)
(6, 4, 4, '12345678', CURRENT_DATE - INTERVAL '15 days', '16:00:00', 'Ajuste por recuento físico de stock', TRUE);

-- 6. Detalles de Movimientos (Vincular id_insumo con id_movimiento)
INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, id_estado_herramienta) VALUES 
-- Detalle Movimiento 1 (Entrada por compra de pintura)
(1, 1, 20.00, NULL),  -- Pintura látex acrílico antihongo negro
(1, 2, 50.00, NULL),  -- Pintura látex interior mate base blanca
(1, 7, 30.00, NULL),  -- Pincel Nº 30
(1, 17, 100.00, NULL),-- Diluyente aguarrás
-- Detalle Movimiento 2 (Entrada herramientas)
(2, 13, 10.00, NULL), -- Lija circular Nº 80
(2, 15, 5.00, NULL),  -- Pistola para pintar
-- Detalle Movimiento 3 (Salida por consumo ODT)
(3, 1, 5.00, NULL),   -- Pintura látex antihongo negro
(3, 7, 2.00, NULL),   -- Pincel Nº 30
-- Detalle Movimiento 4 (Salida mantenimiento)
(4, 2, 10.00, NULL),  -- Pintura látex blanca
(4, 17, 5.00, NULL),  -- Diluyente aguarrás
-- Detalle Movimiento 5 (Salida limpieza)
(5, 18, 3.00, NULL),  -- Thinner Diluyente
-- Detalle Movimiento 6 (Ajuste)
(6, 13, 2.00, NULL);  -- Lija circular Nº 80

-- 7. Órdenes de Trabajo (ODT) - Se agrega la columna obligatoria numero_ot
INSERT INTO orden_de_trabajo (id_odt, numero_ot, id_mov_egreso, id_mov_devolucion, id_localidad, id_jurisdiccion, pa, trabajo_a_realizar, fecha_inicio, fecha_final, hora_inicio, hora_final, estado, observaciones, activo) VALUES 
(1, 'OT-2024-001', 3, NULL, 1, 1, 'PA-2024-001', 'Pintura de oficina y pasillos del Juzgado Civil N°3', CURRENT_DATE - INTERVAL '2 days', CURRENT_DATE + INTERVAL '2 days', '08:00:00', '17:00:00', 'En Proceso', 'Se utilizó pintura antihongo negra', TRUE),
(2, 'OT-2024-002', 4, NULL, 2, 3, 'PA-2024-002', 'Mantenimiento y pintura de rejas perimetrales', CURRENT_DATE - INTERVAL '5 days', CURRENT_DATE - INTERVAL '3 days', '09:00:00', '16:00:00', 'Finalizada', 'Trabajo completado satisfactoriamente', TRUE),
(3, 'OT-2024-003', NULL, NULL, 1, 2, 'PA-2024-003', 'Reparación de paredes en Depósito Central', CURRENT_DATE, CURRENT_DATE + INTERVAL '1 day', '10:00:00', '15:00:00', 'Pendiente', 'Falta material de mezcla', TRUE);

-- 8. Comisiones (Operarios asignados a ODTs mediante DNI)
INSERT INTO comision (id_odt, id_operario) VALUES 
(1, '35123456'),
(1, '38987654'),
(2, '40111222'),
(2, '36555444'),
(3, '38987654');

-- 9. Ajustar las secuencias de las claves SERIAL
SELECT setval('insumos_id_insumo_seq', (SELECT COALESCE(MAX(id_insumo), 1) FROM insumos));
SELECT setval('herramienta_id_herramienta_seq', (SELECT COALESCE(MAX(id_herramienta), 1) FROM herramienta));
SELECT setval('movimiento_id_movimiento_seq', (SELECT COALESCE(MAX(id_movimiento), 1) FROM movimiento));
SELECT setval('movimiento_detalle_id_detalle_seq', (SELECT COALESCE(MAX(id_detalle), 1) FROM movimiento_detalle));
SELECT setval('orden_de_trabajo_id_odt_seq', (SELECT COALESCE(MAX(id_odt), 1) FROM orden_de_trabajo));
SELECT setval('comision_id_comision_seq', (SELECT COALESCE(MAX(id_comision), 1) FROM comision));