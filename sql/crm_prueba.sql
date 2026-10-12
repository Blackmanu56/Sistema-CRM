-- =========================================================
-- CRM ADEMI — instalación limpia (UN SOLO SCRIPT)
-- Base: crm_prueba  |  charset utf8mb4
--
-- Importar (borra y recrea la base):
--   mysql -u root < sql/crm_prueba.sql
--   o desde phpMyAdmin: Importar este archivo
--
-- Usuario inicial:
--   usuario: superadmin
--   clave:   admin
--
-- Incluye esquema final + catálogos. Sin datos de prueba
-- (clientes, tareas, turnos, actividades).
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS crm_prueba;
CREATE DATABASE crm_prueba
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE crm_prueba;

-- ---------------------------------------------------------
-- Catálogo base
-- ---------------------------------------------------------

CREATE TABLE area (
  id_area              SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave                VARCHAR(30)  NOT NULL,
  nombre               VARCHAR(80)  NOT NULL,
  activo               TINYINT(1) NOT NULL DEFAULT 1,
  atiende_clientes     TINYINT(1) NOT NULL DEFAULT 0,
  asignable_empleado   TINYINT(1) NOT NULL DEFAULT 1,
  creado_en            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_area_clave UNIQUE (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rol (
  id_rol      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(30)  NOT NULL,
  nombre      VARCHAR(80)  NOT NULL,
  CONSTRAINT uq_rol_clave UNIQUE (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tipo_tarea (
  id_tipo     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_area     SMALLINT UNSIGNED NOT NULL,
  nombre      VARCHAR(80) NOT NULL,
  CONSTRAINT fk_tipo_tarea_area FOREIGN KEY (id_area) REFERENCES area(id_area),
  CONSTRAINT uq_tipo_tarea_area_nombre UNIQUE (id_area, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provincia (
  id_provincia SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(80) NOT NULL,
  CONSTRAINT uq_provincia_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE localidad (
  id_localidad  SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80) NOT NULL,
  id_provincia  SMALLINT UNSIGNED NOT NULL,
  CONSTRAINT fk_localidad_provincia FOREIGN KEY (id_provincia) REFERENCES provincia(id_provincia),
  CONSTRAINT uq_localidad UNIQUE (nombre, id_provincia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Empleados
-- ---------------------------------------------------------

CREATE TABLE empleado (
  id_empleado          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombres              VARCHAR(80)  NOT NULL,
  apellido             VARCHAR(80)  NOT NULL,
  cuil                 CHAR(11)     NOT NULL,
  dni                  VARCHAR(15)  NOT NULL,
  fecha_nacimiento     DATE NULL,
  correo               VARCHAR(150) NOT NULL,
  telefono             VARCHAR(30),
  telefono_alt         VARCHAR(30),
  direccion            VARCHAR(200),
  estado_civil         VARCHAR(40) NULL,
  localidad_domicilio  VARCHAR(100) NULL,
  provincia_domicilio  VARCHAR(80) NULL,
  puesto               VARCHAR(120) NULL,
  tipo_vinculo         VARCHAR(80) NULL,
  estado_laboral       VARCHAR(40) NOT NULL DEFAULT 'activo',
  fecha_baja           DATE NULL,
  motivo_baja          VARCHAR(200) NULL,
  modalidad            VARCHAR(40) NULL,
  horario_jornada      VARCHAR(120) NULL,
  condicion_fiscal     VARCHAR(60) NULL,
  cuit_facturacion     VARCHAR(13) NULL,
  fecha_ingreso        DATE NOT NULL,
  fecha_alta           DATE NOT NULL DEFAULT (CURRENT_DATE),
  nombre_usuario       VARCHAR(50)  NOT NULL,
  contrasena_hash      VARCHAR(255) NOT NULL,
  primer_acceso        TINYINT(1) NOT NULL DEFAULT 1,
  id_rol               SMALLINT UNSIGNED NOT NULL,
  id_area              SMALLINT UNSIGNED NULL,
  id_responsable       INT UNSIGNED NULL,
  activo               TINYINT(1) NOT NULL DEFAULT 1,
  creado_en            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_empleado_cuil    UNIQUE (cuil),
  CONSTRAINT uq_empleado_dni     UNIQUE (dni),
  CONSTRAINT uq_empleado_correo  UNIQUE (correo),
  CONSTRAINT uq_empleado_usuario UNIQUE (nombre_usuario),
  CONSTRAINT fk_empleado_rol  FOREIGN KEY (id_rol)  REFERENCES rol(id_rol),
  CONSTRAINT fk_empleado_area FOREIGN KEY (id_area) REFERENCES area(id_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE empleado_rol_adicional (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_empleado   INT UNSIGNED NOT NULL,
  id_rol        SMALLINT UNSIGNED NOT NULL,
  id_area       SMALLINT UNSIGNED NULL,
  fecha_desde   DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_hasta   DATE NULL,
  CONSTRAINT fk_rol_adic_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
  CONSTRAINT fk_rol_adic_rol      FOREIGN KEY (id_rol)      REFERENCES rol(id_rol),
  CONSTRAINT fk_rol_adic_area     FOREIGN KEY (id_area)     REFERENCES area(id_area),
  CONSTRAINT chk_rol_adic_fechas  CHECK (fecha_hasta IS NULL OR fecha_hasta >= fecha_desde),
  INDEX idx_rol_adic_activo (id_empleado, fecha_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Clientes / portal
-- ---------------------------------------------------------

CREATE TABLE usuario_cliente (
  id_usuario      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre          VARCHAR(150) NOT NULL,
  correo          VARCHAR(150) NOT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  primer_acceso   TINYINT(1) NOT NULL DEFAULT 1,
  dni             VARCHAR(15) NULL,
  telefono        VARCHAR(30),
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_usuario_cliente_correo UNIQUE (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente (
  id_cliente       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_cliente     ENUM('emprendedor','empresa','municipalidad','organismo') NOT NULL,
  nombre           VARCHAR(150) NOT NULL,
  correo           VARCHAR(150) NULL,
  telefono         VARCHAR(30),
  id_localidad     SMALLINT UNSIGNED NOT NULL,
  direccion        VARCHAR(200),
  cuil_cuit        VARCHAR(13) NOT NULL,
  id_area_afectada SMALLINT UNSIGNED NULL,
  origen_contacto  ENUM('web','presencial','whatsapp','redes') NULL,
  fecha_alta       DATE NOT NULL DEFAULT (CURRENT_DATE),
  activo           TINYINT(1) NOT NULL DEFAULT 1,
  creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_cliente_cuil_cuit UNIQUE (cuil_cuit),
  CONSTRAINT fk_cliente_localidad FOREIGN KEY (id_localidad)     REFERENCES localidad(id_localidad),
  CONSTRAINT fk_cliente_area      FOREIGN KEY (id_area_afectada) REFERENCES area(id_area),
  INDEX idx_cliente_tipo (tipo_cliente),
  INDEX idx_cliente_tipo_activo (tipo_cliente, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_persona (
  id_cliente  INT UNSIGNED PRIMARY KEY,
  genero      ENUM('femenino','masculino','otro') NULL,
  dni         VARCHAR(15) NULL,
  CONSTRAINT fk_clipersona_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_empresa (
  id_cliente    INT UNSIGNED PRIMARY KEY,
  razon_social  VARCHAR(150) NULL,
  CONSTRAINT fk_cliempresa_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes_usuario_clientes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario    INT UNSIGNED NOT NULL,
  id_cliente    INT UNSIGNED NOT NULL,
  rol           ENUM('titular','representante') NOT NULL,
  es_principal  TINYINT(1) NOT NULL DEFAULT 0,
  fecha_desde   DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_hasta   DATE NULL,
  CONSTRAINT fk_cuc_usuario  FOREIGN KEY (id_usuario) REFERENCES usuario_cliente(id_usuario),
  CONSTRAINT fk_cuc_cliente  FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
  CONSTRAINT uq_cuc_usuario_cliente UNIQUE (id_usuario, id_cliente),
  CONSTRAINT chk_cuc_fechas CHECK (fecha_hasta IS NULL OR fecha_hasta >= fecha_desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_area (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente    INT UNSIGNED NOT NULL,
  id_area       SMALLINT UNSIGNED NOT NULL,
  fecha_desde   DATE NOT NULL DEFAULT (CURRENT_DATE),
  CONSTRAINT fk_cliarea_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
  CONSTRAINT fk_cliarea_area    FOREIGN KEY (id_area)    REFERENCES area(id_area),
  CONSTRAINT uq_cliarea UNIQUE (id_cliente, id_area)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Tareas y flujo
-- ---------------------------------------------------------

CREATE TABLE eje (
  id_eje   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tipo  INT UNSIGNED NOT NULL,
  nombre   VARCHAR(80) NOT NULL,
  CONSTRAINT fk_eje_tipo FOREIGN KEY (id_tipo) REFERENCES tipo_tarea(id_tipo),
  CONSTRAINT uq_eje_tipo_nombre UNIQUE (id_tipo, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE atencion (
  id_atencion        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente         INT UNSIGNED NOT NULL,
  id_area            SMALLINT UNSIGNED NOT NULL,
  id_empleado        INT UNSIGNED NULL,
  motivo             ENUM('consulta','documentacion','seguimiento','tramite','otro') NOT NULL DEFAULT 'consulta',
  canal              ENUM('web','presencial','whatsapp','redes') NOT NULL DEFAULT 'presencial',
  detalle            TEXT,
  estado             ENUM('pendiente','abierta','en_atencion','cerrada','derivada','cancelada') NOT NULL DEFAULT 'abierta',
  id_atencion_origen INT UNSIGNED NULL,
  fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_turno        DATETIME NULL,
  CONSTRAINT fk_aten_cliente  FOREIGN KEY (id_cliente)  REFERENCES cliente(id_cliente),
  CONSTRAINT fk_aten_area     FOREIGN KEY (id_area)     REFERENCES area(id_area),
  CONSTRAINT fk_aten_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
  CONSTRAINT fk_aten_origen   FOREIGN KEY (id_atencion_origen) REFERENCES atencion(id_atencion),
  INDEX idx_aten_estado_fecha (estado, fecha_turno),
  INDEX idx_aten_area_estado (id_area, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea (
  id_tarea          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo            VARCHAR(200) NOT NULL,
  descripcion       TEXT,
  id_area           SMALLINT UNSIGNED NOT NULL,
  id_tipo           INT UNSIGNED NOT NULL,
  prioridad         ENUM('urgente','alta','media','baja') NOT NULL DEFAULT 'media',
  estado            ENUM('no_iniciada','en_proceso','pausada','terminada','cancelada') NOT NULL DEFAULT 'no_iniciada',
  fecha_inicio      DATE NOT NULL,
  fecha_cierre      DATE NOT NULL,
  avance            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  id_eje            INT UNSIGNED NULL,
  fecha_cierre_real DATE NULL,
  id_atencion       INT UNSIGNED NULL,
  id_cliente        INT UNSIGNED NULL,
  id_usuario_actor  INT UNSIGNED NULL,
  id_tarea_padre    INT UNSIGNED NULL,
  uid               VARCHAR(20),
  creado_en         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tarea_area        FOREIGN KEY (id_area)          REFERENCES area(id_area),
  CONSTRAINT fk_tarea_tipo        FOREIGN KEY (id_tipo)          REFERENCES tipo_tarea(id_tipo),
  CONSTRAINT fk_tarea_cliente     FOREIGN KEY (id_cliente)       REFERENCES cliente(id_cliente),
  CONSTRAINT fk_tarea_usuario_act FOREIGN KEY (id_usuario_actor) REFERENCES usuario_cliente(id_usuario),
  CONSTRAINT fk_tarea_padre       FOREIGN KEY (id_tarea_padre)   REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tarea_eje         FOREIGN KEY (id_eje)           REFERENCES eje(id_eje),
  CONSTRAINT fk_tarea_aten        FOREIGN KEY (id_atencion)      REFERENCES atencion(id_atencion),
  CONSTRAINT chk_tarea_avance     CHECK (avance BETWEEN 0 AND 100),
  CONSTRAINT chk_tarea_fechas     CHECK (fecha_cierre >= fecha_inicio),
  INDEX idx_tarea_estado (estado),
  INDEX idx_tarea_cierre_estado (fecha_cierre, estado),
  INDEX idx_tarea_padre (id_tarea_padre),
  INDEX idx_tarea_cliente (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_responsable (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea      INT UNSIGNED NOT NULL,
  id_empleado   INT UNSIGNED NOT NULL,
  es_principal  TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_tresp_tarea    FOREIGN KEY (id_tarea)    REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tresp_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
  CONSTRAINT uq_tresp_tarea_empleado UNIQUE (id_tarea, id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_inicio (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea            INT UNSIGNED NOT NULL,
  id_empleado         INT UNSIGNED NOT NULL,
  fecha_click_inicio  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tini_tarea    FOREIGN KEY (id_tarea)    REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tini_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
  CONSTRAINT uq_tini_tarea_empleado UNIQUE (id_tarea, id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_enlace (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea     INT UNSIGNED NOT NULL,
  url          VARCHAR(500) NOT NULL,
  fecha_carga  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tenl_tarea FOREIGN KEY (id_tarea) REFERENCES tarea(id_tarea)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_comentario (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea          INT UNSIGNED NOT NULL,
  id_empleado       INT UNSIGNED NULL,
  id_usuario_actor  INT UNSIGNED NULL,
  texto             TEXT NOT NULL,
  fecha_carga       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tcom_tarea          FOREIGN KEY (id_tarea)          REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tcom_empleado       FOREIGN KEY (id_empleado)       REFERENCES empleado(id_empleado),
  CONSTRAINT fk_tcom_usuario_actor  FOREIGN KEY (id_usuario_actor)  REFERENCES usuario_cliente(id_usuario),
  CONSTRAINT chk_tcom_autor CHECK (id_empleado IS NOT NULL OR id_usuario_actor IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_avance (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea     INT UNSIGNED NOT NULL,
  id_empleado  INT UNSIGNED NULL,
  url          VARCHAR(500) NULL,
  comentario   TEXT NULL,
  archivo      VARCHAR(255) NULL,
  fecha_carga  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tav_tarea FOREIGN KEY (id_tarea)    REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tav_emp   FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE actividad (
  id_actividad    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea        INT UNSIGNED NOT NULL,
  id_empleado     INT UNSIGNED NULL,
  tipo            ENUM('creada','proceso','completada','vencida','cancelada','cambio') NOT NULL,
  descripcion     VARCHAR(300),
  fecha_actividad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_act_tarea    FOREIGN KEY (id_tarea)    REFERENCES tarea(id_tarea),
  CONSTRAINT fk_act_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aviso (
  id_aviso          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea          INT UNSIGNED NOT NULL,
  id_empleado_de    INT UNSIGNED NOT NULL,
  id_empleado_para  INT UNSIGNED NOT NULL,
  fecha_aviso       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmado        TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_aviso_tarea  FOREIGN KEY (id_tarea)         REFERENCES tarea(id_tarea),
  CONSTRAINT fk_aviso_de     FOREIGN KEY (id_empleado_de)   REFERENCES empleado(id_empleado),
  CONSTRAINT fk_aviso_para   FOREIGN KEY (id_empleado_para) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crm_aviso (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_empleado_para   INT UNSIGNED NOT NULL,
  id_empleado_de     INT UNSIGNED NULL,
  id_tarea           INT UNSIGNED NULL,
  tipo               VARCHAR(40) NOT NULL DEFAULT 'info',
  titulo             VARCHAR(200) NOT NULL,
  detalle            TEXT NULL,
  url                VARCHAR(300) NULL,
  confirmado         TINYINT(1) NOT NULL DEFAULT 0,
  fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crmaviso_para  FOREIGN KEY (id_empleado_para) REFERENCES empleado(id_empleado),
  CONSTRAINT fk_crmaviso_de    FOREIGN KEY (id_empleado_de)   REFERENCES empleado(id_empleado),
  CONSTRAINT fk_crmaviso_tarea FOREIGN KEY (id_tarea)         REFERENCES tarea(id_tarea),
  INDEX idx_crmaviso_bandeja (id_empleado_para, confirmado, fecha),
  INDEX idx_crmaviso_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_solicitud_baja (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea           INT UNSIGNED NOT NULL,
  id_solicita        INT UNSIGNED NOT NULL,
  motivo             TEXT NOT NULL,
  estado             ENUM('pendiente_coord','pendiente_general','aceptada','rechazada') NOT NULL DEFAULT 'pendiente_coord',
  id_revisa_coord    INT UNSIGNED NULL,
  nota_coord         TEXT NULL,
  id_revisa_general  INT UNSIGNED NULL,
  nota_general       TEXT NULL,
  fecha_solicitud    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion   DATETIME NULL,
  CONSTRAINT fk_tsb_tarea    FOREIGN KEY (id_tarea)          REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tsb_solicita FOREIGN KEY (id_solicita)       REFERENCES empleado(id_empleado),
  CONSTRAINT fk_tsb_coord    FOREIGN KEY (id_revisa_coord)   REFERENCES empleado(id_empleado),
  CONSTRAINT fk_tsb_general  FOREIGN KEY (id_revisa_general) REFERENCES empleado(id_empleado),
  INDEX idx_tsb_tarea (id_tarea),
  INDEX idx_tsb_estado (estado),
  INDEX idx_tsb_pendientes (estado, fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_aviso_vencimiento (
  id_tarea     INT UNSIGNED NOT NULL,
  fecha_aviso  DATE NOT NULL,
  PRIMARY KEY (id_tarea, fecha_aviso),
  CONSTRAINT fk_tavenc_tarea FOREIGN KEY (id_tarea) REFERENCES tarea(id_tarea) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aviso_atencion (
  id_aviso         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_atencion      INT UNSIGNED NOT NULL,
  id_empleado_de   INT UNSIGNED NULL,
  id_empleado_para INT UNSIGNED NOT NULL,
  confirmado       TINYINT(1) NOT NULL DEFAULT 0,
  fecha_aviso      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_avaten_aten FOREIGN KEY (id_atencion)      REFERENCES atencion(id_atencion),
  CONSTRAINT fk_avaten_de   FOREIGN KEY (id_empleado_de)   REFERENCES empleado(id_empleado),
  CONSTRAINT fk_avaten_para FOREIGN KEY (id_empleado_para) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Programas
-- ---------------------------------------------------------

CREATE TABLE programa (
  id_programa  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(120) NOT NULL,
  descripcion  TEXT,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  creado_en    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_programa_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE programa_item (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_programa   INT UNSIGNED NOT NULL,
  id_area       SMALLINT UNSIGNED NOT NULL,
  id_tipo       INT UNSIGNED NULL,
  id_eje        INT UNSIGNED NULL,
  titulo        VARCHAR(200) NOT NULL,
  CONSTRAINT fk_progitem_prog FOREIGN KEY (id_programa) REFERENCES programa(id_programa),
  CONSTRAINT fk_progitem_area FOREIGN KEY (id_area)     REFERENCES area(id_area),
  CONSTRAINT fk_progitem_tipo FOREIGN KEY (id_tipo)     REFERENCES tipo_tarea(id_tipo),
  CONSTRAINT fk_progitem_eje  FOREIGN KEY (id_eje)      REFERENCES eje(id_eje)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_modelo (
  id_modelo  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_area    SMALLINT UNSIGNED NOT NULL,
  id_tipo    INT UNSIGNED NOT NULL,
  id_eje     INT UNSIGNED NULL,
  titulo     VARCHAR(200) NOT NULL,
  CONSTRAINT fk_tmod_area FOREIGN KEY (id_area) REFERENCES area(id_area),
  CONSTRAINT fk_tmod_tipo FOREIGN KEY (id_tipo) REFERENCES tipo_tarea(id_tipo),
  CONSTRAINT fk_tmod_eje  FOREIGN KEY (id_eje)  REFERENCES eje(id_eje)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_programa (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_cliente    INT UNSIGNED NOT NULL,
  id_programa   INT UNSIGNED NOT NULL,
  fecha_desde   DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_hasta   DATE NULL,
  CONSTRAINT fk_cliprog_cli  FOREIGN KEY (id_cliente)  REFERENCES cliente(id_cliente),
  CONSTRAINT fk_cliprog_prog FOREIGN KEY (id_programa) REFERENCES programa(id_programa),
  INDEX idx_cliprog_activo (id_cliente, id_programa, fecha_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Actividades (eventos) y portal
-- ---------------------------------------------------------

CREATE TABLE evento (
  id_evento         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo            VARCHAR(200) NOT NULL,
  descripcion       TEXT,
  tipo              VARCHAR(40) NOT NULL DEFAULT 'reunion',
  fecha             DATE NOT NULL,
  id_area           SMALLINT UNSIGNED NOT NULL,
  id_cliente        INT UNSIGNED NULL,
  organismo         VARCHAR(200) NULL,
  id_empleado       INT UNSIGNED NULL,
  id_tarea_origen   INT UNSIGNED NULL,
  asistencia        TEXT,
  resultado         TEXT,
  autoinscripcion   TINYINT(1) NOT NULL DEFAULT 0,
  publico           ENUM('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos',
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  creado_en         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evento_area    FOREIGN KEY (id_area)         REFERENCES area(id_area),
  CONSTRAINT fk_evento_cliente FOREIGN KEY (id_cliente)      REFERENCES cliente(id_cliente),
  CONSTRAINT fk_evento_emp     FOREIGN KEY (id_empleado)     REFERENCES empleado(id_empleado),
  CONSTRAINT fk_evento_tarea   FOREIGN KEY (id_tarea_origen) REFERENCES tarea(id_tarea)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evento_inscripcion (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_evento          INT UNSIGNED NOT NULL,
  id_cliente         INT UNSIGNED NOT NULL,
  estado             ENUM('inscrito','asistio','ausente') NOT NULL DEFAULT 'inscrito',
  fecha_inscripcion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_evins (id_evento, id_cliente),
  INDEX idx_evins_cliente (id_cliente, estado),
  CONSTRAINT fk_evins_evento  FOREIGN KEY (id_evento)  REFERENCES evento(id_evento) ON DELETE CASCADE,
  CONSTRAINT fk_evins_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evento_tarea (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_evento  INT UNSIGNED NOT NULL,
  id_tarea   INT UNSIGNED NOT NULL,
  CONSTRAINT fk_evtar_evento FOREIGN KEY (id_evento) REFERENCES evento(id_evento),
  CONSTRAINT fk_evtar_tarea  FOREIGN KEY (id_tarea)  REFERENCES tarea(id_tarea),
  CONSTRAINT uq_evtar UNIQUE (id_evento, id_tarea)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Agenda / informes / versión de esquema
-- ---------------------------------------------------------

CREATE TABLE turno_config (
  clave VARCHAR(40) NOT NULL PRIMARY KEY,
  valor VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crm_schema_version (
  clave VARCHAR(60) NOT NULL PRIMARY KEY,
  fecha DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Solicitudes de alta web (evaluación Coordinación general → emprendedor/empresa)
CREATE TABLE cliente_solicitud (
  id_solicitud INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  correo VARCHAR(150) NOT NULL,
  telefono VARCHAR(30) NULL,
  dni VARCHAR(15) NULL,
  cuil_cuit VARCHAR(13) NOT NULL,
  nombre_negocio VARCHAR(150) NULL,
  id_localidad SMALLINT UNSIGNED NOT NULL,
  tipo_autodeclarado ENUM('emprendedor','empresa') NOT NULL DEFAULT 'emprendedor',
  sector VARCHAR(80) NULL,
  sector_otro VARCHAR(120) NULL,
  anios_funcionamiento VARCHAR(40) NULL,
  personas_involucradas VARCHAR(40) NULL,
  personal_formal TINYINT(1) NOT NULL DEFAULT 0,
  producto_servicio TEXT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  estado ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  sugerencia_tipo ENUM('emprendedor','empresa') NULL,
  tipo_asignado ENUM('emprendedor','empresa') NULL,
  id_cliente INT UNSIGNED NULL,
  id_revisa INT UNSIGNED NULL,
  nota_revision TEXT NULL,
  fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion DATETIME NULL,
  INDEX idx_csol_estado (estado, fecha_solicitud),
  INDEX idx_csol_correo (correo),
  CONSTRAINT fk_csol_loc FOREIGN KEY (id_localidad) REFERENCES localidad(id_localidad),
  CONSTRAINT fk_csol_cli FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
  CONSTRAINT fk_csol_rev FOREIGN KEY (id_revisa) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cliente_aviso (
  id_aviso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  detalle TEXT NULL,
  leido TINYINT(1) NOT NULL DEFAULT 0,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_claviso_user (id_usuario, leido, fecha),
  CONSTRAINT fk_claviso_user FOREIGN KEY (id_usuario) REFERENCES usuario_cliente(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE informe_plantilla (
  id_plantilla  SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave         VARCHAR(40) NOT NULL,
  nombre        VARCHAR(120) NOT NULL,
  descripcion   VARCHAR(300) NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT uq_informe_plantilla UNIQUE (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE informe_publicado (
  id_informe       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_plantilla     SMALLINT UNSIGNED NOT NULL,
  titulo           VARCHAR(200) NOT NULL,
  filtros_json     TEXT NULL,
  resumen_json     TEXT NULL,
  id_empleado_de   INT UNSIGNED NOT NULL,
  id_area          SMALLINT UNSIGNED NULL,
  publicado        TINYINT(1) NOT NULL DEFAULT 1,
  fecha_desde      DATE NULL,
  fecha_hasta      DATE NULL,
  creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_infpub_plantilla FOREIGN KEY (id_plantilla)   REFERENCES informe_plantilla(id_plantilla),
  CONSTRAINT fk_infpub_empleado  FOREIGN KEY (id_empleado_de) REFERENCES empleado(id_empleado),
  CONSTRAINT fk_infpub_area      FOREIGN KEY (id_area)        REFERENCES area(id_area),
  INDEX idx_infpub_presidencia (publicado, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------
-- Datos de catálogo
-- ---------------------------------------------------------

INSERT INTO rol (clave, nombre) VALUES
  ('superadmin', 'Superadmin'),
  ('coordinador_general', 'Coordinador general'),
  ('coordinador', 'Coordinador de área'),
  ('empleado', 'Empleado'),
  ('presidencia', 'Presidencia'),
  ('admin_tareas', 'Administración de tareas');

-- Orden alfabético por nombre. Solo Administración como área nueva (sin Analista).
INSERT INTO area (clave, nombre, atiende_clientes, asignable_empleado) VALUES
  ('administracion', 'Administración',         0, 1),
  ('comercio',       'Comercio exterior',      1, 1),
  ('general',        'Coordinación general',   0, 0),
  ('emprendedores',  'Emprendedores',          1, 1),
  ('empresas',       'Empresas',               1, 1),
  ('marketing',      'Marketing',              1, 1),
  ('presidencia',    'Presidencia',            0, 0),
  ('recepcion',      'Recepción',              1, 1),
  ('rrhh',           'Recursos humanos',       0, 1),
  ('sistemas',       'Sistemas',               0, 1),
  ('vinculaciones',  'Vinculación académica',  1, 1);

INSERT INTO tipo_tarea (id_area, nombre)
SELECT id_area, 'General' FROM area WHERE clave NOT IN ('presidencia');

INSERT INTO turno_config (clave, valor) VALUES
  ('turnos_por_dia', '16'),
  ('hora_desde', '07:00'),
  ('hora_hasta', '19:30');

INSERT INTO informe_plantilla (clave, nombre, descripcion) VALUES
  ('actividades_tipo_localidad', 'Actividades por tipo y localidad', 'Ej. capacitaciones en Posadas'),
  ('actividades_tipo_cliente', 'Actividades por tipo de cliente', 'Emprendedor, empresa, municipalidad u organismo'),
  ('rondas_municipalidades', 'Rondas / actividades para municipalidades', 'Enfoque municipalidades'),
  ('clientes_por_tipo', 'Clientes por tipo', 'Conteo y detalle agrupado por tipo de cliente');

INSERT INTO crm_schema_version (clave, fecha) VALUES
  ('area_flags_v1', NOW()),
  ('turno_agenda_v1', NOW()),
  ('evento_portal_v1', NOW());

INSERT INTO provincia (nombre) VALUES
  ('Buenos Aires'), ('CABA'), ('Catamarca'), ('Chaco'), ('Chubut'),
  ('Córdoba'), ('Corrientes'), ('Entre Ríos'), ('Formosa'), ('Jujuy'),
  ('La Pampa'), ('La Rioja'), ('Mendoza'), ('Misiones'), ('Neuquén'),
  ('Río Negro'), ('Salta'), ('San Juan'), ('San Luis'), ('Santa Cruz'),
  ('Santa Fe'), ('Santiago del Estero'), ('Tierra del Fuego'), ('Tucumán');

INSERT INTO localidad (nombre, id_provincia)
SELECT nombre, (SELECT id_provincia FROM provincia WHERE nombre = 'Misiones')
FROM (
  SELECT 'Posadas' AS nombre UNION ALL
  SELECT 'Garupá' UNION ALL
  SELECT 'Fachinal' UNION ALL
  SELECT 'Apóstoles' UNION ALL
  SELECT 'Azara' UNION ALL
  SELECT 'San José' UNION ALL
  SELECT 'Tres Capones' UNION ALL
  SELECT 'Aristóbulo del Valle' UNION ALL
  SELECT 'Campo Grande' UNION ALL
  SELECT 'Campo Ramón' UNION ALL
  SELECT 'Dos de Mayo' UNION ALL
  SELECT 'Candelaria' UNION ALL
  SELECT 'Bonpland' UNION ALL
  SELECT 'Cerro Corá' UNION ALL
  SELECT 'Loreto' UNION ALL
  SELECT 'Santa Ana' UNION ALL
  SELECT 'Profundidad' UNION ALL
  SELECT 'Mártires' UNION ALL
  SELECT 'Concepción de la Sierra' UNION ALL
  SELECT 'Santa María' UNION ALL
  SELECT 'Eldorado' UNION ALL
  SELECT 'Puerto Piray' UNION ALL
  SELECT 'Colonia Victoria' UNION ALL
  SELECT 'Alicia' UNION ALL
  SELECT 'San Vicente' UNION ALL
  SELECT 'San Antonio' UNION ALL
  SELECT 'Bernardo de Irigoyen' UNION ALL
  SELECT 'Comandante Andresito' UNION ALL
  SELECT 'El Soberbio' UNION ALL
  SELECT 'Puerto Iguazú' UNION ALL
  SELECT 'Wanda' UNION ALL
  SELECT 'Puerto Libertad' UNION ALL
  SELECT 'Leandro N. Alem' UNION ALL
  SELECT 'Almafuerte' UNION ALL
  SELECT 'Cerro Azul' UNION ALL
  SELECT 'Gobernador Roca' UNION ALL
  SELECT 'Olegario V. Andrade' UNION ALL
  SELECT 'Panambí' UNION ALL
  SELECT 'Jardín América' UNION ALL
  SELECT 'Puerto Rico' UNION ALL
  SELECT 'Ruiz de Montoya' UNION ALL
  SELECT 'Capioví' UNION ALL
  SELECT 'Garuhapé' UNION ALL
  SELECT 'Montecarlo' UNION ALL
  SELECT 'Caraguatay' UNION ALL
  SELECT 'Oberá' UNION ALL
  SELECT 'Campo Viera' UNION ALL
  SELECT 'General Alvear' UNION ALL
  SELECT 'Los Helechos' UNION ALL
  SELECT 'San Ignacio' UNION ALL
  SELECT 'Corpus' UNION ALL
  SELECT 'Santo Pipó' UNION ALL
  SELECT 'Hipólito Yrigoyen' UNION ALL
  SELECT 'San Javier' UNION ALL
  SELECT 'Florentino Ameghino' UNION ALL
  SELECT 'San Pedro' UNION ALL
  SELECT 'Paraíso' UNION ALL
  SELECT 'Veinticinco de Mayo'
) AS loc;

-- Usuario bootstrap (clave: admin)
INSERT INTO empleado (
  nombres, apellido, cuil, dni, correo, fecha_ingreso,
  nombre_usuario, contrasena_hash, primer_acceso, id_rol, id_area, estado_laboral
) VALUES (
  'Super', 'Admin',
  '20999999990', '99999999', 'admin@ademi.com.ar', CURRENT_DATE,
  'superadmin',
  '$2y$12$v/ne1uFvsv1MtAp40XlFyeQTwy8f1aC6Yt3BwFlghv/dkZ4Sp01La',
  0,
  (SELECT id_rol  FROM rol  WHERE clave = 'superadmin'),
  (SELECT id_area FROM area WHERE clave = 'sistemas'),
  'activo'
);
