-- =========================================================
-- MIGRACIÓN: motivo de devolución (RF-34)
-- Para bases de datos YA creadas. Una instalación nueva no la necesita: db_control_stock.sql y
-- seed_data.sql ya incluyen estos cambios. Se puede ejecutar más de una vez sin problemas.
-- =========================================================

CREATE TABLE IF NOT EXISTS motivo_devolucion (
    id_motivo SERIAL PRIMARY KEY,
    motivo VARCHAR(50) NOT NULL
);

INSERT INTO motivo_devolucion (id_motivo, motivo) VALUES
(1, 'Sobrante'),
(2, 'Fin de tarea'),
(3, 'Herramienta rota'),
(4, 'Otro')
ON CONFLICT (id_motivo) DO UPDATE SET motivo = EXCLUDED.motivo;

SELECT setval(pg_get_serial_sequence('motivo_devolucion', 'id_motivo'), (SELECT MAX(id_motivo) FROM motivo_devolucion));

ALTER TABLE movimiento ADD COLUMN IF NOT EXISTS id_motivo INT;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_mov_motivo') THEN
        ALTER TABLE movimiento
            ADD CONSTRAINT fk_mov_motivo FOREIGN KEY (id_motivo) REFERENCES motivo_devolucion(id_motivo) ON DELETE SET NULL;
    END IF;
END $$;
