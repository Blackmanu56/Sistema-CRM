<?php
// Informe de actividades: usa los permisos de lectura de Actividades y el vínculo real evento_tarea.
require_once BASE_PATH.'/includes/actividades.php';

$ia_esc=function($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$ia_fecha=function($value) {
	$value=trim((string)$value);
	if($value==='' || $value==='0000-00-00') { return 'No registrada'; }
	$time=strtotime($value);
	return $time ? date('d/m/Y',$time) : 'No registrada';
};
$ia_tipo_texto=function($tipo) {
	if(function_exists('crm_portal_tipo_actividad')) { return crm_portal_tipo_actividad($tipo); }
	return ucfirst(str_replace('_',' ',trim((string)$tipo)));
};
$ia_estados=array(
	'proxima'=>'Próxima',
	'realizada'=>'Realizada',
	'pasada'=>'Pasada · sin registro de realización',
	'cancelada'=>'Cancelada'
);
$ia_global=!empty($crm_informe_actividades_global);
$ia_areas=isset($crm_informe_actividades_areas) && is_array($crm_informe_actividades_areas) ? $crm_informe_actividades_areas : array();
$ia_area_ids=array();
foreach($ia_areas as $ia_area) { $ia_area_ids[]=intval(isset($ia_area['id_area']) ? $ia_area['id_area'] : $ia_area['id']); }
$ia_area_ids=array_values(array_unique(array_filter($ia_area_ids)));
if(!$ia_global && count($ia_area_ids)===0) { http_response_code(403); exit('No estás autorizado para consultar este informe.'); }

$ia_scope_sql='';
if(!$ia_global)
{
	$ia_ids_sql=implode(',',array_map('intval',$ia_area_ids));
	$ia_scope_sql=' AND (ev.id_area IN ('.$ia_ids_sql.') OR EXISTS (
		SELECT 1 FROM evento_tarea etp INNER JOIN tarea tp ON tp.id_tarea=etp.id_tarea
		WHERE etp.id_evento=ev.id_evento AND tp.id_area IN ('.$ia_ids_sql.') AND tp.estado<>\'cancelada\'
	) OR EXISTS (
		SELECT 1 FROM tarea to2 WHERE to2.id_tarea=ev.id_tarea_origen
		AND to2.id_area IN ('.$ia_ids_sql.') AND to2.estado<>\'cancelada\'
	))';
}
$ia_participantes="(SELECT GROUP_CONCAT(DISTINCT ar.nombre ORDER BY ar.nombre SEPARATOR ', ') FROM evento_tarea etp2 INNER JOIN tarea tp2 ON tp2.id_tarea=etp2.id_tarea INNER JOIN area ar ON ar.id_area=tp2.id_area WHERE etp2.id_evento=ev.id_evento AND ar.id_area<>ev.id_area AND tp2.estado<>'cancelada')";
$ia_participante_ids="(SELECT GROUP_CONCAT(DISTINCT ar2.id_area ORDER BY ar2.nombre SEPARATOR ',') FROM evento_tarea etp3 INNER JOIN tarea tp3 ON tp3.id_tarea=etp3.id_tarea INNER JOIN area ar2 ON ar2.id_area=tp3.id_area WHERE etp3.id_evento=ev.id_evento AND ar2.id_area<>ev.id_area AND tp3.estado<>'cancelada')";
$ia_sql="SELECT ev.id_evento, ev.titulo, ev.descripcion, ev.tipo, ev.fecha, ev.id_area, ev.id_empleado,
	ev.organismo, ev.resultado, ev.activo,
	a.clave AS area_clave, a.nombre AS area_organizadora,
	CONCAT(TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')))) AS responsable,
	l.nombre AS localidad,
	".$ia_participantes." AS areas_participantes, ".$ia_participante_ids." AS areas_participantes_ids,
	(SELECT COUNT(*) FROM evento_inscripcion ei WHERE ei.id_evento=ev.id_evento) AS n_inscriptos,
	(SELECT COUNT(*) FROM evento_inscripcion ei WHERE ei.id_evento=ev.id_evento AND ei.estado='asistio') AS n_asistieron
	FROM evento ev INNER JOIN area a ON a.id_area=ev.id_area
	LEFT JOIN empleado e ON e.id_empleado=ev.id_empleado
	LEFT JOIN localidad l ON l.id_localidad=ev.id_localidad
	WHERE ev.activo IN (0,1)".$ia_scope_sql." ORDER BY ev.fecha DESC, ev.id_evento DESC";
$ia_candidates=acceso_consulta($ia_sql);
$ia_visible=array();
foreach($ia_candidates as $ia_row)
{
	if(actividad_puede_ver($ia_row))
	{
		$ia_row['estado_informe']=actividad_estado_lista($ia_row);
		$ia_row['tipo_texto']=$ia_tipo_texto($ia_row['tipo']);
		$ia_row['areas_participantes']=trim((string)$ia_row['areas_participantes']);
		$ia_row['participante_ids']=array_values(array_filter(array_map('intval',explode(',',(string)$ia_row['areas_participantes_ids']))));
		$ia_visible[]=$ia_row;
	}
}

$ia_organizadoras=array();
$ia_participantes_areas=array();
foreach($ia_visible as $ia_row)
{
	$id_org=intval($ia_row['id_area']);
	$ia_organizadoras[$id_org]=trim((string)$ia_row['area_organizadora']);
	if($ia_row['areas_participantes']!=='' && count($ia_row['participante_ids']))
	{
		$names=explode(', ',$ia_row['areas_participantes']);
		foreach($ia_row['participante_ids'] as $idx=>$id_part) { if(isset($names[$idx])) { $ia_participantes_areas[$id_part]=trim($names[$idx]); } }
	}
}
asort($ia_organizadoras,SORT_NATURAL|SORT_FLAG_CASE);
asort($ia_participantes_areas,SORT_NATURAL|SORT_FLAG_CASE);
$ia_tipos_disponibles=array();
$ia_responsables=array();
foreach($ia_visible as $ia_row)
{
	$ia_tipos_disponibles[(string)$ia_row['tipo']]=$ia_row['tipo_texto'];
	if(intval($ia_row['id_empleado'])>0 && trim((string)$ia_row['responsable'])!=='') { $ia_responsables[intval($ia_row['id_empleado'])]=trim((string)$ia_row['responsable']); }
}
asort($ia_tipos_disponibles,SORT_NATURAL|SORT_FLAG_CASE);
asort($ia_responsables,SORT_NATURAL|SORT_FLAG_CASE);

$ia_form=array(
	'q'=>isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string)$_GET['q']) : '',
	'organizadora'=>isset($_GET['organizadora']) && is_scalar($_GET['organizadora']) ? trim((string)$_GET['organizadora']) : '',
	'participante'=>isset($_GET['participante']) && is_scalar($_GET['participante']) ? trim((string)$_GET['participante']) : '',
	'tipo'=>isset($_GET['tipo']) && is_scalar($_GET['tipo']) ? trim((string)$_GET['tipo']) : '',
	'estado'=>isset($_GET['estado']) && is_scalar($_GET['estado']) ? trim((string)$_GET['estado']) : '',
	'desde'=>isset($_GET['desde']) && is_scalar($_GET['desde']) ? trim((string)$_GET['desde']) : '',
	'hasta'=>isset($_GET['hasta']) && is_scalar($_GET['hasta']) ? trim((string)$_GET['hasta']) : '',
	'responsable'=>isset($_GET['responsable']) && is_scalar($_GET['responsable']) ? trim((string)$_GET['responsable']) : ''
);
if(function_exists('mb_substr')) { $ia_form['q']=mb_substr($ia_form['q'],0,150,'UTF-8'); } else { $ia_form['q']=substr($ia_form['q'],0,150); }
$ia_errors=array();
$ia_valid_date=function($value) { if($value==='') { return true; } if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) { return false; } $p=explode('-',$value); return checkdate(intval($p[1]),intval($p[2]),intval($p[0])); };
if(!$ia_valid_date($ia_form['desde'])) { $ia_errors[]='La fecha desde no es válida.'; $ia_form['desde']=''; }
if(!$ia_valid_date($ia_form['hasta'])) { $ia_errors[]='La fecha hasta no es válida.'; $ia_form['hasta']=''; }
if($ia_form['desde']!=='' && $ia_form['hasta']!=='' && $ia_form['desde']>$ia_form['hasta']) { $ia_errors[]='La fecha inicial no puede ser posterior a la final.'; }
if(!isset($ia_tipos_disponibles[$ia_form['tipo']])) { $ia_form['tipo']=''; }
if($ia_form['estado']!=='' && !isset($ia_estados[$ia_form['estado']])) { $ia_form['estado']=''; }
if($ia_form['organizadora']!=='' && !isset($ia_organizadoras[intval($ia_form['organizadora'])])) { $ia_form['organizadora']=''; }
if($ia_form['participante']!=='' && !isset($ia_participantes_areas[intval($ia_form['participante'])])) { $ia_form['participante']=''; }
if($ia_form['responsable']!=='' && !isset($ia_responsables[intval($ia_form['responsable'])])) { $ia_form['responsable']=''; }

$ia_match_text=function($needle,$haystack) {
	if(function_exists('mb_stripos')) { return mb_stripos((string)$haystack,(string)$needle,0,'UTF-8')!==false; }
	return stripos((string)$haystack,(string)$needle)!==false;
};
$ia_rows=array();
if(count($ia_errors)===0)
{
	foreach($ia_visible as $ia_row)
	{
		if($ia_form['q']!=='' && !$ia_match_text($ia_form['q'],$ia_row['titulo'])) { continue; }
		if($ia_form['organizadora']!=='' && intval($ia_form['organizadora'])!==intval($ia_row['id_area'])) { continue; }
		if($ia_form['participante']!=='' && !in_array(intval($ia_form['participante']),$ia_row['participante_ids'],true)) { continue; }
		if($ia_form['tipo']!=='' && $ia_form['tipo']!==$ia_row['tipo']) { continue; }
		if($ia_form['estado']!=='' && $ia_form['estado']!==$ia_row['estado_informe']) { continue; }
		$dia=substr((string)$ia_row['fecha'],0,10);
		if($ia_form['desde']!=='' && $dia<$ia_form['desde']) { continue; }
		if($ia_form['hasta']!=='' && $dia>$ia_form['hasta']) { continue; }
		if($ia_form['responsable']!=='' && intval($ia_form['responsable'])!==intval($ia_row['id_empleado'])) { continue; }
		$ia_rows[]=$ia_row;
	}
}
$ia_total=count($ia_rows);
$ia_applied=array();
if($ia_form['q']!=='') { $ia_applied[]='Búsqueda: '.$ia_form['q']; }
if($ia_form['organizadora']!=='') { $ia_applied[]='Área organizadora: '.$ia_organizadoras[intval($ia_form['organizadora'])]; }
if($ia_form['participante']!=='') { $ia_applied[]='Área participante: '.$ia_participantes_areas[intval($ia_form['participante'])]; }
if($ia_form['tipo']!=='') { $ia_applied[]='Tipo: '.$ia_tipos_disponibles[$ia_form['tipo']]; }
if($ia_form['estado']!=='') { $ia_applied[]='Estado: '.$ia_estados[$ia_form['estado']]; }
if($ia_form['desde']!=='') { $ia_applied[]='Desde: '.date('d/m/Y',strtotime($ia_form['desde'])); }
if($ia_form['hasta']!=='') { $ia_applied[]='Hasta: '.date('d/m/Y',strtotime($ia_form['hasta'])); }
if($ia_form['responsable']!=='') { $ia_applied[]='Responsable: '.$ia_responsables[intval($ia_form['responsable'])]; }
$ia_emitted=date('d/m/Y');

$actividad_ficha=isset($_GET['actividad_ficha']) && is_scalar($_GET['actividad_ficha']) && $_GET['actividad_ficha']!=='0';
$ia_ficha=null;
if($actividad_ficha)
{
	$id_ficha=filter_var($_GET['actividad_ficha'],FILTER_VALIDATE_INT);
	if(!$id_ficha || intval($id_ficha)<=0) { http_response_code(404); exit('La actividad solicitada no existe o no está autorizada.'); }
	foreach($ia_visible as $ia_row) { if(intval($ia_row['id_evento'])===intval($id_ficha)) { $ia_ficha=$ia_row; break; } }
	if(!$ia_ficha) { http_response_code(404); exit('La actividad solicitada no existe o no está autorizada.'); }
}

$ia_sheet=function($ia_row,$class_name='activity-sheet') use ($ia_esc,$ia_fecha,$ia_estados,$ia_emitted) {
	$status=isset($ia_estados[$ia_row['estado_informe']]) ? $ia_estados[$ia_row['estado_informe']] : 'No disponible';
	$participants=trim((string)$ia_row['areas_participantes']);
	if($participants==='') { $participants='Sin áreas participantes registradas'; }
	$details=array(
		'Tipo'=>$ia_row['tipo_texto'],
		'Área organizadora'=>$ia_row['area_organizadora'],
		'Áreas participantes'=>$participants,
		'Fecha'=>$ia_fecha($ia_row['fecha']),
		'Estado'=>$status,
		'Responsable'=>trim((string)$ia_row['responsable'])!=='' ? $ia_row['responsable'] : 'No asignado',
		'Localidad'=>trim((string)$ia_row['localidad'])!=='' ? $ia_row['localidad'] : 'No registrada',
		'Organismo'=>trim((string)$ia_row['organismo'])!=='' ? $ia_row['organismo'] : 'No registrado',
		'Inscripciones'=>intval($ia_row['n_inscriptos']),
		'Asistencias registradas'=>intval($ia_row['n_asistieron'])
	);
	$html='<article class="'.htmlspecialchars($class_name,ENT_QUOTES,'UTF-8').'" data-activity-sheet><header class="activity-sheet-head"><div><div class="activity-sheet-brand">ADEMI · INFORME INSTITUCIONAL</div><h1>'.$ia_esc($ia_row['titulo']).'</h1><p class="activity-sheet-subtitle">'.$ia_esc($ia_row['tipo_texto']).' · '.$ia_esc($status).'</p></div><div class="activity-sheet-emitted">Emitida el '.$ia_esc($ia_emitted).'</div></header>';
	$html.='<section class="activity-sheet-section"><h2>Organización y programación</h2><div class="activity-sheet-grid">';
	foreach($details as $label=>$value) { $html.='<div class="activity-sheet-item"><span class="activity-sheet-label">'.$ia_esc($label).'</span><div class="activity-sheet-value">'.$ia_esc($value).'</div></div>'; }
	$html.='</div></section><section class="activity-sheet-section"><h2>Descripción</h2><div class="activity-sheet-text">'.$ia_esc(trim((string)$ia_row['descripcion'])!=='' ? $ia_row['descripcion'] : 'Sin descripción registrada.').'</div></section>';
	if(trim((string)$ia_row['resultado'])!=='') { $html.='<section class="activity-sheet-section"><h2>Resultados</h2><div class="activity-sheet-text">'.$ia_esc($ia_row['resultado']).'</div></section>'; }
	$html.='<footer class="activity-sheet-footer">ADEMI · Ficha de actividad</footer></article>';
	return $html;
};

if($actividad_ficha)
{
	?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ficha de actividad | ADEMI</title><style>
@page{size:A4 portrait;margin:13mm 14mm}*{box-sizing:border-box}html,body{margin:0;background:#eef2f7;color:#1e293b;font:11pt/1.45 Arial,sans-serif}.activity-sheet{width:min(900px,100%);margin:20px auto;padding:26px;background:#fff;border:1px solid #d9e2ef;border-radius:10px;box-shadow:0 8px 26px rgba(15,23,42,.08)}.activity-sheet-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding-bottom:14px;border-bottom:2px solid #2563eb}.activity-sheet-brand{font-size:9pt;letter-spacing:.12em;font-weight:700;color:#2563eb}.activity-sheet h1{margin:5px 0;font-size:21pt;color:#17365d}.activity-sheet-subtitle,.activity-sheet-emitted{margin:0;color:#64748b;font-size:9pt}.activity-sheet-emitted{white-space:nowrap}.activity-sheet-section{margin-top:18px}.activity-sheet-section h2{margin:0 0 9px;padding-bottom:5px;border-bottom:1px solid #dce5f0;font-size:12pt;color:#1e3a5f}.activity-sheet-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px}.activity-sheet-item{min-width:0}.activity-sheet-label{display:block;margin-bottom:2px;color:#64748b;font-size:8pt;letter-spacing:.06em;text-transform:uppercase}.activity-sheet-value,.activity-sheet-text{white-space:pre-wrap;overflow-wrap:anywhere;color:#23364d}.activity-sheet-footer{margin-top:22px;padding-top:7px;border-top:1px solid #dce5f0;text-align:center;color:#64748b;font-size:8pt}@media(max-width:600px){.activity-sheet{margin:0;padding:18px;border-radius:0}.activity-sheet-head{flex-direction:column;gap:6px}.activity-sheet-grid{grid-template-columns:1fr}}@media print{html,body{background:#fff}.activity-sheet{width:100%;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.activity-sheet-section{break-inside:avoid;page-break-inside:avoid}.activity-sheet-footer{position:static}}
</style></head><body><?php echo $ia_sheet($ia_ficha); ?></body></html><?php
	exit();
}

if(!empty($impresion_fichas))
{
	if(count($ia_errors)>0 || count($ia_rows)===0)
	{
		echo '<!doctype html><html lang="es" data-rrhh-print-ready="1" data-rrhh-print-error="1" data-rrhh-print-message="No hay actividades para imprimir con los filtros seleccionados."><meta charset="utf-8"><title>Sin resultados</title><body><p>No hay actividades para imprimir con los filtros seleccionados.</p></body></html>';
		exit();
	}
	?><!doctype html><html lang="es" data-rrhh-print-ready="1"><head><meta charset="utf-8"><title>Fichas de actividades | ADEMI</title><style>
@page{size:A4 portrait;margin:13mm 14mm}*{box-sizing:border-box}html,body{margin:0;padding:0;width:auto;height:auto;min-height:0;overflow:visible;background:#fff;color:#1e293b;font:10.5pt/1.4 Arial,sans-serif}.activity-print-page{display:block;position:static;width:100%;margin:0;padding:0;break-inside:auto;page-break-inside:auto}.activity-print-page+.activity-print-page{break-before:page;page-break-before:always}.activity-print-page:last-child{break-after:auto;page-break-after:auto}.activity-sheet-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-bottom:8px;border-bottom:2px solid #2563eb}.activity-sheet-brand{font-size:8.5pt;letter-spacing:.12em;font-weight:700;color:#334155}.activity-sheet h1{margin:4px 0;font-size:18pt;color:#17365d}.activity-sheet-subtitle,.activity-sheet-emitted{margin:0;color:#64748b;font-size:8.5pt}.activity-sheet-emitted{white-space:nowrap}.activity-sheet-section{margin-top:14px}.activity-sheet-section h2{margin:0 0 7px;padding-bottom:4px;border-bottom:1px solid #cbd5e1;font-size:10.5pt;color:#1e3a5f}.activity-sheet-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 16px}.activity-sheet-label{display:block;margin-bottom:2px;color:#64748b;font-size:7.5pt;letter-spacing:.05em;text-transform:uppercase}.activity-sheet-value,.activity-sheet-text{white-space:pre-wrap;overflow-wrap:anywhere}.activity-sheet-footer{margin-top:18px;padding-top:6px;border-top:1px solid #cbd5e1;text-align:center;color:#64748b;font-size:8pt}.activity-print-page .activity-sheet-section{break-inside:avoid;page-break-inside:avoid}
</style></head><body><?php foreach($ia_rows as $ia_row) { echo $ia_sheet($ia_row,'activity-print-page'); } ?></body></html><?php
	exit();
}

$ia_result_html=function() use ($ia_rows,$ia_total,$ia_errors,$ia_esc,$ia_fecha,$ia_estados,$ia_emitted,$ia_applied,$ia_sheet) {
	?><div class="rrhh-report-async-response" id="rrhh-report-async-response" data-total="<?php echo intval($ia_total); ?>" data-invalid="<?php echo count($ia_errors)>0 ? '1' : '0'; ?>">
	<?php foreach($ia_errors as $ia_error) { ?><div class="alert alert-danger rrhh-report-async-error"><?php echo $ia_esc($ia_error); ?></div><?php } ?>
	<header class="rrhh-report-print-only rrhh-report-print-header"><div class="institution">ADEMI · INFORME INSTITUCIONAL</div><h1>Informe de actividades</h1><div class="rrhh-report-meta">Emitido el <?php echo $ia_esc($ia_emitted); ?></div><div class="rrhh-report-filters-print">Filtros: <?php echo $ia_esc(count($ia_applied) ? implode(' · ',$ia_applied) : 'Todas las actividades autorizadas'); ?></div></header>

	<div class="grid simple crm-activity-report-grid"><div class="grid-title rrhh-report-results-heading"><h4>Resultados <span class="rrhh-report-total-count">Total de actividades: <?php echo intval($ia_total); ?></span></h4><div class="rrhh-report-result-actions no-print"><button type="button" class="btn btn-white" data-print-list><i class="fa fa-print"></i> Imprimir informe</button><button type="button" class="btn btn-primary" data-print-fichas><i class="fa fa-print"></i> Imprimir fichas</button><span class="rrhh-report-print-fichas-status" data-print-fichas-status role="status" hidden></span><button type="button" class="btn btn-white btn-sm" data-print-fichas-retry hidden>Reintentar impresión</button></div></div>
	<div class="grid-body"><div class="table-responsive"><table class="table table-bordered table-hover rrhh-report-table crm-activity-report-table"><thead><tr><th>Actividad</th><th>Tipo</th><th>Área organizadora</th><th>Áreas participantes</th><th>Fecha</th><th>Estado</th><th>Responsable</th></tr></thead><tbody>
	<?php if($ia_total===0) { ?><tr><td colspan="7" class="rrhh-report-empty">No se encontraron actividades con los criterios seleccionados.</td></tr><?php } else { foreach($ia_rows as $ia_row) { $ia_href=url_crm('informes.php?reporte=actividades&actividad_ficha='.intval($ia_row['id_evento'])); $ia_status=isset($ia_estados[$ia_row['estado_informe']]) ? $ia_estados[$ia_row['estado_informe']] : 'No disponible'; ?>
	<tr class="rrhh-report-row-link" tabindex="0" role="button" data-report-open data-href="<?php echo $ia_esc($ia_href); ?>" aria-haspopup="dialog" aria-label="Abrir ficha de actividad: <?php echo $ia_esc($ia_row['titulo']); ?>"><td><?php echo $ia_esc($ia_row['titulo']); ?></td><td><?php echo $ia_esc($ia_row['tipo_texto']); ?></td><td><?php echo $ia_esc($ia_row['area_organizadora']); ?></td><td><?php echo $ia_esc($ia_row['areas_participantes']!=='' ? $ia_row['areas_participantes'] : 'Sin participantes'); ?></td><td><?php echo $ia_esc($ia_fecha($ia_row['fecha'])); ?></td><td><?php echo $ia_esc($ia_status); ?></td><td><?php echo $ia_esc(trim((string)$ia_row['responsable'])!=='' ? $ia_row['responsable'] : 'No asignado'); ?></td></tr>
	<?php } } ?></tbody></table></div><div class="rrhh-report-print-only rrhh-report-print-footer">ADEMI · Informe interno de actividades · Registros: <?php echo intval($ia_total); ?></div></div></div></div></div><?php
};
if(!empty($ajax_resultados)) { $ia_result_html(); exit(); }
?>
<div class="rrhh-report-wrap crm-activity-report" data-activity-report>
<form method="get" action="<?php echo $ia_esc(url_crm('informes.php')); ?>" class="rrhh-report-filters" data-report-filters>
	<input type="hidden" name="reporte" value="actividades">
	<div class="grid simple rrhh-report-grid no-print"><div class="grid-title no-border"><h4><i class="fa fa-filter"></i> Filtros</h4></div><div class="grid-body no-border"><div class="rrhh-report-filters-grid crm-activity-filters-grid">
	<div class="form-group"><label for="activity-report-q">Buscar actividad</label><input id="activity-report-q" name="q" type="search" class="form-control" maxlength="150" value="<?php echo $ia_esc($ia_form['q']); ?>" placeholder="Nombre o título"></div>
	<div class="form-group"><label for="activity-report-organizer">Área organizadora</label><select id="activity-report-organizer" name="organizadora" class="form-control"><option value="">Todas</option><?php foreach($ia_organizadoras as $ia_area_id=>$ia_area_name) { ?><option value="<?php echo intval($ia_area_id); ?>" <?php echo (string)$ia_area_id===$ia_form['organizadora'] ? 'selected' : ''; ?>><?php echo $ia_esc($ia_area_name); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="activity-report-participant">Área participante</label><select id="activity-report-participant" name="participante" class="form-control"><option value="">Todas</option><?php foreach($ia_participantes_areas as $ia_area_id=>$ia_area_name) { ?><option value="<?php echo intval($ia_area_id); ?>" <?php echo (string)$ia_area_id===$ia_form['participante'] ? 'selected' : ''; ?>><?php echo $ia_esc($ia_area_name); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="activity-report-type">Tipo</label><select id="activity-report-type" name="tipo" class="form-control"><option value="">Todos</option><?php foreach($ia_tipos_disponibles as $ia_type=>$ia_type_text) { ?><option value="<?php echo $ia_esc($ia_type); ?>" <?php echo $ia_form['tipo']===$ia_type ? 'selected' : ''; ?>><?php echo $ia_esc($ia_type_text); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="activity-report-status">Estado</label><select id="activity-report-status" name="estado" class="form-control"><option value="">Todos</option><?php foreach($ia_estados as $ia_state=>$ia_state_text) { ?><option value="<?php echo $ia_esc($ia_state); ?>" <?php echo $ia_form['estado']===$ia_state ? 'selected' : ''; ?>><?php echo $ia_esc($ia_state_text); ?></option><?php } ?></select></div>
	<div class="form-group"><label for="activity-report-from">Fecha desde</label><input id="activity-report-from" name="desde" type="date" class="form-control" value="<?php echo $ia_esc($ia_form['desde']); ?>"></div>
	<div class="form-group"><label for="activity-report-to">Fecha hasta</label><input id="activity-report-to" name="hasta" type="date" class="form-control" value="<?php echo $ia_esc($ia_form['hasta']); ?>"></div>
	<div class="form-group"><label for="activity-report-owner">Responsable</label><select id="activity-report-owner" name="responsable" class="form-control"><option value="">Todos</option><?php foreach($ia_responsables as $ia_owner_id=>$ia_owner_name) { ?><option value="<?php echo intval($ia_owner_id); ?>" <?php echo (string)$ia_owner_id===$ia_form['responsable'] ? 'selected' : ''; ?>><?php echo $ia_esc($ia_owner_name); ?></option><?php } ?></select></div>
	<div class="rrhh-report-actions"><button type="button" class="btn btn-white" data-report-clear>Limpiar</button></div>
	</div></div></div></div>
</form>
<div id="rrhh-report-fetch-error" class="alert alert-danger rrhh-report-async-error" role="alert" hidden></div>
<?php $ia_result_html(); ?>
</div>
<div class="rrhh-report-modal no-print" id="crm-activity-modal" role="dialog" aria-modal="true" aria-labelledby="crm-activity-modal-title" hidden><div class="rrhh-report-modal-dialog"><div class="rrhh-report-modal-head"><h2 id="crm-activity-modal-title">Ficha de actividad</h2><button type="button" class="btn btn-white btn-sm" data-activity-close aria-label="Cerrar ficha"><i class="fa fa-times"></i></button></div><div class="rrhh-report-modal-body"><div class="rrhh-report-modal-loading" data-activity-loading role="status">Cargando ficha…</div><iframe class="rrhh-report-modal-frame" data-activity-frame title="Ficha de actividad" src="about:blank"></iframe></div><div class="rrhh-report-modal-foot"><p class="rrhh-report-modal-hint">Para guardar como PDF, elegí esa opción en el diálogo de impresión del navegador.</p><button type="button" class="btn btn-white" data-activity-print><i class="fa fa-print"></i> Imprimir</button><button type="button" class="btn btn-primary" data-activity-pdf><i class="fa fa-file-pdf-o"></i> Guardar como PDF</button><button type="button" class="btn btn-white" data-activity-close>Cerrar</button></div></div></div>
<script>
(function(){
var form=document.querySelector('[data-activity-report] [data-report-filters]'),modal=document.getElementById('crm-activity-modal'),frame=document.querySelector('[data-activity-frame]'),loader=document.querySelector('[data-activity-loading]'),timer=null,seq=0,controller=null,bodyOverflow='',returnFocus=null,massFrame=null,massTimer=null,dialogRequested=false;
if(modal&&modal.parentNode!==document.body){document.body.appendChild(modal);}
function root(){return document.getElementById('rrhh-report-async-response');}
function url(ajax){var u=new URL(form.action,location.href),p=new URLSearchParams(new FormData(form));if(ajax){p.set('ajax','resultados');}u.search=p.toString();return u;}
function buttons(disabled){var total=root()?parseInt(root().getAttribute('data-total'),10):0;document.querySelectorAll('[data-print-list],[data-print-fichas]').forEach(function(b){b.disabled=disabled||!total||!!massFrame;});}
function refresh(){if(massFrame&&!dialogRequested){dispose();}if(controller){controller.abort();}controller=typeof AbortController!=='undefined'?new AbortController():null;var n=++seq;form.setAttribute('aria-busy','true');buttons(true);var opt={credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}};if(controller){opt.signal=controller.signal;}return fetch(url(true).toString(),opt).then(function(r){if(!r.ok){throw new Error();}return r.text();}).then(function(html){if(n!==seq){return false;}var doc=new DOMParser().parseFromString(html,'text/html'),next=doc.getElementById('rrhh-report-async-response'),old=root();if(!next||!old){throw new Error();}old.replaceWith(document.importNode(next,true));var err=document.getElementById('rrhh-report-fetch-error');if(err){err.hidden=true;}history.replaceState({},'',url(false).pathname+url(false).search);return true;}).catch(function(e){if(n===seq&&e.name!=='AbortError'){var err=document.getElementById('rrhh-report-fetch-error');if(err){err.textContent='No se pudieron actualizar los resultados. Revisá tu conexión e intentá nuevamente.';err.hidden=false;}return false;}return false;}).then(function(ok){if(n===seq){form.removeAttribute('aria-busy');buttons(false);}return ok;});}
function dispose(){dialogRequested=false;if(massTimer){clearTimeout(massTimer);massTimer=null;}if(massFrame){massFrame.remove();massFrame=null;}buttons(false);}
function showPrint(message,retry){var status=document.querySelector('[data-print-fichas-status]'),button=document.querySelector('[data-print-fichas-retry]');if(status){status.textContent=message;status.hidden=false;}if(button){button.hidden=!retry;}}
function startPrint(u){if(massFrame){return;}showPrint('Preparando las fichas…',false);massFrame=document.createElement('iframe');massFrame.setAttribute('aria-hidden','true');massFrame.setAttribute('tabindex','-1');massFrame.className='no-print';massFrame.title='Documento temporal de impresión';massFrame.style.cssText='position:fixed;left:-10000px;top:0;width:1000px;height:1200px;border:0;opacity:0;pointer-events:none;z-index:-1';var target=massFrame;target.addEventListener('load',function(){if(massFrame!==target){return;}try{var doc=target.contentDocument,html=doc.documentElement;if(html.getAttribute('data-rrhh-print-error')==='1'){var msg=html.getAttribute('data-rrhh-print-message')||'No hay fichas para imprimir.';dispose();showPrint(msg,false);return;}if(html.getAttribute('data-rrhh-print-ready')!=='1'){dispose();showPrint('No se pudo preparar la impresión. Actualizá el informe e intentá nuevamente.',false);return;}dialogRequested=true;showPrint('Abriendo el diálogo de impresión…',false);target.contentWindow.addEventListener('afterprint',function(){if(massFrame===target){dispose();var s=document.querySelector('[data-print-fichas-status]');if(s){s.hidden=true;}}});target.contentWindow.print();massTimer=setTimeout(function(){if(massFrame===target){dialogRequested=false;showPrint('Si no apareció el diálogo de impresión, podés reintentar.',true);}},1800);}catch(e){dispose();showPrint('El navegador no pudo abrir la impresión automáticamente.',true);}});target.src=u.toString();document.body.appendChild(target);}
form.addEventListener('submit',function(e){e.preventDefault();refresh();});
var search=form.querySelector('[name=q]');search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(refresh,350);});form.querySelectorAll('select,input[type=date]').forEach(function(el){el.addEventListener('change',function(){clearTimeout(timer);timer=setTimeout(refresh,0);});});
form.querySelector('[data-report-clear]').addEventListener('click',function(){form.reset();search.focus();refresh();});
document.addEventListener('click',function(e){var b=e.target.closest('[data-print-list]');if(b){e.preventDefault();refresh().then(function(ok){if(ok&&root()&&parseInt(root().getAttribute('data-total'),10)>0&&root().getAttribute('data-invalid')!=='1'){window.print();}});return;}b=e.target.closest('[data-print-fichas]');if(b){e.preventDefault();if(b.disabled){return;}var u=url(false);u.searchParams.set('imprimir_fichas','1');startPrint(u);return;}b=e.target.closest('[data-print-fichas-retry]');if(b){e.preventDefault();var u=url(false);u.searchParams.set('imprimir_fichas','1');startPrint(u);return;}var row=e.target.closest('[data-report-open][data-href]');if(row){e.preventDefault();returnFocus=row;document.getElementById('crm-activity-modal-title').textContent='Ficha de '+row.cells[0].textContent.trim();modal.hidden=false;bodyOverflow=document.body.style.overflow;document.body.style.overflow='hidden';loader.hidden=false;frame.src=row.getAttribute('data-href');var closeButton=modal.querySelector('[data-activity-close]');if(closeButton){closeButton.focus();}return;}if(e.target.closest('[data-activity-close]')){close();return;}if(e.target.closest('[data-activity-print],[data-activity-pdf]')){e.preventDefault();try{frame.contentWindow.focus();frame.contentWindow.print();}catch(err){loader.textContent='No se pudo abrir el diálogo de impresión. Volvé a intentar cuando la ficha termine de cargar.';loader.hidden=false;}}});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal&&!modal.hidden){close();return;}var row=e.target.closest&&e.target.closest('[data-report-open][data-href]');if(row&&(e.key==='Enter'||e.key===' ')){e.preventDefault();row.click();}});
function close(){if(!modal||modal.hidden){return;}modal.hidden=true;frame.src='about:blank';document.body.style.overflow=bodyOverflow;if(returnFocus&&document.contains(returnFocus)){returnFocus.focus();}returnFocus=null;}
frame.addEventListener('load',function(){if(modal.hidden||frame.getAttribute('src')==='about:blank'){return;}try{var doc=frame.contentDocument;if(doc.querySelector('[data-activity-sheet]')){loader.hidden=true;doc.addEventListener('keydown',function(event){if(event.key==='Escape'){event.preventDefault();close();}});return;}}catch(e){}loader.textContent='No se pudo cargar la ficha. Verificá que tu sesión siga activa y tengas autorización para consultar esta actividad.';loader.hidden=false;});
buttons(false);
})();
</script>
<style>
.crm-activity-filters-grid{grid-template-columns:minmax(160px,1.5fr) minmax(130px,1fr) minmax(130px,1fr) minmax(100px,.8fr) minmax(100px,.8fr) minmax(120px,.9fr) minmax(120px,.9fr) minmax(130px,1fr) auto}.crm-activity-report-grid .grid-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.crm-activity-report-grid .grid-title h4{margin:0}.crm-activity-report-table th:nth-child(1){width:22%}.crm-activity-report-table th:nth-child(2){width:10%}.crm-activity-report-table th:nth-child(3){width:14%}.crm-activity-report-table th:nth-child(4){width:20%}.crm-activity-report-table th:nth-child(5){width:10%}.crm-activity-report-table th:nth-child(6){width:9%}.crm-activity-report-table th:nth-child(7){width:15%}
@media(max-width:1100px){.crm-activity-filters-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:767px){.crm-activity-filters-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media print{.rrhh-report-print-header,.rrhh-report-print-header .institution,.rrhh-report-print-header h1,.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{display:block!important;visibility:visible!important}.rrhh-report-print-header{padding:0 0 4px!important;margin:0 0 5px!important}.rrhh-report-print-header .institution,.rrhh-report-print-header h1,.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{color:#334155!important}.rrhh-report-print-header .institution{font-size:8.5pt!important}.rrhh-report-print-header h1{font-size:14pt!important;margin:1px 0!important}.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{font-size:7.5pt!important}.crm-activity-report-grid>.grid-title{display:none!important}.crm-activity-report-table{width:100%!important;max-width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;font-size:7.5pt!important}.crm-activity-report-table th,.crm-activity-report-table td{box-sizing:border-box!important;padding:2.5px 4px!important;line-height:1.16!important;white-space:normal!important;overflow-wrap:normal!important;word-break:normal!important;hyphens:none!important}.crm-activity-report-table th:nth-child(1){width:22%!important}.crm-activity-report-table th:nth-child(2){width:10%!important}.crm-activity-report-table th:nth-child(3){width:14%!important}.crm-activity-report-table th:nth-child(4){width:20%!important}.crm-activity-report-table th:nth-child(5){width:10%!important}.crm-activity-report-table th:nth-child(6){width:9%!important}.crm-activity-report-table th:nth-child(7){width:15%!important}.crm-activity-report-table thead{display:table-header-group!important}.crm-activity-report-table tr{break-inside:avoid!important;page-break-inside:avoid!important}}
</style>
