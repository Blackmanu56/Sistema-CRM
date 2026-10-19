-- CRM ADEMI - Full schema installer (structure only)
-- Snapshot generated from the local crm_prueba schema.
-- No application/test records, credentials, or local paths are included.
-- Import this file into a newly created, empty database. It does not select or create a database.
-- Baseline role, area, and report-availability configuration is seeded at the end.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `actividad` (
  `id_actividad` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `tipo` enum('creada','proceso','completada','vencida','cancelada','cambio') NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `fecha_actividad` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_actividad`),
  KEY `fk_act_tarea` (`id_tarea`),
  KEY `fk_act_empleado` (`id_empleado`),
  CONSTRAINT `fk_act_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_act_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `area` (
  `id_area` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `atiende_clientes` tinyint(1) NOT NULL DEFAULT 0,
  `puede_clientes` tinyint(1) NOT NULL DEFAULT 0,
  `recibe_derivaciones` tinyint(1) NOT NULL DEFAULT 0,
  `recibe_turnos` tinyint(1) NOT NULL DEFAULT 0,
  `asignable_empleado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_area`),
  UNIQUE KEY `uq_area_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `area_informe` (
  `id_area` smallint(5) unsigned NOT NULL,
  `codigo_informe` varchar(32) NOT NULL,
  `habilitado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_area`,`codigo_informe`),
  CONSTRAINT `fk_area_informe_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `atencion` (
  `id_atencion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_area_origen` smallint(5) unsigned DEFAULT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `id_empleado_origen` int(10) unsigned DEFAULT NULL,
  `motivo` enum('consulta','documentacion','seguimiento','tramite','otro') NOT NULL DEFAULT 'consulta',
  `canal` enum('web','presencial','whatsapp','redes') NOT NULL DEFAULT 'presencial',
  `detalle` text DEFAULT NULL,
  `estado` enum('pendiente','abierta','en_atencion','cerrada','derivada','cancelada') NOT NULL DEFAULT 'abierta',
  `id_atencion_origen` int(10) unsigned DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_turno` datetime DEFAULT NULL,
  `fecha_derivacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_atencion`),
  KEY `fk_aten_cliente` (`id_cliente`),
  KEY `fk_aten_empleado` (`id_empleado`),
  KEY `fk_aten_origen` (`id_atencion_origen`),
  KEY `idx_aten_estado_fecha` (`estado`,`fecha_turno`),
  KEY `idx_aten_area_estado` (`id_area`,`estado`),
  KEY `fk_atencion_area_origen` (`id_area_origen`),
  KEY `fk_atencion_empleado_origen` (`id_empleado_origen`),
  CONSTRAINT `fk_aten_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_aten_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_aten_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_aten_origen` FOREIGN KEY (`id_atencion_origen`) REFERENCES `atencion` (`id_atencion`),
  CONSTRAINT `fk_atencion_area_origen` FOREIGN KEY (`id_area_origen`) REFERENCES `area` (`id_area`) ON DELETE SET NULL,
  CONSTRAINT `fk_atencion_empleado_origen` FOREIGN KEY (`id_empleado_origen`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `atencion_seguimiento` (
  `id_seguimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_atencion` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `estado_anterior` varchar(30) DEFAULT NULL,
  `estado_nuevo` varchar(30) NOT NULL,
  `accion` varchar(50) NOT NULL,
  `observacion` text DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_seguimiento`),
  KEY `idx_atseg_atencion` (`id_atencion`),
  KEY `idx_atseg_area` (`id_area`),
  KEY `idx_atseg_empleado` (`id_empleado`),
  CONSTRAINT `fk_atseg_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`) ON DELETE CASCADE,
  CONSTRAINT `fk_atseg_atencion` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`) ON DELETE CASCADE,
  CONSTRAINT `fk_atseg_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aviso` (
  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado_de` int(10) unsigned NOT NULL,
  `id_empleado_para` int(10) unsigned NOT NULL,
  `fecha_aviso` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_aviso`),
  KEY `fk_aviso_tarea` (`id_tarea`),
  KEY `fk_aviso_de` (`id_empleado_de`),
  KEY `fk_aviso_para` (`id_empleado_para`),
  CONSTRAINT `fk_aviso_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_aviso_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_aviso_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aviso_atencion` (
  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_atencion` int(10) unsigned NOT NULL,
  `id_empleado_de` int(10) unsigned DEFAULT NULL,
  `id_empleado_para` int(10) unsigned NOT NULL,
  `confirmado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_aviso` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_aviso`),
  KEY `fk_avaten_aten` (`id_atencion`),
  KEY `fk_avaten_de` (`id_empleado_de`),
  KEY `fk_avaten_para` (`id_empleado_para`),
  CONSTRAINT `fk_avaten_aten` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`),
  CONSTRAINT `fk_avaten_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_avaten_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente` (
  `id_cliente` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_cliente` enum('emprendedor','empresa','municipalidad','organismo') NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `id_localidad` smallint(5) unsigned NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `cuil_cuit` varchar(13) NOT NULL,
  `id_area_afectada` smallint(5) unsigned DEFAULT NULL,
  `id_empleado_alta` int(10) unsigned DEFAULT NULL,
  `origen_contacto` enum('web','presencial','whatsapp','redes') DEFAULT NULL,
  `fecha_alta` date NOT NULL DEFAULT curdate(),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `uq_cliente_cuil_cuit` (`cuil_cuit`),
  KEY `fk_cliente_localidad` (`id_localidad`),
  KEY `fk_cliente_area` (`id_area_afectada`),
  KEY `idx_cliente_tipo` (`tipo_cliente`),
  KEY `idx_cliente_tipo_activo` (`tipo_cliente`,`activo`),
  KEY `fk_cliente_empleado_alta` (`id_empleado_alta`),
  CONSTRAINT `fk_cliente_area` FOREIGN KEY (`id_area_afectada`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_cliente_empleado_alta` FOREIGN KEY (`id_empleado_alta`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL,
  CONSTRAINT `fk_cliente_localidad` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_area` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cliarea` (`id_cliente`,`id_area`),
  KEY `fk_cliarea_area` (`id_area`),
  CONSTRAINT `fk_cliarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_cliarea_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_aviso` (
  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `detalle` text DEFAULT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_aviso`),
  KEY `idx_claviso_user` (`id_usuario`,`leido`,`fecha`),
  CONSTRAINT `fk_claviso_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_cliente` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_empresa` (
  `id_cliente` int(10) unsigned NOT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  CONSTRAINT `fk_cliempresa_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_persona` (
  `id_cliente` int(10) unsigned NOT NULL,
  `genero` enum('femenino','masculino','otro') DEFAULT NULL,
  `dni` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  CONSTRAINT `fk_clipersona_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_programa` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_programa` int(10) unsigned NOT NULL,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  `fecha_hasta` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cliprog_prog` (`id_programa`),
  KEY `idx_cliprog_activo` (`id_cliente`,`id_programa`,`fecha_hasta`),
  CONSTRAINT `fk_cliprog_cli` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_cliprog_prog` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id_programa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cliente_solicitud` (
  `id_solicitud` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `dni` varchar(15) DEFAULT NULL,
  `cuil_cuit` varchar(13) NOT NULL,
  `nombre_negocio` varchar(150) DEFAULT NULL,
  `id_localidad` smallint(5) unsigned NOT NULL,
  `tipo_autodeclarado` enum('emprendedor','empresa') NOT NULL DEFAULT 'emprendedor',
  `sector` varchar(80) DEFAULT NULL,
  `sector_otro` varchar(120) DEFAULT NULL,
  `anios_funcionamiento` varchar(40) DEFAULT NULL,
  `personas_involucradas` varchar(40) DEFAULT NULL,
  `personal_formal` tinyint(1) NOT NULL DEFAULT 0,
  `producto_servicio` text DEFAULT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  `sugerencia_tipo` enum('emprendedor','empresa') DEFAULT NULL,
  `tipo_asignado` enum('emprendedor','empresa') DEFAULT NULL,
  `id_cliente` int(10) unsigned DEFAULT NULL,
  `id_revisa` int(10) unsigned DEFAULT NULL,
  `nota_revision` text DEFAULT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `idx_csol_estado` (`estado`,`fecha_solicitud`),
  KEY `idx_csol_correo` (`correo`),
  KEY `fk_csol_loc` (`id_localidad`),
  KEY `fk_csol_cli` (`id_cliente`),
  KEY `fk_csol_rev` (`id_revisa`),
  CONSTRAINT `fk_csol_cli` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_csol_loc` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`),
  CONSTRAINT `fk_csol_rev` FOREIGN KEY (`id_revisa`) REFERENCES `empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clientes_usuario_clientes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_cliente` int(10) unsigned NOT NULL,
  `rol` enum('titular','representante') NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  `fecha_hasta` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cuc_usuario_cliente` (`id_usuario`,`id_cliente`),
  KEY `fk_cuc_cliente` (`id_cliente`),
  CONSTRAINT `fk_cuc_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_cuc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_cliente` (`id_usuario`),
  CONSTRAINT `chk_cuc_fechas` CHECK (`fecha_hasta` is null or `fecha_hasta` >= `fecha_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crm_aviso` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado_para` int(10) unsigned NOT NULL,
  `id_empleado_de` int(10) unsigned DEFAULT NULL,
  `id_tarea` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'info',
  `titulo` varchar(200) NOT NULL,
  `detalle` text DEFAULT NULL,
  `url` varchar(300) DEFAULT NULL,
  `confirmado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_crmaviso_de` (`id_empleado_de`),
  KEY `fk_crmaviso_tarea` (`id_tarea`),
  KEY `idx_crmaviso_bandeja` (`id_empleado_para`,`confirmado`,`fecha`),
  KEY `idx_crmaviso_tipo` (`tipo`),
  CONSTRAINT `fk_crmaviso_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_crmaviso_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_crmaviso_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crm_schema_version` (
  `clave` varchar(60) NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `dominio` (
  `id_dominio` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_vencimiento` date NOT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_dominio`),
  UNIQUE KEY `idx_nombre_activo` (`nombre`,`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `dominio_renovacion` (
  `id_renovacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_dominio` int(11) NOT NULL,
  `fecha_renovacion` date NOT NULL,
  `fecha_vencimiento_anterior` date NOT NULL,
  `fecha_vencimiento_nueva` date NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_renovacion`),
  KEY `idx_dominio` (`id_dominio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `eje` (
  `id_eje` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tipo` int(10) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_eje`),
  UNIQUE KEY `uq_eje_tipo_nombre` (`id_tipo`,`nombre`),
  CONSTRAINT `fk_eje_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empleado` (
  `id_empleado` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `foto_perfil_key` varchar(64) DEFAULT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `cuil` char(11) NOT NULL,
  `dni` varchar(15) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `telefono_alt` varchar(30) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `estado_civil` varchar(40) DEFAULT NULL,
  `localidad_domicilio` varchar(100) DEFAULT NULL,
  `provincia_domicilio` varchar(80) DEFAULT NULL,
  `puesto` varchar(120) DEFAULT NULL,
  `tipo_vinculo` varchar(80) DEFAULT NULL,
  `estado_laboral` varchar(40) NOT NULL DEFAULT 'activo',
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` varchar(200) DEFAULT NULL,
  `modalidad` varchar(40) DEFAULT NULL,
  `horario_jornada` varchar(120) DEFAULT NULL,
  `condicion_fiscal` varchar(60) DEFAULT NULL,
  `cuit_facturacion` varchar(13) DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_alta` date NOT NULL DEFAULT curdate(),
  `nombre_usuario` varchar(50) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `primer_acceso` tinyint(1) NOT NULL DEFAULT 1,
  `id_rol` smallint(5) unsigned NOT NULL,
  `id_area` smallint(5) unsigned DEFAULT NULL,
  `id_responsable` int(10) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `uq_empleado_cuil` (`cuil`),
  UNIQUE KEY `uq_empleado_dni` (`dni`),
  UNIQUE KEY `uq_empleado_correo` (`correo`),
  UNIQUE KEY `uq_empleado_usuario` (`nombre_usuario`),
  KEY `fk_empleado_rol` (`id_rol`),
  KEY `fk_empleado_area` (`id_area`),
  CONSTRAINT `fk_empleado_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_empleado_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empleado_documento_legajo` (
  `id_documento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` int(10) unsigned NOT NULL,
  `categoria` varchar(40) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `archivo_key` varchar(64) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario_carga` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_documento`),
  UNIQUE KEY `uq_empleado_documento_legajo_key` (`archivo_key`),
  KEY `idx_empleado_documento_legajo_fecha` (`id_empleado`,`fecha_carga`),
  KEY `idx_empleado_documento_legajo_cargado_por` (`id_usuario_carga`),
  CONSTRAINT `fk_empleado_documento_legajo_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_empleado_documento_legajo_usuario` FOREIGN KEY (`id_usuario_carga`) REFERENCES `empleado` (`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empleado_rol_adicional` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` int(10) unsigned NOT NULL,
  `id_rol` smallint(5) unsigned NOT NULL,
  `id_area` smallint(5) unsigned DEFAULT NULL,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  `fecha_hasta` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rol_adic_rol` (`id_rol`),
  KEY `fk_rol_adic_area` (`id_area`),
  KEY `idx_rol_adic_activo` (`id_empleado`,`fecha_hasta`),
  CONSTRAINT `fk_rol_adic_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_rol_adic_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_rol_adic_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`),
  CONSTRAINT `chk_rol_adic_fechas` CHECK (`fecha_hasta` is null or `fecha_hasta` >= `fecha_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evento` (
  `id_evento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'reunion',
  `fecha` date NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_cliente` int(10) unsigned DEFAULT NULL,
  `id_localidad` smallint(5) unsigned DEFAULT NULL,
  `organismo` varchar(200) DEFAULT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `id_tarea_origen` int(10) unsigned DEFAULT NULL,
  `asistencia` text DEFAULT NULL,
  `resultado` text DEFAULT NULL,
  `autoinscripcion` tinyint(1) NOT NULL DEFAULT 0,
  `publico` enum('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_evento`),
  KEY `fk_evento_area` (`id_area`),
  KEY `fk_evento_cliente` (`id_cliente`),
  KEY `fk_evento_emp` (`id_empleado`),
  KEY `fk_evento_tarea` (`id_tarea_origen`),
  KEY `idx_evento_localidad_activo` (`id_localidad`,`activo`),
  CONSTRAINT `fk_evento_area_v2` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_evento_cliente_v2` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_evento_emp_v2` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_evento_localidad_v2` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`),
  CONSTRAINT `fk_evento_tarea_v2` FOREIGN KEY (`id_tarea_origen`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evento_inscripcion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_evento` int(10) unsigned NOT NULL,
  `id_cliente` int(10) unsigned NOT NULL,
  `estado` enum('inscrito','asistio','ausente') NOT NULL DEFAULT 'inscrito',
  `fecha_inscripcion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evins` (`id_evento`,`id_cliente`),
  KEY `idx_evins_cliente` (`id_cliente`,`estado`),
  CONSTRAINT `fk_evins_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_evins_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `evento_tarea` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_evento` int(10) unsigned NOT NULL,
  `id_tarea` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evtar` (`id_evento`,`id_tarea`),
  KEY `fk_evtar_tarea` (`id_tarea`),
  CONSTRAINT `fk_evtar_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`),
  CONSTRAINT `fk_evtar_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `informe_plantilla` (
  `id_plantilla` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_plantilla`),
  UNIQUE KEY `uq_informe_plantilla` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `informe_publicado` (
  `id_informe` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_plantilla` smallint(5) unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `filtros_json` text DEFAULT NULL,
  `resumen_json` text DEFAULT NULL,
  `id_empleado_de` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned DEFAULT NULL,
  `publicado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_desde` date DEFAULT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_informe`),
  KEY `fk_infpub_plantilla` (`id_plantilla`),
  KEY `fk_infpub_empleado` (`id_empleado_de`),
  KEY `fk_infpub_area` (`id_area`),
  KEY `idx_infpub_presidencia` (`publicado`,`creado_en`),
  CONSTRAINT `fk_infpub_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_infpub_empleado` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_infpub_plantilla` FOREIGN KEY (`id_plantilla`) REFERENCES `informe_plantilla` (`id_plantilla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `localidad` (
  `id_localidad` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `id_provincia` smallint(5) unsigned NOT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `poligono_geojson` longtext DEFAULT NULL,
  PRIMARY KEY (`id_localidad`),
  UNIQUE KEY `uq_localidad` (`nombre`,`id_provincia`),
  KEY `fk_localidad_provincia` (`id_provincia`),
  CONSTRAINT `fk_localidad_provincia` FOREIGN KEY (`id_provincia`) REFERENCES `provincia` (`id_provincia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `presentacion_registro_interno` (
  `modulo` varchar(40) NOT NULL,
  `id_registro` int(10) unsigned NOT NULL,
  `clasificacion` enum('simulado') NOT NULL DEFAULT 'simulado',
  `nota` varchar(255) NOT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`modulo`,`id_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Control interno de datos simulados de presentacion; no es utilizado por la interfaz del CRM';

CREATE TABLE `programa` (
  `id_programa` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_programa`),
  UNIQUE KEY `uq_programa_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `programa_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_programa` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_tipo` int(10) unsigned DEFAULT NULL,
  `id_eje` int(10) unsigned DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_progitem_prog` (`id_programa`),
  KEY `fk_progitem_area` (`id_area`),
  KEY `fk_progitem_tipo` (`id_tipo`),
  KEY `fk_progitem_eje` (`id_eje`),
  CONSTRAINT `fk_progitem_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_progitem_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),
  CONSTRAINT `fk_progitem_prog` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id_programa`),
  CONSTRAINT `fk_progitem_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `provincia` (
  `id_provincia` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_provincia`),
  UNIQUE KEY `uq_provincia_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rol` (
  `id_rol` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `uq_rol_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sala_reunion` (
  `id_sala` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_sala`),
  UNIQUE KEY `uq_sala_reunion_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sala_reunion_reserva` (
  `id_reserva` int(11) NOT NULL AUTO_INCREMENT,
  `id_sala` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `id_empleado` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `motivo` varchar(500) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activa',
  `creado_en` datetime NOT NULL,
  `actualizado_en` datetime NOT NULL,
  PRIMARY KEY (`id_reserva`),
  KEY `idx_sala_reserva_agenda` (`id_sala`,`fecha`,`estado`,`hora_inicio`,`hora_fin`),
  KEY `idx_sala_reserva_area` (`id_area`,`fecha`),
  KEY `idx_sala_reserva_empleado` (`id_empleado`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea` (
  `id_tarea` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_tipo` int(10) unsigned NOT NULL,
  `prioridad` enum('urgente','alta','media','baja') NOT NULL DEFAULT 'media',
  `estado` enum('no_iniciada','en_proceso','pausada','terminada','cancelada') NOT NULL DEFAULT 'no_iniciada',
  `fecha_inicio` date NOT NULL,
  `fecha_cierre` date NOT NULL,
  `avance` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `id_eje` int(10) unsigned DEFAULT NULL,
  `fecha_cierre_real` date DEFAULT NULL,
  `id_atencion` int(10) unsigned DEFAULT NULL,
  `id_cliente` int(10) unsigned DEFAULT NULL,
  `id_usuario_actor` int(10) unsigned DEFAULT NULL,
  `id_tarea_padre` int(10) unsigned DEFAULT NULL,
  `uid` varchar(20) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_tarea`),
  KEY `fk_tarea_area` (`id_area`),
  KEY `fk_tarea_tipo` (`id_tipo`),
  KEY `fk_tarea_cliente` (`id_cliente`),
  KEY `fk_tarea_usuario_act` (`id_usuario_actor`),
  KEY `fk_tarea_padre` (`id_tarea_padre`),
  KEY `idx_tarea_estado` (`estado`),
  KEY `idx_tarea_cierre_estado` (`fecha_cierre`,`estado`),
  KEY `fk_tarea_eje` (`id_eje`),
  KEY `fk_tarea_aten` (`id_atencion`),
  CONSTRAINT `fk_tarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_tarea_aten` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`),
  CONSTRAINT `fk_tarea_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_tarea_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),
  CONSTRAINT `fk_tarea_padre` FOREIGN KEY (`id_tarea_padre`) REFERENCES `tarea` (`id_tarea`),
  CONSTRAINT `fk_tarea_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`),
  CONSTRAINT `fk_tarea_usuario_act` FOREIGN KEY (`id_usuario_actor`) REFERENCES `usuario_cliente` (`id_usuario`),
  CONSTRAINT `chk_tarea_avance` CHECK (`avance` between 0 and 100),
  CONSTRAINT `chk_tarea_fechas` CHECK (`fecha_cierre` >= `fecha_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_avance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tav_tarea` (`id_tarea`),
  KEY `fk_tav_emp` (`id_empleado`),
  CONSTRAINT `fk_tav_emp` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tav_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_aviso_vencimiento` (
  `id_tarea` int(10) unsigned NOT NULL,
  `fecha_aviso` date NOT NULL,
  PRIMARY KEY (`id_tarea`,`fecha_aviso`),
  CONSTRAINT `fk_tavenc_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_comentario` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `id_usuario_actor` int(10) unsigned DEFAULT NULL,
  `texto` text NOT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tcom_tarea` (`id_tarea`),
  KEY `fk_tcom_empleado` (`id_empleado`),
  KEY `fk_tcom_usuario_actor` (`id_usuario_actor`),
  CONSTRAINT `fk_tcom_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tcom_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`),
  CONSTRAINT `fk_tcom_usuario_actor` FOREIGN KEY (`id_usuario_actor`) REFERENCES `usuario_cliente` (`id_usuario`),
  CONSTRAINT `chk_tcom_autor` CHECK (`id_empleado` is not null or `id_usuario_actor` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_enlace` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_tarea_comentario` int(10) unsigned DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarea_enlace_comentario` (`id_tarea_comentario`),
  KEY `fk_tenl_tarea` (`id_tarea`),
  CONSTRAINT `fk_tenl_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_inicio` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned NOT NULL,
  `fecha_click_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tini_tarea_empleado` (`id_tarea`,`id_empleado`),
  KEY `fk_tini_empleado` (`id_empleado`),
  CONSTRAINT `fk_tini_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tini_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_modelo` (
  `id_modelo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_tipo` int(10) unsigned NOT NULL,
  `id_eje` int(10) unsigned DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  PRIMARY KEY (`id_modelo`),
  KEY `fk_tmod_area` (`id_area`),
  KEY `fk_tmod_tipo` (`id_tipo`),
  KEY `fk_tmod_eje` (`id_eje`),
  CONSTRAINT `fk_tmod_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_tmod_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),
  CONSTRAINT `fk_tmod_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_responsable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tresp_tarea_empleado` (`id_tarea`,`id_empleado`),
  KEY `fk_tresp_empleado` (`id_empleado`),
  CONSTRAINT `fk_tresp_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tresp_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tarea_solicitud_baja` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_solicita` int(10) unsigned NOT NULL,
  `motivo` text NOT NULL,
  `estado` enum('pendiente_coord','pendiente_general','aceptada','rechazada') NOT NULL DEFAULT 'pendiente_coord',
  `id_revisa_coord` int(10) unsigned DEFAULT NULL,
  `nota_coord` text DEFAULT NULL,
  `id_revisa_general` int(10) unsigned DEFAULT NULL,
  `nota_general` text DEFAULT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tsb_solicita` (`id_solicita`),
  KEY `fk_tsb_coord` (`id_revisa_coord`),
  KEY `fk_tsb_general` (`id_revisa_general`),
  KEY `idx_tsb_tarea` (`id_tarea`),
  KEY `idx_tsb_estado` (`estado`),
  KEY `idx_tsb_pendientes` (`estado`,`fecha_solicitud`),
  CONSTRAINT `fk_tsb_coord` FOREIGN KEY (`id_revisa_coord`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_general` FOREIGN KEY (`id_revisa_general`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_solicita` FOREIGN KEY (`id_solicita`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tipo_tarea` (
  `id_tipo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_area` smallint(5) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_tipo`),
  UNIQUE KEY `uq_tipo_tarea_area_nombre` (`id_area`,`nombre`),
  CONSTRAINT `fk_tipo_tarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `turno_config` (
  `clave` varchar(40) NOT NULL,
  `valor` varchar(120) NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_cliente` (
  `id_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `primer_acceso` tinyint(1) NOT NULL DEFAULT 1,
  `dni` varchar(15) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_cliente_correo` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Minimal baseline configuration; no employees or login accounts are created.
INSERT INTO `rol` (`id_rol`, `clave`, `nombre`) VALUES
(1, 'superadmin', 'Superadmin'),
(2, 'coordinador_general', 'Coordinador general'),
(3, 'coordinador', 'Coordinador de área'),
(4, 'empleado', 'Empleado'),
(5, 'presidencia', 'Presidencia'),
(6, 'admin_tareas', 'Administración de tareas');

INSERT INTO `area` (`id_area`, `clave`, `nombre`, `activo`, `atiende_clientes`, `puede_clientes`, `recibe_derivaciones`, `recibe_turnos`, `asignable_empleado`) VALUES
(1, 'recepcion', 'Recepción', 1, 1, 1, 1, 1, 1),
(2, 'marketing', 'Marketing', 1, 1, 1, 1, 1, 1),
(3, 'empresas', 'Empresas', 1, 1, 1, 1, 1, 1),
(4, 'emprendedores', 'Emprendedores', 1, 1, 1, 1, 1, 1),
(5, 'comercio', 'Comercio exterior', 1, 1, 1, 1, 1, 1),
(6, 'rrhh', 'Recursos humanos', 1, 1, 1, 1, 1, 1),
(7, 'vinculaciones', 'Vinculación académica', 1, 1, 1, 1, 1, 1),
(8, 'sistemas', 'Sistemas', 1, 0, 1, 0, 0, 1),
(9, 'administracion', 'Administración', 1, 1, 1, 1, 1, 1),
(10, 'general', 'Coordinación general', 1, 0, 0, 0, 0, 0),
(11, 'presidencia', 'Presidencia', 1, 0, 0, 0, 0, 0),
(14, 'analista', 'Analista', 1, 1, 1, 1, 1, 1);

INSERT INTO `area_informe` (`id_area`, `codigo_informe`, `habilitado`) VALUES
(1, 'colaboradores', 1),
(1, 'clientes', 1),
(1, 'actividades', 1),
(1, 'tareas', 1),
(1, 'atenciones', 1),
(2, 'colaboradores', 1),
(2, 'clientes', 1),
(2, 'actividades', 1),
(2, 'tareas', 1),
(2, 'atenciones', 1),
(3, 'colaboradores', 1),
(3, 'clientes', 1),
(3, 'actividades', 1),
(3, 'tareas', 1),
(3, 'atenciones', 1),
(4, 'colaboradores', 1),
(4, 'clientes', 1),
(4, 'actividades', 1),
(4, 'tareas', 1),
(4, 'atenciones', 1),
(5, 'colaboradores', 1),
(5, 'clientes', 1),
(5, 'actividades', 1),
(5, 'tareas', 1),
(5, 'atenciones', 1),
(6, 'colaboradores', 1),
(6, 'clientes', 1),
(6, 'actividades', 1),
(6, 'tareas', 1),
(6, 'atenciones', 1),
(7, 'colaboradores', 1),
(7, 'clientes', 1),
(7, 'actividades', 1),
(7, 'tareas', 1),
(7, 'atenciones', 1),
(8, 'colaboradores', 1),
(8, 'clientes', 1),
(8, 'actividades', 1),
(8, 'tareas', 1),
(8, 'atenciones', 1),
(9, 'colaboradores', 1),
(9, 'clientes', 1),
(9, 'actividades', 1),
(9, 'tareas', 1),
(9, 'atenciones', 1),
(10, 'colaboradores', 1),
(10, 'clientes', 1),
(10, 'actividades', 1),
(10, 'tareas', 1),
(10, 'atenciones', 1),
(11, 'colaboradores', 1),
(11, 'clientes', 1),
(11, 'actividades', 1),
(11, 'tareas', 1),
(11, 'atenciones', 1),
(14, 'colaboradores', 1),
(14, 'clientes', 1),
(14, 'actividades', 1),
(14, 'tareas', 1),
(14, 'atenciones', 1);

SET FOREIGN_KEY_CHECKS=1;
