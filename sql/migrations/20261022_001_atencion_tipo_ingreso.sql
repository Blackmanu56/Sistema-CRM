-- Distingue las atenciones creadas desde un turno de las visitas sin turno.
-- Los registros existentes permanecen NULL porque su origen histórico no se puede inferir con seguridad.
-- Compatible con MariaDB 10.4+; no modifica ni elimina datos existentes.
ALTER TABLE atencion
	ADD COLUMN IF NOT EXISTS tipo_ingreso ENUM('turno','sin_turno') NULL DEFAULT NULL AFTER fecha_turno;
