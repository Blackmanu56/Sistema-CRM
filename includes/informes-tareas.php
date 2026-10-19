<?php
// Informe de tareas: lectura mediante la autorización operativa existente.
require_once BASE_PATH.'/includes/actividades.php';

$it_esc=function($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$it_date=function($value) {
	$value=trim((string)$value);
	if($value==='' || $value==='0000-00-00') { return 'No registrada'; }
	$time=strtotime($value);
	return $time ? date('d/m/Y',$time) : 'No registrada';
};
$it_state_labels=array('no_iniciada'=>'No iniciada','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Terminada');
$it_priority_labels=array('urgente'=>'Urgente','alta'=>'Alta','media'=>'Media','baja'=>'Baja');
$it_types=array('independiente'=>'Independiente','vinculada'=>'Vinculada a actividad');
$it_global=!empty($crm_informe_tareas_global);
$it_area_keys=isset($crm_informe_tareas_areas) && is_array($crm_informe_tareas_areas) ? array_keys($crm_informe_tareas_areas) : array();
$it_area_keys=array_values(array_unique(array_filter(array_map('strval',$it_area_keys))));
$it_area_rows=$it_global
	? acceso_consulta("SELECT id_area, clave, nombre FROM area ORDER BY nombre")
	: (count($it_area_keys)>0 ? acceso_consulta("SELECT id_area, clave, nombre FROM area WHERE clave IN (".implode(',',array_fill(0,count($it_area_keys),'?')).") ORDER BY nombre",str_repeat('s',count($it_area_keys)),$it_area_keys) : array());
$it_area_scope=array();
$it_area_names=array();
foreach($it_area_rows as $it_area_row)
{
	$it_id=intval($it_area_row['id_area']);
	$it_area_scope[$it_id]=$it_area_row;
	$it_area_names[$it_id]=(string)$it_area_row['nombre'];
}

$it_candidates=array();
if($it_global)
{
	$it_candidates=acceso_consulta("SELECT t.id_tarea AS id, t.id_tarea, t.titulo, t.descripcion, t.id_area, t.id_tipo,
		t.prioridad, t.estado, t.fecha_inicio, t.fecha_cierre, t.avance, t.id_tarea_padre,
		t.fecha_cierre_real, t.creado_en, a.clave AS area_clave, a.nombre AS area_nombre,
		tp.nombre AS tipo_nombre, padre.titulo AS tarea_padre_titulo
		FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
		INNER JOIN tipo_tarea tp ON tp.id_tipo=t.id_tipo
		LEFT JOIN tarea padre ON padre.id_tarea=t.id_tarea_padre
		WHERE t.estado<>'cancelada' ORDER BY t.fecha_cierre ASC, t.id_tarea ASC");
}
elseif(count($it_area_scope)>0)
{
	$it_ids_sql=implode(',',array_map('intval',array_keys($it_area_scope)));
	$it_candidates=acceso_consulta("SELECT t.id_tarea AS id, t.id_tarea, t.titulo, t.descripcion, t.id_area, t.id_tipo,
		t.prioridad, t.estado, t.fecha_inicio, t.fecha_cierre, t.avance, t.id_tarea_padre,
		t.fecha_cierre_real, t.creado_en, a.clave AS area_clave, a.nombre AS area_nombre,
		tp.nombre AS tipo_nombre, padre.titulo AS tarea_padre_titulo
		FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
		INNER JOIN tipo_tarea tp ON tp.id_tipo=t.id_tipo
		LEFT JOIN tarea padre ON padre.id_tarea=t.id_tarea_padre
		WHERE t.estado<>'cancelada' AND t.id_area IN ($it_ids_sql)
		ORDER BY t.fecha_cierre ASC, t.id_tarea ASC");
}

// La función compartida de lectura recibe el área real y los responsables
// con el mismo formato que el módulo operativo; nunca se usa una autorización
// distinta para el informe.
$it_candidate_ids=array();
foreach($it_candidates as $it_candidate) { $it_candidate_ids[]=intval($it_candidate['id_tarea']); }
$it_candidate_ids=array_values(array_unique(array_filter($it_candidate_ids)));
$it_responsibles=array();
if(count($it_candidate_ids)>0)
{
	$it_assignment_rows=acceso_consulta("SELECT tr.id_tarea, tr.id_empleado,
		TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))) AS nombre,
		tr.es_principal
		FROM tarea_responsable tr INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
		WHERE tr.id_tarea IN (".implode(',',array_map('intval',$it_candidate_ids)).")
		ORDER BY tr.id_tarea, tr.es_principal DESC, e.apellido, e.nombres");
	foreach($it_assignment_rows as $it_assignment)
	{
		$it_id=intval($it_assignment['id_tarea']);
		if(!isset($it_responsibles[$it_id])) { $it_responsibles[$it_id]=array('names'=>array(),'ids'=>array(),'by_id'=>array()); }
		$it_name=trim((string)$it_assignment['nombre']);
		if($it_name!=='' && !in_array($it_name,$it_responsibles[$it_id]['names'],true)) { $it_responsibles[$it_id]['names'][]=$it_name; }
		$it_person_id=intval($it_assignment['id_empleado']);
		$it_responsibles[$it_id]['ids'][]=$it_person_id;
		$it_responsibles[$it_id]['by_id'][$it_person_id]=$it_name;
	}
}
$it_visible=array();
foreach($it_candidates as $it_candidate)
{
	$it_id=intval($it_candidate['id_tarea']);
	$it_candidate['responsables']=isset($it_responsibles[$it_id]) ? $it_responsibles[$it_id]['names'] : array();
	$it_candidate['responsable']=isset($it_candidate['responsables'][0]) ? $it_candidate['responsables'][0] : '';
	$it_candidate['responsable_ids']=isset($it_responsibles[$it_id]) ? array_values(array_unique($it_responsibles[$it_id]['ids'])) : array();
	$it_candidate['responsables_por_id']=isset($it_responsibles[$it_id]) ? $it_responsibles[$it_id]['by_id'] : array();
	if(tarea_puede_ver($it_candidate)) { $it_visible[$it_id]=$it_candidate; }
}
foreach($it_visible as $it_id=>&$it_task)
{
	$it_parent_id=intval($it_task['id_tarea_padre']);
	if($it_parent_id>0 && !isset($it_visible[$it_parent_id])) { $it_task['tarea_padre_titulo']=''; }
}
unset($it_task);

// Activity links are read only through the two persisted relationships:
// evento_tarea and evento.id_tarea_origen. A child's parent link is included
// because subtasks inherit the parent task's explicit activity association.
$it_activity_map=array();
if(count($it_candidate_ids)>0 && acceso_tabla_existe('evento_tarea'))
{
	$it_event_rows=acceso_consulta("SELECT DISTINCT rel.id_tarea, ev.id_evento, ev.titulo, ev.fecha,
		ev.id_area, ev.id_empleado, ar.clave AS area_clave
		FROM (
			SELECT et.id_tarea, et.id_evento FROM evento_tarea et
			UNION
			SELECT ev2.id_tarea_origen AS id_tarea, ev2.id_evento FROM evento ev2 WHERE ev2.id_tarea_origen IS NOT NULL
			UNION
			SELECT sub.id_tarea AS id_tarea, et2.id_evento FROM evento_tarea et2 INNER JOIN tarea sub ON sub.id_tarea_padre=et2.id_tarea
		) rel INNER JOIN evento ev ON ev.id_evento=rel.id_evento
		INNER JOIN area ar ON ar.id_area=ev.id_area
		WHERE rel.id_tarea IN (".implode(',',array_map('intval',$it_candidate_ids)).")
		ORDER BY ev.fecha DESC, ev.id_evento DESC");
	foreach($it_event_rows as $it_event)
	{
		$it_task_id=intval($it_event['id_tarea']);
		if(!isset($it_visible[$it_task_id])) { continue; }
		if(!isset($it_activity_map[$it_task_id])) { $it_activity_map[$it_task_id]=array('linked'=>true,'visible'=>array()); }
		if(actividad_puede_ver($it_event)) { $it_activity_map[$it_task_id]['visible'][]=$it_event; }
	}
}
foreach($it_visible as $it_id=>&$it_task)
{
	$it_task['actividades_linked']=isset($it_activity_map[$it_id]) ? $it_activity_map[$it_id]['visible'] : array();
	$it_task['tiene_actividad']=isset($it_activity_map[$it_id]);
}
unset($it_task);

// Preload subtasks for visible parent tasks. The parent progress is calculated
// with the same average/terminated=100 rule as crm_tarea_recalcular_padre(),
// without writing or changing operational data.
$it_parent_ids=array();
foreach($it_visible as $it_id=>$it_task) { if(empty($it_task['id_tarea_padre'])) { $it_parent_ids[]=$it_id; } }
$it_children_all=array();
$it_children_visible=array();
if(count($it_parent_ids)>0)
{
	$it_child_rows=acceso_consulta("SELECT sub.id_tarea AS id, sub.id_tarea, sub.titulo, sub.descripcion,
		sub.id_area, sub.id_tipo, sub.prioridad, sub.estado, sub.fecha_inicio, sub.fecha_cierre,
		sub.avance, sub.id_tarea_padre, a.clave AS area_clave, a.nombre AS area_nombre,
		tp.nombre AS tipo_nombre, padre.titulo AS tarea_padre_titulo
		FROM tarea sub INNER JOIN area a ON a.id_area=sub.id_area
		INNER JOIN tipo_tarea tp ON tp.id_tipo=sub.id_tipo
		LEFT JOIN tarea padre ON padre.id_tarea=sub.id_tarea_padre
		WHERE sub.id_tarea_padre IN (".implode(',',array_map('intval',$it_parent_ids)).") AND sub.estado<>'cancelada'
		ORDER BY sub.id_tarea ASC");
	$it_child_ids=array();
	foreach($it_child_rows as $it_child) { $it_child_ids[]=intval($it_child['id_tarea']); }
	$it_child_responsibles=array();
	if(count($it_child_ids)>0)
	{
		foreach(acceso_consulta("SELECT tr.id_tarea, tr.id_empleado, TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))) AS nombre, tr.es_principal FROM tarea_responsable tr INNER JOIN empleado e ON e.id_empleado=tr.id_empleado WHERE tr.id_tarea IN (".implode(',',array_map('intval',$it_child_ids)).") ORDER BY tr.id_tarea, tr.es_principal DESC, e.apellido, e.nombres") as $it_child_assignment)
		{
			$it_child_id=intval($it_child_assignment['id_tarea']);
			if(!isset($it_child_responsibles[$it_child_id])) { $it_child_responsibles[$it_child_id]=array('names'=>array(),'ids'=>array(),'by_id'=>array()); }
			$it_child_name=trim((string)$it_child_assignment['nombre']);
			if($it_child_name!=='' && !in_array($it_child_name,$it_child_responsibles[$it_child_id]['names'],true)) { $it_child_responsibles[$it_child_id]['names'][]=$it_child_name; }
			$it_child_employee_id=intval($it_child_assignment['id_empleado']);
			$it_child_responsibles[$it_child_id]['ids'][]=$it_child_employee_id;
			$it_child_responsibles[$it_child_id]['by_id'][$it_child_employee_id]=$it_child_name;
		}
	}
	foreach($it_child_rows as $it_child)
	{
		$it_child_id=intval($it_child['id_tarea']);
		$it_child['responsables']=isset($it_child_responsibles[$it_child_id]) ? $it_child_responsibles[$it_child_id]['names'] : array();
		$it_child['responsable_ids']=isset($it_child_responsibles[$it_child_id]) ? array_values(array_unique($it_child_responsibles[$it_child_id]['ids'])) : array();
		$it_child['responsables_por_id']=isset($it_child_responsibles[$it_child_id]) ? $it_child_responsibles[$it_child_id]['by_id'] : array();
		$it_child['actividades_linked']=isset($it_visible[$it_child_id]) ? $it_visible[$it_child_id]['actividades_linked'] : array();
		$it_child['tiene_actividad']=isset($it_visible[$it_child_id]) ? !empty($it_visible[$it_child_id]['tiene_actividad']) : false;
		$it_child['responsable']=isset($it_child['responsables'][0]) ? $it_child['responsables'][0] : '';
		$it_parent_id=intval($it_child['id_tarea_padre']);
		if(!isset($it_children_all[$it_parent_id])) { $it_children_all[$it_parent_id]=array(); }
		$it_children_all[$it_parent_id][]=$it_child;
		if(tarea_puede_ver($it_child))
		{
			if(!isset($it_children_visible[$it_parent_id])) { $it_children_visible[$it_parent_id]=array(); }
			$it_children_visible[$it_parent_id][]=$it_child;
		}
	}
}
foreach($it_visible as $it_id=>&$it_task)
{
	$it_task['subtareas']=isset($it_children_visible[$it_id]) ? $it_children_visible[$it_id] : array();
	$it_progress_children=isset($it_children_all[$it_id]) ? $it_children_all[$it_id] : array();
	$it_task['avance_calculado']=null;
	if(count($it_progress_children)>0)
	{
		$it_sum=0;
		$it_all_done=true;
		foreach($it_progress_children as $it_progress_child)
		{
			$it_child_progress=strtolower((string)$it_progress_child['estado'])==='terminada' ? 100 : intval($it_progress_child['avance']);
			$it_sum+=$it_child_progress;
			if(strtolower((string)$it_progress_child['estado'])!=='terminada') { $it_all_done=false; }
		}
		$it_task['avance_calculado']=$it_all_done ? 100 : intval(round($it_sum/count($it_progress_children)));
	}
}
unset($it_task);
foreach($it_visible as $it_id=>&$it_task)
{
	if(!empty($it_task['id_tarea_padre'])) { continue; }
	$it_links=array();
	foreach($it_task['actividades_linked'] as $it_link) { $it_links[intval($it_link['id_evento'])]=$it_link; }
	$it_has_link=!empty($it_task['tiene_actividad']);
	foreach($it_task['subtareas'] as $it_child)
	{
		if(!empty($it_child['tiene_actividad'])) { $it_has_link=true; }
		foreach($it_child['actividades_linked'] as $it_link) { $it_links[intval($it_link['id_evento'])]=$it_link; }
	}
	$it_task['actividades_linked']=array_values($it_links);
	$it_task['tiene_actividad']=$it_has_link;
}
unset($it_task);

$it_date_validate=function($value) {
	$value=trim((string)$value);
	if($value==='') { return ''; }
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) { return false; }
	$parts=explode('-',$value);
	return checkdate(intval($parts[1]),intval($parts[2]),intval($parts[0])) ? $value : false;
};
$it_form=array(
	'q'=>isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string)$_GET['q']) : '',
	'area'=>isset($_GET['area']) && is_scalar($_GET['area']) ? trim((string)$_GET['area']) : '',
	'empleado'=>isset($_GET['empleado']) && is_scalar($_GET['empleado']) ? trim((string)$_GET['empleado']) : '',
	'estado'=>isset($_GET['estado']) && is_scalar($_GET['estado']) ? trim((string)$_GET['estado']) : '',
	'prioridad'=>isset($_GET['prioridad']) && is_scalar($_GET['prioridad']) ? trim((string)$_GET['prioridad']) : '',
	'tipo'=>isset($_GET['tipo']) && is_scalar($_GET['tipo']) ? trim((string)$_GET['tipo']) : '',
	'desde'=>isset($_GET['desde']) && is_scalar($_GET['desde']) ? trim((string)$_GET['desde']) : '',
	'hasta'=>isset($_GET['hasta']) && is_scalar($_GET['hasta']) ? trim((string)$_GET['hasta']) : ''
);
if(function_exists('mb_substr')) { $it_form['q']=mb_substr($it_form['q'],0,150,'UTF-8'); } else { $it_form['q']=substr($it_form['q'],0,150); }
$it_errors=array();
$it_form['desde']=$it_date_validate($it_form['desde']);
$it_form['hasta']=$it_date_validate($it_form['hasta']);
if($it_form['desde']===false) { $it_errors[]='La fecha desde no es válida.'; $it_form['desde']=''; }
if($it_form['hasta']===false) { $it_errors[]='La fecha hasta no es válida.'; $it_form['hasta']=''; }
if($it_form['desde']!=='' && $it_form['hasta']!=='' && $it_form['desde']>$it_form['hasta']) { $it_errors[]='La fecha inicial no puede ser posterior a la final.'; }

$it_areas_options=array();
$it_people_options=array();
$it_states_options=array();
$it_priorities_options=array();
foreach($it_visible as $it_task)
{
	$it_areas_options[intval($it_task['id_area'])]=$it_task['area_nombre'];
	if(isset($it_state_labels[$it_task['estado']])) { $it_states_options[$it_task['estado']]=$it_state_labels[$it_task['estado']]; }
	if(isset($it_priority_labels[$it_task['prioridad']])) { $it_priorities_options[$it_task['prioridad']]=$it_priority_labels[$it_task['prioridad']]; }
	foreach($it_task['responsable_ids'] as $it_person_id)
	{
		$it_person_name=isset($it_task['responsables_por_id'][$it_person_id]) ? $it_task['responsables_por_id'][$it_person_id] : '';
		if($it_person_id>0 && $it_person_name!=='') { $it_people_options[$it_person_id]=$it_person_name; }
	}
	foreach($it_task['subtareas'] as $it_filter_child)
	{
		foreach($it_filter_child['responsable_ids'] as $it_person_id)
		{
			$it_person_name=isset($it_filter_child['responsables_por_id'][$it_person_id]) ? $it_filter_child['responsables_por_id'][$it_person_id] : '';
			if($it_person_id>0 && $it_person_name!=='') { $it_people_options[$it_person_id]=$it_person_name; }
		}
	}
}
asort($it_areas_options,SORT_NATURAL|SORT_FLAG_CASE);
asort($it_people_options,SORT_NATURAL|SORT_FLAG_CASE);
$it_area_filter=$it_form['area']==='' ? 0 : intval($it_form['area']);
if($it_area_filter>0 && !isset($it_areas_options[$it_area_filter])) { $it_area_filter=0; $it_form['area']=''; }
$it_employee_filter=$it_form['empleado']==='' ? 0 : intval($it_form['empleado']);
if($it_employee_filter>0 && !isset($it_people_options[$it_employee_filter])) { $it_employee_filter=0; $it_form['empleado']=''; }
if($it_form['estado']!=='' && !isset($it_states_options[$it_form['estado']])) { $it_form['estado']=''; }
if($it_form['prioridad']!=='' && !isset($it_priorities_options[$it_form['prioridad']])) { $it_form['prioridad']=''; }
if($it_form['tipo']!=='' && !isset($it_types[$it_form['tipo']])) { $it_form['tipo']=''; }
$it_contains=function($needle,$haystack) {
	if(function_exists('mb_stripos')) { return mb_stripos((string)$haystack,(string)$needle,0,'UTF-8')!==false; }
	return stripos((string)$haystack,(string)$needle)!==false;
};
$it_rows=array();
if(count($it_errors)===0)
{
	foreach($it_visible as $it_task)
	{
		// Agrupar cada subtarea visible bajo su principal. Si el usuario no
		// puede consultar la principal, mostrar solo la subtarea sin revelar datos.
		if(!empty($it_task['id_tarea_padre']) && isset($it_visible[intval($it_task['id_tarea_padre'])])) { continue; }
		$it_child_rows=isset($it_task['subtareas']) ? $it_task['subtareas'] : array();
		$it_filter_items=array_merge(array($it_task),$it_child_rows);
		$it_group_matches=false;
		foreach($it_filter_items as $it_filter_item)
		{
			$it_activity_titles=array_map(function($event) { return $event['titulo']; },isset($it_filter_item['actividades_linked']) ? $it_filter_item['actividades_linked'] : array());
			$it_search_text=implode(' ',array($it_filter_item['titulo'],$it_filter_item['descripcion'],$it_filter_item['area_nombre'],$it_filter_item['tipo_nombre'],implode(' ',isset($it_filter_item['responsables']) ? $it_filter_item['responsables'] : array()),implode(' ',$it_activity_titles)));
			if($it_form['q']!=='' && !$it_contains($it_form['q'],$it_search_text)) { continue; }
			if($it_area_filter>0 && $it_area_filter!==intval($it_filter_item['id_area'])) { continue; }
			if($it_employee_filter>0 && !in_array($it_employee_filter,isset($it_filter_item['responsable_ids']) ? $it_filter_item['responsable_ids'] : array(),true)) { continue; }
			if($it_form['estado']!=='' && $it_form['estado']!==$it_filter_item['estado']) { continue; }
			if($it_form['prioridad']!=='' && $it_form['prioridad']!==$it_filter_item['prioridad']) { continue; }
			if($it_form['tipo']==='independiente' && !empty($it_filter_item['tiene_actividad'])) { continue; }
			if($it_form['tipo']==='vinculada' && empty($it_filter_item['tiene_actividad'])) { continue; }
			$it_due=substr((string)$it_filter_item['fecha_cierre'],0,10);
			if($it_form['desde']!=='' && $it_due<$it_form['desde']) { continue; }
			if($it_form['hasta']!=='' && $it_due>$it_form['hasta']) { continue; }
			$it_group_matches=true;
			break;
		}
		if(!$it_group_matches) { continue; }
		$it_rows[]=$it_task;
	}
}
$it_total=count($it_rows);
$it_subtask_total=0;
foreach($it_rows as $it_counted_task)
{
	$it_subtask_total+=count(isset($it_counted_task['subtareas']) ? $it_counted_task['subtareas'] : array());
}
$it_filters_applied=array();
if($it_form['q']!=='') { $it_filters_applied[]='Búsqueda: '.$it_form['q']; }
if($it_area_filter>0) { $it_filters_applied[]='Área: '.$it_areas_options[$it_area_filter]; }
if($it_employee_filter>0) { $it_filters_applied[]='Asignado: '.$it_people_options[$it_employee_filter]; }
if($it_form['estado']!=='') { $it_filters_applied[]='Estado: '.$it_states_options[$it_form['estado']]; }
if($it_form['prioridad']!=='') { $it_filters_applied[]='Prioridad: '.$it_priorities_options[$it_form['prioridad']]; }
if($it_form['tipo']!=='') { $it_filters_applied[]='Tipo: '.$it_types[$it_form['tipo']]; }
if($it_form['desde']!=='') { $it_filters_applied[]='Vence desde: '.date('d/m/Y',strtotime($it_form['desde'])); }
if($it_form['hasta']!=='') { $it_filters_applied[]='Vence hasta: '.date('d/m/Y',strtotime($it_form['hasta'])); }
$it_emitted=date('d/m/Y');

$it_sheet=function($it_task,$class_name='task-sheet') use ($it_esc,$it_date,$it_state_labels,$it_priority_labels,$it_emitted) {
	$it_task_type=!empty($it_task['id_tarea_padre']) ? (trim((string)$it_task['tarea_padre_titulo'])!=='' ? 'Subtarea de: '.(string)$it_task['tarea_padre_titulo'] : 'Subtarea · tarea principal no disponible') : 'Tarea principal';
	$it_activity_names=array();
	foreach($it_task['actividades_linked'] as $it_activity) { $it_activity_names[]=(string)$it_activity['titulo'].' ('.$it_date($it_activity['fecha']).')'; }
	if(!empty($it_task['tiene_actividad']) && count($it_activity_names)===0) { $it_activity_names[]='Actividad vinculada; detalle no disponible para este perfil'; }
	if(count($it_activity_names)===0) { $it_activity_names[]='Independiente'; }
	$it_percent=$it_task['avance_calculado']===null ? 'No corresponde' : intval($it_task['avance_calculado']).'%';
	$it_status=isset($it_state_labels[$it_task['estado']]) ? $it_state_labels[$it_task['estado']] : 'No disponible';
	$it_priority=isset($it_priority_labels[$it_task['prioridad']]) ? $it_priority_labels[$it_task['prioridad']] : 'No disponible';
	$it_meta=array(
		'Área responsable'=>$it_task['area_nombre'],
		'Objetivo / tipo operativo'=>trim((string)$it_task['tipo_nombre'])!=='' ? $it_task['tipo_nombre'] : 'No registrado',
		'Clasificación'=>$it_task_type,
		'Estado'=>$it_status,
		'Prioridad'=>$it_priority,
		'Fecha de inicio'=>$it_date($it_task['fecha_inicio']),
		'Fecha límite'=>$it_date($it_task['fecha_cierre']),
		'Responsables'=>count($it_task['responsables']) ? implode(', ',$it_task['responsables']) : 'Sin asignar',
		'Actividad vinculada'=>implode(' · ',$it_activity_names),
		'Avance calculado'=>$it_percent
	);
	$it_html='<article class="'.htmlspecialchars($class_name,ENT_QUOTES,'UTF-8').'" data-task-sheet><header class="task-sheet-head"><div><div class="task-sheet-brand">ADEMI · INFORME INSTITUCIONAL</div><h1>'.$it_esc($it_task['titulo']).'</h1><p class="task-sheet-subtitle">'.$it_esc($it_task_type).' · '.$it_esc($it_status).' · '.$it_esc($it_priority).'</p></div><div class="task-sheet-emitted">Emitida el '.$it_esc($it_emitted).'</div></header>';
	$it_html.='<section class="task-sheet-section"><h2>Información general</h2><div class="task-sheet-grid">';
	foreach($it_meta as $it_label=>$it_value) { $it_html.='<div class="task-sheet-item"><span class="task-sheet-label">'.$it_esc($it_label).'</span><div class="task-sheet-value">'.$it_esc($it_value).'</div></div>'; }
	$it_html.='</div></section><section class="task-sheet-section"><h2>Descripción</h2><div class="task-sheet-text">'.$it_esc(trim((string)$it_task['descripcion'])!=='' ? $it_task['descripcion'] : 'Sin descripción registrada.').'</div></section>';
	if(count($it_task['subtareas'])>0)
	{
		$it_html.='<section class="task-sheet-section"><h2>Subtareas visibles y avance</h2><p class="task-sheet-progress">Avance calculado sobre todas las subtareas activas: <strong>'.$it_esc($it_percent).'</strong></p><div class="task-sheet-subtasks"><table><thead><tr><th>Subtarea</th><th>Responsable</th><th>Estado</th><th>Avance</th><th>Fecha límite</th></tr></thead><tbody>';
		foreach($it_task['subtareas'] as $it_child)
		{
			$it_child_status=isset($it_state_labels[$it_child['estado']]) ? $it_state_labels[$it_child['estado']] : 'No disponible';
			$it_child_advance=strtolower((string)$it_child['estado'])==='terminada' ? 100 : intval($it_child['avance']);
			$it_html.='<tr><td>'.$it_esc($it_child['titulo']).'</td><td>'.$it_esc(count($it_child['responsables']) ? implode(', ',$it_child['responsables']) : 'Sin asignar').'</td><td>'.$it_esc($it_child_status).'</td><td>'.intval($it_child_advance).'%</td><td>'.$it_esc($it_date($it_child['fecha_cierre'])).'</td></tr>';
		}
		$it_html.='</tbody></table></div></section>';
	}
	$it_html.='<footer class="task-sheet-footer">ADEMI · Informe interno de tareas</footer></article>';
	return $it_html;
};

$it_task_ficha=isset($_GET['tarea_ficha']) && is_scalar($_GET['tarea_ficha']) && $_GET['tarea_ficha']!=='0';
$it_ficha=null;
if($it_task_ficha)
{
	$it_ficha_id=filter_var($_GET['tarea_ficha'],FILTER_VALIDATE_INT);
	if(!$it_ficha_id || intval($it_ficha_id)<=0) { http_response_code(404); exit('La tarea solicitada no existe o no está autorizada.'); }
	foreach($it_visible as $it_row) { if(intval($it_row['id_tarea'])===intval($it_ficha_id)) { $it_ficha=$it_row; break; } }
	if(!$it_ficha) { http_response_code(404); exit('La tarea solicitada no existe o no está autorizada.'); }
}
if($it_task_ficha)
{
	?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ficha de tarea | ADEMI</title><style>
@page{size:A4 portrait;margin:13mm 14mm}*{box-sizing:border-box}html,body{margin:0;background:#eef2f7;color:#1e293b;font:11pt/1.45 Arial,sans-serif}.task-sheet{width:min(900px,100%);margin:20px auto;padding:26px;background:#fff;border:1px solid #d9e2ef;border-radius:10px;box-shadow:0 8px 26px rgba(15,23,42,.08)}.task-sheet-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding-bottom:14px;border-bottom:2px solid #2563eb}.task-sheet-brand{font-size:9pt;letter-spacing:.12em;font-weight:700;color:#2563eb}.task-sheet h1{margin:5px 0;font-size:21pt;color:#17365d}.task-sheet-subtitle,.task-sheet-emitted{margin:0;color:#64748b;font-size:9pt}.task-sheet-emitted{white-space:nowrap}.task-sheet-section{margin-top:18px}.task-sheet-section h2{margin:0 0 9px;padding-bottom:5px;border-bottom:1px solid #dce5f0;font-size:12pt;color:#1e3a5f}.task-sheet-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px}.task-sheet-item{min-width:0}.task-sheet-label{display:block;margin-bottom:2px;color:#64748b;font-size:8pt;letter-spacing:.06em;text-transform:uppercase}.task-sheet-value,.task-sheet-text{white-space:pre-wrap;overflow-wrap:anywhere;color:#23364d}.task-sheet-progress{margin:0 0 8px;color:#334155}.task-sheet-subtasks table{width:100%;border-collapse:collapse;font-size:9pt}.task-sheet-subtasks th,.task-sheet-subtasks td{padding:5px;border:1px solid #d7e0ea;text-align:left;vertical-align:top}.task-sheet-footer{margin-top:22px;padding-top:7px;border-top:1px solid #dce5f0;text-align:center;color:#64748b;font-size:8pt}@media(max-width:600px){.task-sheet{margin:0;padding:18px;border-radius:0}.task-sheet-head{flex-direction:column;gap:6px}.task-sheet-grid{grid-template-columns:1fr}}@media print{html,body{background:#fff}.task-sheet{width:100%;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.task-sheet-section{break-inside:auto;page-break-inside:auto}.task-sheet-footer{position:static}.task-sheet-subtasks tr{break-inside:avoid;page-break-inside:avoid}}
</style><style>
.task-sheet-head{gap:12px;padding-bottom:10px}.task-sheet h1{font-size:18pt;line-height:1.2}.task-sheet-section{margin-top:13px}.task-sheet-section h2{margin-bottom:7px;padding-bottom:4px}.task-sheet-grid{gap:7px 14px}.task-sheet-footer{margin-top:14px;padding-top:6px}
@media print{.task-sheet{width:100%;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.task-sheet-head{padding-bottom:8px}.task-sheet-section{margin-top:10px}.task-sheet-section h2{margin-bottom:5px}.task-sheet-grid{gap:5px 10px}.task-sheet-footer{margin-top:10px;padding-top:5px}.task-sheet-subtasks thead{display:table-header-group}.task-sheet-subtasks tr{break-inside:avoid;page-break-inside:avoid}.task-print-page+.task-print-page{break-before:page;page-break-before:always}}
</style></head><body><?php echo $it_sheet($it_ficha); ?></body></html><?php
	exit();
}

if(!empty($impresion_fichas))
{
	if(count($it_errors)>0 || count($it_rows)===0)
	{
		$it_message=count($it_errors)>0 ? implode(' ',$it_errors) : 'No hay tareas para imprimir con los filtros seleccionados.';
		?><!doctype html><html lang="es" data-rrhh-print-ready="1" data-rrhh-print-error="1" data-rrhh-print-message="<?php echo $it_esc($it_message); ?>"><meta charset="utf-8"><title>Sin resultados</title><body><p><?php echo $it_esc($it_message); ?></p></body></html><?php
		exit();
	}
	?><!doctype html><html lang="es" data-rrhh-print-ready="1"><head><meta charset="utf-8"><title>Fichas de tareas | ADEMI</title><style>
@page{size:A4 portrait;margin:13mm 14mm}*{box-sizing:border-box}html,body{margin:0;padding:0;width:auto;height:auto;min-height:0;overflow:visible;background:#fff;color:#1e293b;font:10.5pt/1.4 Arial,sans-serif}.task-print-list{display:block;width:100%;margin:0;padding:0}.task-print-page{display:block;position:static;width:100%;margin:0;padding:0;break-inside:auto;page-break-inside:auto}.task-print-page+.task-print-page{break-before:page;page-break-before:always}.task-sheet-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-bottom:8px;border-bottom:2px solid #2563eb}.task-sheet-brand{font-size:8.5pt;letter-spacing:.12em;font-weight:700;color:#334155}.task-sheet h1{margin:4px 0;font-size:18pt;color:#17365d}.task-sheet-subtitle,.task-sheet-emitted{margin:0;color:#64748b;font-size:8.5pt}.task-sheet-emitted{white-space:nowrap}.task-sheet-section{margin-top:14px}.task-sheet-section h2{margin:0 0 7px;padding-bottom:4px;border-bottom:1px solid #cbd5e1;font-size:10.5pt;color:#1e3a5f}.task-sheet-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px}.task-sheet-label{display:block;margin-bottom:2px;color:#64748b;font-size:7.5pt;letter-spacing:.05em;text-transform:uppercase}.task-sheet-value,.task-sheet-text{white-space:pre-wrap;overflow-wrap:anywhere}.task-sheet-progress{margin:0 0 7px}.task-sheet-subtasks table{width:100%;border-collapse:collapse;font-size:8pt}.task-sheet-subtasks th,.task-sheet-subtasks td{padding:3px 4px;border:1px solid #cbd5e1;text-align:left;vertical-align:top}.task-sheet-footer{margin-top:16px;padding-top:6px;border-top:1px solid #cbd5e1;text-align:center;color:#64748b;font-size:8pt}.task-print-page+.task-print-page{break-before:page;page-break-before:always}@media print{html,body{overflow:visible!important}.task-print-page{margin:0!important}.task-print-page .task-sheet-section{break-inside:auto;page-break-inside:auto}.task-print-page .task-sheet-subtasks tr{break-inside:avoid;page-break-inside:avoid}}
</style><style>
.task-sheet-head{gap:12px;padding-bottom:10px}.task-sheet h1{font-size:18pt;line-height:1.2}.task-sheet-section{margin-top:13px}.task-sheet-section h2{margin-bottom:7px;padding-bottom:4px}.task-sheet-grid{gap:7px 14px}.task-sheet-footer{margin-top:14px;padding-top:6px}
@media print{.task-sheet{width:100%;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.task-sheet-head{padding-bottom:8px}.task-sheet-section{margin-top:10px}.task-sheet-section h2{margin-bottom:5px}.task-sheet-grid{gap:5px 10px}.task-sheet-footer{margin-top:10px;padding-top:5px}.task-sheet-subtasks thead{display:table-header-group}.task-sheet-subtasks tr{break-inside:avoid;page-break-inside:avoid}.task-print-page+.task-print-page{break-before:page;page-break-before:always}}
</style></head><body><main class="task-print-list"><?php foreach($it_rows as $it_row) { echo $it_sheet($it_row,'task-print-page'); } ?></main></body></html><?php
	exit();
}

$it_result_html=function() use ($it_rows,$it_total,$it_subtask_total,$it_errors,$it_esc,$it_date,$it_state_labels,$it_priority_labels,$it_emitted,$it_filters_applied,$it_types,$it_sheet) {
	?><div class="rrhh-report-async-response" id="rrhh-report-async-response" data-total="<?php echo intval($it_total); ?>" data-invalid="<?php echo count($it_errors)>0 ? '1' : '0'; ?>">
	<?php foreach($it_errors as $it_error) { ?><div class="alert alert-danger rrhh-report-async-error"><?php echo $it_esc($it_error); ?></div><?php } ?>
	<header class="rrhh-report-print-only rrhh-report-print-header"><div class="institution">ADEMI · INFORME INSTITUCIONAL</div><h1>Informe de tareas</h1><div class="rrhh-report-meta">Emitido el <?php echo $it_esc($it_emitted); ?></div><div class="rrhh-report-filters-print">Filtros: <?php echo $it_esc(count($it_filters_applied) ? implode(' · ',$it_filters_applied) : 'Todas las tareas autorizadas'); ?></div></header>
	<div class="grid simple rrhh-report-grid crm-task-report-grid"><div class="grid-title rrhh-report-results-heading"><h4>Resultados <span class="rrhh-report-total-count">Total de tareas: <?php echo intval($it_total); ?> · Subtareas agrupadas: <?php echo intval($it_subtask_total); ?></span></h4><div class="rrhh-report-result-actions no-print"><button type="button" class="btn btn-white" data-print-list <?php echo (count($it_errors)>0 || $it_total===0) ? 'disabled' : ''; ?>><i class="fa fa-print"></i> Imprimir informe</button><button type="button" class="btn btn-primary" data-print-fichas <?php echo (count($it_errors)>0 || $it_total===0) ? 'disabled' : ''; ?>><i class="fa fa-print"></i> Imprimir fichas</button><span class="rrhh-report-print-fichas-status" data-print-fichas-status role="status" aria-live="polite" hidden></span><button type="button" class="btn btn-white btn-sm" data-print-fichas-retry hidden>Reintentar impresión</button></div></div>
	<div class="grid-body"><div class="table-responsive crm-task-screen-table"><table class="table table-bordered table-hover rrhh-report-table crm-task-report-table"><thead><tr><th>Tarea</th><th>Subtareas</th><th>Área</th><th>Responsable / asignado</th><th>Estado</th><th>Prioridad</th><th>Fecha límite</th><th>Actividad vinculada</th><th>Tipo de tarea</th></tr></thead><tbody>
	<?php if(count($it_errors)>0 || $it_total===0) { ?><tr><td colspan="9" class="rrhh-report-empty"><?php echo count($it_errors)>0 ? 'Corregí los filtros para consultar el informe.' : 'No se encontraron tareas con los criterios seleccionados.'; ?></td></tr><?php } else { foreach($it_rows as $it_row) {
		$it_status=isset($it_state_labels[$it_row['estado']]) ? $it_state_labels[$it_row['estado']] : 'No disponible';
		$it_priority=isset($it_priority_labels[$it_row['prioridad']]) ? $it_priority_labels[$it_row['prioridad']] : 'No disponible';
		$it_activities=array(); foreach($it_row['actividades_linked'] as $it_activity) { $it_activities[]=(string)$it_activity['titulo']; }
		$it_activity_text=count($it_activities) ? implode(' · ',$it_activities) : (!empty($it_row['tiene_actividad']) ? 'Vinculada · detalle no disponible' : '—');
		$it_kind=!empty($it_row['tiene_actividad']) ? $it_types['vinculada'] : $it_types['independiente'];
		$it_href=url_crm('informes.php?reporte=tareas&tarea_ficha='.intval($it_row['id_tarea']));
	?>
	<tr class="rrhh-report-row-link" tabindex="0" role="button" data-report-open data-href="<?php echo $it_esc($it_href); ?>" aria-haspopup="dialog" aria-label="Abrir ficha de tarea: <?php echo $it_esc($it_row['titulo']); ?>">
	<td><div class="crm-task-group-title"><?php echo $it_esc(!empty($it_row['id_tarea_padre']) ? 'Subtarea · tarea principal no disponible: '.$it_row['titulo'] : $it_row['titulo']); ?></div></td>
	<td><?php if(count($it_row['subtareas'])>0) { ?><div class="crm-task-subtask-list" aria-label="Subtareas"><?php foreach($it_row['subtareas'] as $it_child_row) { $it_parent_ids=isset($it_row['responsable_ids']) ? $it_row['responsable_ids'] : array(); $it_child_different_ids=array_values(array_diff(isset($it_child_row['responsable_ids']) ? $it_child_row['responsable_ids'] : array(),$it_parent_ids)); $it_child_different_names=array(); foreach($it_child_different_ids as $it_child_person_id) { if(isset($it_child_row['responsables_por_id'][$it_child_person_id]) && $it_child_row['responsables_por_id'][$it_child_person_id]!=='') { $it_child_different_names[]=$it_child_row['responsables_por_id'][$it_child_person_id]; } } ?><div class="crm-task-subtask"><span class="crm-task-subtask-name"><?php echo $it_esc($it_child_row['titulo']); ?></span><?php if(count($it_child_different_names)>0) { ?><span class="crm-task-subtask-meta">Responsable: <?php echo $it_esc(implode(', ',$it_child_different_names)); ?></span><?php } ?></div><?php } ?></div><?php } else { ?><span class="crm-task-no-subtasks">Sin subtareas</span><?php } ?></td>
	<td><?php echo $it_esc($it_row['area_nombre']); ?></td><td><?php echo $it_esc(count($it_row['responsables']) ? implode(', ',$it_row['responsables']) : 'Sin asignar'); ?></td><td><?php echo $it_esc($it_status); ?></td><td><?php echo $it_esc($it_priority); ?></td><td><?php echo $it_esc($it_date($it_row['fecha_cierre'])); ?></td><td><?php echo $it_esc($it_activity_text); ?></td><td><?php echo $it_esc($it_kind); ?></td></tr>
	<?php } } ?></tbody></table></div>
	<div class="crm-task-report-print-table-wrap"><table class="crm-task-report-print-table"><thead><tr><th>Tarea</th><th>Subtareas</th><th>Área</th><th>Responsable</th><th>Estado y prioridad</th><th>Fecha límite</th><th>Actividad vinculada y tipo</th></tr></thead><tbody>
	<?php if(count($it_errors)>0 || $it_total===0) { ?><tr><td colspan="7" class="rrhh-report-empty"><?php echo count($it_errors)>0 ? 'Corregí los filtros para consultar el informe.' : 'No se encontraron tareas con los criterios seleccionados.'; ?></td></tr><?php } else { foreach($it_rows as $it_row) {
		$it_status=isset($it_state_labels[$it_row['estado']]) ? $it_state_labels[$it_row['estado']] : 'No disponible';
		$it_priority=isset($it_priority_labels[$it_row['prioridad']]) ? $it_priority_labels[$it_row['prioridad']] : 'No disponible';
		$it_activities=array(); foreach($it_row['actividades_linked'] as $it_activity) { $it_activities[]=(string)$it_activity['titulo']; }
		$it_activity_text=count($it_activities) ? implode(' · ',$it_activities) : (!empty($it_row['tiene_actividad']) ? 'Vinculada · detalle no disponible' : 'Independiente');
		$it_kind=!empty($it_row['tiene_actividad']) ? $it_types['vinculada'] : $it_types['independiente'];
	?>
	<?php $it_parent_ids=isset($it_row['responsable_ids']) ? $it_row['responsable_ids'] : array(); ?>
	<tr><td><strong class="crm-task-group-title"><?php echo $it_esc(!empty($it_row['id_tarea_padre']) ? 'Subtarea (tarea principal no disponible): '.$it_row['titulo'] : $it_row['titulo']); ?></strong></td><td><?php if(count($it_row['subtareas'])>0) { ?><div class="crm-task-subtask-list"><?php foreach($it_row['subtareas'] as $it_child_row) { $it_child_different_ids=array_values(array_diff(isset($it_child_row['responsable_ids']) ? $it_child_row['responsable_ids'] : array(),$it_parent_ids)); $it_child_different_names=array(); foreach($it_child_different_ids as $it_child_person_id) { if(isset($it_child_row['responsables_por_id'][$it_child_person_id]) && $it_child_row['responsables_por_id'][$it_child_person_id]!=='') { $it_child_different_names[]=$it_child_row['responsables_por_id'][$it_child_person_id]; } } ?><div class="crm-task-subtask"><span class="crm-task-subtask-name"><?php echo $it_esc($it_child_row['titulo']); ?></span><?php if(count($it_child_different_names)>0) { ?><span class="crm-task-subtask-meta">Responsable: <?php echo $it_esc(implode(', ',$it_child_different_names)); ?></span><?php } ?></div><?php } ?></div><?php } else { ?><span class="crm-task-no-subtasks">Sin subtareas</span><?php } ?></td><td><?php echo $it_esc($it_row['area_nombre']); ?></td><td><?php echo $it_esc(count($it_row['responsables']) ? implode(', ',$it_row['responsables']) : 'Sin asignar'); ?></td><td><span class="crm-task-print-label">Estado</span><?php echo $it_esc($it_status); ?><span class="crm-task-print-label">Prioridad</span><?php echo $it_esc($it_priority); ?></td><td class="crm-task-print-date"><?php echo $it_esc($it_date($it_row['fecha_cierre'])); ?></td><td><?php echo $it_esc($it_activity_text); ?><span class="crm-task-print-label">Tipo</span><?php echo $it_esc($it_kind); ?></td></tr>
	<?php } } ?></tbody></table></div><div class="rrhh-report-print-only rrhh-report-print-footer">ADEMI · Informe interno de tareas · Registros: <?php echo intval($it_total); ?></div></div></div></div><?php
};
if(!empty($ajax_resultados)) { $it_result_html(); exit(); }
?>
<div class="rrhh-report-wrap crm-task-report" data-task-report>
<form method="get" action="<?php echo $it_esc(url_crm('informes.php')); ?>" class="rrhh-report-filters" data-report-filters>
	<input type="hidden" name="reporte" value="tareas">
	<div class="grid simple rrhh-report-grid no-print"><div class="grid-title no-border"><h4><i class="fa fa-filter"></i> Filtros</h4></div><div class="grid-body no-border"><div class="rrhh-report-filters-grid crm-task-filters-grid">
	<div class="form-group"><label for="task-report-q">Buscar tarea</label><input id="task-report-q" name="q" type="search" class="form-control" maxlength="150" value="<?php echo $it_esc($it_form['q']); ?>" placeholder="Título, descripción o asignado"></div>
	<?php if($it_global || count($it_area_scope)!==1) { ?><div class="form-group"><label for="task-report-area">Área</label><select id="task-report-area" name="area" class="form-control"><option value="">Todas</option><?php foreach($it_areas_options as $it_area_id=>$it_area_name) { ?><option value="<?php echo intval($it_area_id); ?>" <?php echo (string)$it_area_id===$it_form['area'] ? 'selected' : ''; ?>><?php echo $it_esc($it_area_name); ?></option><?php } ?></select></div><?php } ?>
	<div class="form-group"><label for="task-report-person">Empleado asignado</label><select id="task-report-person" name="empleado" class="form-control"><option value="">Todos</option><?php foreach($it_people_options as $it_person_id=>$it_person_name) { ?><option value="<?php echo intval($it_person_id); ?>" <?php echo (string)$it_person_id===$it_form['empleado'] ? 'selected' : ''; ?>><?php echo $it_esc($it_person_name); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="task-report-status">Estado</label><select id="task-report-status" name="estado" class="form-control"><option value="">Todos</option><?php foreach($it_states_options as $it_state=>$it_state_text) { ?><option value="<?php echo $it_esc($it_state); ?>" <?php echo $it_form['estado']===$it_state ? 'selected' : ''; ?>><?php echo $it_esc($it_state_text); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="task-report-priority">Prioridad</label><select id="task-report-priority" name="prioridad" class="form-control"><option value="">Todas</option><?php foreach($it_priorities_options as $it_priority=>$it_priority_text) { ?><option value="<?php echo $it_esc($it_priority); ?>" <?php echo $it_form['prioridad']===$it_priority ? 'selected' : ''; ?>><?php echo $it_esc($it_priority_text); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="task-report-from">Vence desde</label><input id="task-report-from" name="desde" type="date" class="form-control" value="<?php echo $it_esc($it_form['desde']); ?>"></div>
	<div class="form-group"><label for="task-report-to">Vence hasta</label><input id="task-report-to" name="hasta" type="date" class="form-control" value="<?php echo $it_esc($it_form['hasta']); ?>"></div>
	<div class="crm-task-filter-type-actions"><div class="form-group"><label for="task-report-kind">Tipo de tarea</label><select id="task-report-kind" name="tipo" class="form-control"><option value="">Todas</option><?php foreach($it_types as $it_type=>$it_type_text) { ?><option value="<?php echo $it_esc($it_type); ?>" <?php echo $it_form['tipo']===$it_type ? 'selected' : ''; ?>><?php echo $it_esc($it_type_text); ?></option><?php } ?></select></div><div class="rrhh-report-actions"><button type="button" class="btn btn-white" data-report-clear>Limpiar</button></div></div>
	</div></div></div></div>
</form>
<div id="rrhh-report-fetch-error" class="alert alert-danger rrhh-report-async-error" role="alert" hidden></div>
<?php $it_result_html(); ?>
</div>
<div class="rrhh-report-modal no-print" id="crm-task-modal" role="dialog" aria-modal="true" aria-labelledby="crm-task-modal-title" hidden><div class="rrhh-report-modal-dialog"><div class="rrhh-report-modal-head"><h2 id="crm-task-modal-title">Ficha de tarea</h2><button type="button" class="btn btn-white btn-sm" data-task-close aria-label="Cerrar ficha"><i class="fa fa-times"></i></button></div><div class="rrhh-report-modal-body"><div class="rrhh-report-modal-loading" data-task-loading role="status">Cargando ficha…</div><iframe class="rrhh-report-modal-frame" data-task-frame title="Ficha de tarea" src="about:blank"></iframe></div><div class="rrhh-report-modal-foot"><p class="rrhh-report-modal-hint">Para guardar como PDF, elegí esa opción en el diálogo de impresión del navegador.</p><button type="button" class="btn btn-white" data-task-print><i class="fa fa-print"></i> Imprimir</button><button type="button" class="btn btn-primary" data-task-pdf><i class="fa fa-file-pdf-o"></i> Guardar como PDF</button><button type="button" class="btn btn-white" data-task-close>Cerrar</button></div></div></div>
<script>
(function(){
var form=document.querySelector('[data-task-report] [data-report-filters]'),modal=document.getElementById('crm-task-modal'),frame=document.querySelector('[data-task-frame]'),loader=document.querySelector('[data-task-loading]'),timer=null,seq=0,controller=null,bodyOverflow='',returnFocus=null,massFrame=null,massTimer=null,dialogRequested=false;
if(modal&&modal.parentNode!==document.body){document.body.appendChild(modal);}
function root(){return document.getElementById('rrhh-report-async-response');}
function url(ajax){var u=new URL(form.action,location.href),p=new URLSearchParams(new FormData(form));if(ajax){p.set('ajax','resultados');}u.search=p.toString();return u;}
function buttons(disabled){var total=root()?parseInt(root().getAttribute('data-total'),10):0;document.querySelectorAll('[data-print-list],[data-print-fichas]').forEach(function(b){b.disabled=disabled||!total||!!massFrame;});}
function refresh(){if(massFrame&&!dialogRequested){dispose();}if(controller){controller.abort();}controller=typeof AbortController!=='undefined'?new AbortController():null;var n=++seq;form.setAttribute('aria-busy','true');buttons(true);var opt={credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}};if(controller){opt.signal=controller.signal;}return fetch(url(true).toString(),opt).then(function(r){if(!r.ok){throw new Error();}return r.text();}).then(function(html){if(n!==seq){return false;}var doc=new DOMParser().parseFromString(html,'text/html'),next=doc.getElementById('rrhh-report-async-response'),old=root();if(!next||!old){throw new Error();}old.replaceWith(document.importNode(next,true));var err=document.getElementById('rrhh-report-fetch-error');if(err){err.hidden=true;}var address=url(false);history.replaceState({},'',address.pathname+address.search);return true;}).catch(function(e){if(n===seq&&e.name!=='AbortError'){var err=document.getElementById('rrhh-report-fetch-error');if(err){err.textContent='No se pudieron actualizar los resultados. Revisá tu conexión e intentá nuevamente.';err.hidden=false;}return false;}return false;}).then(function(ok){if(n===seq){form.removeAttribute('aria-busy');buttons(false);}return ok;});}
function dispose(){dialogRequested=false;if(massTimer){clearTimeout(massTimer);massTimer=null;}if(massFrame){massFrame.remove();massFrame=null;}buttons(false);}
function showPrint(message,retry){var status=document.querySelector('[data-print-fichas-status]'),button=document.querySelector('[data-print-fichas-retry]');if(status){status.textContent=message;status.hidden=false;}if(button){button.hidden=!retry;}}
function startPrint(u){if(massFrame){return;}showPrint('Preparando las fichas…',false);massFrame=document.createElement('iframe');massFrame.setAttribute('aria-hidden','true');massFrame.setAttribute('tabindex','-1');massFrame.className='no-print';massFrame.title='Documento temporal de impresión';massFrame.style.cssText='position:fixed;left:-10000px;top:0;width:1000px;height:1200px;border:0;opacity:0;pointer-events:none;z-index:-1';var target=massFrame;target.addEventListener('load',function(){if(massFrame!==target){return;}try{var html=target.contentDocument.documentElement;if(html.getAttribute('data-rrhh-print-error')==='1'){var msg=html.getAttribute('data-rrhh-print-message')||'No hay tareas para imprimir.';dispose();showPrint(msg,false);return;}if(html.getAttribute('data-rrhh-print-ready')!=='1'){dispose();showPrint('No se pudo preparar la impresión. Actualizá el informe e intentá nuevamente.',false);return;}dialogRequested=true;showPrint('Abriendo el diálogo de impresión…',false);target.contentWindow.addEventListener('afterprint',function(){if(massFrame===target){dispose();var status=document.querySelector('[data-print-fichas-status]');if(status){status.hidden=true;}}});target.contentWindow.print();massTimer=setTimeout(function(){if(massFrame===target){dialogRequested=false;showPrint('Si no apareció el diálogo de impresión, podés reintentar.',true);}},1800);}catch(e){dispose();showPrint('El navegador no pudo abrir la impresión automáticamente.',true);}});target.src=u.toString();document.body.appendChild(target);}
form.addEventListener('submit',function(e){e.preventDefault();refresh();});var q=form.querySelector('[name=q]');q.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(refresh,350);});form.querySelectorAll('select,input[type=date]').forEach(function(el){el.addEventListener('change',function(){clearTimeout(timer);timer=setTimeout(refresh,0);});});form.querySelector('[data-report-clear]').addEventListener('click',function(){form.reset();q.focus();refresh();});
document.addEventListener('click',function(e){var b=e.target.closest('[data-print-list]');if(b){e.preventDefault();refresh().then(function(ok){if(ok&&root()&&parseInt(root().getAttribute('data-total'),10)>0&&root().getAttribute('data-invalid')!=='1'){window.print();}});return;}b=e.target.closest('[data-print-fichas]');if(b){e.preventDefault();if(b.disabled){return;}var u=url(false);u.searchParams.set('imprimir_fichas','1');startPrint(u);return;}b=e.target.closest('[data-print-fichas-retry]');if(b){e.preventDefault();var u=url(false);u.searchParams.set('imprimir_fichas','1');startPrint(u);return;}var row=e.target.closest('[data-report-open][data-href]');if(row){e.preventDefault();returnFocus=row;document.getElementById('crm-task-modal-title').textContent='Ficha de '+row.cells[0].textContent.trim();modal.hidden=false;bodyOverflow=document.body.style.overflow;document.body.style.overflow='hidden';loader.hidden=false;frame.src=row.getAttribute('data-href');var closeButton=modal.querySelector('[data-task-close]');if(closeButton){closeButton.focus();}return;}if(e.target.closest('[data-task-close]')){close();return;}if(e.target.closest('[data-task-print],[data-task-pdf]')){e.preventDefault();try{frame.contentWindow.focus();frame.contentWindow.print();}catch(err){loader.textContent='No se pudo abrir el diálogo de impresión. Volvé a intentar cuando la ficha termine de cargar.';loader.hidden=false;}}});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal&&!modal.hidden){close();return;}var row=e.target.closest&&e.target.closest('[data-report-open][data-href]');if(row&&(e.key==='Enter'||e.key===' ')){e.preventDefault();row.click();}});
function close(){if(!modal||modal.hidden){return;}modal.hidden=true;frame.src='about:blank';document.body.style.overflow=bodyOverflow;if(returnFocus&&document.contains(returnFocus)){returnFocus.focus();}returnFocus=null;}
frame.addEventListener('load',function(){if(modal.hidden||frame.getAttribute('src')==='about:blank'){return;}try{var doc=frame.contentDocument;if(doc.querySelector('[data-task-sheet]')){loader.hidden=true;doc.addEventListener('keydown',function(event){if(event.key==='Escape'){event.preventDefault();close();}});return;}}catch(e){}loader.textContent='No se pudo cargar la ficha. Verificá que tu sesión siga activa y tengas autorización para consultar esta tarea.';loader.hidden=false;});buttons(false);
})();
</script>
<style>
.crm-task-filters-grid{grid-template-columns:minmax(170px,1.45fr) minmax(110px,.9fr) minmax(145px,1.15fr) minmax(105px,.85fr) minmax(100px,.8fr) minmax(125px,.95fr) minmax(125px,.95fr) minmax(140px,1fr) auto}
.crm-task-filter-type-actions{display:flex;grid-column:span 2;align-items:flex-end;gap:8px;min-width:0}
.crm-task-filter-type-actions .form-group{flex:1 1 140px;min-width:0}
.crm-task-filter-type-actions .rrhh-report-actions{flex:0 0 auto;align-items:flex-end;justify-content:flex-start;min-height:32px}
.crm-task-filter-type-actions .rrhh-report-actions .btn{white-space:nowrap}
.crm-task-report-grid .grid-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.crm-task-report-grid .grid-title h4{margin:0}
.crm-task-report-table{width:100%;table-layout:fixed}
.crm-task-report-table th:nth-child(1){width:15%}.crm-task-report-table th:nth-child(2){width:22%}.crm-task-report-table th:nth-child(3){width:8%}.crm-task-report-table th:nth-child(4){width:13%}.crm-task-report-table th:nth-child(5){width:8%}.crm-task-report-table th:nth-child(6){width:7%}.crm-task-report-table th:nth-child(7){width:9%}.crm-task-report-table th:nth-child(8){width:11%}.crm-task-report-table th:nth-child(9){width:7%}
.crm-task-group-title{font-weight:700;color:#223a55}
.crm-task-subtask-list{margin:0;border-left:2px solid #cbd5e1;padding-left:8px}
.crm-task-subtask{padding:5px 0;font-weight:400}
.crm-task-subtask+.crm-task-subtask{border-top:1px solid #e5eaf0}
.crm-task-subtask-name{display:block}
.crm-task-subtask-meta{display:block;margin-top:2px;color:#64748b;font-size:11px}
.crm-task-no-subtasks{color:#64748b;font-style:italic}
.crm-task-report-print-table-wrap{display:none}
@media(max-width:1100px){.crm-task-filters-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:767px){.crm-task-filters-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-task-filter-type-actions{grid-column:1/-1}}
@media print{
	.rrhh-report-print-header,.rrhh-report-print-header .institution,.rrhh-report-print-header h1,.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{display:block!important;visibility:visible!important}
	.rrhh-report-print-header{padding:0 0 4px!important;margin:0 0 5px!important}
	.rrhh-report-print-header .institution,.rrhh-report-print-header h1,.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{color:#334155!important}
	.rrhh-report-print-header .institution{font-size:8.5pt!important}
	.rrhh-report-print-header h1{font-size:14pt!important;margin:1px 0!important}
	.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{font-size:7.5pt!important}
	.crm-task-report-grid .grid-title{display:none!important}
	.rrhh-report-grid .crm-task-screen-table{display:none!important}
	.crm-task-report-print-table-wrap{display:block!important;width:100%!important;max-width:100%!important;overflow:visible!important}
	.crm-task-report-print-table{display:table!important;width:100%!important;max-width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;font-size:8pt!important;line-height:1.2!important}
	.crm-task-report-print-table th,.crm-task-report-print-table td{box-sizing:border-box!important;padding:3px 4px!important;border:1px solid #cbd5e1!important;text-align:left!important;vertical-align:top!important;white-space:normal!important;overflow-wrap:break-word!important;word-break:normal!important;hyphens:none!important}
	.crm-task-report-print-table th:nth-child(1){width:16%!important}.crm-task-report-print-table th:nth-child(2){width:25%!important}.crm-task-report-print-table th:nth-child(3){width:9%!important}.crm-task-report-print-table th:nth-child(4){width:13%!important}.crm-task-report-print-table th:nth-child(5){width:12%!important}.crm-task-report-print-table th:nth-child(6){width:8%!important}.crm-task-report-print-table th:nth-child(7){width:17%!important}
	.crm-task-report-print-table thead{display:table-header-group!important}
	.crm-task-report-print-table tr{break-inside:avoid!important;page-break-inside:avoid!important}
	.crm-task-report-print-table .crm-task-subtask-list{margin:0;border-left:0;padding-left:0}
	.crm-task-report-print-table .crm-task-subtask{padding:3px 0}
	.crm-task-report-print-table .crm-task-subtask+.crm-task-subtask{border-top:1px solid #e5eaf0}
	.crm-task-report-print-table .crm-task-subtask-meta,.crm-task-print-label{display:block;margin-top:2px;color:#64748b;font-size:7.5pt;font-weight:400}
	.crm-task-report-print-table .crm-task-print-date{white-space:nowrap!important}
	.crm-task-report-print-table .crm-task-group-title{font-size:8.2pt}
}
</style>
