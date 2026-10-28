-- =========================================================
-- TABLA DE AUDITORÍA Y TRAZABILIDAD (bd/auditoria.sql)
-- Optimizado para PostgreSQL con soporte JSONB e índices B-tree y GIN
-- =========================================================

CREATE TABLE IF NOT EXISTS auditoria (
    id_auditoria BIGSERIAL PRIMARY KEY,
    fecha_hora TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
    dni_usuario VARCHAR(20),
    nombre_usuario VARCHAR(150),
    rol_usuario VARCHAR(50),
    accion VARCHAR(50) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    descripcion TEXT NOT NULL,
    ip VARCHAR(45),
    user_agent TEXT,
    datos_anteriores JSONB,
    datos_nuevos JSONB
);

-- Índices B-tree para búsquedas, filtros frecuentes y ordenamiento cronológico
CREATE INDEX IF NOT EXISTS idx_auditoria_fecha ON auditoria (fecha_hora DESC);
CREATE INDEX IF NOT EXISTS idx_auditoria_dni ON auditoria (dni_usuario);
CREATE INDEX IF NOT EXISTS idx_auditoria_modulo ON auditoria (modulo);
CREATE INDEX IF NOT EXISTS idx_auditoria_accion ON auditoria (accion);

-- Índices GIN para consultas eficientes dentro de los documentos JSONB
CREATE INDEX IF NOT EXISTS idx_auditoria_datos_anteriores_gin ON auditoria USING gin (datos_anteriores);
CREATE INDEX IF NOT EXISTS idx_auditoria_datos_nuevos_gin ON auditoria USING gin (datos_nuevos);
