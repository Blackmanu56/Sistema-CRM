-- ============================================================================
-- CRM ADEMI - MIGRACIÓN GENERAL DE BASE DE DATOS
-- Archivo: sql/BDD.sql
-- Propósito: Incorporar a una base existente todas las extensiones, tablas
--            y columnas requeridas por la versión avanzada del CRM de desarrollo:
--            1. Módulo Dominios (Sistemas) y su historial de renovaciones.
--            2. Alertas, avisos y circuito de solicitud de baja de tareas.
--            3. Roles adicionales y soporte multi-área para colaboradores.
--            4. Mapa interactivo de eventos (coordenadas geográficas en Misiones).
--            5. Ajustes de legajo RRHH, áreas operativas y compatibilidad general.
--
-- INSTRUCCIONES DE APLICACIÓN SEGURA (PARA FACUNDO / INTEGRACIÓN):
-- 1. Respaldar la base de datos de producción antes de ejecutar:
--      mysqldump -u [usuario] -p [base_de_datos] > backup_produccion_previo.sql
-- 2. Este script es IDEMPOTENTE:
--    - No destruye ni recrea tablas existentes (DROP TABLE no se utiliza).
--    - No sobrescribe información ni borra registros existentes.
--    - No incluye datos personales ni registros de prueba.
--    - Verifica la existencia de tablas y columnas antes de agregarlas.
-- 3. Ejecución por consola MySQL / MariaDB o cliente gráfico (phpMyAdmin):
--      mysql -u [usuario] -p [base_de_datos] < sql/BDD.sql
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. TABLA DE CONTROL DE VERSIONES DE ESQUEMA
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `crm_schema_version` (
  `clave` VARCHAR(50) NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. MÓDULO DOMINIOS (SISTEMAS)
--    Almacena dominios web, proveedores, responsables y vencimientos.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dominio` (
  `id_dominio` INT(11) NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `url` VARCHAR(255) DEFAULT NULL,
  `proveedor` VARCHAR(100) DEFAULT NULL,
  `responsable` VARCHAR(100) DEFAULT NULL,
  `fecha_inicio` DATE DEFAULT NULL,
  `fecha_vencimiento` DATE NOT NULL,
  `notas` TEXT DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_dominio`),
  UNIQUE KEY `idx_nombre_activo` (`nombre`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 3. HISTORIAL DE RENOVACIONES DE DOMINIOS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dominio_renovacion` (
  `id_renovacion` INT(11) NOT NULL AUTO_INCREMENT,
  `id_dominio` INT(11) NOT NULL,
  `fecha_renovacion` DATE NOT NULL,
  `fecha_vencimiento_anterior` DATE NOT NULL,
  `fecha_vencimiento_nueva` DATE NOT NULL,
  `id_usuario` INT(11) DEFAULT NULL,
  `notas` TEXT DEFAULT NULL,
  `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_renovacion`),
  KEY `idx_dominio` (`id_dominio`),
  CONSTRAINT `fk_domren_dominio` FOREIGN KEY (`id_dominio`) REFERENCES `dominio` (`id_dominio`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 4. ROLES ADICIONALES DE EMPLEADOS (Soporte multi-área)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `empleado_rol_adicional` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_empleado` INT(10) UNSIGNED NOT NULL,
  `id_rol` SMALLINT(5) UNSIGNED NOT NULL,
  `id_area` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `fecha_desde` DATE NOT NULL DEFAULT (CURRENT_DATE),
  `fecha_hasta` DATE DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rol_adic_rol` (`id_rol`),
  KEY `fk_rol_adic_area` (`id_area`),
  KEY `idx_rol_adic_activo` (`id_empleado`, `fecha_hasta`),
  CONSTRAINT `fk_rol_adic_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_rol_adic_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_rol_adic_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`),
  CONSTRAINT `chk_rol_adic_fechas` CHECK (`fecha_hasta` IS NULL OR `fecha_hasta` >= `fecha_desde`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. BANDEJA DE AVISOS Y ALERTAS (Campanita, dominios y tareas)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `crm_aviso` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_empleado_para` INT(10) UNSIGNED NOT NULL,
  `id_empleado_de` INT(10) UNSIGNED DEFAULT NULL,
  `id_tarea` INT(10) UNSIGNED DEFAULT NULL,
  `tipo` VARCHAR(40) NOT NULL DEFAULT 'info',
  `titulo` VARCHAR(200) NOT NULL,
  `detalle` TEXT DEFAULT NULL,
  `url` VARCHAR(300) DEFAULT NULL,
  `confirmado` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_crmaviso_de` (`id_empleado_de`),
  KEY `fk_crmaviso_tarea` (`id_tarea`),
  KEY `idx_crmaviso_bandeja` (`id_empleado_para`, `confirmado`, `fecha`),
  KEY `idx_crmaviso_tipo` (`tipo`),
  CONSTRAINT `fk_crmaviso_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_crmaviso_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_crmaviso_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. CONTROL DE AVISOS DE VENCIMIENTO DE TAREAS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarea_aviso_vencimiento` (
  `id_tarea` INT(10) UNSIGNED NOT NULL,
  `fecha_aviso` DATE NOT NULL,
  PRIMARY KEY (`id_tarea`, `fecha_aviso`),
  CONSTRAINT `fk_tavenc_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. CIRCUITO DE SOLICITUD DE BAJA DE TAREAS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarea_solicitud_baja` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_tarea` INT(10) UNSIGNED NOT NULL,
  `id_solicita` INT(10) UNSIGNED NOT NULL,
  `motivo` TEXT NOT NULL,
  `estado` ENUM('pendiente_coord','pendiente_general','aceptada','rechazada') NOT NULL DEFAULT 'pendiente_coord',
  `id_revisa_coord` INT(10) UNSIGNED DEFAULT NULL,
  `nota_coord` TEXT DEFAULT NULL,
  `id_revisa_general` INT(10) UNSIGNED DEFAULT NULL,
  `nota_general` TEXT DEFAULT NULL,
  `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tsb_solicita` (`id_solicita`),
  KEY `fk_tsb_coord` (`id_revisa_coord`),
  KEY `fk_tsb_general` (`id_revisa_general`),
  KEY `idx_tsb_tarea` (`id_tarea`),
  KEY `idx_tsb_estado` (`estado`),
  KEY `idx_tsb_pendientes` (`estado`, `fecha_solicitud`),
  CONSTRAINT `fk_tsb_coord` FOREIGN KEY (`id_revisa_coord`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_general` FOREIGN KEY (`id_revisa_general`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_solicita` FOREIGN KEY (`id_solicita`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. PORTAL CLIENTE: AUTOINSCRIPCIÓN A ACTIVIDADES
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evento_inscripcion` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_evento` INT(10) UNSIGNED NOT NULL,
  `id_cliente` INT(10) UNSIGNED NOT NULL,
  `estado` ENUM('inscrito','asistio','ausente') NOT NULL DEFAULT 'inscrito',
  `fecha_inscripcion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evins` (`id_evento`, `id_cliente`),
  KEY `idx_evins_cliente` (`id_cliente`, `estado`),
  CONSTRAINT `fk_evins_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE,
  CONSTRAINT `fk_evins_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. MAPA INTERACTIVO Y CAMPOS GEOGRÁFICOS EN LOCALIDAD Y EVENTO
-- ----------------------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_crm_migrar_mapa_y_columnas`$$
CREATE PROCEDURE `sp_crm_migrar_mapa_y_columnas`()
BEGIN
  -- A. Localidad: latitud, longitud y geojson
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'localidad' AND COLUMN_NAME = 'latitud'
  ) THEN
    ALTER TABLE `localidad` ADD COLUMN `latitud` DECIMAL(10,7) NULL AFTER `id_provincia`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'localidad' AND COLUMN_NAME = 'longitud'
  ) THEN
    ALTER TABLE `localidad` ADD COLUMN `longitud` DECIMAL(10,7) NULL AFTER `latitud`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'localidad' AND COLUMN_NAME = 'poligono_geojson'
  ) THEN
    ALTER TABLE `localidad` ADD COLUMN `poligono_geojson` LONGTEXT NULL AFTER `longitud`;
  END IF;

  -- B. Evento: id_localidad para ubicación geográfica
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evento' AND COLUMN_NAME = 'id_localidad'
  ) THEN
    ALTER TABLE `evento` ADD COLUMN `id_localidad` SMALLINT UNSIGNED NULL AFTER `id_cliente`;
    ALTER TABLE `evento` ADD INDEX `idx_evento_localidad_activo` (`id_localidad`, `activo`);
    ALTER TABLE `evento` ADD CONSTRAINT `fk_evento_localidad` FOREIGN KEY (`id_localidad`) REFERENCES `localidad`(`id_localidad`);
  END IF;

  -- C. Evento: autoinscripción y público objetivo
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evento' AND COLUMN_NAME = 'autoinscripcion'
  ) THEN
    ALTER TABLE `evento` ADD COLUMN `autoinscripcion` TINYINT(1) NOT NULL DEFAULT 0 AFTER `resultado`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evento' AND COLUMN_NAME = 'publico'
  ) THEN
    ALTER TABLE `evento` ADD COLUMN `publico` ENUM('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos' AFTER `autoinscripcion`;
  END IF;

  -- D. Area: flags operativas
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'area' AND COLUMN_NAME = 'atiende_clientes'
  ) THEN
    ALTER TABLE `area` ADD COLUMN `atiende_clientes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activo`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'area' AND COLUMN_NAME = 'asignable_empleado'
  ) THEN
    ALTER TABLE `area` ADD COLUMN `asignable_empleado` TINYINT(1) NOT NULL DEFAULT 1 AFTER `atiende_clientes`;
  END IF;

  -- E. Tarea: estado cancelada
  ALTER TABLE `tarea` MODIFY `estado` ENUM('no_iniciada','en_proceso','pausada','terminada','cancelada') NOT NULL DEFAULT 'no_iniciada';

  -- F. Empleado: campos extendidos de legajo RRHH
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'fecha_nacimiento') THEN
    ALTER TABLE `empleado` ADD COLUMN `fecha_nacimiento` DATE NULL AFTER `dni`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'telefono_alt') THEN
    ALTER TABLE `empleado` ADD COLUMN `telefono_alt` VARCHAR(30) NULL AFTER `telefono`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'estado_civil') THEN
    ALTER TABLE `empleado` ADD COLUMN `estado_civil` VARCHAR(40) NULL AFTER `direccion`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'localidad_domicilio') THEN
    ALTER TABLE `empleado` ADD COLUMN `localidad_domicilio` VARCHAR(100) NULL AFTER `estado_civil`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'provincia_domicilio') THEN
    ALTER TABLE `empleado` ADD COLUMN `provincia_domicilio` VARCHAR(80) NULL AFTER `localidad_domicilio`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'puesto') THEN
    ALTER TABLE `empleado` ADD COLUMN `puesto` VARCHAR(120) NULL AFTER `provincia_domicilio`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'tipo_vinculo') THEN
    ALTER TABLE `empleado` ADD COLUMN `tipo_vinculo` VARCHAR(80) NULL AFTER `puesto`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'estado_laboral') THEN
    ALTER TABLE `empleado` ADD COLUMN `estado_laboral` VARCHAR(40) NOT NULL DEFAULT 'activo' AFTER `tipo_vinculo`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'fecha_baja') THEN
    ALTER TABLE `empleado` ADD COLUMN `fecha_baja` DATE NULL AFTER `estado_laboral`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'motivo_baja') THEN
    ALTER TABLE `empleado` ADD COLUMN `motivo_baja` VARCHAR(200) NULL AFTER `fecha_baja`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'modalidad') THEN
    ALTER TABLE `empleado` ADD COLUMN `modalidad` VARCHAR(40) NULL AFTER `motivo_baja`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'horario_jornada') THEN
    ALTER TABLE `empleado` ADD COLUMN `horario_jornada` VARCHAR(120) NULL AFTER `modalidad`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'condicion_fiscal') THEN
    ALTER TABLE `empleado` ADD COLUMN `condicion_fiscal` VARCHAR(60) NULL AFTER `horario_jornada`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'cuit_facturacion') THEN
    ALTER TABLE `empleado` ADD COLUMN `cuit_facturacion` VARCHAR(13) NULL AFTER `condicion_fiscal`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleado' AND COLUMN_NAME = 'id_responsable') THEN
    ALTER TABLE `empleado` ADD COLUMN `id_responsable` INT UNSIGNED NULL AFTER `id_area`;
  END IF;

  -- G. Atencion y Cliente: compatibilidad de canales y orígenes
  ALTER TABLE `cliente` MODIFY `origen_contacto` ENUM('web','presencial','whatsapp','redes') NULL;
  ALTER TABLE `atencion` MODIFY `estado` ENUM('pendiente','abierta','en_atencion','cerrada','derivada','cancelada') NOT NULL DEFAULT 'abierta';
  ALTER TABLE `atencion` MODIFY `canal` ENUM('web','presencial','whatsapp','redes') NOT NULL DEFAULT 'presencial';

  -- H. Auditoría y trazabilidad de alta de clientes por empleado
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cliente' AND COLUMN_NAME = 'id_empleado_alta') THEN
    ALTER TABLE `cliente` ADD COLUMN `id_empleado_alta` INT(10) UNSIGNED DEFAULT NULL AFTER `id_area_afectada`;
    ALTER TABLE `cliente` ADD CONSTRAINT `fk_cliente_empleado_alta` FOREIGN KEY (`id_empleado_alta`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL;
  END IF;

END$$

DELIMITER ;

CALL `sp_crm_migrar_mapa_y_columnas`();
DROP PROCEDURE IF EXISTS `sp_crm_migrar_mapa_y_columnas`;

-- ----------------------------------------------------------------------------
-- 10. COMPATIBILIDAD GEOGRÁFICA DE EVENTOS EXISTENTES (Sin sobreescribir)
-- ----------------------------------------------------------------------------
UPDATE evento ev
INNER JOIN cliente c ON c.id_cliente = ev.id_cliente
SET ev.id_localidad = c.id_localidad
WHERE ev.id_localidad IS NULL AND c.id_localidad IS NOT NULL;

-- ----------------------------------------------------------------------------
-- 11. COORDENADAS BASE PARA LOCALIDADES DE MISIONES (Solo si están vacías)
--     Fuente: GeoNames Argentina / OpenStreetMap. Puntos geográficos centrales.
-- ----------------------------------------------------------------------------
UPDATE localidad l
INNER JOIN provincia p ON p.id_provincia = l.id_provincia
SET
  l.latitud = IF(l.latitud IS NULL, CASE l.nombre
    WHEN 'Posadas' THEN -27.3918400
    WHEN 'Garupá' THEN -27.4831500
    WHEN 'Fachinal' THEN -27.6291600
    WHEN 'Apóstoles' THEN -27.9142100
    WHEN 'Azara' THEN -28.0606700
    WHEN 'San José' THEN -27.7691600
    WHEN 'Tres Capones' THEN -28.0043100
    WHEN 'Aristóbulo del Valle' THEN -27.0957100
    WHEN 'Campo Grande' THEN -27.2077000
    WHEN 'Campo Ramón' THEN -27.4549000
    WHEN 'Dos de Mayo' THEN -27.0199800
    WHEN 'Candelaria' THEN -27.4617000
    WHEN 'Bonpland' THEN -27.4834700
    WHEN 'Cerro Corá' THEN -27.5137700
    WHEN 'Loreto' THEN -27.3317700
    WHEN 'Santa Ana' THEN -27.3692400
    WHEN 'Profundidad' THEN -27.5613100
    WHEN 'Mártires' THEN -27.4226500
    WHEN 'Concepción de la Sierra' THEN -27.9822500
    WHEN 'Santa María' THEN -27.9349000
    WHEN 'Eldorado' THEN -26.4045300
    WHEN 'Puerto Piray' THEN -26.4693700
    WHEN 'Colonia Victoria' THEN -26.3312400
    WHEN 'Alicia' THEN -27.3781456
    WHEN 'San Vicente' THEN -26.9932723
    WHEN 'San Antonio' THEN -26.0599300
    WHEN 'Bernardo de Irigoyen' THEN -26.2554300
    WHEN 'Comandante Andresito' THEN -25.6700000
    WHEN 'El Soberbio' THEN -27.2966200
    WHEN 'Puerto Iguazú' THEN -25.5991200
    WHEN 'Wanda' THEN -25.9733700
    WHEN 'Puerto Libertad' THEN -25.9195800
    WHEN 'Leandro N. Alem' THEN -27.6037600
    WHEN 'Almafuerte' THEN -27.5082500
    WHEN 'Cerro Azul' THEN -27.6336300
    WHEN 'Gobernador Roca' THEN -27.1905700
    WHEN 'Olegario V. Andrade' THEN -27.5670500
    WHEN 'Panambí' THEN -27.7219900
    WHEN 'Jardín América' THEN -27.0434600
    WHEN 'Puerto Rico' THEN -26.8089000
    WHEN 'Ruiz de Montoya' THEN -26.9708800
    WHEN 'Capioví' THEN -26.9293600
    WHEN 'Garuhapé' THEN -26.8211600
    WHEN 'Montecarlo' THEN -26.5684900
    WHEN 'Caraguatay' THEN -26.6058700
    WHEN 'Oberá' THEN -27.4868300
    WHEN 'Campo Viera' THEN -27.3327100
    WHEN 'General Alvear' THEN -27.4261100
    WHEN 'Los Helechos' THEN -27.5570000
    WHEN 'San Ignacio' THEN -27.2586500
    WHEN 'Corpus' THEN -27.1294700
    WHEN 'Santo Pipó' THEN -27.1414700
    WHEN 'Hipólito Yrigoyen' THEN -27.0920000
    WHEN 'San Javier' THEN -27.8739800
    WHEN 'Florentino Ameghino' THEN -27.6333500
    WHEN 'San Pedro' THEN -26.6218000
    WHEN 'Paraíso' THEN -26.6870700
    WHEN 'Veinticinco de Mayo' THEN -27.3779600
    ELSE NULL
  END, l.latitud),
  l.longitud = IF(l.longitud IS NULL, CASE l.nombre
    WHEN 'Posadas' THEN -55.9237900
    WHEN 'Garupá' THEN -55.8292400
    WHEN 'Fachinal' THEN -55.7050600
    WHEN 'Apóstoles' THEN -55.7535500
    WHEN 'Azara' THEN -55.6770900
    WHEN 'San José' THEN -55.7803300
    WHEN 'Tres Capones' THEN -55.6093500
    WHEN 'Aristóbulo del Valle' THEN -54.8934400
    WHEN 'Campo Grande' THEN -54.9797700
    WHEN 'Campo Ramón' THEN -55.0212200
    WHEN 'Dos de Mayo' THEN -54.6846200
    WHEN 'Candelaria' THEN -55.7449000
    WHEN 'Bonpland' THEN -55.4778400
    WHEN 'Cerro Corá' THEN -55.6089500
    WHEN 'Loreto' THEN -55.5249900
    WHEN 'Santa Ana' THEN -55.5816700
    WHEN 'Profundidad' THEN -55.7050600
    WHEN 'Mártires' THEN -55.3765900
    WHEN 'Concepción de la Sierra' THEN -55.5208000
    WHEN 'Santa María' THEN -55.4074200
    WHEN 'Eldorado' THEN -54.6184700
    WHEN 'Puerto Piray' THEN -54.7073600
    WHEN 'Colonia Victoria' THEN -54.6214100
    WHEN 'Alicia' THEN -54.3518686
    WHEN 'San Vicente' THEN -54.4866356
    WHEN 'San Antonio' THEN -53.7357800
    WHEN 'Bernardo de Irigoyen' THEN -53.6468200
    WHEN 'Comandante Andresito' THEN -54.0455200
    WHEN 'El Soberbio' THEN -54.1981100
    WHEN 'Puerto Iguazú' THEN -54.5735500
    WHEN 'Wanda' THEN -54.5630400
    WHEN 'Puerto Libertad' THEN -54.5822900
    WHEN 'Leandro N. Alem' THEN -55.3236500
    WHEN 'Almafuerte' THEN -55.4025800
    WHEN 'Cerro Azul' THEN -55.4974600
    WHEN 'Gobernador Roca' THEN -55.4692700
    WHEN 'Olegario V. Andrade' THEN -55.5020800
    WHEN 'Panambí' THEN -54.9148600
    WHEN 'Jardín América' THEN -55.2269800
    WHEN 'Puerto Rico' THEN -55.0214700
    WHEN 'Ruiz de Montoya' THEN -55.0506700
    WHEN 'Capioví' THEN -55.0577300
    WHEN 'Garuhapé' THEN -54.9577400
    WHEN 'Montecarlo' THEN -54.7579000
    WHEN 'Caraguatay' THEN -54.7809300
    WHEN 'Oberá' THEN -55.1199400
    WHEN 'Campo Viera' THEN -55.0565100
    WHEN 'General Alvear' THEN -55.1691600
    WHEN 'Los Helechos' THEN -55.0760000
    WHEN 'San Ignacio' THEN -55.5389700
    WHEN 'Corpus' THEN -55.5108300
    WHEN 'Santo Pipó' THEN -55.4094500
    WHEN 'Hipólito Yrigoyen' THEN -55.2860000
    WHEN 'San Javier' THEN -55.1348500
    WHEN 'Florentino Ameghino' THEN -55.0837200
    WHEN 'San Pedro' THEN -54.1090200
    WHEN 'Paraíso' THEN -54.2030700
    WHEN 'Veinticinco de Mayo' THEN -54.7450500
    ELSE NULL
  END, l.longitud)
WHERE p.nombre = 'Misiones'
  AND (l.latitud IS NULL OR l.longitud IS NULL);

-- ----------------------------------------------------------------------------
-- 13. DERIVACIONES DE CLIENTES ENTRE ÁREAS Y SEGUIMIENTO HISTÓRICO
-- ----------------------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_crm_migrar_derivaciones`$$
CREATE PROCEDURE `sp_crm_migrar_derivaciones`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atencion' AND COLUMN_NAME = 'id_area_origen'
  ) THEN
    ALTER TABLE `atencion` ADD COLUMN `id_area_origen` SMALLINT(5) UNSIGNED DEFAULT NULL AFTER `id_area`;
    ALTER TABLE `atencion` ADD CONSTRAINT `fk_atencion_area_origen` FOREIGN KEY (`id_area_origen`) REFERENCES `area`(`id_area`) ON DELETE SET NULL;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atencion' AND COLUMN_NAME = 'id_empleado_origen'
  ) THEN
    ALTER TABLE `atencion` ADD COLUMN `id_empleado_origen` INT(10) UNSIGNED DEFAULT NULL AFTER `id_empleado`;
    ALTER TABLE `atencion` ADD CONSTRAINT `fk_atencion_empleado_origen` FOREIGN KEY (`id_empleado_origen`) REFERENCES `empleado`(`id_empleado`) ON DELETE SET NULL;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atencion' AND COLUMN_NAME = 'fecha_derivacion'
  ) THEN
    ALTER TABLE `atencion` ADD COLUMN `fecha_derivacion` DATETIME DEFAULT NULL AFTER `fecha_turno`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atencion_seguimiento'
  ) THEN
    CREATE TABLE `atencion_seguimiento` (
      `id_seguimiento` INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `id_atencion` INT(10) UNSIGNED NOT NULL,
      `id_area` SMALLINT(5) UNSIGNED NOT NULL,
      `id_empleado` INT(10) UNSIGNED DEFAULT NULL,
      `estado_anterior` VARCHAR(30) DEFAULT NULL,
      `estado_nuevo` VARCHAR(30) NOT NULL,
      `accion` VARCHAR(50) NOT NULL,
      `observacion` TEXT DEFAULT NULL,
      `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY `idx_atseg_atencion` (`id_atencion`),
      KEY `idx_atseg_area` (`id_area`),
      KEY `idx_atseg_empleado` (`id_empleado`),
      CONSTRAINT `fk_atseg_atencion` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`) ON DELETE CASCADE,
      CONSTRAINT `fk_atseg_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`) ON DELETE CASCADE,
      CONSTRAINT `fk_atseg_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  END IF;
END$$

DELIMITER ;

CALL `sp_crm_migrar_derivaciones`();
DROP PROCEDURE IF EXISTS `sp_crm_migrar_derivaciones`;

-- ----------------------------------------------------------------------------
-- 14. SOLICITUDES DE ALTA WEB Y AVISOS PARA CLIENTES
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cliente_solicitud` (
  `id_solicitud` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(150) NOT NULL,
  `correo` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(30) NULL,
  `dni` VARCHAR(15) NULL,
  `cuil_cuit` VARCHAR(13) NOT NULL,
  `nombre_negocio` VARCHAR(150) NULL,
  `id_localidad` SMALLINT UNSIGNED NOT NULL,
  `tipo_autodeclarado` ENUM('emprendedor','empresa') NOT NULL DEFAULT 'emprendedor',
  `sector` VARCHAR(80) NULL,
  `sector_otro` VARCHAR(120) NULL,
  `anios_funcionamiento` VARCHAR(40) NULL,
  `personas_involucradas` VARCHAR(40) NULL,
  `personal_formal` TINYINT(1) NOT NULL DEFAULT 0,
  `producto_servicio` TEXT NULL,
  `contrasena_hash` VARCHAR(255) NOT NULL,
  `estado` ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  `sugerencia_tipo` ENUM('emprendedor','empresa') NULL,
  `tipo_asignado` ENUM('emprendedor','empresa') NULL,
  `id_cliente` INT UNSIGNED NULL,
  `id_revisa` INT UNSIGNED NULL,
  `nota_revision` TEXT NULL,
  `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_resolucion` DATETIME NULL,
  INDEX `idx_csol_estado` (`estado`, `fecha_solicitud`),
  INDEX `idx_csol_correo` (`correo`),
  CONSTRAINT `fk_csol_loc` FOREIGN KEY (`id_localidad`) REFERENCES `localidad`(`id_localidad`),
  CONSTRAINT `fk_csol_cli` FOREIGN KEY (`id_cliente`) REFERENCES `cliente`(`id_cliente`),
  CONSTRAINT `fk_csol_rev` FOREIGN KEY (`id_revisa`) REFERENCES `empleado`(`id_empleado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cliente_aviso` (
  `id_aviso` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `detalle` TEXT NULL,
  `leido` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_claviso_user` (`id_usuario`, `leido`, `fecha`),
  CONSTRAINT `fk_claviso_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_cliente`(`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. REGISTRO DE VERSIÓN DE MIGRACIÓN
-- ----------------------------------------------------------------------------
INSERT INTO `crm_schema_version` (`clave`, `fecha`)
VALUES
  ('v2_migracion_general_ademi', NOW()),
  ('cliente_solicitud_v1', NOW()),
  ('cliente_aviso_v1', NOW())
ON DUPLICATE KEY UPDATE `fecha` = NOW();

SET FOREIGN_KEY_CHECKS = 1;

-- Fin de BDD.sql
