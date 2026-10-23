-- CRM ADEMI: private client digital-file metadata.
-- Safe to run repeatedly: creates only this new table and preserves all existing data.
-- File contents remain outside the web root; only metadata and private storage keys are stored here.
CREATE TABLE IF NOT EXISTS `cliente_documento_legajo` (
  `id_documento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `categoria` varchar(40) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `archivo_key` varchar(40) NOT NULL,
  `mime_type` varchar(127) NOT NULL,
  `tamano_bytes` bigint(20) unsigned NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  `id_empleado_carga` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_documento`),
  UNIQUE KEY `uq_cliente_documento_legajo_key` (`archivo_key`),
  KEY `idx_cliente_documento_legajo_cliente_fecha` (`id_cliente`,`fecha_carga`),
  KEY `idx_cliente_documento_legajo_cargado_por` (`id_empleado_carga`),
  CONSTRAINT `fk_cliente_documento_legajo_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_cliente_documento_legajo_empleado` FOREIGN KEY (`id_empleado_carga`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
