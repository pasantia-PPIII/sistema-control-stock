-- =========================================================
-- MIGRACIÓN: ajustes del análisis de requerimientos
-- Para bases YA CREADAS con db_control_stock.sql anterior (no borra datos).
-- Una base nueva no la necesita: db_control_stock.sql ya incluye todo esto.
-- Se puede ejecutar más de una vez.
-- =========================================================

-- 1) RF-08: descripción del material / insumo
ALTER TABLE insumos ADD COLUMN IF NOT EXISTS descripcion VARCHAR(500);

-- 2) RF-18: baja de herramienta con motivo y fecha
ALTER TABLE insumos ADD COLUMN IF NOT EXISTS motivo_baja VARCHAR(20);
ALTER TABLE insumos ADD COLUMN IF NOT EXISTS observacion_baja VARCHAR(255);
ALTER TABLE insumos ADD COLUMN IF NOT EXISTS fecha_baja DATE;
ALTER TABLE insumos DROP CONSTRAINT IF EXISTS ck_insumos_motivo_baja;
ALTER TABLE insumos ADD CONSTRAINT ck_insumos_motivo_baja
    CHECK (motivo_baja IS NULL OR motivo_baja IN ('Rotura', 'Pérdida', 'Obsolescencia', 'Otro'));

-- 3) RF-45: tipo de trabajo, dependencia solicitante y fecha de solicitud de la orden de trabajo
ALTER TABLE orden_de_trabajo ADD COLUMN IF NOT EXISTS tipo_trabajo VARCHAR(50);
ALTER TABLE orden_de_trabajo ADD COLUMN IF NOT EXISTS dependencia_solicitante VARCHAR(150);
ALTER TABLE orden_de_trabajo ADD COLUMN IF NOT EXISTS fecha_solicitud DATE;
ALTER TABLE orden_de_trabajo DROP CONSTRAINT IF EXISTS ck_odt_tipo_trabajo;
ALTER TABLE orden_de_trabajo ADD CONSTRAINT ck_odt_tipo_trabajo
    CHECK (tipo_trabajo IS NULL OR tipo_trabajo IN ('Reparación', 'Construcción', 'Mantenimiento edilicio'));

-- 4) RF-45 / RF-46: rubros involucrados en cada orden
CREATE TABLE IF NOT EXISTS orden_rubro (
    id_odt INT NOT NULL,
    id_rubro INT NOT NULL,
    PRIMARY KEY (id_odt, id_rubro),
    CONSTRAINT fk_orden_rubro_odt FOREIGN KEY (id_odt) REFERENCES orden_de_trabajo(id_odt) ON DELETE CASCADE,
    CONSTRAINT fk_orden_rubro_rubro FOREIGN KEY (id_rubro) REFERENCES rubro(id_rubro)
);

-- 5) RF-45: número de OT único. Si ya hay números repetidos, la migración se detiene y los lista:
--    hay que corregirlos a mano (renumerar o anular) y volver a ejecutar este archivo.
DO $$
DECLARE
    repetidos TEXT;
BEGIN
    SELECT string_agg(numero || ' (' || cantidad || ' órdenes)', ', ')
      INTO repetidos
      FROM (SELECT lower(btrim(numero_ot)) AS numero, COUNT(*) AS cantidad
              FROM orden_de_trabajo
             GROUP BY lower(btrim(numero_ot))
            HAVING COUNT(*) > 1) d;

    IF repetidos IS NOT NULL THEN
        RAISE EXCEPTION 'Hay números de orden de trabajo repetidos: %. Corríjalos y vuelva a ejecutar la migración.', repetidos;
    END IF;
END
$$;

CREATE UNIQUE INDEX IF NOT EXISTS uq_odt_numero_ot ON orden_de_trabajo (lower(btrim(numero_ot)));
