-- Configuración de disponibilidad de informes por área.
-- Los permisos de consulta y el alcance de los datos siguen verificándose en PHP.
CREATE TABLE IF NOT EXISTS area_informe (
	id_area SMALLINT UNSIGNED NOT NULL,
	codigo_informe VARCHAR(32) NOT NULL,
	habilitado TINYINT(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (id_area, codigo_informe),
	CONSTRAINT fk_area_informe_area
		FOREIGN KEY (id_area) REFERENCES area (id_area)
		ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve the current availability of the five standard tabs for all areas
-- that exist when this migration is applied. Existing permission checks remain
-- mandatory and prevent this seed from granting access to any report data.
INSERT INTO area_informe (id_area, codigo_informe, habilitado)
SELECT id_area, 'colaboradores', 1 FROM area
ON DUPLICATE KEY UPDATE habilitado=area_informe.habilitado;
INSERT INTO area_informe (id_area, codigo_informe, habilitado)
SELECT id_area, 'clientes', 1 FROM area
ON DUPLICATE KEY UPDATE habilitado=area_informe.habilitado;
INSERT INTO area_informe (id_area, codigo_informe, habilitado)
SELECT id_area, 'actividades', 1 FROM area
ON DUPLICATE KEY UPDATE habilitado=area_informe.habilitado;
INSERT INTO area_informe (id_area, codigo_informe, habilitado)
SELECT id_area, 'tareas', 1 FROM area
ON DUPLICATE KEY UPDATE habilitado=area_informe.habilitado;
INSERT INTO area_informe (id_area, codigo_informe, habilitado)
SELECT id_area, 'atenciones', 1 FROM area
ON DUPLICATE KEY UPDATE habilitado=area_informe.habilitado;
