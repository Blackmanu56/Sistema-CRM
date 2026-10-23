-- CRM ADEMI: manual 3/6 month follow-ups for enterprise clients.
-- Idempotent additive migration; existing CRM records are not modified.
CREATE TABLE IF NOT EXISTS `empresa_seguimiento` (
  `id_seguimiento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_atencion_referencia` int(10) unsigned NOT NULL,
  `tipo_seguimiento` enum('3_meses','6_meses') NOT NULL,
  `fecha_referencia` date NOT NULL,
  `fecha_prevista` date NOT NULL,
  `fecha_realizacion` date DEFAULT NULL,
  `estado` enum('pendiente','realizado') NOT NULL DEFAULT 'pendiente',
  `situacion_resultado` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `id_empleado_responsable` int(10) unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_seguimiento`),
  UNIQUE KEY `uq_empresa_seguimiento_atencion_hito` (`id_atencion_referencia`,`tipo_seguimiento`),
  KEY `idx_empresa_seguimiento_cliente_fecha` (`id_cliente`,`fecha_prevista`),
  KEY `idx_empresa_seguimiento_responsable` (`id_empleado_responsable`),
  CONSTRAINT `fk_empresa_seguimiento_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_empresa_seguimiento_atencion` FOREIGN KEY (`id_atencion_referencia`) REFERENCES `atencion` (`id_atencion`),
  CONSTRAINT `fk_empresa_seguimiento_responsable` FOREIGN KEY (`id_empleado_responsable`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
