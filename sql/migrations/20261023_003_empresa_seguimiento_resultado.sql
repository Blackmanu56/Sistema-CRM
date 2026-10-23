-- CRM ADEMI: add an optional outcome classification to enterprise follow-ups.
-- Requires 20261023_001_empresa_seguimiento.sql first.
-- Existing follow-ups remain NULL; no existing values or rows are rewritten.
SET @crm_schema = DATABASE();
SET @crm_followup_table = (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
);
SET @crm_has_outcome = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'empresa_seguimiento'
    AND COLUMN_NAME = 'resultado_clasificacion'
);
SET @crm_ddl = IF(
  @crm_followup_table = 1 AND @crm_has_outcome = 0,
  'ALTER TABLE `empresa_seguimiento` ADD COLUMN `resultado_clasificacion` enum(''positivo'',''neutro'',''negativo'') DEFAULT NULL AFTER `estado`',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;
