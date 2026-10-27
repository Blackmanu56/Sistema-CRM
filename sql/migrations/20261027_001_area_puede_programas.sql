-- CRM ADEMI: per-area switch for the Programas module (programme catalogue management).
-- Additive and non-destructive: no existing programme, role or permission is touched.
--
-- Areas seeded to 1 preserve the behaviour that exists today:
--   * `sistemas` -> public/sistemas/programas.php is the Sistemas sidebar entry point
--                   (includes/staff/leftbar.php:346-348) and is reachable by any user
--                   whose active area is sistemas, because crm_puede_gestionar_programas()
--                   (includes/crm_extra.php:3063-3071) returns true for acceso_area()=='sistemas'.
--   * `general`  -> public/general/programas.php is the only other entry point
--                   (leftbar.php:389-391), gated by acceso_puede('general') plus
--                   superadmin / coordinador general, and it is also the default landing
--                   area for both roles (includes/acceso.php:1784-1788).
--   * every other area exposes no Programas entry point, so it stays 0.
--
-- Deliberately NOT seeded: emprendedores and comercio own objectives via
-- programa_item.id_area. That is programme content ownership, not module access, and
-- enrolment (crm_puede_anotar_programa()) is a separate check that reads no area column.
--
-- Idempotent: the ALTER is a no-op on re-run, and the seed only executes the first time
-- the column is created, so a later manual change is never overwritten.

SET @crm_col_previa = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'area'
    AND COLUMN_NAME = 'puede_programas'
);

ALTER TABLE `area`
  ADD COLUMN IF NOT EXISTS `puede_programas` TINYINT(1) NOT NULL DEFAULT 0;

SET @crm_seed = IF(
  @crm_col_previa = 0,
  'UPDATE `area` SET `puede_programas` = 1 WHERE `clave` IN (''sistemas'', ''general'')',
  'SELECT 1'
);
PREPARE crm_seed_stmt FROM @crm_seed;
EXECUTE crm_seed_stmt;
DEALLOCATE PREPARE crm_seed_stmt;
