-- CRM ADEMI: support manual enterprise follow-ups alongside the automatic 3/6-month milestones.
-- Requires 20261023_001_empresa_seguimiento.sql first (table must exist).
-- Additive and non-destructive: existing follow-up rows and their atencion links are untouched.
-- Manual rows are identified by tipo_seguimiento='manual'. The automatic milestone uniqueness
-- (atencion + hito) moves to a generated column that is NULL for manual rows, so unlimited
-- manual follow-ups can coexist per company while automatic 3/6-month hits stay unique.
SET @crm_schema = DATABASE();
SET @crm_followup_table = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
);

-- 1) Extend the hito enum with the manual marker (existing values are preserved verbatim).
SET @crm_has_manual_type = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'tipo_seguimiento'
    AND COLUMN_TYPE LIKE '%manual%'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_manual_type = 0,
  'ALTER TABLE `empresa_seguimiento` MODIFY COLUMN `tipo_seguimiento` enum(''3_meses'',''6_meses'',''manual'') NOT NULL',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

-- 2) Motive for manually scheduled follow-ups (NULL for automatic milestones).
SET @crm_has_motivo = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'motivo'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_motivo = 0,
  'ALTER TABLE `empresa_seguimiento` ADD COLUMN `motivo` varchar(255) DEFAULT NULL AFTER `tipo_seguimiento`',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

-- 3) Traceability: employee who scheduled the follow-up (who programs it).
SET @crm_has_creator = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'id_empleado_creador'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_creator = 0,
  'ALTER TABLE `empresa_seguimiento` ADD COLUMN `id_empleado_creador` int(10) unsigned DEFAULT NULL AFTER `id_empleado_responsable`',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

-- 4) Generated key: hito label for automatic milestones, NULL for manual follow-ups.
SET @crm_has_hito_key = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'hito_clave'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_hito_key = 0,
  'ALTER TABLE `empresa_seguimiento` ADD COLUMN `hito_clave` varchar(16) GENERATED ALWAYS AS (IF(`tipo_seguimiento`=''manual'',NULL,`tipo_seguimiento`)) STORED',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

-- 5) Foreign key for the scheduling employee (mirrors the responsable constraint: SET NULL).
--    Column existence is re-checked here: step 3 captured its flag before its own ALTER ran.
SET @crm_has_creator_now = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'id_empleado_creador'
);
SET @crm_has_creator_fk = (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND CONSTRAINT_NAME = 'fk_empresa_seguimiento_creador'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_creator_now = 1 AND @crm_has_creator_fk = 0,
  'ALTER TABLE `empresa_seguimiento` ADD CONSTRAINT `fk_empresa_seguimiento_creador` FOREIGN KEY (`id_empleado_creador`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

-- 6) Move the automatic milestone unique key onto the generated column.
--    Old form: (id_atencion_referencia, tipo_seguimiento) -> blocked every manual row.
--    New form: (id_atencion_referencia, hito_clave)      -> manuals are NULL, so unlimited
--    manual rows can share the same atencion; automatic 3/6 hits stay unique per atencion.
--    Generated-column existence is re-checked here (step 4 captured it before its ALTER).
SET @crm_has_hito_key_now = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'hito_clave'
);
SET @crm_old_unique_on_tipo = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND INDEX_NAME = 'uq_empresa_seguimiento_atencion_hito'
    AND COLUMN_NAME = 'tipo_seguimiento'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_hito_key_now = 1 AND @crm_old_unique_on_tipo > 0,
  'ALTER TABLE `empresa_seguimiento` DROP INDEX `uq_empresa_seguimiento_atencion_hito`, ADD UNIQUE KEY `uq_empresa_seguimiento_atencion_hito` (`id_atencion_referencia`,`hito_clave`)',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;
