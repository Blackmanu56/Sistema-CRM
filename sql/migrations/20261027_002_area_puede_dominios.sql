-- CRM ADEMI: per-area switch for the Dominios module (domain registry management).
-- Additive and non-destructive: no existing domain, role or permission is touched.
--
-- Area seeded to 1 preserves the behaviour that exists today:
--   * `sistemas` -> public/sistemas/dominios.php is guarded by crm_proteger_staff('sistemas')
--                   (line 5) and is the only entry point: the Sistemas sidebar link
--                   (includes/staff/leftbar.php:370-372) is hardcoded to
--                   `$left_area_act === 'sistemas'`, and the same target is used by
--                   public/sistemas/menu-seccion.php:19 and includes/dashboard-pagina.php:645.
--   * there is no public/general/dominios.php, and the General sidebar block
--                   (leftbar.php:383-399) exposes no Dominios link, so `general` stays 0.
--                   This differs from puede_programas, which does have a General entry point.
--   * every other area exposes no Dominios entry point, so it stays 0.
--
-- Note: viewing requires only area access, while saving, renewing and disabling a domain
-- additionally require acceso_puede_gestionar('sistemas') (dominios.php:8, enforced at :322).
-- That check stays in PHP and is not replaced by this column.
--
-- Note: the Dominios report (includes/informes-dominios.php) and the expiry notices are
-- separate consumers of the same data and deliberately do NOT read this column.
--
-- Idempotent: the ALTER is a no-op on re-run, and the seed only executes the first time
-- the column is created, so a later manual change is never overwritten.

SET @crm_col_previa = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'area'
    AND COLUMN_NAME = 'puede_dominios'
);

ALTER TABLE `area`
  ADD COLUMN IF NOT EXISTS `puede_dominios` TINYINT(1) NOT NULL DEFAULT 0;

SET @crm_seed = IF(
  @crm_col_previa = 0,
  'UPDATE `area` SET `puede_dominios` = 1 WHERE `clave` = ''sistemas''',
  'SELECT 1'
);
PREPARE crm_seed_stmt FROM @crm_seed;
EXECUTE crm_seed_stmt;
DEALLOCATE PREPARE crm_seed_stmt;
