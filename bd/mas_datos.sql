-- =========================================================
-- DATOS FICTICIOS ADICIONALES (mas_datos.sql)
-- Para probar la interfaz con gran volumen de registros
-- =========================================================

-- ---------------------------------------------------------
-- 1. OPERARIOS (100)
-- ---------------------------------------------------------
DO $$
DECLARE i int;
BEGIN
  FOR i IN 1..100 LOOP
    INSERT INTO operario (dni, apellido, nombre, legajo, id_rubro, activo)
    VALUES ('5' || lpad(i::text, 7, '0'),
            'Apellido' || i,
            'Nombre' || i,
            'LEG-' || i,
            (1 + floor(random() * 4))::int,
            TRUE)
    ON CONFLICT (dni) DO NOTHING;
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 2. USUARIOS (50, rol vista)
-- ---------------------------------------------------------
DO $$
DECLARE i int;
DECLARE v_id_op int;
BEGIN
  FOR i IN 1..50 LOOP
    SELECT id_operario INTO v_id_op FROM operario WHERE dni = '5' || lpad(i::text, 7, '0');
    INSERT INTO usuario (dni, contrasena, nombre, apellido, id_rol, id_operario, activo)
    VALUES ('5' || lpad(i::text, 7, '0'),
            '$2y$10$ffZTGT0/fLMszdyuZPErXu3e7Oi1rvi4qazCtkqxYLYNmTD3wM79a',
            'Nombre' || i,
            'Apellido' || i,
            3,
            v_id_op,
            TRUE)
    ON CONFLICT (dni) DO NOTHING;
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 3. INSUMOS (300) + STOCK
-- ---------------------------------------------------------
DO $$
DECLARE i int;
DECLARE v_stock_id int;
BEGIN
  FOR i IN 1..300 LOOP
    INSERT INTO stock_actual (stock_actual, fecha)
    VALUES ((random() * 100)::numeric(10,2), CURRENT_DATE)
    RETURNING id_stock_actual INTO v_stock_id;

    INSERT INTO insumos (codigo, nombre, id_tipo, id_rubro, id_ubicacion, id_unidad_medida,
                         id_stock_actual, stock_minimo, es_perecedero, fecha_vencimiento,
                         serie_modelo, fecha_registro, activo)
    VALUES ('INS-' || lpad(i::text, 4, '0'),
            'Insumo ficticio ' || i,
            (1 + floor(random() * 2))::int,
            (1 + floor(random() * 4))::int,
            (1 + floor(random() * 4))::int,
            (1 + floor(random() * 8))::int,
            v_stock_id,
            (random() * 20)::numeric(10,2),
            (random() < 0.3),
            CASE WHEN random() < 0.3 THEN CURRENT_DATE + (floor(random() * 60))::int ELSE NULL END,
            CASE WHEN random() < 0.1 THEN 'SERIE-' || i ELSE NULL END,
            CURRENT_DATE,
            TRUE);
  END LOOP;
END $$;

-- Herramientas (tipo 2) nacen "Disponible"
UPDATE insumos SET id_estado_herramienta = 1 WHERE id_tipo = 2 AND id_estado_herramienta IS NULL;

-- ---------------------------------------------------------
-- 4. ORDENES DE TRABAJO (60)
-- ---------------------------------------------------------
DO $$
DECLARE i int;
BEGIN
  FOR i IN 1..60 LOOP
    INSERT INTO orden_de_trabajo (numero_ot, pa, trabajo_a_realizar, id_localidad, id_jurisdiccion,
                                  fecha_inicio, fecha_final, hora_inicio, hora_final, estado,
                                  observaciones, activo)
    VALUES ('OT-2026-' || lpad(i::text, 3, '0'),
            'PA-2026-' || lpad(i::text, 3, '0'),
            'Trabajo ficticio número ' || i,
            (1 + floor(random() * 4))::int,
            (1 + floor(random() * 4))::int,
            CURRENT_DATE - (floor(random() * 30))::int,
            CURRENT_DATE + (floor(random() * 30))::int,
            '08:00',
            '17:00',
            (ARRAY['Pendiente','En Proceso','Pausada','Finalizada','Anulada'])[1 + floor(random()*5)],
            'Observación ' || i,
            TRUE);
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 5. MOVIMIENTOS (150) + DETALLES
-- ---------------------------------------------------------
DO $$
DECLARE i int;
DECLARE v_id_mov int;
DECLARE v_id_insumo int;
DECLARE v_cant numeric(10,2);
DECLARE j int;
BEGIN
  FOR i IN 1..150 LOOP
    INSERT INTO movimiento (id_tipo_mov, id_operario, dni_usuario, fecha, hora, observaciones, activo)
    VALUES ((1 + floor(random() * 4))::int,
            (1 + floor(random() * 100))::int,
            '12345678',
            CURRENT_DATE - (floor(random() * 60))::int,
            '09:00',
            'Movimiento ficticio ' || i,
            TRUE)
    RETURNING id_movimiento INTO v_id_mov;

    -- 1 a 4 líneas de detalle por movimiento
    FOR j IN 1..(1 + floor(random() * 4))::int LOOP
      SELECT id_insumo INTO v_id_insumo FROM insumos ORDER BY random() LIMIT 1;
      v_cant := (1 + floor(random() * 5))::numeric;
      INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, id_estado_herramienta)
      VALUES (v_id_mov, v_id_insumo, v_cant, NULL);
    END LOOP;
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 6. COMISIONES (asignar operarios a OTs)
-- ---------------------------------------------------------
DO $$
DECLARE i int;
DECLARE v_id_odt int;
DECLARE v_id_op int;
BEGIN
  FOR i IN 1..200 LOOP
    SELECT id_odt INTO v_id_odt FROM orden_de_trabajo ORDER BY random() LIMIT 1;
    SELECT id_operario INTO v_id_op FROM operario ORDER BY random() LIMIT 1;
    INSERT INTO comision (id_odt, id_operario, activo)
    VALUES (v_id_odt, v_id_op, TRUE)
    ON CONFLICT DO NOTHING;
  END LOOP;
END $$;

-- Actualizar secuencias
SELECT setval('operario_id_operario_seq', (SELECT MAX(id_operario) FROM operario));
SELECT setval('insumos_id_insumo_seq', (SELECT MAX(id_insumo) FROM insumos));
SELECT setval('stock_actual_id_stock_actual_seq', (SELECT MAX(id_stock_actual) FROM stock_actual));
SELECT setval('orden_de_trabajo_id_odt_seq', (SELECT MAX(id_odt) FROM orden_de_trabajo));
SELECT setval('movimiento_id_movimiento_seq', (SELECT MAX(id_movimiento) FROM movimiento));