-- =========================================================
-- SCRIPT DE CREACIÓN PARA POSTGRESQL (Base de datos: db_control_stock)
-- =========================================================

-- Limpieza preventiva en caso de ejecuciones previas
DROP TABLE IF EXISTS movimiento_detalle CASCADE;
DROP TABLE IF EXISTS comision CASCADE;
DROP TABLE IF EXISTS orden_de_trabajo CASCADE;
DROP TABLE IF EXISTS movimiento CASCADE;
DROP TABLE IF EXISTS herramienta CASCADE;
DROP TABLE IF EXISTS insumos CASCADE;
DROP TABLE IF EXISTS stock_actual CASCADE;
DROP TABLE IF EXISTS operario CASCADE;
DROP TABLE IF EXISTS usuario CASCADE;
DROP TABLE IF EXISTS rol CASCADE;
DROP TABLE IF EXISTS tipo_movimiento CASCADE;
DROP TABLE IF EXISTS ubicacion CASCADE;
DROP TABLE IF EXISTS rubro CASCADE;
DROP TABLE IF EXISTS tipo CASCADE;
DROP TABLE IF EXISTS unidad_medida CASCADE;
DROP TABLE IF EXISTS jurisdiccion CASCADE;
DROP TABLE IF EXISTS localidad CASCADE;
DROP TABLE IF EXISTS estado_herramienta CASCADE;

-- ---------------------------------------------------------
-- 1. TABLAS DE CATÁLOGOS / INDEPENDIENTES
-- ---------------------------------------------------------

CREATE TABLE rol (
    id_rol INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    rol VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255)
);

CREATE TABLE rubro (
    id_rubro INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255),
    activo BOOLEAN DEFAULT TRUE
);

COMMENT ON COLUMN rubro.activo IS 'TRUE = Activo, FALSE = Inactivo/Deshabilitado';

CREATE TABLE tipo (
    id_tipo INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    detalle VARCHAR(255)
);

CREATE TABLE unidad_medida (
    id_unidad_medida INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    unidad_medida VARCHAR(50) NOT NULL
);

CREATE TABLE ubicacion (
    id_ubicacion INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ubicacion VARCHAR(100) NOT NULL
);

CREATE TABLE estado_herramienta (
    id_estado_herramienta INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    estado VARCHAR(50) NOT NULL
);

CREATE TABLE tipo_movimiento (
    id_tipo_mov INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL
);

CREATE TABLE jurisdiccion (
    id_jurisdiccion INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    jurisdiccion VARCHAR(100) NOT NULL,
    direccion VARCHAR(255)
);

CREATE TABLE localidad (
    id_localidad INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    localidad VARCHAR(100) NOT NULL
);

CREATE TABLE stock_actual (
    id_stock_actual INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    stock_actual NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    fecha DATE NOT NULL
);

-- ---------------------------------------------------------
-- 2. USUARIOS, OPERARIOS E INSUMOS
-- ---------------------------------------------------------

CREATE TABLE operario (
    dni VARCHAR(20) PRIMARY KEY,
    apellido VARCHAR(100) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    codigo VARCHAR(50) UNIQUE,
    id_rubro INT,
    activo BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_operario_rubro FOREIGN KEY (id_rubro) REFERENCES rubro(id_rubro)
);

CREATE TABLE usuario (
    dni VARCHAR(20) PRIMARY KEY,
	dni_operario VARCHAR(20) UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    activo BOOLEAN  DEFAULT TRUE,
	CONSTRAINT fk_usuario_operario FOREIGN KEY (dni_operario)
		REFERENCES operario(dni)
		ON DELETE SET NULL
		ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES rol(id_rol)
);

CREATE TABLE insumos (
    codigo VARCHAR(50) PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    id_unidad_medida INT NOT NULL,
    id_tipo INT NOT NULL,
    id_rubro INT NOT NULL,
    id_ubicacion INT NOT NULL,
    id_stock_actual INT,
    stock_minimo NUMERIC(10,2) DEFAULT 0.00,
    es_perecedero BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_vencimiento DATE,
    serie_modelo VARCHAR(100),
    fecha_registro DATE NOT NULL DEFAULT CURRENT_DATE,
    activo BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_insumos_unidad FOREIGN KEY (id_unidad_medida) REFERENCES unidad_medida(id_unidad_medida),
    CONSTRAINT fk_insumos_tipo FOREIGN KEY (id_tipo) REFERENCES tipo(id_tipo),
    CONSTRAINT fk_insumos_rubro FOREIGN KEY (id_rubro) REFERENCES rubro(id_rubro),
    CONSTRAINT fk_insumos_ubicacion FOREIGN KEY (id_ubicacion) REFERENCES ubicacion(id_ubicacion),
    CONSTRAINT fk_insumos_stock FOREIGN KEY (id_stock_actual) REFERENCES stock_actual(id_stock_actual)
);

CREATE TABLE herramienta (
    id_herramienta INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_operario VARCHAR(20),
    fecha_entrega DATE,
    fecha_devolucion DATE,
    id_estado_herramienta INT,
    observaciones TEXT,
    activo  BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_herramienta_operario FOREIGN KEY (id_operario) REFERENCES operario(dni),
    CONSTRAINT fk_herramienta_estado FOREIGN KEY (id_estado_herramienta) REFERENCES estado_herramienta(id_estado_herramienta)
);

-- ---------------------------------------------------------
-- 3. MOVIMIENTOS, ÓRDENES DE TRABAJO Y COMISIÓN
-- ---------------------------------------------------------

CREATE TABLE movimiento (
    id_movimiento INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_tipo_mov INT NOT NULL,
    id_operario VARCHAR(20),
    id_usuario VARCHAR(20) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    observaciones TEXT,
    activo BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_mov_tipo FOREIGN KEY (id_tipo_mov) REFERENCES tipo_movimiento(id_tipo_mov),
    CONSTRAINT fk_mov_operario FOREIGN KEY (id_operario) REFERENCES operario(dni),
    CONSTRAINT fk_mov_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(dni)
);

COMMENT ON COLUMN movimiento.activo IS 'TRUE= VÁLIDO, FALSE= Anulado/Cancelado';

CREATE TABLE movimiento_detalle (
    id INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_movimiento INT NOT NULL,
    id_insumo VARCHAR(50) NOT NULL,
    cantidad NUMERIC(10,2) NOT NULL,
    id_estado_herramienta INT,
    CONSTRAINT fk_det_movimiento FOREIGN KEY (id_movimiento) REFERENCES movimiento(id_movimiento),
    CONSTRAINT fk_det_insumo FOREIGN KEY (id_insumo) REFERENCES insumos(codigo),
    CONSTRAINT fk_det_estado_herr FOREIGN KEY (id_estado_herramienta) REFERENCES estado_herramienta(id_estado_herramienta)
);

CREATE TABLE orden_de_trabajo (
    id_odt INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_mov_egreso INT,
    id_mov_devolucion INT,
    id_localidad INT,
    id_jurisdiccion INT,
    pa VARCHAR(50),
    trabajo_a_realizar TEXT,
    fecha_inicio DATE,
    fecha_final DATE,
    hora_inicio TIME,
    hora_final TIME,
    estado VARCHAR(50),
    observaciones TEXT,
    activo BOOLEAN DEFAULT TRUE,
    archivo_pdf VARCHAR (255),
    CONSTRAINT fk_odt_mov_egreso FOREIGN KEY (id_mov_egreso) REFERENCES movimiento(id_movimiento),
    CONSTRAINT fk_odt_mov_dev FOREIGN KEY (id_mov_devolucion) REFERENCES movimiento(id_movimiento),
    CONSTRAINT fk_odt_localidad FOREIGN KEY (id_localidad) REFERENCES localidad(id_localidad),
    CONSTRAINT fk_odt_jurisdiccion FOREIGN KEY (id_jurisdiccion) REFERENCES jurisdiccion(id_jurisdiccion)
);

COMMENT ON COLUMN orden_de_trabajo.activo IS 'TRUE= Activa, FALSE=Anulada';

-- La comisión relaciona operarios a una Orden de Trabajo (ODT)
CREATE TABLE comision (
    id_comision INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_odt INT NOT NULL,
    id_operario VARCHAR(20) NOT NULL,
    CONSTRAINT fk_comision_odt FOREIGN KEY (id_odt) REFERENCES orden_de_trabajo(id_odt),
    CONSTRAINT fk_comision_operario FOREIGN KEY (id_operario) REFERENCES operario(dni)
);