-- ---------------------------------------------------------------------------
-- Migración v3: separar las capacidades del área
--   antes: area.atiende_clientes  (un solo booleano para 4 funcionalidades)
--   ahora: puede_clientes / recibe_derivaciones / recibe_turnos
-- Entorno: crm_prueba  |  Rama: manu
-- Idempotente: se puede re-ejecutar sin dañar configuraciones ya ajustadas.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1) Columnas nuevas (solo si no existen)
-- ---------------------------------------------------------------------------
ALTER TABLE `area`
  ADD COLUMN IF NOT EXISTS `puede_clientes`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `atiende_clientes`,
  ADD COLUMN IF NOT EXISTS `recibe_derivaciones` TINYINT(1) NOT NULL DEFAULT 0 AFTER `puede_clientes`,
  ADD COLUMN IF NOT EXISTS `recibe_turnos`       TINYINT(1) NOT NULL DEFAULT 0 AFTER `recibe_derivaciones`;

-- ---------------------------------------------------------------------------
-- 2) Medición del estado PREVIO al backfill: ¿hay áreas que todavía no tienen
--    capacidades? Se mide ACÁ y no después, porque el backfill de más abajo
--    justamente las completa y borraría la evidencia de "primera corrida".
--    Si alguna área de atiende_clientes=1 está en tres ceros -> es la primer
--    migración de esa base y más abajo se aplica la configuración inicial.
--    Si no queda ninguna (base ya migrada o recién instalada con el dump v3),
--    no se toca nada y no se pisa una configuración ajustada por el usuario.
-- ---------------------------------------------------------------------------
SELECT COUNT(*) INTO @v3_pendientes
  FROM `area`
 WHERE `puede_clientes` = 0
   AND `recibe_derivaciones` = 0
   AND `recibe_turnos` = 0
   AND `atiende_clientes` = 1;
SET @v3_primera = IF(@v3_pendientes > 0, 1, 0);

-- ---------------------------------------------------------------------------
-- 3) Backfill: conservar el funcionamiento anterior.
--    Solo aplica a filas que todavía están en los tres ceros (primer corrida),
--    así una configuración ya ajustada no se pisa al re-ejecutar.
-- ---------------------------------------------------------------------------
UPDATE `area`
   SET `puede_clientes`      = `atiende_clientes`,
       `recibe_derivaciones` = `atiende_clientes`,
       `recibe_turnos`       = `atiende_clientes`
 WHERE `puede_clientes` = 0
   AND `recibe_derivaciones` = 0
   AND `recibe_turnos` = 0;

-- ---------------------------------------------------------------------------
-- 4) Configuración inicial (solo en la PRIMERA corrida, según @v3_primera)
-- ---------------------------------------------------------------------------

-- RRHH: Clientes + derivaciones + turnos (área operativa completa).
UPDATE `area`
   SET `puede_clientes` = 1, `recibe_derivaciones` = 1, `recibe_turnos` = 1
 WHERE @v3_primera = 1 AND `clave` = 'rrhh';

-- Sistemas: solo Clientes (la derivación/turnos se habilitó sin querer).
UPDATE `area`
   SET `puede_clientes` = 1, `recibe_derivaciones` = 0, `recibe_turnos` = 0
 WHERE @v3_primera = 1 AND `clave` = 'sistemas';

-- Administración: Clientes; conserva derivaciones y turnos previos.
UPDATE `area`
   SET `puede_clientes` = 1
 WHERE @v3_primera = 1 AND `clave` = 'administracion';

-- Analista: conserva sus capacidades existentes.
UPDATE `area`
   SET `puede_clientes` = 1, `recibe_derivaciones` = 1, `recibe_turnos` = 1
 WHERE @v3_primera = 1 AND `clave` = 'analista';

-- Dirección institucional: nunca tuvo ninguna.
UPDATE `area`
   SET `puede_clientes` = 0, `recibe_derivaciones` = 0, `recibe_turnos` = 0
 WHERE @v3_primera = 1 AND `clave` IN ('general', 'presidencia');

-- ---------------------------------------------------------------------------
-- 5) Verificación
-- ---------------------------------------------------------------------------
SELECT `clave`, `nombre`, `atiende_clientes`,
       `puede_clientes`, `recibe_derivaciones`, `recibe_turnos`
  FROM `area`
 ORDER BY `id_area`;
