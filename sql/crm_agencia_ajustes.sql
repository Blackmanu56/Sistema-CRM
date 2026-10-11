-- Ajustes de agencia: Administración, turnos pendientes, origen redes, ficha RRHH básica.
-- Idempotente donde es posible (MariaDB 10.4).

-- Área Administración (solo tareas/actividades)
INSERT INTO area (clave, nombre)
SELECT 'administracion', 'Administración'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area WHERE clave='administracion');

UPDATE area SET nombre='Vinculación académica' WHERE clave='vinculaciones';

-- Solicitudes web de turno
ALTER TABLE atencion
  MODIFY estado ENUM('pendiente','abierta','en_atencion','cerrada','derivada','cancelada') NOT NULL DEFAULT 'abierta';

-- Origen de captación de clientes (redes) + canal de turno
ALTER TABLE cliente
  MODIFY origen_contacto ENUM('web','presencial','whatsapp','redes') NULL;

ALTER TABLE atencion
  MODIFY canal ENUM('web','presencial','whatsapp','redes') NOT NULL DEFAULT 'presencial';

-- Campos de ficha RRHH (subset operativo). Silenciar error si ya existen al re-ejecutar.
ALTER TABLE empleado ADD COLUMN fecha_nacimiento DATE NULL AFTER dni;
ALTER TABLE empleado ADD COLUMN telefono_alt VARCHAR(30) NULL AFTER telefono;
ALTER TABLE empleado ADD COLUMN estado_civil VARCHAR(40) NULL AFTER direccion;
ALTER TABLE empleado ADD COLUMN localidad_domicilio VARCHAR(100) NULL AFTER estado_civil;
ALTER TABLE empleado ADD COLUMN provincia_domicilio VARCHAR(80) NULL AFTER localidad_domicilio;
ALTER TABLE empleado ADD COLUMN puesto VARCHAR(120) NULL AFTER provincia_domicilio;
ALTER TABLE empleado ADD COLUMN tipo_vinculo VARCHAR(80) NULL AFTER puesto;
ALTER TABLE empleado ADD COLUMN estado_laboral VARCHAR(40) NOT NULL DEFAULT 'activo' AFTER tipo_vinculo;
ALTER TABLE empleado ADD COLUMN fecha_baja DATE NULL AFTER estado_laboral;
ALTER TABLE empleado ADD COLUMN motivo_baja VARCHAR(200) NULL AFTER fecha_baja;
ALTER TABLE empleado ADD COLUMN modalidad VARCHAR(40) NULL AFTER motivo_baja;
ALTER TABLE empleado ADD COLUMN horario_jornada VARCHAR(120) NULL AFTER modalidad;
ALTER TABLE empleado ADD COLUMN condicion_fiscal VARCHAR(60) NULL AFTER horario_jornada;
ALTER TABLE empleado ADD COLUMN cuit_facturacion VARCHAR(13) NULL AFTER condicion_fiscal;
ALTER TABLE empleado ADD COLUMN id_responsable INT UNSIGNED NULL AFTER id_area;
