-- Adaptaciones CRM prueba: subtareas, eliminación, avisos y vencimientos.
-- Idempotente donde se puede. Correr sobre crm_prueba.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------
-- tarea: estado eliminada + índices de consulta
-- ---------------------------------------------------------
ALTER TABLE tarea
  MODIFY estado ENUM('no_iniciada','en_proceso','pausada','terminada','cancelada')
    NOT NULL DEFAULT 'no_iniciada';

-- Índices útiles (ignorar error si ya existen al re-correr a mano)
ALTER TABLE tarea ADD INDEX idx_tarea_estado (estado);
ALTER TABLE tarea ADD INDEX idx_tarea_cierre_estado (fecha_cierre, estado);
ALTER TABLE tarea ADD INDEX idx_tarea_padre (id_tarea_padre);
ALTER TABLE tarea ADD INDEX idx_tarea_cliente (id_cliente);

-- ---------------------------------------------------------
-- actividad: tipos del flujo actual
-- ---------------------------------------------------------
ALTER TABLE actividad
  MODIFY tipo ENUM('creada','proceso','completada','vencida','cancelada','cambio') NOT NULL;

-- ---------------------------------------------------------
-- atencion: índices de bandeja / listados
-- ---------------------------------------------------------
ALTER TABLE atencion ADD INDEX idx_aten_estado_fecha (estado, fecha_turno);
ALTER TABLE atencion ADD INDEX idx_aten_area_estado (id_area, estado);

-- ---------------------------------------------------------
-- Avisos enriquecidos (campanita: cambios, vencimientos, bajas, partes)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS crm_aviso (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_empleado_para   INT UNSIGNED NOT NULL,
  id_empleado_de     INT UNSIGNED NULL,
  id_tarea           INT UNSIGNED NULL,
  tipo               VARCHAR(40) NOT NULL DEFAULT 'info',
  titulo             VARCHAR(200) NOT NULL,
  detalle            TEXT NULL,
  url                VARCHAR(300) NULL,
  confirmado         TINYINT(1) NOT NULL DEFAULT 0,
  fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_crmaviso_para FOREIGN KEY (id_empleado_para) REFERENCES empleado(id_empleado),
  CONSTRAINT fk_crmaviso_de   FOREIGN KEY (id_empleado_de)   REFERENCES empleado(id_empleado),
  CONSTRAINT fk_crmaviso_tarea FOREIGN KEY (id_tarea)        REFERENCES tarea(id_tarea),
  INDEX idx_crmaviso_bandeja (id_empleado_para, confirmado, fecha),
  INDEX idx_crmaviso_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpiar ceros inválidos antes de FKs (instalaciones previas sin FK)
UPDATE crm_aviso SET id_empleado_de = NULL WHERE id_empleado_de = 0;
UPDATE crm_aviso SET id_tarea = NULL WHERE id_tarea = 0;

-- Si la tabla ya existía sin FKs, intentar agregarlas
-- (si ya están, MySQL avisa y se puede ignorar)

-- ---------------------------------------------------------
-- Pedido de eliminación: empleado → coord área → coord general
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS tarea_solicitud_baja (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_tarea           INT UNSIGNED NOT NULL,
  id_solicita        INT UNSIGNED NOT NULL,
  motivo             TEXT NOT NULL,
  estado             ENUM('pendiente_coord','pendiente_general','aceptada','rechazada')
                       NOT NULL DEFAULT 'pendiente_coord',
  id_revisa_coord    INT UNSIGNED NULL,
  nota_coord         TEXT NULL,
  id_revisa_general  INT UNSIGNED NULL,
  nota_general       TEXT NULL,
  fecha_solicitud    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion   DATETIME NULL,
  CONSTRAINT fk_tsb_tarea    FOREIGN KEY (id_tarea)          REFERENCES tarea(id_tarea),
  CONSTRAINT fk_tsb_solicita FOREIGN KEY (id_solicita)       REFERENCES empleado(id_empleado),
  CONSTRAINT fk_tsb_coord    FOREIGN KEY (id_revisa_coord)   REFERENCES empleado(id_empleado),
  CONSTRAINT fk_tsb_general  FOREIGN KEY (id_revisa_general) REFERENCES empleado(id_empleado),
  INDEX idx_tsb_tarea (id_tarea),
  INDEX idx_tsb_estado (estado),
  INDEX idx_tsb_pendientes (estado, fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Control de aviso de vencimiento (1 por tarea y día)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS tarea_aviso_vencimiento (
  id_tarea     INT UNSIGNED NOT NULL,
  fecha_aviso  DATE NOT NULL,
  PRIMARY KEY (id_tarea, fecha_aviso),
  CONSTRAINT fk_tavenc_tarea FOREIGN KEY (id_tarea) REFERENCES tarea(id_tarea) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- programa / modelos: FKs faltantes en algunos entornos
-- ---------------------------------------------------------
-- tarea_modelo sin FKs en BD viva
ALTER TABLE tarea_modelo
  ADD CONSTRAINT fk_tmod_area FOREIGN KEY (id_area) REFERENCES area(id_area);
ALTER TABLE tarea_modelo
  ADD CONSTRAINT fk_tmod_tipo FOREIGN KEY (id_tipo) REFERENCES tipo_tarea(id_tipo);
ALTER TABLE tarea_modelo
  ADD CONSTRAINT fk_tmod_eje  FOREIGN KEY (id_eje)  REFERENCES eje(id_eje);

-- Un cliente activo una sola vez por programa
ALTER TABLE cliente_programa
  ADD INDEX idx_cliprog_activo (id_cliente, id_programa, fecha_hasta);

-- atencion origen (derivaciones encadenadas)
ALTER TABLE atencion
  ADD CONSTRAINT fk_aten_origen FOREIGN KEY (id_atencion_origen) REFERENCES atencion(id_atencion);

-- ---------------------------------------------------------
-- Portal cliente: autoinscripción a actividades
-- ---------------------------------------------------------
ALTER TABLE evento
  ADD COLUMN autoinscripcion TINYINT(1) NOT NULL DEFAULT 0 AFTER resultado;
ALTER TABLE evento
  ADD COLUMN publico ENUM('todos','persona','empresa') NOT NULL DEFAULT 'todos' AFTER autoinscripcion;

CREATE TABLE IF NOT EXISTS evento_inscripcion (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_evento          INT UNSIGNED NOT NULL,
  id_cliente         INT UNSIGNED NOT NULL,
  estado             ENUM('inscrito','asistio','ausente') NOT NULL DEFAULT 'inscrito',
  fecha_inscripcion  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_evins (id_evento, id_cliente),
  INDEX idx_evins_cliente (id_cliente, estado),
  CONSTRAINT fk_evins_evento  FOREIGN KEY (id_evento)  REFERENCES evento(id_evento) ON DELETE CASCADE,
  CONSTRAINT fk_evins_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
