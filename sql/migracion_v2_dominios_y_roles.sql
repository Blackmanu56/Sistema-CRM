-- ============================================================================
-- CRM ADEMI - MIGRACIÓN VERSIONADA V2: DOMINIOS Y ROLES ADICIONALES
-- Archivo: sql/migracion_v2_dominios_y_roles.sql
-- Propósito: Incorporar soporte para el módulo Dominios de Sistemas,
--            historial de renovaciones, alertas de vencimiento y roles adicionales.
--
-- INSTRUCCIONES DE APLICACIÓN SEGURA (PARA FACUNDO / INTEGRACIÓN):
-- 1. Realizar copia de seguridad previa de la base de datos de destino:
--      mysqldump -u [usuario] -p [base_de_datos] > backup_previo.sql
-- 2. Este script es IDEMPOTENTE: puede ejecutarse múltiples veces sin borrar
--    ni duplicar registros ni alterar información existente.
-- 3. Ejecución recomendada por consola MySQL / phpMyAdmin:
--      mysql -u [usuario] -p [base_de_datos] < sql/migracion_v2_dominios_y_roles.sql
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. TABLA DE CONTROL DE VERSIONES DE ESQUEMA (Idempotente)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `crm_schema_version` (
  `clave` VARCHAR(50) NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. MÓDULO DOMINIOS (SISTEMAS): TABLA PRINCIPAL DE DOMINIOS
--    Almacena dominios web, proveedores, responsables, fechas de vigencia y estado.
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
-- 3. MÓDULO DOMINIOS: HISTORIAL DE RENOVACIONES
--    Audita cada renovación de dominio con fechas previa y nueva.
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
-- 4. ROLES ADICIONALES Y CAMBIO DE ÁREA: empleado_rol_adicional
--    Permite que un empleado tenga funciones en más de un área simultáneamente.
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
-- 5. BANDEJA DE ALERTAS Y NOTIFICACIONES: crm_aviso
--    Utilizada para avisos de dominios por vencer/vencidos y eventos de tareas.
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
-- 6. CONTROL DE AVISOS DE VENCIMIENTO DE TAREAS: tarea_aviso_vencimiento
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarea_aviso_vencimiento` (
  `id_tarea` INT(10) UNSIGNED NOT NULL,
  `fecha_aviso` DATE NOT NULL,
  PRIMARY KEY (`id_tarea`, `fecha_aviso`),
  CONSTRAINT `fk_tavenc_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. SOLICITUDES DE ELIMINACIÓN DE TAREAS: tarea_solicitud_baja
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
-- 8. COMPATIBILIDAD DE COLUMNAS EN TABLAS BASE (Idempotente mediante Procedimiento)
-- ----------------------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_crm_aplicar_columnas_compatibilidad`$$
CREATE PROCEDURE `sp_crm_aplicar_columnas_compatibilidad`()
BEGIN
  -- area: atiende_clientes
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'area' AND COLUMN_NAME = 'atiende_clientes'
  ) THEN
    ALTER TABLE `area` ADD COLUMN `atiende_clientes` TINYINT(1) NOT NULL DEFAULT 0 AFTER `activo`;
  END IF;

  -- area: asignable_empleado
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'area' AND COLUMN_NAME = 'asignable_empleado'
  ) THEN
    ALTER TABLE `area` ADD COLUMN `asignable_empleado` TINYINT(1) NOT NULL DEFAULT 1 AFTER `atiende_clientes`;
  END IF;

  -- tarea: verificar estado 'cancelada'
  ALTER TABLE `tarea` MODIFY `estado` ENUM('no_iniciada','en_proceso','pausada','terminada','cancelada') NOT NULL DEFAULT 'no_iniciada';

END$$

DELIMITER ;

CALL `sp_crm_aplicar_columnas_compatibilidad`();
DROP PROCEDURE IF EXISTS `sp_crm_aplicar_columnas_compatibilidad`;

-- ----------------------------------------------------------------------------
-- 9. REGISTRO DE VERSIÓN DE MIGRACIÓN
-- ----------------------------------------------------------------------------
INSERT INTO `crm_schema_version` (`clave`, `fecha`)
VALUES ('v2_dominios_y_roles_adicionales', NOW())
ON DUPLICATE KEY UPDATE `fecha` = NOW();

SET FOREIGN_KEY_CHECKS = 1;

-- Fin de migración V2
