<?php
// Informe de clientes: lectura limitada a los alcances ya permitidos por el CRM.
$esc=function($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
$areas_informe=acceso_consulta("SELECT id_area, clave, nombre FROM area WHERE activo=1 ORDER BY nombre");
$area_ids_informe=array();
foreach($areas_informe as $area_item) { $area_ids_informe[(string)intval($area_item['id_area'])]=$area_item; }
$scope_ids_informe=array();
if(!$crm_informe_clientes_global)
{
	foreach($crm_informe_clientes_areas as $scope_item) { $scope_ids_informe[]=intval(isset($scope_item['id_area']) ? $scope_item['id_area'] : $scope_item['id']); }
	$scope_ids_informe=array_values(array_unique(array_filter($scope_ids_informe)));
}
$scope_sql_informe=$crm_informe_clientes_global ? '' : implode(',', $scope_ids_informe);
$tipos_informe=acceso_consulta("SELECT DISTINCT tipo_cliente FROM cliente ORDER BY tipo_cliente");
$tipos_validos_informe=array();
foreach($tipos_informe as $tipo_item) { $tipos_validos_informe[(string)$tipo_item['tipo_cliente']]=true; }
$areas_filtro_informe=$crm_informe_clientes_global ? $areas_informe : array_values($crm_informe_clientes_areas);
$normalizar_fecha_informe=function($value) {
	$value=trim((string)$value);
	if($value==='') { return ''; }
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return false; }
	$parts=explode('-', $value);
	return checkdate(intval($parts[1]), intval($parts[2]), intval($parts[0])) ? $value : false;
};
$form_informe=array(
	'q'=>isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string)$_GET['q']) : '',
	'tipo'=>isset($_GET['tipo']) && is_scalar($_GET['tipo']) ? trim((string)$_GET['tipo']) : '',
	'area'=>isset($_GET['area']) && is_scalar($_GET['area']) ? trim((string)$_GET['area']) : '',
	'estado'=>isset($_GET['estado']) && is_scalar($_GET['estado']) ? trim((string)$_GET['estado']) : '',
	'desde'=>isset($_GET['desde']) && is_scalar($_GET['desde']) ? (string)$_GET['desde'] : '',
	'hasta'=>isset($_GET['hasta']) && is_scalar($_GET['hasta']) ? (string)$_GET['hasta'] : ''
);
if(function_exists('mb_substr')) { $form_informe['q']=mb_substr($form_informe['q'],0,150,'UTF-8'); }
else { $form_informe['q']=substr($form_informe['q'],0,150); }
$errores_informe=array();
$desde_informe=$normalizar_fecha_informe($form_informe['desde']);
$hasta_informe=$normalizar_fecha_informe($form_informe['hasta']);
if($desde_informe===false) { $errores_informe[]='La fecha desde no es válida.'; $desde_informe=''; }
if($hasta_informe===false) { $errores_informe[]='La fecha hasta no es válida.'; $hasta_informe=''; }
if($desde_informe!=='' && $hasta_informe!=='' && $desde_informe>$hasta_informe) { $errores_informe[]='La fecha inicial no puede ser posterior a la fecha final.'; }
if($form_informe['tipo']!=='' && !isset($tipos_validos_informe[$form_informe['tipo']])) { $form_informe['tipo']=''; }
$area_filtro_id=0;
foreach($areas_filtro_informe as $area_item)
{
	$item_area_id=intval(isset($area_item['id_area']) ? $area_item['id_area'] : (isset($area_item['id']) ? $area_item['id'] : 0));
	if((string)$item_area_id===$form_informe['area'])
	{
		$area_filtro_id=$item_area_id;
		break;
	}
}
if($form_informe['area']!=='' && $area_filtro_id<=0) { $form_informe['area']=''; }
if(!in_array($form_informe['estado'], array('activo','inactivo'), true)) { $form_informe['estado']=''; }

$visibilidad_sql_informe='';
if(!$crm_informe_clientes_global)
{
	if(count($scope_ids_informe)===0) { http_response_code(403); exit('No estás autorizado para consultar este informe.'); }
	$visibilidad_partes=array();
	if(in_array('emprendedores', array_keys($crm_informe_clientes_areas), true)) { $visibilidad_partes[]="c.tipo_cliente='emprendedor'"; }
	if(in_array('empresas', array_keys($crm_informe_clientes_areas), true)) { $visibilidad_partes[]="c.tipo_cliente='empresa'"; }
	$visibilidad_partes[]="EXISTS (SELECT 1 FROM cliente_area ca WHERE ca.id_cliente=c.id_cliente AND ca.id_area IN (".$scope_sql_informe."))";
	$visibilidad_partes[]="EXISTS (SELECT 1 FROM atencion at WHERE at.id_cliente=c.id_cliente AND at.id_area IN (".$scope_sql_informe."))";
	$visibilidad_partes[]="EXISTS (SELECT 1 FROM tarea t WHERE t.id_cliente=c.id_cliente AND t.id_area IN (".$scope_sql_informe."))";
	$visibilidad_sql_informe='('.implode(' OR ', $visibilidad_partes).')';
}
$area_expr_informe=function($id_area) use ($areas_filtro_informe) {
	$id_area=intval($id_area);
	$claves=array();
	foreach($areas_filtro_informe as $area_item)
	{
		$item_id=intval(isset($area_item['id_area']) ? $area_item['id_area'] : (isset($area_item['id']) ? $area_item['id'] : 0));
		if($item_id===$id_area) { $claves[]=(string)$area_item['clave']; }
	}
	$parts=array("EXISTS (SELECT 1 FROM cliente_area ca WHERE ca.id_cliente=c.id_cliente AND ca.id_area=".$id_area.")", "EXISTS (SELECT 1 FROM atencion at WHERE at.id_cliente=c.id_cliente AND at.id_area=".$id_area.")", "EXISTS (SELECT 1 FROM tarea t WHERE t.id_cliente=c.id_cliente AND t.id_area=".$id_area.")", "c.id_area_afectada=".$id_area);
	if(in_array('emprendedores',$claves,true)) { $parts[]="c.tipo_cliente='emprendedor'"; }
	if(in_array('empresas',$claves,true)) { $parts[]="c.tipo_cliente='empresa'"; }
	return '('.implode(' OR ',$parts).')';
};
$where_informe=array(); $types_informe=''; $values_informe=array();
if($visibilidad_sql_informe!=='') { $where_informe[]=$visibilidad_sql_informe; }
if($form_informe['q']!=='')
{
	$like='%'.$form_informe['q'].'%';
	$where_informe[]="(c.nombre LIKE ? OR c.correo LIKE ? OR c.cuil_cuit LIKE ? OR cp.dni LIKE ? OR ce.razon_social LIKE ? OR c.telefono LIKE ? OR l.nombre LIKE ?)";
	$types_informe.='sssssss';
	for($i=0; $i<7; $i++) { $values_informe[]=$like; }
}
if($form_informe['tipo']!=='') { $where_informe[]='c.tipo_cliente=?'; $types_informe.='s'; $values_informe[]=$form_informe['tipo']; }
if($area_filtro_id>0) { $where_informe[]=$area_expr_informe($area_filtro_id); }
if($form_informe['estado']==='activo') { $where_informe[]='c.activo=1'; }
elseif($form_informe['estado']==='inactivo') { $where_informe[]='c.activo=0'; }
if($desde_informe!=='' && $desde_informe!==false) { $where_informe[]='c.fecha_alta>=?'; $types_informe.='s'; $values_informe[]=$desde_informe; }
if($hasta_informe!=='' && $hasta_informe!==false) { $where_informe[]='c.fecha_alta<=?'; $types_informe.='s'; $values_informe[]=$hasta_informe; }
$where_sql_informe=count($where_informe) ? ' WHERE '.implode(' AND ', $where_informe) : '';
$area_afectada_select_informe=$crm_informe_clientes_global ? 'a.nombre' : 'CASE WHEN a.id_area IN ('.$scope_sql_informe.') THEN a.nombre ELSE NULL END';
$areas_relacionadas_sql_informe="(SELECT GROUP_CONCAT(DISTINCT ar.nombre ORDER BY ar.nombre SEPARATOR ', ') FROM area ar WHERE ".($crm_informe_clientes_global ? 'ar.activo=1' : 'ar.id_area IN ('.$scope_sql_informe.')')." AND (
	ar.id_area=c.id_area_afectada OR EXISTS (SELECT 1 FROM cliente_area ca2 WHERE ca2.id_cliente=c.id_cliente AND ca2.id_area=ar.id_area)
	OR EXISTS (SELECT 1 FROM atencion at2 WHERE at2.id_cliente=c.id_cliente AND at2.id_area=ar.id_area)
	OR EXISTS (SELECT 1 FROM tarea t2 WHERE t2.id_cliente=c.id_cliente AND t2.id_area=ar.id_area)
))";
$select_informe=cliente_sql_base();
$select_informe=str_replace('cp.genero, c.direccion', 'cp.genero, cp.dni, c.direccion, c.id_area_afectada, c.fecha_alta, c.activo, c.creado_en', $select_informe);
$select_informe=str_replace('a.clave AS area_clave, l.nombre AS localidad_nombre, l.id_provincia', 'a.clave AS area_clave, '.$area_afectada_select_informe.' AS area_afectada_nombre, l.nombre AS localidad_nombre, l.id_provincia, ce.razon_social, '.$areas_relacionadas_sql_informe.' AS areas_relacionadas', $select_informe);
$select_informe=str_replace('LEFT JOIN localidad l ON l.id_localidad=c.id_localidad', 'LEFT JOIN cliente_empresa ce ON ce.id_cliente=c.id_cliente LEFT JOIN localidad l ON l.id_localidad=c.id_localidad', $select_informe);
$fetch_clientes_informe=function($extra_condition='', $extra_types='', $extra_values=array()) use ($select_informe,$where_sql_informe,$types_informe,$values_informe) {
	$where=$where_sql_informe;
	if($extra_condition!=='') { $where.=($where!=='' ? ' AND ' : ' WHERE ').$extra_condition; }
	return acceso_consulta($select_informe.$where.' ORDER BY c.nombre ASC,c.id_cliente ASC',$types_informe.$extra_types,array_merge($values_informe,$extra_values));
};
$rows_informe=array();
if(count($errores_informe)===0) { $rows_informe=$fetch_clientes_informe(); }
$total_informe=count($rows_informe);
$areas_aplicadas_informe=array();
foreach($form_informe['area']!=='' ? $areas_filtro_informe : array() as $area_item)
{
	if(intval(isset($area_item['id_area']) ? $area_item['id_area'] : $area_item['id'])===$area_filtro_id) { $areas_aplicadas_informe[]=$area_item['nombre']; break; }
}
$filtros_aplicados_informe=array();
if($form_informe['q']!=='') { $filtros_aplicados_informe[]='Búsqueda: '.$form_informe['q']; }
if($form_informe['tipo']!=='') { $filtros_aplicados_informe[]='Tipo: '.ucfirst(str_replace('_',' ',$form_informe['tipo'])); }
if(count($areas_aplicadas_informe)) { $filtros_aplicados_informe[]='Área relacionada: '.$areas_aplicadas_informe[0]; }
if($form_informe['estado']==='activo') { $filtros_aplicados_informe[]='Estado: Activo'; }
elseif($form_informe['estado']==='inactivo') { $filtros_aplicados_informe[]='Estado: Inactivo'; }
if($desde_informe!=='' && $desde_informe!==false) { $filtros_aplicados_informe[]='Registrado desde: '.date('d/m/Y',strtotime($desde_informe)); }
if($hasta_informe!=='' && $hasta_informe!==false) { $filtros_aplicados_informe[]='Registrado hasta: '.date('d/m/Y',strtotime($hasta_informe)); }
$fecha_emision_informe=date('d/m/Y');
$fecha_texto_informe=function($value) { $value=trim((string)$value); if($value==='' || $value==='0000-00-00') { return 'No registrado'; } $time=strtotime($value); return $time ? date('d/m/Y',$time) : 'No registrado'; };
$tipo_texto_informe=function($value) { return ucfirst(str_replace('_',' ',trim((string)$value))); };
$cliente_ficha_row=null;
if($cliente_ficha)
{
	$id_ficha_informe=isset($_GET['cliente_ficha']) && is_scalar($_GET['cliente_ficha']) ? filter_var($_GET['cliente_ficha'],FILTER_VALIDATE_INT) : false;
	if(!$id_ficha_informe || intval($id_ficha_informe)<=0) { http_response_code(404); $cliente_ficha_row=null; }
	else { $cliente_ficha_rows=$fetch_clientes_informe('c.id_cliente=?','i',array(intval($id_ficha_informe))); $cliente_ficha_row=count($cliente_ficha_rows) ? $cliente_ficha_rows[0] : null; }
	if(!$cliente_ficha_row)
	{
		http_response_code(404);
		echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ficha no disponible</title><body><main><h1>Ficha no disponible</h1><p>El cliente solicitado no existe o no está autorizado para tu perfil.</p></main></body></html>';
		exit();
	}
	$cliente_data_informe=$cliente_ficha_row;
	$cliente_data_informe['estado_texto']=intval($cliente_data_informe['activo'])===1 ? 'Activo' : 'Inactivo';
	$cliente_data_informe['tipo_texto']=$tipo_texto_informe($cliente_data_informe['tipo_cliente']);
	$cliente_data_informe['fecha_texto']=$fecha_texto_informe($cliente_data_informe['fecha_alta']);
	$cliente_data_informe['localidad_texto']=trim((string)$cliente_data_informe['localidad_nombre']);
	if($cliente_data_informe['localidad_texto']==='') { $cliente_data_informe['localidad_texto']='No registrada'; }
	$cliente_data_informe['areas_texto']=trim((string)$cliente_data_informe['areas_relacionadas']);
	if($cliente_data_informe['areas_texto']==='') { $cliente_data_informe['areas_texto']='No registrada'; }
	if($cliente_data_informe['area_afectada_nombre'] && !in_array($cliente_data_informe['area_afectada_nombre'], array_map('trim',explode(',',$cliente_data_informe['areas_texto'])), true)) { $cliente_data_informe['areas_texto']=trim($cliente_data_informe['areas_texto'].', '.$cliente_data_informe['area_afectada_nombre'],', '); }
	if(!$ajax_resultados && !$impresion_fichas)
	{
		$cliente_ficha_data=$cliente_data_informe;
		$impresion_individual=true;
	}
}
if(!empty($impresion_individual))
{
	$print_value=function($value) use ($esc) { $value=trim((string)$value); return $esc($value!=='' ? $value : 'No registrado'); };
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ficha de cliente | ADEMI</title><style>
@page{size:A4 portrait;margin:14mm}*{box-sizing:border-box}html,body{margin:0;background:#eef2f7;color:#1e293b;font:14px/1.45 Arial,sans-serif}.client-sheet{width:min(900px,100%);margin:24px auto;padding:28px;background:#fff;border:1px solid #d9e2ef;border-radius:12px;box-shadow:0 8px 26px rgba(15,23,42,.08)}.client-sheet-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:0 0 18px;border-bottom:1px solid #dce5f0}.client-brand{font-size:11px;letter-spacing:.14em;font-weight:700;color:#2563eb}.client-sheet h1{margin:5px 0 2px;font-size:25px;color:#17365d}.client-subtitle{margin:0;color:#64748b}.client-sheet-emitted{font-size:11px;color:#64748b;white-space:nowrap}.client-sheet-section{margin-top:20px}.client-sheet-section h2{font-size:14px;color:#1e3a5f;margin:0 0 10px;padding-bottom:7px;border-bottom:1px solid #e2e8f0}.client-data-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 20px}.client-data-item{min-width:0}.client-data-label{display:block;margin-bottom:3px;font-size:10px;letter-spacing:.07em;text-transform:uppercase;color:#64748b}.client-data-value{overflow-wrap:anywhere;color:#23364d}.client-sheet-footer{margin-top:24px;padding-top:9px;border-top:1px solid #e2e8f0;font-size:10px;color:#64748b;text-align:center}.client-sheet-actions{display:flex;gap:8px;justify-content:center;margin:0 auto 14px}.client-sheet-actions button{border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:8px 12px;color:#1e3a5f;cursor:pointer}.client-sheet-actions button.primary{background:#2563eb;color:#fff;border-color:#2563eb}@media(max-width:600px){.client-sheet{margin:0;padding:18px;border-radius:0}.client-sheet-head{align-items:flex-start;flex-direction:column;gap:7px}.client-data-grid{grid-template-columns:1fr}}@media print{html,body{background:#fff;font-size:11pt}.client-sheet{width:100%;margin:0;padding:0;border:0;border-radius:0;box-shadow:none}.client-sheet-actions{display:none!important}.client-sheet-section{break-inside:avoid;page-break-inside:avoid}.client-sheet-footer{position:fixed;bottom:0;left:0;right:0}}
</style></head><body><main class="client-sheet" data-client-ficha>
<header class="client-sheet-head"><div><div class="client-brand">ADEMI · INFORME DE CLIENTES</div><h1><?php echo $esc($cliente_ficha_data['nombre']); ?></h1><p class="client-subtitle"><?php echo $esc($cliente_ficha_data['tipo_texto']); ?></p></div><div class="client-sheet-emitted">Emitida el <?php echo $esc($fecha_emision_informe); ?></div></header>
<section class="client-sheet-section"><h2>Datos del cliente</h2><div class="client-data-grid">
<?php foreach(array('DNI'=>isset($cliente_ficha_data['dni']) ? $cliente_ficha_data['dni'] : '', 'CUIL / CUIT'=>$cliente_ficha_data['cuil_cuit'],'Correo electrónico'=>$cliente_ficha_data['correo'],'Teléfono'=>$cliente_ficha_data['telefono'],'Dirección'=>$cliente_ficha_data['direccion'],'Localidad'=>$cliente_ficha_data['localidad_texto'],'Área relacionada'=>$cliente_ficha_data['areas_texto'],'Estado'=>$cliente_ficha_data['estado_texto'],'Fecha de registro'=>$cliente_ficha_data['fecha_texto'],'Razón social'=>$cliente_ficha_data['razon_social']) as $label=>$value) { ?><div class="client-data-item"><span class="client-data-label"><?php echo $esc($label); ?></span><div class="client-data-value"><?php echo $print_value($value); ?></div></div><?php } ?>
</div></section><footer class="client-sheet-footer">ADEMI · Documento generado por el sistema institucional</footer></main></body></html>
<?php exit(); }

if($impresion_fichas)
{
	if(count($errores_informe)>0 || count($rows_informe)===0)
	{
		echo '<!doctype html><html lang="es" data-rrhh-print-ready="1" data-rrhh-print-error="1" data-rrhh-print-message="No hay clientes para imprimir con los filtros seleccionados."><meta charset="utf-8"><title>Sin resultados</title><body><p>No hay clientes para imprimir con los filtros seleccionados.</p></body></html>';
		exit();
	}
	?>
<!doctype html><html lang="es" data-rrhh-print-ready="1"><head><meta charset="utf-8"><title>Fichas de clientes | ADEMI</title><style>
@page{size:A4 portrait;margin:13mm 14mm}*{box-sizing:border-box}html,body{margin:0;padding:0;width:auto;height:auto;min-height:0;overflow:visible;background:#fff;color:#1e293b;font:10.5pt/1.4 Arial,sans-serif}.client-print-page{display:block;position:static;width:100%;margin:0;padding:0;break-inside:auto;page-break-inside:auto}.client-print-page+.client-print-page{break-before:page;page-break-before:always}.client-print-brand{font-size:9pt;letter-spacing:.14em;font-weight:700;color:#334155}.client-print-head{padding-bottom:9px;border-bottom:2px solid #2563eb}.client-print-head h1{margin:4px 0 2px;font-size:19pt;color:#17365d}.client-print-subtitle{margin:0;color:#64748b}.client-print-emitted{margin-top:7px;font-size:8.5pt;color:#64748b}.client-print-section{margin-top:17px}.client-print-section h2{font-size:11pt;margin:0 0 9px;padding-bottom:5px;border-bottom:1px solid #cbd5e1;color:#1e3a5f}.client-print-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px}.client-print-item{min-width:0}.client-print-label{display:block;margin-bottom:2px;font-size:8pt;letter-spacing:.06em;text-transform:uppercase;color:#64748b}.client-print-value{overflow-wrap:anywhere}.client-print-footer{margin-top:22px;padding-top:7px;border-top:1px solid #cbd5e1;font-size:8pt;color:#64748b;text-align:center}@media print{html,body{background:#fff!important}.client-print-page+.client-print-page{break-before:page;page-break-before:always}.client-print-page:last-child{break-after:auto;page-break-after:auto}}
</style></head><body>
<?php foreach($rows_informe as $cliente_print) { $tipo_print=$tipo_texto_informe($cliente_print['tipo_cliente']); $localidad_print=trim((string)$cliente_print['localidad_nombre']); $areas_print=trim((string)$cliente_print['areas_relacionadas']); if($areas_print==='' && trim((string)$cliente_print['area_afectada_nombre'])!=='') { $areas_print=$cliente_print['area_afectada_nombre']; } ?>
<article class="client-print-page"><header class="client-print-head"><div class="client-print-brand">ADEMI · INFORME DE CLIENTES</div><h1><?php echo $esc($cliente_print['nombre']); ?></h1><p class="client-print-subtitle"><?php echo $esc($tipo_print); ?></p><div class="client-print-emitted">Emitida el <?php echo $esc($fecha_emision_informe); ?></div></header><section class="client-print-section"><h2>Datos del cliente</h2><div class="client-print-grid">
<?php foreach(array('DNI'=>isset($cliente_print['dni']) ? $cliente_print['dni'] : '', 'CUIL / CUIT'=>$cliente_print['cuil_cuit'],'Correo electrónico'=>$cliente_print['correo'],'Teléfono'=>$cliente_print['telefono'],'Dirección'=>$cliente_print['direccion'],'Localidad'=>$localidad_print,'Área relacionada'=>$areas_print,'Estado'=>intval($cliente_print['activo'])===1 ? 'Activo' : 'Inactivo','Fecha de registro'=>$fecha_texto_informe($cliente_print['fecha_alta']),'Razón social'=>$cliente_print['razon_social']) as $label=>$value) { ?><div class="client-print-item"><span class="client-print-label"><?php echo $esc($label); ?></span><div class="client-print-value"><?php echo $esc(trim((string)$value)!=='' ? $value : 'No registrado'); ?></div></div><?php } ?>
</div></section><footer class="client-print-footer">ADEMI · Ficha de cliente</footer></article>
<?php } ?></body></html><?php exit();
}

$reporte_esc=function($value) use ($esc) { return $esc($value); };
$resultados_html=function() use ($rows_informe,$total_informe,$errores_informe,$esc,$tipo_texto_informe,$fecha_texto_informe,$fecha_emision_informe,$filtros_aplicados_informe,$ajax_resultados) {
?>
<div class="rrhh-report-async-response" id="rrhh-report-async-response" data-total="<?php echo intval($total_informe); ?>" data-invalid="<?php echo count($errores_informe)>0 ? '1' : '0'; ?>">
<?php foreach($errores_informe as $error_informe) { ?><div class="alert alert-danger rrhh-report-async-error"><?php echo $esc($error_informe); ?></div><?php } ?>
<header class="rrhh-report-print-only rrhh-report-print-header"><div class="institution">ADEMI · INFORME INSTITUCIONAL</div><h1>Informe de clientes</h1><div class="rrhh-report-meta">Emitido el <?php echo $esc($fecha_emision_informe); ?></div><div class="rrhh-report-filters-print">Filtros: <?php echo $esc(count($filtros_aplicados_informe) ? implode(' · ',$filtros_aplicados_informe) : 'Todos los clientes'); ?></div></header>
<div class="grid simple crm-client-report-grid"><div class="grid-title crm-client-results-heading"><h4>Resultados <span class="rrhh-report-total-count">Total de clientes: <?php echo intval($total_informe); ?></span></h4><div class="rrhh-report-result-actions no-print"><button type="button" class="btn btn-white" data-print-list><i class="fa fa-print"></i> Imprimir informe</button><button type="button" class="btn btn-primary" data-print-fichas><i class="fa fa-print"></i> Imprimir fichas</button><span class="rrhh-report-print-fichas-status" data-print-fichas-status role="status" hidden></span><button type="button" class="btn btn-white btn-sm" data-print-fichas-retry hidden>Reintentar impresión</button></div></div>
<div class="grid-body"><div class="table-responsive"><table class="table table-bordered table-hover rrhh-report-table crm-client-report-table"><thead><tr><th>Cliente</th><th>Tipo</th><th>DNI / CUIL-CUIT</th><th>Área relacionada</th><th>Localidad</th><th>Estado</th><th>Registro</th></tr></thead><tbody>
<?php if($total_informe===0) { ?><tr><td colspan="7" class="rrhh-report-empty">No se encontraron clientes con los criterios seleccionados.</td></tr><?php } else { foreach($rows_informe as $cliente_row) { $nombre_cliente=trim((string)$cliente_row['nombre']); $href=url_crm('informes.php?reporte=clientes&cliente_ficha='.intval($cliente_row['id_cliente'])); $area_cliente=trim((string)$cliente_row['areas_relacionadas']); if($area_cliente==='' && trim((string)$cliente_row['area_afectada_nombre'])!=='') { $area_cliente=$cliente_row['area_afectada_nombre']; } $documento_cliente=trim((string)$cliente_row['dni']); if($documento_cliente==='') { $documento_cliente=trim((string)$cliente_row['cuil_cuit']); } ?>
<tr class="rrhh-report-row-link" tabindex="0" role="button" data-report-open data-href="<?php echo $esc($href); ?>" aria-haspopup="dialog" aria-label="Abrir ficha de <?php echo $esc($nombre_cliente); ?>"><td><?php echo $esc($nombre_cliente); ?></td><td><?php echo $esc($tipo_texto_informe($cliente_row['tipo_cliente'])); ?></td><td><?php echo $esc($documento_cliente!=='' ? $documento_cliente : 'No registrado'); ?></td><td><?php echo $esc($area_cliente!=='' ? $area_cliente : 'No registrada'); ?></td><td><?php echo $esc($cliente_row['localidad_nombre']!=='' ? $cliente_row['localidad_nombre'] : 'No registrada'); ?></td><td><?php echo intval($cliente_row['activo'])===1 ? 'Activo' : 'Inactivo'; ?></td><td><?php echo $esc($fecha_texto_informe($cliente_row['fecha_alta'])); ?></td></tr>
<?php } } ?></tbody></table></div><div class="rrhh-report-print-only rrhh-report-print-footer">ADEMI · Informe interno de clientes · Registros: <?php echo intval($total_informe); ?></div></div></div></div>
<?php
};
if($ajax_resultados) { $resultados_html(); exit(); }
?>
<div class="rrhh-report-wrap crm-client-report" data-client-report>
<form method="get" action="<?php echo $esc(url_crm('informes.php')); ?>" class="rrhh-report-filters" data-report-filters>
<input type="hidden" name="reporte" value="clientes">
<div class="grid simple rrhh-report-grid no-print"><div class="grid-title no-border"><h4><i class="fa fa-filter"></i> Filtros</h4></div><div class="grid-body no-border"><div class="rrhh-report-filters-grid crm-client-filters-grid">
<div class="form-group"><label for="client-report-q">Buscar cliente</label><input id="client-report-q" name="q" type="search" class="form-control" maxlength="150" value="<?php echo $esc($form_informe['q']); ?>" placeholder="Nombre, DNI, CUIT"></div>
<div class="form-group"><label for="client-report-type">Tipo de cliente</label><select id="client-report-type" name="tipo" class="form-control"><option value="">Todos</option><?php foreach($tipos_informe as $tipo_item) { ?><option value="<?php echo $esc($tipo_item['tipo_cliente']); ?>" <?php echo $form_informe['tipo']===$tipo_item['tipo_cliente'] ? 'selected' : ''; ?>><?php echo $esc($tipo_texto_informe($tipo_item['tipo_cliente'])); ?></option><?php } ?></select></div>
<div class="form-group"><label for="client-report-area">Área relacionada</label><select id="client-report-area" name="area" class="form-control"><option value="">Todas</option><?php foreach($areas_filtro_informe as $area_item) { $area_id_option=intval(isset($area_item['id_area']) ? $area_item['id_area'] : $area_item['id']); ?><option value="<?php echo $area_id_option; ?>" <?php echo (string)$area_id_option===$form_informe['area'] ? 'selected' : ''; ?>><?php echo $esc($area_item['nombre']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="client-report-status">Estado</label><select id="client-report-status" name="estado" class="form-control"><option value="">Todos</option><option value="activo" <?php echo $form_informe['estado']==='activo' ? 'selected' : ''; ?>>Activos</option><option value="inactivo" <?php echo $form_informe['estado']==='inactivo' ? 'selected' : ''; ?>>Inactivos</option></select></div>
<div class="form-group"><label for="client-report-from">Registro desde</label><input id="client-report-from" name="desde" type="date" class="form-control" value="<?php echo $esc($form_informe['desde']); ?>"></div>
<div class="form-group"><label for="client-report-to">Registro hasta</label><input id="client-report-to" name="hasta" type="date" class="form-control" value="<?php echo $esc($form_informe['hasta']); ?>"></div>
<div class="rrhh-report-actions"><button type="button" class="btn btn-white" data-report-clear>Limpiar</button></div>
</div></div></div></div>
</form>
<div id="rrhh-report-fetch-error" class="alert alert-danger rrhh-report-async-error" role="alert" hidden></div>
<?php $resultados_html(); ?>
</div>
<div class="rrhh-report-modal no-print" id="crm-client-modal" role="dialog" aria-modal="true" aria-labelledby="crm-client-modal-title" hidden><div class="rrhh-report-modal-dialog"><div class="rrhh-report-modal-head"><h2 id="crm-client-modal-title">Ficha del cliente</h2><button type="button" class="btn btn-white btn-sm" data-client-close aria-label="Cerrar ficha"><i class="fa fa-times"></i></button></div><div class="rrhh-report-modal-body"><div class="rrhh-report-modal-loading" data-client-loading role="status">Cargando ficha…</div><iframe class="rrhh-report-modal-frame" data-client-frame title="Ficha del cliente" src="about:blank"></iframe></div><div class="rrhh-report-modal-foot"><p class="rrhh-report-modal-hint">Para guardar como PDF, elegí esa opción en el diálogo de impresión del navegador.</p><button type="button" class="btn btn-white" data-client-print><i class="fa fa-print"></i> Imprimir</button><button type="button" class="btn btn-primary" data-client-pdf><i class="fa fa-file-pdf-o"></i> Guardar como PDF</button><button type="button" class="btn btn-white" data-client-close>Cerrar</button></div></div></div>
<script>
(function(){
var form=document.querySelector('[data-report-filters]'),modal=document.getElementById('crm-client-modal'),frame=document.querySelector('[data-client-frame]'),loader=document.querySelector('[data-client-loading]'),timer=null,seq=0,controller=null,bodyOverflow='',returnFocus=null,massFrame=null,massTimer=null,dialogRequested=false;
if(modal&&modal.parentNode!==document.body){document.body.appendChild(modal);}
function root(){return document.getElementById('rrhh-report-async-response');}
function url(ajax){var u=new URL(form.action,location.href),p=new URLSearchParams(new FormData(form));if(ajax){p.set('ajax','resultados');}u.search=p.toString();return u;}
function buttons(disabled){var total=root()?parseInt(root().getAttribute('data-total'),10):0;document.querySelectorAll('[data-print-list],[data-print-fichas]').forEach(function(b){b.disabled=disabled||!total||!!massFrame;});}
function refresh(){if(massFrame&&!dialogRequested){dispose();}if(controller){controller.abort();}controller=typeof AbortController!=='undefined'?new AbortController():null;var n=++seq;form.setAttribute('aria-busy','true');buttons(true);var opt={credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}};if(controller){opt.signal=controller.signal;}return fetch(url(true).toString(),opt).then(function(r){if(!r.ok){throw new Error();}return r.text();}).then(function(html){if(n!==seq){return false;}var doc=new DOMParser().parseFromString(html,'text/html'),newRoot=doc.getElementById('rrhh-report-async-response'),old=root();if(!newRoot||!old){throw new Error();}old.replaceWith(document.importNode(newRoot,true));var err=document.getElementById('rrhh-report-fetch-error');if(err){err.hidden=true;}history.replaceState({},'',url(false).pathname+url(false).search);return true;}).catch(function(e){if(n===seq&&e.name!=='AbortError'){var err=document.getElementById('rrhh-report-fetch-error');if(err){err.textContent='No se pudieron actualizar los resultados. Revisá tu conexión e intentá nuevamente.';err.hidden=false;}return false;}return false;}).then(function(ok){if(n===seq){form.removeAttribute('aria-busy');buttons(false);}return ok;});}
function dispose(){dialogRequested=false;if(massTimer){clearTimeout(massTimer);massTimer=null;}if(massFrame){massFrame.remove();massFrame=null;}buttons(false);}
function showPrint(message,retry){var status=document.querySelector('[data-print-fichas-status]'),button=document.querySelector('[data-print-fichas-retry]');if(status){status.textContent=message;status.hidden=false;}if(button){button.hidden=!retry;}}
function startPrint(u){if(massFrame){return;}showPrint('Preparando las fichas…',false);massFrame=document.createElement('iframe');massFrame.setAttribute('aria-hidden','true');massFrame.setAttribute('tabindex','-1');massFrame.className='no-print';massFrame.title='Documento temporal de impresión';massFrame.style.cssText='position:fixed;left:-10000px;top:0;width:1000px;height:1200px;border:0;opacity:0;pointer-events:none;z-index:-1';var target=massFrame;target.addEventListener('load',function(){if(massFrame!==target){return;}try{var doc=target.contentDocument,html=doc.documentElement;if(html.getAttribute('data-rrhh-print-error')==='1'){var msg=html.getAttribute('data-rrhh-print-message')||'No hay fichas para imprimir.';dispose();showPrint(msg,false);return;}if(html.getAttribute('data-rrhh-print-ready')!=='1'){dispose();showPrint('No se pudo preparar la impresión. Actualizá el informe e intentá nuevamente.',false);return;}dialogRequested=true;showPrint('Abriendo el diálogo de impresión…',false);target.contentWindow.addEventListener('afterprint',function(){if(massFrame===target){dispose();var s=document.querySelector('[data-print-fichas-status]');if(s){s.hidden=true;}}});target.contentWindow.print();massTimer=setTimeout(function(){if(massFrame===target){dialogRequested=false;showPrint('Si no apareció el diálogo de impresión, podés reintentar.',true);}},1800);}catch(e){dispose();showPrint('El navegador no pudo abrir la impresión automáticamente.',true);}});target.src=u.toString();document.body.appendChild(target);}
form.addEventListener('submit',function(e){e.preventDefault();refresh();});var q=form.querySelector('[name=q]');q.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(refresh,350);});form.querySelectorAll('select,input[type=date]').forEach(function(el){el.addEventListener('change',function(){clearTimeout(timer);timer=setTimeout(refresh,0);});});form.querySelector('[data-report-clear]').addEventListener('click',function(){form.reset();q.focus();refresh();});
 document.addEventListener('click',function(e){if(e.target===modal){close();return;}var b=e.target.closest('[data-print-list]');if(b){e.preventDefault();refresh().then(function(ok){if(ok&&root()&&parseInt(root().getAttribute('data-total'),10)>0&&root().getAttribute('data-invalid')!=='1'){window.print();}});return;}b=e.target.closest('[data-print-fichas]');if(b){e.preventDefault();if(b.disabled){return;}var u=url(false);u.searchParams.set('imprimir_fichas','1');startPrint(u);return;}b=e.target.closest('[data-print-fichas-retry]');if(b){e.preventDefault();var u=url(false);u.searchParams.set('imprimir_fichas','1');startPrint(u);return;}var row=e.target.closest('[data-report-open][data-href]');if(row){e.preventDefault();returnFocus=row;document.getElementById('crm-client-modal-title').textContent='Ficha de '+row.cells[0].textContent.trim();modal.hidden=false;bodyOverflow=document.body.style.overflow;document.body.style.overflow='hidden';loader.hidden=false;frame.src=row.getAttribute('data-href');var closeButton=modal.querySelector('[data-client-close]');if(closeButton){closeButton.focus();}return;}if(e.target.closest('[data-client-close]')){close();return;}if(e.target.closest('[data-client-print],[data-client-pdf]')){e.preventDefault();try{frame.contentWindow.focus();frame.contentWindow.print();}catch(err){loader.textContent='No se pudo abrir el diálogo de impresión. Volvé a intentar cuando la ficha termine de cargar.';loader.hidden=false;}}});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&modal&&!modal.hidden){close();return;}var row=e.target.closest&&e.target.closest('[data-report-open][data-href]');if(row&&(e.key==='Enter'||e.key===' ')){e.preventDefault();row.click();}});
function close(){if(!modal||modal.hidden){return;}modal.hidden=true;frame.src='about:blank';document.body.style.overflow=bodyOverflow;if(returnFocus&&document.contains(returnFocus)){returnFocus.focus();}returnFocus=null;}
frame.addEventListener('load',function(){if(modal.hidden||frame.getAttribute('src')==='about:blank'){return;}try{var doc=frame.contentDocument;if(doc.querySelector('[data-client-ficha]')){loader.hidden=true;doc.addEventListener('keydown',function(event){if(event.key==='Escape'){event.preventDefault();close();}});return;}}catch(e){}loader.textContent='No se pudo cargar la ficha. Verificá que tu sesión siga activa y tengas autorización para consultar este cliente.';loader.hidden=false;});buttons(false);
})();
</script>
<style>
.crm-client-filters-grid{grid-template-columns:minmax(180px,1.7fr) minmax(120px,1fr) minmax(140px,1.1fr) minmax(100px,.8fr) minmax(125px,1fr) minmax(125px,1fr) auto}.crm-client-report-grid .grid-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.crm-client-report-grid .grid-title h4{margin:0}.crm-client-report-table th:nth-child(1){width:19%}.crm-client-report-table th:nth-child(2){width:10%}.crm-client-report-table th:nth-child(3){width:15%}.crm-client-report-table th:nth-child(4){width:18%}.crm-client-report-table th:nth-child(5){width:14%}.crm-client-report-table th:nth-child(6){width:9%}.crm-client-report-table th:nth-child(7){width:15%}
@media(max-width:1100px){.crm-client-filters-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:767px){.crm-client-filters-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media print{.rrhh-report-print-header,.rrhh-report-print-header .institution,.rrhh-report-print-header h1,.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{display:block!important;visibility:visible!important}.rrhh-report-print-header{padding:0 0 4px!important;margin:0 0 5px!important}.rrhh-report-print-header .institution,.rrhh-report-print-header h1,.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{color:#334155!important}.rrhh-report-print-header .institution{font-size:8.5pt!important}.rrhh-report-print-header h1{font-size:14pt!important;margin:1px 0!important}.rrhh-report-print-header .rrhh-report-meta,.rrhh-report-print-header .rrhh-report-filters-print{font-size:7.5pt!important}.crm-client-report-grid>.grid-title{display:none!important}.crm-client-report-table{width:100%!important;max-width:100%!important;table-layout:fixed!important;border-collapse:collapse!important;font-size:8pt!important}.crm-client-report-table th,.crm-client-report-table td{box-sizing:border-box!important;padding:2.5px 4px!important;line-height:1.16!important;white-space:normal!important;overflow-wrap:normal!important;word-break:normal!important;hyphens:none!important}.crm-client-report-table th:nth-child(1){width:24%!important}.crm-client-report-table th:nth-child(2){width:13%!important;white-space:nowrap!important}.crm-client-report-table th:nth-child(3){width:12%!important;white-space:nowrap!important;font-variant-numeric:tabular-nums}.crm-client-report-table th:nth-child(4){width:23%!important}.crm-client-report-table th:nth-child(5){width:11%!important}.crm-client-report-table th:nth-child(6){width:7%!important}.crm-client-report-table th:nth-child(7){width:10%!important}.crm-client-report-table thead{display:table-header-group!important}.crm-client-report-table tr{break-inside:avoid!important;page-break-inside:avoid!important}}
</style>
