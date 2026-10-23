-- Solicitudes de cambio de fechas de tareas, con trazabilidad de aprobación/rechazo.
-- No altera tareas ni solicitudes históricas; aplicar en la base de pruebas tras respaldo.
CREATE TABLE IF NOT EXISTS tarea_solicitud_cambio_fecha (
	id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	id_tarea INT UNSIGNED NOT NULL,
	id_solicita INT UNSIGNED NOT NULL,
	fecha_inicio_original DATE NOT NULL,
	fecha_cierre_original DATE NOT NULL,
	fecha_inicio_propuesta DATE NOT NULL,
	fecha_cierre_propuesta DATE NOT NULL,
	motivo TEXT NOT NULL,
	estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
	id_resuelve INT UNSIGNED DEFAULT NULL,
	motivo_resolucion TEXT DEFAULT NULL,
	fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	fecha_resolucion DATETIME DEFAULT NULL,
	PRIMARY KEY (id),
	KEY idx_tscf_tarea_estado (id_tarea, estado, id),
	KEY idx_tscf_solicitante_fecha (id_solicita, fecha_solicitud),
	KEY idx_tscf_resuelve (id_resuelve),
	CONSTRAINT fk_tscf_tarea FOREIGN KEY (id_tarea) REFERENCES tarea (id_tarea),
	CONSTRAINT fk_tscf_solicita FOREIGN KEY (id_solicita) REFERENCES empleado (id_empleado),
	CONSTRAINT fk_tscf_resuelve FOREIGN KEY (id_resuelve) REFERENCES empleado (id_empleado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
