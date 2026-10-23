-- CRM ADEMI: record the employee who created each activity/event.
-- Existing events intentionally remain NULL (creator unknown).
-- Safe to run repeatedly; only adds the nullable creator reference and its index/FK.

SET @crm_schema = DATABASE();

SET @crm_has_creator = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'evento'
    AND COLUMN_NAME = 'id_empleado_creador'
);
SET @crm_ddl = IF(
  @crm_has_creator = 0,
  'ALTER TABLE `evento` ADD COLUMN `id_empleado_creador` int(10) unsigned DEFAULT NULL AFTER `id_empleado`',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

SET @crm_has_creator_index = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'evento'
    AND INDEX_NAME = 'idx_evento_creador'
);
SET @crm_ddl = IF(
  @crm_has_creator_index = 0,
  'ALTER TABLE `evento` ADD KEY `idx_evento_creador` (`id_empleado_creador`)',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;

SET @crm_has_creator_fk = (
  SELECT COUNT(*)
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @crm_schema
    AND TABLE_NAME = 'evento'
    AND COLUMN_NAME = 'id_empleado_creador'
    AND REFERENCED_TABLE_NAME = 'empleado'
    AND REFERENCED_COLUMN_NAME = 'id_empleado'
);
SET @crm_ddl = IF(
  @crm_has_creator_fk = 0,
  'ALTER TABLE `evento` ADD CONSTRAINT `fk_evento_creador` FOREIGN KEY (`id_empleado_creador`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE crm_stmt FROM @crm_ddl;
EXECUTE crm_stmt;
DEALLOCATE PREPARE crm_stmt;
