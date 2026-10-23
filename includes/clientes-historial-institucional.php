<?php
// Historial institucional del cliente. Las consultas se limitan por permiso,
// filtros y paginación antes de recuperar registros.
$hist_cliente_id=intval($id);
$historial_modo_ficha=!empty($modo_ficha_solo_lectura);
$hist_tab=isset($_GET['historial_tab']) && is_string($_GET['historial_tab']) ? trim($_GET['historial_tab']) : '';
if($hist_tab==='' && isset($_GET['seg']))
{
	$hist_tab=$_GET['seg']==='programas' ? 'programas' : ($_GET['seg']==='tareas' ? 'tareas' : 'atenciones');
}
if(!in_array($hist_tab, array('atenciones','programas','tareas','actividades'), true)) { $hist_tab='atenciones'; }
$hist_buscar=isset($_GET['historial_buscar']) && is_string($_GET['historial_buscar']) ? trim($_GET['historial_buscar']) : '';
$hist_area=isset($_GET['historial_area']) && is_string($_GET['historial_area']) ? trim($_GET['historial_area']) : '';
$hist_desde=isset($_GET['historial_desde']) && is_string($_GET['historial_desde']) ? trim($_GET['historial_desde']) : '';
$hist_hasta=isset($_GET['historial_hasta']) && is_string($_GET['historial_hasta']) ? trim($_GET['historial_hasta']) : '';
$hist_estado=isset($_GET['historial_estado']) && is_string($_GET['historial_estado']) ? trim($_GET['historial_estado']) : '';
$hist_tipo=isset($_GET['historial_tipo']) && is_string($_GET['historial_tipo']) ? trim($_GET['historial_tipo']) : '';
$hist_error='';
$hist_fecha_valida=function($fecha) {
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { return false; }
	$partes=explode('-', $fecha);
	return checkdate(intval($partes[1]), intval($partes[2]), intval($partes[0]));
};
foreach(array('hist_desde','hist_hasta') as $campo_fecha)
{
	if($$campo_fecha!=='' && !$hist_fecha_valida($$campo_fecha))
	{
		$$campo_fecha='';
		$hist_error='Una de las fechas ingresadas no es válida.';
	}
}
if($hist_desde!=='' && $hist_hasta!=='' && $hist_desde>$hist_hasta)
{
	$hist_error='La fecha desde no puede ser posterior a la fecha hasta.';
}

$hist_etiquetas=acceso_etiquetas_area();
$hist_areas_autorizadas=array();
foreach($hist_etiquetas as $clave_area=>$nombre_area_hist)
{
	if(function_exists('acceso_puede') && acceso_puede($clave_area))
	{
		$hist_areas_autorizadas[$clave_area]=$nombre_area_hist;
	}
}
if($hist_area!=='' && !isset($hist_areas_autorizadas[$hist_area])) { $hist_area=''; }

// Arma los contextos de área usados por los mismos controles de visibilidad de tareas.
$hist_roles_area=array();
$hist_area_sesion=function_exists('acceso_area') ? acceso_area() : $area_cli;
if($hist_area_sesion==='') { $hist_area_sesion=$area_cli; }
$hist_rol_sesion=isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '';
if($hist_area_sesion!=='') { $hist_roles_area[$hist_area_sesion]=$hist_rol_sesion; }
foreach(function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array() as $rol_extra_hist)
{
	if(isset($rol_extra_hist['area']) && trim((string)$rol_extra_hist['area'])!=='')
	{
		$hist_roles_area[$rol_extra_hist['area']]=isset($rol_extra_hist['rol']) ? $rol_extra_hist['rol'] : '';
	}
}
$hist_areas_coord_tareas=array();
$hist_areas_asignadas_tareas=array();
foreach($hist_roles_area as $clave_hist_rol=>$rol_hist)
{
	if($rol_hist==='coordinador' || $rol_hist==='coordinacion') { $hist_areas_coord_tareas[]=$clave_hist_rol; }
	else { $hist_areas_asignadas_tareas[]=$clave_hist_rol; }
}
if(function_exists('acceso_es_coord_rrhh') && acceso_es_coord_rrhh()) { $hist_areas_coord_tareas[]='recepcion'; }
$hist_areas_coord_tareas=array_values(array_unique($hist_areas_coord_tareas));
$hist_areas_asignadas_tareas=array_values(array_unique($hist_areas_asignadas_tareas));
$hist_emp_id=function_exists('crm_id_empleado_sesion') ? intval(crm_id_empleado_sesion()) : 0;
$hist_es_global=function_exists('acceso_es_general') && acceso_es_general();

$hist_sources=array();

// Atenciones y movimientos de seguimiento se presentan como eventos distintos,
// manteniendo una sola fila por registro real de atención o de trazabilidad.
if(acceso_tabla_existe('atencion'))
{
	$hist_at_tiene_area_origen=function_exists('crm_columna_existe') && crm_columna_existe('atencion','id_area_origen');
	$hist_at_area_origen_expr=$hist_at_tiene_area_origen ? 'COALESCE(at.id_area_origen,at_origen.id_area)' : 'at_origen.id_area';
	$hist_at_scope='1=1';
	$hist_at_scope_types='';
	$hist_at_scope_vals=array();
	if(!$hist_es_global && $area_cli!=='recepcion')
	{
		$hist_at_scope='(ad.clave=? OR ao.clave=?)';
		$hist_at_scope_types='ss';
		$hist_at_scope_vals=array($area_cli,$area_cli);
	}
	$hist_at_base="SELECT CONCAT('at-',at.id_atencion) AS id_registro, at.id_atencion AS orden,
		at.id_atencion AS id_atencion, at.fecha AS fecha,
		CASE WHEN at.id_atencion_origen IS NULL THEN 'atencion' ELSE 'derivacion' END AS tipo_key,
		CASE WHEN at.id_atencion_origen IS NULL THEN 'Atención' ELSE 'Derivación' END AS tipo,
		CASE at.motivo WHEN 'consulta' THEN 'Consulta' WHEN 'documentacion' THEN 'Documentación' WHEN 'seguimiento' THEN 'Seguimiento' WHEN 'tramite' THEN 'Trámite' WHEN 'otro' THEN 'Otro' ELSE 'Atención registrada' END AS titulo,
		ad.clave AS area_key, ad.nombre AS area, IFNULL(ao.nombre,'') AS area_origen, IFNULL(ao.clave,'') AS area_origen_key,
		at.estado AS estado_key, IFNULL(at.detalle,'') AS detalle,
		TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))) AS responsable,
		IFNULL(at.fecha_turno,'') AS fecha_turno, '' AS fecha_hasta, '' AS fecha_inicio, 0 AS avance, '' AS estado_anterior
		FROM atencion at INNER JOIN area ad ON ad.id_area=at.id_area
		LEFT JOIN atencion at_origen ON at_origen.id_atencion=at.id_atencion_origen
		LEFT JOIN area ao ON ao.id_area=$hist_at_area_origen_expr
		LEFT JOIN empleado e ON e.id_empleado=at.id_empleado
		WHERE at.id_cliente=? AND $hist_at_scope";
	$hist_at_types='i'.$hist_at_scope_types;
	$hist_at_vals=array_merge(array($hist_cliente_id),$hist_at_scope_vals);
	if(acceso_tabla_existe('atencion_seguimiento'))
	{
		$hist_areas_log=array_keys($hist_areas_autorizadas);
		$hist_log_scope='1=0';
		$hist_log_types='';
		$hist_log_vals=array();
		if(count($hist_areas_log)>0)
		{
			$hist_log_scope='al.clave IN ('.implode(',',array_fill(0,count($hist_areas_log),'?')).')';
			$hist_log_types=str_repeat('s',count($hist_areas_log));
			$hist_log_vals=$hist_areas_log;
		}
		$hist_log_base="SELECT CONCAT('seg-',s.id_seguimiento) AS id_registro, s.id_seguimiento AS orden,
			s.id_atencion AS id_atencion, s.fecha AS fecha, 'seguimiento' AS tipo_key, 'Seguimiento' AS tipo,
			s.accion AS titulo, al.clave AS area_key, al.nombre AS area, '' AS area_origen, '' AS area_origen_key,
			IFNULL(s.estado_nuevo,'') AS estado_key, IFNULL(s.observacion,'') AS detalle,
			TRIM(CONCAT(IFNULL(ee.nombres,''),' ',IFNULL(ee.apellido,''))) AS responsable,
			'' AS fecha_turno, '' AS fecha_hasta, '' AS fecha_inicio, 0 AS avance,
			CONCAT(IFNULL(s.estado_anterior,''),'|',IFNULL(s.estado_nuevo,'')) AS estado_anterior
			FROM atencion_seguimiento s INNER JOIN atencion at ON at.id_atencion=s.id_atencion
			INNER JOIN area ad ON ad.id_area=at.id_area
			LEFT JOIN atencion at_origen ON at_origen.id_atencion=at.id_atencion_origen
			LEFT JOIN area ao ON ao.id_area=$hist_at_area_origen_expr
			INNER JOIN area al ON al.id_area=s.id_area LEFT JOIN empleado ee ON ee.id_empleado=s.id_empleado
			WHERE at.id_cliente=? AND $hist_at_scope AND $hist_log_scope";
		$hist_at_base.=' UNION ALL '.$hist_log_base;
		$hist_at_types.='i'.$hist_at_scope_types.$hist_log_types;
		$hist_at_vals=array_merge($hist_at_vals,array($hist_cliente_id),$hist_at_scope_vals,$hist_log_vals);
	}
	$hist_sources['atenciones']=array('sql'=>$hist_at_base,'types'=>$hist_at_types,'values'=>$hist_at_vals,'area'=>true,'area_source'=>true,'status'=>true,'type'=>true);
}

if(acceso_tabla_existe('cliente_programa') && acceso_tabla_existe('programa'))
{
	$hist_sources['programas']=array(
		'sql'=>"SELECT CONCAT('prog-',cp.id) AS id_registro, cp.id AS orden, 0 AS id_atencion,
			cp.fecha_desde AS fecha, 'programa' AS tipo_key, 'Programa' AS tipo,
			p.nombre AS titulo, '' AS area_key, 'Área no registrada en esta relación' AS area, '' AS area_origen, '' AS area_origen_key,
			IF(cp.fecha_hasta IS NULL,'vigente','finalizado') AS estado_key,
			IFNULL(p.descripcion,'') AS detalle, '' AS responsable, '' AS fecha_turno, IFNULL(cp.fecha_hasta,'') AS fecha_hasta, cp.fecha_desde AS fecha_inicio, 0 AS avance, '' AS estado_anterior
			FROM cliente_programa cp INNER JOIN programa p ON p.id_programa=cp.id_programa WHERE cp.id_cliente=?",
		'types'=>'i','values'=>array($hist_cliente_id),'area'=>false,'status'=>true,'type'=>false
	);
}

if(acceso_tabla_existe('tarea'))
{
	$hist_task_access=array();
	$hist_task_types='i';
	$hist_task_vals=array($hist_cliente_id);
	if($hist_es_global)
	{
		$hist_task_access[]='1=1';
	}
	else
	{
		foreach($hist_areas_coord_tareas as $area_coord_hist)
		{
			$hist_task_access[]='ta.clave=?';
			$hist_task_types.='s';
			$hist_task_vals[]=$area_coord_hist;
		}
		if($hist_emp_id>0 && acceso_tabla_existe('tarea_responsable'))
		{
			foreach($hist_areas_asignadas_tareas as $area_asignada_hist)
			{
				$hist_task_access[]='(ta.clave=? AND EXISTS (SELECT 1 FROM tarea_responsable trp WHERE trp.id_tarea=t.id_tarea AND trp.id_empleado=?))';
				$hist_task_types.='si';
				$hist_task_vals[]=$area_asignada_hist;
				$hist_task_vals[]=$hist_emp_id;
			}
		}
	}
	$hist_task_scope=count($hist_task_access)>0 ? '('.implode(' OR ',$hist_task_access).')' : '1=0';
	$hist_task_resp="(SELECT tr.id_tarea, GROUP_CONCAT(DISTINCT CONCAT(IFNULL(er.nombres,''),' ',IFNULL(er.apellido,'')) ORDER BY tr.es_principal DESC, er.apellido, er.nombres SEPARATOR ', ') AS nombres
		FROM tarea_responsable tr INNER JOIN empleado er ON er.id_empleado=tr.id_empleado GROUP BY tr.id_tarea)";
	if(!acceso_tabla_existe('tarea_responsable')) { $hist_task_resp="(SELECT 0 AS id_tarea, '' AS nombres)"; }
	$hist_sources['tareas']=array(
		'sql'=>"SELECT CONCAT('tarea-',t.id_tarea) AS id_registro, t.id_tarea AS orden, 0 AS id_atencion,
			t.fecha_cierre AS fecha, CAST(t.id_tipo AS CHAR) AS tipo_key, IFNULL(tt.nombre,'Tarea') AS tipo,
			t.titulo AS titulo, ta.clave AS area_key, ta.nombre AS area, '' AS area_origen, '' AS area_origen_key,
			t.estado AS estado_key, IFNULL(t.descripcion,'') AS detalle, IFNULL(resp.nombres,'') AS responsable,
			'' AS fecha_turno, t.fecha_cierre AS fecha_hasta, t.fecha_inicio AS fecha_inicio, IFNULL(t.avance,0) AS avance, '' AS estado_anterior
			FROM tarea t INNER JOIN area ta ON ta.id_area=t.id_area
			LEFT JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
			LEFT JOIN $hist_task_resp resp ON resp.id_tarea=t.id_tarea
			WHERE t.id_cliente=? AND LOWER(t.estado) NOT IN ('cancelada','eliminada') AND $hist_task_scope",
		'types'=>$hist_task_types,'values'=>$hist_task_vals,'area'=>true,'status'=>true,'type'=>false
	);
}

if(acceso_tabla_existe('evento'))
{
	$hist_event_direct=array();
	$hist_event_types='';
	$hist_event_vals=array();
	if($hist_es_global) { $hist_event_direct[]='1=1'; }
	else
	{
		$hist_area_evento_directa=array_keys($hist_roles_area);
		if(count($hist_area_evento_directa)>0)
		{
			$hist_event_direct[]='ea.clave IN ('.implode(',',array_fill(0,count($hist_area_evento_directa),'?')).')';
			$hist_event_types.=str_repeat('s',count($hist_area_evento_directa));
			$hist_event_vals=array_merge($hist_event_vals,$hist_area_evento_directa);
		}
		if($hist_emp_id>0)
		{
			$hist_event_direct[]='ev.id_empleado=?';
			$hist_event_types.='i';
			$hist_event_vals[]=$hist_emp_id;
		}
		$hist_event_task_access=array();
		$hist_event_task_types='';
		$hist_event_task_vals=array();
		if(acceso_tabla_existe('evento_tarea'))
		{
			if($hist_emp_id>0 && acceso_tabla_existe('tarea_responsable'))
			{
				$hist_event_task_access[]='EXISTS (SELECT 1 FROM evento_tarea et INNER JOIN tarea t ON t.id_tarea=et.id_tarea INNER JOIN tarea_responsable tr ON tr.id_tarea=t.id_tarea WHERE et.id_evento=ev.id_evento AND tr.id_empleado=? AND LOWER(t.estado)<>' . "'cancelada')";
				$hist_event_task_types.='i';
				$hist_event_task_vals[]=$hist_emp_id;
			}
			foreach($hist_areas_coord_tareas as $area_coord_evento)
			{
				$hist_event_task_access[]='EXISTS (SELECT 1 FROM evento_tarea et INNER JOIN tarea t ON t.id_tarea=et.id_tarea INNER JOIN area atarea ON atarea.id_area=t.id_area WHERE et.id_evento=ev.id_evento AND atarea.clave=? AND LOWER(t.estado)<>' . "'cancelada')";
				$hist_event_task_types.='s';
				$hist_event_task_vals[]=$area_coord_evento;
			}
		}
		if(count($hist_event_task_access)>0)
		{
			$hist_event_direct[]='('.implode(' OR ',$hist_event_task_access).')';
			$hist_event_types.=$hist_event_task_types;
			$hist_event_vals=array_merge($hist_event_vals,$hist_event_task_vals);
		}
	}
	$hist_event_scope=count($hist_event_direct)>0 ? '('.implode(' OR ',$hist_event_direct).')' : '1=0';
	$hist_event_enroll_join='';
	$hist_event_client='ev.id_cliente=?';
	if(acceso_tabla_existe('evento_inscripcion'))
	{
		$hist_event_enroll_join='LEFT JOIN evento_inscripcion ei ON ei.id_evento=ev.id_evento AND ei.id_cliente=?';
		$hist_event_client='(ev.id_cliente=? OR ei.id IS NOT NULL)';
	}
	else { $hist_event_enroll_join=''; }
	$hist_event_base="SELECT CONCAT('evento-',ev.id_evento) AS id_registro, ev.id_evento AS orden, 0 AS id_atencion,
		ev.fecha AS fecha, ev.tipo AS tipo_key, ev.tipo AS tipo, ev.titulo AS titulo,
		ea.clave AS area_key, ea.nombre AS area, '' AS area_origen, '' AS area_origen_key,
		".(acceso_tabla_existe('evento_inscripcion') ? "IF(ei.estado IS NOT NULL,ei.estado,IF(ev.activo=1,'activa','cancelada'))" : "IF(ev.activo=1,'activa','cancelada')")." AS estado_key,
		CONCAT_WS(' · ',NULLIF(ev.descripcion,''),NULLIF(ev.organismo,'')) AS detalle,
		TRIM(CONCAT(IFNULL(ee.nombres,''),' ',IFNULL(ee.apellido,''))) AS responsable,
		'' AS fecha_turno, '' AS fecha_hasta, '' AS fecha_inicio, 0 AS avance, '' AS estado_anterior
		FROM evento ev INNER JOIN area ea ON ea.id_area=ev.id_area
		LEFT JOIN empleado ee ON ee.id_empleado=ev.id_empleado $hist_event_enroll_join
		WHERE $hist_event_client AND $hist_event_scope";
	$hist_event_base_types=(acceso_tabla_existe('evento_inscripcion') ? 'i' : 'i').$hist_event_types;
	$hist_event_base_vals=array_merge(array($hist_cliente_id),$hist_event_vals);
	if(acceso_tabla_existe('evento_inscripcion'))
	{
		$hist_event_base_types='i'.$hist_event_base_types;
		$hist_event_base_vals=array_merge(array($hist_cliente_id),$hist_event_base_vals);
	}
	$hist_sources['actividades']=array('sql'=>$hist_event_base,'types'=>$hist_event_base_types,'values'=>$hist_event_base_vals,'area'=>true,'status'=>true,'type'=>true);
}

$hist_areas_autorizadas=array();
foreach($hist_sources as $source_area_hist)
{
	if(empty($source_area_hist['area'])) { continue; }
		$areas_registradas=acceso_consulta('SELECT DISTINCT h.area_key, h.area, h.area_origen_key, h.area_origen FROM ('.$source_area_hist['sql'].') h WHERE h.area_key<>\'\' OR h.area_origen_key<>\'\' ORDER BY h.area',$source_area_hist['types'],$source_area_hist['values']);
	foreach($areas_registradas as $area_registrada_hist)
	{
		$clave_registrada=trim((string)$area_registrada_hist['area_key']);
		if($clave_registrada!=='') { $hist_areas_autorizadas[$clave_registrada]=trim((string)$area_registrada_hist['area']); }
		$clave_origen_registrada=trim((string)$area_registrada_hist['area_origen_key']);
		if($clave_origen_registrada!=='') { $hist_areas_autorizadas[$clave_origen_registrada]=trim((string)$area_registrada_hist['area_origen']); }
	}
}
asort($hist_areas_autorizadas);
if($hist_area!=='' && !isset($hist_areas_autorizadas[$hist_area])) { $hist_area=''; }

$hist_estados_opciones=array(
	'atenciones'=>array('pendiente'=>'Pendiente','abierta'=>'Abierta','en_atencion'=>'En atención','cerrada'=>'Cerrada','derivada'=>'Derivada','cancelada'=>'Cancelada'),
	'programas'=>array('vigente'=>'Vigente','finalizado'=>'Finalizado'),
	'tareas'=>array('no_iniciada'=>'No iniciada','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Terminada'),
	'actividades'=>array('inscrito'=>'Inscripto','asistio'=>'Asistió','ausente'=>'Ausente','activa'=>'Activa','cancelada'=>'Cancelada')
);
$hist_tipos_opciones=array(
	'atenciones'=>array('atencion'=>'Atención','derivacion'=>'Derivación','seguimiento'=>'Seguimiento'),
	'programas'=>array(), 'tareas'=>array(),
	'actividades'=>array('reunion'=>'Reunión','capacitacion'=>'Capacitación','ronda'=>'Ronda de negocios','otro'=>'Otro')
);
if(isset($hist_sources['actividades']))
{
	$hist_tipos_evento=acceso_consulta("SELECT DISTINCT h.tipo_key, h.tipo FROM (".$hist_sources['actividades']['sql'].") h ORDER BY h.tipo",$hist_sources['actividades']['types'],$hist_sources['actividades']['values']);
	foreach($hist_tipos_evento as $tipo_evento_hist)
	{
		$key_tipo=trim((string)$tipo_evento_hist['tipo_key']);
		if($key_tipo!=='' && !isset($hist_tipos_opciones['actividades'][$key_tipo])) { $hist_tipos_opciones['actividades'][$key_tipo]=ucfirst(str_replace('_',' ',$key_tipo)); }
	}
}
if(!isset($hist_sources[$hist_tab]))
{
	$hist_sources[$hist_tab]=array('sql'=>'SELECT 1 AS id_registro, 0 AS orden, 0 AS id_atencion, NULL AS fecha, \'\' AS tipo_key, \'\' AS tipo, \'\' AS titulo, \'\' AS area_key, \'\' AS area, \'\' AS area_origen, \'\' AS estado_key, \'\' AS detalle, \'\' AS responsable, \'\' AS fecha_turno, \'\' AS fecha_hasta, \'\' AS estado_anterior WHERE 1=0','types'=>'','values'=>array(),'area'=>false,'status'=>false,'type'=>false);
}
if(!isset($hist_estados_opciones[$hist_tab])) { $hist_estados_opciones[$hist_tab]=array(); }
if(!isset($hist_tipos_opciones[$hist_tab])) { $hist_tipos_opciones[$hist_tab]=array(); }
if($hist_estado!=='' && !isset($hist_estados_opciones[$hist_tab][$hist_estado])) { $hist_estado=''; }
if($hist_tipo!=='' && !isset($hist_tipos_opciones[$hist_tab][$hist_tipo])) { $hist_tipo=''; }

$hist_armar_filtros=function($source,$especificos) use($hist_buscar,$hist_area,$hist_desde,$hist_hasta,$hist_estado,$hist_tipo) {
	$where=array(); $types=''; $values=array();
	if($hist_buscar!=='')
	{
		$where[]="CONCAT_WS(' ',h.titulo,h.tipo,h.area,h.area_origen,h.detalle,h.responsable) LIKE ?";
		$types.='s'; $values[]='%'.$hist_buscar.'%';
	}
	if($source['area'] && $hist_area!=='')
	{
		$where[]=!empty($source['area_source']) ? '(h.area_key=? OR h.area_origen_key=?)' : 'h.area_key=?';
		$types.=!empty($source['area_source']) ? 'ss' : 's';
		$values[]=$hist_area;
		if(!empty($source['area_source'])) { $values[]=$hist_area; }
	}
	if($hist_desde!=='') { $where[]='h.fecha>=?'; $types.='s'; $values[]=$hist_desde.' 00:00:00'; }
	if($hist_hasta!=='') { $where[]='h.fecha<?'; $types.='s'; $values[]=date('Y-m-d',strtotime($hist_hasta.' +1 day')).' 00:00:00'; }
	if($especificos && $source['status'] && $hist_estado!=='') { $where[]='h.estado_key=?'; $types.='s'; $values[]=$hist_estado; }
	if($especificos && $source['type'] && $hist_tipo!=='') { $where[]='h.tipo_key=?'; $types.='s'; $values[]=$hist_tipo; }
	return array(count($where)>0 ? ' WHERE '.implode(' AND ',$where) : '',$types,$values);
};

$hist_totales=array('atenciones'=>0,'programas'=>0,'tareas'=>0,'actividades'=>0);
$hist_consulta_error=false;
foreach($hist_totales as $tab_historial=>$total_vacio)
{
	if(!isset($hist_sources[$tab_historial])) { continue; }
	$source_hist=$hist_sources[$tab_historial];
	$partes_filtro=$hist_armar_filtros($source_hist,false);
	$resultado_total=acceso_consulta_una('SELECT COUNT(*) AS total FROM ('.$source_hist['sql'].') h'.$partes_filtro[0],$source_hist['types'].$partes_filtro[1],array_merge($source_hist['values'],$partes_filtro[2]));
	if($resultado_total) { $hist_totales[$tab_historial]=intval($resultado_total['total']); }
	else if(acceso_ultimo_error()!=='') { $hist_consulta_error=true; }
}
$hist_source_actual=$hist_sources[$hist_tab];
$hist_filtros_actuales=$hist_armar_filtros($hist_source_actual,true);
$hist_total_actual=isset($hist_totales[$hist_tab]) ? $hist_totales[$hist_tab] : 0;
$hist_total_filtrado=acceso_consulta_una('SELECT COUNT(*) AS total FROM ('.$hist_source_actual['sql'].') h'.$hist_filtros_actuales[0],$hist_source_actual['types'].$hist_filtros_actuales[1],array_merge($hist_source_actual['values'],$hist_filtros_actuales[2]));
if($hist_total_filtrado) { $hist_total_actual=intval($hist_total_filtrado['total']); }
else if(acceso_ultimo_error()!=='') { $hist_consulta_error=true; }
$hist_por_pagina=10;
$hist_paginas=max(1,(int)ceil($hist_total_actual/$hist_por_pagina));
$hist_pagina=isset($_GET['historial_pagina']) ? max(1,intval($_GET['historial_pagina'])) : 1;
if($hist_pagina>$hist_paginas) { $hist_pagina=$hist_paginas; }
$hist_offset=($hist_pagina-1)*$hist_por_pagina;
$hist_registros=acceso_consulta('SELECT * FROM ('.$hist_source_actual['sql'].') h'.$hist_filtros_actuales[0].' ORDER BY h.fecha DESC, h.orden DESC LIMIT ?,?',$hist_source_actual['types'].$hist_filtros_actuales[1].'ii',array_merge($hist_source_actual['values'],$hist_filtros_actuales[2],array($hist_offset,$hist_por_pagina)));
if(!$hist_registros && acceso_ultimo_error()!=='') { $hist_consulta_error=true; }

$hist_tab_url=function($tab_url,$pagina_url=1,$incluir_especificos=true) use($hist_cliente_id,$hist_buscar,$hist_area,$hist_desde,$hist_hasta,$hist_estado,$hist_tipo,$historial_modo_ficha) {
	$params=array('id'=>$hist_cliente_id,'historial_tab'=>$tab_url,'historial_pagina'=>$pagina_url);
	if($historial_modo_ficha) { $params['vista']='ficha'; }
	if($hist_buscar!=='') { $params['historial_buscar']=$hist_buscar; }
	if($hist_area!=='') { $params['historial_area']=$hist_area; }
	if($hist_desde!=='') { $params['historial_desde']=$hist_desde; }
	if($hist_hasta!=='') { $params['historial_hasta']=$hist_hasta; }
	if($incluir_especificos && $hist_estado!=='') { $params['historial_estado']=$hist_estado; }
	if($incluir_especificos && $hist_tipo!=='') { $params['historial_tipo']=$hist_tipo; }
	return 'editar-cliente.php?'.http_build_query($params).'#clienteHistorialPantalla';
};
$hist_fecha_texto=function($fecha) {
	$fecha=trim((string)$fecha);
	if($fecha==='' || $fecha==='0000-00-00' || $fecha==='0000-00-00 00:00:00') { return 'Sin fecha'; }
	$marca=strtotime($fecha);
	return $marca ? date(strpos($fecha,':')!==false ? 'd/m/Y H:i' : 'd/m/Y',$marca) : $fecha;
};
$hist_estado_texto=function($estado) use($hist_estados_opciones,$hist_tab) {
	return isset($hist_estados_opciones[$hist_tab]) && isset($hist_estados_opciones[$hist_tab][$estado]) ? $hist_estados_opciones[$hist_tab][$estado] : ($estado!=='' ? ucfirst(str_replace('_',' ',$estado)) : 'Sin estado');
};
$hist_acciones_labels=array('alta_turno'=>'Turno agendado','alta_atencion'=>'Atención espontánea','alta_sin_turno'=>'Visita sin turno','inicio_atencion'=>'Inicio de atención','asignacion_responsable'=>'Asignación de responsable','reprogramacion'=>'Reprogramación','derivacion'=>'Derivación','derivacion_sin_turno'=>'Derivación de visita sin turno','recepcion'=>'Recepción','cierre'=>'Cierre','cancelacion'=>'Cancelación','seguimiento'=>'Seguimiento');
?>
<style>
#clienteHistorialPantalla .cliente-historial-nav { margin:0 0 14px; }
#clienteHistorialPantalla .cliente-historial-filtros { display:flex; flex-wrap:wrap; align-items:flex-end; gap:10px; margin:0 0 14px; }
#clienteHistorialPantalla .cliente-historial-filtros .form-group { min-width:150px; margin:0; }
#clienteHistorialPantalla .cliente-historial-filtros .form-group.buscar { min-width:220px; flex:1 1 220px; }
#clienteHistorialPantalla .cliente-historial-tabla { margin-bottom:10px; }
#clienteHistorialPantalla .cliente-historial-tabla th { white-space:nowrap; }
#clienteHistorialPantalla .cliente-historial-detalle { max-width:480px; white-space:normal; overflow-wrap:anywhere; }
#clienteHistorialPantalla .cliente-historial-traza { color:#64748b; font-size:12px; }
#clienteHistorialPantalla .cliente-historial-paginacion { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
@media (max-width:767px) { #clienteHistorialPantalla .cliente-historial-filtros .form-group { flex:1 1 100%; min-width:0; } }
</style>
<div class="grid simple" id="clienteHistorialPantalla">
	<div class="grid-title no-border">
		<h4>Historial institucional</h4>
		<p class="muted">Consultá las atenciones, programas, tareas y actividades vinculadas a este cliente.</p>
		<ul class="nav nav-tabs cliente-historial-nav">
			<li class="<?php echo $hist_tab==='atenciones' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($hist_tab_url('atenciones',1,false),ENT_QUOTES,'UTF-8'); ?>">Atenciones (<?php echo intval($hist_totales['atenciones']); ?>)</a></li>
			<li class="<?php echo $hist_tab==='programas' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($hist_tab_url('programas',1,false),ENT_QUOTES,'UTF-8'); ?>">Programas (<?php echo intval($hist_totales['programas']); ?>)</a></li>
			<li class="<?php echo $hist_tab==='tareas' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($hist_tab_url('tareas',1,false),ENT_QUOTES,'UTF-8'); ?>">Tareas (<?php echo intval($hist_totales['tareas']); ?>)</a></li>
			<li class="<?php echo $hist_tab==='actividades' ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($hist_tab_url('actividades',1,false),ENT_QUOTES,'UTF-8'); ?>">Actividades (<?php echo intval($hist_totales['actividades']); ?>)</a></li>
		</ul>
	</div>
	<div class="grid-body no-border">
		<form method="get" action="editar-cliente.php#clienteHistorialPantalla" class="cliente-historial-filtros">
			<input type="hidden" name="id" value="<?php echo $hist_cliente_id; ?>">
			<?php if($historial_modo_ficha) { ?><input type="hidden" name="vista" value="ficha"><?php } ?>
			<input type="hidden" name="historial_tab" value="<?php echo htmlspecialchars($hist_tab,ENT_QUOTES,'UTF-8'); ?>">
			<div class="form-group buscar"><label for="historial_buscar">Buscar</label><input type="search" id="historial_buscar" name="historial_buscar" class="form-control" value="<?php echo htmlspecialchars($hist_buscar,ENT_QUOTES,'UTF-8'); ?>" placeholder="Nombre, motivo o detalle"></div>
			<div class="form-group"><label for="historial_area">Área</label>
			<?php if($hist_tab==='programas') { ?><select id="historial_area" class="form-control" disabled><option>Filtro no disponible</option></select><?php if($hist_area!=='') { ?><input type="hidden" name="historial_area" value="<?php echo htmlspecialchars($hist_area,ENT_QUOTES,'UTF-8'); ?>"><?php } ?><?php } else { ?>
			<select id="historial_area" name="historial_area" class="form-control"><option value="">Todas las áreas disponibles</option><?php foreach($hist_areas_autorizadas as $clave_area=>$nombre_area_hist) { ?><option value="<?php echo htmlspecialchars($clave_area,ENT_QUOTES,'UTF-8'); ?>" <?php echo $hist_area===$clave_area ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombre_area_hist,ENT_QUOTES,'UTF-8'); ?></option><?php } ?></select><?php } ?></div>
			<div class="form-group"><label for="historial_desde">Fecha desde</label><input type="date" id="historial_desde" name="historial_desde" class="form-control" value="<?php echo htmlspecialchars($hist_desde,ENT_QUOTES,'UTF-8'); ?>"></div>
			<div class="form-group"><label for="historial_hasta">Fecha hasta</label><input type="date" id="historial_hasta" name="historial_hasta" class="form-control" value="<?php echo htmlspecialchars($hist_hasta,ENT_QUOTES,'UTF-8'); ?>"></div>
			<?php if(count($hist_estados_opciones[$hist_tab])>0) { ?><div class="form-group"><label for="historial_estado">Estado</label><select id="historial_estado" name="historial_estado" class="form-control"><option value="">Todos</option><?php foreach($hist_estados_opciones[$hist_tab] as $clave_estado=>$nombre_estado) { ?><option value="<?php echo htmlspecialchars($clave_estado,ENT_QUOTES,'UTF-8'); ?>" <?php echo $hist_estado===$clave_estado ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombre_estado,ENT_QUOTES,'UTF-8'); ?></option><?php } ?></select></div><?php } ?>
			<?php if(count($hist_tipos_opciones[$hist_tab])>0) { ?><div class="form-group"><label for="historial_tipo"><?php echo $hist_tab==='atenciones' ? 'Tipo de registro' : 'Tipo de actividad'; ?></label><select id="historial_tipo" name="historial_tipo" class="form-control"><option value="">Todos</option><?php foreach($hist_tipos_opciones[$hist_tab] as $clave_tipo=>$nombre_tipo) { ?><option value="<?php echo htmlspecialchars($clave_tipo,ENT_QUOTES,'UTF-8'); ?>" <?php echo $hist_tipo===$clave_tipo ? 'selected' : ''; ?>><?php echo htmlspecialchars($nombre_tipo,ENT_QUOTES,'UTF-8'); ?></option><?php } ?></select></div><?php } ?>
			<?php $hist_limpieza_params=array('id'=>$hist_cliente_id,'historial_tab'=>$hist_tab); if($historial_modo_ficha) { $hist_limpieza_params['vista']='ficha'; } ?>
			<div class="form-group"><button type="submit" class="btn btn-primary">Filtrar</button> <a class="btn btn-white" href="<?php echo htmlspecialchars('editar-cliente.php?'.http_build_query($hist_limpieza_params).'#clienteHistorialPantalla',ENT_QUOTES,'UTF-8'); ?>">Limpiar</a></div>
		</form>
		<?php if($hist_error!=='') { ?><div class="alert alert-warning"><?php echo htmlspecialchars($hist_error,ENT_QUOTES,'UTF-8'); ?></div><?php } ?>
		<?php if($hist_tab==='programas' && $hist_area!=='') { ?><p class="cliente-historial-traza">Los programas no guardan una relación con un área; el filtro Área no se aplica a esta pestaña.</p><?php } ?>
		<?php if($hist_consulta_error) { ?><div class="alert alert-warning">No se pudo cargar una parte del historial. Probá nuevamente o contactá al administrador.</div><?php } else if(count($hist_registros)===0) { ?><p class="muted">No hay registros para esta pestaña y los filtros seleccionados.</p><?php } else { ?>
		<div class="table-responsive">
		<table class="table table-striped table-hover cliente-historial-tabla">
		<thead><tr>
		<?php if($hist_tab==='atenciones') { ?><th>Fecha</th><th>Registro</th><th>Área y trazabilidad</th><th>Detalle</th><th>Estado</th><th>Responsable</th><th>Acción</th>
		<?php } else if($hist_tab==='programas') { ?><th>Programa</th><th>Desde</th><th>Hasta</th><th>Estado</th><th>Descripción</th>
		<?php } else if($hist_tab==='tareas') { ?><th>Tarea</th><th>Área</th><th>Responsable(s)</th><th>Inicio</th><th>Fecha límite</th><th>Estado y avance</th>
		<?php } else { ?><th>Actividad</th><th>Tipo</th><th>Área</th><th>Fecha</th><th>Participación / estado</th><th>Responsable</th><?php } ?>
		</tr></thead><tbody>
		<?php foreach($hist_registros as $registro_hist) { ?>
		<tr>
		<?php if($hist_tab==='atenciones') {
			$titulo_hist=isset($hist_acciones_labels[$registro_hist['titulo']]) ? $hist_acciones_labels[$registro_hist['titulo']] : ucfirst(str_replace('_',' ',(string)$registro_hist['titulo']));
			$es_seguimiento=$registro_hist['tipo_key']==='seguimiento';
			$estado_transicion=explode('|',(string)$registro_hist['estado_anterior'],2);
			// Abrir desde el área actual conserva el contexto usado para autorizar la ficha.
			$url_detalle=url_crm($area_cli.'/atencion.php?id='.intval($registro_hist['id_atencion']));
		?>
			<td><?php echo htmlspecialchars($hist_fecha_texto($registro_hist['fecha']),ENT_QUOTES,'UTF-8'); ?><?php if(!$es_seguimiento && !empty($registro_hist['fecha_turno'])) { ?><div class="cliente-historial-traza">Turno: <?php echo htmlspecialchars($hist_fecha_texto($registro_hist['fecha_turno']),ENT_QUOTES,'UTF-8'); ?></div><?php } ?></td>
			<td><?php echo htmlspecialchars($es_seguimiento ? $titulo_hist : $registro_hist['titulo'],ENT_QUOTES,'UTF-8'); ?><?php if(!$es_seguimiento) { ?><div class="cliente-historial-traza"><?php echo htmlspecialchars($registro_hist['tipo'],ENT_QUOTES,'UTF-8'); ?></div><?php } ?></td>
			<td><?php echo htmlspecialchars($registro_hist['area'],ENT_QUOTES,'UTF-8'); ?><?php if(trim($registro_hist['area_origen'])!=='') { ?><div class="cliente-historial-traza">Derivada desde <?php echo htmlspecialchars($registro_hist['area_origen'],ENT_QUOTES,'UTF-8'); ?></div><?php } ?></td>
			<td class="cliente-historial-detalle"><?php echo nl2br(htmlspecialchars($registro_hist['detalle'],ENT_QUOTES,'UTF-8')); ?><?php if($es_seguimiento && count($estado_transicion)===2 && ($estado_transicion[0]!=='' || $estado_transicion[1]!=='')) { ?><div class="cliente-historial-traza">Estado: <?php echo htmlspecialchars($hist_estado_texto($estado_transicion[0]),ENT_QUOTES,'UTF-8'); ?> → <?php echo htmlspecialchars($hist_estado_texto($estado_transicion[1]),ENT_QUOTES,'UTF-8'); ?></div><?php } ?></td>
			<td><?php echo htmlspecialchars($hist_estado_texto($registro_hist['estado_key']),ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars(trim($registro_hist['responsable'])!=='' ? $registro_hist['responsable'] : 'Sin asignar',ENT_QUOTES,'UTF-8'); ?></td>
			<td><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($url_detalle,ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-folder-open-o"></i> Ver detalle</a></td>
		<?php } else if($hist_tab==='programas') { ?>
			<td><strong><?php echo htmlspecialchars($registro_hist['titulo'],ENT_QUOTES,'UTF-8'); ?></strong></td><td><?php echo htmlspecialchars($hist_fecha_texto($registro_hist['fecha_inicio']),ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($registro_hist['fecha_hasta']!=='' ? $hist_fecha_texto($registro_hist['fecha_hasta']) : 'En curso',ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($hist_estado_texto($registro_hist['estado_key']),ENT_QUOTES,'UTF-8'); ?></td><td class="cliente-historial-detalle"><?php echo nl2br(htmlspecialchars($registro_hist['detalle'],ENT_QUOTES,'UTF-8')); ?></td>
		<?php } else if($hist_tab==='tareas') {
			$url_tarea=url_crm(trim((string)$registro_hist['area_key']).'/ver-tarea.php?id='.intval($registro_hist['orden']));
		?>
			<td><a href="<?php echo htmlspecialchars($url_tarea,ENT_QUOTES,'UTF-8'); ?>"><?php echo htmlspecialchars($registro_hist['titulo'],ENT_QUOTES,'UTF-8'); ?></a></td><td><?php echo htmlspecialchars($registro_hist['area'],ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars(trim($registro_hist['responsable'])!=='' ? $registro_hist['responsable'] : 'Sin asignar',ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($hist_fecha_texto($registro_hist['fecha_inicio']),ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($hist_fecha_texto($registro_hist['fecha_hasta']),ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($hist_estado_texto($registro_hist['estado_key']),ENT_QUOTES,'UTF-8'); ?> · <?php echo intval($registro_hist['avance']); ?>%</td>
		<?php } else {
			$tipos_eventos_label=array('reunion'=>'Reunión','capacitacion'=>'Capacitación','ronda'=>'Ronda de negocios','otro'=>'Otro');
		?>
			<td><strong><?php echo htmlspecialchars($registro_hist['titulo'],ENT_QUOTES,'UTF-8'); ?></strong><div class="cliente-historial-traza"><?php echo htmlspecialchars($registro_hist['detalle'],ENT_QUOTES,'UTF-8'); ?></div></td><td><?php echo htmlspecialchars(isset($tipos_eventos_label[$registro_hist['tipo_key']]) ? $tipos_eventos_label[$registro_hist['tipo_key']] : ucfirst(str_replace('_',' ',$registro_hist['tipo_key'])),ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($registro_hist['area'],ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($hist_fecha_texto($registro_hist['fecha']),ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars($hist_estado_texto($registro_hist['estado_key']),ENT_QUOTES,'UTF-8'); ?></td><td><?php echo htmlspecialchars(trim($registro_hist['responsable'])!=='' ? $registro_hist['responsable'] : 'No registrado',ENT_QUOTES,'UTF-8'); ?></td>
		<?php } ?>
		</tr>
		<?php } ?>
		</tbody></table></div>
		<?php } ?>
		<div class="cliente-historial-paginacion">
			<span><?php echo intval($hist_total_actual); ?> registro(s) · Página <?php echo intval($hist_pagina); ?> de <?php echo intval($hist_paginas); ?></span>
			<div><?php if($hist_pagina>1) { ?><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($hist_tab_url($hist_tab,$hist_pagina-1,true),ENT_QUOTES,'UTF-8'); ?>">Anterior</a><?php } ?> <?php if($hist_pagina<$hist_paginas) { ?><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($hist_tab_url($hist_tab,$hist_pagina+1,true),ENT_QUOTES,'UTF-8'); ?>">Siguiente</a><?php } ?></div>
		</div>
		<?php if(!$historial_modo_ficha && !$clientes_solo_lectura && $hist_tab==='programas' && function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa() && function_exists('crm_programas_lista')) { ?>
		<form method="post" action="editar-cliente.php?id=<?php echo $hist_cliente_id; ?>&amp;historial_tab=programas" style="margin-top:16px;">
			<input type="hidden" name="id_cliente" value="<?php echo $hist_cliente_id; ?>">
			<div class="form-group"><label for="historial_programa_nuevo">Anotar en programa</label><select id="historial_programa_nuevo" name="id_programa" class="form-control" required><option value="">Seleccionar programa…</option><?php foreach(crm_programas_lista() as $programa_disponible) { ?><option value="<?php echo intval($programa_disponible['id_programa']); ?>"><?php echo htmlspecialchars($programa_disponible['nombre'],ENT_QUOTES,'UTF-8'); ?></option><?php } ?></select></div>
			<button type="submit" name="anotar_programa" value="1" class="btn btn-white">Anotar y generar tareas</button>
		</form>
		<?php } ?>
	</div>
</div>
