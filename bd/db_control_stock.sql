-- =========================================================
-- SCRIPT DE ESTRUCTURA PARA POSTGRESQL (db_control_stock)
-- =========================================================

-- Limpieza preventiva en orden inverso de dependencias
DROP TABLE IF EXISTS movimiento_detalle CASCADE;
DROP TABLE IF EXISTS movimiento CASCADE;
DROP TABLE IF EXISTS comision CASCADE;
DROP TABLE IF EXISTS orden_de_trabajo CASCADE;
DROP TABLE IF EXISTS herramienta CASCADE;
DROP TABLE IF EXISTS insumos CASCADE;
DROP TABLE IF EXISTS usuario CASCADE;
DROP TABLE IF EXISTS operario CASCADE;
DROP TABLE IF EXISTS rubro CASCADE;
DROP TABLE IF EXISTS tipo_movimiento CASCADE;
DROP TABLE IF EXISTS estado_herramienta CASCADE;
DROP TABLE IF EXISTS ubicacion CASCADE;
DROP TABLE IF EXISTS unidad_medida CASCADE;
DROP TABLE IF EXISTS tipo CASCADE;
DROP TABLE IF EXISTS jurisdiccion CASCADE;
DROP TABLE IF EXISTS localidad CASCADE;
DROP TABLE IF EXISTS rol CASCADE;

-- ---------------------------------------------------------
-- 1. CATÁLOGOS BASE
-- ---------------------------------------------------------

CREATE TABLE rol (
    id_rol SERIAL PRIMARY KEY,
    rol VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255)
);

CREATE TABLE localidad (
    id_localidad SERIAL PRIMARY KEY,
    localidad VARCHAR(100) NOT NULL
);

CREATE TABLE jurisdiccion (
    id_jurisdiccion SERIAL PRIMARY KEY,
    jurisdiccion VARCHAR(100) NOT NULL,
    direccion VARCHAR(255)
);

CREATE TABLE tipo (
    id_tipo SERIAL PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    detalle VARCHAR(255)
);

CREATE TABLE unidad_medida (
    id_unidad_medida SERIAL PRIMARY KEY,
    unidad_medida VARCHAR(50) NOT NULL
);

CREATE TABLE ubicacion (
    id_ubicacion SERIAL PRIMARY KEY,
    ubicacion VARCHAR(100) NOT NULL
);

CREATE TABLE estado_herramienta (
    id_estado_herramienta SERIAL PRIMARY KEY,
    estado VARCHAR(50) NOT NULL
);

CREATE TABLE tipo_movimiento (
    id_tipo_mov SERIAL PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL
);

CREATE TABLE rubro (
    id_rubro SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255),
    activo BOOLEAN NOT NULL DEFAULT TRUE
);

-- ---------------------------------------------------------
-- 2. OPERARIOS Y USUARIOS
-- ---------------------------------------------------------

CREATE TABLE operario (
    id_operario SERIAL PRIMARY KEY,
    dni VARCHAR(20) NOT NULL UNIQUE,
    apellido VARCHAR(100) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    legajo VARCHAR(50) UNIQUE,
    id_rubro INT,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_operario_rubro FOREIGN KEY (id_rubro) REFERENCES rubro(id_rubro) ON DELETE SET NULL
);

CREATE TABLE usuario (
    dni VARCHAR(20) PRIMARY KEY,
    contrasena VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    id_rol INT NOT NULL,
    id_operario INT,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES rol(id_rol),
    CONSTRAINT fk_usuario_operario FOREIGN KEY (id_operario) REFERENCES operario(id_operario) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- 3. INSUMOS Y HERRAMIENTAS
-- ---------------------------------------------------------

CREATE TABLE insumos (
    id_insumo SERIAL PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    id_tipo INT NOT NULL,
    id_rubro INT NOT NULL,
    id_ubicacion INT,
    id_unidad_medida INT,
    stock_actual NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    stock_minimo NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    es_perecedero BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_vencimiento DATE,
    serie_modelo VARCHAR(100),
    id_estado_herramienta INT,
    fecha_registro DATE NOT NULL DEFAULT CURRENT_DATE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_insumos_tipo FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo),
    CONSTRAINT fk_insumos_rubro FOREIGN KEY (id_rubro) REFERENCES rubro(id_rubro),
    CONSTRAINT fk_insumos_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES ubicacion(id_ubicacion) ON DELETE SET NULL,
    CONSTRAINT fk_insumos_unidad FOREIGN KEY (id_unidad_medida) REFERENCES unidad_medida(id_unidad_medida) ON DELETE SET NULL,
    CONSTRAINT fk_insumos_estado_herr FOREIGN KEY (id_estado_herramienta) REFERENCES estado_herramienta(id_estado_herramienta) ON DELETE SET NULL
);

CREATE TABLE herramienta (
    id_herramienta SERIAL PRIMARY KEY,
    id_operario VARCHAR(20),
    fecha_entrega DATE,
    fecha_devolucion DATE,
    id_estado_herramienta INT,
    observaciones TEXT,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_herramienta_operario FOREIGN KEY (id_operario) REFERENCES operario(dni) ON DELETE SET NULL,
    CONSTRAINT fk_herramienta_estado_herr FOREIGN KEY (id_estado_herramienta) REFERENCES estado_herramienta(id_estado_herramienta) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- 4. ÓRDENES DE TRABAJO Y COMISIÓN
-- ---------------------------------------------------------

CREATE TABLE orden_de_trabajo (
    id_odt SERIAL PRIMARY KEY,
    numero_ot VARCHAR(50) NOT NULL,
    pa VARCHAR(50),
    trabajo_a_realizar TEXT,
    id_localidad INT,
    id_jurisdiccion INT,
    fecha_inicio DATE,
    fecha_final DATE,
    hora_inicio TIME,
    hora_final TIME,
    estado VARCHAR(50) DEFAULT 'Pendiente',
    observaciones TEXT,
    archivo_pdf VARCHAR(255),
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    id_mov_egreso INT,
    id_mov_devolucion INT,
    CONSTRAINT fk_odt_localidad FOREIGN KEY (id_localidad) REFERENCES localidad(id_localidad) ON DELETE SET NULL,
    CONSTRAINT fk_odt_jurisdiccion FOREIGN KEY (id_jurisdiccion) REFERENCES jurisdiccion(id_jurisdiccion) ON DELETE SET NULL
);

CREATE TABLE comision (
    id_comision SERIAL PRIMARY KEY,
    id_odt INT NOT NULL,
    id_operario VARCHAR(20) NOT NULL,
    CONSTRAINT fk_comision_odt FOREIGN KEY (id_odt) REFERENCES orden_de_trabajo(id_odt) ON DELETE CASCADE,
    CONSTRAINT fk_comision_operario FOREIGN KEY (id_operario) REFERENCES operario(dni) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- 5. MOVIMIENTOS Y DETALLES
-- ---------------------------------------------------------

CREATE TABLE movimiento (
    id_movimiento SERIAL PRIMARY KEY,
    id_tipo_mov INT NOT NULL,
    id_operario INT,
    dni_usuario VARCHAR(20) NOT NULL,
    id_odt INT,
    fecha DATE NOT NULL DEFAULT CURRENT_DATE,
    hora TIME NOT NULL DEFAULT CURRENT_TIME,
    observaciones TEXT,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_mov_tipo FOREIGN KEY (id_tipo_mov) REFERENCES tipo_movimiento(id_tipo_mov),
    CONSTRAINT fk_mov_operario FOREIGN KEY (id_operario) REFERENCES operario(id_operario) ON DELETE SET NULL,
    CONSTRAINT fk_mov_usuario FOREIGN KEY (dni_usuario) REFERENCES usuario(dni),
    CONSTRAINT fk_mov_odt FOREIGN KEY (id_odt) REFERENCES orden_de_trabajo(id_odt) ON DELETE SET NULL
);

CREATE TABLE movimiento_detalle (
    id_detalle SERIAL PRIMARY KEY,
    id_movimiento INT NOT NULL,
    id_insumo INT NOT NULL,
    cantidad NUMERIC(10,2) NOT NULL,
    id_estado_herramienta INT,
    CONSTRAINT fk_det_movimiento FOREIGN KEY (id_movimiento) REFERENCES movimiento(id_movimiento) ON DELETE CASCADE,
    CONSTRAINT fk_det_insumo FOREIGN KEY (id_insumo) REFERENCES insumos(id_insumo),
    CONSTRAINT fk_det_estado_herr FOREIGN KEY (id_estado_herramienta) REFERENCES estado_herramienta(id_estado_herramienta) ON DELETE SET NULL
);

-- Claves foráneas diferidas para orden_de_trabajo
ALTER TABLE orden_de_trabajo
    ADD CONSTRAINT fk_odt_mov_egreso FOREIGN KEY (id_mov_egreso) REFERENCES movimiento(id_movimiento) ON DELETE SET NULL,
    ADD CONSTRAINT fk_odt_mov_dev FOREIGN KEY (id_mov_devolucion) REFERENCES movimiento(id_movimiento) ON DELETE SET NULL;