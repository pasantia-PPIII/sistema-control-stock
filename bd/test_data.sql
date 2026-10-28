-- =========================================================
-- DATOS DE PRUEBA (test_data.sql)
-- Base de datos: db_control_stock
-- Incluye operarios, usuarios, rubros, stock, insumos, herramientas, movimientos, OTs y comisiones
--
-- USO: cargar sobre una base recién creada, en este orden:
--      bd/db_control_stock.sql -> bd/seed_data.sql -> bd/test_data.sql
--      (no es re-ejecutable sobre datos ya cargados: usa IDs explícitos).
--
-- Todas las fechas son RELATIVAS a CURRENT_DATE, así los vencimientos, las órdenes vencidas, la
-- actividad de los últimos 30 días y la evolución mensual siguen siendo válidos el día que se cargue.
--
-- El stock de cada insumo y el estado de cada herramienta NO se escriben a mano: el bloque
-- "6. RECÁLCULO DE STOCK" reproduce el historial de movimientos activos con las mismas reglas que
-- clases/movimiento.php, de modo que stock, estados y movimientos son siempre coherentes entre sí.
--
-- QUÉ SE PUEDE PROBAR CON ESTOS DATOS (resumen)
--   Reportes > Stock          12 materiales en stock crítico (uno en 0 y dos justo en el mínimo),
--                              12 perecederos vencidos o por vencer, 18 materiales con consumo
--                              reciente, 13 con stock inmóvil (>90 días sin salida),
--                              y faltantes de configuración (sin mínimo, ubicación, unidad o fecha).
--   Reportes > Movimientos    54 movimientos de los 4 tipos repartidos en 8 meses, actividad
--                              diaria de los últimos 30 días y 3 movimientos anulados (no cuentan).
--   Reportes > Herramientas   7 herramientas: 4 prestadas (6, 9, 12 y 25 días; un operario con 2),
--                              1 disponible, 1 en reparación y 1 dada de baja.
--   Reportes > Órdenes        14 órdenes activas en todos los estados, 4 vencidas, 2 herramientas
--                              sin devolver de órdenes cerradas (Finalizada y Anulada).
--   Reportes > Consumo        consumo neto (entregas - devoluciones) por material, rubro, operario,
--                              orden y jurisdicción; 3 ajustes repetidos sobre un mismo material (lijas).
--   Altas/bajas lógicas       operario inactivo, insumo inactivo y orden deshabilitada.
-- =========================================================

-- ---------------------------------------------------------
-- 1. USUARIOS Y OPERARIOS
-- ---------------------------------------------------------

-- 1. Operarios de prueba (el último está dado de baja)
INSERT INTO operario (id_operario, dni, apellido, nombre, legajo, id_rubro, activo) VALUES
(1, '35123456', 'Pérez', 'Juan Carlos', 'OP-101', 1, TRUE), -- Carpintería
(2, '38987654', 'González', 'Martín', 'OP-102', 3, TRUE), -- Pintura
(3, '40111222', 'López', 'Ramiro', 'OP-103', 4, TRUE), -- Herrería
(4, '36555444', 'Ramírez', 'Carlos', 'OP-104', 2, TRUE),  -- Albañilería
(5, '41222333', 'Benítez', 'Sergio', 'OP-105', 4, TRUE), -- Herrería
(6, '37444555', 'Acosta', 'Laura', 'OP-106', 3, TRUE), -- Pintura
(7, '39666777', 'Fernández', 'Diego', 'OP-107', 1, TRUE), -- Carpintería
(8, '34888999', 'Romero', 'Patricia', 'OP-108', 4, TRUE), -- Herrería
(9, '42111000', 'Silva', 'Gustavo', 'OP-109', 2, TRUE), -- Albañilería
(10, '33000111', 'Medina', 'Rubén', 'OP-110', 2, FALSE) -- Albañilería (dado de baja)
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
-- Las herramientas (15 y 36 a 41) nacen con 1 unidad en el pañol. Los materiales arrancan en 0:
-- su stock real lo calcula el bloque "6. RECÁLCULO DE STOCK" a partir de los movimientos.
INSERT INTO stock_actual (id_stock_actual, stock_actual, fecha) VALUES
(1, 0, CURRENT_DATE), (2, 0, CURRENT_DATE), (3, 0, CURRENT_DATE), (4, 0, CURRENT_DATE),
(5, 0, CURRENT_DATE), (6, 0, CURRENT_DATE), (7, 0, CURRENT_DATE), (8, 0, CURRENT_DATE),
(9, 0, CURRENT_DATE), (10, 0, CURRENT_DATE), (11, 0, CURRENT_DATE), (12, 0, CURRENT_DATE),
(13, 0, CURRENT_DATE), (14, 0, CURRENT_DATE),
(15, 1, CURRENT_DATE), -- Herramienta (Pistola para pintar)
(16, 0, CURRENT_DATE), (17, 0, CURRENT_DATE), (18, 0, CURRENT_DATE), (19, 0, CURRENT_DATE),
(20, 0, CURRENT_DATE), (21, 0, CURRENT_DATE), (22, 0, CURRENT_DATE), (23, 0, CURRENT_DATE),
(24, 0, CURRENT_DATE), (25, 0, CURRENT_DATE), (26, 0, CURRENT_DATE), (27, 0, CURRENT_DATE),
(28, 0, CURRENT_DATE), (29, 0, CURRENT_DATE), (30, 0, CURRENT_DATE), (31, 0, CURRENT_DATE),
(32, 0, CURRENT_DATE), (33, 0, CURRENT_DATE), (34, 0, CURRENT_DATE), (35, 0, CURRENT_DATE),
(36, 1, CURRENT_DATE), (37, 1, CURRENT_DATE), (38, 1, CURRENT_DATE), -- Herramientas
(39, 1, CURRENT_DATE), (40, 1, CURRENT_DATE), (41, 1, CURRENT_DATE);

-- 4. Insumos (Materiales consumibles y Herramientas)
-- Columnas: id, código, nombre, unidad, tipo (1 Material / 2 Herramienta), rubro, ubicación, stock, mínimo,
--           perecedero, vencimiento, serie/modelo, fecha de alta, activo
INSERT INTO insumos (id_insumo, codigo, nombre, id_unidad_medida, id_tipo, id_rubro, id_ubicacion, id_stock_actual, stock_minimo, es_perecedero, fecha_vencimiento, serie_modelo, fecha_registro, activo) VALUES
-- Pinturas y Esmaltes (Litros)
(1, '24027', 'Pintura látex acrílico antihongo negro', 3, 1, 3, 4, 1, 5.00, TRUE, CURRENT_DATE + 28, NULL, CURRENT_DATE - 240, TRUE),
(2, '24000', 'Pintura látex interior mate base blanca', 3, 1, 3, 4, 2, 10.00, TRUE, CURRENT_DATE + 12, NULL, CURRENT_DATE - 240, TRUE),
(3, '24001', 'Pintura látex antihongo interior-exterior mate base blanca', 3, 1, 3, 4, 3, 10.00, TRUE, CURRENT_DATE + 25, NULL, CURRENT_DATE - 240, TRUE),
(4, '24004', 'Esmalte satinado al aceite negro', 3, 1, 3, 4, 4, 4.00, TRUE, CURRENT_DATE - 60, NULL, CURRENT_DATE - 240, TRUE),
(5, '24006', 'Esmalte satinado al agua blanco', 3, 1, 3, 4, 5, 5.00, TRUE, CURRENT_DATE - 10, NULL, CURRENT_DATE - 240, TRUE),
(6, '24532', 'Pintura para piso', 3, 1, 3, 4, 6, 5.00, TRUE, CURRENT_DATE - 30, NULL, CURRENT_DATE - 240, TRUE),

-- Accesorios de Pintura (Unidades, Rollos, Cajas)
(7, '24018', 'Pincel Nº 30 cerda blanca', 1, 1, 3, 1, 7, 10.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(8, '24019', 'Pincel nº 25 cerda blanca', 1, 1, 3, 1, 8, 10.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(9, '24013', 'Cinta papel de pintor para enmascarar de 36mm x 50m', 8, 1, 3, 1, 9, 15.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(10, '24014', 'Cinta papel de pintor para enmascarar de 24mm x 50m', 8, 1, 3, 1, 10, 15.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(11, '24007', 'Viruta grosor mediano', 7, 1, 3, 4, 11, 20.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(12, '24008', 'Viruta grosor fino', 7, 1, 3, 4, 12, 20.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),

-- Lijas y herramienta de pintura
(13, '24024', 'Lija circular con velcro p/ máquina roto orbital (125 mm) Nº 80', 1, 1, 3, 3, 13, 5.00, FALSE, NULL, 'Bosch GEX 125', CURRENT_DATE - 240, TRUE),
(14, '24025', 'Lija circular con velcro p/ máquina roto orbital (125 mm) Nº 100', 1, 1, 3, 3, 14, 5.00, FALSE, NULL, 'Bosch GEX 125', CURRENT_DATE - 240, TRUE),
(15, '24751', 'Pistola para pintar', 1, 2, 3, 3, 15, 2.00, FALSE, NULL, 'Sata Jet 1000', CURRENT_DATE - 240, TRUE),
(16, '24503', 'Lija al agua pliego', 1, 1, 3, 1, 16, 20.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),

-- Insumos de Preparación (Litros y Bolsas)
(17, '24002', 'Diluyente aguarrás', 3, 1, 3, 4, 17, 20.00, TRUE, CURRENT_DATE + 20, NULL, CURRENT_DATE - 240, TRUE),
(18, '24003', 'Thinner Diluyente', 3, 1, 3, 4, 18, 15.00, TRUE, CURRENT_DATE + 3, NULL, CURRENT_DATE - 240, TRUE),
(19, '24010', 'Impermeabilizante acrílico elástico frentes/muros transparente', 3, 1, 3, 4, 19, 5.00, TRUE, CURRENT_DATE + 9, NULL, CURRENT_DATE - 240, TRUE),
(20, '24016', 'Mezcla adhesiva a base de yeso para interior', 6, 1, 2, 4, 20, 5.00, TRUE, CURRENT_DATE - 120, NULL, CURRENT_DATE - 240, TRUE),

-- Herrería, albañilería y carpintería
(21, '24030', 'Disco de corte para metal 115 mm', 1, 1, 4, 1, 21, 20.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(22, '24031', 'Electrodo 2,5 mm', 4, 1, 4, 1, 22, 10.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(23, '24032', 'Cemento portland x 50 kg', 6, 1, 2, 2, 23, 10.00, TRUE, CURRENT_DATE + 18, NULL, CURRENT_DATE - 240, TRUE),
(24, '24033', 'Cal hidratada x 25 kg', 6, 1, 2, 2, 24, 8.00, TRUE, CURRENT_DATE - 5, NULL, CURRENT_DATE - 240, TRUE),
(25, '24034', 'Ladrillo hueco 12x18x33', 1, 1, 2, 2, 25, 100.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(26, '24035', 'Tornillo autoperforante 8 x 1" (caja x 100)', 7, 1, 1, 1, 26, 5.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(27, '24036', 'Tablón de pino 1" x 6"', 2, 1, 1, 2, 27, 30.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(28, '24037', 'Placa terciada fenólica 18 mm', 5, 1, 1, 2, 28, 6.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(29, '24038', 'Bisagra de acero 3"', 1, 1, 1, 1, 29, 20.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(30, '24039', 'Cable unipolar 2,5 mm', 2, 1, 4, 1, 30, 100.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
(31, '24040', 'Cinta aisladora', 8, 1, 4, 1, 31, 10.00, FALSE, NULL, NULL, CURRENT_DATE - 240, TRUE),
-- Insumo dado de baja (aparece solo al incluir inactivos)
(32, '24041', 'Caño de PVC 110 mm', 2, 1, 2, 2, 32, 20.00, FALSE, NULL, NULL, CURRENT_DATE - 240, FALSE),
-- Con datos incompletos a propósito (para probar "Calidad de los datos" en Reportes)
(33, '24042', 'Pegamento de contacto', 3, 1, 1, 4, 33, 5.00, TRUE, NULL, NULL, CURRENT_DATE - 19, TRUE),   -- perecedero sin fecha de vencimiento
(34, '24043', 'Guantes de seguridad', 1, 1, 4, NULL, 34, 0.00, FALSE, NULL, NULL, CURRENT_DATE - 19, TRUE), -- sin ubicación y sin stock mínimo
(35, '24044', 'Precinto plástico', NULL, 1, 4, 1, 35, 0.00, FALSE, NULL, NULL, CURRENT_DATE - 19, TRUE),     -- sin unidad y sin stock mínimo

-- Herramientas (tipo 2): su estado y disponibilidad salen de los movimientos
(36, '24800', 'Taladro percutor 13 mm', 1, 2, 1, 3, 36, 1.00, FALSE, NULL, 'Bosch GSB 13 RE', CURRENT_DATE - 240, TRUE),
(37, '24801', 'Amoladora angular 4 1/2"', 1, 2, 4, 3, 37, 1.00, FALSE, NULL, 'DeWalt DWE402', CURRENT_DATE - 240, TRUE),
(38, '24802', 'Rodillo antigota 22 cm', 1, 2, 3, 3, 38, 1.00, FALSE, NULL, 'Tigre 22', CURRENT_DATE - 240, TRUE),
(39, '24803', 'Hormigonera eléctrica 130 L', 1, 2, 2, 3, 39, 1.00, FALSE, NULL, 'Lusqtoff LH-130', CURRENT_DATE - 240, TRUE),
(40, '24804', 'Soldadora inverter 200 A', 1, 2, 4, 3, 40, 1.00, FALSE, NULL, 'Lincoln Invertec 200', CURRENT_DATE - 240, TRUE),
(41, '24805', 'Sierra circular 7 1/4"', 1, 2, 1, 3, 41, 1.00, FALSE, NULL, 'Makita 5007', CURRENT_DATE - 240, TRUE)
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
-- Tipos: 1 = Entrega, 2 = Devolución, 3 = Ingreso, 4 = Ajuste de Inventario.
-- Usuarios que registran: 12345678 (admin) y 87654321 (pañolero).
INSERT INTO movimiento (id_movimiento, id_tipo_mov, id_operario, dni_usuario, fecha, hora, observaciones, activo) VALUES
-- Ingresos iniciales y reposiciones
(1,  3, 4, '12345678', CURRENT_DATE - 230, '09:00:00', 'Compra inicial: pinturas y solventes', TRUE),
(2,  3, 4, '87654321', CURRENT_DATE - 229, '09:30:00', 'Compra inicial: albañilería y carpintería', TRUE),
(3,  3, 5, '87654321', CURRENT_DATE - 228, '10:15:00', 'Compra inicial: herrería y electricidad', TRUE),
(4,  3, 6, '87654321', CURRENT_DATE - 226, '11:00:00', 'Compra inicial: pinceles, lijas y cintas', TRUE),
(5,  3, 4, '12345678', CURRENT_DATE - 150, '09:10:00', 'Reposición de pinturas', TRUE),
-- Entregas antiguas (más de 90 días): alimentan la evolución mensual y el "stock inmóvil"
(6,  1, 2, '87654321', CURRENT_DATE - 145, '10:20:00', 'Salida para pintura de despachos', TRUE),
(7,  1, 7, '87654321', CURRENT_DATE - 130, '08:45:00', 'Salida para carpintería de obra', TRUE),
(8,  3, 5, '87654321', CURRENT_DATE - 118, '09:40:00', 'Reposición de cemento y cal', TRUE),
(9,  1, 10, '12345678', CURRENT_DATE - 112, '14:10:00', 'Ladrillos para mampostería', TRUE),
(10, 1, 4, '87654321', CURRENT_DATE - 100, '13:30:00', 'Yeso y viruta para revoque', TRUE),
(11, 1, 3, '87654321', CURRENT_DATE - 95,  '15:00:00', 'Lijas y cintas para herrería', TRUE),
(12, 2, 2, '87654321', CURRENT_DATE - 93,  '09:05:00', 'Devolución de un pincel sin uso', TRUE),
(13, 3, 5, '12345678', CURRENT_DATE - 92,  '10:00:00', 'Reposición de cable', TRUE),
(14, 1, 5, '87654321', CURRENT_DATE - 80,  '11:25:00', 'Discos y electrodos para taller de herrería', TRUE),
(15, 1, 3, '87654321', CURRENT_DATE - 70,  '08:30:00', 'Entrega de soldadora para portones', TRUE),
(16, 2, 3, '87654321', CURRENT_DATE - 68,  '16:20:00', 'Soldadora devuelta: quemada, se da de baja', TRUE),
(17, 1, 7, '87654321', CURRENT_DATE - 62,  '09:15:00', 'Herrajes para puertas', TRUE),
(18, 3, 4, '12345678', CURRENT_DATE - 58,  '09:50:00', 'Reposición de bisagras', TRUE),
(19, 4, 4, '12345678', CURRENT_DATE - 52,  '16:00:00', 'Ajuste por recuento físico de lijas', TRUE),
(20, 1, 8, '87654321', CURRENT_DATE - 48,  '10:40:00', 'Cable y cinta para instalación eléctrica', TRUE),
(21, 1, 6, '87654321', CURRENT_DATE - 45,  '14:00:00', 'Pintura para oficinas', TRUE),
(22, 1, 4, '87654321', CURRENT_DATE - 40,  '08:20:00', 'Entrega de hormigonera para contrapiso', TRUE),
(23, 2, 4, '87654321', CURRENT_DATE - 38,  '17:00:00', 'Hormigonera devuelta con falla: va a reparación', TRUE),
(24, 3, 6, '87654321', CURRENT_DATE - 33,  '09:25:00', 'Reposición de pinceles y cintas', TRUE),
-- Últimos 30 días
(25, 1, 6, '87654321', CURRENT_DATE - 28,  '08:40:00', 'Salida para pintura del Juzgado Civil N°3', TRUE),
(26, 4, 4, '12345678', CURRENT_DATE - 26,  '16:10:00', 'Segundo ajuste de lijas: diferencia con el sistema', TRUE),
(27, 1, 2, '87654321', CURRENT_DATE - 25,  '09:00:00', 'Pistola para pintar rejas perimetrales', TRUE),
(28, 1, 7, '87654321', CURRENT_DATE - 24,  '10:30:00', 'Herrajes para muebles de guardado', TRUE),
(29, 1, 5, '87654321', CURRENT_DATE - 22,  '11:10:00', 'Discos y electrodos para rejas', TRUE),
(30, 4, 5, '12345678', CURRENT_DATE - 21,  '15:45:00', 'Ajuste de discos de corte por recuento físico', TRUE),
(31, 1, 2, '87654321', CURRENT_DATE - 20,  '08:15:00', 'Rodillo para pintura epoxi', TRUE),
(32, 3, 6, '87654321', CURRENT_DATE - 19,  '09:30:00', 'Alta de pegamento, guantes y precintos', TRUE),
(33, 2, 2, '87654321', CURRENT_DATE - 18,  '16:30:00', 'Rodillo devuelto en buen estado', TRUE),
(34, 1, 8, '87654321', CURRENT_DATE - 17,  '10:00:00', 'Cable y cinta aisladora para tableros', TRUE),
(35, 1, 6, '12345678', CURRENT_DATE - 16,  '13:20:00', 'Solventes para limpieza de herramientas', TRUE),
(36, 1, 9, '87654321', CURRENT_DATE - 14,  '09:45:00', 'Cemento para reparación sanitaria', TRUE),
(37, 1, 3, '87654321', CURRENT_DATE - 12,  '08:50:00', 'Amoladora para estructura metálica', TRUE),
(38, 1, 6, '87654321', CURRENT_DATE - 11,  '14:40:00', 'Pintura y cintas para refacción', TRUE),
(39, 1, 2, '87654321', CURRENT_DATE - 9,   '09:20:00', 'Sierra circular para puertas', TRUE),
(40, 4, 6, '12345678', CURRENT_DATE - 9,   '17:00:00', 'Ajuste de pinceles por recuento físico', TRUE),
(41, 1, 7, '87654321', CURRENT_DATE - 8,   '10:10:00', 'Herrajes y guantes para carpintería', TRUE),
(42, 1, 5, '87654321', CURRENT_DATE - 7,   '11:00:00', 'Electrodos y discos de corte', TRUE),
(43, 1, 1, '87654321', CURRENT_DATE - 6,   '08:30:00', 'Taladro para colocar herrajes', TRUE),
(44, 3, 6, '12345678', CURRENT_DATE - 6,   '15:00:00', 'Reposición de solventes', TRUE),
(45, 2, 6, '87654321', CURRENT_DATE - 5,   '10:05:00', 'Devolución de un litro de pintura sin usar', TRUE),
(46, 1, 4, '87654321', CURRENT_DATE - 4,   '09:35:00', 'Cal, cemento y discos para mampostería', TRUE),
(47, 1, 6, '87654321', CURRENT_DATE - 3,   '13:00:00', 'Pinceles, cintas y lijas al agua', TRUE),
(48, 1, 8, '12345678', CURRENT_DATE - 2,   '10:20:00', 'Cable y cinta aisladora', TRUE),
(49, 1, 1, '87654321', CURRENT_DATE - 1,   '12:15:00', 'Bisagras y lija para mantenimiento', TRUE),
(50, 1, 2, '87654321', CURRENT_DATE,       '08:05:00', 'Pintura y aguarrás', TRUE),
-- Movimientos ANULADOS (activo = FALSE): no afectan stock, estado ni reportes
(51, 1, 4, '87654321', CURRENT_DATE - 15,  '11:00:00', 'Entrega cargada por error (50 litros): anulada', FALSE),
(52, 1, 1, '87654321', CURRENT_DATE - 10,  '09:00:00', 'Taladro entregado por error: anulada', FALSE),
(53, 3, 6, '12345678', CURRENT_DATE - 30,  '10:30:00', 'Ingreso duplicado de pintura: anulado', FALSE),
-- Tercer ajuste sobre las lijas (material con diferencias recurrentes)
(54, 4, 4, '12345678', CURRENT_DATE - 10,  '16:30:00', 'Tercer ajuste de lijas: nueva diferencia con el sistema', TRUE);

-- 7. Detalles de Movimientos (Vincular id_insumo con id_movimiento)
-- En herramientas, id_estado_herramienta es el estado en que queda: 2 Prestada (entrega) o el de la devolución
-- (1 Disponible, 3 En Reparación, 4 Dada de baja). En materiales va NULL.
-- En un Ajuste de Inventario la cantidad es el stock REAL contado.
INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, id_estado_herramienta) VALUES
-- 1 a 5: ingresos
(1, 1, 18.00, NULL), (1, 2, 14.00, NULL), (1, 3, 20.00, NULL), (1, 4, 15.00, NULL), (1, 5, 18.00, NULL),
(1, 6, 12.00, NULL), (1, 17, 30.00, NULL), (1, 18, 18.00, NULL), (1, 19, 15.00, NULL),
(2, 11, 50.00, NULL), (2, 12, 45.00, NULL), (2, 20, 9.00, NULL), (2, 25, 360.00, NULL), (2, 26, 5.00, NULL),
(2, 27, 90.00, NULL), (2, 28, 12.00, NULL), (2, 29, 15.00, NULL),
(3, 21, 40.00, NULL), (3, 22, 12.00, NULL), (3, 30, 100.00, NULL), (3, 31, 20.00, NULL), (3, 32, 50.00, NULL),
(4, 7, 9.00, NULL), (4, 8, 20.00, NULL), (4, 9, 12.00, NULL), (4, 10, 18.00, NULL), (4, 13, 20.00, NULL),
(4, 14, 15.00, NULL), (4, 16, 25.00, NULL),
(5, 1, 5.00, NULL), (5, 2, 5.00, NULL), (5, 3, 8.00, NULL),
-- 6 a 24
(6, 4, 3.00, NULL), (6, 6, 2.00, NULL), (6, 8, 4.00, NULL),
(7, 27, 10.00, NULL), (7, 28, 2.00, NULL),
(8, 23, 5.00, NULL), (8, 24, 17.00, NULL),
(9, 25, 60.00, NULL),
(10, 20, 2.00, NULL), (10, 11, 5.00, NULL),
(11, 14, 3.00, NULL), (11, 10, 4.00, NULL),
(12, 8, 1.00, NULL),
(13, 30, 50.00, NULL),
(14, 21, 10.00, NULL), (14, 22, 4.00, NULL),
(15, 40, 1.00, 2),
(16, 40, 1.00, 4),
(17, 26, 2.00, NULL), (17, 29, 6.00, NULL),
(18, 29, 12.00, NULL),
(19, 13, 9.00, NULL),
(20, 30, 60.00, NULL), (20, 31, 5.00, NULL),
(21, 1, 4.00, NULL), (21, 2, 6.00, NULL),
(22, 39, 1.00, 2),
(23, 39, 1.00, 3),
(24, 8, 8.00, NULL), (24, 9, 10.00, NULL), (24, 10, 10.00, NULL),
-- 25 a 50: últimos 30 días
(25, 1, 3.00, NULL), (25, 2, 4.00, NULL), (25, 7, 2.00, NULL),
(26, 13, 6.00, NULL),
(27, 15, 1.00, 2),
(28, 26, 1.00, NULL), (28, 29, 4.00, NULL),
(29, 21, 6.00, NULL), (29, 22, 3.00, NULL),
(30, 21, 20.00, NULL),
(31, 38, 1.00, 2),
(32, 33, 8.00, NULL), (32, 34, 50.00, NULL), (32, 35, 200.00, NULL),
(33, 38, 1.00, 1),
(34, 30, 30.00, NULL), (34, 31, 3.00, NULL),
(35, 17, 5.00, NULL), (35, 18, 4.00, NULL),
(36, 23, 2.00, NULL),
(37, 37, 1.00, 2),
(38, 1, 2.00, NULL), (38, 3, 3.00, NULL), (38, 9, 4.00, NULL),
(39, 41, 1.00, 2),
(40, 7, 6.00, NULL),
(41, 29, 3.00, NULL), (41, 26, 1.00, NULL), (41, 34, 6.00, NULL),
(42, 22, 2.00, NULL), (42, 21, 4.00, NULL),
(43, 36, 1.00, 2),
(44, 17, 6.00, NULL), (44, 18, 6.00, NULL),
(45, 1, 1.00, NULL),
(46, 24, 2.00, NULL), (46, 23, 1.00, NULL), (46, 21, 10.00, NULL),
(47, 7, 2.00, NULL), (47, 9, 3.00, NULL), (47, 16, 25.00, NULL),
(48, 30, 20.00, NULL), (48, 31, 2.00, NULL),
(49, 29, 2.00, NULL), (49, 13, 2.00, NULL),
(50, 2, 1.00, NULL), (50, 17, 1.00, NULL),
-- 51 a 53: anulados
(51, 1, 50.00, NULL),
(52, 36, 1.00, 2),
(53, 2, 100.00, NULL),
-- 54: ajuste (stock contado = 7)
(54, 13, 7.00, NULL);

-- ---------------------------------------------------------
-- 4. ÓRDENES DE TRABAJO Y COMISIONES
-- ---------------------------------------------------------

-- 8. Órdenes de Trabajo (ODT)
-- Estados: Pendiente, En Progreso, Pausada, Finalizada, Anulada. Vencidas = fecha final pasada y sin cerrar (3, 4, 9 y 13).
-- La 2 (Finalizada) y la 5 (Anulada) dejaron herramientas sin devolver. La 15 está deshabilitada (activo = FALSE).
INSERT INTO orden_de_trabajo (id_odt, numero_ot, id_mov_egreso, id_mov_devolucion, id_localidad, id_jurisdiccion, pa, trabajo_a_realizar, fecha_inicio, fecha_final, hora_inicio, hora_final, estado, observaciones, activo) VALUES
(1,  'OT-2026-001', 25, NULL, 1, 1, 'PA-2026-001', 'Pintura de oficinas y pasillos del Juzgado Civil N°3', CURRENT_DATE - 6, CURRENT_DATE + 5, '08:00:00', '17:00:00', 'En Progreso', 'Se utiliza pintura antihongo negra', TRUE),
(2,  'OT-2026-002', 27, NULL, 2, 3, 'PA-2026-002', 'Pintura de rejas perimetrales con pistola', CURRENT_DATE - 26, CURRENT_DATE - 20, '09:00:00', '16:00:00', 'Finalizada', 'Trabajo terminado; la pistola para pintar no fue devuelta', TRUE),
(3,  'OT-2026-003', NULL, NULL, 1, 2, 'PA-2026-003', 'Reparación de paredes en Depósito Central', CURRENT_DATE - 10, CURRENT_DATE - 8, '10:00:00', '15:00:00', 'Pendiente', 'Falta material de mezcla', TRUE),
(4,  'OT-2026-004', 37, NULL, 1, 2, 'PA-2026-004', 'Amolado y reparación de estructura metálica', CURRENT_DATE - 12, CURRENT_DATE - 3, '08:00:00', '16:00:00', 'Pausada', 'Pausada por falta de discos de corte', TRUE),
(5,  'OT-2026-005', 39, NULL, 3, 4, 'PA-2026-005', 'Carpintería de puertas y marcos', CURRENT_DATE - 12, CURRENT_DATE - 5, '08:30:00', '15:30:00', 'Anulada', 'Anulada por cambio de prioridades; la sierra sigue prestada', TRUE),
(6,  'OT-2026-006', 31, 33, 1, 1, 'PA-2026-006', 'Pintura epoxi en sala de máquinas', CURRENT_DATE - 21, CURRENT_DATE - 17, '08:00:00', '17:00:00', 'Finalizada', 'Herramienta devuelta en buen estado', TRUE),
(7,  'OT-2026-007', 22, 23, 4, 5, 'PA-2026-007', 'Hormigonado de contrapiso en patio', CURRENT_DATE - 41, CURRENT_DATE - 37, '07:30:00', '15:00:00', 'Finalizada', 'La hormigonera volvió con falla', TRUE),
(8,  'OT-2026-008', 15, 16, 5, 6, 'PA-2026-008', 'Soldadura de portones de acceso', CURRENT_DATE - 71, CURRENT_DATE - 67, '08:00:00', '16:00:00', 'Finalizada', 'La soldadora quedó fuera de servicio', TRUE),
(9,  'OT-2026-009', 29, NULL, 3, 4, 'PA-2026-009', 'Reparación de rejas y cerramientos', CURRENT_DATE - 23, CURRENT_DATE - 2, '08:00:00', '17:00:00', 'En Progreso', 'En curso con la fecha final vencida', TRUE),
(10, 'OT-2026-010', NULL, NULL, 4, 5, 'PA-2026-010', 'Instalación eléctrica de oficinas', CURRENT_DATE + 3, CURRENT_DATE + 12, '09:00:00', '17:00:00', 'Pendiente', 'A iniciar la semana próxima', TRUE),
(11, 'OT-2026-011', 38, NULL, 5, 6, 'PA-2026-011', 'Refacción de mampostería', CURRENT_DATE - 12, CURRENT_DATE + 20, '08:00:00', '16:00:00', 'En Progreso', NULL, TRUE),
(12, 'OT-2026-012', 6, NULL, 1, 1, 'PA-2026-012', 'Pintura de despachos', CURRENT_DATE - 148, CURRENT_DATE - 140, '08:00:00', '17:00:00', 'Finalizada', NULL, TRUE),
(13, 'OT-2026-013', NULL, NULL, 2, 3, 'PA-2026-013', 'Revoque y pintura de fachada', CURRENT_DATE - 5, CURRENT_DATE - 1, '08:00:00', '17:00:00', 'Pendiente', 'Sin iniciar y con la fecha final vencida', TRUE),
(14, 'OT-2026-014', 28, NULL, 1, 1, 'PA-2026-014', 'Fabricación de muebles de guardado', CURRENT_DATE - 3, CURRENT_DATE + 30, '08:00:00', '16:00:00', 'Pausada', 'A la espera de placas', TRUE),
(15, 'OT-2026-015', NULL, NULL, 1, 2, 'PA-2026-015', 'Orden cargada por error (deshabilitada)', CURRENT_DATE - 2, CURRENT_DATE + 2, '08:00:00', '12:00:00', 'Pendiente', 'Deshabilitada', FALSE);

-- 9. Comisiones (operarios asignados a cada ODT, por id_operario)
INSERT INTO comision (id_odt, id_operario) VALUES
(1, 6), (1, 2), (1, 1),
(2, 2), (2, 3),
(3, 4), (3, 10),
(4, 3), (4, 5),
(5, 7), (5, 2),
(6, 2),
(7, 4), (7, 10),
(8, 3), (8, 5),
(9, 5), (9, 3),
(10, 8),
(11, 4), (11, 6),
(12, 2), (12, 6),
(13, 4),
(14, 7), (14, 1),
(15, 1);

-- Vincula los movimientos con la orden de trabajo que los usa (igual que hace el sistema al vincular)
UPDATE movimiento SET id_odt = 1  WHERE id_movimiento IN (25, 43);
UPDATE movimiento SET id_odt = 2  WHERE id_movimiento = 27;
UPDATE movimiento SET id_odt = 4  WHERE id_movimiento = 37;
UPDATE movimiento SET id_odt = 5  WHERE id_movimiento = 39;
UPDATE movimiento SET id_odt = 6  WHERE id_movimiento IN (31, 33);
UPDATE movimiento SET id_odt = 7  WHERE id_movimiento IN (22, 23);
UPDATE movimiento SET id_odt = 8  WHERE id_movimiento IN (15, 16);
UPDATE movimiento SET id_odt = 9  WHERE id_movimiento = 29;
UPDATE movimiento SET id_odt = 11 WHERE id_movimiento IN (38, 46);
UPDATE movimiento SET id_odt = 12 WHERE id_movimiento = 6;
UPDATE movimiento SET id_odt = 14 WHERE id_movimiento = 28;

-- ---------------------------------------------------------
-- 5. REAJUSTE DE SECUENCIAS
-- ---------------------------------------------------------

SELECT setval('rubro_id_rubro_seq', (SELECT COALESCE(MAX(id_rubro), 1) FROM rubro));
SELECT setval('operario_id_operario_seq', (SELECT COALESCE(MAX(id_operario), 1) FROM operario));
SELECT setval('stock_actual_id_stock_actual_seq', (SELECT COALESCE(MAX(id_stock_actual), 1) FROM stock_actual));
SELECT setval('insumos_id_insumo_seq', (SELECT COALESCE(MAX(id_insumo), 1) FROM insumos));
SELECT setval('herramienta_id_herramienta_seq', (SELECT COALESCE(MAX(id_herramienta), 1) FROM herramienta));
SELECT setval('movimiento_id_movimiento_seq', (SELECT COALESCE(MAX(id_movimiento), 1) FROM movimiento));
SELECT setval('movimiento_detalle_id_detalle_seq', (SELECT COALESCE(MAX(id_detalle), 1) FROM movimiento_detalle));
SELECT setval('orden_de_trabajo_id_odt_seq', (SELECT COALESCE(MAX(id_odt), 1) FROM orden_de_trabajo));
SELECT setval('comision_id_comision_seq', (SELECT COALESCE(MAX(id_comision), 1) FROM comision));

-- ---------------------------------------------------------
-- 6. RECÁLCULO DE STOCK Y ESTADO DE HERRAMIENTAS
-- ---------------------------------------------------------
-- Reproduce, en orden cronológico, los movimientos ACTIVOS con las mismas reglas que
-- clases/movimiento.php (Movimiento::aplicarEfecto):
--   Materiales:    Ingreso y Devolución suman, Entrega resta, Ajuste fija el stock contado.
--   Herramientas:  Entrega -> Prestada (stock 0); Devolución -> el estado indicado en el detalle
--                  (Disponible = stock 1; En Reparación o Dada de baja = stock 0).
-- Si algún movimiento dejara un material con stock negativo, la carga se interrumpe con un error.
DO $$
DECLARE
    r RECORD;
    v_stock NUMERIC;
    v_estado INT;
BEGIN
    FOR r IN
        SELECT md.id_insumo, md.cantidad, md.id_estado_herramienta AS estado_detalle,
               tm.tipo AS tipo_mov, t.tipo AS tipo_insumo, i.id_stock_actual, i.nombre,
               m.id_movimiento
        FROM movimiento_detalle md
        INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento AND m.activo = TRUE
        INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
        INNER JOIN insumos i ON md.id_insumo = i.id_insumo
        INNER JOIN tipo t ON i.id_tipo = t.id_tipo
        ORDER BY m.fecha, m.hora, m.id_movimiento, md.id_detalle
    LOOP
        IF r.tipo_insumo = 'Herramienta' THEN
            IF r.tipo_mov = 'Entrega' THEN
                v_estado := 2;
            ELSIF r.tipo_mov = 'Devolución' THEN
                v_estado := COALESCE(r.estado_detalle, 1);
            ELSE
                RAISE EXCEPTION 'Movimiento % : una herramienta (%) solo admite Entrega o Devolución', r.id_movimiento, r.nombre;
            END IF;
            UPDATE insumos SET id_estado_herramienta = v_estado WHERE id_insumo = r.id_insumo;
            UPDATE stock_actual
               SET stock_actual = CASE WHEN v_estado = 1 THEN 1 ELSE 0 END
             WHERE id_stock_actual = r.id_stock_actual;
        ELSE
            SELECT stock_actual INTO v_stock FROM stock_actual WHERE id_stock_actual = r.id_stock_actual;
            IF r.tipo_mov = 'Ajuste de Inventario' THEN
                v_stock := r.cantidad;
            ELSIF r.tipo_mov = 'Entrega' THEN
                v_stock := v_stock - r.cantidad;
            ELSE
                v_stock := v_stock + r.cantidad;
            END IF;
            IF v_stock < 0 THEN
                RAISE EXCEPTION 'Movimiento % : stock negativo para % (%)', r.id_movimiento, r.nombre, v_stock;
            END IF;
            UPDATE stock_actual SET stock_actual = v_stock WHERE id_stock_actual = r.id_stock_actual;
        END IF;
    END LOOP;

    -- La fecha del registro de stock es la del último movimiento que lo tocó (o el alta del insumo)
    UPDATE stock_actual sa
       SET fecha = COALESCE(
               (SELECT MAX(m.fecha)
                  FROM movimiento_detalle md
                  INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento AND m.activo = TRUE
                  INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                 WHERE i.id_stock_actual = sa.id_stock_actual),
               (SELECT i.fecha_registro FROM insumos i WHERE i.id_stock_actual = sa.id_stock_actual LIMIT 1),
               CURRENT_DATE);
END $$;

-- 7. Motivo de las devoluciones de prueba (RF-34): todas se cargan como "Fin de tarea" (id 2)
UPDATE movimiento SET id_motivo = 2 WHERE id_tipo_mov = 2;
