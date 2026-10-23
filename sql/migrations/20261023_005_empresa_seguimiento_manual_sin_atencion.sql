-- CRM ADEMI: allow manual enterprise follow-ups without a reference atencion.
-- Requires 20261023_001 (table) and 20261023_004 (manual follow-ups) first.
-- Additive and non-destructive: existing rows keep their values (every current row has
-- both columns set, so the new CHECK admits them unchanged).
-- Rules enforced from now on (CHECK constraint, paired and atomic):
--   * Automatic 3/6-month milestones: id_atencion_referencia AND fecha_referencia NOT NULL.
--   * Manual follow-ups: both NULL (no reference atencion) or both set (atencion kept).
--   * Mixed pairs are rejected for every tipo. The unique key (id_atencion_referencia,
--     hito_clave) needs no change: manuals keep hito_clave NULL and NULL atencion, so
--     unlimited manuals coexist; automatics stay unique because both columns are forced.
SET @crm_schema = DATABASE();
SET @crm_followup_table = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
);

-- 1) Make the reference atencion optional at the column level (FK stays: NULL skips it).
SET @crm_atencion_nullable = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'id_atencion_referencia'
    AND IS_NULLABLE = 'YES'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_atencion_nullable = 0,
  'ALTER TABLE `empresa_seguimiento` MODIFY COLUMN `id_atencion_referencia` int(10) unsigned DEFAULT NULL',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

-- 2) Make the reference date optional; it always mirrors the reference atencion.
SET @crm_fecha_ref_nullable = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'fecha_referencia'
    AND IS_NULLABLE = 'YES'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_fecha_ref_nullable = 0,
  'ALTER TABLE `empresa_seguimiento` MODIFY COLUMN `fecha_referencia` date DEFAULT NULL',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

-- 3) Enforce who may omit the pair: automatics always require it, manuals may omit both.
--    Existence is re-checked here so this step works on a fresh run and on re-runs.
SET @crm_has_ref_check = (
  SELECT COUNT(*)
  FROM information_schema.CHECK_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND CONSTRAINT_NAME = 'chk_empresa_seguimiento_referencia'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_ref_check = 0,
  'ALTER TABLE `empresa_seguimiento` ADD CONSTRAINT `chk_empresa_seguimiento_referencia` CHECK ((`id_atencion_referencia` IS NOT NULL AND `fecha_referencia` IS NOT NULL) OR (`tipo_seguimiento` = ''manual'' AND `id_atencion_referencia` IS NULL AND `fecha_referencia` IS NULL))',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;
