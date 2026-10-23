-- ============================================================================
-- CRM ADEMI — INSTALACIÓN COMPLETA DESDE CERO (HOSTING)
-- Archivo: sql/crm_instalacion_completa.sql
-- Base de datos destino: [nombre_base_de_datos_hosting]
-- Motor: MariaDB 10.4+ / MySQL 8.0+
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
--
-- Contenido:
-- 1. Estructura completa de las 51 tablas del CRM en orden de dependencias.
-- 2. Restricciones de integridad referencial (claves foráneas) e índices.
-- 3. Restricciones CHECK y columnas generadas (empresa_seguimiento.hito_clave).
-- 4. Catálogos maestros indispensables (roles, áreas, provincias, localidades,
--    tipos de tareas, configuración de turnos, plantillas de informe, matriz
--    de permisos de informes por área y registro de versiones del esquema).
-- 5. Procedimiento seguro para la creación del primer Superadministrador.
--
-- INSTRUCCIONES DE USO EN HOSTING:
-- 1. Cree una base de datos vacía con juego de caracteres utf8mb4_unicode_ci.
-- 2. Importe este archivo mediante phpMyAdmin o línea de comandos:
--      mysql -h [host] -u [usuario] -p [base_de_datos] < crm_instalacion_completa.sql
-- 3. Cree el usuario Superadministrador siguiendo el apartado final documentado.
-- ============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- ----------------------------------------------------------------------------
-- SECCIÓN 1: ESTRUCTURA DE TABLAS (51 TABLAS EN ORDEN TOPOLÓGICO)
-- ----------------------------------------------------------------------------
-- Tabla 1/51: `area`
CREATE TABLE IF NOT EXISTS `area` (\n  `id_area` smallint(5) unsigned NOT NULL AUTO_INCREMENT,\n  `clave` varchar(30) NOT NULL,\n  `nombre` varchar(80) NOT NULL,\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  `atiende_clientes` tinyint(1) NOT NULL DEFAULT 0,\n  `puede_clientes` tinyint(1) NOT NULL DEFAULT 0,\n  `recibe_derivaciones` tinyint(1) NOT NULL DEFAULT 0,\n  `recibe_turnos` tinyint(1) NOT NULL DEFAULT 0,\n  `puede_atenciones` tinyint(1) NOT NULL DEFAULT 1,\n  `informes_habilitados` tinyint(1) NOT NULL DEFAULT 1,\n  `asignable_empleado` tinyint(1) NOT NULL DEFAULT 1,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_area`),\n  UNIQUE KEY `uq_area_clave` (`clave`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 2/51: `crm_schema_version`
CREATE TABLE IF NOT EXISTS `crm_schema_version` (\n  `clave` varchar(60) NOT NULL,\n  `fecha` datetime NOT NULL,\n  PRIMARY KEY (`clave`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 3/51: `dominio`
CREATE TABLE IF NOT EXISTS `dominio` (\n  `id_dominio` int(11) NOT NULL AUTO_INCREMENT,\n  `nombre` varchar(150) NOT NULL,\n  `url` varchar(255) DEFAULT NULL,\n  `proveedor` varchar(100) DEFAULT NULL,\n  `responsable` varchar(100) DEFAULT NULL,\n  `fecha_inicio` date DEFAULT NULL,\n  `fecha_vencimiento` date NOT NULL,\n  `notas` text DEFAULT NULL,\n  `activo` tinyint(1) DEFAULT 1,\n  `creado_en` datetime DEFAULT current_timestamp(),\n  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`id_dominio`),\n  UNIQUE KEY `idx_nombre_activo` (`nombre`,`activo`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla 4/51: `dominio_renovacion`
CREATE TABLE IF NOT EXISTS `dominio_renovacion` (\n  `id_renovacion` int(11) NOT NULL AUTO_INCREMENT,\n  `id_dominio` int(11) NOT NULL,\n  `fecha_renovacion` date NOT NULL,\n  `fecha_vencimiento_anterior` date NOT NULL,\n  `fecha_vencimiento_nueva` date NOT NULL,\n  `id_usuario` int(11) DEFAULT NULL,\n  `notas` text DEFAULT NULL,\n  `creado_en` datetime DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_renovacion`),\n  KEY `idx_dominio` (`id_dominio`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabla 5/51: `informe_plantilla`
CREATE TABLE IF NOT EXISTS `informe_plantilla` (\n  `id_plantilla` smallint(5) unsigned NOT NULL AUTO_INCREMENT,\n  `clave` varchar(40) NOT NULL,\n  `nombre` varchar(120) NOT NULL,\n  `descripcion` varchar(300) DEFAULT NULL,\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  PRIMARY KEY (`id_plantilla`),\n  UNIQUE KEY `uq_informe_plantilla` (`clave`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 6/51: `presentacion_registro_interno`
CREATE TABLE IF NOT EXISTS `presentacion_registro_interno` (\n  `modulo` varchar(40) NOT NULL,\n  `id_registro` int(10) unsigned NOT NULL,\n  `clasificacion` enum('simulado') NOT NULL DEFAULT 'simulado',\n  `nota` varchar(255) NOT NULL,\n  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  PRIMARY KEY (`modulo`,`id_registro`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Control interno de datos simulados de presentacion; no es utilizado por la interfaz del CRM';

-- Tabla 7/51: `programa`
CREATE TABLE IF NOT EXISTS `programa` (\n  `id_programa` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `nombre` varchar(120) NOT NULL,\n  `descripcion` text DEFAULT NULL,\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_programa`),\n  UNIQUE KEY `uq_programa_nombre` (`nombre`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 8/51: `provincia`
CREATE TABLE IF NOT EXISTS `provincia` (\n  `id_provincia` smallint(5) unsigned NOT NULL AUTO_INCREMENT,\n  `nombre` varchar(80) NOT NULL,\n  PRIMARY KEY (`id_provincia`),\n  UNIQUE KEY `uq_provincia_nombre` (`nombre`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 9/51: `rol`
CREATE TABLE IF NOT EXISTS `rol` (\n  `id_rol` smallint(5) unsigned NOT NULL AUTO_INCREMENT,\n  `clave` varchar(30) NOT NULL,\n  `nombre` varchar(80) NOT NULL,\n  PRIMARY KEY (`id_rol`),\n  UNIQUE KEY `uq_rol_clave` (`clave`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 10/51: `sala_reunion`
CREATE TABLE IF NOT EXISTS `sala_reunion` (\n  `id_sala` int(11) NOT NULL AUTO_INCREMENT,\n  `nombre` varchar(120) NOT NULL,\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  PRIMARY KEY (`id_sala`),\n  UNIQUE KEY `uq_sala_reunion_nombre` (`nombre`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 11/51: `sala_reunion_reserva`
CREATE TABLE IF NOT EXISTS `sala_reunion_reserva` (\n  `id_reserva` int(11) NOT NULL AUTO_INCREMENT,\n  `id_sala` int(11) NOT NULL,\n  `id_area` int(11) NOT NULL,\n  `id_empleado` int(11) NOT NULL,\n  `fecha` date NOT NULL,\n  `hora_inicio` time NOT NULL,\n  `hora_fin` time NOT NULL,\n  `motivo` varchar(500) NOT NULL,\n  `estado` varchar(20) NOT NULL DEFAULT 'activa',\n  `creado_en` datetime NOT NULL,\n  `actualizado_en` datetime NOT NULL,\n  PRIMARY KEY (`id_reserva`),\n  KEY `idx_sala_reserva_agenda` (`id_sala`,`fecha`,`estado`,`hora_inicio`,`hora_fin`),\n  KEY `idx_sala_reserva_area` (`id_area`,`fecha`),\n  KEY `idx_sala_reserva_empleado` (`id_empleado`,`fecha`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 12/51: `turno_config`
CREATE TABLE IF NOT EXISTS `turno_config` (\n  `clave` varchar(40) NOT NULL,\n  `valor` varchar(120) NOT NULL,\n  PRIMARY KEY (`clave`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 13/51: `usuario_cliente`
CREATE TABLE IF NOT EXISTS `usuario_cliente` (\n  `id_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `nombre` varchar(150) NOT NULL,\n  `correo` varchar(150) NOT NULL,\n  `contrasena_hash` varchar(255) NOT NULL,\n  `primer_acceso` tinyint(1) NOT NULL DEFAULT 1,\n  `dni` varchar(15) DEFAULT NULL,\n  `telefono` varchar(30) DEFAULT NULL,\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_usuario`),\n  UNIQUE KEY `uq_usuario_cliente_correo` (`correo`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 14/51: `area_informe`
CREATE TABLE IF NOT EXISTS `area_informe` (\n  `id_area` smallint(5) unsigned NOT NULL,\n  `codigo_informe` varchar(32) NOT NULL,\n  `habilitado` tinyint(1) NOT NULL DEFAULT 0,\n  PRIMARY KEY (`id_area`,`codigo_informe`),\n  CONSTRAINT `fk_area_informe_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 15/51: `tipo_tarea`
CREATE TABLE IF NOT EXISTS `tipo_tarea` (\n  `id_tipo` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `nombre` varchar(80) NOT NULL,\n  PRIMARY KEY (`id_tipo`),\n  UNIQUE KEY `uq_tipo_tarea_area_nombre` (`id_area`,`nombre`),\n  CONSTRAINT `fk_tipo_tarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 16/51: `localidad`
CREATE TABLE IF NOT EXISTS `localidad` (\n  `id_localidad` smallint(5) unsigned NOT NULL AUTO_INCREMENT,\n  `nombre` varchar(80) NOT NULL,\n  `id_provincia` smallint(5) unsigned NOT NULL,\n  `latitud` decimal(10,7) DEFAULT NULL,\n  `longitud` decimal(10,7) DEFAULT NULL,\n  `poligono_geojson` longtext DEFAULT NULL,\n  PRIMARY KEY (`id_localidad`),\n  UNIQUE KEY `uq_localidad` (`nombre`,`id_provincia`),\n  KEY `fk_localidad_provincia` (`id_provincia`),\n  CONSTRAINT `fk_localidad_provincia` FOREIGN KEY (`id_provincia`) REFERENCES `provincia` (`id_provincia`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 17/51: `empleado`
CREATE TABLE IF NOT EXISTS `empleado` (\n  `id_empleado` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `foto_perfil_key` varchar(64) DEFAULT NULL,\n  `nombres` varchar(80) NOT NULL,\n  `apellido` varchar(80) NOT NULL,\n  `cuil` char(11) NOT NULL,\n  `dni` varchar(15) NOT NULL,\n  `fecha_nacimiento` date DEFAULT NULL,\n  `correo` varchar(150) NOT NULL,\n  `telefono` varchar(30) DEFAULT NULL,\n  `telefono_alt` varchar(30) DEFAULT NULL,\n  `direccion` varchar(200) DEFAULT NULL,\n  `estado_civil` varchar(40) DEFAULT NULL,\n  `localidad_domicilio` varchar(100) DEFAULT NULL,\n  `provincia_domicilio` varchar(80) DEFAULT NULL,\n  `puesto` varchar(120) DEFAULT NULL,\n  `tipo_vinculo` varchar(80) DEFAULT NULL,\n  `estado_laboral` varchar(40) NOT NULL DEFAULT 'activo',\n  `fecha_baja` date DEFAULT NULL,\n  `motivo_baja` varchar(200) DEFAULT NULL,\n  `modalidad` varchar(40) DEFAULT NULL,\n  `horario_jornada` varchar(120) DEFAULT NULL,\n  `condicion_fiscal` varchar(60) DEFAULT NULL,\n  `cuit_facturacion` varchar(13) DEFAULT NULL,\n  `fecha_ingreso` date NOT NULL,\n  `fecha_alta` date NOT NULL DEFAULT curdate(),\n  `nombre_usuario` varchar(50) NOT NULL,\n  `contrasena_hash` varchar(255) NOT NULL,\n  `primer_acceso` tinyint(1) NOT NULL DEFAULT 1,\n  `id_rol` smallint(5) unsigned NOT NULL,\n  `id_area` smallint(5) unsigned DEFAULT NULL,\n  `id_responsable` int(10) unsigned DEFAULT NULL,\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_empleado`),\n  UNIQUE KEY `uq_empleado_cuil` (`cuil`),\n  UNIQUE KEY `uq_empleado_dni` (`dni`),\n  UNIQUE KEY `uq_empleado_correo` (`correo`),\n  UNIQUE KEY `uq_empleado_usuario` (`nombre_usuario`),\n  KEY `fk_empleado_rol` (`id_rol`),\n  KEY `fk_empleado_area` (`id_area`),\n  CONSTRAINT `fk_empleado_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_empleado_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 18/51: `cliente_aviso`
CREATE TABLE IF NOT EXISTS `cliente_aviso` (\n  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_usuario` int(10) unsigned NOT NULL,\n  `titulo` varchar(200) NOT NULL,\n  `detalle` text DEFAULT NULL,\n  `leido` tinyint(1) NOT NULL DEFAULT 0,\n  `fecha` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_aviso`),\n  KEY `idx_claviso_user` (`id_usuario`,`leido`,`fecha`),\n  CONSTRAINT `fk_claviso_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_cliente` (`id_usuario`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 19/51: `eje`
CREATE TABLE IF NOT EXISTS `eje` (\n  `id_eje` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tipo` int(10) unsigned NOT NULL,\n  `nombre` varchar(80) NOT NULL,\n  PRIMARY KEY (`id_eje`),\n  UNIQUE KEY `uq_eje_tipo_nombre` (`id_tipo`,`nombre`),\n  CONSTRAINT `fk_eje_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 20/51: `cliente`
CREATE TABLE IF NOT EXISTS `cliente` (\n  `id_cliente` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `tipo_cliente` enum('emprendedor','empresa','municipalidad','organismo') NOT NULL,\n  `nombre` varchar(150) NOT NULL,\n  `correo` varchar(150) DEFAULT NULL,\n  `telefono` varchar(30) DEFAULT NULL,\n  `id_localidad` smallint(5) unsigned NOT NULL,\n  `direccion` varchar(200) DEFAULT NULL,\n  `cuil_cuit` varchar(13) NOT NULL,\n  `id_area_afectada` smallint(5) unsigned DEFAULT NULL,\n  `id_empleado_alta` int(10) unsigned DEFAULT NULL,\n  `origen_contacto` enum('web','presencial','whatsapp','redes') DEFAULT NULL,\n  `fecha_alta` date NOT NULL DEFAULT curdate(),\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_cliente`),\n  UNIQUE KEY `uq_cliente_cuil_cuit` (`cuil_cuit`),\n  KEY `fk_cliente_localidad` (`id_localidad`),\n  KEY `fk_cliente_area` (`id_area_afectada`),\n  KEY `idx_cliente_tipo` (`tipo_cliente`),\n  KEY `idx_cliente_tipo_activo` (`tipo_cliente`,`activo`),\n  KEY `fk_cliente_empleado_alta` (`id_empleado_alta`),\n  CONSTRAINT `fk_cliente_area` FOREIGN KEY (`id_area_afectada`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_cliente_empleado_alta` FOREIGN KEY (`id_empleado_alta`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL,\n  CONSTRAINT `fk_cliente_localidad` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 21/51: `empleado_documento_legajo`
CREATE TABLE IF NOT EXISTS `empleado_documento_legajo` (\n  `id_documento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `id_empleado` int(10) unsigned NOT NULL,\n  `categoria` varchar(40) NOT NULL,\n  `nombre_original` varchar(255) NOT NULL,\n  `archivo_key` varchar(64) NOT NULL,\n  `descripcion` text DEFAULT NULL,\n  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),\n  `id_usuario_carga` int(10) unsigned NOT NULL,\n  PRIMARY KEY (`id_documento`),\n  UNIQUE KEY `uq_empleado_documento_legajo_key` (`archivo_key`),\n  KEY `idx_empleado_documento_legajo_fecha` (`id_empleado`,`fecha_carga`),\n  KEY `idx_empleado_documento_legajo_cargado_por` (`id_usuario_carga`),\n  CONSTRAINT `fk_empleado_documento_legajo_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_empleado_documento_legajo_usuario` FOREIGN KEY (`id_usuario_carga`) REFERENCES `empleado` (`id_empleado`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 22/51: `empleado_rol_adicional`
CREATE TABLE IF NOT EXISTS `empleado_rol_adicional` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_empleado` int(10) unsigned NOT NULL,\n  `id_rol` smallint(5) unsigned NOT NULL,\n  `id_area` smallint(5) unsigned DEFAULT NULL,\n  `fecha_desde` date NOT NULL DEFAULT curdate(),\n  `fecha_hasta` date DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `fk_rol_adic_rol` (`id_rol`),\n  KEY `fk_rol_adic_area` (`id_area`),\n  KEY `idx_rol_adic_activo` (`id_empleado`,`fecha_hasta`),\n  CONSTRAINT `fk_rol_adic_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_rol_adic_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_rol_adic_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`),\n  CONSTRAINT `chk_rol_adic_fechas` CHECK (`fecha_hasta` is null or `fecha_hasta` >= `fecha_desde`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 23/51: `informe_publicado`
CREATE TABLE IF NOT EXISTS `informe_publicado` (\n  `id_informe` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_plantilla` smallint(5) unsigned NOT NULL,\n  `titulo` varchar(200) NOT NULL,\n  `filtros_json` text DEFAULT NULL,\n  `resumen_json` text DEFAULT NULL,\n  `id_empleado_de` int(10) unsigned NOT NULL,\n  `id_area` smallint(5) unsigned DEFAULT NULL,\n  `publicado` tinyint(1) NOT NULL DEFAULT 1,\n  `fecha_desde` date DEFAULT NULL,\n  `fecha_hasta` date DEFAULT NULL,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_informe`),\n  KEY `fk_infpub_plantilla` (`id_plantilla`),\n  KEY `fk_infpub_empleado` (`id_empleado_de`),\n  KEY `fk_infpub_area` (`id_area`),\n  KEY `idx_infpub_presidencia` (`publicado`,`creado_en`),\n  CONSTRAINT `fk_infpub_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_infpub_empleado` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_infpub_plantilla` FOREIGN KEY (`id_plantilla`) REFERENCES `informe_plantilla` (`id_plantilla`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 24/51: `programa_item`
CREATE TABLE IF NOT EXISTS `programa_item` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_programa` int(10) unsigned NOT NULL,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `id_tipo` int(10) unsigned DEFAULT NULL,\n  `id_eje` int(10) unsigned DEFAULT NULL,\n  `titulo` varchar(200) NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `fk_progitem_prog` (`id_programa`),\n  KEY `fk_progitem_area` (`id_area`),\n  KEY `fk_progitem_tipo` (`id_tipo`),\n  KEY `fk_progitem_eje` (`id_eje`),\n  CONSTRAINT `fk_progitem_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_progitem_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),\n  CONSTRAINT `fk_progitem_prog` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id_programa`),\n  CONSTRAINT `fk_progitem_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 25/51: `tarea_modelo`
CREATE TABLE IF NOT EXISTS `tarea_modelo` (\n  `id_modelo` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `id_tipo` int(10) unsigned NOT NULL,\n  `id_eje` int(10) unsigned DEFAULT NULL,\n  `titulo` varchar(200) NOT NULL,\n  PRIMARY KEY (`id_modelo`),\n  KEY `fk_tmod_area` (`id_area`),\n  KEY `fk_tmod_tipo` (`id_tipo`),\n  KEY `fk_tmod_eje` (`id_eje`),\n  CONSTRAINT `fk_tmod_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_tmod_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),\n  CONSTRAINT `fk_tmod_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 26/51: `atencion`
CREATE TABLE IF NOT EXISTS `atencion` (\n  `id_atencion` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_cliente` int(10) unsigned NOT NULL,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `id_area_origen` smallint(5) unsigned DEFAULT NULL,\n  `id_empleado` int(10) unsigned DEFAULT NULL,\n  `id_empleado_origen` int(10) unsigned DEFAULT NULL,\n  `motivo` enum('consulta','documentacion','seguimiento','tramite','otro') NOT NULL DEFAULT 'consulta',\n  `canal` enum('web','presencial','whatsapp','redes') NOT NULL DEFAULT 'presencial',\n  `detalle` text DEFAULT NULL,\n  `estado` enum('pendiente','abierta','en_atencion','cerrada','derivada','cancelada') NOT NULL DEFAULT 'abierta',\n  `id_atencion_origen` int(10) unsigned DEFAULT NULL,\n  `fecha` datetime NOT NULL DEFAULT current_timestamp(),\n  `fecha_turno` datetime DEFAULT NULL,\n  `tipo_ingreso` enum('turno','sin_turno') DEFAULT NULL,\n  `fecha_derivacion` datetime DEFAULT NULL,\n  PRIMARY KEY (`id_atencion`),\n  KEY `fk_aten_cliente` (`id_cliente`),\n  KEY `fk_aten_empleado` (`id_empleado`),\n  KEY `fk_aten_origen` (`id_atencion_origen`),\n  KEY `idx_aten_estado_fecha` (`estado`,`fecha_turno`),\n  KEY `idx_aten_area_estado` (`id_area`,`estado`),\n  KEY `fk_atencion_area_origen` (`id_area_origen`),\n  KEY `fk_atencion_empleado_origen` (`id_empleado_origen`),\n  CONSTRAINT `fk_aten_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_aten_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_aten_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_aten_origen` FOREIGN KEY (`id_atencion_origen`) REFERENCES `atencion` (`id_atencion`),\n  CONSTRAINT `fk_atencion_area_origen` FOREIGN KEY (`id_area_origen`) REFERENCES `area` (`id_area`) ON DELETE SET NULL,\n  CONSTRAINT `fk_atencion_empleado_origen` FOREIGN KEY (`id_empleado_origen`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 27/51: `cliente_area`
CREATE TABLE IF NOT EXISTS `cliente_area` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_cliente` int(10) unsigned NOT NULL,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `fecha_desde` date NOT NULL DEFAULT curdate(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_cliarea` (`id_cliente`,`id_area`),\n  KEY `fk_cliarea_area` (`id_area`),\n  CONSTRAINT `fk_cliarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_cliarea_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 28/51: `cliente_documento_legajo`
CREATE TABLE IF NOT EXISTS `cliente_documento_legajo` (\n  `id_documento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `id_cliente` int(10) unsigned NOT NULL,\n  `categoria` varchar(40) NOT NULL,\n  `nombre_original` varchar(255) NOT NULL,\n  `archivo_key` varchar(40) NOT NULL,\n  `mime_type` varchar(127) NOT NULL,\n  `tamano_bytes` bigint(20) unsigned NOT NULL,\n  `descripcion` text DEFAULT NULL,\n  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),\n  `id_empleado_carga` int(10) unsigned DEFAULT NULL,\n  PRIMARY KEY (`id_documento`),\n  UNIQUE KEY `uq_cliente_documento_legajo_key` (`archivo_key`),\n  KEY `idx_cliente_documento_legajo_cliente_fecha` (`id_cliente`,`fecha_carga`),\n  KEY `idx_cliente_documento_legajo_cargado_por` (`id_empleado_carga`),\n  CONSTRAINT `fk_cliente_documento_legajo_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_cliente_documento_legajo_empleado` FOREIGN KEY (`id_empleado_carga`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 29/51: `cliente_empresa`
CREATE TABLE IF NOT EXISTS `cliente_empresa` (\n  `id_cliente` int(10) unsigned NOT NULL,\n  `razon_social` varchar(150) DEFAULT NULL,\n  PRIMARY KEY (`id_cliente`),\n  CONSTRAINT `fk_cliempresa_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 30/51: `cliente_persona`
CREATE TABLE IF NOT EXISTS `cliente_persona` (\n  `id_cliente` int(10) unsigned NOT NULL,\n  `genero` enum('femenino','masculino','otro') DEFAULT NULL,\n  `dni` varchar(15) DEFAULT NULL,\n  PRIMARY KEY (`id_cliente`),\n  CONSTRAINT `fk_clipersona_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 31/51: `cliente_programa`
CREATE TABLE IF NOT EXISTS `cliente_programa` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_cliente` int(10) unsigned NOT NULL,\n  `id_programa` int(10) unsigned NOT NULL,\n  `fecha_desde` date NOT NULL DEFAULT curdate(),\n  `fecha_hasta` date DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `fk_cliprog_prog` (`id_programa`),\n  KEY `idx_cliprog_activo` (`id_cliente`,`id_programa`,`fecha_hasta`),\n  CONSTRAINT `fk_cliprog_cli` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_cliprog_prog` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id_programa`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 32/51: `cliente_solicitud`
CREATE TABLE IF NOT EXISTS `cliente_solicitud` (\n  `id_solicitud` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `nombre` varchar(150) NOT NULL,\n  `correo` varchar(150) NOT NULL,\n  `telefono` varchar(30) DEFAULT NULL,\n  `dni` varchar(15) DEFAULT NULL,\n  `cuil_cuit` varchar(13) NOT NULL,\n  `nombre_negocio` varchar(150) DEFAULT NULL,\n  `id_localidad` smallint(5) unsigned NOT NULL,\n  `tipo_autodeclarado` enum('emprendedor','empresa') NOT NULL DEFAULT 'emprendedor',\n  `sector` varchar(80) DEFAULT NULL,\n  `sector_otro` varchar(120) DEFAULT NULL,\n  `anios_funcionamiento` varchar(40) DEFAULT NULL,\n  `personas_involucradas` varchar(40) DEFAULT NULL,\n  `personal_formal` tinyint(1) NOT NULL DEFAULT 0,\n  `producto_servicio` text DEFAULT NULL,\n  `contrasena_hash` varchar(255) NOT NULL,\n  `estado` enum('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',\n  `sugerencia_tipo` enum('emprendedor','empresa') DEFAULT NULL,\n  `tipo_asignado` enum('emprendedor','empresa') DEFAULT NULL,\n  `id_cliente` int(10) unsigned DEFAULT NULL,\n  `id_revisa` int(10) unsigned DEFAULT NULL,\n  `nota_revision` text DEFAULT NULL,\n  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),\n  `fecha_resolucion` datetime DEFAULT NULL,\n  PRIMARY KEY (`id_solicitud`),\n  KEY `idx_csol_estado` (`estado`,`fecha_solicitud`),\n  KEY `idx_csol_correo` (`correo`),\n  KEY `fk_csol_loc` (`id_localidad`),\n  KEY `fk_csol_cli` (`id_cliente`),\n  KEY `fk_csol_rev` (`id_revisa`),\n  CONSTRAINT `fk_csol_cli` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_csol_loc` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`),\n  CONSTRAINT `fk_csol_rev` FOREIGN KEY (`id_revisa`) REFERENCES `empleado` (`id_empleado`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 33/51: `clientes_usuario_clientes`
CREATE TABLE IF NOT EXISTS `clientes_usuario_clientes` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_usuario` int(10) unsigned NOT NULL,\n  `id_cliente` int(10) unsigned NOT NULL,\n  `rol` enum('titular','representante') NOT NULL,\n  `es_principal` tinyint(1) NOT NULL DEFAULT 0,\n  `fecha_desde` date NOT NULL DEFAULT curdate(),\n  `fecha_hasta` date DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_cuc_usuario_cliente` (`id_usuario`,`id_cliente`),\n  KEY `fk_cuc_cliente` (`id_cliente`),\n  CONSTRAINT `fk_cuc_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_cuc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_cliente` (`id_usuario`),\n  CONSTRAINT `chk_cuc_fechas` CHECK (`fecha_hasta` is null or `fecha_hasta` >= `fecha_desde`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 34/51: `atencion_seguimiento`
CREATE TABLE IF NOT EXISTS `atencion_seguimiento` (\n  `id_seguimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_atencion` int(10) unsigned NOT NULL,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `id_empleado` int(10) unsigned DEFAULT NULL,\n  `estado_anterior` varchar(30) DEFAULT NULL,\n  `estado_nuevo` varchar(30) NOT NULL,\n  `accion` varchar(50) NOT NULL,\n  `observacion` text DEFAULT NULL,\n  `fecha` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_seguimiento`),\n  KEY `idx_atseg_atencion` (`id_atencion`),\n  KEY `idx_atseg_area` (`id_area`),\n  KEY `idx_atseg_empleado` (`id_empleado`),\n  CONSTRAINT `fk_atseg_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`) ON DELETE CASCADE,\n  CONSTRAINT `fk_atseg_atencion` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`) ON DELETE CASCADE,\n  CONSTRAINT `fk_atseg_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 35/51: `aviso_atencion`
CREATE TABLE IF NOT EXISTS `aviso_atencion` (\n  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_atencion` int(10) unsigned NOT NULL,\n  `id_empleado_de` int(10) unsigned DEFAULT NULL,\n  `id_empleado_para` int(10) unsigned NOT NULL,\n  `confirmado` tinyint(1) NOT NULL DEFAULT 0,\n  `fecha_aviso` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_aviso`),\n  KEY `fk_avaten_aten` (`id_atencion`),\n  KEY `fk_avaten_de` (`id_empleado_de`),\n  KEY `fk_avaten_para` (`id_empleado_para`),\n  CONSTRAINT `fk_avaten_aten` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`),\n  CONSTRAINT `fk_avaten_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_avaten_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 36/51: `empresa_seguimiento`
CREATE TABLE IF NOT EXISTS `empresa_seguimiento` (\n  `id_seguimiento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n  `id_cliente` int(10) unsigned NOT NULL,\n  `id_atencion_referencia` int(10) unsigned DEFAULT NULL,\n  `tipo_seguimiento` enum('3_meses','6_meses','manual') NOT NULL,\n  `motivo` varchar(255) DEFAULT NULL,\n  `fecha_referencia` date DEFAULT NULL,\n  `fecha_prevista` date NOT NULL,\n  `fecha_realizacion` date DEFAULT NULL,\n  `estado` enum('pendiente','realizado') NOT NULL DEFAULT 'pendiente',\n  `resultado_clasificacion` enum('positivo','neutro','negativo') DEFAULT NULL,\n  `situacion_resultado` text DEFAULT NULL,\n  `observaciones` text DEFAULT NULL,\n  `id_empleado_responsable` int(10) unsigned DEFAULT NULL,\n  `id_empleado_creador` int(10) unsigned DEFAULT NULL,\n  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),\n  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),\n  `hito_clave` varchar(16) GENERATED ALWAYS AS (if(`tipo_seguimiento` = 'manual',NULL,`tipo_seguimiento`)) STORED,\n  PRIMARY KEY (`id_seguimiento`),\n  UNIQUE KEY `uq_empresa_seguimiento_atencion_hito` (`id_atencion_referencia`,`hito_clave`),\n  KEY `idx_empresa_seguimiento_cliente_fecha` (`id_cliente`,`fecha_prevista`),\n  KEY `idx_empresa_seguimiento_responsable` (`id_empleado_responsable`),\n  KEY `fk_empresa_seguimiento_creador` (`id_empleado_creador`),\n  CONSTRAINT `fk_empresa_seguimiento_atencion` FOREIGN KEY (`id_atencion_referencia`) REFERENCES `atencion` (`id_atencion`),\n  CONSTRAINT `fk_empresa_seguimiento_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_empresa_seguimiento_creador` FOREIGN KEY (`id_empleado_creador`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL,\n  CONSTRAINT `fk_empresa_seguimiento_responsable` FOREIGN KEY (`id_empleado_responsable`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL,\n  CONSTRAINT `chk_empresa_seguimiento_referencia` CHECK (`id_atencion_referencia` is not null and `fecha_referencia` is not null or `tipo_seguimiento` = 'manual' and `id_atencion_referencia` is null and `fecha_referencia` is null)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 37/51: `tarea`
CREATE TABLE IF NOT EXISTS `tarea` (\n  `id_tarea` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `titulo` varchar(200) NOT NULL,\n  `descripcion` text DEFAULT NULL,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `id_tipo` int(10) unsigned NOT NULL,\n  `prioridad` enum('urgente','alta','media','baja') NOT NULL DEFAULT 'media',\n  `estado` enum('no_iniciada','en_proceso','pausada','terminada','cancelada') NOT NULL DEFAULT 'no_iniciada',\n  `fecha_inicio` date NOT NULL,\n  `fecha_cierre` date NOT NULL,\n  `avance` tinyint(3) unsigned NOT NULL DEFAULT 0,\n  `id_eje` int(10) unsigned DEFAULT NULL,\n  `fecha_cierre_real` date DEFAULT NULL,\n  `id_atencion` int(10) unsigned DEFAULT NULL,\n  `id_cliente` int(10) unsigned DEFAULT NULL,\n  `id_usuario_actor` int(10) unsigned DEFAULT NULL,\n  `id_tarea_padre` int(10) unsigned DEFAULT NULL,\n  `uid` varchar(20) DEFAULT NULL,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_tarea`),\n  KEY `fk_tarea_area` (`id_area`),\n  KEY `fk_tarea_tipo` (`id_tipo`),\n  KEY `fk_tarea_cliente` (`id_cliente`),\n  KEY `fk_tarea_usuario_act` (`id_usuario_actor`),\n  KEY `fk_tarea_padre` (`id_tarea_padre`),\n  KEY `idx_tarea_estado` (`estado`),\n  KEY `idx_tarea_cierre_estado` (`fecha_cierre`,`estado`),\n  KEY `fk_tarea_eje` (`id_eje`),\n  KEY `fk_tarea_aten` (`id_atencion`),\n  CONSTRAINT `fk_tarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_tarea_aten` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`),\n  CONSTRAINT `fk_tarea_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_tarea_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),\n  CONSTRAINT `fk_tarea_padre` FOREIGN KEY (`id_tarea_padre`) REFERENCES `tarea` (`id_tarea`),\n  CONSTRAINT `fk_tarea_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`),\n  CONSTRAINT `fk_tarea_usuario_act` FOREIGN KEY (`id_usuario_actor`) REFERENCES `usuario_cliente` (`id_usuario`),\n  CONSTRAINT `chk_tarea_avance` CHECK (`avance` between 0 and 100),\n  CONSTRAINT `chk_tarea_fechas` CHECK (`fecha_cierre` >= `fecha_inicio`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 38/51: `actividad`
CREATE TABLE IF NOT EXISTS `actividad` (\n  `id_actividad` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_empleado` int(10) unsigned DEFAULT NULL,\n  `tipo` enum('creada','proceso','completada','vencida','cancelada','cambio') NOT NULL,\n  `descripcion` varchar(300) DEFAULT NULL,\n  `fecha_actividad` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_actividad`),\n  KEY `fk_act_tarea` (`id_tarea`),\n  KEY `fk_act_empleado` (`id_empleado`),\n  CONSTRAINT `fk_act_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_act_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 39/51: `aviso`
CREATE TABLE IF NOT EXISTS `aviso` (\n  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_empleado_de` int(10) unsigned NOT NULL,\n  `id_empleado_para` int(10) unsigned NOT NULL,\n  `fecha_aviso` datetime NOT NULL DEFAULT current_timestamp(),\n  `confirmado` tinyint(1) NOT NULL DEFAULT 0,\n  PRIMARY KEY (`id_aviso`),\n  KEY `fk_aviso_tarea` (`id_tarea`),\n  KEY `fk_aviso_de` (`id_empleado_de`),\n  KEY `fk_aviso_para` (`id_empleado_para`),\n  CONSTRAINT `fk_aviso_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_aviso_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_aviso_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 40/51: `crm_aviso`
CREATE TABLE IF NOT EXISTS `crm_aviso` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_empleado_para` int(10) unsigned NOT NULL,\n  `id_empleado_de` int(10) unsigned DEFAULT NULL,\n  `id_tarea` int(10) unsigned DEFAULT NULL,\n  `tipo` varchar(40) NOT NULL DEFAULT 'info',\n  `titulo` varchar(200) NOT NULL,\n  `detalle` text DEFAULT NULL,\n  `url` varchar(300) DEFAULT NULL,\n  `confirmado` tinyint(1) NOT NULL DEFAULT 0,\n  `fecha` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `fk_crmaviso_de` (`id_empleado_de`),\n  KEY `fk_crmaviso_tarea` (`id_tarea`),\n  KEY `idx_crmaviso_bandeja` (`id_empleado_para`,`confirmado`,`fecha`),\n  KEY `idx_crmaviso_tipo` (`tipo`),\n  CONSTRAINT `fk_crmaviso_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_crmaviso_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_crmaviso_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 41/51: `evento`
CREATE TABLE IF NOT EXISTS `evento` (\n  `id_evento` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `titulo` varchar(200) NOT NULL,\n  `descripcion` text DEFAULT NULL,\n  `tipo` varchar(40) NOT NULL DEFAULT 'reunion',\n  `fecha` date NOT NULL,\n  `id_area` smallint(5) unsigned NOT NULL,\n  `id_cliente` int(10) unsigned DEFAULT NULL,\n  `id_localidad` smallint(5) unsigned DEFAULT NULL,\n  `organismo` varchar(200) DEFAULT NULL,\n  `id_empleado` int(10) unsigned DEFAULT NULL,\n  `id_empleado_creador` int(10) unsigned DEFAULT NULL,\n  `id_tarea_origen` int(10) unsigned DEFAULT NULL,\n  `asistencia` text DEFAULT NULL,\n  `resultado` text DEFAULT NULL,\n  `autoinscripcion` tinyint(1) NOT NULL DEFAULT 0,\n  `publico` enum('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos',\n  `activo` tinyint(1) NOT NULL DEFAULT 1,\n  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id_evento`),\n  KEY `fk_evento_area` (`id_area`),\n  KEY `fk_evento_cliente` (`id_cliente`),\n  KEY `fk_evento_emp` (`id_empleado`),\n  KEY `fk_evento_tarea` (`id_tarea_origen`),\n  KEY `idx_evento_localidad_activo` (`id_localidad`,`activo`),\n  KEY `idx_evento_creador` (`id_empleado_creador`),\n  CONSTRAINT `fk_evento_area_v2` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),\n  CONSTRAINT `fk_evento_cliente_v2` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_evento_creador` FOREIGN KEY (`id_empleado_creador`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL,\n  CONSTRAINT `fk_evento_emp_v2` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_evento_localidad_v2` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`),\n  CONSTRAINT `fk_evento_tarea_v2` FOREIGN KEY (`id_tarea_origen`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 42/51: `tarea_avance`
CREATE TABLE IF NOT EXISTS `tarea_avance` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_empleado` int(10) unsigned DEFAULT NULL,\n  `url` varchar(500) DEFAULT NULL,\n  `comentario` text DEFAULT NULL,\n  `archivo` varchar(255) DEFAULT NULL,\n  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `fk_tav_tarea` (`id_tarea`),\n  KEY `fk_tav_emp` (`id_empleado`),\n  CONSTRAINT `fk_tav_emp` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tav_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 43/51: `tarea_aviso_vencimiento`
CREATE TABLE IF NOT EXISTS `tarea_aviso_vencimiento` (\n  `id_tarea` int(10) unsigned NOT NULL,\n  `fecha_aviso` date NOT NULL,\n  PRIMARY KEY (`id_tarea`,`fecha_aviso`),\n  CONSTRAINT `fk_tavenc_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 44/51: `tarea_comentario`
CREATE TABLE IF NOT EXISTS `tarea_comentario` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_empleado` int(10) unsigned DEFAULT NULL,\n  `id_usuario_actor` int(10) unsigned DEFAULT NULL,\n  `texto` text NOT NULL,\n  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  KEY `fk_tcom_tarea` (`id_tarea`),\n  KEY `fk_tcom_empleado` (`id_empleado`),\n  KEY `fk_tcom_usuario_actor` (`id_usuario_actor`),\n  CONSTRAINT `fk_tcom_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tcom_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`),\n  CONSTRAINT `fk_tcom_usuario_actor` FOREIGN KEY (`id_usuario_actor`) REFERENCES `usuario_cliente` (`id_usuario`),\n  CONSTRAINT `chk_tcom_autor` CHECK (`id_empleado` is not null or `id_usuario_actor` is not null)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 45/51: `tarea_enlace`
CREATE TABLE IF NOT EXISTS `tarea_enlace` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_tarea_comentario` int(10) unsigned DEFAULT NULL,\n  `url` varchar(500) NOT NULL,\n  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_tarea_enlace_comentario` (`id_tarea_comentario`),\n  KEY `fk_tenl_tarea` (`id_tarea`),\n  CONSTRAINT `fk_tenl_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 46/51: `tarea_inicio`
CREATE TABLE IF NOT EXISTS `tarea_inicio` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_empleado` int(10) unsigned NOT NULL,\n  `fecha_click_inicio` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_tini_tarea_empleado` (`id_tarea`,`id_empleado`),\n  KEY `fk_tini_empleado` (`id_empleado`),\n  CONSTRAINT `fk_tini_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tini_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 47/51: `tarea_responsable`
CREATE TABLE IF NOT EXISTS `tarea_responsable` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_empleado` int(10) unsigned NOT NULL,\n  `es_principal` tinyint(1) NOT NULL DEFAULT 0,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_tresp_tarea_empleado` (`id_tarea`,`id_empleado`),\n  KEY `fk_tresp_empleado` (`id_empleado`),\n  CONSTRAINT `fk_tresp_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tresp_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 48/51: `tarea_solicitud_baja`
CREATE TABLE IF NOT EXISTS `tarea_solicitud_baja` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_solicita` int(10) unsigned NOT NULL,\n  `motivo` text NOT NULL,\n  `estado` enum('pendiente_coord','pendiente_general','aceptada','rechazada') NOT NULL DEFAULT 'pendiente_coord',\n  `id_revisa_coord` int(10) unsigned DEFAULT NULL,\n  `nota_coord` text DEFAULT NULL,\n  `id_revisa_general` int(10) unsigned DEFAULT NULL,\n  `nota_general` text DEFAULT NULL,\n  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),\n  `fecha_resolucion` datetime DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `fk_tsb_solicita` (`id_solicita`),\n  KEY `fk_tsb_coord` (`id_revisa_coord`),\n  KEY `fk_tsb_general` (`id_revisa_general`),\n  KEY `idx_tsb_tarea` (`id_tarea`),\n  KEY `idx_tsb_estado` (`estado`),\n  KEY `idx_tsb_pendientes` (`estado`,`fecha_solicitud`),\n  CONSTRAINT `fk_tsb_coord` FOREIGN KEY (`id_revisa_coord`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tsb_general` FOREIGN KEY (`id_revisa_general`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tsb_solicita` FOREIGN KEY (`id_solicita`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tsb_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 49/51: `tarea_solicitud_cambio_fecha`
CREATE TABLE IF NOT EXISTS `tarea_solicitud_cambio_fecha` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_tarea` int(10) unsigned NOT NULL,\n  `id_solicita` int(10) unsigned NOT NULL,\n  `fecha_inicio_original` date NOT NULL,\n  `fecha_cierre_original` date NOT NULL,\n  `fecha_inicio_propuesta` date NOT NULL,\n  `fecha_cierre_propuesta` date NOT NULL,\n  `motivo` text NOT NULL,\n  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',\n  `id_resuelve` int(10) unsigned DEFAULT NULL,\n  `motivo_resolucion` text DEFAULT NULL,\n  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),\n  `fecha_resolucion` datetime DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `idx_tscf_tarea_estado` (`id_tarea`,`estado`,`id`),\n  KEY `idx_tscf_solicitante_fecha` (`id_solicita`,`fecha_solicitud`),\n  KEY `idx_tscf_resuelve` (`id_resuelve`),\n  CONSTRAINT `fk_tscf_resuelve` FOREIGN KEY (`id_resuelve`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tscf_solicita` FOREIGN KEY (`id_solicita`) REFERENCES `empleado` (`id_empleado`),\n  CONSTRAINT `fk_tscf_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 50/51: `evento_inscripcion`
CREATE TABLE IF NOT EXISTS `evento_inscripcion` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_evento` int(10) unsigned NOT NULL,\n  `id_cliente` int(10) unsigned NOT NULL,\n  `estado` enum('inscrito','asistio','ausente') NOT NULL DEFAULT 'inscrito',\n  `fecha_inscripcion` datetime NOT NULL DEFAULT current_timestamp(),\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_evins` (`id_evento`,`id_cliente`),\n  KEY `idx_evins_cliente` (`id_cliente`,`estado`),\n  CONSTRAINT `fk_evins_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),\n  CONSTRAINT `fk_evins_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla 51/51: `evento_tarea`
CREATE TABLE IF NOT EXISTS `evento_tarea` (\n  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n  `id_evento` int(10) unsigned NOT NULL,\n  `id_tarea` int(10) unsigned NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uq_evtar` (`id_evento`,`id_tarea`),\n  KEY `fk_evtar_tarea` (`id_tarea`),\n  CONSTRAINT `fk_evtar_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`),\n  CONSTRAINT `fk_evtar_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------------------------
-- SECCIÓN 2: CATÁLOGOS MAESTROS INDISPENSABLES
-- (Sin datos de clientes, empleados, turnos, tareas ni historiales operativos)
-- ----------------------------------------------------------------------------

INSERT INTO `rol` (`id_rol`, `clave`, `nombre`) VALUES (1,'superadmin','Superadmin'),
(2,'coordinador_general','Coordinador general'),
(3,'coordinador','Coordinador de área'),
(4,'empleado','Empleado'),
(5,'presidencia','Presidencia'),
(6,'admin_tareas','Administración de tareas');
INSERT INTO `area` (`id_area`, `clave`, `nombre`, `activo`, `atiende_clientes`, `puede_clientes`, `recibe_derivaciones`, `recibe_turnos`, `puede_atenciones`, `informes_habilitados`, `asignable_empleado`, `creado_en`) VALUES (1,'recepcion','Recepción',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(2,'marketing','Marketing',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(3,'empresas','Empresas',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(4,'emprendedores','Emprendedores',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(5,'comercio','Comercio exterior',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(6,'rrhh','Recursos humanos',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(7,'vinculaciones','Vinculación académica',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(8,'sistemas','Sistemas',1,0,1,0,0,1,1,1,'2026-10-02 19:05:50'),
(9,'administracion','Administración',1,1,1,1,1,1,1,1,'2026-10-02 19:05:50'),
(10,'general','Coordinación general',1,0,0,0,0,1,1,0,'2026-10-02 19:05:50'),
(11,'presidencia','Presidencia',1,0,0,0,0,1,1,0,'2026-10-02 19:05:50'),
(14,'analista','Analista',1,1,1,1,1,1,1,1,'2026-10-03 14:00:13');
INSERT INTO `provincia` (`id_provincia`, `nombre`) VALUES (1,'Buenos Aires'),
(2,'CABA'),
(3,'Catamarca'),
(4,'Chaco'),
(5,'Chubut'),
(6,'Córdoba'),
(7,'Corrientes'),
(8,'Entre Ríos'),
(9,'Formosa'),
(10,'Jujuy'),
(11,'La Pampa'),
(12,'La Rioja'),
(13,'Mendoza'),
(14,'Misiones'),
(15,'Neuquén'),
(16,'Río Negro'),
(17,'Salta'),
(18,'San Juan'),
(19,'San Luis'),
(20,'Santa Cruz'),
(21,'Santa Fe'),
(22,'Santiago del Estero'),
(23,'Tierra del Fuego'),
(24,'Tucumán');
INSERT INTO `localidad` (`id_localidad`, `nombre`, `id_provincia`, `latitud`, `longitud`, `poligono_geojson`) VALUES (1,'Posadas',14,-27.3918400,-55.9237900,NULL),
(2,'Garupá',14,-27.4831500,-55.8292400,NULL),
(3,'Fachinal',14,-27.6291600,-55.7050600,NULL),
(4,'Apóstoles',14,-27.9142100,-55.7535500,NULL),
(5,'Azara',14,-28.0606700,-55.6770900,NULL),
(6,'San José',14,-27.7691600,-55.7803300,NULL),
(7,'Tres Capones',14,-28.0043100,-55.6093500,NULL),
(8,'Aristóbulo del Valle',14,-27.0957100,-54.8934400,NULL),
(9,'Campo Grande',14,-27.2077000,-54.9797700,NULL),
(10,'Campo Ramón',14,-27.4549000,-55.0212200,NULL),
(11,'Dos de Mayo',14,-27.0199800,-54.6846200,NULL),
(12,'Candelaria',14,-27.4617000,-55.7449000,NULL),
(13,'Bonpland',14,-27.4834700,-55.4778400,NULL),
(14,'Cerro Corá',14,-27.5137700,-55.6089500,NULL),
(15,'Loreto',14,-27.3317700,-55.5249900,NULL),
(16,'Santa Ana',14,-27.3692400,-55.5816700,NULL),
(17,'Profundidad',14,-27.5613100,-55.7050600,NULL),
(18,'Mártires',14,-27.4226500,-55.3765900,NULL),
(19,'Concepción de la Sierra',14,-27.9822500,-55.5208000,NULL),
(20,'Santa María',14,-27.9349000,-55.4074200,NULL),
(21,'Eldorado',14,-26.4045300,-54.6184700,NULL),
(22,'Puerto Piray',14,-26.4693700,-54.7073600,NULL),
(23,'Colonia Victoria',14,-26.3312400,-54.6214100,NULL),
(24,'Alicia',14,-27.3781456,-54.3518686,NULL),
(25,'San Vicente',14,-26.9932723,-54.4866356,NULL),
(26,'San Antonio',14,-26.0599300,-53.7357800,NULL),
(27,'Bernardo de Irigoyen',14,-26.2554300,-53.6468200,NULL),
(28,'Comandante Andresito',14,-25.6700000,-54.0455200,NULL),
(29,'El Soberbio',14,-27.2966200,-54.1981100,NULL),
(30,'Puerto Iguazú',14,-25.5991200,-54.5735500,NULL),
(31,'Wanda',14,-25.9733700,-54.5630400,NULL),
(32,'Puerto Libertad',14,-25.9195800,-54.5822900,NULL),
(33,'Leandro N. Alem',14,-27.6037600,-55.3236500,NULL),
(34,'Almafuerte',14,-27.5082500,-55.4025800,NULL),
(35,'Cerro Azul',14,-27.6336300,-55.4974600,NULL),
(36,'Gobernador Roca',14,-27.1905700,-55.4692700,NULL),
(37,'Olegario V. Andrade',14,-27.5670500,-55.5020800,NULL),
(38,'Panambí',14,-27.7219900,-54.9148600,NULL),
(39,'Jardín América',14,-27.0434600,-55.2269800,NULL),
(40,'Puerto Rico',14,-26.8089000,-55.0214700,NULL),
(41,'Ruiz de Montoya',14,-26.9708800,-55.0506700,NULL),
(42,'Capioví',14,-26.9293600,-55.0577300,NULL),
(43,'Garuhapé',14,-26.8211600,-54.9577400,NULL),
(44,'Montecarlo',14,-26.5684900,-54.7579000,NULL),
(45,'Caraguatay',14,-26.6058700,-54.7809300,NULL),
(46,'Oberá',14,-27.4868300,-55.1199400,NULL),
(47,'Campo Viera',14,-27.3327100,-55.0565100,NULL),
(48,'General Alvear',14,-27.4261100,-55.1691600,NULL),
(49,'Los Helechos',14,-27.5570000,-55.0760000,NULL),
(50,'San Ignacio',14,-27.2586500,-55.5389700,NULL),
(51,'Corpus',14,-27.1294700,-55.5108300,NULL),
(52,'Santo Pipó',14,-27.1414700,-55.4094500,NULL),
(53,'Hipólito Yrigoyen',14,-27.0920000,-55.2860000,NULL),
(54,'San Javier',14,-27.8739800,-55.1348500,NULL),
(55,'Florentino Ameghino',14,-27.6333500,-55.0837200,NULL),
(56,'San Pedro',14,-26.6218000,-54.1090200,NULL),
(57,'Paraíso',14,-26.6870700,-54.2030700,NULL),
(58,'Veinticinco de Mayo',14,-27.3779600,-54.7450500,NULL);
INSERT INTO `tipo_tarea` (`id_tipo`, `id_area`, `nombre`) VALUES (29,1,'General'),
(30,2,'General'),
(31,3,'General'),
(1,4,'Acelerar'),
(3,4,'Código de Barras'),
(2,4,'Origen Misiones'),
(4,5,'Acelerar'),
(32,6,'General'),
(33,7,'General'),
(34,8,'General'),
(35,9,'General'),
(36,10,'General'),
(37,11,'General'),
(38,14,'General');
INSERT INTO `turno_config` (`clave`, `valor`) VALUES ('hora_desde','07:00'),
('hora_hasta','19:30'),
('turnos_por_dia','16');
INSERT INTO `informe_plantilla` (`id_plantilla`, `clave`, `nombre`, `descripcion`, `activo`) VALUES (1,'actividades_tipo_localidad','Actividades por tipo y localidad','Ej. capacitaciones en Posadas',1),
(2,'actividades_tipo_cliente','Actividades por tipo de cliente','Emprendedor, empresa, municipalidad u organismo',1),
(3,'rondas_municipalidades','Rondas / actividades para municipalidades','Enfoque municipalidades',1),
(4,'clientes_por_tipo','Clientes por tipo','Conteo y detalle agrupado por tipo de cliente',1);
INSERT INTO `area_informe` (`id_area`, `codigo_informe`, `habilitado`) VALUES (1,'actividades',1),
(1,'atenciones',1),
(1,'clientes',1),
(1,'colaboradores',1),
(1,'tareas',1),
(2,'actividades',1),
(2,'atenciones',1),
(2,'clientes',1),
(2,'colaboradores',1),
(2,'tareas',1),
(3,'actividades',1),
(3,'atenciones',1),
(3,'clientes',1),
(3,'colaboradores',1),
(3,'tareas',1),
(4,'actividades',1),
(4,'atenciones',1),
(4,'clientes',1),
(4,'colaboradores',1),
(4,'tareas',1),
(5,'actividades',1),
(5,'atenciones',1),
(5,'clientes',1),
(5,'colaboradores',1),
(5,'tareas',1),
(6,'actividades',1),
(6,'atenciones',1),
(6,'clientes',1),
(6,'colaboradores',1),
(6,'tareas',1),
(7,'actividades',1),
(7,'atenciones',1),
(7,'clientes',1),
(7,'colaboradores',1),
(7,'tareas',1),
(8,'actividades',1),
(8,'atenciones',1),
(8,'clientes',1),
(8,'colaboradores',1),
(8,'tareas',1),
(9,'actividades',1),
(9,'atenciones',1),
(9,'clientes',1),
(9,'colaboradores',1),
(9,'tareas',1),
(10,'actividades',1),
(10,'atenciones',1),
(10,'clientes',1),
(10,'colaboradores',1),
(10,'tareas',1),
(11,'actividades',1),
(11,'atenciones',1),
(11,'clientes',1),
(11,'colaboradores',1),
(11,'tareas',1),
(14,'actividades',0),
(14,'atenciones',0),
(14,'clientes',1),
(14,'colaboradores',1),
(14,'tareas',0);
INSERT INTO `crm_schema_version` (`clave`, `fecha`) VALUES ('area_flags_v1','2026-10-22 21:03:17'),
('atencion_fecha_turno','2026-10-22 21:03:17'),
('cliente_aviso_v1','2026-10-22 21:03:17'),
('cliente_solicitud_v1','2026-10-22 21:03:17'),
('evento_v3','2026-10-02 16:10:48'),
('evento_v4','2026-10-22 21:03:17'),
('flujo_tareas','2026-10-22 21:03:17'),
('tarea_enlace_comentario_v1','2026-10-17 23:14:37'),
('turno_agenda_v3','2026-10-22 21:03:17'),
('v2_migracion_general_ademi','2026-10-12 19:29:45');


-- ----------------------------------------------------------------------------
-- SECCIÓN 3: PROVISIÓN DEL PRIMER SUPERADMINISTRADOR
-- ----------------------------------------------------------------------------
-- Por seguridad institucional y mejores prácticas, este script NO incluye
-- ninguna contraseña por defecto ni credenciales predecibles.
--
-- Para dar de alta el primer usuario superadministrador:
--
-- PASO 1: Generar el hash seguro de su contraseña mediante PHP (BCRYPT / $2y$).
--   En consola:
--     php -r "echo password_hash('SuContraseñaSegura123!', PASSWORD_BCRYPT);"
--   O cree un archivo PHP temporal que devuelva el resultado de password_hash.
--
-- PASO 2: Ejecute la siguiente sentencia reemplazando [HASH_GENERADO], correo y datos:
--
-- INSERT INTO `empleado` (
--   `nombres`, `apellido`, `cuil`, `dni`, `correo`, `fecha_ingreso`,
--   `nombre_usuario`, `contrasena_hash`, `id_rol`, `id_area`, `activo`, `primer_acceso`
-- ) VALUES (
--   'Super', 'Administrador',
--   '20999999990', '99999999', 'admin@ademi.com.ar', CURRENT_DATE,
--   'superadmin',
--   '[HASH_GENERADO]',
--   (SELECT id_rol FROM rol WHERE clave = 'superadmin'),
--   (SELECT id_area FROM area WHERE clave = 'sistemas'),
--   1, 0
-- );
--
-- NOTA:
-- - id_rol 'superadmin' otorga acceso global irrestricto.
-- - id_area 'sistemas' es el área institucional asignada a la administración técnica.
-- - primer_acceso = 0 indica cuenta ya inicializada.

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIN DE INSTALACIÓN COMPLETA
-- ============================================================================
