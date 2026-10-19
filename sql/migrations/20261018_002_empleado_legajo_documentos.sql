-- Private employee digital-file index. Store the PDF bytes outside the public web root.
CREATE TABLE IF NOT EXISTS empleado_documento_legajo (
	id_documento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	id_empleado INT UNSIGNED NOT NULL,
	categoria VARCHAR(40) NOT NULL,
	nombre_original VARCHAR(255) NOT NULL,
	archivo_key VARCHAR(64) NOT NULL,
	descripcion TEXT NULL,
	fecha_carga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	id_usuario_carga INT UNSIGNED NOT NULL,
	PRIMARY KEY (id_documento),
	UNIQUE KEY uq_empleado_documento_legajo_key (archivo_key),
	KEY idx_empleado_documento_legajo_fecha (id_empleado, fecha_carga),
	KEY idx_empleado_documento_legajo_cargado_por (id_usuario_carga),
	CONSTRAINT fk_empleado_documento_legajo_empleado
		FOREIGN KEY (id_empleado) REFERENCES empleado (id_empleado)
		ON DELETE RESTRICT,
	CONSTRAINT fk_empleado_documento_legajo_usuario
		FOREIGN KEY (id_usuario_carga) REFERENCES empleado (id_empleado)
		ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
