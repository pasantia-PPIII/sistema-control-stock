-- =========================================================
-- DATOS FICTICIOS ADICIONALES (mas_datos.sql)
-- Base de datos: db_control_stock
-- Para probar la interfaz con gran volumen de registros (paginación, autocompletado, reportes).
--
-- USO: cargar DESPUÉS de test_data.sql, sobre una base recién creada:
--      bd/db_control_stock.sql -> bd/seed_data.sql -> bd/test_data.sql -> bd/mas_datos.sql
--      Se ejecuta una sola vez: si ya fue cargado, se interrumpe sin tocar nada.
--
-- Qué agrega (todo con identificadores propios para no chocar con test_data.sql):
--   100 operarios   DNI 50000001 a 50000100, legajo DEMO-001 a DEMO-100
--    50 usuarios    rol "vista" (consulta), DNI 50000001 a 50000050, contraseña: admin123
--   300 insumos     códigos DEMO-0001 a DEMO-0300 (materiales y herramientas)
--    60 órdenes     OT-2026-101 a OT-2026-160, en todos los estados
--   150 movimientos entregas, devoluciones e ingresos, en orden cronológico (últimos 60 días)
--   ~200 comisiones operarios asignados a las órdenes nuevas
--
-- Los movimientos respetan las mismas reglas que clases/movimiento.php: nunca se entrega más de lo que
-- hay en stock, las herramientas solo se entregan si están Disponibles, las devoluciones llevan motivo y
-- las entregas no se vinculan a órdenes Finalizadas o Anuladas. El stock y el estado de cada herramienta
-- se van actualizando al mismo tiempo que se crean los movimientos, así que quedan coherentes entre sí.
--
-- Todo va en una transacción: si algo falla, no queda nada cargado a medias.
-- Las fechas son relativas a CURRENT_DATE.
-- =========================================================

BEGIN;

-- Evita cargar el script dos veces (los códigos y DNI serían los mismos)
DO $$
BEGIN
  IF EXISTS (SELECT 1 FROM insumos WHERE codigo = 'DEMO-0001') THEN
    RAISE EXCEPTION 'mas_datos.sql ya fue cargado en esta base: no se vuelve a ejecutar.';
  END IF;
END $$;

-- ---------------------------------------------------------
-- 1. OPERARIOS (100)
-- ---------------------------------------------------------
DO $$
DECLARE
  i int;
  apellidos text[] := ARRAY['Acuña','Aguirre','Benítez','Cabrera','Duarte','Escobar','Fernández','Giménez',
                            'Insaurralde','Maidana','Núñez','Ojeda','Peralta','Quiroz','Ríos','Sosa','Torres','Vera'];
  nombres   text[] := ARRAY['Alberto','Bruno','Carlos','Daniel','Esteban','Facundo','Gabriel','Hugo','Ignacio',
                            'Julián','Lucas','Marcos','Nicolás','Omar','Pablo','Ramón','Sebastián','Tomás'];
BEGIN
  FOR i IN 1..100 LOOP
    INSERT INTO operario (dni, apellido, nombre, legajo, id_rubro, activo)
    VALUES ('5' || lpad(i::text, 7, '0'),
            apellidos[1 + floor(random() * array_length(apellidos, 1))::int],
            nombres[1 + floor(random() * array_length(nombres, 1))::int],
            'DEMO-' || lpad(i::text, 3, '0'),
            1 + floor(random() * 4)::int,
            TRUE);
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 2. USUARIOS (50, rol vista), cada uno vinculado a su operario
-- ---------------------------------------------------------
INSERT INTO usuario (dni, contrasena, nombre, apellido, id_rol, id_operario, activo)
SELECT o.dni,
       '$2y$10$ffZTGT0/fLMszdyuZPErXu3e7Oi1rvi4qazCtkqxYLYNmTD3wM79a',
       o.nombre, o.apellido, 3, o.id_operario, TRUE
  FROM operario o
 WHERE o.legajo LIKE 'DEMO-%'
   AND o.dni BETWEEN '50000001' AND '50000050';

-- ---------------------------------------------------------
-- 3. INSUMOS (300) + STOCK
-- ---------------------------------------------------------
-- Cerca del 15 % son herramientas: nacen Disponibles, con 1 unidad en el pañol.
-- Los materiales nacen con un stock holgado (para que haya entregas posibles), un mínimo propio y,
-- si son perecederos, un vencimiento que puede estar ya vencido o por vencer.
DO $$
DECLARE
  i int;
  v_stock_id int;
  v_es_herr boolean;
  v_perec boolean;
BEGIN
  FOR i IN 1..300 LOOP
    v_es_herr := (random() < 0.15);
    v_perec := (NOT v_es_herr) AND (random() < 0.3);

    INSERT INTO stock_actual (stock_actual, fecha)
    VALUES (CASE WHEN v_es_herr THEN 1 ELSE (20 + floor(random() * 180))::numeric(10,2) END,
            CURRENT_DATE - 90)
    RETURNING id_stock_actual INTO v_stock_id;

    INSERT INTO insumos (codigo, nombre, id_tipo, id_rubro, id_ubicacion, id_unidad_medida,
                         id_stock_actual, stock_minimo, es_perecedero, fecha_vencimiento,
                         serie_modelo, id_estado_herramienta, fecha_registro, activo)
    VALUES ('DEMO-' || lpad(i::text, 4, '0'),
            CASE WHEN v_es_herr THEN 'Herramienta ficticia ' ELSE 'Material ficticio ' END || i,
            CASE WHEN v_es_herr THEN 2 ELSE 1 END,
            1 + floor(random() * 4)::int,
            CASE WHEN v_es_herr THEN 3 ELSE 1 + floor(random() * 4)::int END,
            CASE WHEN v_es_herr THEN 1 ELSE 1 + floor(random() * 8)::int END,
            v_stock_id,
            CASE WHEN v_es_herr THEN 0 ELSE (floor(random() * 30))::numeric(10,2) END,
            v_perec,
            CASE WHEN v_perec THEN CURRENT_DATE + (floor(random() * 150) - 30)::int ELSE NULL END,
            CASE WHEN v_es_herr THEN 'MOD-' || i ELSE NULL END,
            CASE WHEN v_es_herr THEN 1 ELSE NULL END,
            CURRENT_DATE - 90,
            TRUE);
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 4. ÓRDENES DE TRABAJO (60)
-- ---------------------------------------------------------
-- Estados: Pendiente, En Progreso, Pausada, Finalizada, Anulada. La fecha final nunca es anterior a la de inicio.
DO $$
DECLARE
  i int;
  v_inicio date;
  v_id_odt int;
  v_rubro int;
BEGIN
  FOR i IN 1..60 LOOP
    v_inicio := CURRENT_DATE - (floor(random() * 50) - 10)::int;
    -- Tipo de trabajo, dependencia y fecha de solicitud (anterior o igual al inicio) son obligatorios en el sistema
    INSERT INTO orden_de_trabajo (numero_ot, pa, trabajo_a_realizar, id_localidad, id_jurisdiccion,
                                  fecha_inicio, fecha_final, hora_inicio, hora_final, estado,
                                  observaciones, activo,
                                  tipo_trabajo, dependencia_solicitante, fecha_solicitud)
    VALUES ('OT-2026-' || lpad((100 + i)::text, 3, '0'),
            'PA-2026-' || lpad((100 + i)::text, 3, '0'),
            'Trabajo ficticio número ' || i,
            1 + floor(random() * 5)::int,
            1 + floor(random() * 6)::int,
            v_inicio,
            v_inicio + (1 + floor(random() * 30))::int,
            '08:00',
            '17:00',
            (ARRAY['Pendiente','En Progreso','Pausada','Finalizada','Anulada'])[1 + floor(random() * 5)::int],
            'Observación ' || i,
            TRUE,
            (ARRAY['Reparación','Construcción','Mantenimiento edilicio','Otro'])[1 + floor(random() * 4)::int],
            (ARRAY['Juzgado Civil N°3','Cámara Penal','Registro Civil','Fiscalía de Distrito','Mesa de Entradas',
                   'Dirección de Infraestructura','Archivo General'])[1 + floor(random() * 7)::int],
            v_inicio - (1 + floor(random() * 10))::int)
    RETURNING id_odt INTO v_id_odt;

    -- Cada orden abarca uno o dos rubros (el segundo puede coincidir con el primero: no se repite)
    v_rubro := 1 + floor(random() * 4)::int;
    INSERT INTO orden_rubro (id_odt, id_rubro) VALUES (v_id_odt, v_rubro);
    INSERT INTO orden_rubro (id_odt, id_rubro) VALUES (v_id_odt, 1 + floor(random() * 4)::int)
    ON CONFLICT (id_odt, id_rubro) DO NOTHING;
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 5. MOVIMIENTOS (150) + DETALLES
-- ---------------------------------------------------------
-- Se crean en orden cronológico (del más antiguo al más nuevo) y cada uno actualiza el stock o el estado
-- de la herramienta en el momento, igual que lo hace el sistema. Si en algún paso no hay con qué armar el
-- tipo de movimiento elegido (por ejemplo, ninguna herramienta prestada para devolver), se hace un ingreso.
DO $$
DECLARE
  i int;
  j int;
  v_tipo int;          -- 1 Entrega, 2 Devolución, 3 Ingreso
  v_es_herr boolean;
  v_id_mov int;
  v_id_op int;
  v_id_odt int;
  v_id_motivo int;
  v_dni text;
  v_obs text;
  v_ins record;
  v_cant numeric(10,2);
  v_estado_final int;
  v_usados int[];
  v_lineas int;
  v_r double precision;
BEGIN
  FOR i IN 1..150 LOOP
    v_r := random();
    IF    v_r < 0.40 THEN v_tipo := 1; v_es_herr := FALSE;   -- entrega de materiales
    ELSIF v_r < 0.50 THEN v_tipo := 2; v_es_herr := FALSE;   -- devolución de materiales
    ELSIF v_r < 0.70 THEN v_tipo := 3; v_es_herr := FALSE;   -- ingreso de materiales
    ELSIF v_r < 0.85 THEN v_tipo := 1; v_es_herr := TRUE;    -- entrega de herramienta
    ELSE                  v_tipo := 2; v_es_herr := TRUE;    -- devolución de herramienta
    END IF;

    -- Sin herramientas para el paso elegido: se cae a un ingreso de materiales
    IF v_es_herr AND v_tipo = 1 AND NOT EXISTS (
         SELECT 1 FROM insumos WHERE codigo LIKE 'DEMO-%' AND id_tipo = 2 AND id_estado_herramienta = 1) THEN
      v_tipo := 3; v_es_herr := FALSE;
    ELSIF v_es_herr AND v_tipo = 2 AND NOT EXISTS (
         SELECT 1 FROM insumos WHERE codigo LIKE 'DEMO-%' AND id_tipo = 2 AND id_estado_herramienta = 2) THEN
      v_tipo := 3; v_es_herr := FALSE;
    END IF;

    SELECT id_operario INTO v_id_op FROM operario WHERE legajo LIKE 'DEMO-%' ORDER BY random() LIMIT 1;
    v_dni := (ARRAY['12345678','87654321'])[1 + floor(random() * 2)::int];
    v_obs := 'Movimiento ficticio ' || i;

    -- Orden vinculada: solo entregas y devoluciones, en la mitad de los casos. A las entregas solo se les
    -- permiten órdenes habilitadas que no estén Finalizadas ni Anuladas.
    v_id_odt := NULL;
    IF v_tipo IN (1, 2) AND random() < 0.5 THEN
      SELECT id_odt INTO v_id_odt FROM orden_de_trabajo
       WHERE numero_ot LIKE 'OT-2026-1%' AND activo = TRUE
         AND (v_tipo = 2 OR estado NOT IN ('Finalizada', 'Anulada'))
       ORDER BY random() LIMIT 1;
    END IF;

    -- Motivo: obligatorio en devoluciones (4 = "Otro" exige detallarlo en las observaciones)
    v_id_motivo := NULL;
    IF v_tipo = 2 THEN
      v_id_motivo := 1 + floor(random() * 4)::int;
      IF v_id_motivo = 4 THEN
        v_obs := 'Devolución por otro motivo (ficticio ' || i || ')';
      END IF;
    END IF;

    INSERT INTO movimiento (id_tipo_mov, id_operario, dni_usuario, id_odt, id_motivo, fecha, hora, observaciones, activo)
    VALUES (v_tipo, v_id_op, v_dni, v_id_odt, v_id_motivo,
            CURRENT_DATE - (((150 - i) * 60) / 150),
            make_time(8 + floor(random() * 9)::int, floor(random() * 60)::int, 0),
            v_obs, TRUE)
    RETURNING id_movimiento INTO v_id_mov;

    IF v_es_herr THEN
      -- Una herramienta por movimiento
      IF v_tipo = 1 THEN
        SELECT i2.id_insumo, i2.id_stock_actual INTO v_ins FROM insumos i2
         WHERE i2.codigo LIKE 'DEMO-%' AND i2.id_tipo = 2 AND i2.id_estado_herramienta = 1
         ORDER BY random() LIMIT 1;
        v_estado_final := 2;                              -- Prestada
      ELSE
        SELECT i2.id_insumo, i2.id_stock_actual INTO v_ins FROM insumos i2
         WHERE i2.codigo LIKE 'DEMO-%' AND i2.id_tipo = 2 AND i2.id_estado_herramienta = 2
         ORDER BY random() LIMIT 1;
        v_r := random();                                  -- estado en que vuelve
        v_estado_final := CASE WHEN v_r < 0.85 THEN 1 WHEN v_r < 0.95 THEN 3 ELSE 4 END;
      END IF;

      INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, id_estado_herramienta)
      VALUES (v_id_mov, v_ins.id_insumo, 1, v_estado_final);
      UPDATE insumos SET id_estado_herramienta = v_estado_final WHERE id_insumo = v_ins.id_insumo;
      UPDATE stock_actual
         SET stock_actual = CASE WHEN v_estado_final = 1 THEN 1 ELSE 0 END,
             fecha = CURRENT_DATE - (((150 - i) * 60) / 150)
       WHERE id_stock_actual = v_ins.id_stock_actual;
    ELSE
      -- De 1 a 4 materiales distintos por movimiento
      v_usados := ARRAY[]::int[];
      v_lineas := 1 + floor(random() * 4)::int;
      FOR j IN 1..v_lineas LOOP
        SELECT i2.id_insumo, i2.id_stock_actual, sa.stock_actual AS stock INTO v_ins
          FROM insumos i2
          INNER JOIN stock_actual sa ON sa.id_stock_actual = i2.id_stock_actual
         WHERE i2.codigo LIKE 'DEMO-%' AND i2.id_tipo = 1
           AND NOT (i2.id_insumo = ANY (v_usados))
           AND (v_tipo <> 1 OR sa.stock_actual >= 1)     -- no se entrega lo que no hay
         ORDER BY random() LIMIT 1;
        EXIT WHEN v_ins.id_insumo IS NULL;
        v_usados := v_usados || v_ins.id_insumo;

        v_cant := 1 + floor(random() * 5);
        IF v_tipo = 1 THEN
          v_cant := LEAST(v_cant, floor(v_ins.stock));   -- nunca más que el stock disponible
        ELSIF v_tipo = 3 THEN
          v_cant := v_cant * 10;
        END IF;

        INSERT INTO movimiento_detalle (id_movimiento, id_insumo, cantidad, id_estado_herramienta)
        VALUES (v_id_mov, v_ins.id_insumo, v_cant, NULL);

        UPDATE stock_actual
           SET stock_actual = stock_actual + CASE WHEN v_tipo = 1 THEN -v_cant ELSE v_cant END,
               fecha = CURRENT_DATE - (((150 - i) * 60) / 150)
         WHERE id_stock_actual = v_ins.id_stock_actual;
      END LOOP;

      -- Si no se pudo armar ninguna línea (no quedaba stock para entregar), no se deja un movimiento vacío
      IF NOT EXISTS (SELECT 1 FROM movimiento_detalle WHERE id_movimiento = v_id_mov) THEN
        DELETE FROM movimiento WHERE id_movimiento = v_id_mov;
      END IF;
    END IF;
  END LOOP;
END $$;

-- ---------------------------------------------------------
-- 6. COMISIONES (operarios asignados a las órdenes nuevas, sin repetir)
-- ---------------------------------------------------------
INSERT INTO comision (id_odt, id_operario, activo)
SELECT DISTINCT ON (par.id_odt, par.id_operario) par.id_odt, par.id_operario, TRUE
  FROM (
        SELECT (SELECT id_odt FROM orden_de_trabajo
                 WHERE numero_ot LIKE 'OT-2026-1%' AND g.n > 0 ORDER BY random() LIMIT 1) AS id_odt,
               (SELECT id_operario FROM operario
                 WHERE legajo LIKE 'DEMO-%' AND g.n > 0 ORDER BY random() LIMIT 1) AS id_operario
          FROM generate_series(1, 200) AS g(n)
       ) par
 WHERE par.id_odt IS NOT NULL AND par.id_operario IS NOT NULL;

-- ---------------------------------------------------------
-- 7. REAJUSTE DE SECUENCIAS
-- ---------------------------------------------------------
SELECT setval('operario_id_operario_seq', (SELECT COALESCE(MAX(id_operario), 1) FROM operario));
SELECT setval('stock_actual_id_stock_actual_seq', (SELECT COALESCE(MAX(id_stock_actual), 1) FROM stock_actual));
SELECT setval('insumos_id_insumo_seq', (SELECT COALESCE(MAX(id_insumo), 1) FROM insumos));
SELECT setval('orden_de_trabajo_id_odt_seq', (SELECT COALESCE(MAX(id_odt), 1) FROM orden_de_trabajo));
SELECT setval('movimiento_id_movimiento_seq', (SELECT COALESCE(MAX(id_movimiento), 1) FROM movimiento));
SELECT setval('movimiento_detalle_id_detalle_seq', (SELECT COALESCE(MAX(id_detalle), 1) FROM movimiento_detalle));
SELECT setval('comision_id_comision_seq', (SELECT COALESCE(MAX(id_comision), 1) FROM comision));

COMMIT;
