<?php
require_once __DIR__.'/sistemas_dominios.php';
require_once __DIR__.'/informes-dominios-scope.php';

if(empty($puede_ver_dominios) || !crm_informes_dominios_puede_consultar(
	function_exists('acceso_area_activa') ? acceso_area_activa() : '',
	acceso_es_super() || acceso_es_coord_general(),
	function_exists('acceso_puede') && acceso_puede('sistemas')
))
{
	http_response_code(403);
	exit('No estás autorizado para consultar este informe en el área activa.');
}

$id_ficha_dominio=isset($_GET['dominio_ficha']) && is_scalar($_GET['dominio_ficha']) ? filter_var($_GET['dominio_ficha'],FILTER_VALIDATE_INT) : false;
$dominio_escapar=function($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); };
$dominio_fecha=function($value) {
	$value=trim((string)$value);
	if($value==='' || $value==='0000-00-00' || $value==='0000-00-00 00:00:00') { return 'No registrada'; }
	$time=strtotime($value);
	return $time===false ? 'No registrada' : date('d/m/Y',$time);
};
$dominio_fecha_valida=function($value) {
	$value=trim((string)$value);
	if($value==='') { return ''; }
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) { return false; }
	$parts=explode('-',$value);
	return checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) ? $value : false;
};
$dominio_estado=function($row) {
	return crm_dominio_calcular_estado(isset($row['fecha_vencimiento']) ? $row['fecha_vencimiento'] : '',isset($row['fecha_inicio']) ? $row['fecha_inicio'] : null);
};

// Individual ficha: expose only fields appropriate for a read-only report.
if(array_key_exists('dominio_ficha',$_GET))
{
	if($id_ficha_dominio===false || $id_ficha_dominio<=0) { http_response_code(404); exit('El dominio no existe o no está disponible.'); }
	$dominio_ficha=acceso_consulta_una(
		"SELECT id_dominio, nombre, proveedor, responsable, fecha_inicio, fecha_vencimiento, creado_en, actualizado_en, activo FROM dominio WHERE id_dominio=? AND activo=1 LIMIT 1",
		'i',array((int)$id_ficha_dominio)
	);
	if(!$dominio_ficha) { http_response_code(404); exit('El dominio no existe o no está disponible.'); }
	$estado_ficha=$dominio_estado($dominio_ficha);
	$renovaciones=acceso_consulta(
		"SELECT fecha_renovacion, fecha_vencimiento_anterior, fecha_vencimiento_nueva, creado_en FROM dominio_renovacion WHERE id_dominio=? ORDER BY fecha_renovacion DESC, id_renovacion DESC",
		'i',array((int)$id_ficha_dominio)
	);
	?>
<!doctype html><html lang="es" data-domain-ficha><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Ficha de dominio | ADEMI</title><style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#243247;font:15px/1.5 Arial,sans-serif}.domain-sheet{max-width:900px;margin:24px auto;background:#fff;border:1px solid #dce4ee;border-radius:12px;overflow:hidden;box-shadow:0 8px 28px rgba(20,40,70,.08)}.domain-head{padding:28px 32px;background:#edf5ff;border-bottom:1px solid #d8e4f2}.domain-brand{font-size:12px;letter-spacing:.12em;font-weight:700;color:#2863a6}.domain-head h1{margin:7px 0 0;font-size:27px;overflow-wrap:anywhere}.domain-content{padding:26px 32px}.domain-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 28px}.domain-field{padding-bottom:10px;border-bottom:1px solid #e8edf3;min-width:0}.domain-field strong{display:block;margin-bottom:4px;color:#718096;font-size:11px;letter-spacing:.06em;text-transform:uppercase}.domain-field span{overflow-wrap:anywhere}.domain-section{margin:26px 0 12px;font-size:17px}.domain-renewals{width:100%;border-collapse:collapse}.domain-renewals th,.domain-renewals td{text-align:left;padding:9px 10px;border:1px solid #dce4ee}.domain-renewals th{background:#f1f5f9}.domain-empty{color:#718096}.domain-status{display:inline-block;padding:3px 9px;border-radius:14px;background:#e9f1fb;color:#315d8c;font-size:12px;font-weight:700}@media(max-width:640px){.domain-sheet{margin:0;border-radius:0}.domain-head,.domain-content{padding:20px}.domain-grid{grid-template-columns:1fr;gap:12px}}
@page{size:A4 portrait;margin:14mm}@media print{html,body{background:#fff!important;color:#172033!important;font-size:10pt!important}.domain-sheet{max-width:none;margin:0;border:0;border-radius:0;box-shadow:none}.domain-head{padding:12mm 10mm 7mm;background:#eef5ff!important;print-color-adjust:exact;-webkit-print-color-adjust:exact}.domain-head h1{font-size:21pt}.domain-content{padding:8mm 10mm}.domain-grid{gap:5mm 12mm}.domain-field{padding-bottom:3mm}.domain-section{margin:8mm 0 4mm}.domain-renewals tr{break-inside:avoid;page-break-inside:avoid}}
</style></head><body><main class="domain-sheet"><header class="domain-head"><div class="domain-brand">ADEMI · SISTEMAS</div><h1><?php echo $dominio_escapar($dominio_ficha['nombre']); ?></h1></header><section class="domain-content"><div class="domain-grid">
<div class="domain-field"><strong>Estado</strong><span class="domain-status"><?php echo $dominio_escapar($estado_ficha['etiqueta']); ?></span></div>
<div class="domain-field"><strong>Proveedor</strong><span><?php echo $dominio_escapar(trim((string)$dominio_ficha['proveedor'])!=='' ? $dominio_ficha['proveedor'] : 'No registrado'); ?></span></div>
<div class="domain-field"><strong>Responsable</strong><span><?php echo $dominio_escapar(trim((string)$dominio_ficha['responsable'])!=='' ? $dominio_ficha['responsable'] : 'No registrado'); ?></span></div>
<div class="domain-field"><strong>Fecha de inicio</strong><span><?php echo $dominio_escapar($dominio_fecha($dominio_ficha['fecha_inicio'])); ?></span></div>
<div class="domain-field"><strong>Fecha de vencimiento</strong><span><?php echo $dominio_escapar($dominio_fecha($dominio_ficha['fecha_vencimiento'])); ?></span></div>
<div class="domain-field"><strong>Registrado</strong><span><?php echo $dominio_escapar($dominio_fecha($dominio_ficha['creado_en'])); ?></span></div>
</div>
<h2 class="domain-section">Historial de renovaciones</h2>
<?php if(!$renovaciones) { ?><p class="domain-empty">No hay renovaciones registradas.</p><?php } else { ?><table class="domain-renewals"><thead><tr><th>Renovación</th><th>Vencimiento anterior</th><th>Nuevo vencimiento</th></tr></thead><tbody><?php foreach($renovaciones as $renovacion) { ?><tr><td><?php echo $dominio_escapar($dominio_fecha($renovacion['fecha_renovacion'])); ?></td><td><?php echo $dominio_escapar($dominio_fecha($renovacion['fecha_vencimiento_anterior'])); ?></td><td><?php echo $dominio_escapar($dominio_fecha($renovacion['fecha_vencimiento_nueva'])); ?></td></tr><?php } ?></tbody></table><?php } ?>
</section></main></body></html>
	<?php
	exit();
}

$dominios_filtros=array(
	'q'=>isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string)$_GET['q']) : '',
	'estado'=>isset($_GET['estado']) && is_scalar($_GET['estado']) ? trim((string)$_GET['estado']) : '',
	'responsable'=>isset($_GET['responsable']) && is_scalar($_GET['responsable']) ? trim((string)$_GET['responsable']) : '',
	'proveedor'=>isset($_GET['proveedor']) && is_scalar($_GET['proveedor']) ? trim((string)$_GET['proveedor']) : '',
	'desde'=>isset($_GET['desde']) && is_scalar($_GET['desde']) ? trim((string)$_GET['desde']) : '',
	'hasta'=>isset($_GET['hasta']) && is_scalar($_GET['hasta']) ? trim((string)$_GET['hasta']) : ''
);
$dominios_filtros['q']=function_exists('mb_substr') ? mb_substr($dominios_filtros['q'],0,150,'UTF-8') : substr($dominios_filtros['q'],0,150);
$errores_dominios=array();
$desde_dominios=$dominio_fecha_valida($dominios_filtros['desde']);
$hasta_dominios=$dominio_fecha_valida($dominios_filtros['hasta']);
if($desde_dominios===false) { $errores_dominios[]='La fecha de vencimiento inicial no es válida.'; $desde_dominios=''; }
if($hasta_dominios===false) { $errores_dominios[]='La fecha de vencimiento final no es válida.'; $hasta_dominios=''; }
if($desde_dominios!=='' && $hasta_dominios!=='' && $desde_dominios>$hasta_dominios) { $errores_dominios[]='La fecha inicial no puede ser posterior a la fecha final.'; }
if(!in_array($dominios_filtros['estado'],array('vigente','proximo','vencido','indefinido'),true)) { $dominios_filtros['estado']=''; }
$responsables_dominios=acceso_consulta("SELECT DISTINCT responsable FROM dominio WHERE activo=1 AND responsable IS NOT NULL AND TRIM(responsable)<>'' ORDER BY responsable");
$proveedores_dominios=acceso_consulta("SELECT DISTINCT proveedor FROM dominio WHERE activo=1 AND proveedor IS NOT NULL AND TRIM(proveedor)<>'' ORDER BY proveedor");
$responsables_validos=array(); foreach($responsables_dominios as $v) { $responsables_validos[(string)$v['responsable']]=true; }
$proveedores_validos=array(); foreach($proveedores_dominios as $v) { $proveedores_validos[(string)$v['proveedor']]=true; }
if($dominios_filtros['responsable']!=='' && !isset($responsables_validos[$dominios_filtros['responsable']])) { $dominios_filtros['responsable']=''; }
if($dominios_filtros['proveedor']!=='' && !isset($proveedores_validos[$dominios_filtros['proveedor']])) { $dominios_filtros['proveedor']=''; }

$where_dominios=array('activo=1'); $types_dominios=''; $params_dominios=array();
if($dominios_filtros['q']!=='')
{
	$where_dominios[]='(nombre LIKE ? OR proveedor LIKE ? OR responsable LIKE ?)';
	$like_dominio='%'.$dominios_filtros['q'].'%';
	$types_dominios.='sss'; array_push($params_dominios,$like_dominio,$like_dominio,$like_dominio);
}
if($dominios_filtros['responsable']!=='') { $where_dominios[]='responsable=?'; $types_dominios.='s'; $params_dominios[]=$dominios_filtros['responsable']; }
if($dominios_filtros['proveedor']!=='') { $where_dominios[]='proveedor=?'; $types_dominios.='s'; $params_dominios[]=$dominios_filtros['proveedor']; }
if($desde_dominios!=='' && $desde_dominios!==false) { $where_dominios[]='fecha_vencimiento>=?'; $types_dominios.='s'; $params_dominios[]=$desde_dominios; }
if($hasta_dominios!=='' && $hasta_dominios!==false) { $where_dominios[]='fecha_vencimiento<=?'; $types_dominios.='s'; $params_dominios[]=$hasta_dominios; }
$sql_dominios="SELECT id_dominio, nombre, proveedor, responsable, fecha_inicio, fecha_vencimiento, creado_en, actualizado_en, activo FROM dominio WHERE ".implode(' AND ',$where_dominios)." ORDER BY fecha_vencimiento ASC, nombre ASC";
$filas_dominios=acceso_consulta($sql_dominios,$types_dominios,$params_dominios);
$filas_dominios=array_values(array_filter($filas_dominios,function($row) use ($dominios_filtros,$dominio_estado) { return $dominios_filtros['estado']==='' || $dominio_estado($row)['clave']===$dominios_filtros['estado']; }));
$total_dominios=count($filas_dominios);
$filtros_texto_dominios=array();
foreach(array('q'=>'Búsqueda','estado'=>'Estado','responsable'=>'Responsable','proveedor'=>'Proveedor','desde'=>'Vence desde','hasta'=>'Vence hasta') as $key=>$label) { if($dominios_filtros[$key]!=='') { $filtros_texto_dominios[]=$label.': '.$dominios_filtros[$key]; } }
$fecha_emision_dominios=date('d/m/Y');
$fecha_texto_dominios=$dominio_fecha;

$render_dominios_resultados=function() use ($filas_dominios,$total_dominios,$errores_dominios,$dominios_filtros,$filtros_texto_dominios,$fecha_emision_dominios,$dominio_escapar,$dominio_fecha,$dominio_estado,$ajax_resultados) {
?>
<div class="rrhh-report-async-response" id="rrhh-report-async-response" data-total="<?php echo $total_dominios; ?>" data-invalid="<?php echo $errores_dominios ? '1' : '0'; ?>">
<?php foreach($errores_dominios as $error) { ?><div class="alert alert-danger rrhh-report-async-error"><?php echo $dominio_escapar($error); ?></div><?php } ?>
<header class="rrhh-report-print-only rrhh-report-print-header"><div class="institution">ADEMI · INFORME INSTITUCIONAL</div><h1>Informe de dominios</h1><div class="rrhh-report-meta">Emitido el <?php echo $dominio_escapar($fecha_emision_dominios); ?></div><div class="rrhh-report-filters-print">Filtros: <?php echo $dominio_escapar($filtros_texto_dominios ? implode(' · ',$filtros_texto_dominios) : 'Todos los dominios activos'); ?></div></header>
<div class="grid simple rrhh-report-grid crm-domain-report-grid"><div class="grid-title"><h4>Resultados <span class="rrhh-report-total-count">Total de dominios: <?php echo $total_dominios; ?></span></h4><div class="rrhh-report-result-actions no-print"><button type="button" class="btn btn-white" data-domain-print-list><i class="fa fa-print"></i> Imprimir informe</button></div></div><div class="grid-body"><div class="table-responsive"><table class="table table-bordered table-hover rrhh-report-table crm-domain-report-table"><thead><tr><th>Dominio</th><th>Proveedor</th><th>Responsable</th><th>Registro</th><th>Vencimiento</th><th>Estado</th></tr></thead><tbody>
<?php if($total_dominios===0) { ?><tr><td colspan="6" class="rrhh-report-empty">No se encontraron dominios con los filtros seleccionados.</td></tr><?php } else { foreach($filas_dominios as $row) { $estado=$dominio_estado($row); $href=url_crm('informes.php?reporte=dominios&dominio_ficha='.intval($row['id_dominio'])); ?>
<tr class="rrhh-report-row-link" tabindex="0" role="button" data-domain-open data-href="<?php echo $dominio_escapar($href); ?>" aria-haspopup="dialog" aria-label="Abrir ficha de <?php echo $dominio_escapar($row['nombre']); ?>"><td><?php echo $dominio_escapar($row['nombre']); ?></td><td><?php echo $dominio_escapar(trim((string)$row['proveedor'])!=='' ? $row['proveedor'] : 'No registrado'); ?></td><td><?php echo $dominio_escapar(trim((string)$row['responsable'])!=='' ? $row['responsable'] : 'No registrado'); ?></td><td><?php echo $dominio_escapar($dominio_fecha($row['creado_en'])); ?></td><td><?php echo $dominio_escapar($dominio_fecha($row['fecha_vencimiento'])); ?></td><td><span class="crm-domain-status crm-domain-status-<?php echo $dominio_escapar($estado['clave']); ?>"><?php echo $dominio_escapar($estado['etiqueta']); ?></span></td></tr>
<?php } } ?></tbody></table></div><div class="rrhh-report-print-only rrhh-report-print-footer">ADEMI · Informe interno de dominios · Registros: <?php echo $total_dominios; ?></div></div></div></div>
<?php
};
if($ajax_resultados) { $render_dominios_resultados(); exit(); }
?>
<div class="rrhh-report-wrap crm-domain-report" data-domain-report>
<form method="get" action="<?php echo $dominio_escapar(url_crm('informes.php')); ?>" class="rrhh-report-filters" data-domain-filters><input type="hidden" name="reporte" value="dominios">
<div class="grid simple rrhh-report-grid no-print"><div class="grid-title no-border"><h4><i class="fa fa-filter"></i> Filtros</h4></div><div class="grid-body no-border"><div class="rrhh-report-filters-grid crm-domain-filters-grid">
<div class="form-group"><label for="domain-q">Buscar dominio</label><input id="domain-q" name="q" type="search" class="form-control" maxlength="150" value="<?php echo $dominio_escapar($dominios_filtros['q']); ?>" placeholder="Dominio, proveedor"></div>
<div class="form-group"><label for="domain-state">Estado</label><select id="domain-state" name="estado" class="form-control"><option value="">Todos</option><?php foreach(array('vigente'=>'Vencimiento lejano','proximo'=>'Por vencer','vencido'=>'Vencido','indefinido'=>'Sin fecha') as $key=>$label) { ?><option value="<?php echo $key; ?>" <?php echo $dominios_filtros['estado']===$key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></div>
<div class="form-group"><label for="domain-owner">Responsable</label><select id="domain-owner" name="responsable" class="form-control"><option value="">Todos</option><?php foreach($responsables_dominios as $option) { ?><option value="<?php echo $dominio_escapar($option['responsable']); ?>" <?php echo $dominios_filtros['responsable']===(string)$option['responsable'] ? 'selected' : ''; ?>><?php echo $dominio_escapar($option['responsable']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="domain-provider">Proveedor</label><select id="domain-provider" name="proveedor" class="form-control"><option value="">Todos</option><?php foreach($proveedores_dominios as $option) { ?><option value="<?php echo $dominio_escapar($option['proveedor']); ?>" <?php echo $dominios_filtros['proveedor']===(string)$option['proveedor'] ? 'selected' : ''; ?>><?php echo $dominio_escapar($option['proveedor']); ?></option><?php } ?></select></div>
<div class="form-group"><label for="domain-from">Vence desde</label><input id="domain-from" name="desde" type="date" class="form-control" value="<?php echo $dominio_escapar($dominios_filtros['desde']); ?>"></div>
<div class="form-group"><label for="domain-to">Vence hasta</label><input id="domain-to" name="hasta" type="date" class="form-control" value="<?php echo $dominio_escapar($dominios_filtros['hasta']); ?>"></div>
<div class="rrhh-report-actions"><button type="button" class="btn btn-white" data-domain-clear>Limpiar</button></div>
</div></div></div></form>
<div id="domain-report-fetch-error" class="alert alert-danger no-print" role="alert" hidden>No se pudieron actualizar los resultados.</div>
<?php $render_dominios_resultados(); ?>
</div>
<div class="rrhh-report-modal no-print" id="crm-domain-modal" role="dialog" aria-modal="true" aria-labelledby="crm-domain-modal-title" hidden><div class="rrhh-report-modal-dialog"><div class="rrhh-report-modal-head"><h2 id="crm-domain-modal-title">Ficha del dominio</h2><button type="button" class="btn btn-white btn-sm" data-domain-close aria-label="Cerrar ficha"><i class="fa fa-times"></i></button></div><div class="rrhh-report-modal-body"><div class="rrhh-report-modal-loading" data-domain-loading role="status">Cargando ficha…</div><iframe class="rrhh-report-modal-frame" data-domain-frame title="Ficha del dominio" src="about:blank"></iframe></div><div class="rrhh-report-modal-foot"><p class="rrhh-report-modal-hint">Para guardar como PDF, elegí esa opción en el diálogo de impresión.</p><button type="button" class="btn btn-white" data-domain-print><i class="fa fa-print"></i> Imprimir</button><button type="button" class="btn btn-primary" data-domain-pdf><i class="fa fa-file-pdf-o"></i> Guardar como PDF</button><button type="button" class="btn btn-white" data-domain-close>Cerrar</button></div></div></div>
<script>
(function(){
var form=document.querySelector('[data-domain-filters]'),modal=document.getElementById('crm-domain-modal'),frame=document.querySelector('[data-domain-frame]'),loader=document.querySelector('[data-domain-loading]'),timer=null,sequence=0,controller=null,bodyOverflow='',returnFocus=null;
if(!form||!modal||!frame){return;} if(modal.parentNode!==document.body){document.body.appendChild(modal);}
function root(){return document.getElementById('rrhh-report-async-response');}
function requestUrl(ajax){var u=new URL(form.action,location.href),p=new URLSearchParams(new FormData(form));if(ajax){p.set('ajax','resultados');}u.search=p.toString();return u;}
function refresh(){if(controller){controller.abort();}controller=typeof AbortController!=='undefined'?new AbortController():null;var n=++sequence;form.setAttribute('aria-busy','true');var options={credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}};if(controller){options.signal=controller.signal;}return fetch(requestUrl(true).toString(),options).then(function(response){if(!response.ok){throw new Error();}return response.text();}).then(function(html){if(n!==sequence){return false;}var doc=new DOMParser().parseFromString(html,'text/html'),next=doc.getElementById('rrhh-report-async-response'),old=root();if(!next||!old){throw new Error();}old.replaceWith(document.importNode(next,true));var error=document.getElementById('domain-report-fetch-error');if(error){error.hidden=true;}history.replaceState({},'',requestUrl(false).pathname+requestUrl(false).search);return true;}).catch(function(error){if(n===sequence&&error.name!=='AbortError'){var notice=document.getElementById('domain-report-fetch-error');if(notice){notice.hidden=false;}return false;}return false;}).then(function(ok){if(n===sequence){form.removeAttribute('aria-busy');}return ok;});}
function open(row){returnFocus=row;document.getElementById('crm-domain-modal-title').textContent='Ficha de '+row.cells[0].textContent.trim();modal.hidden=false;bodyOverflow=document.body.style.overflow;document.body.style.overflow='hidden';loader.hidden=false;loader.textContent='Cargando ficha…';frame.src=row.getAttribute('data-href');var closeButton=modal.querySelector('[data-domain-close]');if(closeButton){closeButton.focus();}}
function close(){if(modal.hidden){return;}modal.hidden=true;frame.src='about:blank';document.body.style.overflow=bodyOverflow;if(returnFocus&&document.contains(returnFocus)){returnFocus.focus();}returnFocus=null;}
form.querySelector('[name=q]').addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(refresh,350);});
form.querySelectorAll('select,input[type=date]').forEach(function(control){control.addEventListener('change',function(){clearTimeout(timer);timer=setTimeout(refresh,0);});});
form.querySelector('[data-domain-clear]').addEventListener('click',function(){form.querySelectorAll('input:not([type=hidden])').forEach(function(input){input.value='';});form.querySelectorAll('select').forEach(function(select){select.selectedIndex=0;});form.querySelector('[name=q]').focus();refresh();});
document.addEventListener('click',function(event){var row=event.target.closest('[data-domain-open][data-href]');if(row){event.preventDefault();open(row);return;}if(event.target.closest('[data-domain-close]')){close();return;}if(event.target.closest('[data-domain-print],[data-domain-pdf]')){event.preventDefault();try{frame.contentWindow.focus();frame.contentWindow.print();}catch(error){loader.textContent='No se pudo abrir la impresión. Esperá a que termine de cargar la ficha.';loader.hidden=false;}return;}if(event.target.closest('[data-domain-print-list]')){event.preventDefault();refresh().then(function(ok){if(ok&&root()&&parseInt(root().getAttribute('data-total'),10)>0&&root().getAttribute('data-invalid')!=='1'){window.print();}});}});
document.addEventListener('keydown',function(event){var row=event.target.closest&&event.target.closest('[data-domain-open][data-href]');if(row&&(event.key==='Enter'||event.key===' ')){event.preventDefault();open(row);}if(event.key==='Escape'&&!modal.hidden){close();}});
frame.addEventListener('load',function(){if(modal.hidden||frame.getAttribute('src')==='about:blank'){return;}try{if(frame.contentDocument.querySelector('[data-domain-ficha]')){loader.hidden=true;return;}}catch(error){}loader.textContent='No se pudo cargar la ficha. Verificá que tu sesión siga activa y tengas autorización.';loader.hidden=false;});
})();
</script>
<style>
.crm-domain-report .grid-title{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.crm-domain-report .grid-title h4{margin:0}.crm-domain-filters-grid{grid-template-columns:minmax(190px,1.8fr) minmax(140px,1fr) minmax(145px,1.1fr) minmax(145px,1.1fr) minmax(140px,1fr) minmax(140px,1fr) auto}.crm-domain-report-table th:nth-child(1){width:24%}.crm-domain-report-table th:nth-child(2){width:16%}.crm-domain-report-table th:nth-child(3){width:18%}.crm-domain-report-table th:nth-child(4){width:13%}.crm-domain-report-table th:nth-child(5){width:15%}.crm-domain-report-table th:nth-child(6){width:14%}.crm-domain-status{display:inline-block;padding:3px 8px;border-radius:14px;font-size:12px;font-weight:600;white-space:nowrap}.crm-domain-status-vigente{background:#e8f5ec;color:#26723a}.crm-domain-status-proximo{background:#fff3d5;color:#8a5b00}.crm-domain-status-vencido{background:#fde8e7;color:#a8322a}.crm-domain-status-indefinido{background:#eef1f5;color:#596579}.crm-domain-report .rrhh-report-row-link{cursor:pointer}.crm-domain-report .rrhh-report-row-link:focus{outline:2px solid #3976c5;outline-offset:-2px}.crm-domain-report .rrhh-report-modal-frame{width:100%;height:100%;min-height:480px;border:0;background:#fff}.crm-domain-report .rrhh-report-modal-body{overflow:auto}.crm-domain-report .rrhh-report-modal[hidden]{display:none!important}.rrhh-report-print-only{display:none}
@media(max-width:1100px){.crm-domain-filters-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:767px){.crm-domain-filters-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.crm-domain-filters-grid .rrhh-report-actions{grid-column:1/-1}.crm-domain-report .rrhh-report-modal-frame{min-height:360px}}
@media print{@page{size:A4 landscape;margin:10mm}.crm-domain-report .rrhh-report-table{table-layout:fixed!important}.crm-domain-report .rrhh-report-table th:nth-child(1){width:24%!important}.crm-domain-report .rrhh-report-table th:nth-child(2){width:16%!important}.crm-domain-report .rrhh-report-table th:nth-child(3){width:18%!important}.crm-domain-report .rrhh-report-table th:nth-child(4){width:13%!important}.crm-domain-report .rrhh-report-table th:nth-child(5){width:15%!important}.crm-domain-report .rrhh-report-table th:nth-child(6){width:14%!important}.crm-domain-report .rrhh-report-table th,.crm-domain-report .rrhh-report-table td{overflow-wrap:normal!important;word-break:normal!important}.crm-domain-report .rrhh-report-row-link{cursor:default!important}}
</style>
