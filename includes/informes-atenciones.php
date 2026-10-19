<?php
// Informe de atenciones: consulta de solo lectura con el alcance autorizado por área.
require_once BASE_PATH.'/includes/informes-atenciones-scope.php';

$iaa_esc=function($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$iaa_global=!empty($crm_informe_atenciones_global);
$iaa_areas=isset($crm_informe_atenciones_areas) && is_array($crm_informe_atenciones_areas) ? $crm_informe_atenciones_areas : array();
$iaa_area_ids=array();
foreach($iaa_areas as $iaa_area) { $iaa_area_ids[]=intval(isset($iaa_area['id_area']) ? $iaa_area['id_area'] : $iaa_area['id']); }
$iaa_area_ids=array_values(array_unique(array_filter($iaa_area_ids)));
if(!$iaa_global && count($iaa_area_ids)===0) { http_response_code(403); exit('No estás autorizado para consultar este informe.'); }

$iaa_scope=crm_informes_atenciones_scope_condition($iaa_area_ids,$iaa_global);
$iaa_detail_area_ids=isset($crm_informe_atenciones_detalle_area_ids) && is_array($crm_informe_atenciones_detalle_area_ids) ? array_values(array_unique(array_filter(array_map('intval',$crm_informe_atenciones_detalle_area_ids)))) : array();
$iaa_detail_scope=crm_informes_atenciones_scope_condition($iaa_detail_area_ids,$iaa_global);
$iaa_can_view_details=($iaa_global || count($iaa_detail_area_ids)>0);
$iaa_row_can_view_detail=function($row) use ($iaa_global,$iaa_detail_area_ids) {
	return $iaa_global || in_array(intval($row['id_area']),$iaa_detail_area_ids,true);
};
$iaa_all_areas=acceso_consulta("SELECT id_area, clave, nombre FROM area WHERE activo=1 ORDER BY nombre");
$iaa_area_options=array();
if($iaa_global)
{
	foreach($iaa_all_areas as $iaa_area) { $iaa_area_options[intval($iaa_area['id_area'])]=(string)$iaa_area['nombre']; }
}
else
{
	foreach($iaa_areas as $iaa_area) { $iaa_area_options[intval(isset($iaa_area['id_area']) ? $iaa_area['id_area'] : $iaa_area['id'])]=(string)$iaa_area['nombre']; }
}
asort($iaa_area_options,SORT_NATURAL|SORT_FLAG_CASE);
$iaa_show_area=$iaa_global;
$iaa_report_key='atenciones';
$iaa_report_title='Informe de atenciones';

$iaa_motivos=array(
	'consulta'=>'Consulta',
	'documentacion'=>'Documentación',
	'seguimiento'=>'Seguimiento',
	'tramite'=>'Trámite',
	'otro'=>'Otro'
);
$iaa_canales=array('web'=>'Web','presencial'=>'Presencial','whatsapp'=>'WhatsApp','redes'=>'Redes sociales');
$iaa_estados=array(
	'pendiente'=>'Pendiente',
	'abierta'=>'Abierta',
	'en_atencion'=>'En atención',
	'cerrada'=>'Cerrada',
	'derivada'=>'Derivada',
	'cancelada'=>'Cancelada'
);
$iaa_date_value=function($value) {
	$value=trim((string)$value);
	if($value==='' || $value==='0000-00-00' || $value==='0000-00-00 00:00:00') { return 'No registrada'; }
	$time=strtotime($value);
	return $time ? date(strlen($value)>10 ? 'd/m/Y H:i' : 'd/m/Y',$time) : 'No registrada';
};
$iaa_status_text=function($value) use ($iaa_estados) {
	$value=(string)$value;
	return isset($iaa_estados[$value]) ? $iaa_estados[$value] : ($value!=='' ? $value : 'Sin estado');
};
$iaa_motive_text=function($value) use ($iaa_motivos) {
	$value=(string)$value;
	return isset($iaa_motivos[$value]) ? $iaa_motivos[$value] : ($value!=='' ? ucfirst(str_replace('_',' ',$value)) : 'No informado');
};
$iaa_channel_text=function($value) use ($iaa_canales) {
	$value=(string)$value;
	return isset($iaa_canales[$value]) ? $iaa_canales[$value] : ($value!=='' ? ucfirst(str_replace('_',' ',$value)) : 'No informado');
};
$iaa_valid_date=function($value) {
	if($value==='') { return true; }
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) { return false; }
	$parts=explode('-',$value);
	return checkdate(intval($parts[1]),intval($parts[2]),intval($parts[0]));
};

$iaa_form=array(
	'q'=>isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string)$_GET['q']) : '',
	'area'=>isset($_GET['area']) && is_scalar($_GET['area']) ? trim((string)$_GET['area']) : '',
	'empleado'=>isset($_GET['empleado']) && is_scalar($_GET['empleado']) ? trim((string)$_GET['empleado']) : '',
	'estado'=>isset($_GET['estado']) && is_scalar($_GET['estado']) ? trim((string)$_GET['estado']) : '',
	'motivo'=>isset($_GET['motivo']) && is_scalar($_GET['motivo']) ? trim((string)$_GET['motivo']) : '',
	'desde'=>isset($_GET['desde']) && is_scalar($_GET['desde']) ? trim((string)$_GET['desde']) : '',
	'hasta'=>isset($_GET['hasta']) && is_scalar($_GET['hasta']) ? trim((string)$_GET['hasta']) : ''
);
if(!$iaa_global) { $iaa_form['area']=''; }
if(function_exists('mb_substr')) { $iaa_form['q']=mb_substr($iaa_form['q'],0,150,'UTF-8'); }
else { $iaa_form['q']=substr($iaa_form['q'],0,150); }
$iaa_errors=array();
if(!$iaa_valid_date($iaa_form['desde'])) { $iaa_errors[]='La fecha desde no es válida.'; $iaa_form['desde']=''; }
if(!$iaa_valid_date($iaa_form['hasta'])) { $iaa_errors[]='La fecha hasta no es válida.'; $iaa_form['hasta']=''; }
if($iaa_form['desde']!=='' && $iaa_form['hasta']!=='' && $iaa_form['desde']>$iaa_form['hasta']) { $iaa_errors[]='La fecha desde no puede ser posterior a la fecha hasta.'; }
if($iaa_form['estado']!=='' && !isset($iaa_estados[$iaa_form['estado']])) { $iaa_form['estado']=''; }
if($iaa_form['motivo']!=='' && !isset($iaa_motivos[$iaa_form['motivo']])) { $iaa_form['motivo']=''; }

$iaa_context_id=0;
if(!$iaa_global && isset($crm_informe_atenciones_contexto,$iaa_areas[$crm_informe_atenciones_contexto]))
{
	$iaa_context_id=intval(isset($iaa_areas[$crm_informe_atenciones_contexto]['id_area']) ? $iaa_areas[$crm_informe_atenciones_contexto]['id_area'] : $iaa_areas[$crm_informe_atenciones_contexto]['id']);
}
$iaa_area_filter_id=$iaa_global ? 0 : $iaa_context_id;
if($iaa_global && $iaa_form['area']!=='')
{
	$candidate=intval($iaa_form['area']);
	if(isset($iaa_area_options[$candidate]) && (string)$candidate===$iaa_form['area']) { $iaa_area_filter_id=$candidate; }
	else
	{
		$iaa_errors[]='El área seleccionada no está disponible para tu perfil.';
		$iaa_form['area']='';
	}
}

$iaa_employee_sql="SELECT DISTINCT e.id_empleado, TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))) AS nombre
	FROM atencion at INNER JOIN empleado e ON e.id_empleado=at.id_empleado
	WHERE ".$iaa_scope['sql'];
$iaa_employee_types=$iaa_scope['types'];
$iaa_employee_params=$iaa_scope['params'];
if($iaa_area_filter_id>0)
{
	$iaa_employee_sql.=' AND at.id_area=?';
	$iaa_employee_types.='i';
	$iaa_employee_params[]=$iaa_area_filter_id;
}
$iaa_employee_sql.=' ORDER BY nombre';
$iaa_employee_rows=acceso_consulta($iaa_employee_sql,$iaa_employee_types,$iaa_employee_params);
$iaa_employees=array();
foreach($iaa_employee_rows as $iaa_employee) { $iaa_employees[intval($iaa_employee['id_empleado'])]=trim((string)$iaa_employee['nombre']); }
if($iaa_form['empleado']!=='' && (!ctype_digit($iaa_form['empleado']) || !isset($iaa_employees[intval($iaa_form['empleado'])])))
{
	$iaa_errors[]='El empleado seleccionado no está disponible para tu alcance.';
	$iaa_form['empleado']='';
}

$iaa_base_sql=" FROM atencion at
	INNER JOIN cliente c ON c.id_cliente=at.id_cliente
	INNER JOIN area a ON a.id_area=at.id_area
	LEFT JOIN area ao ON ao.id_area=at.id_area_origen
	LEFT JOIN empleado e ON e.id_empleado=at.id_empleado
	LEFT JOIN empleado eo ON eo.id_empleado=at.id_empleado_origen";
$iaa_build_query=function($detail_id=0,$detail_only=false) use (&$iaa_form,$iaa_scope,$iaa_detail_scope,$iaa_area_filter_id,$iaa_base_sql) {
	$scope=$detail_only ? $iaa_detail_scope : $iaa_scope;
	$where=array($scope['sql']);
	$types=$scope['types'];
	$params=$scope['params'];
	if($iaa_area_filter_id>0)
	{
		$where[]='at.id_area=?';
		$types.='i';
		$params[]=$iaa_area_filter_id;
	}
	if($iaa_form['q']!=='')
	{
		$like='%'.$iaa_form['q'].'%';
		$search="(c.nombre LIKE ? OR c.correo LIKE ? OR c.cuil_cuit LIKE ? OR at.motivo LIKE ? OR at.canal LIKE ? OR IFNULL(at.detalle,'') LIKE ? OR IFNULL(a.nombre,'') LIKE ? OR IFNULL(ao.nombre,'') LIKE ? OR IFNULL(e.nombres,'') LIKE ? OR IFNULL(e.apellido,'') LIKE ? OR IFNULL(eo.nombres,'') LIKE ? OR IFNULL(eo.apellido,'') LIKE ?";
		for($i=0;$i<12;$i++) { $types.='s'; $params[]=$like; }
		if(function_exists('acceso_tabla_existe') && acceso_tabla_existe('atencion_seguimiento'))
		{
			$search.=" OR EXISTS (SELECT 1 FROM atencion_seguimiento sx WHERE sx.id_atencion=at.id_atencion AND IFNULL(sx.observacion,'') LIKE ?)";
			$types.='s';
			$params[]=$like;
		}
		$where[]=$search.')';
	}
	if($iaa_form['empleado']!=='') { $where[]='at.id_empleado=?'; $types.='i'; $params[]=intval($iaa_form['empleado']); }
	if($iaa_form['estado']!=='') { $where[]='at.estado=?'; $types.='s'; $params[]=$iaa_form['estado']; }
	if($iaa_form['motivo']!=='') { $where[]='at.motivo=?'; $types.='s'; $params[]=$iaa_form['motivo']; }
	if($iaa_form['desde']!=='') { $where[]='COALESCE(at.fecha_turno,at.fecha)>=?'; $types.='s'; $params[]=$iaa_form['desde'].' 00:00:00'; }
	if($iaa_form['hasta']!=='') { $where[]='COALESCE(at.fecha_turno,at.fecha)<?'; $types.='s'; $params[]=date('Y-m-d H:i:s',strtotime($iaa_form['hasta'].' +1 day')); }
	if(intval($detail_id)>0) { $where[]='at.id_atencion=?'; $types.='i'; $params[]=intval($detail_id); }
	return array('sql'=>' WHERE '.implode(' AND ',$where),'types'=>$types,'params'=>$params);
};
$iaa_fetch=function($detail_id=0,$detail_only=false) use ($iaa_build_query,$iaa_base_sql) {
	$built=$iaa_build_query($detail_id,$detail_only);
	$sql="SELECT at.id_atencion, at.id_cliente, at.id_area, at.id_area_origen, at.id_empleado, at.id_empleado_origen,
		at.id_atencion_origen, at.motivo, at.canal, at.detalle, at.estado, at.fecha, at.fecha_turno, at.fecha_derivacion,
		c.nombre AS cliente_nombre,
		a.clave AS area_clave, a.nombre AS area_nombre, ao.clave AS area_origen_clave, ao.nombre AS area_origen_nombre,
		TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))) AS responsable_nombre,
		TRIM(CONCAT(IFNULL(eo.nombres,''),' ',IFNULL(eo.apellido,''))) AS empleado_origen_nombre".$iaa_base_sql.$built['sql'].
		" ORDER BY COALESCE(at.fecha_turno,at.fecha) DESC, at.id_atencion DESC";
	if(intval($detail_id)>0) { $sql.=' LIMIT 1'; }
	return acceso_consulta($sql,$built['types'],$built['params']);
};

$iaa_sheet=function($row,$class_name='attention-sheet') use ($iaa_esc,$iaa_date_value,$iaa_status_text,$iaa_motive_text,$iaa_channel_text) {
	$id=intval($row['id_atencion']);
	$history=(function_exists('crm_atencion_seguimiento_historial') && (!function_exists('acceso_tabla_existe') || acceso_tabla_existe('atencion_seguimiento'))) ? crm_atencion_seguimiento_historial($id) : array();
	$tasks=array();
	if(function_exists('crm_tareas_de_atencion') && function_exists('tarea_puede_ver'))
	{
		foreach(crm_tareas_de_atencion($id) as $task)
		{
			if(tarea_puede_ver($task)) { $tasks[]=$task; }
		}
	}
	$html='<article class="'.htmlspecialchars($class_name,ENT_QUOTES,'UTF-8').'" data-attention-sheet>';
	$html.='<header class="attention-sheet-head"><div><div class="attention-institution">ADEMI · ATENCIONES</div><h1>Ficha de atención #'.$id.'</h1></div><div class="attention-issued">Emitida el '.date('d/m/Y').'</div></header>';
	$html.='<section class="attention-section"><h2>Información general</h2><div class="attention-grid">';
	$html.='<div><span class="attention-label">Estado</span><strong>'.htmlspecialchars($iaa_status_text($row['estado']),ENT_QUOTES,'UTF-8').'</strong></div>';
	$html.='<div><span class="attention-label">Tipo / motivo</span>'.htmlspecialchars($iaa_motive_text($row['motivo']),ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Canal</span>'.htmlspecialchars($iaa_channel_text($row['canal']),ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Registrada</span>'.htmlspecialchars($iaa_date_value($row['fecha']),ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Fecha y hora de atención / turno</span>'.htmlspecialchars($iaa_date_value($row['fecha_turno']!==null && $row['fecha_turno']!=='' ? $row['fecha_turno'] : $row['fecha']),ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Fecha de derivación</span>'.htmlspecialchars($iaa_date_value($row['fecha_derivacion']),ENT_QUOTES,'UTF-8').'</div></div></section>';
	$html.='<section class="attention-section"><h2>Cliente y responsables</h2><div class="attention-grid">';
	$html.='<div><span class="attention-label">Cliente</span>'.htmlspecialchars(trim((string)$row['cliente_nombre'])!=='' ? $row['cliente_nombre'] : 'No registrado',ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Área responsable</span>'.htmlspecialchars($row['area_nombre'],ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Empleado responsable</span>'.htmlspecialchars(trim((string)$row['responsable_nombre'])!=='' ? $row['responsable_nombre'] : 'Sin asignar',ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Área de origen</span>'.htmlspecialchars(trim((string)$row['area_origen_nombre'])!=='' ? $row['area_origen_nombre'] : 'Sin derivación de origen',ENT_QUOTES,'UTF-8').'</div>';
	$html.='<div><span class="attention-label">Empleado de origen</span>'.htmlspecialchars(trim((string)$row['empleado_origen_nombre'])!=='' ? $row['empleado_origen_nombre'] : 'No registrado',ENT_QUOTES,'UTF-8').'</div></div></section>';
	$html.='<section class="attention-section"><h2>Motivo y observaciones</h2><div class="attention-text">'.(trim((string)$row['detalle'])!=='' ? nl2br(htmlspecialchars((string)$row['detalle'],ENT_QUOTES,'UTF-8')) : '<span class="attention-empty">Sin observaciones registradas.</span>').'</div></section>';
	if(count($history)>0)
	{
		$html.='<section class="attention-section"><h2>Seguimiento</h2><div class="attention-history">';
		foreach($history as $item)
		{
			$html.='<div class="attention-history-item"><div class="attention-history-meta"><strong>'.htmlspecialchars($iaa_date_value($item['fecha']),ENT_QUOTES,'UTF-8').'</strong><span>'.htmlspecialchars(trim((string)$item['area_nombre'].' · '.(string)$item['empleado_nombre'],' ·')!=='' ? trim((string)$item['area_nombre'].' · '.(string)$item['empleado_nombre'],' ·') : 'Seguimiento',ENT_QUOTES,'UTF-8').'</span></div><div>'.htmlspecialchars($item['observacion'],ENT_QUOTES,'UTF-8').'</div></div>';
		}
		$html.='</div></section>';
	}
	if(count($tasks)>0)
	{
		$html.='<section class="attention-section"><h2>Tareas relacionadas autorizadas</h2><div class="attention-history">';
		foreach($tasks as $task) { $html.='<div class="attention-history-item"><strong>'.htmlspecialchars($task['titulo'],ENT_QUOTES,'UTF-8').'</strong><div>'.htmlspecialchars(trim((string)$task['area_clave'].' · '.(string)$task['estado'],' ·'),ENT_QUOTES,'UTF-8').'</div></div>'; }
		$html.='</div></section>';
	}
	$html.='<footer class="attention-sheet-footer">ADEMI · Documento de consulta interna</footer></article>';
	return $html;
};
$iaa_sheet_styles='<style>@page{size:A4 portrait;margin:12mm}html,body{margin:0;padding:0;background:#f3f6fa;color:#22364d;font-family:Arial,sans-serif;font-size:14px;line-height:1.45}.attention-sheet{box-sizing:border-box;width:min(820px,100%);margin:22px auto;padding:24px 28px;background:#fff;border:1px solid #dbe3ed;border-radius:10px;box-shadow:0 4px 18px rgba(30,50,75,.08)}.attention-sheet-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:0 0 16px;margin:0 0 14px;border-bottom:2px solid #dbeafe}.attention-institution{font-size:11px;letter-spacing:.12em;font-weight:700;color:#2563eb}.attention-sheet-head h1{font-size:23px;line-height:1.2;margin:5px 0 0;color:#20364f}.attention-issued{font-size:11px;color:#64748b;white-space:nowrap}.attention-section{margin:0 0 14px;break-inside:avoid;page-break-inside:avoid}.attention-section h2{font-size:14px;margin:0 0 8px;padding-bottom:5px;border-bottom:1px solid #e2e8f0;color:#29445f}.attention-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px 20px}.attention-label{display:block;margin-bottom:2px;color:#64748b;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.attention-text{white-space:pre-wrap;overflow-wrap:anywhere}.attention-empty{color:#64748b;font-style:italic}.attention-history-item{padding:7px 0;border-bottom:1px solid #edf1f5;break-inside:avoid;page-break-inside:avoid}.attention-history-item:last-child{border-bottom:0}.attention-history-meta{display:flex;justify-content:space-between;gap:12px;margin-bottom:3px;color:#64748b;font-size:11px}.attention-sheet-footer{margin-top:16px;padding-top:7px;border-top:1px solid #dbe3ed;text-align:center;color:#64748b;font-size:10px}@media(max-width:600px){.attention-sheet{margin:10px;padding:17px 15px}.attention-sheet-head{flex-wrap:wrap}.attention-grid{grid-template-columns:1fr}}@media print{html,body{background:#fff!important;color:#1f2937!important;font-size:9pt!important}.attention-sheet{width:auto;max-width:none;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.attention-sheet-head{padding-bottom:4mm;margin-bottom:4mm}.attention-sheet-head h1{font-size:16pt}.attention-institution{font-size:8.5pt}.attention-issued{font-size:8pt}.attention-section{margin-bottom:4mm}.attention-section h2{font-size:10pt}.attention-grid{gap:2.5mm 6mm}.attention-label{font-size:7.5pt}.attention-sheet-footer{font-size:7.5pt;margin-top:5mm}}</style>';

$iaa_detail_requested=isset($_GET['atencion_ficha']) && is_scalar($_GET['atencion_ficha']) && (string)$_GET['atencion_ficha']!=='0';
$iaa_detail=$iaa_detail_requested && ctype_digit((string)$_GET['atencion_ficha']) ? intval($_GET['atencion_ficha']) : 0;
if($iaa_detail_requested && $iaa_detail<=0) { http_response_code(404); exit('No se encontró la atención o no está disponible para tu perfil.'); }
if($iaa_detail>0)
{
	if(!$iaa_can_view_details) { http_response_code(403); exit('No estás autorizado para consultar el detalle de esta atención.'); }
	$detail_rows=$iaa_fetch($iaa_detail,true);
	if(count($detail_rows)!==1) { http_response_code(404); exit('No se encontró la atención o no está disponible para tu perfil.'); }
	$iaa_detail_html=$iaa_sheet($detail_rows[0]);
	echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ficha de atención</title>'.$iaa_sheet_styles.'</head><body>'.$iaa_detail_html.'</body></html>';
	exit();
}

$iaa_where=$iaa_build_query();
$iaa_count=0;
$iaa_rows=array();
if(count($iaa_errors)===0)
{
	$count_row=acceso_consulta_una('SELECT COUNT(*) AS total'.$iaa_base_sql.$iaa_where['sql'],$iaa_where['types'],$iaa_where['params']);
	$iaa_count=$count_row ? intval($count_row['total']) : 0;
	$iaa_rows=$iaa_fetch();
}
$iaa_total=count($iaa_rows);
$iaa_applied=array();
if($iaa_form['q']!=='') { $iaa_applied[]='Búsqueda: '.$iaa_form['q']; }
if($iaa_area_filter_id>0 && isset($iaa_area_options[$iaa_area_filter_id])) { $iaa_applied[]='Área: '.$iaa_area_options[$iaa_area_filter_id]; }
if($iaa_form['empleado']!=='') { $iaa_applied[]='Empleado: '.$iaa_employees[intval($iaa_form['empleado'])]; }
if($iaa_form['estado']!=='') { $iaa_applied[]='Estado: '.$iaa_estados[$iaa_form['estado']]; }
if($iaa_form['motivo']!=='') { $iaa_applied[]='Tipo: '.$iaa_motivos[$iaa_form['motivo']]; }
if($iaa_form['desde']!=='') { $iaa_applied[]='Desde: '.$iaa_date_value($iaa_form['desde']); }
if($iaa_form['hasta']!=='') { $iaa_applied[]='Hasta: '.$iaa_date_value($iaa_form['hasta']); }
$iaa_emitted=date('d/m/Y H:i');
$iaa_filters_text=count($iaa_applied) ? implode(' · ',$iaa_applied) : 'Todas las atenciones autorizadas';

$iaa_result_html=function() use ($iaa_esc,$iaa_rows,$iaa_total,$iaa_count,$iaa_errors,$iaa_emitted,$iaa_filters_text,$iaa_status_text,$iaa_motive_text,$iaa_channel_text,$iaa_date_value,$iaa_form,$iaa_global,$iaa_area_filter_id,$iaa_area_options,$iaa_can_view_details,$iaa_row_can_view_detail,$iaa_report_title,$iaa_report_key) {
	?><div id="rrhh-report-async-response" class="rrhh-report-async-response" data-total="<?php echo intval($iaa_total); ?>" data-count="<?php echo intval($iaa_count); ?>">
	<?php foreach($iaa_errors as $error) { ?><div class="alert alert-danger rrhh-report-async-error"><?php echo $iaa_esc($error); ?></div><?php } ?>
	<header class="rrhh-report-print-only rrhh-report-print-header"><div class="institution">ADEMI · INFORME INSTITUCIONAL</div><h1><?php echo $iaa_esc($iaa_report_title); ?></h1><div class="rrhh-report-meta">Emitido el <?php echo $iaa_esc($iaa_emitted); ?></div><div class="rrhh-report-filters-print">Filtros: <?php echo $iaa_esc($iaa_filters_text); ?></div></header>
	<div class="grid simple rrhh-report-grid crm-attention-report-grid"><div class="grid-title rrhh-report-results-heading"><h4>Resultados <span class="rrhh-report-total-count">Total de atenciones: <?php echo intval($iaa_total); ?></span></h4><div class="rrhh-report-result-actions no-print"><button type="button" class="btn btn-white" data-print-list><i class="fa fa-print"></i> Imprimir informe</button></div></div>
	<div class="grid-body"><div class="table-responsive"><table class="table table-bordered table-hover rrhh-report-table crm-attention-report-table"><thead><tr><th>Fecha</th><th>Cliente</th><th>Área responsable</th><th>Responsable</th><th>Tipo / motivo</th><th>Estado</th></tr></thead><tbody>
	<?php if($iaa_total===0) { ?><tr><td colspan="6" class="rrhh-report-empty">No se encontraron atenciones con los criterios seleccionados.</td></tr><?php } else { foreach($iaa_rows as $row) { $datetime=isset($row['fecha_turno']) && $row['fecha_turno']!==null && $row['fecha_turno']!=='' ? $row['fecha_turno'] : $row['fecha']; $can_open=$iaa_row_can_view_detail($row); $href=url_crm('informes.php?reporte='.$iaa_report_key.'&atencion_ficha='.intval($row['id_atencion'])); if($iaa_global && $iaa_area_filter_id>0) { $href.='&area='.intval($iaa_area_filter_id); } ?>
	<tr<?php if($can_open) { ?> class="rrhh-report-row-link" tabindex="0" role="button" data-report-open data-href="<?php echo $iaa_esc($href); ?>" aria-haspopup="dialog" aria-label="Abrir ficha de atención #<?php echo intval($row['id_atencion']); ?>"<?php } ?>><td><?php echo $iaa_esc($iaa_date_value($datetime)); ?></td><td><?php echo $iaa_esc($row['cliente_nombre']); ?></td><td><?php echo $iaa_esc($row['area_nombre']); ?></td><td><?php echo $iaa_esc(trim((string)$row['responsable_nombre'])!=='' ? $row['responsable_nombre'] : 'Sin asignar'); ?></td><td><?php echo $iaa_esc($iaa_motive_text($row['motivo'])); ?><span class="crm-attention-channel-label"><?php echo $iaa_esc($iaa_channel_text($row['canal'])); ?></span></td><td><?php echo $iaa_esc($iaa_status_text($row['estado'])); ?></td></tr>
	<?php } } ?></tbody></table></div><div class="rrhh-report-print-only rrhh-report-print-footer">ADEMI · Informe interno de atenciones · Registros: <?php echo intval($iaa_total); ?></div></div></div></div><?php
};
if(!empty($ajax_resultados)) { $iaa_result_html(); exit(); }
?>
<div class="rrhh-report-wrap crm-attention-report" data-attention-report>
<form method="get" action="<?php echo $iaa_esc(url_crm('informes.php')); ?>" class="rrhh-report-filters" data-report-filters data-default-area="<?php echo intval($iaa_context_id); ?>">
	<input type="hidden" name="reporte" value="<?php echo $iaa_esc($iaa_report_key); ?>">
	<div class="grid simple rrhh-report-grid no-print"><div class="grid-title no-border"><h4><i class="fa fa-filter"></i> Filtros</h4></div><div class="grid-body no-border"><div class="rrhh-report-filters-grid crm-attention-filters-grid <?php echo $iaa_show_area ? 'crm-attention-filters-has-area' : 'crm-attention-filters-single-area'; ?>">
	<div class="form-group"><label for="attention-report-q">Buscar atención</label><input id="attention-report-q" name="q" type="search" class="form-control" maxlength="150" value="<?php echo $iaa_esc($iaa_form['q']); ?>" placeholder="Cliente, motivo u observación"></div>
	<?php if($iaa_show_area) { ?><div class="form-group"><label for="attention-report-area">Área</label><select id="attention-report-area" name="area" class="form-control"><?php if($iaa_global) { ?><option value="" <?php echo $iaa_form['area']==='' ? 'selected' : ''; ?>>Todas las áreas</option><?php } ?><?php foreach($iaa_area_options as $area_id=>$area_name) { ?><option value="<?php echo intval($area_id); ?>" <?php echo (string)$area_id===$iaa_form['area'] ? 'selected' : ''; ?>><?php echo $iaa_esc($area_name); ?></option><?php } ?></select></div><?php } ?>
	<div class="form-group"><label for="attention-report-employee">Empleado responsable</label><select id="attention-report-employee" name="empleado" class="form-control"><option value="">Todos</option><?php foreach($iaa_employees as $employee_id=>$employee_name) { ?><option value="<?php echo intval($employee_id); ?>" <?php echo (string)$employee_id===$iaa_form['empleado'] ? 'selected' : ''; ?>><?php echo $iaa_esc($employee_name); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="attention-report-state">Estado</label><select id="attention-report-state" name="estado" class="form-control"><option value="">Todos</option><?php foreach($iaa_estados as $state=>$state_label) { ?><option value="<?php echo $iaa_esc($state); ?>" <?php echo $iaa_form['estado']===$state ? 'selected' : ''; ?>><?php echo $iaa_esc($state_label); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="attention-report-reason">Tipo de atención</label><select id="attention-report-reason" name="motivo" class="form-control"><option value="">Todos</option><?php foreach($iaa_motivos as $reason=>$reason_label) { ?><option value="<?php echo $iaa_esc($reason); ?>" <?php echo $iaa_form['motivo']===$reason ? 'selected' : ''; ?>><?php echo $iaa_esc($reason_label); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="attention-report-from">Fecha desde</label><input id="attention-report-from" name="desde" type="date" class="form-control" value="<?php echo $iaa_esc($iaa_form['desde']); ?>"></div>
	<div class="form-group"><label for="attention-report-to">Fecha hasta</label><input id="attention-report-to" name="hasta" type="date" class="form-control" value="<?php echo $iaa_esc($iaa_form['hasta']); ?>"></div>
	<div class="rrhh-report-actions"><button type="button" class="btn btn-white" data-report-clear>Limpiar</button></div>
	</div></div></div>
</form>
<div id="rrhh-report-fetch-error" class="alert alert-danger rrhh-report-async-error" role="alert" hidden></div>
<?php $iaa_result_html(); ?>
</div>
<div class="rrhh-report-modal no-print" id="crm-attention-modal" role="dialog" aria-modal="true" aria-labelledby="crm-attention-modal-title" hidden><div class="rrhh-report-modal-dialog"><div class="rrhh-report-modal-head"><h2 id="crm-attention-modal-title">Ficha de atención</h2><button type="button" class="btn btn-white btn-sm" data-attention-close aria-label="Cerrar ficha"><i class="fa fa-times"></i></button></div><div class="rrhh-report-modal-body"><div class="rrhh-report-modal-loading" data-attention-loading role="status">Cargando ficha…</div><iframe class="rrhh-report-modal-frame" data-attention-frame title="Ficha de atención" src="about:blank"></iframe></div><div class="rrhh-report-modal-foot"><p class="rrhh-report-modal-hint">Para guardar como PDF, elegí esa opción en el diálogo de impresión del navegador.</p><button type="button" class="btn btn-white" data-attention-print><i class="fa fa-print"></i> Imprimir</button><button type="button" class="btn btn-primary" data-attention-pdf><i class="fa fa-file-pdf-o"></i> Guardar como PDF</button><button type="button" class="btn btn-white" data-attention-close>Cerrar</button></div></div></div>
<script>
(function(){
var form=document.querySelector('[data-attention-report] [data-report-filters]'),modal=document.getElementById('crm-attention-modal'),frame=document.querySelector('[data-attention-frame]'),loader=document.querySelector('[data-attention-loading]'),timer=null,seq=0,controller=null,bodyOverflow='',returnFocus=null;
if(modal&&modal.parentNode!==document.body){document.body.appendChild(modal);}
function root(){return document.getElementById('rrhh-report-async-response');}
function url(ajax){var u=new URL(form.action,location.href),p=new URLSearchParams(new FormData(form));if(ajax){p.set('ajax','resultados');}u.search=p.toString();return u;}
function buttons(disabled){var total=root()?parseInt(root().getAttribute('data-total'),10):0;var button=document.querySelector('[data-print-list]');if(button){button.disabled=disabled||!total;}}
function refresh(){if(controller){controller.abort();}controller=typeof AbortController!=='undefined'?new AbortController():null;var n=++seq;form.setAttribute('aria-busy','true');buttons(true);var opt={credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}};if(controller){opt.signal=controller.signal;}return fetch(url(true).toString(),opt).then(function(r){if(!r.ok){throw new Error();}return r.text();}).then(function(html){if(n!==seq){return false;}var doc=new DOMParser().parseFromString(html,'text/html'),next=doc.getElementById('rrhh-report-async-response'),old=root();if(!next||!old){throw new Error();}old.replaceWith(document.importNode(next,true));var err=document.getElementById('rrhh-report-fetch-error');if(err){err.hidden=true;}var address=url(false);history.replaceState({},'',address.pathname+address.search);return true;}).catch(function(e){if(n===seq&&e.name!=='AbortError'){var err=document.getElementById('rrhh-report-fetch-error');if(err){err.textContent='No se pudieron actualizar los resultados. Revisá tu conexión e intentá nuevamente.';err.hidden=false;}return false;}return false;}).then(function(ok){if(n===seq){form.removeAttribute('aria-busy');buttons(false);}return ok;});}
form.addEventListener('submit',function(e){e.preventDefault();refresh();});var search=form.querySelector('[name=q]');search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(refresh,350);});form.querySelectorAll('select,input[type=date]').forEach(function(el){el.addEventListener('change',function(){clearTimeout(timer);timer=setTimeout(refresh,0);});});form.querySelector('[data-report-clear]').addEventListener('click',function(){form.reset();var area=form.querySelector('[name=area]'),defaultArea=form.getAttribute('data-default-area');if(area&&defaultArea&&Array.prototype.some.call(area.options,function(option){return option.value===defaultArea;})){area.value=defaultArea;}search.focus();refresh();});
document.addEventListener('click',function(e){var b=e.target.closest('[data-print-list]');if(b){e.preventDefault();refresh().then(function(ok){if(ok&&root()&&parseInt(root().getAttribute('data-total'),10)>0){window.print();}});return;}var row=e.target.closest('[data-report-open][data-href]');if(row){e.preventDefault();returnFocus=row;document.getElementById('crm-attention-modal-title').textContent='Ficha de atención #'+row.getAttribute('aria-label').replace(/^.*#/, '').trim();modal.hidden=false;bodyOverflow=document.body.style.overflow;document.body.style.overflow='hidden';loader.hidden=false;frame.src=row.getAttribute('data-href');var closeButton=modal.querySelector('[data-attention-close]');if(closeButton){closeButton.focus();}return;}if(e.target.closest('[data-attention-close]')){close();return;}if(e.target.closest('[data-attention-print],[data-attention-pdf]')){e.preventDefault();try{frame.contentWindow.focus();frame.contentWindow.print();}catch(err){loader.textContent='No se pudo abrir el diálogo de impresión. Volvé a intentar cuando la ficha termine de cargar.';loader.hidden=false;}}});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal&&!modal.hidden){close();return;}var row=e.target.closest&&e.target.closest('[data-report-open][data-href]');if(row&&(e.key==='Enter'||e.key===' ')){e.preventDefault();row.click();}});
function close(){if(!modal||modal.hidden){return;}modal.hidden=true;frame.src='about:blank';document.body.style.overflow=bodyOverflow;if(returnFocus&&document.contains(returnFocus)){returnFocus.focus();}returnFocus=null;}
frame.addEventListener('load',function(){if(modal.hidden||frame.getAttribute('src')==='about:blank'){return;}try{var doc=frame.contentDocument;if(doc.querySelector('[data-attention-sheet]')){loader.hidden=true;doc.addEventListener('keydown',function(event){if(event.key==='Escape'){event.preventDefault();close();}});return;}}catch(e){}loader.textContent='No se pudo cargar la ficha. Verificá que tu sesión siga activa y tengas autorización para consultar esta atención.';loader.hidden=false;});buttons(false);
})();
</script>
<style>
.crm-attention-filters-grid{gap:8px;align-items:end}
.crm-attention-filters-has-area{grid-template-columns:minmax(165px,1.4fr) minmax(125px,1.05fr) minmax(120px,1fr) minmax(105px,.85fr) minmax(120px,.95fr) minmax(125px,1fr) minmax(125px,1fr) max-content}
.crm-attention-filters-single-area{grid-template-columns:minmax(180px,1.5fr) minmax(135px,1.1fr) minmax(120px,.95fr) minmax(135px,1fr) minmax(135px,1fr) minmax(135px,1fr) max-content}
.crm-attention-report-grid .grid-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.crm-attention-report-grid .grid-title h4{margin:0}
.crm-attention-channel-label{display:block;margin-top:3px;color:#64748b;font-size:11px}
.crm-attention-report-table th:nth-child(1){width:16%}.crm-attention-report-table th:nth-child(2){width:22%}.crm-attention-report-table th:nth-child(3){width:17%}.crm-attention-report-table th:nth-child(4){width:17%}.crm-attention-report-table th:nth-child(5){width:15%}.crm-attention-report-table th:nth-child(6){width:13%}
@media(max-width:1200px){.crm-attention-filters-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:767px){.crm-attention-filters-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.crm-attention-filters-grid .rrhh-report-actions{grid-column:span 2}}
@media print{.crm-attention-report-grid>.grid-title{display:none!important}.crm-attention-report-table{width:100%!important;max-width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;font-size:7.8pt!important}.crm-attention-report-table th,.crm-attention-report-table td{box-sizing:border-box!important;padding:3px 4px!important;line-height:1.18!important;white-space:normal!important;overflow-wrap:normal!important;word-break:normal!important;hyphens:none!important}.crm-attention-report-table th:nth-child(1){width:16%!important}.crm-attention-report-table th:nth-child(2){width:22%!important}.crm-attention-report-table th:nth-child(3){width:17%!important}.crm-attention-report-table th:nth-child(4){width:17%!important}.crm-attention-report-table th:nth-child(5){width:15%!important}.crm-attention-report-table th:nth-child(6){width:13%!important}.crm-attention-report-table thead{display:table-header-group!important}.crm-attention-report-table tr{break-inside:avoid!important;page-break-inside:avoid!important}}
</style>
