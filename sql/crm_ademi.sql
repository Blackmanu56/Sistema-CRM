-- =========================================================
-- CRM ADEMI - Modelo de base de datos
-- MySQL 8 / MariaDB 10.5+
-- Version 2: reestructuracion de clientes/representantes
-- =========================================================

CREATE DATABASE IF NOT EXISTS crm_ademi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE crm_ademi;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- Cargar areas, roles, tipos de tarea y localidades.
-- ---------------------------------------------------------

CREATE TABLE area (
  id_area     SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(30)  NOT NULL,
  nombre      VARCHAR(80)  NOT NULL,
  CONSTRAINT uq_area_clave UNIQUE (clave)
) ENGINE=InnoDB;

CREATE TABLE rol (
  id_rol      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(30)  NOT NULL,
  nombre      VARCHAR(80)  NOT NULL,
  CONSTRAINT uq_rol_clave UNIQUE (clave)
) ENGINE=InnoDB;

CREATE TABLE tipo_tarea (
  id_tipo     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_area     SMALLINT UNSIGNED NOT NULL,
  nombre      VARCHAR(80) NOT NULL,
  CONSTRAINT fk_tipo_tarea_area FOREIGN KEY (id_area) REFERENCES area(id_area),
  CONSTRAINT uq_tipo_tarea_area_nombre UNIQUE (id_area, nombre)
) ENGINE=InnoDB;

CREATE TABLE provincia (
  id_provincia SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(80) NOT NULL,
  CONSTRAINT uq_provincia_nombre UNIQUE (nombre)
) ENGINE=InnoDB;

CREATE TABLE localidad (
  id_localidad  SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80) NOT NULL,
  id_provincia  SMALLINT UNSIGNED NOT NULL,
  CONSTRAINT fk_localidad_provincia FOREIGN KEY (id_provincia) REFERENCES provincia(id_provincia),
  CONSTRAINT uq_localidad UNIQUE (nombre, id_provincia)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Personas: empleados
-- ---------------------------------------------------------

CREATE TABLE empleado (
  id_empleado     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombres         VARCHAR(80)  NOT NULL,
  apellido        VARCHAR(80)  NOT NULL,
  cuil            CHAR(11)     NOT NULL,
  dni             VARCHAR(15)  NOT NULL,
  correo          VARCHAR(150) NOT NULL,
  telefono        VARCHAR(30),
  direccion       VARCHAR(200),
  fecha_ingreso   DATE NOT NULL,
  fecha_alta      DATE NOT NULL DEFAULT (CURRENT_DATE),
  nombre_usuario  VARCHAR(50)  NOT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  primer_acceso   TINYINT(1) NOT NULL DEFAULT 1,
  id_rol          SMALLINT UNSIGNED NOT NULL,
  id_area         SMALLINT UNSIGNED NOT NULL,
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_empleado_cuil    UNIQUE (cuil),
  CONSTRAINT uq_empleado_dni     UNIQUE (dni),
  CONSTRAINT uq_empleado_correo  UNIQUE (correo),
  CONSTRAINT uq_empleado_usuario UNIQUE (nombre_usuario),
  CONSTRAINT fk_empleado_rol  FOREIGN KEY (id_rol)  REFERENCES rol(id_rol),
  CONSTRAINT fk_empleado_area FOREIGN KEY (id_area) REFERENCES area(id_area)
) ENGINE=InnoDB;

-- Rol adicional por empleado. Son exepciones a los roles fijos de la tabla empleado. Ejemplo: coordinador general que además coordina un área puntual. ULISES
CREATE TABLE empleado_rol_adicional (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_empleado   INT UNSIGNED NOT NULL,
  id_rol        SMALLINT UNSIGNED NOT NULL,
  id_area       SMALLINT UNSIGNED NULL,
  fecha_desde   DATE NOT NULL,
  fecha_hasta   DATE NULL,
  CONSTRAINT fk_rol_adic_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
  CONSTRAINT fk_rol_adic_rol      FOREIGN KEY (id_rol)      REFERENCES rol(id_rol),
  CONSTRAINT fk_rol_adic_area     FOREIGN KEY (id_area)     REFERENCES area(id_area),
  CONSTRAINT chk_rol_adic_fechas  CHECK (fecha_hasta IS NULL OR fecha_hasta >= fecha_desde)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Clientes
-- ---------------------------------------------------------

CREATE TABLE usuario_cliente (
  id_usuario      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre          VARCHAR(150) NOT NULL,
  correo          VARCHAR(150) NOT NULL,
  contrasena_hash VARCHAR(255) NOT NULL,
  primer_acceso   TINYINT(1) NOT NULL DEFAULT 1,
  telefono        VARCHAR(30),
  activo          TINYINT(1) NOT NULL DEFAULT 1,
  creado_en       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_usuario_cliente_correo UNIQUE (correo)
) ENGINE=InnoDB;

CREATE TABLE cliente (
  id_cliente       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_cliente     ENUM('persona','empresa') NOT NULL,
  nombre           VARCHAR(150) NOT NULL,
  correo           VARCHAR(150) NULL,
  telefono         VARCHAR(30),
  id_localidad     SMALLINT UNSIGNED NOT NULL,
  direccion        VARCHAR(200),
  cuil_cuit        VARCHAR(13) NOT NULL,
  id_area_afectada SMALLINT UNSIGNED NOT NULL,
  fecha_alta       DATE NOT NULL DEFAULT (CURRENT_DATE),
  activo           TINYINT(1) NOT NULL DEFAULT 1,
  creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_cliente_cuil_cuit UNIQUE (cuil_cuit),
  CONSTRAINT fk_cliente_localidad FOREIGN KEY (id_localidad)     REFERENCES localidad(id_localidad),
  CONSTRAINT fk_cliente_area      FOREIGN KEY (id_area_afectada) REFERENCES area(id_area)
) ENGINE=InnoDB;

-- Datos exclusivos de clientes tipo persona (1 a 1 con cliente)
CREATE TABLE cliente_persona (
  id_cliente  INT UNSIGNED PRIMARY KEY,
  genero      ENUM('femenino','masculino','otro') NULL,
  CONSTRAINT fk_clipersona_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB;

-- Datos exclusivos de clientes tipo empresa (1 a 1 con cliente)
CREATE TABLE cliente_empresa (
  id_cliente    INT UNSIGNED PRIMARY KEY,
  razon_social  VARCHAR(150) NULL,
  CONSTRAINT fk_cliempresa_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tareas
-- ---------------------------------------------------------

CREATE TABLE tarea (
  id_tarea         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titulo           VARCHAR(200) NOT NULL,
  descripcion      TEXT,
  id_area          SMALLINT UNSIGNED NOT NULL,
  id_tipo          INT UNSIGNED NOT NULL,
  prioridad        ENUM('urgente','alta','media','baja') NOT NULL DEFAULT 'media',
  estado           ENUM('no_iniciada','en_proceso','pausada','terminada') NOT NULL DEFAULT 'no_iniciada',
  fecha_inicio     DATE NOT NULL,
  fecha_cierre     DATE NOT NULL,
  avance           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  id_cliente       INT UNSIGNED NULL,
  id_usuario_actor INT UNSIGNED NULL,
  id_tarea_padre   INT UNSIGNED NULL,
  uid              VARCHAR(20),
  creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tarea_area        FOREIGN KEY (id_area)          REFERENCES area(id_area),
  CONSTRAINT fk_tarea_tipo        FOREIGN KEY (id_tipo)          REFERENCES tipo_tarea(id_tipo),
  CONSTRAINT fk_tarea_cliente     FOREIGN KEY (id_cliente)       REFERENCES cliente(id_cliente),
  CONSTRAINT fk_tarea_usuario_act FOREIGN KEY (id_usuario_actor) REFERENCES usuario_cliente(id_usuario),
  CONSTRAINT fk_tarea_padre       FOREIGN KEY (id_tarea_padre)   REFERENCES tarea(id_tarea),
  CONSTRAINT chk_tarea_avance     CHECK (avance BETWEEN 0 AND 100),
  CONSTRAINT chk_tarea_fechas     CHECK (fecha_cierre >= fecha_inicio)
) ENGINE=InnoDB;

CREATE TABLE tarea_responsable (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea      INT UNSIGNED NOT NULL,
  id_empleado   INT UNSIGNED NOT NULL,
  es_principal  TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_tresp_tarea    FOREIGN KEY (id_tarea)    REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tresp_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
  CONSTRAINT uq_tresp_tarea_empleado UNIQUE (id_tarea, id_empleado)
) ENGINE=InnoDB;

CREATE TABLE tarea_inicio (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea            INT UNSIGNED NOT NULL,
  id_empleado         INT UNSIGNED NOT NULL,
  fecha_click_inicio  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tini_tarea    FOREIGN KEY (id_tarea)    REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tini_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado),
  CONSTRAINT uq_tini_tarea_empleado UNIQUE (id_tarea, id_empleado)
) ENGINE=InnoDB;

CREATE TABLE tarea_enlace (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea     INT UNSIGNED NOT NULL,
  url          VARCHAR(500) NOT NULL,
  fecha_carga  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tenl_tarea FOREIGN KEY (id_tarea) REFERENCES tarea(id_tarea)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

CREATE TABLE actividad (
  id_actividad    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea        INT UNSIGNED NOT NULL,
  id_empleado     INT UNSIGNED NULL,
  tipo            ENUM('creada','proceso','completada','vencida') NOT NULL,
  descripcion     VARCHAR(300),
  fecha_actividad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_act_tarea    FOREIGN KEY (id_tarea)    REFERENCES tarea(id_tarea),
  CONSTRAINT fk_act_empleado FOREIGN KEY (id_empleado) REFERENCES empleado(id_empleado)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;


-- ---------------------------------------------------------
-- Datos fijos de catálogo
-- ---------------------------------------------------------

INSERT INTO rol (clave, nombre) VALUES
  ('superadmin', 'Superadmin'),
  ('coordinador_general', 'Coordinador general'),
  ('coordinador', 'Coordinador de área'),
  ('empleado', 'Empleado');

INSERT INTO area (clave, nombre) VALUES
  ('recepcion', 'Recepción'),
  ('marketing', 'Marketing'),
  ('empresas', 'Coordinación de empresas'),
  ('emprendedores', 'Coordinación de emprendedores'),
  ('comercio', 'Coordinación de comercio exterior'),
  ('rrhh', 'Recursos humanos'),
  ('vinculaciones', 'Coordinación de vinculaciones'),
  ('sistemas', 'Sistemas'),
  ('general', 'Coordinación general');

-- =========================================================
-- Provincias
-- =========================================================

INSERT INTO provincia (nombre) VALUES
  ('Buenos Aires'), ('CABA'), ('Catamarca'), ('Chaco'), ('Chubut'),
  ('Córdoba'), ('Corrientes'), ('Entre Ríos'), ('Formosa'), ('Jujuy'),
  ('La Pampa'), ('La Rioja'), ('Mendoza'), ('Misiones'), ('Neuquén'),
  ('Río Negro'), ('Salta'), ('San Juan'), ('San Luis'), ('Santa Cruz'),
  ('Santa Fe'), ('Santiago del Estero'), ('Tierra del Fuego'), ('Tucumán');

-- =========================================================
-- Localidades de Misiones (listado no exhaustivo — ver nota)
-- =========================================================

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

-- Primer usuario: superadmin (la clave va hasheada en contrasena_hash).
INSERT INTO empleado (
  nombres, apellido, cuil, dni, correo, fecha_ingreso,
  nombre_usuario, contrasena_hash, id_rol, id_area
) VALUES (
  'Super', 'Admin',
  '20999999990', '99999999', 'admin@ademi.com.ar', CURRENT_DATE,
  'superadmin',
  '$2b$12$v/ne1uFvsv1MtAp40XlFyeQTwy8f1aC6Yt3BwFlghv/dkZ4Sp01La',
  (SELECT id_rol  FROM rol  WHERE clave = 'superadmin'),
  (SELECT id_area FROM area WHERE clave = 'sistemas')
);
