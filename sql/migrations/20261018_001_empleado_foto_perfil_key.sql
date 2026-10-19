-- Pending migration for private employee profile photographs.
-- Execute only after reviewing the deployment and backing up the target database.
ALTER TABLE empleado
	ADD COLUMN IF NOT EXISTS foto_perfil_key VARCHAR(64) NULL DEFAULT NULL AFTER id_empleado;
