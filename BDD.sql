-- CRM ADEMI — principal schema installer/updater (MariaDB 10.4+; tested on 10.4.32; reference schema inspected on 11.4.13).
-- Select the target database first. This file never creates/selects a database.
-- Safe for an empty database or an existing compatible CRM schema; repeatable.
-- Adds missing tables/columns/indexes/foreign keys only. Never drops CRM data or rewrites existing configuration.
-- Existing same-named columns must match the canonical type and nullability; incompatible schemas abort before persistent changes.
-- Standard seeds (roles/areas/report availability) are inserted only when absent and never overwrite existing rows.
-- No employee/client/task/activity/turn/credential/document/image test records are included.
-- For existing databases, make and verify a private backup before running this script.
SET NAMES utf8mb4;
DELIMITER $$
DROP PROCEDURE IF EXISTS crm_ademi_bdd_apply_20261019$$
CREATE PROCEDURE crm_ademi_bdd_apply_20261019()
BEGIN
  DECLARE v_area_informe_existe TINYINT DEFAULT 0;
  DECLARE v_fk_errors BIGINT DEFAULT 0;
  DECLARE v_duplicate BIGINT DEFAULT 0;
  DECLARE v_fk_checks TINYINT DEFAULT 1;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET FOREIGN_KEY_CHECKS=v_fk_checks;
    DROP TEMPORARY TABLE IF EXISTS _crm_bdd_expected_columns;
    DROP TEMPORARY TABLE IF EXISTS _crm_bdd_allowed_missing;
    RESIGNAL;
  END;
  IF DATABASE() IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: select the target database before running this file.'; END IF;
  SET v_fk_checks=@@FOREIGN_KEY_CHECKS;
  SELECT EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='area_informe') INTO v_area_informe_existe;
  CREATE TEMPORARY TABLE _crm_bdd_expected_columns (table_name VARCHAR(64) NOT NULL, column_name VARCHAR(64) NOT NULL, column_type VARCHAR(255) NOT NULL, is_nullable CHAR(3) NOT NULL, PRIMARY KEY(table_name,column_name));
  INSERT INTO _crm_bdd_expected_columns (table_name,column_name,column_type,is_nullable) VALUES
    ('actividad','id_actividad','int(10) unsigned','NO'),
    ('actividad','id_tarea','int(10) unsigned','NO'),
    ('actividad','id_empleado','int(10) unsigned','YES'),
    ('actividad','tipo','enum(''creada'',''proceso'',''completada'',''vencida'',''cancelada'',''cambio'')','NO'),
    ('actividad','descripcion','varchar(300)','YES'),
    ('actividad','fecha_actividad','datetime','NO'),
    ('area','id_area','smallint(5) unsigned','NO'),
    ('area','clave','varchar(30)','NO'),
    ('area','nombre','varchar(80)','NO'),
    ('area','activo','tinyint(1)','NO'),
    ('area','atiende_clientes','tinyint(1)','NO'),
    ('area','puede_clientes','tinyint(1)','NO'),
    ('area','recibe_derivaciones','tinyint(1)','NO'),
    ('area','recibe_turnos','tinyint(1)','NO'),
    ('area','asignable_empleado','tinyint(1)','NO'),
    ('area','creado_en','timestamp','NO'),
    ('area_informe','id_area','smallint(5) unsigned','NO'),
    ('area_informe','codigo_informe','varchar(32)','NO'),
    ('area_informe','habilitado','tinyint(1)','NO'),
    ('atencion','id_atencion','int(10) unsigned','NO'),
    ('atencion','id_cliente','int(10) unsigned','NO'),
    ('atencion','id_area','smallint(5) unsigned','NO'),
    ('atencion','id_area_origen','smallint(5) unsigned','YES'),
    ('atencion','id_empleado','int(10) unsigned','YES'),
    ('atencion','id_empleado_origen','int(10) unsigned','YES'),
    ('atencion','motivo','enum(''consulta'',''documentacion'',''seguimiento'',''tramite'',''otro'')','NO'),
    ('atencion','canal','enum(''web'',''presencial'',''whatsapp'',''redes'')','NO'),
    ('atencion','detalle','text','YES'),
    ('atencion','estado','enum(''pendiente'',''abierta'',''en_atencion'',''cerrada'',''derivada'',''cancelada'')','NO'),
    ('atencion','id_atencion_origen','int(10) unsigned','YES'),
    ('atencion','fecha','datetime','NO'),
    ('atencion','fecha_turno','datetime','YES'),
    ('atencion','fecha_derivacion','datetime','YES'),
    ('atencion_seguimiento','id_seguimiento','int(10) unsigned','NO'),
    ('atencion_seguimiento','id_atencion','int(10) unsigned','NO'),
    ('atencion_seguimiento','id_area','smallint(5) unsigned','NO'),
    ('atencion_seguimiento','id_empleado','int(10) unsigned','YES'),
    ('atencion_seguimiento','estado_anterior','varchar(30)','YES'),
    ('atencion_seguimiento','estado_nuevo','varchar(30)','NO'),
    ('atencion_seguimiento','accion','varchar(50)','NO'),
    ('atencion_seguimiento','observacion','text','YES'),
    ('atencion_seguimiento','fecha','datetime','NO'),
    ('aviso','id_aviso','int(10) unsigned','NO'),
    ('aviso','id_tarea','int(10) unsigned','NO'),
    ('aviso','id_empleado_de','int(10) unsigned','NO'),
    ('aviso','id_empleado_para','int(10) unsigned','NO'),
    ('aviso','fecha_aviso','datetime','NO'),
    ('aviso','confirmado','tinyint(1)','NO'),
    ('aviso_atencion','id_aviso','int(10) unsigned','NO'),
    ('aviso_atencion','id_atencion','int(10) unsigned','NO'),
    ('aviso_atencion','id_empleado_de','int(10) unsigned','YES'),
    ('aviso_atencion','id_empleado_para','int(10) unsigned','NO'),
    ('aviso_atencion','confirmado','tinyint(1)','NO'),
    ('aviso_atencion','fecha_aviso','datetime','NO'),
    ('cliente','id_cliente','int(10) unsigned','NO'),
    ('cliente','tipo_cliente','enum(''emprendedor'',''empresa'',''municipalidad'',''organismo'')','NO'),
    ('cliente','nombre','varchar(150)','NO'),
    ('cliente','correo','varchar(150)','YES'),
    ('cliente','telefono','varchar(30)','YES'),
    ('cliente','id_localidad','smallint(5) unsigned','NO'),
    ('cliente','direccion','varchar(200)','YES'),
    ('cliente','cuil_cuit','varchar(13)','NO'),
    ('cliente','id_area_afectada','smallint(5) unsigned','YES'),
    ('cliente','id_empleado_alta','int(10) unsigned','YES'),
    ('cliente','origen_contacto','enum(''web'',''presencial'',''whatsapp'',''redes'')','YES'),
    ('cliente','fecha_alta','date','NO'),
    ('cliente','activo','tinyint(1)','NO'),
    ('cliente','creado_en','timestamp','NO'),
    ('cliente_area','id','int(10) unsigned','NO'),
    ('cliente_area','id_cliente','int(10) unsigned','NO'),
    ('cliente_area','id_area','smallint(5) unsigned','NO'),
    ('cliente_area','fecha_desde','date','NO'),
    ('cliente_aviso','id_aviso','int(10) unsigned','NO'),
    ('cliente_aviso','id_usuario','int(10) unsigned','NO'),
    ('cliente_aviso','titulo','varchar(200)','NO'),
    ('cliente_aviso','detalle','text','YES'),
    ('cliente_aviso','leido','tinyint(1)','NO'),
    ('cliente_aviso','fecha','datetime','NO'),
    ('cliente_empresa','id_cliente','int(10) unsigned','NO'),
    ('cliente_empresa','razon_social','varchar(150)','YES'),
    ('cliente_persona','id_cliente','int(10) unsigned','NO'),
    ('cliente_persona','genero','enum(''femenino'',''masculino'',''otro'')','YES'),
    ('cliente_persona','dni','varchar(15)','YES'),
    ('cliente_programa','id','int(10) unsigned','NO'),
    ('cliente_programa','id_cliente','int(10) unsigned','NO'),
    ('cliente_programa','id_programa','int(10) unsigned','NO'),
    ('cliente_programa','fecha_desde','date','NO'),
    ('cliente_programa','fecha_hasta','date','YES'),
    ('cliente_solicitud','id_solicitud','int(10) unsigned','NO'),
    ('cliente_solicitud','nombre','varchar(150)','NO'),
    ('cliente_solicitud','correo','varchar(150)','NO'),
    ('cliente_solicitud','telefono','varchar(30)','YES'),
    ('cliente_solicitud','dni','varchar(15)','YES'),
    ('cliente_solicitud','cuil_cuit','varchar(13)','NO'),
    ('cliente_solicitud','nombre_negocio','varchar(150)','YES'),
    ('cliente_solicitud','id_localidad','smallint(5) unsigned','NO'),
    ('cliente_solicitud','tipo_autodeclarado','enum(''emprendedor'',''empresa'')','NO'),
    ('cliente_solicitud','sector','varchar(80)','YES'),
    ('cliente_solicitud','sector_otro','varchar(120)','YES'),
    ('cliente_solicitud','anios_funcionamiento','varchar(40)','YES'),
    ('cliente_solicitud','personas_involucradas','varchar(40)','YES'),
    ('cliente_solicitud','personal_formal','tinyint(1)','NO'),
    ('cliente_solicitud','producto_servicio','text','YES'),
    ('cliente_solicitud','contrasena_hash','varchar(255)','NO'),
    ('cliente_solicitud','estado','enum(''pendiente'',''aceptada'',''rechazada'')','NO'),
    ('cliente_solicitud','sugerencia_tipo','enum(''emprendedor'',''empresa'')','YES'),
    ('cliente_solicitud','tipo_asignado','enum(''emprendedor'',''empresa'')','YES'),
    ('cliente_solicitud','id_cliente','int(10) unsigned','YES'),
    ('cliente_solicitud','id_revisa','int(10) unsigned','YES'),
    ('cliente_solicitud','nota_revision','text','YES'),
    ('cliente_solicitud','fecha_solicitud','datetime','NO'),
    ('cliente_solicitud','fecha_resolucion','datetime','YES'),
    ('clientes_usuario_clientes','id','int(10) unsigned','NO'),
    ('clientes_usuario_clientes','id_usuario','int(10) unsigned','NO'),
    ('clientes_usuario_clientes','id_cliente','int(10) unsigned','NO'),
    ('clientes_usuario_clientes','rol','enum(''titular'',''representante'')','NO'),
    ('clientes_usuario_clientes','es_principal','tinyint(1)','NO'),
    ('clientes_usuario_clientes','fecha_desde','date','NO'),
    ('clientes_usuario_clientes','fecha_hasta','date','YES'),
    ('crm_aviso','id','int(10) unsigned','NO'),
    ('crm_aviso','id_empleado_para','int(10) unsigned','NO'),
    ('crm_aviso','id_empleado_de','int(10) unsigned','YES'),
    ('crm_aviso','id_tarea','int(10) unsigned','YES'),
    ('crm_aviso','tipo','varchar(40)','NO'),
    ('crm_aviso','titulo','varchar(200)','NO'),
    ('crm_aviso','detalle','text','YES'),
    ('crm_aviso','url','varchar(300)','YES'),
    ('crm_aviso','confirmado','tinyint(1)','NO'),
    ('crm_aviso','fecha','datetime','NO'),
    ('crm_schema_version','clave','varchar(60)','NO'),
    ('crm_schema_version','fecha','datetime','NO'),
    ('dominio','id_dominio','int(11)','NO'),
    ('dominio','nombre','varchar(150)','NO'),
    ('dominio','url','varchar(255)','YES'),
    ('dominio','proveedor','varchar(100)','YES'),
    ('dominio','responsable','varchar(100)','YES'),
    ('dominio','fecha_inicio','date','YES'),
    ('dominio','fecha_vencimiento','date','NO'),
    ('dominio','notas','text','YES'),
    ('dominio','activo','tinyint(1)','YES'),
    ('dominio','creado_en','datetime','YES'),
    ('dominio','actualizado_en','datetime','YES'),
    ('dominio_renovacion','id_renovacion','int(11)','NO'),
    ('dominio_renovacion','id_dominio','int(11)','NO'),
    ('dominio_renovacion','fecha_renovacion','date','NO'),
    ('dominio_renovacion','fecha_vencimiento_anterior','date','NO'),
    ('dominio_renovacion','fecha_vencimiento_nueva','date','NO'),
    ('dominio_renovacion','id_usuario','int(11)','YES'),
    ('dominio_renovacion','notas','text','YES'),
    ('dominio_renovacion','creado_en','datetime','YES'),
    ('eje','id_eje','int(10) unsigned','NO'),
    ('eje','id_tipo','int(10) unsigned','NO'),
    ('eje','nombre','varchar(80)','NO'),
    ('empleado','id_empleado','int(10) unsigned','NO'),
    ('empleado','foto_perfil_key','varchar(64)','YES'),
    ('empleado','nombres','varchar(80)','NO'),
    ('empleado','apellido','varchar(80)','NO'),
    ('empleado','cuil','char(11)','NO'),
    ('empleado','dni','varchar(15)','NO'),
    ('empleado','fecha_nacimiento','date','YES'),
    ('empleado','correo','varchar(150)','NO'),
    ('empleado','telefono','varchar(30)','YES'),
    ('empleado','telefono_alt','varchar(30)','YES'),
    ('empleado','direccion','varchar(200)','YES'),
    ('empleado','estado_civil','varchar(40)','YES'),
    ('empleado','localidad_domicilio','varchar(100)','YES'),
    ('empleado','provincia_domicilio','varchar(80)','YES'),
    ('empleado','puesto','varchar(120)','YES'),
    ('empleado','tipo_vinculo','varchar(80)','YES'),
    ('empleado','estado_laboral','varchar(40)','NO'),
    ('empleado','fecha_baja','date','YES'),
    ('empleado','motivo_baja','varchar(200)','YES'),
    ('empleado','modalidad','varchar(40)','YES'),
    ('empleado','horario_jornada','varchar(120)','YES'),
    ('empleado','condicion_fiscal','varchar(60)','YES'),
    ('empleado','cuit_facturacion','varchar(13)','YES'),
    ('empleado','fecha_ingreso','date','NO'),
    ('empleado','fecha_alta','date','NO'),
    ('empleado','nombre_usuario','varchar(50)','NO'),
    ('empleado','contrasena_hash','varchar(255)','NO'),
    ('empleado','primer_acceso','tinyint(1)','NO'),
    ('empleado','id_rol','smallint(5) unsigned','NO'),
    ('empleado','id_area','smallint(5) unsigned','YES'),
    ('empleado','id_responsable','int(10) unsigned','YES'),
    ('empleado','activo','tinyint(1)','NO'),
    ('empleado','creado_en','timestamp','NO'),
    ('empleado_documento_legajo','id_documento','bigint(20) unsigned','NO'),
    ('empleado_documento_legajo','id_empleado','int(10) unsigned','NO'),
    ('empleado_documento_legajo','categoria','varchar(40)','NO'),
    ('empleado_documento_legajo','nombre_original','varchar(255)','NO'),
    ('empleado_documento_legajo','archivo_key','varchar(64)','NO'),
    ('empleado_documento_legajo','descripcion','text','YES'),
    ('empleado_documento_legajo','fecha_carga','datetime','NO'),
    ('empleado_documento_legajo','id_usuario_carga','int(10) unsigned','NO'),
    ('empleado_rol_adicional','id','int(10) unsigned','NO'),
    ('empleado_rol_adicional','id_empleado','int(10) unsigned','NO'),
    ('empleado_rol_adicional','id_rol','smallint(5) unsigned','NO'),
    ('empleado_rol_adicional','id_area','smallint(5) unsigned','YES'),
    ('empleado_rol_adicional','fecha_desde','date','NO'),
    ('empleado_rol_adicional','fecha_hasta','date','YES'),
    ('evento','id_evento','int(10) unsigned','NO'),
    ('evento','titulo','varchar(200)','NO'),
    ('evento','descripcion','text','YES'),
    ('evento','tipo','varchar(40)','NO'),
    ('evento','fecha','date','NO'),
    ('evento','id_area','smallint(5) unsigned','NO'),
    ('evento','id_cliente','int(10) unsigned','YES'),
    ('evento','id_localidad','smallint(5) unsigned','YES'),
    ('evento','organismo','varchar(200)','YES'),
    ('evento','id_empleado','int(10) unsigned','YES'),
    ('evento','id_tarea_origen','int(10) unsigned','YES'),
    ('evento','asistencia','text','YES'),
    ('evento','resultado','text','YES'),
    ('evento','autoinscripcion','tinyint(1)','NO'),
    ('evento','publico','enum(''todos'',''emprendedor'',''empresa'',''municipalidad'',''organismo'')','NO'),
    ('evento','activo','tinyint(1)','NO'),
    ('evento','creado_en','timestamp','NO'),
    ('evento_inscripcion','id','int(10) unsigned','NO'),
    ('evento_inscripcion','id_evento','int(10) unsigned','NO'),
    ('evento_inscripcion','id_cliente','int(10) unsigned','NO'),
    ('evento_inscripcion','estado','enum(''inscrito'',''asistio'',''ausente'')','NO'),
    ('evento_inscripcion','fecha_inscripcion','datetime','NO'),
    ('evento_tarea','id','int(10) unsigned','NO'),
    ('evento_tarea','id_evento','int(10) unsigned','NO'),
    ('evento_tarea','id_tarea','int(10) unsigned','NO'),
    ('informe_plantilla','id_plantilla','smallint(5) unsigned','NO'),
    ('informe_plantilla','clave','varchar(40)','NO'),
    ('informe_plantilla','nombre','varchar(120)','NO'),
    ('informe_plantilla','descripcion','varchar(300)','YES'),
    ('informe_plantilla','activo','tinyint(1)','NO'),
    ('informe_publicado','id_informe','int(10) unsigned','NO'),
    ('informe_publicado','id_plantilla','smallint(5) unsigned','NO'),
    ('informe_publicado','titulo','varchar(200)','NO'),
    ('informe_publicado','filtros_json','text','YES'),
    ('informe_publicado','resumen_json','text','YES'),
    ('informe_publicado','id_empleado_de','int(10) unsigned','NO'),
    ('informe_publicado','id_area','smallint(5) unsigned','YES'),
    ('informe_publicado','publicado','tinyint(1)','NO'),
    ('informe_publicado','fecha_desde','date','YES'),
    ('informe_publicado','fecha_hasta','date','YES'),
    ('informe_publicado','creado_en','timestamp','NO'),
    ('localidad','id_localidad','smallint(5) unsigned','NO'),
    ('localidad','nombre','varchar(80)','NO'),
    ('localidad','id_provincia','smallint(5) unsigned','NO'),
    ('localidad','latitud','decimal(10,7)','YES'),
    ('localidad','longitud','decimal(10,7)','YES'),
    ('localidad','poligono_geojson','longtext','YES'),
    ('presentacion_registro_interno','modulo','varchar(40)','NO'),
    ('presentacion_registro_interno','id_registro','int(10) unsigned','NO'),
    ('presentacion_registro_interno','clasificacion','enum(''simulado'')','NO'),
    ('presentacion_registro_interno','nota','varchar(255)','NO'),
    ('presentacion_registro_interno','actualizado_en','timestamp','NO'),
    ('programa','id_programa','int(10) unsigned','NO'),
    ('programa','nombre','varchar(120)','NO'),
    ('programa','descripcion','text','YES'),
    ('programa','activo','tinyint(1)','NO'),
    ('programa','creado_en','timestamp','NO'),
    ('programa_item','id','int(10) unsigned','NO'),
    ('programa_item','id_programa','int(10) unsigned','NO'),
    ('programa_item','id_area','smallint(5) unsigned','NO'),
    ('programa_item','id_tipo','int(10) unsigned','YES'),
    ('programa_item','id_eje','int(10) unsigned','YES'),
    ('programa_item','titulo','varchar(200)','NO'),
    ('provincia','id_provincia','smallint(5) unsigned','NO'),
    ('provincia','nombre','varchar(80)','NO'),
    ('rol','id_rol','smallint(5) unsigned','NO'),
    ('rol','clave','varchar(30)','NO'),
    ('rol','nombre','varchar(80)','NO'),
    ('sala_reunion','id_sala','int(11)','NO'),
    ('sala_reunion','nombre','varchar(120)','NO'),
    ('sala_reunion','activo','tinyint(1)','NO'),
    ('sala_reunion_reserva','id_reserva','int(11)','NO'),
    ('sala_reunion_reserva','id_sala','int(11)','NO'),
    ('sala_reunion_reserva','id_area','int(11)','NO'),
    ('sala_reunion_reserva','id_empleado','int(11)','NO'),
    ('sala_reunion_reserva','fecha','date','NO'),
    ('sala_reunion_reserva','hora_inicio','time','NO'),
    ('sala_reunion_reserva','hora_fin','time','NO'),
    ('sala_reunion_reserva','motivo','varchar(500)','NO'),
    ('sala_reunion_reserva','estado','varchar(20)','NO'),
    ('sala_reunion_reserva','creado_en','datetime','NO'),
    ('sala_reunion_reserva','actualizado_en','datetime','NO'),
    ('tarea','id_tarea','int(10) unsigned','NO'),
    ('tarea','titulo','varchar(200)','NO'),
    ('tarea','descripcion','text','YES'),
    ('tarea','id_area','smallint(5) unsigned','NO'),
    ('tarea','id_tipo','int(10) unsigned','NO'),
    ('tarea','prioridad','enum(''urgente'',''alta'',''media'',''baja'')','NO'),
    ('tarea','estado','enum(''no_iniciada'',''en_proceso'',''pausada'',''terminada'',''cancelada'')','NO'),
    ('tarea','fecha_inicio','date','NO'),
    ('tarea','fecha_cierre','date','NO'),
    ('tarea','avance','tinyint(3) unsigned','NO'),
    ('tarea','id_eje','int(10) unsigned','YES'),
    ('tarea','fecha_cierre_real','date','YES'),
    ('tarea','id_atencion','int(10) unsigned','YES'),
    ('tarea','id_cliente','int(10) unsigned','YES'),
    ('tarea','id_usuario_actor','int(10) unsigned','YES'),
    ('tarea','id_tarea_padre','int(10) unsigned','YES'),
    ('tarea','uid','varchar(20)','YES'),
    ('tarea','creado_en','timestamp','NO'),
    ('tarea_avance','id','int(10) unsigned','NO'),
    ('tarea_avance','id_tarea','int(10) unsigned','NO'),
    ('tarea_avance','id_empleado','int(10) unsigned','YES'),
    ('tarea_avance','url','varchar(500)','YES'),
    ('tarea_avance','comentario','text','YES'),
    ('tarea_avance','archivo','varchar(255)','YES'),
    ('tarea_avance','fecha_carga','datetime','NO'),
    ('tarea_aviso_vencimiento','id_tarea','int(10) unsigned','NO'),
    ('tarea_aviso_vencimiento','fecha_aviso','date','NO'),
    ('tarea_comentario','id','int(10) unsigned','NO'),
    ('tarea_comentario','id_tarea','int(10) unsigned','NO'),
    ('tarea_comentario','id_empleado','int(10) unsigned','YES'),
    ('tarea_comentario','id_usuario_actor','int(10) unsigned','YES'),
    ('tarea_comentario','texto','text','NO'),
    ('tarea_comentario','fecha_carga','datetime','NO'),
    ('tarea_enlace','id','int(10) unsigned','NO'),
    ('tarea_enlace','id_tarea','int(10) unsigned','NO'),
    ('tarea_enlace','id_tarea_comentario','int(10) unsigned','YES'),
    ('tarea_enlace','url','varchar(500)','NO'),
    ('tarea_enlace','fecha_carga','datetime','NO'),
    ('tarea_inicio','id','int(10) unsigned','NO'),
    ('tarea_inicio','id_tarea','int(10) unsigned','NO'),
    ('tarea_inicio','id_empleado','int(10) unsigned','NO'),
    ('tarea_inicio','fecha_click_inicio','datetime','NO'),
    ('tarea_modelo','id_modelo','int(10) unsigned','NO'),
    ('tarea_modelo','id_area','smallint(5) unsigned','NO'),
    ('tarea_modelo','id_tipo','int(10) unsigned','NO'),
    ('tarea_modelo','id_eje','int(10) unsigned','YES'),
    ('tarea_modelo','titulo','varchar(200)','NO'),
    ('tarea_responsable','id','int(10) unsigned','NO'),
    ('tarea_responsable','id_tarea','int(10) unsigned','NO'),
    ('tarea_responsable','id_empleado','int(10) unsigned','NO'),
    ('tarea_responsable','es_principal','tinyint(1)','NO'),
    ('tarea_solicitud_baja','id','int(10) unsigned','NO'),
    ('tarea_solicitud_baja','id_tarea','int(10) unsigned','NO'),
    ('tarea_solicitud_baja','id_solicita','int(10) unsigned','NO'),
    ('tarea_solicitud_baja','motivo','text','NO'),
    ('tarea_solicitud_baja','estado','enum(''pendiente_coord'',''pendiente_general'',''aceptada'',''rechazada'')','NO'),
    ('tarea_solicitud_baja','id_revisa_coord','int(10) unsigned','YES'),
    ('tarea_solicitud_baja','nota_coord','text','YES'),
    ('tarea_solicitud_baja','id_revisa_general','int(10) unsigned','YES'),
    ('tarea_solicitud_baja','nota_general','text','YES'),
    ('tarea_solicitud_baja','fecha_solicitud','datetime','NO'),
    ('tarea_solicitud_baja','fecha_resolucion','datetime','YES'),
    ('tipo_tarea','id_tipo','int(10) unsigned','NO'),
    ('tipo_tarea','id_area','smallint(5) unsigned','NO'),
    ('tipo_tarea','nombre','varchar(80)','NO'),
    ('turno_config','clave','varchar(40)','NO'),
    ('turno_config','valor','varchar(120)','NO'),
    ('usuario_cliente','id_usuario','int(10) unsigned','NO'),
    ('usuario_cliente','nombre','varchar(150)','NO'),
    ('usuario_cliente','correo','varchar(150)','NO'),
    ('usuario_cliente','contrasena_hash','varchar(255)','NO'),
    ('usuario_cliente','primer_acceso','tinyint(1)','NO'),
    ('usuario_cliente','dni','varchar(15)','YES'),
    ('usuario_cliente','telefono','varchar(30)','YES'),
    ('usuario_cliente','activo','tinyint(1)','NO'),
    ('usuario_cliente','creado_en','timestamp','NO');
  CREATE TEMPORARY TABLE _crm_bdd_allowed_missing (table_name VARCHAR(64) NOT NULL, column_name VARCHAR(64) NOT NULL, PRIMARY KEY(table_name,column_name));
  INSERT INTO _crm_bdd_allowed_missing VALUES
    ('area','asignable_empleado'),
    ('area','atiende_clientes'),
    ('area','puede_clientes'),
    ('area','recibe_derivaciones'),
    ('area','recibe_turnos'),
    ('atencion','fecha_derivacion'),
    ('atencion','id_area_origen'),
    ('atencion','id_empleado_origen'),
    ('cliente','id_empleado_alta'),
    ('empleado','condicion_fiscal'),
    ('empleado','cuit_facturacion'),
    ('empleado','estado_civil'),
    ('empleado','estado_laboral'),
    ('empleado','fecha_baja'),
    ('empleado','fecha_nacimiento'),
    ('empleado','foto_perfil_key'),
    ('empleado','horario_jornada'),
    ('empleado','id_responsable'),
    ('empleado','localidad_domicilio'),
    ('empleado','modalidad'),
    ('empleado','motivo_baja'),
    ('empleado','provincia_domicilio'),
    ('empleado','puesto'),
    ('empleado','telefono_alt'),
    ('empleado','tipo_vinculo'),
    ('evento','autoinscripcion'),
    ('evento','id_localidad'),
    ('evento','publico'),
    ('localidad','latitud'),
    ('localidad','longitud'),
    ('localidad','poligono_geojson'),
    ('tarea_enlace','id_tarea_comentario');
  IF EXISTS (
    SELECT 1 FROM _crm_bdd_expected_columns e JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=e.table_name AND c.COLUMN_NAME=e.column_name
    WHERE LOWER(c.COLUMN_TYPE)<>LOWER(e.column_type) OR c.IS_NULLABLE<>e.is_nullable
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: incompatible existing column type/nullability detected; no CRM schema changes were applied. Compare SHOW CREATE TABLE and reconcile first.'; END IF;
  IF EXISTS (
    SELECT 1 FROM information_schema.TABLES t JOIN _crm_bdd_expected_columns e ON e.table_name=t.TABLE_NAME
    LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=e.table_name AND c.COLUMN_NAME=e.column_name
    LEFT JOIN _crm_bdd_allowed_missing a ON a.table_name=e.table_name AND a.column_name=e.column_name
    WHERE t.TABLE_SCHEMA=DATABASE() AND c.COLUMN_NAME IS NULL AND a.column_name IS NULL
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: an existing table is missing a non-migratable column; no CRM schema changes were applied. Compare SHOW CREATE TABLE first.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES t JOIN (SELECT DISTINCT table_name FROM _crm_bdd_expected_columns) e ON e.table_name=t.TABLE_NAME WHERE t.TABLE_SCHEMA=DATABASE() AND UPPER(COALESCE(t.ENGINE,''))<>'INNODB') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: an existing CRM table is not InnoDB; no CRM schema changes were applied.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evento' AND INDEX_NAME='idx_evento_localidad_activo' GROUP BY INDEX_NAME HAVING COUNT(*)<>2 OR MAX(NON_UNIQUE)<>1 OR GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')<>'id_localidad,activo') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: existing evento locality index has an incompatible definition; no schema changes were applied.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tarea_enlace' AND INDEX_NAME='uq_tarea_enlace_comentario' GROUP BY INDEX_NAME HAVING COUNT(*)<>1 OR MAX(NON_UNIQUE)<>0 OR GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')<>'id_tarea_comentario') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: existing task-comment index has an incompatible definition; no schema changes were applied.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS r WHERE r.CONSTRAINT_SCHEMA=DATABASE() AND r.CONSTRAINT_NAME='fk_evento_localidad' AND NOT (r.TABLE_NAME='evento' AND r.REFERENCED_TABLE_NAME='localidad' AND r.DELETE_RULE='RESTRICT' AND (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME)=1 AND EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME='evento' AND k.COLUMN_NAME='id_localidad' AND k.REFERENCED_TABLE_NAME='localidad' AND k.REFERENCED_COLUMN_NAME='id_localidad'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: existing evento-localidad foreign key has an incompatible definition; no schema changes were applied.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS r WHERE r.CONSTRAINT_SCHEMA=DATABASE() AND r.CONSTRAINT_NAME='fk_cliente_empleado_alta' AND NOT (r.TABLE_NAME='cliente' AND r.REFERENCED_TABLE_NAME='empleado' AND r.DELETE_RULE='SET NULL' AND (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME)=1 AND EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME='cliente' AND k.COLUMN_NAME='id_empleado_alta' AND k.REFERENCED_TABLE_NAME='empleado' AND k.REFERENCED_COLUMN_NAME='id_empleado'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: existing client-employee foreign key has an incompatible definition; no schema changes were applied.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS r WHERE r.CONSTRAINT_SCHEMA=DATABASE() AND r.CONSTRAINT_NAME='fk_atencion_area_origen' AND NOT (r.TABLE_NAME='atencion' AND r.REFERENCED_TABLE_NAME='area' AND r.DELETE_RULE='SET NULL' AND (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME)=1 AND EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME='atencion' AND k.COLUMN_NAME='id_area_origen' AND k.REFERENCED_TABLE_NAME='area' AND k.REFERENCED_COLUMN_NAME='id_area'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: existing attention-origin-area foreign key has an incompatible definition; no schema changes were applied.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS r WHERE r.CONSTRAINT_SCHEMA=DATABASE() AND r.CONSTRAINT_NAME='fk_atencion_empleado_origen' AND NOT (r.TABLE_NAME='atencion' AND r.REFERENCED_TABLE_NAME='empleado' AND r.DELETE_RULE='SET NULL' AND (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME)=1 AND EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k WHERE k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME='atencion' AND k.COLUMN_NAME='id_empleado_origen' AND k.REFERENCED_TABLE_NAME='empleado' AND k.REFERENCED_COLUMN_NAME='id_empleado'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: existing attention-origin-employee foreign key has an incompatible definition; no schema changes were applied.'; END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rol') THEN
    IF EXISTS (SELECT 1 FROM rol r JOIN (SELECT 1 id_rol,'superadmin' clave UNION ALL SELECT 2,'coordinador_general' UNION ALL SELECT 3,'coordinador' UNION ALL SELECT 4,'empleado' UNION ALL SELECT 5,'presidencia' UNION ALL SELECT 6,'admin_tareas') s ON r.id_rol=s.id_rol WHERE r.clave<>s.clave) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: a baseline role ID is already assigned to a different role; no schema changes were applied.'; END IF;
    IF EXISTS (SELECT 1 FROM rol r JOIN (SELECT 1 id_rol,'superadmin' clave UNION ALL SELECT 2,'coordinador_general' UNION ALL SELECT 3,'coordinador' UNION ALL SELECT 4,'empleado' UNION ALL SELECT 5,'presidencia' UNION ALL SELECT 6,'admin_tareas') s ON r.clave=s.clave WHERE r.id_rol<>s.id_rol) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: a baseline role key is assigned to a different ID; no schema changes were applied.'; END IF;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='area') THEN
    IF EXISTS (SELECT 1 FROM area a JOIN (SELECT 1 id_area,'recepcion' clave UNION ALL SELECT 2,'marketing' UNION ALL SELECT 3,'empresas' UNION ALL SELECT 4,'emprendedores' UNION ALL SELECT 5,'comercio' UNION ALL SELECT 6,'rrhh' UNION ALL SELECT 7,'vinculaciones' UNION ALL SELECT 8,'sistemas' UNION ALL SELECT 9,'administracion' UNION ALL SELECT 10,'general' UNION ALL SELECT 11,'presidencia' UNION ALL SELECT 14,'analista') s ON a.id_area=s.id_area WHERE a.clave<>s.clave) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: a baseline area ID is already assigned to a different area; no schema changes were applied.'; END IF;
    IF EXISTS (SELECT 1 FROM area a JOIN (SELECT 1 id_area,'recepcion' clave UNION ALL SELECT 2,'marketing' UNION ALL SELECT 3,'empresas' UNION ALL SELECT 4,'emprendedores' UNION ALL SELECT 5,'comercio' UNION ALL SELECT 6,'rrhh' UNION ALL SELECT 7,'vinculaciones' UNION ALL SELECT 8,'sistemas' UNION ALL SELECT 9,'administracion' UNION ALL SELECT 10,'general' UNION ALL SELECT 11,'presidencia' UNION ALL SELECT 14,'analista') s ON a.clave=s.clave WHERE a.id_area<>s.id_area) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: a baseline area key is assigned to a different ID; no schema changes were applied.'; END IF;
  END IF;
  SET FOREIGN_KEY_CHECKS=0;
  CREATE TABLE IF NOT EXISTS `actividad` (
  `id_actividad` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `tipo` enum('creada','proceso','completada','vencida','cancelada','cambio') NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `fecha_actividad` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_actividad`),
  KEY `fk_act_tarea` (`id_tarea`),
  KEY `fk_act_empleado` (`id_empleado`),
  CONSTRAINT `fk_act_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_act_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `area` (
  `id_area` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `atiende_clientes` tinyint(1) NOT NULL DEFAULT 0,
  `puede_clientes` tinyint(1) NOT NULL DEFAULT 0,
  `recibe_derivaciones` tinyint(1) NOT NULL DEFAULT 0,
  `recibe_turnos` tinyint(1) NOT NULL DEFAULT 0,
  `asignable_empleado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_area`),
  UNIQUE KEY `uq_area_clave` (`clave`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `area_informe` (
  `id_area` smallint(5) unsigned NOT NULL,
  `codigo_informe` varchar(32) NOT NULL,
  `habilitado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_area`,`codigo_informe`),
  CONSTRAINT `fk_area_informe_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `atencion` (
  `id_atencion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_area_origen` smallint(5) unsigned DEFAULT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `id_empleado_origen` int(10) unsigned DEFAULT NULL,
  `motivo` enum('consulta','documentacion','seguimiento','tramite','otro') NOT NULL DEFAULT 'consulta',
  `canal` enum('web','presencial','whatsapp','redes') NOT NULL DEFAULT 'presencial',
  `detalle` text DEFAULT NULL,
  `estado` enum('pendiente','abierta','en_atencion','cerrada','derivada','cancelada') NOT NULL DEFAULT 'abierta',
  `id_atencion_origen` int(10) unsigned DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_turno` datetime DEFAULT NULL,
  `fecha_derivacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_atencion`),
  KEY `fk_aten_cliente` (`id_cliente`),
  KEY `fk_aten_empleado` (`id_empleado`),
  KEY `fk_aten_origen` (`id_atencion_origen`),
  KEY `idx_aten_estado_fecha` (`estado`,`fecha_turno`),
  KEY `idx_aten_area_estado` (`id_area`,`estado`),
  KEY `fk_atencion_area_origen` (`id_area_origen`),
  KEY `fk_atencion_empleado_origen` (`id_empleado_origen`),
  CONSTRAINT `fk_aten_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_aten_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_aten_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_aten_origen` FOREIGN KEY (`id_atencion_origen`) REFERENCES `atencion` (`id_atencion`),
  CONSTRAINT `fk_atencion_area_origen` FOREIGN KEY (`id_area_origen`) REFERENCES `area` (`id_area`) ON DELETE SET NULL,
  CONSTRAINT `fk_atencion_empleado_origen` FOREIGN KEY (`id_empleado_origen`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `atencion_seguimiento` (
  `id_seguimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_atencion` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `estado_anterior` varchar(30) DEFAULT NULL,
  `estado_nuevo` varchar(30) NOT NULL,
  `accion` varchar(50) NOT NULL,
  `observacion` text DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_seguimiento`),
  KEY `idx_atseg_atencion` (`id_atencion`),
  KEY `idx_atseg_area` (`id_area`),
  KEY `idx_atseg_empleado` (`id_empleado`),
  CONSTRAINT `fk_atseg_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`) ON DELETE CASCADE,
  CONSTRAINT `fk_atseg_atencion` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`) ON DELETE CASCADE,
  CONSTRAINT `fk_atseg_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `aviso` (
  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado_de` int(10) unsigned NOT NULL,
  `id_empleado_para` int(10) unsigned NOT NULL,
  `fecha_aviso` datetime NOT NULL DEFAULT current_timestamp(),
  `confirmado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_aviso`),
  KEY `fk_aviso_tarea` (`id_tarea`),
  KEY `fk_aviso_de` (`id_empleado_de`),
  KEY `fk_aviso_para` (`id_empleado_para`),
  CONSTRAINT `fk_aviso_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_aviso_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_aviso_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `aviso_atencion` (
  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_atencion` int(10) unsigned NOT NULL,
  `id_empleado_de` int(10) unsigned DEFAULT NULL,
  `id_empleado_para` int(10) unsigned NOT NULL,
  `confirmado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_aviso` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_aviso`),
  KEY `fk_avaten_aten` (`id_atencion`),
  KEY `fk_avaten_de` (`id_empleado_de`),
  KEY `fk_avaten_para` (`id_empleado_para`),
  CONSTRAINT `fk_avaten_aten` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`),
  CONSTRAINT `fk_avaten_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_avaten_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `cliente` (
  `id_cliente` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipo_cliente` enum('emprendedor','empresa','municipalidad','organismo') NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `id_localidad` smallint(5) unsigned NOT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `cuil_cuit` varchar(13) NOT NULL,
  `id_area_afectada` smallint(5) unsigned DEFAULT NULL,
  `id_empleado_alta` int(10) unsigned DEFAULT NULL,
  `origen_contacto` enum('web','presencial','whatsapp','redes') DEFAULT NULL,
  `fecha_alta` date NOT NULL DEFAULT curdate(),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `uq_cliente_cuil_cuit` (`cuil_cuit`),
  KEY `fk_cliente_localidad` (`id_localidad`),
  KEY `fk_cliente_area` (`id_area_afectada`),
  KEY `idx_cliente_tipo` (`tipo_cliente`),
  KEY `idx_cliente_tipo_activo` (`tipo_cliente`,`activo`),
  KEY `fk_cliente_empleado_alta` (`id_empleado_alta`),
  CONSTRAINT `fk_cliente_area` FOREIGN KEY (`id_area_afectada`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_cliente_empleado_alta` FOREIGN KEY (`id_empleado_alta`) REFERENCES `empleado` (`id_empleado`) ON DELETE SET NULL,
  CONSTRAINT `fk_cliente_localidad` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `cliente_area` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cliarea` (`id_cliente`,`id_area`),
  KEY `fk_cliarea_area` (`id_area`),
  CONSTRAINT `fk_cliarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_cliarea_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `cliente_aviso` (
  `id_aviso` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `detalle` text DEFAULT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_aviso`),
  KEY `idx_claviso_user` (`id_usuario`,`leido`,`fecha`),
  CONSTRAINT `fk_claviso_user` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_cliente` (`id_usuario`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `cliente_empresa` (
  `id_cliente` int(10) unsigned NOT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  CONSTRAINT `fk_cliempresa_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `cliente_persona` (
  `id_cliente` int(10) unsigned NOT NULL,
  `genero` enum('femenino','masculino','otro') DEFAULT NULL,
  `dni` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  CONSTRAINT `fk_clipersona_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `cliente_programa` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_programa` int(10) unsigned NOT NULL,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  `fecha_hasta` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cliprog_prog` (`id_programa`),
  KEY `idx_cliprog_activo` (`id_cliente`,`id_programa`,`fecha_hasta`),
  CONSTRAINT `fk_cliprog_cli` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_cliprog_prog` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id_programa`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `cliente_solicitud` (
  `id_solicitud` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `dni` varchar(15) DEFAULT NULL,
  `cuil_cuit` varchar(13) NOT NULL,
  `nombre_negocio` varchar(150) DEFAULT NULL,
  `id_localidad` smallint(5) unsigned NOT NULL,
  `tipo_autodeclarado` enum('emprendedor','empresa') NOT NULL DEFAULT 'emprendedor',
  `sector` varchar(80) DEFAULT NULL,
  `sector_otro` varchar(120) DEFAULT NULL,
  `anios_funcionamiento` varchar(40) DEFAULT NULL,
  `personas_involucradas` varchar(40) DEFAULT NULL,
  `personal_formal` tinyint(1) NOT NULL DEFAULT 0,
  `producto_servicio` text DEFAULT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  `sugerencia_tipo` enum('emprendedor','empresa') DEFAULT NULL,
  `tipo_asignado` enum('emprendedor','empresa') DEFAULT NULL,
  `id_cliente` int(10) unsigned DEFAULT NULL,
  `id_revisa` int(10) unsigned DEFAULT NULL,
  `nota_revision` text DEFAULT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `idx_csol_estado` (`estado`,`fecha_solicitud`),
  KEY `idx_csol_correo` (`correo`),
  KEY `fk_csol_loc` (`id_localidad`),
  KEY `fk_csol_cli` (`id_cliente`),
  KEY `fk_csol_rev` (`id_revisa`),
  CONSTRAINT `fk_csol_cli` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_csol_loc` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`),
  CONSTRAINT `fk_csol_rev` FOREIGN KEY (`id_revisa`) REFERENCES `empleado` (`id_empleado`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `clientes_usuario_clientes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_cliente` int(10) unsigned NOT NULL,
  `rol` enum('titular','representante') NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  `fecha_hasta` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cuc_usuario_cliente` (`id_usuario`,`id_cliente`),
  KEY `fk_cuc_cliente` (`id_cliente`),
  CONSTRAINT `fk_cuc_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_cuc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario_cliente` (`id_usuario`),
  CONSTRAINT `chk_cuc_fechas` CHECK (`fecha_hasta` is null or `fecha_hasta` >= `fecha_desde`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `crm_aviso` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado_para` int(10) unsigned NOT NULL,
  `id_empleado_de` int(10) unsigned DEFAULT NULL,
  `id_tarea` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'info',
  `titulo` varchar(200) NOT NULL,
  `detalle` text DEFAULT NULL,
  `url` varchar(300) DEFAULT NULL,
  `confirmado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_crmaviso_de` (`id_empleado_de`),
  KEY `fk_crmaviso_tarea` (`id_tarea`),
  KEY `idx_crmaviso_bandeja` (`id_empleado_para`,`confirmado`,`fecha`),
  KEY `idx_crmaviso_tipo` (`tipo`),
  CONSTRAINT `fk_crmaviso_de` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_crmaviso_para` FOREIGN KEY (`id_empleado_para`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_crmaviso_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `crm_schema_version` (
  `clave` varchar(60) NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`clave`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `dominio` (
  `id_dominio` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_vencimiento` date NOT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_dominio`),
  UNIQUE KEY `idx_nombre_activo` (`nombre`,`activo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  CREATE TABLE IF NOT EXISTS `dominio_renovacion` (
  `id_renovacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_dominio` int(11) NOT NULL,
  `fecha_renovacion` date NOT NULL,
  `fecha_vencimiento_anterior` date NOT NULL,
  `fecha_vencimiento_nueva` date NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_renovacion`),
  KEY `idx_dominio` (`id_dominio`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
  CREATE TABLE IF NOT EXISTS `eje` (
  `id_eje` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tipo` int(10) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_eje`),
  UNIQUE KEY `uq_eje_tipo_nombre` (`id_tipo`,`nombre`),
  CONSTRAINT `fk_eje_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `empleado` (
  `id_empleado` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `foto_perfil_key` varchar(64) DEFAULT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellido` varchar(80) NOT NULL,
  `cuil` char(11) NOT NULL,
  `dni` varchar(15) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `telefono_alt` varchar(30) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `estado_civil` varchar(40) DEFAULT NULL,
  `localidad_domicilio` varchar(100) DEFAULT NULL,
  `provincia_domicilio` varchar(80) DEFAULT NULL,
  `puesto` varchar(120) DEFAULT NULL,
  `tipo_vinculo` varchar(80) DEFAULT NULL,
  `estado_laboral` varchar(40) NOT NULL DEFAULT 'activo',
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` varchar(200) DEFAULT NULL,
  `modalidad` varchar(40) DEFAULT NULL,
  `horario_jornada` varchar(120) DEFAULT NULL,
  `condicion_fiscal` varchar(60) DEFAULT NULL,
  `cuit_facturacion` varchar(13) DEFAULT NULL,
  `fecha_ingreso` date NOT NULL,
  `fecha_alta` date NOT NULL DEFAULT curdate(),
  `nombre_usuario` varchar(50) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `primer_acceso` tinyint(1) NOT NULL DEFAULT 1,
  `id_rol` smallint(5) unsigned NOT NULL,
  `id_area` smallint(5) unsigned DEFAULT NULL,
  `id_responsable` int(10) unsigned DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_empleado`),
  UNIQUE KEY `uq_empleado_cuil` (`cuil`),
  UNIQUE KEY `uq_empleado_dni` (`dni`),
  UNIQUE KEY `uq_empleado_correo` (`correo`),
  UNIQUE KEY `uq_empleado_usuario` (`nombre_usuario`),
  KEY `fk_empleado_rol` (`id_rol`),
  KEY `fk_empleado_area` (`id_area`),
  CONSTRAINT `fk_empleado_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_empleado_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `empleado_documento_legajo` (
  `id_documento` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` int(10) unsigned NOT NULL,
  `categoria` varchar(40) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `archivo_key` varchar(64) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  `id_usuario_carga` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_documento`),
  UNIQUE KEY `uq_empleado_documento_legajo_key` (`archivo_key`),
  KEY `idx_empleado_documento_legajo_fecha` (`id_empleado`,`fecha_carga`),
  KEY `idx_empleado_documento_legajo_cargado_por` (`id_usuario_carga`),
  CONSTRAINT `fk_empleado_documento_legajo_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_empleado_documento_legajo_usuario` FOREIGN KEY (`id_usuario_carga`) REFERENCES `empleado` (`id_empleado`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `empleado_rol_adicional` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_empleado` int(10) unsigned NOT NULL,
  `id_rol` smallint(5) unsigned NOT NULL,
  `id_area` smallint(5) unsigned DEFAULT NULL,
  `fecha_desde` date NOT NULL DEFAULT curdate(),
  `fecha_hasta` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rol_adic_rol` (`id_rol`),
  KEY `fk_rol_adic_area` (`id_area`),
  KEY `idx_rol_adic_activo` (`id_empleado`,`fecha_hasta`),
  CONSTRAINT `fk_rol_adic_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_rol_adic_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_rol_adic_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`),
  CONSTRAINT `chk_rol_adic_fechas` CHECK (`fecha_hasta` is null or `fecha_hasta` >= `fecha_desde`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `evento` (
  `id_evento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` varchar(40) NOT NULL DEFAULT 'reunion',
  `fecha` date NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_cliente` int(10) unsigned DEFAULT NULL,
  `id_localidad` smallint(5) unsigned DEFAULT NULL,
  `organismo` varchar(200) DEFAULT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `id_tarea_origen` int(10) unsigned DEFAULT NULL,
  `asistencia` text DEFAULT NULL,
  `resultado` text DEFAULT NULL,
  `autoinscripcion` tinyint(1) NOT NULL DEFAULT 0,
  `publico` enum('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_evento`),
  KEY `fk_evento_area` (`id_area`),
  KEY `fk_evento_cliente` (`id_cliente`),
  KEY `fk_evento_emp` (`id_empleado`),
  KEY `fk_evento_tarea` (`id_tarea_origen`),
  KEY `idx_evento_localidad_activo` (`id_localidad`,`activo`),
  CONSTRAINT `fk_evento_area_v2` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_evento_cliente_v2` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_evento_emp_v2` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_evento_localidad_v2` FOREIGN KEY (`id_localidad`) REFERENCES `localidad` (`id_localidad`),
  CONSTRAINT `fk_evento_tarea_v2` FOREIGN KEY (`id_tarea_origen`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `evento_inscripcion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_evento` int(10) unsigned NOT NULL,
  `id_cliente` int(10) unsigned NOT NULL,
  `estado` enum('inscrito','asistio','ausente') NOT NULL DEFAULT 'inscrito',
  `fecha_inscripcion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evins` (`id_evento`,`id_cliente`),
  KEY `idx_evins_cliente` (`id_cliente`,`estado`),
  CONSTRAINT `fk_evins_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_evins_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `evento_tarea` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_evento` int(10) unsigned NOT NULL,
  `id_tarea` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evtar` (`id_evento`,`id_tarea`),
  KEY `fk_evtar_tarea` (`id_tarea`),
  CONSTRAINT `fk_evtar_evento` FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id_evento`),
  CONSTRAINT `fk_evtar_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `informe_plantilla` (
  `id_plantilla` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(40) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_plantilla`),
  UNIQUE KEY `uq_informe_plantilla` (`clave`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `informe_publicado` (
  `id_informe` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_plantilla` smallint(5) unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `filtros_json` text DEFAULT NULL,
  `resumen_json` text DEFAULT NULL,
  `id_empleado_de` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned DEFAULT NULL,
  `publicado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_desde` date DEFAULT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_informe`),
  KEY `fk_infpub_plantilla` (`id_plantilla`),
  KEY `fk_infpub_empleado` (`id_empleado_de`),
  KEY `fk_infpub_area` (`id_area`),
  KEY `idx_infpub_presidencia` (`publicado`,`creado_en`),
  CONSTRAINT `fk_infpub_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_infpub_empleado` FOREIGN KEY (`id_empleado_de`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_infpub_plantilla` FOREIGN KEY (`id_plantilla`) REFERENCES `informe_plantilla` (`id_plantilla`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `localidad` (
  `id_localidad` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `id_provincia` smallint(5) unsigned NOT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `poligono_geojson` longtext DEFAULT NULL,
  PRIMARY KEY (`id_localidad`),
  UNIQUE KEY `uq_localidad` (`nombre`,`id_provincia`),
  KEY `fk_localidad_provincia` (`id_provincia`),
  CONSTRAINT `fk_localidad_provincia` FOREIGN KEY (`id_provincia`) REFERENCES `provincia` (`id_provincia`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `presentacion_registro_interno` (
  `modulo` varchar(40) NOT NULL,
  `id_registro` int(10) unsigned NOT NULL,
  `clasificacion` enum('simulado') NOT NULL DEFAULT 'simulado',
  `nota` varchar(255) NOT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`modulo`,`id_registro`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Control interno de datos simulados de presentacion; no es utilizado por la interfaz del CRM';
  CREATE TABLE IF NOT EXISTS `programa` (
  `id_programa` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_programa`),
  UNIQUE KEY `uq_programa_nombre` (`nombre`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `programa_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_programa` int(10) unsigned NOT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_tipo` int(10) unsigned DEFAULT NULL,
  `id_eje` int(10) unsigned DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_progitem_prog` (`id_programa`),
  KEY `fk_progitem_area` (`id_area`),
  KEY `fk_progitem_tipo` (`id_tipo`),
  KEY `fk_progitem_eje` (`id_eje`),
  CONSTRAINT `fk_progitem_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_progitem_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),
  CONSTRAINT `fk_progitem_prog` FOREIGN KEY (`id_programa`) REFERENCES `programa` (`id_programa`),
  CONSTRAINT `fk_progitem_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `provincia` (
  `id_provincia` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_provincia`),
  UNIQUE KEY `uq_provincia_nombre` (`nombre`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `rol` (
  `id_rol` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(30) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `uq_rol_clave` (`clave`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `sala_reunion` (
  `id_sala` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_sala`),
  UNIQUE KEY `uq_sala_reunion_nombre` (`nombre`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `sala_reunion_reserva` (
  `id_reserva` int(11) NOT NULL AUTO_INCREMENT,
  `id_sala` int(11) NOT NULL,
  `id_area` int(11) NOT NULL,
  `id_empleado` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `motivo` varchar(500) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activa',
  `creado_en` datetime NOT NULL,
  `actualizado_en` datetime NOT NULL,
  PRIMARY KEY (`id_reserva`),
  KEY `idx_sala_reserva_agenda` (`id_sala`,`fecha`,`estado`,`hora_inicio`,`hora_fin`),
  KEY `idx_sala_reserva_area` (`id_area`,`fecha`),
  KEY `idx_sala_reserva_empleado` (`id_empleado`,`fecha`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea` (
  `id_tarea` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_tipo` int(10) unsigned NOT NULL,
  `prioridad` enum('urgente','alta','media','baja') NOT NULL DEFAULT 'media',
  `estado` enum('no_iniciada','en_proceso','pausada','terminada','cancelada') NOT NULL DEFAULT 'no_iniciada',
  `fecha_inicio` date NOT NULL,
  `fecha_cierre` date NOT NULL,
  `avance` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `id_eje` int(10) unsigned DEFAULT NULL,
  `fecha_cierre_real` date DEFAULT NULL,
  `id_atencion` int(10) unsigned DEFAULT NULL,
  `id_cliente` int(10) unsigned DEFAULT NULL,
  `id_usuario_actor` int(10) unsigned DEFAULT NULL,
  `id_tarea_padre` int(10) unsigned DEFAULT NULL,
  `uid` varchar(20) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_tarea`),
  KEY `fk_tarea_area` (`id_area`),
  KEY `fk_tarea_tipo` (`id_tipo`),
  KEY `fk_tarea_cliente` (`id_cliente`),
  KEY `fk_tarea_usuario_act` (`id_usuario_actor`),
  KEY `fk_tarea_padre` (`id_tarea_padre`),
  KEY `idx_tarea_estado` (`estado`),
  KEY `idx_tarea_cierre_estado` (`fecha_cierre`,`estado`),
  KEY `fk_tarea_eje` (`id_eje`),
  KEY `fk_tarea_aten` (`id_atencion`),
  CONSTRAINT `fk_tarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_tarea_aten` FOREIGN KEY (`id_atencion`) REFERENCES `atencion` (`id_atencion`),
  CONSTRAINT `fk_tarea_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_tarea_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),
  CONSTRAINT `fk_tarea_padre` FOREIGN KEY (`id_tarea_padre`) REFERENCES `tarea` (`id_tarea`),
  CONSTRAINT `fk_tarea_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`),
  CONSTRAINT `fk_tarea_usuario_act` FOREIGN KEY (`id_usuario_actor`) REFERENCES `usuario_cliente` (`id_usuario`),
  CONSTRAINT `chk_tarea_avance` CHECK (`avance` between 0 and 100),
  CONSTRAINT `chk_tarea_fechas` CHECK (`fecha_cierre` >= `fecha_inicio`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_avance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tav_tarea` (`id_tarea`),
  KEY `fk_tav_emp` (`id_empleado`),
  CONSTRAINT `fk_tav_emp` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tav_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_aviso_vencimiento` (
  `id_tarea` int(10) unsigned NOT NULL,
  `fecha_aviso` date NOT NULL,
  PRIMARY KEY (`id_tarea`,`fecha_aviso`),
  CONSTRAINT `fk_tavenc_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_comentario` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned DEFAULT NULL,
  `id_usuario_actor` int(10) unsigned DEFAULT NULL,
  `texto` text NOT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_tcom_tarea` (`id_tarea`),
  KEY `fk_tcom_empleado` (`id_empleado`),
  KEY `fk_tcom_usuario_actor` (`id_usuario_actor`),
  CONSTRAINT `fk_tcom_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tcom_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`),
  CONSTRAINT `fk_tcom_usuario_actor` FOREIGN KEY (`id_usuario_actor`) REFERENCES `usuario_cliente` (`id_usuario`),
  CONSTRAINT `chk_tcom_autor` CHECK (`id_empleado` is not null or `id_usuario_actor` is not null)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_enlace` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_tarea_comentario` int(10) unsigned DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `fecha_carga` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tarea_enlace_comentario` (`id_tarea_comentario`),
  KEY `fk_tenl_tarea` (`id_tarea`),
  CONSTRAINT `fk_tenl_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_inicio` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned NOT NULL,
  `fecha_click_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tini_tarea_empleado` (`id_tarea`,`id_empleado`),
  KEY `fk_tini_empleado` (`id_empleado`),
  CONSTRAINT `fk_tini_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tini_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_modelo` (
  `id_modelo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_area` smallint(5) unsigned NOT NULL,
  `id_tipo` int(10) unsigned NOT NULL,
  `id_eje` int(10) unsigned DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  PRIMARY KEY (`id_modelo`),
  KEY `fk_tmod_area` (`id_area`),
  KEY `fk_tmod_tipo` (`id_tipo`),
  KEY `fk_tmod_eje` (`id_eje`),
  CONSTRAINT `fk_tmod_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`),
  CONSTRAINT `fk_tmod_eje` FOREIGN KEY (`id_eje`) REFERENCES `eje` (`id_eje`),
  CONSTRAINT `fk_tmod_tipo` FOREIGN KEY (`id_tipo`) REFERENCES `tipo_tarea` (`id_tipo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_responsable` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_empleado` int(10) unsigned NOT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tresp_tarea_empleado` (`id_tarea`,`id_empleado`),
  KEY `fk_tresp_empleado` (`id_empleado`),
  CONSTRAINT `fk_tresp_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tresp_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tarea_solicitud_baja` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tarea` int(10) unsigned NOT NULL,
  `id_solicita` int(10) unsigned NOT NULL,
  `motivo` text NOT NULL,
  `estado` enum('pendiente_coord','pendiente_general','aceptada','rechazada') NOT NULL DEFAULT 'pendiente_coord',
  `id_revisa_coord` int(10) unsigned DEFAULT NULL,
  `nota_coord` text DEFAULT NULL,
  `id_revisa_general` int(10) unsigned DEFAULT NULL,
  `nota_general` text DEFAULT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tsb_solicita` (`id_solicita`),
  KEY `fk_tsb_coord` (`id_revisa_coord`),
  KEY `fk_tsb_general` (`id_revisa_general`),
  KEY `idx_tsb_tarea` (`id_tarea`),
  KEY `idx_tsb_estado` (`estado`),
  KEY `idx_tsb_pendientes` (`estado`,`fecha_solicitud`),
  CONSTRAINT `fk_tsb_coord` FOREIGN KEY (`id_revisa_coord`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_general` FOREIGN KEY (`id_revisa_general`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_solicita` FOREIGN KEY (`id_solicita`) REFERENCES `empleado` (`id_empleado`),
  CONSTRAINT `fk_tsb_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tarea` (`id_tarea`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `tipo_tarea` (
  `id_tipo` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_area` smallint(5) unsigned NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`id_tipo`),
  UNIQUE KEY `uq_tipo_tarea_area_nombre` (`id_area`,`nombre`),
  CONSTRAINT `fk_tipo_tarea_area` FOREIGN KEY (`id_area`) REFERENCES `area` (`id_area`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `turno_config` (
  `clave` varchar(40) NOT NULL,
  `valor` varchar(120) NOT NULL,
  PRIMARY KEY (`clave`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  CREATE TABLE IF NOT EXISTS `usuario_cliente` (
  `id_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `primer_acceso` tinyint(1) NOT NULL DEFAULT 1,
  `dni` varchar(15) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_cliente_correo` (`correo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='area' AND COLUMN_NAME='asignable_empleado') THEN ALTER TABLE `area` ADD COLUMN `asignable_empleado` tinyint(1) NOT NULL DEFAULT 1; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='area' AND COLUMN_NAME='atiende_clientes') THEN ALTER TABLE `area` ADD COLUMN `atiende_clientes` tinyint(1) NOT NULL DEFAULT 0; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='area' AND COLUMN_NAME='puede_clientes') THEN ALTER TABLE `area` ADD COLUMN `puede_clientes` tinyint(1) NOT NULL DEFAULT 0; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='area' AND COLUMN_NAME='recibe_derivaciones') THEN ALTER TABLE `area` ADD COLUMN `recibe_derivaciones` tinyint(1) NOT NULL DEFAULT 0; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='area' AND COLUMN_NAME='recibe_turnos') THEN ALTER TABLE `area` ADD COLUMN `recibe_turnos` tinyint(1) NOT NULL DEFAULT 0; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atencion' AND COLUMN_NAME='fecha_derivacion') THEN ALTER TABLE `atencion` ADD COLUMN `fecha_derivacion` datetime DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atencion' AND COLUMN_NAME='id_area_origen') THEN ALTER TABLE `atencion` ADD COLUMN `id_area_origen` smallint(5) unsigned DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atencion' AND COLUMN_NAME='id_empleado_origen') THEN ALTER TABLE `atencion` ADD COLUMN `id_empleado_origen` int(10) unsigned DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cliente' AND COLUMN_NAME='id_empleado_alta') THEN ALTER TABLE `cliente` ADD COLUMN `id_empleado_alta` int(10) unsigned DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='condicion_fiscal') THEN ALTER TABLE `empleado` ADD COLUMN `condicion_fiscal` varchar(60) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='cuit_facturacion') THEN ALTER TABLE `empleado` ADD COLUMN `cuit_facturacion` varchar(13) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='estado_civil') THEN ALTER TABLE `empleado` ADD COLUMN `estado_civil` varchar(40) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='estado_laboral') THEN ALTER TABLE `empleado` ADD COLUMN `estado_laboral` varchar(40) NOT NULL DEFAULT 'activo'; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='fecha_baja') THEN ALTER TABLE `empleado` ADD COLUMN `fecha_baja` date DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='fecha_nacimiento') THEN ALTER TABLE `empleado` ADD COLUMN `fecha_nacimiento` date DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='foto_perfil_key') THEN ALTER TABLE `empleado` ADD COLUMN `foto_perfil_key` varchar(64) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='horario_jornada') THEN ALTER TABLE `empleado` ADD COLUMN `horario_jornada` varchar(120) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='id_responsable') THEN ALTER TABLE `empleado` ADD COLUMN `id_responsable` int(10) unsigned DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='localidad_domicilio') THEN ALTER TABLE `empleado` ADD COLUMN `localidad_domicilio` varchar(100) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='modalidad') THEN ALTER TABLE `empleado` ADD COLUMN `modalidad` varchar(40) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='motivo_baja') THEN ALTER TABLE `empleado` ADD COLUMN `motivo_baja` varchar(200) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='provincia_domicilio') THEN ALTER TABLE `empleado` ADD COLUMN `provincia_domicilio` varchar(80) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='puesto') THEN ALTER TABLE `empleado` ADD COLUMN `puesto` varchar(120) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='telefono_alt') THEN ALTER TABLE `empleado` ADD COLUMN `telefono_alt` varchar(30) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='empleado' AND COLUMN_NAME='tipo_vinculo') THEN ALTER TABLE `empleado` ADD COLUMN `tipo_vinculo` varchar(80) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evento' AND COLUMN_NAME='autoinscripcion') THEN ALTER TABLE `evento` ADD COLUMN `autoinscripcion` tinyint(1) NOT NULL DEFAULT 0; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evento' AND COLUMN_NAME='id_localidad') THEN ALTER TABLE `evento` ADD COLUMN `id_localidad` smallint(5) unsigned DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evento' AND COLUMN_NAME='publico') THEN ALTER TABLE `evento` ADD COLUMN `publico` enum('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos'; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='localidad' AND COLUMN_NAME='latitud') THEN ALTER TABLE `localidad` ADD COLUMN `latitud` decimal(10,7) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='localidad' AND COLUMN_NAME='longitud') THEN ALTER TABLE `localidad` ADD COLUMN `longitud` decimal(10,7) DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='localidad' AND COLUMN_NAME='poligono_geojson') THEN ALTER TABLE `localidad` ADD COLUMN `poligono_geojson` longtext DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tarea_enlace' AND COLUMN_NAME='id_tarea_comentario') THEN ALTER TABLE `tarea_enlace` ADD COLUMN `id_tarea_comentario` int(10) unsigned DEFAULT NULL; END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='evento' AND INDEX_NAME='idx_evento_localidad_activo') THEN ALTER TABLE evento ADD INDEX idx_evento_localidad_activo (id_localidad,activo); END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tarea_enlace' AND INDEX_NAME='uq_tarea_enlace_comentario') THEN
    SELECT COUNT(*) INTO v_duplicate FROM (SELECT id_tarea_comentario FROM tarea_enlace WHERE id_tarea_comentario IS NOT NULL GROUP BY id_tarea_comentario HAVING COUNT(*)>1) d;
    IF v_duplicate>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: duplicate task-comment links prevent the unique index; no existing rows were changed.'; END IF;
    ALTER TABLE tarea_enlace ADD UNIQUE KEY uq_tarea_enlace_comentario (id_tarea_comentario);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_evento_localidad') THEN
    SELECT COUNT(*) INTO v_fk_errors FROM evento c LEFT JOIN localidad p ON p.id_localidad=c.id_localidad WHERE c.id_localidad IS NOT NULL AND p.id_localidad IS NULL;
    IF v_fk_errors>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: evento.id_localidad contains orphan references; no existing rows were changed.'; END IF;
    ALTER TABLE evento ADD CONSTRAINT fk_evento_localidad FOREIGN KEY (id_localidad) REFERENCES localidad(id_localidad);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_cliente_empleado_alta') THEN
    SELECT COUNT(*) INTO v_fk_errors FROM cliente c LEFT JOIN empleado p ON p.id_empleado=c.id_empleado_alta WHERE c.id_empleado_alta IS NOT NULL AND p.id_empleado IS NULL;
    IF v_fk_errors>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: cliente.id_empleado_alta contains orphan references; no existing rows were changed.'; END IF;
    ALTER TABLE cliente ADD CONSTRAINT fk_cliente_empleado_alta FOREIGN KEY (id_empleado_alta) REFERENCES empleado(id_empleado) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_atencion_area_origen') THEN
    SELECT COUNT(*) INTO v_fk_errors FROM atencion c LEFT JOIN area p ON p.id_area=c.id_area_origen WHERE c.id_area_origen IS NOT NULL AND p.id_area IS NULL;
    IF v_fk_errors>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: atencion.id_area_origen contains orphan references; no existing rows were changed.'; END IF;
    ALTER TABLE atencion ADD CONSTRAINT fk_atencion_area_origen FOREIGN KEY (id_area_origen) REFERENCES area(id_area) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='fk_atencion_empleado_origen') THEN
    SELECT COUNT(*) INTO v_fk_errors FROM atencion c LEFT JOIN empleado p ON p.id_empleado=c.id_empleado_origen WHERE c.id_empleado_origen IS NOT NULL AND p.id_empleado IS NULL;
    IF v_fk_errors>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='CRM BDD: atencion.id_empleado_origen contains orphan references; no existing rows were changed.'; END IF;
    ALTER TABLE atencion ADD CONSTRAINT fk_atencion_empleado_origen FOREIGN KEY (id_empleado_origen) REFERENCES empleado(id_empleado) ON DELETE SET NULL;
  END IF;
  INSERT IGNORE INTO rol (id_rol,clave,nombre) VALUES
(1, 'superadmin', 'Superadmin'),
(2, 'coordinador_general', 'Coordinador general'),
(3, 'coordinador', 'Coordinador de área'),
(4, 'empleado', 'Empleado'),
(5, 'presidencia', 'Presidencia'),
(6, 'admin_tareas', 'Administración de tareas');
  INSERT IGNORE INTO area (id_area,clave,nombre,activo,atiende_clientes,puede_clientes,recibe_derivaciones,recibe_turnos,asignable_empleado) VALUES
(1, 'recepcion', 'Recepción', 1, 1, 1, 1, 1, 1),
(2, 'marketing', 'Marketing', 1, 1, 1, 1, 1, 1),
(3, 'empresas', 'Empresas', 1, 1, 1, 1, 1, 1),
(4, 'emprendedores', 'Emprendedores', 1, 1, 1, 1, 1, 1),
(5, 'comercio', 'Comercio exterior', 1, 1, 1, 1, 1, 1),
(6, 'rrhh', 'Recursos humanos', 1, 1, 1, 1, 1, 1),
(7, 'vinculaciones', 'Vinculación académica', 1, 1, 1, 1, 1, 1),
(8, 'sistemas', 'Sistemas', 1, 0, 1, 0, 0, 1),
(9, 'administracion', 'Administración', 1, 1, 1, 1, 1, 1),
(10, 'general', 'Coordinación general', 1, 0, 0, 0, 0, 0),
(11, 'presidencia', 'Presidencia', 1, 0, 0, 0, 0, 0),
(14, 'analista', 'Analista', 1, 1, 1, 1, 1, 1);
  IF v_area_informe_existe=0 THEN
    INSERT IGNORE INTO area_informe (id_area,codigo_informe,habilitado) SELECT id_area,'colaboradores',1 FROM area;
    INSERT IGNORE INTO area_informe (id_area,codigo_informe,habilitado) SELECT id_area,'clientes',1 FROM area;
    INSERT IGNORE INTO area_informe (id_area,codigo_informe,habilitado) SELECT id_area,'actividades',1 FROM area;
    INSERT IGNORE INTO area_informe (id_area,codigo_informe,habilitado) SELECT id_area,'tareas',1 FROM area;
    INSERT IGNORE INTO area_informe (id_area,codigo_informe,habilitado) SELECT id_area,'atenciones',1 FROM area;
  END IF;
  SET FOREIGN_KEY_CHECKS=v_fk_checks;
  DROP TEMPORARY TABLE IF EXISTS _crm_bdd_expected_columns;
  DROP TEMPORARY TABLE IF EXISTS _crm_bdd_allowed_missing;
END$$
CALL crm_ademi_bdd_apply_20261019()$$
DROP PROCEDURE crm_ademi_bdd_apply_20261019$$
DELIMITER ;
