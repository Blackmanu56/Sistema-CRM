<?php
require_once __DIR__.'/../_init.php';
error_reporting(E_ALL);
$modo_modal_informe=isset($_GET['modal_informe']) && is_scalar($_GET['modal_informe']) && $_GET['modal_informe']==='1';
if($modo_modal_informe)
{
	crm_proteger_staff();
	if(!function_exists('acceso_puede_ver_informe_colaboradores') || !acceso_puede_ver_informe_colaboradores() || !acceso_puede_gestionar('rrhh'))
	{
		http_response_code(403);
		header('Content-Type: text/html; charset=UTF-8');
		echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ficha no disponible</title><style>body{font:15px Arial,sans-serif;margin:0;padding:24px;color:#334155;background:#fff}.message{max-width:620px;margin:12vh auto;padding:24px;border:1px solid #dbe3ed;border-radius:10px;background:#f8fafc;text-align:center}</style></head><body><main class="message" role="alert">La ficha no está disponible para tu perfil.</main></body></html>';
		exit();
	}
	crm_proteger_staff('rrhh');
	acceso_exigir_gestion('rrhh');
}
else
{
	crm_proteger_staff('rrhh');
	acceso_exigir_gestion('rrhh');
}
require_once BASE_PATH.'/includes/rrhh/fotos.php';
require_once BASE_PATH.'/includes/rrhh/legajo.php';
$auto_imprimir=!$modo_modal_informe && isset($_GET['imprimir']) && is_scalar($_GET['imprimir']) && $_GET['imprimir']==='1';
$modo_impresion_historial=!$modo_modal_informe && isset($_GET['imprimir_historial']) && is_scalar($_GET['imprimir_historial']) && $_GET['imprimir_historial']==='1';
$impresion_inactiva_autorizada=($auto_imprimir || $modo_modal_informe) && function_exists('acceso_puede_ver_informe_colaboradores') && acceso_puede_ver_informe_colaboradores();
if($modo_modal_informe && !$impresion_inactiva_autorizada)
{
	http_response_code(403);
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ficha no disponible</title><style>body{font:15px Arial,sans-serif;margin:0;padding:24px;color:#334155;background:#fff}.message{max-width:620px;margin:12vh auto;padding:24px;border:1px solid #dbe3ed;border-radius:10px;background:#f8fafc;text-align:center}</style></head><body><main class="message" role="alert">No estás autorizado para consultar esta ficha desde Informes.</main></body></html>';
	exit();
}
$mostrar_error_modal=function($mensaje, $estado_http) use ($modo_modal_informe) {
	if($modo_modal_informe)
	{
		http_response_code(intval($estado_http));
		header('Content-Type: text/html; charset=UTF-8');
		echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ficha no disponible</title><style>body{font:15px Arial,sans-serif;margin:0;padding:24px;color:#334155;background:#fff}.message{max-width:620px;margin:12vh auto;padding:24px;border:1px solid #dbe3ed;border-radius:10px;background:#f8fafc;text-align:center}</style></head><body><main class="message" role="alert">'.htmlspecialchars((string)$mensaje, ENT_QUOTES, 'UTF-8').'</main></body></html>';
		exit();
	}
	header('Location: empleados.php');
	exit();
};

$id_raw=isset($_GET['id']) ? (string)$_GET['id'] : '';
if($id_raw==='' || !ctype_digit($id_raw) || intval($id_raw)<=0)
{
	$mostrar_error_modal('La ficha solicitada no está disponible.', 404);
}

$empleado=acceso_empleado_por_id(intval($id_raw));
if(!$empleado || $empleado['rol']==='superadmin' || (empty($empleado['activo']) && !$impresion_inactiva_autorizada))
{
	$mostrar_error_modal('La ficha solicitada no existe o no está autorizada para esta consulta.', 404);
}

$foto_id=intval($empleado['id']);
if($_SERVER['REQUEST_METHOD']==='POST')
{
	header('Location: ficha-empleado.php?id='.$foto_id);
	exit();
}
$foto_columna_disponible=crm_rrhh_foto_columna_disponible();
$foto_key=$foto_columna_disponible ? crm_rrhh_foto_key_por_empleado($foto_id) : '';
$foto_url=($foto_key!=='' && crm_rrhh_foto_ruta_por_key($foto_key)!=='') ? url_crm('foto-empleado.php?id='.$foto_id.'&v='.rawurlencode($foto_key)) : '';
$legajo_disponible=!$modo_modal_informe && crm_rrhh_legajo_tabla_disponible();
$puede_gestionar_legajo=!$modo_modal_informe && crm_rrhh_legajo_puede_gestionar($foto_id);
if($modo_impresion_historial && !$puede_gestionar_legajo)
{
	http_response_code(403);
	header('Content-Type: text/plain; charset=UTF-8');
	exit('No estás autorizado para imprimir este historial laboral.');
}
$legajo_categorias=crm_rrhh_legajo_categorias();
$legajo_documentos=$legajo_disponible && $puede_gestionar_legajo ? crm_rrhh_legajo_listar($foto_id) : array();
$legajo_error_consulta=($legajo_documentos===false);
if($legajo_error_consulta) { $legajo_documentos=array(); }
$legajo_fecha_valida=function($valor) {
	if(!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) { return false; }
	$fecha=DateTime::createFromFormat('!Y-m-d', $valor);
	$errores=DateTime::getLastErrors();
	return $fecha && (!$errores || ($errores['warning_count']===0 && $errores['error_count']===0)) && $fecha->format('Y-m-d')===$valor;
};
$historial_desde=isset($_GET['historial_desde']) && is_scalar($_GET['historial_desde']) && $legajo_fecha_valida((string)$_GET['historial_desde']) ? (string)$_GET['historial_desde'] : '';
$historial_hasta=isset($_GET['historial_hasta']) && is_scalar($_GET['historial_hasta']) && $legajo_fecha_valida((string)$_GET['historial_hasta']) ? (string)$_GET['historial_hasta'] : '';
$historial_rango_invalido=$historial_desde!=='' && $historial_hasta!=='' && $historial_desde>$historial_hasta;
$historial_tabs=array('tareas'=>'Tareas asignadas','movimientos'=>'Movimientos de tareas','actividades'=>'Actividades registradas');
$historial_tab=isset($_GET['historial_tab']) && is_scalar($_GET['historial_tab']) && isset($historial_tabs[(string)$_GET['historial_tab']]) ? (string)$_GET['historial_tab'] : 'tareas';
$historial_pagina=isset($_GET['historial_pagina']) && is_scalar($_GET['historial_pagina']) && ctype_digit((string)$_GET['historial_pagina']) ? max(1, intval($_GET['historial_pagina'])) : 1;
$historial_por_pagina=10;
$historial_buscar=isset($_GET['historial_buscar']) && is_scalar($_GET['historial_buscar']) ? trim((string)$_GET['historial_buscar']) : '';
if(function_exists('mb_substr')) { $historial_buscar=mb_substr($historial_buscar, 0, 120, 'UTF-8'); } else { $historial_buscar=substr($historial_buscar, 0, 120); }
$historial_area=isset($_GET['historial_area']) && is_scalar($_GET['historial_area']) && ctype_digit((string)$_GET['historial_area']) ? intval($_GET['historial_area']) : 0;
$historial_estado=isset($_GET['historial_estado']) && is_scalar($_GET['historial_estado']) && in_array((string)$_GET['historial_estado'], array('no_iniciada','en_proceso','pausada','terminada','cancelada'), true) ? (string)$_GET['historial_estado'] : '';
$historial_movimiento=isset($_GET['historial_movimiento']) && is_scalar($_GET['historial_movimiento']) && in_array((string)$_GET['historial_movimiento'], array('creada','proceso','completada','vencida','cancelada','cambio'), true) ? (string)$_GET['historial_movimiento'] : '';
$historial_tipo_actividad=isset($_GET['historial_tipo_actividad']) && is_scalar($_GET['historial_tipo_actividad']) ? trim((string)$_GET['historial_tipo_actividad']) : '';
if(strlen($historial_tipo_actividad)>40) { $historial_tipo_actividad=''; }
$historial_estado_actividad=isset($_GET['historial_estado_actividad']) && is_scalar($_GET['historial_estado_actividad']) && in_array((string)$_GET['historial_estado_actividad'], array('activa','inactiva'), true) ? (string)$_GET['historial_estado_actividad'] : '';
$legajo_tareas=array();
$legajo_tareas_resumen=array('total'=>0, 'no_iniciada'=>0, 'en_proceso'=>0, 'pausada'=>0, 'terminada'=>0, 'cancelada'=>0);
$legajo_historial_tareas=array();
$legajo_historial_actividades=array();
$legajo_historial_totales=array('tareas'=>0,'movimientos'=>0,'actividades'=>0);
$legajo_historial_areas=array();
$legajo_historial_tipos_actividad=array();
$legajo_historial_tipos_movimiento=array();
$evento_tarea_vinculada_sql='0';
$parametros_evento_tarea=array();
$tipos_evento_tarea='';
$legajo_historial_disponible=!$modo_modal_informe && $puede_gestionar_legajo;
if($legajo_historial_disponible && function_exists('acceso_tabla_existe') && acceso_tabla_existe('tarea') && acceso_tabla_existe('tarea_responsable'))
{
	$filas_resumen=acceso_consulta("SELECT t.estado, COUNT(DISTINCT t.id_tarea) AS total FROM tarea_responsable tr INNER JOIN tarea t ON t.id_tarea=tr.id_tarea WHERE tr.id_empleado=? GROUP BY t.estado", 'i', array($foto_id));
	foreach((array)$filas_resumen as $fila_resumen)
	{
		$estado_tarea=isset($fila_resumen['estado']) ? (string)$fila_resumen['estado'] : '';
		$cantidad_estado=isset($fila_resumen['total']) ? intval($fila_resumen['total']) : 0;
		if(isset($legajo_tareas_resumen[$estado_tarea])) { $legajo_tareas_resumen[$estado_tarea]=$cantidad_estado; }
		$legajo_tareas_resumen['total']+=$cantidad_estado;
	}
	$sql_tareas="SELECT DISTINCT t.id_tarea AS id, t.id_tarea, t.titulo, t.estado, t.prioridad, t.avance, t.fecha_inicio, t.fecha_cierre, t.id_tarea_padre, a.clave AS area_clave, a.nombre AS area_nombre FROM tarea_responsable tr INNER JOIN tarea t ON t.id_tarea=tr.id_tarea INNER JOIN area a ON a.id_area=t.id_area WHERE tr.id_empleado=?";
	$tipos_tareas='i'; $params_tareas=array($foto_id);
	if($historial_desde!=='') { $sql_tareas.=' AND (t.fecha_cierre IS NULL OR t.fecha_cierre>=?)'; $tipos_tareas.='s'; $params_tareas[]=$historial_desde; }
	if($historial_hasta!=='') { $sql_tareas.=' AND (t.fecha_inicio IS NULL OR t.fecha_inicio<=?)'; $tipos_tareas.='s'; $params_tareas[]=$historial_hasta; }
	if($historial_area>0) { $sql_tareas.=' AND t.id_area=?'; $tipos_tareas.='i'; $params_tareas[]=$historial_area; }
	if($historial_buscar!=='') { $sql_tareas.=' AND t.titulo LIKE ?'; $tipos_tareas.='s'; $params_tareas[]='%'.$historial_buscar.'%'; }
	if($historial_estado!=='') { $sql_tareas.=' AND t.estado=?'; $tipos_tareas.='s'; $params_tareas[]=$historial_estado; }
	if($historial_rango_invalido) { $sql_tareas.=' AND 1=0'; }
	$fila_total=acceso_consulta_una('SELECT COUNT(DISTINCT t.id_tarea) AS total FROM tarea_responsable tr INNER JOIN tarea t ON t.id_tarea=tr.id_tarea INNER JOIN area a ON a.id_area=t.id_area'.substr($sql_tareas, strpos($sql_tareas, ' WHERE ')), $tipos_tareas, $params_tareas);
	$legajo_historial_totales['tareas']=$fila_total ? intval($fila_total['total']) : 0;
	if(!$modo_impresion_historial && $historial_tab==='tareas') { $historial_paginas_tmp=max(1,(int)ceil($legajo_historial_totales['tareas']/$historial_por_pagina)); $historial_pagina=min($historial_pagina,$historial_paginas_tmp); }
	if($historial_tab==='tareas')
	{
		$sql_tareas.=' ORDER BY t.fecha_cierre DESC, t.id_tarea DESC';
		if(!$modo_impresion_historial) { $sql_tareas.=' LIMIT ? OFFSET ?'; $tipos_tareas.='ii'; $params_tareas[]=$historial_por_pagina; $params_tareas[]=($historial_pagina-1)*$historial_por_pagina; }
		$legajo_tareas=(array)acceso_consulta($sql_tareas, $tipos_tareas, $params_tareas);
	}
}
if($legajo_historial_disponible && function_exists('acceso_tabla_existe') && acceso_tabla_existe('actividad') && acceso_tabla_existe('tarea') && acceso_tabla_existe('tarea_responsable'))
{
	$sql_historial="SELECT act.id_actividad, act.tipo, act.descripcion, act.fecha_actividad, t.id_tarea AS id, t.id_tarea, t.titulo, t.estado, t.id_tarea_padre, a.clave AS area_clave, a.nombre AS area_nombre, CASE WHEN EXISTS(SELECT 1 FROM tarea_responsable tr WHERE tr.id_tarea=t.id_tarea AND tr.id_empleado=?) THEN 1 ELSE 0 END AS empleado_responsable FROM actividad act INNER JOIN tarea t ON t.id_tarea=act.id_tarea INNER JOIN area a ON a.id_area=t.id_area WHERE act.id_empleado=?";
	$tipos_historial='ii'; $parametros_historial=array($foto_id, $foto_id);
	if($historial_desde!=='') { $sql_historial.=' AND act.fecha_actividad>=?'; $tipos_historial.='s'; $parametros_historial[]=$historial_desde.' 00:00:00'; }
	if($historial_hasta!=='') { $sql_historial.=' AND act.fecha_actividad<=?'; $tipos_historial.='s'; $parametros_historial[]=$historial_hasta.' 23:59:59'; }
	if($historial_area>0) { $sql_historial.=' AND t.id_area=?'; $tipos_historial.='i'; $parametros_historial[]=$historial_area; }
	if($historial_buscar!=='') { $sql_historial.=' AND (t.titulo LIKE ? OR act.descripcion LIKE ?)'; $tipos_historial.='ss'; $parametros_historial[]='%'.$historial_buscar.'%'; $parametros_historial[]='%'.$historial_buscar.'%'; }
	if($historial_movimiento!=='') { $sql_historial.=' AND act.tipo=?'; $tipos_historial.='s'; $parametros_historial[]=$historial_movimiento; }
	if($historial_rango_invalido) { $sql_historial.=' AND 1=0'; }
	$tipos_historial_conteo=substr($tipos_historial, 1);
	$params_historial_conteo=array_slice($parametros_historial, 1);
	$fila_total=acceso_consulta_una('SELECT COUNT(DISTINCT act.id_actividad) AS total'.substr($sql_historial, strpos($sql_historial, ' FROM actividad act')), $tipos_historial_conteo, $params_historial_conteo);
	$legajo_historial_totales['movimientos']=$fila_total ? intval($fila_total['total']) : 0;
	if(!$modo_impresion_historial && $historial_tab==='movimientos') { $historial_paginas_tmp=max(1,(int)ceil($legajo_historial_totales['movimientos']/$historial_por_pagina)); $historial_pagina=min($historial_pagina,$historial_paginas_tmp); }
	if($historial_tab==='movimientos')
	{
		$sql_historial.=' ORDER BY act.fecha_actividad DESC, act.id_actividad DESC';
		if(!$modo_impresion_historial) { $sql_historial.=' LIMIT ? OFFSET ?'; $tipos_historial.='ii'; $parametros_historial[]=$historial_por_pagina; $parametros_historial[]=($historial_pagina-1)*$historial_por_pagina; }
		$legajo_historial_tareas=(array)acceso_consulta($sql_historial, $tipos_historial, $parametros_historial);
	}
}
if($legajo_historial_disponible && !$historial_rango_invalido && function_exists('acceso_tabla_existe') && acceso_tabla_existe('evento'))
{
	$evento_tarea_vinculada_sql='0';
	$parametros_evento_tarea=array();
	$tipos_evento_tarea='';
	if(acceso_tabla_existe('evento_tarea') && acceso_tabla_existe('tarea') && acceso_tabla_existe('tarea_responsable'))
	{
		$evento_tarea_vinculada_sql="EXISTS (
			SELECT 1 FROM evento_tarea et
			INNER JOIN tarea_responsable tr ON tr.id_tarea=et.id_tarea
			WHERE et.id_evento=ev.id_evento AND tr.id_empleado=?
		)";
		$tipos_evento_tarea='i';
		$parametros_evento_tarea=array($foto_id);
	}
	if(function_exists('crm_columna_existe') && crm_columna_existe('evento', 'id_tarea_origen') && acceso_tabla_existe('tarea') && acceso_tabla_existe('tarea_responsable'))
	{
		$evento_tarea_origen_sql="EXISTS (
			SELECT 1 FROM tarea_responsable tro
			WHERE tro.id_tarea=ev.id_tarea_origen AND tro.id_empleado=?
		)";
		$evento_tarea_vinculada_sql=$evento_tarea_vinculada_sql==='0' ? $evento_tarea_origen_sql : '('.$evento_tarea_vinculada_sql.' OR '.$evento_tarea_origen_sql.')';
		$tipos_evento_tarea.='i';
		$parametros_evento_tarea[]=$foto_id;
	}
	$sql_eventos="SELECT ev.id_evento, ev.titulo, ev.descripcion, ev.tipo, ev.fecha, ev.id_empleado, ev.activo, a.clave AS area_clave, a.nombre AS area_nombre, (ev.id_empleado=?) AS empleado_responsable_actividad, (".$evento_tarea_vinculada_sql.") AS empleado_responsable_tarea FROM evento ev INNER JOIN area a ON a.id_area=ev.id_area WHERE (ev.id_empleado=? OR (".$evento_tarea_vinculada_sql."))";
	$tipos_eventos='i'.$tipos_evento_tarea.'i'.$tipos_evento_tarea;
	$parametros_eventos=array_merge(array($foto_id), $parametros_evento_tarea, array($foto_id), $parametros_evento_tarea);
	if($historial_desde!=='') { $sql_eventos.=' AND ev.fecha>=?'; $tipos_eventos.='s'; $parametros_eventos[]=$historial_desde; }
	if($historial_hasta!=='') { $sql_eventos.=' AND ev.fecha<=?'; $tipos_eventos.='s'; $parametros_eventos[]=$historial_hasta; }
	if($historial_area>0) { $sql_eventos.=' AND ev.id_area=?'; $tipos_eventos.='i'; $parametros_eventos[]=$historial_area; }
	if($historial_buscar!=='') { $sql_eventos.=' AND (ev.titulo LIKE ? OR ev.descripcion LIKE ?)'; $tipos_eventos.='ss'; $parametros_eventos[]='%'.$historial_buscar.'%'; $parametros_eventos[]='%'.$historial_buscar.'%'; }
	if($historial_tipo_actividad!=='') { $sql_eventos.=' AND ev.tipo=?'; $tipos_eventos.='s'; $parametros_eventos[]=$historial_tipo_actividad; }
	if($historial_estado_actividad==='activa') { $sql_eventos.=' AND ev.activo=1'; }
	elseif($historial_estado_actividad==='inactiva') { $sql_eventos.=' AND ev.activo=0'; }
	if($historial_rango_invalido) { $sql_eventos.=' AND 1=0'; }
	$sql_tipos_evento='SELECT DISTINCT ev.tipo FROM evento ev WHERE (ev.id_empleado=? OR ('.$evento_tarea_vinculada_sql.')) ORDER BY ev.tipo';
	$legajo_historial_tipos_actividad=(array)acceso_consulta($sql_tipos_evento, 'i'.$tipos_evento_tarea, array_merge(array($foto_id),$parametros_evento_tarea));
	$desplazamiento_eventos=1+count($parametros_evento_tarea);
	$tipos_eventos_conteo=substr($tipos_eventos, $desplazamiento_eventos);
	$params_eventos_conteo=array_slice($parametros_eventos, $desplazamiento_eventos);
	$fila_total=acceso_consulta_una('SELECT COUNT(*) AS total'.substr($sql_eventos, strpos($sql_eventos, ' FROM evento ev')), $tipos_eventos_conteo, $params_eventos_conteo);
	$legajo_historial_totales['actividades']=$fila_total ? intval($fila_total['total']) : 0;
	if(!$modo_impresion_historial && $historial_tab==='actividades') { $historial_paginas_tmp=max(1,(int)ceil($legajo_historial_totales['actividades']/$historial_por_pagina)); $historial_pagina=min($historial_pagina,$historial_paginas_tmp); }
	if($historial_tab==='actividades')
	{
		$sql_eventos.=' ORDER BY ev.fecha DESC, ev.id_evento DESC';
		if(!$modo_impresion_historial) { $sql_eventos.=' LIMIT ? OFFSET ?'; $tipos_eventos.='ii'; $parametros_eventos[]=$historial_por_pagina; $parametros_eventos[]=($historial_pagina-1)*$historial_por_pagina; }
		$legajo_historial_actividades=(array)acceso_consulta($sql_eventos, $tipos_eventos, $parametros_eventos);
	}
}

if($legajo_historial_disponible && function_exists('acceso_tabla_existe') && acceso_tabla_existe('area') && acceso_tabla_existe('tarea') && acceso_tabla_existe('tarea_responsable'))
{
	$sql_areas="SELECT DISTINCT a.id_area, a.nombre FROM area a WHERE EXISTS(SELECT 1 FROM tarea_responsable tr INNER JOIN tarea t ON t.id_tarea=tr.id_tarea WHERE tr.id_empleado=? AND t.id_area=a.id_area)";
	$tipos_areas='i'; $params_areas=array($foto_id);
	if(acceso_tabla_existe('actividad')) { $sql_areas.=" OR EXISTS(SELECT 1 FROM actividad act INNER JOIN tarea t2 ON t2.id_tarea=act.id_tarea WHERE act.id_empleado=? AND t2.id_area=a.id_area)"; $tipos_areas.='i'; $params_areas[]=$foto_id; }
	if(acceso_tabla_existe('evento'))
	{
		$sql_areas.=" OR EXISTS(SELECT 1 FROM evento ev WHERE ev.id_area=a.id_area AND (ev.id_empleado=? OR (".$evento_tarea_vinculada_sql.")))";
		$tipos_areas.='i'.$tipos_evento_tarea; $params_areas=array_merge($params_areas, array($foto_id), $parametros_evento_tarea);
	}
	$sql_areas.=' ORDER BY a.nombre';
	$legajo_historial_areas=(array)acceso_consulta($sql_areas, $tipos_areas, $params_areas);
	if(acceso_tabla_existe('actividad'))
	{
		$legajo_historial_tipos_movimiento=(array)acceso_consulta('SELECT DISTINCT tipo FROM actividad WHERE id_empleado=? ORDER BY tipo', 'i', array($foto_id));
	}
}
$historial_total_actual=isset($legajo_historial_totales[$historial_tab]) ? intval($legajo_historial_totales[$historial_tab]) : 0;
$historial_paginas=max(1, (int)ceil($historial_total_actual/$historial_por_pagina));
if($historial_pagina>$historial_paginas) { $historial_pagina=$historial_paginas; }
$historial_url=function($tab, $pagina=1) use ($foto_id,$historial_tab,$historial_buscar,$historial_desde,$historial_hasta,$historial_area,$historial_estado,$historial_movimiento,$historial_tipo_actividad,$historial_estado_actividad) {
	$params=array('id'=>intval($foto_id),'historial_tab'=>(string)$tab,'historial_pagina'=>max(1,intval($pagina)));
	if($historial_buscar!=='') { $params['historial_buscar']=$historial_buscar; }
	if($historial_desde!=='') { $params['historial_desde']=$historial_desde; }
	if($historial_hasta!=='') { $params['historial_hasta']=$historial_hasta; }
	if($historial_area>0) { $params['historial_area']=$historial_area; }
	if($tab===$historial_tab && $historial_estado!=='') { $params['historial_estado']=$historial_estado; }
	if($tab===$historial_tab && $historial_movimiento!=='') { $params['historial_movimiento']=$historial_movimiento; }
	if($tab===$historial_tab && $historial_tipo_actividad!=='') { $params['historial_tipo_actividad']=$historial_tipo_actividad; }
	if($tab===$historial_tab && $historial_estado_actividad!=='') { $params['historial_estado_actividad']=$historial_estado_actividad; }
	return 'ficha-empleado.php?'.http_build_query($params).'#historialLaboral';
};

$menu_activo='empleados';
$nombre=trim((isset($empleado['nombres']) ? $empleado['nombres'] : '').' '.(isset($empleado['apellido']) ? $empleado['apellido'] : ''));
$esc=function($valor) {
	return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
};
$fecha_obj=function($valor) {
	$valor=trim((string)$valor);
	if($valor==='' || $valor==='0000-00-00') { return null; }
	$fecha=DateTime::createFromFormat('!Y-m-d', substr($valor, 0, 10));
	$errores=DateTime::getLastErrors();
	if(!$fecha || ($errores && ($errores['warning_count']>0 || $errores['error_count']>0)) || $fecha->format('Y-m-d')!==substr($valor, 0, 10))
	{
		return null;
	}
	return $fecha;
};
$fecha_txt=function($valor) use ($fecha_obj) {
	$fecha=$fecha_obj($valor);
	return $fecha ? $fecha->format('d/m/Y') : 'No registrado';
};
$fecha_hora_txt=function($valor) {
	$marca=strtotime((string)$valor);
	return $marca!==false ? date('d/m/Y H:i', $marca) : 'No registrada';
};
$fecha_baja='';
$tiene_fecha_baja=false;
if(function_exists('crm_columna_existe') && crm_columna_existe('empleado', 'fecha_baja'))
{
	$tiene_fecha_baja=true;
	$fila_baja=acceso_consulta_una('SELECT fecha_baja FROM empleado WHERE id_empleado=? LIMIT 1', 'i', array(intval($empleado['id'])));
	if($fila_baja && isset($fila_baja['fecha_baja'])) { $fecha_baja=$fila_baja['fecha_baja']; }
}

$domicilio=array();
if(isset($empleado['direccion']) && trim((string)$empleado['direccion'])!=='') { $domicilio[]=trim((string)$empleado['direccion']); }
$columnas_domicilio=array();
foreach(array('localidad_domicilio','provincia_domicilio') as $columna)
{
	if(function_exists('crm_columna_existe') && crm_columna_existe('empleado', $columna)) { $columnas_domicilio[]=$columna; }
}
if(count($columnas_domicilio)>0)
{
	$fila_domicilio=acceso_consulta_una('SELECT '.implode(', ', $columnas_domicilio).' FROM empleado WHERE id_empleado=? LIMIT 1', 'i', array(intval($empleado['id'])));
	if($fila_domicilio)
	{
		foreach($columnas_domicilio as $columna)
		{
			if(isset($fila_domicilio[$columna]) && trim((string)$fila_domicilio[$columna])!=='') { $domicilio[]=trim((string)$fila_domicilio[$columna]); }
		}
	}
}

$area_etiquetas=acceso_etiquetas_area();
$area_nombre=(isset($empleado['area']) && isset($area_etiquetas[$empleado['area']])) ? $area_etiquetas[$empleado['area']] : (isset($empleado['area']) && $empleado['area']!=='' ? $empleado['area'] : 'Sin área asignada');
$rol_nombre=isset($empleado['rol']) && trim((string)$empleado['rol'])!=='' ? acceso_mostrar_rol($empleado['rol']) : 'No registrado';
$estado_laboral=isset($empleado['estado_laboral']) ? trim((string)$empleado['estado_laboral']) : '';
$estados=array('activo'=>'Activo', 'inactivo'=>'Inactivo', 'licencia'=>'Licencia');
$estado_nombre=$estado_laboral!=='' ? (isset($estados[$estado_laboral]) ? $estados[$estado_laboral] : ucfirst(str_replace('_', ' ', $estado_laboral))) : (intval($empleado['activo'])===1 ? 'Activo' : 'Inactivo');

$ingreso=$fecha_obj(isset($empleado['fecha_ingreso']) ? $empleado['fecha_ingreso'] : '');
$baja_obj=$fecha_obj($fecha_baja);
$hoy=new DateTime('today');
$hasta=($baja_obj && $baja_obj<$hoy) ? $baja_obj : $hoy;
$antiguedad='No registrado';
if($ingreso)
{
	$intervalo=$ingreso->diff($hasta);
	if($intervalo->invert)
	{
		$antiguedad='Fecha de ingreso futura';
	}
	else
	{
		$partes=array();
		if($intervalo->y>0) { $partes[]=$intervalo->y.' '.($intervalo->y===1 ? 'año' : 'años'); }
		if($intervalo->m>0) { $partes[]=$intervalo->m.' '.($intervalo->m===1 ? 'mes' : 'meses'); }
		if(count($partes)===0) { $partes[]=$intervalo->d.' '.($intervalo->d===1 ? 'día' : 'días'); }
		$antiguedad=implode(' y ', $partes);
	}
}

$roles_adicionales=isset($empleado['roles_adicionales']) && is_array($empleado['roles_adicionales']) ? $empleado['roles_adicionales'] : array();
$iniciales='';
foreach(array(isset($empleado['nombres']) ? $empleado['nombres'] : '', isset($empleado['apellido']) ? $empleado['apellido'] : '') as $parte_nombre)
{
	$parte_nombre=trim((string)$parte_nombre);
	if($parte_nombre!=='')
	{
		$inicial=function_exists('mb_substr') ? mb_substr($parte_nombre, 0, 1, 'UTF-8') : substr($parte_nombre, 0, 1);
		$iniciales.=function_exists('mb_strtoupper') ? mb_strtoupper($inicial, 'UTF-8') : strtoupper($inicial);
	}
}
if($iniciales==='') { $iniciales='E'; }

$volver_params=array();
foreach(array('q','area','rol','pagina','limite') as $param)
{
	if(isset($_GET[$param]) && is_scalar($_GET[$param])) { $volver_params[$param]=(string)$_GET[$param]; }
}
$volver_url='empleados.php'.(count($volver_params)>0 ? '?'.http_build_query($volver_params) : '');
$editar_url='editar-empleado.php?usuario='.rawurlencode(isset($empleado['usuario']) ? (string)$empleado['usuario'] : '');
$fecha_emision=date('d/m/Y');
if($modo_impresion_historial)
{
	header('Content-Type: text/html; charset=UTF-8');
	$historial_impresion_titulo=$historial_tabs[$historial_tab];
	$historial_impresion_registros=$historial_tab==='tareas' ? $legajo_tareas : ($historial_tab==='movimientos' ? $legajo_historial_tareas : $legajo_historial_actividades);
	$historial_impresion_cantidad=count($historial_impresion_registros);
	$historial_impresion_filtros=array();
	if($historial_buscar!=='') { $historial_impresion_filtros['Búsqueda']=$historial_buscar; }
	if($historial_area>0)
	{
		foreach($legajo_historial_areas as $area_impresion)
		{
			if(intval($area_impresion['id_area'])===$historial_area) { $historial_impresion_filtros['Área']=$area_impresion['nombre']; break; }
		}
	}
	if($historial_desde!=='') { $historial_impresion_filtros['Desde']=$fecha_txt($historial_desde); }
	if($historial_hasta!=='') { $historial_impresion_filtros['Hasta']=$fecha_txt($historial_hasta); }
	if($historial_tab==='tareas' && $historial_estado!=='')
	{
		$estados_impresion=array('no_iniciada'=>'Sin iniciar','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Finalizada','cancelada'=>'Cancelada');
		$historial_impresion_filtros['Estado']=isset($estados_impresion[$historial_estado]) ? $estados_impresion[$historial_estado] : $historial_estado;
	}
	if($historial_tab==='movimientos' && $historial_movimiento!=='') { $historial_impresion_filtros['Tipo de movimiento']=ucfirst(str_replace('_',' ',$historial_movimiento)); }
	if($historial_tab==='actividades')
	{
		if($historial_tipo_actividad!=='') { $historial_impresion_filtros['Tipo de actividad']=ucfirst(str_replace('_',' ',$historial_tipo_actividad)); }
		if($historial_estado_actividad!=='') { $historial_impresion_filtros['Estado']=$historial_estado_actividad==='activa' ? 'Activa' : 'Inactiva'; }
	}
	$texto_impresion=function($valor) use ($esc) { return nl2br($esc(trim((string)$valor)!=='' ? $valor : '—')); };
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8" />
	<title><?php echo $esc($historial_impresion_titulo.' · '.$nombre); ?> | ADEMI</title>
	<style>
		@page { size:A4 portrait; margin:12mm 11mm; }
		* { box-sizing:border-box; }
		body { margin:0; color:#263548; font:9.5pt/1.4 Arial,Helvetica,sans-serif; }
		.brand { padding-bottom:7px; border-bottom:2px solid #2870bd; color:#2870bd; font-size:11pt; font-weight:700; letter-spacing:1.4px; }
		h1 { margin:12px 0 4px; color:#1f3147; font-size:18pt; }
		.employee { margin:0 0 12px; color:#52657a; font-size:11pt; }
		.meta { display:flex; flex-wrap:wrap; gap:6px 18px; margin:0 0 10px; padding:8px 10px; border:1px solid #dce5ef; background:#f5f8fc; }
		.meta span { display:inline-block; }
		.filters { margin:0 0 12px; color:#52657a; font-size:8.5pt; }
		.filters strong { color:#34465b; }
		table { width:100%; border-collapse:collapse; table-layout:fixed; }
		thead { display:table-header-group; }
		tfoot { display:table-footer-group; }
		th, td { padding:6px 6px; border:1px solid #dce3eb; text-align:left; vertical-align:top; overflow-wrap:anywhere; word-break:normal; }
		th { background:#eef3f8; color:#34465b; font-size:8pt; text-transform:uppercase; letter-spacing:.25px; }
		td { font-size:8.5pt; }
		tr { break-inside:avoid; page-break-inside:avoid; }
		.empty { padding:18px; border:1px dashed #cbd5e1; color:#64748b; text-align:center; }
		@media print { body { -webkit-print-color-adjust:exact; print-color-adjust:exact; } }
	</style>
</head>
<body class="rrhh-history-print">
<main data-rrhh-history-print>
	<header>
		<div class="brand">ADEMI · INFORME LABORAL</div>
		<h1><?php echo $esc($historial_impresion_titulo); ?></h1>
		<p class="employee"><strong>Colaborador:</strong> <?php echo $esc($nombre); ?> · <strong>Área:</strong> <?php echo $esc($area_nombre); ?></p>
		<div class="meta"><span><strong>Fecha de emisión:</strong> <?php echo $esc($fecha_emision); ?></span><span><strong>Resultados:</strong> <?php echo intval($historial_impresion_cantidad); ?></span></div>
		<?php if(count($historial_impresion_filtros)>0) { ?><p class="filters"><strong>Filtros aplicados:</strong> <?php $filtro_textos=array(); foreach($historial_impresion_filtros as $filtro_nombre=>$filtro_valor) { $filtro_textos[]=$filtro_nombre.': '.$filtro_valor; } echo $esc(implode(' · ', $filtro_textos)); ?></p><?php } ?>
	</header>
	<?php if($historial_impresion_cantidad===0) { ?><div class="empty">No hay resultados que coincidan con los filtros seleccionados.</div><?php } elseif($historial_tab==='tareas') { ?>
	<table><thead><tr><th style="width:29%">Tarea</th><th style="width:17%">Área</th><th style="width:14%">Estado</th><th style="width:9%">Avance</th><th style="width:14%">Inicio</th><th style="width:17%">Fecha límite</th></tr></thead><tbody>
	<?php foreach($historial_impresion_registros as $registro) { $estado=isset($registro['estado']) ? (string)$registro['estado'] : ''; $estado_etiquetas=array('no_iniciada'=>'Sin iniciar','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Finalizada','cancelada'=>'Cancelada'); ?>
	<tr><td><?php echo $texto_impresion((isset($registro['titulo']) ? $registro['titulo'] : '').(intval(isset($registro['id_tarea_padre']) ? $registro['id_tarea_padre'] : 0)>0 ? ' (subtarea)' : '')); ?></td><td><?php echo $texto_impresion(isset($registro['area_nombre']) ? $registro['area_nombre'] : ''); ?></td><td><?php echo $texto_impresion(isset($estado_etiquetas[$estado]) ? $estado_etiquetas[$estado] : $estado); ?></td><td><?php echo intval(isset($registro['avance']) ? $registro['avance'] : 0); ?>%</td><td><?php echo $esc($fecha_txt(isset($registro['fecha_inicio']) ? $registro['fecha_inicio'] : '')); ?></td><td><?php echo $esc($fecha_txt(isset($registro['fecha_cierre']) ? $registro['fecha_cierre'] : '')); ?></td></tr>
	<?php } ?></tbody></table>
	<?php } elseif($historial_tab==='movimientos') { ?>
	<table><thead><tr><th style="width:17%">Fecha</th><th style="width:22%">Tarea</th><th style="width:15%">Área</th><th style="width:15%">Movimiento</th><th style="width:31%">Detalle</th></tr></thead><tbody>
	<?php foreach($historial_impresion_registros as $registro) { ?>
	<tr><td><?php echo $esc($fecha_hora_txt(isset($registro['fecha_actividad']) ? $registro['fecha_actividad'] : '')); ?></td><td><?php echo $texto_impresion(isset($registro['titulo']) ? $registro['titulo'] : ''); ?></td><td><?php echo $texto_impresion(isset($registro['area_nombre']) ? $registro['area_nombre'] : ''); ?></td><td><?php echo $esc(isset($registro['tipo']) ? ucfirst(str_replace('_',' ',$registro['tipo'])) : '—'); ?><?php if(!empty($registro['empleado_responsable'])) { ?><br /><small>Responsable asignado</small><?php } ?></td><td><?php echo $texto_impresion(isset($registro['descripcion']) && trim((string)$registro['descripcion'])!=='' ? $registro['descripcion'] : 'Sin detalle'); ?></td></tr>
	<?php } ?></tbody></table>
	<?php } else { ?>
	<table><thead><tr><th style="width:13%">Fecha</th><th style="width:20%">Actividad</th><th style="width:15%">Área</th><th style="width:14%">Tipo y estado</th><th style="width:18%">Participación</th><th style="width:21%">Descripción</th></tr></thead><tbody>
	<?php foreach($historial_impresion_registros as $registro) { $participaciones=array(); if(!empty($registro['empleado_responsable_actividad'])) { $participaciones[]='Responsable de la actividad'; } if(!empty($registro['empleado_responsable_tarea'])) { $participaciones[]='Responsable de tarea vinculada'; } ?>
	<tr><td><?php echo $esc($fecha_txt(isset($registro['fecha']) ? $registro['fecha'] : '')); ?></td><td><?php echo $texto_impresion(isset($registro['titulo']) ? $registro['titulo'] : ''); ?></td><td><?php echo $texto_impresion(isset($registro['area_nombre']) ? $registro['area_nombre'] : ''); ?></td><td><?php echo $esc(isset($registro['tipo']) ? ucfirst(str_replace('_',' ',$registro['tipo'])) : '—'); ?><br /><?php echo !empty($registro['activo']) ? 'Activa' : 'Inactiva'; ?></td><td><?php echo $texto_impresion(implode(' · ', $participaciones)); ?></td><td><?php echo $texto_impresion(isset($registro['descripcion']) ? $registro['descripcion'] : 'Sin descripción'); ?></td></tr>
	<?php } ?></tbody></table>
	<?php } ?>
</main>
</body>
</html>
<?php
	exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>CRM | Ficha de colaborador</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.rrhh-ficha-page { padding-bottom: 28px; }
.rrhh-ficha-actions { display:flex; flex-wrap:wrap; max-width:1000px; box-sizing:border-box; justify-content:center; gap:8px; margin:0 auto 14px; }
.rrhh-ficha { max-width:1000px; margin:0 auto; background:#fff; border:1px solid #dce3eb; border-radius:10px; box-shadow:0 5px 18px rgba(24,39,58,.08); overflow:hidden; color:#263548; }
.rrhh-ficha-head { display:flex; flex-wrap:wrap; align-items:center; gap:22px; padding:26px 30px; background:linear-gradient(135deg,#f5f9ff 0%,#edf4fb 100%); border-bottom:1px solid #dce5ef; }
.rrhh-ficha-avatar-block { order:1; flex:0 0 124px; display:flex; flex-direction:column; align-items:center; gap:8px; min-width:0; }
.rrhh-ficha-main { order:2; flex:1 1 300px; min-width:0; }
.rrhh-ficha-avatar { width:104px; height:104px; flex:0 0 104px; display:flex; align-items:center; justify-content:center; overflow:hidden; border-radius:50%; background:#dceaff; border:4px solid #fff; box-shadow:0 2px 10px rgba(33,85,145,.16); color:#2464ad; font-size:30px; font-weight:700; letter-spacing:1px; }
.rrhh-ficha-avatar img { display:block; width:100%; height:100%; object-fit:cover; }
.rrhh-ficha-photo-actions { display:flex; justify-content:center; align-items:center; gap:4px; flex-wrap:wrap; }
.rrhh-ficha-photo-actions .btn { padding:4px 7px; font-size:11px; }
.rrhh-ficha-photo-actions .btn[hidden] { display:none !important; }
.rrhh-ficha-photo-actions form { margin:0; }
.rrhh-ficha-photo-form { order:4; flex:0 0 100%; width:100%; max-width:460px; min-width:0; box-sizing:border-box; margin:0; padding:12px; border:1px solid #dce5ef; border-radius:7px; background:rgba(255,255,255,.9); }
.rrhh-ficha-photo-form[hidden] { display:none !important; }
.rrhh-ficha-photo-form label { display:block; margin:0 0 5px; color:#60758b; font-size:11px; font-weight:600; }
.rrhh-ficha-photo-form input[type=file] { display:block; width:100%; max-width:100%; min-width:0; box-sizing:border-box; font-size:11px; }
.rrhh-ficha-photo-form-actions { display:flex; gap:5px; margin-top:7px; }
.rrhh-ficha-photo-message { width:100%; margin:0 0 5px; padding:6px 9px; font-size:11px; }
.rrhh-ficha-photo-pending { max-width:125px; color:#718096; font-size:10px; text-align:center; }
.rrhh-ficha-brand { color:#2870bd; font-size:12px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; margin-bottom:8px; }
.rrhh-ficha-head h1 { color:#1f3147; font-size:25px; line-height:1.25; margin:0 0 9px; font-weight:700; }
.rrhh-ficha-meta { display:flex; flex-wrap:wrap; gap:8px; }
.rrhh-ficha-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border:1px solid #d5e1ef; border-radius:20px; background:#fff; color:#52657a; font-size:12px; }
.rrhh-ficha-emision { order:3; margin-left:auto; align-self:flex-start; color:#718096; font-size:12px; white-space:nowrap; }
.rrhh-ficha-body { padding:22px 30px 28px; }
.rrhh-ficha-section + .rrhh-ficha-section { margin-top:20px; padding-top:18px; border-top:1px solid #e8edf3; }
.rrhh-ficha-section h2 { color:#34465b; font-size:15px; font-weight:700; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
.rrhh-ficha-section h2 i { color:#4384c5; width:18px; text-align:center; }
.rrhh-ficha-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px 22px; }
.rrhh-ficha-item { min-width:0; }
.rrhh-ficha-label { display:block; color:#7a8999; font-size:11px; font-weight:700; letter-spacing:.45px; text-transform:uppercase; margin-bottom:4px; }
.rrhh-ficha-value { color:#2d3d50; font-size:14px; line-height:1.45; overflow-wrap:anywhere; }
.rrhh-ficha-roles { display:flex; flex-wrap:wrap; gap:7px; }
.rrhh-ficha-role { display:inline-block; border-radius:16px; background:#edf4fc; color:#315e8e; padding:4px 9px; font-size:12px; }
.rrhh-ficha-footer { padding:11px 30px; border-top:1px solid #e8edf3; color:#8491a0; font-size:11px; text-align:right; }
.rrhh-legajo-table-wrap { overflow-x:auto; border:1px solid #e4eaf1; border-radius:8px; }
.rrhh-legajo-table { width:100%; margin:0; background:#fff; }
.rrhh-legajo-table th { background:#f5f8fc; color:#53677d; font-size:11px; text-transform:uppercase; letter-spacing:.35px; white-space:nowrap; }
.rrhh-legajo-table td { vertical-align:middle !important; color:#34465b; font-size:12px; }
.rrhh-legajo-doc-name { font-weight:600; overflow-wrap:anywhere; }
.rrhh-legajo-actions { display:flex; gap:5px; align-items:center; white-space:nowrap; }
.rrhh-legajo-actions form { margin:0; }
.rrhh-legajo-toolbar { display:flex; justify-content:flex-end; margin:0 0 12px; }
.rrhh-legajo-form { margin:0 0 14px; padding:14px; border:1px solid #dce5ef; border-radius:8px; background:#f8fbff; }
.rrhh-legajo-form[hidden] { display:none !important; }
.rrhh-legajo-form .form-group { margin-bottom:10px; }
.rrhh-legajo-empty { padding:16px; border:1px dashed #d8e1eb; border-radius:8px; background:#f8fafc; color:#6c7d90; text-align:center; }
.rrhh-legajo-alert { margin:0 0 12px; padding:9px 12px; font-size:12px; }
.rrhh-legajo-section { margin-top:22px; padding-top:18px; border-top:1px solid #e8edf3; }
.rrhh-legajo-section h2 { color:#34465b; font-size:15px; font-weight:700; margin:0 0 14px; display:flex; align-items:center; gap:8px; }
.rrhh-legajo-section h2 i { color:#4384c5; width:18px; text-align:center; }
.rrhh-legajo-toolbar { display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px; margin:0 0 12px; padding:12px; border:1px solid #e4eaf1; border-radius:8px; background:#f8fafc; }
.rrhh-legajo-toolbar .form-group { margin:0; min-width:150px; }
.rrhh-legajo-toolbar label { display:block; color:#60758b; font-size:11px; margin-bottom:4px; }
.rrhh-legajo-summary { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 12px; }
.rrhh-legajo-summary span { display:inline-block; padding:6px 10px; border:1px solid #dce5ef; border-radius:16px; background:#f8fbff; color:#34465b; font-size:12px; }
.rrhh-history-table-wrap { overflow-x:auto; border:1px solid #e4eaf1; border-radius:8px; margin-bottom:12px; }
.rrhh-history-table { width:100%; margin:0; background:#fff; }
.rrhh-history-table th { background:#f5f8fc; color:#53677d; font-size:11px; text-transform:uppercase; letter-spacing:.35px; }
.rrhh-history-table td { vertical-align:top !important; color:#34465b; font-size:12px; }
.rrhh-history-empty { padding:14px; border:1px dashed #d8e1eb; border-radius:8px; background:#f8fafc; color:#6c7d90; text-align:center; }
.rrhh-history-tabs { display:flex; flex-wrap:wrap; gap:7px; margin:0 0 12px; }
.rrhh-history-print-button { margin-left:auto; }
.rrhh-history-count { display:inline-block; min-width:20px; margin-left:4px; padding:1px 6px; border-radius:10px; background:rgba(100,116,139,.12); font-size:11px; text-align:center; }
.rrhh-history-filter-actions { display:flex; align-items:flex-end; gap:7px; }
.rrhh-history-search { flex:1 1 220px; }
.rrhh-history-pagination { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; margin:10px 0; color:#60758b; font-size:12px; }
.rrhh-history-pagination > div { display:flex; align-items:center; gap:9px; }
.rrhh-history-section h3 { color:#34465b; font-size:14px; font-weight:700; margin:12px 0; display:flex; align-items:center; gap:8px; }
.rrhh-history-section h3 i { color:#4384c5; width:18px; text-align:center; }
.rrhh-history-subline { display:block; margin-top:3px; color:#73859a; }
.rrhh-history-section details summary { cursor:pointer; color:#2f6fab; }
.rrhh-history-section details[open] { min-width:140px; max-width:360px; white-space:normal; }
.rrhh-history-section details[open] summary { margin-bottom:4px; }
@media (max-width:767px) {
	.rrhh-ficha-head { align-items:flex-start; padding:20px; gap:15px; flex-wrap:wrap; }
	.rrhh-ficha-avatar-block { flex-basis:90px; }
	.rrhh-ficha-main { flex-basis:calc(100% - 110px); }
	.rrhh-ficha-avatar { width:76px; height:76px; flex-basis:76px; font-size:23px; }
	.rrhh-ficha-head h1 { font-size:20px; }
	.rrhh-ficha-emision { width:calc(100% - 105px); margin-left:105px; margin-top:-8px; }
	.rrhh-ficha-body { padding:18px 20px 22px; }
	.rrhh-ficha-grid { grid-template-columns:1fr; gap:11px; }
	.rrhh-legajo-toolbar { justify-content:flex-start; }
	.rrhh-legajo-actions { flex-wrap:wrap; white-space:normal; }
}
@media (max-width:420px) { .rrhh-ficha-head { padding:15px; gap:10px; } .rrhh-ficha-avatar-block { flex-basis:78px; } .rrhh-ficha-avatar { width:68px; height:68px; flex-basis:68px; } .rrhh-ficha-photo-actions { flex-direction:column; width:100%; } .rrhh-ficha-photo-actions .btn { width:100%; } .rrhh-ficha-emision { width:calc(100% - 88px); margin-left:88px; } }
body.rrhh-ficha-modal-view { margin:0!important; padding:0!important; background:#fff!important; overflow-x:hidden!important; }
body.rrhh-ficha-modal-view .rrhh-ficha-modal-page { display:block!important; position:static!important; width:100%!important; max-width:none!important; min-width:0!important; min-height:0!important; height:auto!important; margin:0!important; padding:0!important; float:none!important; overflow:visible!important; }
body.rrhh-ficha-modal-view .rrhh-ficha-photo-actions, body.rrhh-ficha-modal-view .rrhh-ficha-photo-form, body.rrhh-ficha-modal-view .rrhh-legajo-section { display:none!important; }
body.rrhh-ficha-modal-view .rrhh-ficha { max-width:none!important; margin:0!important; border:0!important; border-radius:0!important; box-shadow:none!important; }
@page { size:A4 portrait; margin:8mm; }
@media print {
	*, *:before, *:after { box-sizing:border-box !important; }
	html, body { width:auto !important; min-width:0 !important; height:auto !important; margin:0 !important; padding:0 !important; background:#fff !important; color:#263548 !important; font:10pt Arial, sans-serif !important; }
	.header, #main-menu, .page-sidebar, .page-sidebar-wrapper, .crm-menu-seccion, .breadcrumb, .rrhh-no-print, .page-title, .footer, #footer { display:none !important; }
	.page-container, .page-content, .content, .rrhh-ficha-page { display:block !important; position:static !important; width:100% !important; min-width:0 !important; max-width:none !important; height:auto !important; min-height:0 !important; margin:0 !important; padding:0 !important; float:none !important; overflow:visible !important; }
	.rrhh-ficha { box-sizing:border-box !important; width:100% !important; max-width:none !important; margin:0 !important; border:1px solid #dce3eb !important; border-radius:5px !important; box-shadow:none !important; overflow:visible !important; }
	.rrhh-ficha-head { gap:5mm !important; padding:6mm 7mm !important; background:#f1f6fc !important; border-bottom:1px solid #dce5ef !important; break-inside:avoid; page-break-inside:avoid; print-color-adjust:exact; -webkit-print-color-adjust:exact; }
	.rrhh-ficha-avatar-block { flex:0 0 22mm !important; gap:0 !important; }
	.rrhh-ficha-avatar { width:22mm !important; height:22mm !important; flex-basis:22mm !important; border-width:1px !important; background:#dceaff !important; box-shadow:none !important; font-size:18pt !important; }
	.rrhh-ficha-avatar img { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
	.rrhh-ficha-main { flex:1 1 0 !important; }
	.rrhh-ficha-brand { margin-bottom:2mm !important; color:#2870bd !important; font-size:9pt !important; letter-spacing:1.2px !important; }
	.rrhh-ficha-head h1 { margin-bottom:2mm !important; color:#1f3147 !important; font-size:18pt !important; line-height:1.2 !important; overflow-wrap:anywhere !important; }
	.rrhh-ficha-meta { gap:1.5mm !important; }
	.rrhh-ficha-pill { padding:1mm 2mm !important; border-color:#d5e1ef !important; background:#fff !important; font-size:8.5pt !important; }
	.rrhh-ficha-emision { font-size:8.5pt !important; }
	.rrhh-ficha-body { padding:5mm 7mm !important; }
	.rrhh-ficha-section { break-inside:auto; page-break-inside:auto; }
	.rrhh-ficha-section + .rrhh-ficha-section { margin-top:4mm !important; padding-top:3mm !important; border-top:1px solid #e8edf3 !important; }
	.rrhh-ficha-section h2 { margin-bottom:2.5mm !important; color:#34465b !important; font-size:11pt !important; break-after:avoid; page-break-after:avoid; }
	.rrhh-ficha-grid { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)) !important; gap:2.5mm 8mm !important; }
	.rrhh-ficha-item { min-width:0 !important; break-inside:avoid; page-break-inside:avoid; }
	.rrhh-ficha-label { margin-bottom:1mm !important; color:#718096 !important; font-size:8pt !important; }
	.rrhh-ficha-value { color:#2d3d50 !important; font-size:9.5pt !important; line-height:1.3 !important; overflow-wrap:anywhere !important; }
	.rrhh-ficha-roles { gap:5px !important; }
	.rrhh-ficha-role { padding:1mm 2mm !important; font-size:8.5pt !important; }
	.rrhh-ficha-footer { break-inside:avoid; page-break-inside:avoid; margin-top:1mm !important; padding:1.5mm 7mm !important; border-top:1px solid #e8edf3 !important; color:#8491a0 !important; font-size:8pt !important; }
	.rrhh-legajo-section { display:none !important; }
}
</style>
</head>
<body class="<?php echo $modo_modal_informe ? 'rrhh-ficha-modal-view' : ''; ?>">
<?php if(!$modo_modal_informe) { ?>
<?php crm_header_staff(); ?>
<div class="page-container row">
	<?php crm_menu_staff(); ?>
	<div class="clearfix"></div>
</div>
</div>
	<div class="page-content"><div class="content rrhh-ficha-page">
	<?php } else { ?><main class="rrhh-ficha-page rrhh-ficha-modal-page"><?php } ?>
		<?php if(!$modo_modal_informe) { ?>
		<div class="rrhh-ficha-actions rrhh-no-print">
			<a class="btn btn-white" href="<?php echo $esc($volver_url); ?>"><i class="fa fa-arrow-left"></i> Volver a empleados</a>
			<a class="btn btn-white" href="<?php echo $esc($editar_url); ?>"><i class="fa fa-pencil"></i> Modificar colaborador</a>
			<button type="button" class="btn btn-primary" onclick="rrhhImprimirFicha();"><i class="fa fa-print"></i> Imprimir ficha</button>
		</div>
		<?php } ?>
		<article class="rrhh-ficha" data-rrhh-ficha>
			<header class="rrhh-ficha-head">
				<div class="rrhh-ficha-avatar-block">
					<div class="rrhh-ficha-avatar" aria-label="Fotografía de <?php echo $esc($nombre); ?>"><?php if($foto_url!=='') { ?><img src="<?php echo $esc($foto_url); ?>" alt="Fotografía de <?php echo $esc($nombre); ?>" /><?php } else { echo $esc($iniciales); } ?></div>
				</div>
				<div class="rrhh-ficha-main">
					<div class="rrhh-ficha-brand">ADEMI · Recursos Humanos</div>
					<h1><?php echo $esc($nombre); ?></h1>
					<div class="rrhh-ficha-meta">
						<span class="rrhh-ficha-pill"><i class="fa fa-sitemap"></i><?php echo $esc($area_nombre); ?></span>
						<span class="rrhh-ficha-pill"><i class="fa fa-user"></i><?php echo $esc($rol_nombre); ?></span>
						<span class="rrhh-ficha-pill"><i class="fa fa-circle"></i><?php echo $esc($estado_nombre); ?></span>
					</div>
				</div>
				<div class="rrhh-ficha-emision">Emitida el <?php echo $esc($fecha_emision); ?></div>
			</header>
			<div class="rrhh-ficha-body">
				<section class="rrhh-ficha-section">
					<h2><i class="fa fa-id-card-o"></i> Datos personales</h2>
					<div class="rrhh-ficha-grid">
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Nombre y apellido</span><div class="rrhh-ficha-value"><?php echo $esc($nombre); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">DNI</span><div class="rrhh-ficha-value"><?php echo $esc(isset($empleado['dni']) && $empleado['dni']!=='' ? $empleado['dni'] : 'No registrado'); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">CUIL</span><div class="rrhh-ficha-value"><?php echo $esc(isset($empleado['cuil']) && $empleado['cuil']!=='' ? $empleado['cuil'] : 'No registrado'); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Correo electrónico</span><div class="rrhh-ficha-value"><?php echo $esc(isset($empleado['correo']) && $empleado['correo']!=='' ? $empleado['correo'] : 'No registrado'); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Teléfono</span><div class="rrhh-ficha-value"><?php echo $esc(isset($empleado['telefono']) && $empleado['telefono']!=='' ? $empleado['telefono'] : 'No registrado'); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Domicilio</span><div class="rrhh-ficha-value"><?php echo $esc(count($domicilio)>0 ? implode(', ', $domicilio) : 'No registrado'); ?></div></div>
					</div>
				</section>
				<?php if(!$modo_modal_informe) { ?><section class="rrhh-ficha-section rrhh-legajo-section" aria-labelledby="rrhh-legajo-title">
					<h2 id="rrhh-legajo-title"><i class="fa fa-folder-open-o"></i> Legajo digital</h2>
					<?php if(!$legajo_disponible) { ?>
					<div class="alert alert-info rrhh-legajo-alert" role="status">El legajo digital quedará disponible cuando se aplique la migración SQL pendiente.</div>
					<?php } elseif(!$puede_gestionar_legajo) { ?>
					<div class="rrhh-legajo-empty">No tenés autorización para consultar este legajo.</div>
					<?php } else { ?>
					<?php if($legajo_error_consulta) { ?>
					<div class="alert alert-warning rrhh-legajo-alert" role="alert">No se pudo cargar la lista de documentos. Intentá nuevamente más tarde.</div>
					<?php } elseif(count($legajo_documentos)===0) { ?>
					<div class="rrhh-legajo-empty">Todavía no hay documentos cargados en este legajo.</div>
					<?php } else { ?>
					<div class="rrhh-legajo-table-wrap">
						<table class="table table-hover rrhh-legajo-table">
							<thead><tr><th>Fecha de carga</th><th>Categoría</th><th>Documento</th><th>Descripción</th><th class="rrhh-no-print">Acciones</th></tr></thead>
							<tbody>
							<?php foreach($legajo_documentos as $documento) {
								$categoria_doc=isset($legajo_categorias[$documento['categoria']]) ? $legajo_categorias[$documento['categoria']] : 'Otros documentos';
								$fecha_carga='No registrada';
								if(isset($documento['fecha_carga'])) { $fecha_parseada=strtotime($documento['fecha_carga']); if($fecha_parseada!==false) { $fecha_carga=date('d/m/Y H:i', $fecha_parseada); } }
								$url_documento='legajo-documento.php?id='.intval($documento['id_documento']);
							?>
							<tr>
								<td><?php echo $esc($fecha_carga); ?></td>
								<td><?php echo $esc($categoria_doc); ?></td>
								<td class="rrhh-legajo-doc-name"><?php echo $esc($documento['nombre_original']); ?></td>
								<td><?php echo $esc(isset($documento['descripcion']) && trim((string)$documento['descripcion'])!=='' ? $documento['descripcion'] : '—'); ?></td>
								<td class="rrhh-no-print"><div class="rrhh-legajo-actions">
									<a class="btn btn-white btn-xs" href="<?php echo $esc($url_documento.'&modo=ver'); ?>" target="_blank" rel="noopener"><i class="fa fa-eye"></i> Ver PDF</a>
									<a class="btn btn-white btn-xs" href="<?php echo $esc($url_documento.'&modo=descargar'); ?>"><i class="fa fa-download"></i> Descargar</a>
								</div></td>
							</tr>
							<?php } ?>
							</tbody>
						</table>
					</div>
					<?php } ?>
					<?php } ?>
				</section><?php } ?>
				<section class="rrhh-ficha-section">
					<h2><i class="fa fa-briefcase"></i> Datos laborales</h2>
					<div class="rrhh-ficha-grid">
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Área principal</span><div class="rrhh-ficha-value"><?php echo $esc($area_nombre); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Rol principal</span><div class="rrhh-ficha-value"><?php echo $esc($rol_nombre); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Puesto</span><div class="rrhh-ficha-value"><?php echo $esc(isset($empleado['puesto']) && trim((string)$empleado['puesto'])!=='' ? $empleado['puesto'] : 'No registrado'); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Vínculo laboral</span><div class="rrhh-ficha-value"><?php echo $esc(isset($empleado['tipo_vinculo']) && trim((string)$empleado['tipo_vinculo'])!=='' ? ucfirst(str_replace('_', ' ', $empleado['tipo_vinculo'])) : 'No registrado'); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Estado laboral</span><div class="rrhh-ficha-value"><?php echo $esc($estado_nombre); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Fecha de ingreso</span><div class="rrhh-ficha-value"><?php echo $esc($fecha_txt(isset($empleado['fecha_ingreso']) ? $empleado['fecha_ingreso'] : '')); ?></div></div>
						<div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Antigüedad</span><div class="rrhh-ficha-value"><?php echo $esc($antiguedad); ?></div></div>
						<?php if($tiene_fecha_baja) { ?><div class="rrhh-ficha-item"><span class="rrhh-ficha-label">Fecha de baja</span><div class="rrhh-ficha-value"><?php echo $esc($fecha_txt($fecha_baja)); ?></div></div><?php } ?>
						<div class="rrhh-ficha-item">
							<span class="rrhh-ficha-label">Roles adicionales</span>
							<?php if(count($roles_adicionales)===0) { ?><div class="rrhh-ficha-value">No registrado</div><?php } else { ?>
							<div class="rrhh-ficha-roles">
							<?php foreach($roles_adicionales as $extra) {
								$extra_rol=isset($extra['rol']) ? acceso_mostrar_rol($extra['rol']) : '';
								$extra_area=isset($extra['area']) && $extra['area']!=='' ? (isset($area_etiquetas[$extra['area']]) ? $area_etiquetas[$extra['area']] : $extra['area']) : '';
								$extra_texto=$extra_rol.($extra_area!=='' ? ' · '.$extra_area : '');
							?>
							<span class="rrhh-ficha-role"><?php echo $esc($extra_texto); ?></span>
							<?php } ?>
							</div>
							<?php } ?>
						</div>
					</div>
				</section>
				<?php if(!$modo_modal_informe && $legajo_historial_disponible) { ?>
				<section id="historialLaboral" class="rrhh-legajo-section rrhh-history-root" aria-labelledby="rrhh-history-title">
					<h2 id="rrhh-history-title"><i class="fa fa-history"></i> Historial laboral</h2>
					<div class="rrhh-legajo-summary" aria-label="Resumen de tareas asignadas">
						<span>Tareas asignadas: <?php echo intval($legajo_tareas_resumen['total']); ?></span>
						<span>Sin iniciar: <?php echo intval($legajo_tareas_resumen['no_iniciada']); ?></span>
						<span>En proceso: <?php echo intval($legajo_tareas_resumen['en_proceso']); ?></span>
						<span>Pausadas: <?php echo intval($legajo_tareas_resumen['pausada']); ?></span>
						<span>Finalizadas: <?php echo intval($legajo_tareas_resumen['terminada']); ?></span>
						<span>Canceladas: <?php echo intval($legajo_tareas_resumen['cancelada']); ?></span>
					</div>
					<nav class="rrhh-history-tabs rrhh-no-print" aria-label="Secciones del historial laboral">
						<?php foreach($historial_tabs as $tab_clave=>$tab_titulo) { ?>
						<a class="btn <?php echo $historial_tab===$tab_clave ? 'btn-primary' : 'btn-white'; ?>" href="<?php echo $esc($historial_url($tab_clave)); ?>" <?php echo $historial_tab===$tab_clave ? 'aria-current="page"' : ''; ?>><?php echo $esc($tab_titulo); ?> <span class="rrhh-history-count"><?php echo intval($legajo_historial_totales[$tab_clave]); ?></span></a>
						<?php } ?>
						<button type="button" class="btn btn-white rrhh-history-print-button" onclick="rrhhImprimirResultados();"><i class="fa fa-print"></i> Imprimir resultados</button>
					</nav>
					<form id="rrhh-history-filters" class="rrhh-legajo-toolbar rrhh-no-print" method="get" action="ficha-empleado.php">
						<input type="hidden" name="id" value="<?php echo intval($foto_id); ?>" />
						<input type="hidden" name="historial_tab" value="<?php echo $esc($historial_tab); ?>" />
						<div class="form-group rrhh-history-search"><label for="historial-buscar">Buscar tarea o actividad</label><input id="historial-buscar" class="form-control input-sm" type="search" name="historial_buscar" value="<?php echo $esc($historial_buscar); ?>" maxlength="120" /></div>
						<div class="form-group"><label for="historial-area">Área</label><select id="historial-area" class="form-control input-sm" name="historial_area"><option value="">Todas las áreas vinculadas</option><?php foreach($legajo_historial_areas as $area_historial) { ?><option value="<?php echo intval($area_historial['id_area']); ?>" <?php echo $historial_area===intval($area_historial['id_area']) ? 'selected' : ''; ?>><?php echo $esc($area_historial['nombre']); ?></option><?php } ?></select></div>
						<div class="form-group"><label for="historial-desde">Fecha desde</label><input id="historial-desde" class="form-control input-sm" type="date" name="historial_desde" value="<?php echo $esc($historial_desde); ?>" /></div>
						<div class="form-group"><label for="historial-hasta">Fecha hasta</label><input id="historial-hasta" class="form-control input-sm" type="date" name="historial_hasta" value="<?php echo $esc($historial_hasta); ?>" /></div>
						<?php if($historial_tab==='tareas') { ?>
						<div class="form-group"><label for="historial-estado">Estado de tarea</label><select id="historial-estado" class="form-control input-sm" name="historial_estado"><option value="">Todos</option><?php foreach(array('no_iniciada'=>'Sin iniciar','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Finalizada','cancelada'=>'Cancelada') as $estado_valor=>$estado_etiqueta) { ?><option value="<?php echo $esc($estado_valor); ?>" <?php echo $historial_estado===$estado_valor ? 'selected' : ''; ?>><?php echo $esc($estado_etiqueta); ?></option><?php } ?></select></div>
						<?php } elseif($historial_tab==='movimientos') { ?>
						<div class="form-group"><label for="historial-movimiento">Tipo de movimiento</label><select id="historial-movimiento" class="form-control input-sm" name="historial_movimiento"><option value="">Todos</option><?php foreach($legajo_historial_tipos_movimiento as $fila_tipo_movimiento) { $tipo_movimiento=isset($fila_tipo_movimiento['tipo']) ? (string)$fila_tipo_movimiento['tipo'] : ''; if($tipo_movimiento!=='') { ?><option value="<?php echo $esc($tipo_movimiento); ?>" <?php echo $historial_movimiento===$tipo_movimiento ? 'selected' : ''; ?>><?php echo $esc(ucfirst(str_replace('_',' ',$tipo_movimiento))); ?></option><?php } } ?></select></div>
						<?php } else { ?>
						<div class="form-group"><label for="historial-tipo-actividad">Tipo de actividad</label><select id="historial-tipo-actividad" class="form-control input-sm" name="historial_tipo_actividad"><option value="">Todos</option><?php foreach($legajo_historial_tipos_actividad as $fila_tipo_actividad) { $tipo_actividad=isset($fila_tipo_actividad['tipo']) ? (string)$fila_tipo_actividad['tipo'] : ''; if($tipo_actividad!=='') { ?><option value="<?php echo $esc($tipo_actividad); ?>" <?php echo $historial_tipo_actividad===$tipo_actividad ? 'selected' : ''; ?>><?php echo $esc(ucfirst(str_replace('_',' ',$tipo_actividad))); ?></option><?php } } ?></select></div>
						<div class="form-group"><label for="historial-estado-actividad">Estado</label><select id="historial-estado-actividad" class="form-control input-sm" name="historial_estado_actividad"><option value="">Todos</option><option value="activa" <?php echo $historial_estado_actividad==='activa' ? 'selected' : ''; ?>>Activa</option><option value="inactiva" <?php echo $historial_estado_actividad==='inactiva' ? 'selected' : ''; ?>>Inactiva</option></select></div>
						<?php } ?>
						<div class="rrhh-history-filter-actions"><button type="submit" class="btn btn-primary btn-sm">Filtrar</button><a class="btn btn-white btn-sm" href="<?php echo $esc('ficha-empleado.php?id='.intval($foto_id).'&historial_tab='.rawurlencode($historial_tab).'#historialLaboral'); ?>">Limpiar</a></div>
					</form>
					<?php if($historial_rango_invalido) { ?><div class="rrhh-history-empty rrhh-no-print" role="alert">El período indicado no es válido: la fecha desde debe ser anterior o igual a la fecha hasta.</div><?php } ?>
					<?php if($historial_tab==='tareas') { ?>
					<section class="rrhh-history-section" aria-labelledby="rrhh-task-summary-title">
						<h3 id="rrhh-task-summary-title"><i class="fa fa-tasks"></i> Tareas asignadas</h3>
						<?php if($historial_rango_invalido || count($legajo_tareas)===0) { ?><div class="rrhh-history-empty"><?php echo $historial_rango_invalido ? 'El período indicado no es válido.' : 'No hay tareas que coincidan con los filtros seleccionados.'; ?></div><?php } else { ?>
					<div class="rrhh-history-table-wrap"><table class="table table-condensed rrhh-history-table"><thead><tr><th>Tarea</th><th>Área</th><th>Estado</th><th>Avance</th><th>Inicio</th><th>Fecha límite</th></tr></thead><tbody>
					<?php foreach($legajo_tareas as $tarea_historial) {
						$estados_tarea=array('no_iniciada'=>'Sin iniciar','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Finalizada','cancelada'=>'Cancelada');
						$estado_tarea=isset($tarea_historial['estado']) && isset($estados_tarea[$tarea_historial['estado']]) ? $estados_tarea[$tarea_historial['estado']] : (isset($tarea_historial['estado']) ? $tarea_historial['estado'] : 'No registrado');
					?>
					<tr><td><?php echo $esc($tarea_historial['titulo']); ?><?php if(intval($tarea_historial['id_tarea_padre'])>0) { ?> <small>(subtarea)</small><?php } ?></td><td><?php echo $esc($tarea_historial['area_nombre']); ?></td><td><?php echo $esc($estado_tarea); ?></td><td><?php echo intval($tarea_historial['avance']); ?>%</td><td><?php echo $esc($fecha_txt($tarea_historial['fecha_inicio'])); ?></td><td><?php echo $esc($fecha_txt($tarea_historial['fecha_cierre'])); ?></td></tr>
					<?php } ?>
					</tbody></table></div><?php } ?>
					</section><?php } ?>
					<?php if($historial_tab==='movimientos') { ?>
					<section class="rrhh-history-section" aria-labelledby="rrhh-task-history-title">
						<h3 id="rrhh-task-history-title"><i class="fa fa-history"></i> Movimientos de tareas</h3>
						<p class="rrhh-history-note">El resumen cuenta tareas donde figura como responsable. El historial registra acciones hechas por el colaborador; algunas pueden corresponder a tareas asignadas a otra persona.</p>
						<?php if($historial_rango_invalido || count($legajo_historial_tareas)===0) { ?><div class="rrhh-history-empty"><?php echo $historial_rango_invalido ? 'El período indicado no es válido.' : 'No hay movimientos que coincidan con los filtros seleccionados.'; ?></div><?php } else { ?>
					<div class="rrhh-history-table-wrap"><table class="table table-condensed rrhh-history-table"><thead><tr><th>Fecha</th><th>Tarea</th><th>Área</th><th>Movimiento</th><th>Detalle</th></tr></thead><tbody>
					<?php foreach($legajo_historial_tareas as $movimiento) { ?>
					<tr><td><?php echo $esc($fecha_hora_txt($movimiento['fecha_actividad'])); ?></td><td><?php echo $esc($movimiento['titulo']); ?></td><td><?php echo $esc($movimiento['area_nombre']); ?></td><td><?php echo $esc(isset($movimiento['tipo']) ? ucfirst(str_replace('_', ' ', $movimiento['tipo'])) : '—'); ?><?php if(!empty($movimiento['empleado_responsable'])) { ?><small class="rrhh-history-subline">Responsable asignado</small><?php } ?></td><td><details><summary>Ver detalle</summary><?php echo $esc(isset($movimiento['descripcion']) && trim((string)$movimiento['descripcion'])!=='' ? $movimiento['descripcion'] : 'Sin detalle'); ?></details></td></tr>
					<?php } ?>
					</tbody></table></div><?php } ?>
					</section><?php } ?>
					<?php if($historial_tab==='actividades') { ?>
					<section class="rrhh-history-section" aria-labelledby="rrhh-activities-history-title">
						<h3 id="rrhh-activities-history-title"><i class="fa fa-calendar"></i> Actividades registradas</h3>
						<?php if($historial_rango_invalido || count($legajo_historial_actividades)===0) { ?><div class="rrhh-history-empty"><?php echo $historial_rango_invalido ? 'El período indicado no es válido.' : 'No hay actividades que coincidan con los filtros seleccionados.'; ?></div><?php } else { ?>
					<div class="rrhh-history-table-wrap"><table class="table table-condensed rrhh-history-table"><thead><tr><th>Fecha</th><th>Actividad</th><th>Área</th><th>Tipo</th><th>Participación</th><th>Estado</th><th>Descripción</th></tr></thead><tbody>
					<?php foreach($legajo_historial_actividades as $evento_historial) { ?>
					<?php
						$participaciones_evento=array();
						if(!empty($evento_historial['empleado_responsable_actividad'])) { $participaciones_evento[]='Responsable de la actividad'; }
						if(!empty($evento_historial['empleado_responsable_tarea'])) { $participaciones_evento[]='Responsable de tarea vinculada'; }
						$estado_evento=!empty($evento_historial['activo']) ? 'Activa' : 'Inactiva';
					?>
					<tr><td><?php echo $esc($fecha_txt($evento_historial['fecha'])); ?></td><td><?php echo $esc($evento_historial['titulo']); ?></td><td><?php echo $esc($evento_historial['area_nombre']); ?></td><td><?php echo $esc(isset($evento_historial['tipo']) ? ucfirst(str_replace('_', ' ', $evento_historial['tipo'])) : '—'); ?></td><td><?php echo $esc(implode(' · ', $participaciones_evento)); ?></td><td><?php echo $esc($estado_evento); ?></td><td><details><summary>Ver detalle</summary><?php echo $esc(isset($evento_historial['descripcion']) && trim((string)$evento_historial['descripcion'])!=='' ? $evento_historial['descripcion'] : 'Sin descripción'); ?></details></td></tr>
					<?php } ?>
					</tbody></table></div><?php } ?>
					</section><?php } ?>
					<div class="rrhh-history-pagination rrhh-no-print" role="navigation" aria-label="Paginación del historial">
						<span>Mostrando <?php echo $historial_total_actual===0 ? 0 : min($historial_total_actual,(($historial_pagina-1)*$historial_por_pagina)+1); ?>–<?php echo min($historial_total_actual,$historial_pagina*$historial_por_pagina); ?> de <?php echo $historial_total_actual; ?></span>
						<div><?php if($historial_pagina>1) { ?><a class="btn btn-white btn-sm" href="<?php echo $esc($historial_url($historial_tab,$historial_pagina-1)); ?>">Anterior</a><?php } ?><span>Página <?php echo intval($historial_pagina); ?> de <?php echo intval($historial_paginas); ?></span><?php if($historial_pagina<$historial_paginas) { ?><a class="btn btn-white btn-sm" href="<?php echo $esc($historial_url($historial_tab,$historial_pagina+1)); ?>">Siguiente</a><?php } ?></div>
					</div>
				</section>
				<?php } ?>
			</div>
			<footer class="rrhh-ficha-footer">Ficha laboral · ADEMI</footer>
		</article>
	<?php if(!$modo_modal_informe) { ?></div></div><?php } else { ?></main><?php } ?>
<script>
function rrhhImprimirFicha()
{
	var imagen=document.querySelector('[data-rrhh-ficha] .rrhh-ficha-avatar img');
	var iniciado=false;
	function imprimir(){ if(iniciado) { return; } iniciado=true; window.print(); }
	if(!imagen || imagen.complete) { imprimir(); return; }
	imagen.addEventListener('load',imprimir,{once:true});
	imagen.addEventListener('error',imprimir,{once:true});
	window.setTimeout(imprimir,2000);
}
function rrhhImprimirResultados()
{
	var form=document.getElementById('rrhh-history-filters');
	if(!form || !document.body.contains(form)) { return; }
	var existing=document.getElementById('rrhh-history-print-frame');
	if(existing) { return; }
	var params=new URLSearchParams(new FormData(form));
	params.set('imprimir_historial','1');
	params.delete('historial_pagina');
	var frame=document.createElement('iframe');
	frame.id='rrhh-history-print-frame';
	frame.title='Vista de impresión del historial laboral';
	frame.setAttribute('aria-hidden','true');
	frame.tabIndex=-1;
	frame.style.position='fixed';
	frame.style.width='1px';
	frame.style.height='1px';
	frame.style.right='0';
	frame.style.bottom='0';
	frame.style.border='0';
	frame.style.opacity='0';
	frame.style.pointerEvents='none';
	var completado=false;
	var limpieza=setTimeout(function(){ if(frame.parentNode) { frame.parentNode.removeChild(frame); } },180000);
	function limpiar()
	{
		if(completado) { return; }
		completado=true;
		clearTimeout(limpieza);
		if(frame.parentNode) { frame.parentNode.removeChild(frame); }
	}
	frame.onload=function()
	{
		var printWindow=frame.contentWindow;
		var printDocument=frame.contentDocument;
		if(!printWindow || !printDocument) { limpiar(); window.alert('No se pudo cargar el historial autorizado para imprimir.'); return; }
		try
		{
			if(printDocument.location && printDocument.location.href==='about:blank') { return; }
			if(!printDocument.querySelector('[data-rrhh-history-print]')) { limpiar(); window.alert('No se pudo cargar el historial autorizado para imprimir.'); return; }
		}
		catch(error) { limpiar(); window.alert('No se pudo cargar el historial autorizado para imprimir.'); return; }
		if(typeof printWindow.addEventListener==='function') { printWindow.addEventListener('afterprint',limpiar,{once:true}); }
		else { printWindow.onafterprint=limpiar; }
		window.setTimeout(function(){
			if(completado || !frame.parentNode) { return; }
			try { printWindow.focus(); printWindow.print(); }
			catch(error) { limpiar(); window.alert('No se pudo abrir el diálogo de impresión. Probá nuevamente desde la ficha.'); }
		},80);
	};
	frame.src='ficha-empleado.php?'+params.toString();
	document.body.appendChild(frame);
}
</script>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<?php if($auto_imprimir) { ?><script>window.addEventListener('load',function(){window.print();});</script><?php } ?>
</body>
</html>
