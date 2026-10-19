<?php
require_once __DIR__.'/_init.php';
crm_proteger_staff(null, true);
require_once BASE_PATH.'/includes/actividades.php';
require_once BASE_PATH.'/includes/crm_extra.php';
require_once BASE_PATH.'/includes/calendario-institucional.php';
require_once BASE_PATH.'/includes/navegacion-contexto.php';

if(!crm_es_staff())
{
	http_response_code(401);
	exit('Iniciá sesión para consultar el calendario.');
}

$ci_areas_autorizadas=array();
if(function_exists('acceso_mis_areas_asignadas'))
{
	foreach((array)acceso_mis_areas_asignadas() as $area_asignada)
	{
		$clave=isset($area_asignada['clave']) ? trim((string)$area_asignada['clave']) : '';
		$nombre=isset($area_asignada['nombre']) ? trim((string)$area_asignada['nombre']) : '';
		if($clave!=='' && $nombre!=='' && preg_match('/^[a-z0-9_-]+$/i', $clave))
		{
			$ci_areas_autorizadas[$clave]=array('key'=>$clave,'name'=>$nombre);
		}
	}
}
$ci_contexto_general_token=isset($_GET['contexto_general']) && is_string($_GET['contexto_general']) && crm_navegacion_contexto_general_valido($_GET['contexto_general']) ? $_GET['contexto_general'] : '';
$ci_menu_activo='calendario_institucional';
$ci_navbar_path='';
$ci_navbar_area=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
if($ci_contexto_general_token!=='') { $ci_navbar_path=BASE_PATH.'/public/general/menu-seccion.php'; }
elseif($ci_navbar_area!=='' && $ci_navbar_area!=='admin' && is_file(BASE_PATH.'/public/'.$ci_navbar_area.'/menu-seccion.php')) { $ci_navbar_path=BASE_PATH.'/public/'.$ci_navbar_area.'/menu-seccion.php'; }
elseif(acceso_es_super() || acceso_es_coord_general()) { $ci_navbar_path=BASE_PATH.'/public/general/menu-seccion.php'; }

function crm_calendario_institucional_json($data, $status=200)
{
	http_response_code($status);
	header('Content-Type: application/json; charset=UTF-8');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
	exit();
}

if(isset($_GET['ajax']) && is_string($_GET['ajax']) && $_GET['ajax']==='1')
{
	if($_SERVER['REQUEST_METHOD']!=='GET') { crm_calendario_institucional_json(array('ok'=>false,'error'=>'Método no permitido.'),405); }
	$accion=isset($_GET['accion']) && is_string($_GET['accion']) ? $_GET['accion'] : 'eventos';
	if($accion==='detalle')
	{
		$id=isset($_GET['id']) && is_string($_GET['id']) && ctype_digit($_GET['id']) ? intval($_GET['id']) : 0;
		$detalle=crm_calendario_institucional_detalle_autorizado($id);
		if(!$detalle) { crm_calendario_institucional_json(array('ok'=>false,'error'=>'La actividad no existe o no está disponible para tu usuario.'),404); }
		crm_calendario_institucional_json(array('ok'=>true,'event'=>$detalle));
	}
	if($accion!=='eventos') { crm_calendario_institucional_json(array('ok'=>false,'error'=>'La consulta solicitada no es válida.'),400); }
	$filtros=array();
	foreach(array('area'=>'area','tipo'=>'tipo','desde'=>'desde','hasta'=>'hasta','rango_desde'=>'rango_desde','rango_hasta'=>'rango_hasta') as $parametro=>$clave)
	{
		if(isset($_GET[$parametro]) && !is_string($_GET[$parametro])) { crm_calendario_institucional_json(array('ok'=>false,'error'=>'Uno de los filtros no es válido.'),400); }
		$filtros[$clave]=isset($_GET[$parametro]) ? trim($_GET[$parametro]) : '';
		if(in_array($clave,array('desde','hasta','rango_desde','rango_hasta'),true) && $filtros[$clave]!=='' && !crm_calendario_institucional_fecha_valida($filtros[$clave]))
		{
			crm_calendario_institucional_json(array('ok'=>false,'error'=>'Una fecha no es válida.'),400);
		}
	}
	$eventos=crm_calendario_institucional_eventos_autorizados();
	$tipos_visibles=array();
	foreach($eventos as $evento)
	{
		if($evento['type']!=='') { $tipos_visibles[$evento['type']]=$evento['type']; }
	}
	if($filtros['area']!=='' && !isset($ci_areas_autorizadas[$filtros['area']])) { crm_calendario_institucional_json(array('ok'=>false,'error'=>'El área seleccionada no está autorizada.'),403); }
	if($filtros['tipo']!=='' && !isset($tipos_visibles[$filtros['tipo']])) { crm_calendario_institucional_json(array('ok'=>false,'error'=>'El tipo seleccionado no está disponible.'),400); }
	$filtrados=crm_calendario_institucional_filtrar($eventos,$filtros);
	crm_calendario_institucional_json(array(
		'ok'=>true,
		'events'=>$filtrados,
		'areas'=>array_values($ci_areas_autorizadas),
		'types'=>array_values($tipos_visibles)
	));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CRM | Calendario Institucional</title>
	<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
	<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css" />
	<link href="assets/css/style.css" rel="stylesheet" type="text/css" />
	<link href="assets/css/responsive.css" rel="stylesheet" type="text/css" />
	<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css" />
	<style>
		.ci-page-title { align-items:center; display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; margin:8px 0 14px; }
		.ci-filters { align-items:flex-end; display:flex; flex-wrap:wrap; gap:12px; margin-bottom:14px; }
		.ci-filter { flex:1 1 180px; min-width:150px; }
		.ci-filter label { display:block; font-size:12px; margin-bottom:4px; }
		.ci-filter input,.ci-filter select { width:100%; }
		.ci-filter-actions { align-self:flex-end; flex:0 0 auto; }
		.ci-filter-actions .btn { height:34px; }
		.ci-calendar-toolbar { margin-bottom:8px; overflow-x:auto; }
		.ci-toolbar-controls { align-items:center; display:flex; gap:6px; min-width:100%; width:max-content; white-space:nowrap; }
		.ci-period-selectors { align-items:center; display:flex; flex:0 0 auto; gap:6px; }
		.ci-period-selectors select { height:32px; }
		#ci-month { width:130px; }
		#ci-year { width:84px; }
		.ci-toolbar-arrow { height:32px; padding:0; width:34px; }
		.ci-toolbar-clear { height:32px; white-space:nowrap; }
		.ci-toolbar-views { align-items:center; display:flex; gap:6px; margin-left:auto; }
		.ci-toolbar-views .btn { height:32px; }
		.ci-range-title { color:#243447; font-size:18px; font-weight:600; margin:8px 0 12px; min-width:0; text-align:left; }
		.ci-view-button.active { background:#337ab7; border-color:#2e6da4; color:#fff; }
		.ci-calendar-wrap { overflow-x:auto; }
		.ci-month-grid { display:grid; grid-template-columns:repeat(7,minmax(96px,1fr)); min-width:672px; }
		.ci-weekday { background:#f4f7fa; border:1px solid #e5eaf0; color:#5c6875; font-size:11px; font-weight:700; padding:8px; text-align:center; text-transform:uppercase; }
		.ci-day-cell { background:#fff; border:1px solid #e5eaf0; cursor:pointer; min-height:108px; padding:6px; }
		.ci-day-cell:hover { background:#f8fbff; }
		.ci-day-cell.outside { background:#f8fafc; color:#9aa4ae; }
		.ci-day-cell.selected { box-shadow:inset 0 0 0 2px #337ab7; }
		.ci-day-select { background:transparent; border:0; border-radius:50%; color:#344054; cursor:pointer; font-weight:600; height:28px; width:28px; }
		.ci-day-select:hover,.ci-day-select:focus { background:#eaf3fb; outline:2px solid #337ab7; }
		.ci-day-cell.outside .ci-day-select { color:#9aa4ae; }
		.ci-cell-events { display:grid; gap:3px; margin-top:4px; }
		.ci-event-pill { background:#f2f6fa; border:0; border-left:3px solid var(--ci-area,#337ab7); border-radius:3px; color:#263849; cursor:pointer; display:block; font-size:11px; line-height:1.3; overflow:hidden; padding:4px 5px; text-align:left; text-overflow:ellipsis; white-space:nowrap; width:100%; }
		.ci-event-pill:hover,.ci-event-pill:focus { background:#e7f0f8; outline:2px solid #337ab7; }
		.ci-more { color:#64748b; font-size:10px; padding:1px 4px; }
		.ci-week-grid { display:grid; grid-template-columns:repeat(7,minmax(150px,1fr)); gap:8px; min-width:1080px; }
		.ci-week-day { background:#fff; border:1px solid #e5eaf0; border-radius:4px; min-height:190px; padding:8px; }
		.ci-week-day.selected { border-color:#337ab7; box-shadow:0 0 0 1px #337ab7; }
		.ci-week-head { align-items:center; border-bottom:1px solid #edf0f3; display:flex; justify-content:space-between; margin-bottom:7px; padding-bottom:5px; }
		.ci-agenda { display:grid; gap:8px; }
		.ci-agenda-item { background:#fff; border:1px solid #e5eaf0; border-left:4px solid var(--ci-area,#337ab7); border-radius:4px; padding:10px 12px; }
		.ci-agenda-item button { background:none; border:0; color:#243447; font-size:14px; font-weight:600; padding:0; text-align:left; }
		.ci-agenda-meta { color:#64748b; display:flex; flex-wrap:wrap; font-size:11px; gap:6px 14px; margin-top:5px; }
		.ci-year-grid { display:grid; gap:12px; grid-template-columns:repeat(3,minmax(210px,1fr)); }
		.ci-year-month { background:#fff; border:1px solid #e5eaf0; border-radius:4px; padding:9px; }
		.ci-year-month-head { align-items:center; border-bottom:1px solid #edf0f3; display:flex; justify-content:space-between; margin-bottom:5px; padding-bottom:5px; }
		.ci-year-month-head button { background:none; border:0; color:#243447; font-weight:600; padding:2px; }
		.ci-mini-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; text-align:center; }
		.ci-mini-grid span,.ci-mini-grid button { font-size:10px; padding:3px 0; }
		.ci-mini-grid .weekday { color:#8994a0; font-weight:600; }
		.ci-mini-day { background:none; border:0; border-radius:3px; color:#344054; cursor:pointer; position:relative; }
		.ci-mini-day.has-events:after { background:#337ab7; border-radius:50%; bottom:0; content:""; height:3px; left:calc(50% - 1.5px); position:absolute; width:3px; }
		.ci-mini-day:hover,.ci-mini-day:focus { background:#eaf3fb; outline:1px solid #337ab7; }
		.ci-selected-day { border-top:1px solid #e5eaf0; margin-top:14px; padding-top:12px; }
		.ci-selected-day h4 { align-items:center; color:#243447; display:flex; flex-wrap:wrap; gap:8px; justify-content:space-between; margin:0 0 9px; }
		.ci-area-legend { display:flex; flex-wrap:wrap; gap:7px 14px; margin-top:12px; }
		.ci-area-legend span { align-items:center; color:#536170; display:inline-flex; font-size:11px; gap:5px; }
		.ci-area-dot { border-radius:50%; height:9px; width:9px; }
		.ci-empty { background:#f7f9fb; border:1px dashed #d5dde5; border-radius:4px; color:#697586; padding:14px; text-align:center; }
		.ci-modal-backdrop { align-items:center; background:rgba(15,23,42,.62); display:flex; inset:0; justify-content:center; padding:18px; position:fixed; z-index:12000; }
		.ci-modal-backdrop[hidden] { display:none; }
		.ci-modal { background:#fff; border-radius:7px; box-shadow:0 16px 48px rgba(0,0,0,.28); max-height:90vh; max-width:640px; overflow:hidden; width:100%; }
		.ci-modal-head { align-items:center; background:#f4f8fc; border-bottom:1px solid #dce5ed; display:flex; justify-content:space-between; padding:13px 16px; }
		.ci-modal-head h3 { color:#243447; font-size:17px; font-weight:600; margin:0; }
		.ci-modal-close { background:none; border:0; color:#596777; font-size:20px; line-height:1; padding:2px 6px; }
		.ci-modal-content { max-height:calc(90vh - 60px); overflow:auto; padding:16px; }
		.ci-detail-grid { display:grid; gap:12px 18px; grid-template-columns:repeat(2,minmax(0,1fr)); }
		.ci-detail-item label { color:#7b8794; display:block; font-size:10px; font-weight:700; letter-spacing:.4px; margin-bottom:3px; text-transform:uppercase; }
		.ci-detail-item div { color:#334155; font-size:13px; overflow-wrap:anywhere; white-space:pre-wrap; }
		.ci-detail-description { border-top:1px solid #e8edf2; margin-top:14px; padding-top:12px; }
		.ci-error { color:#a94442; margin:8px 0; }
		@media (max-width:900px) { .ci-year-grid { grid-template-columns:repeat(2,minmax(210px,1fr)); } }
		@media (max-width:600px) { .ci-year-grid { grid-template-columns:1fr; } .ci-range-title { min-width:0; text-align:left; } .ci-detail-grid { grid-template-columns:1fr; } }
	</style>
</head>
<body>
<?php crm_header_staff(); ?>
<div class="page-container row">
	<?php crm_menu_staff(); ?>
	<div class="clearfix"></div>
</div>
</div>
<div class="page-content">
	<div class="clearfix"></div>
	<div class="content">
		<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Calendario Institucional</a></li></ul>
		<?php if($ci_navbar_path!=='') { $menu_activo=$ci_menu_activo; require $ci_navbar_path; } ?>
		<div class="ci-page-title"><h3><i class="fa fa-calendar"></i> Calendario Institucional</h3><span class="text-muted">Actividades disponibles según tus permisos actuales.</span></div>
		<div class="grid simple"><div class="grid-title"><h4>Filtros</h4></div><div class="grid-body">
			<div class="ci-filters">
				<div class="ci-filter"><label for="ci-area">Área</label><select id="ci-area" class="form-control"><option value="">Todas las áreas autorizadas</option><?php foreach($ci_areas_autorizadas as $area_opcion) { ?><option value="<?php echo htmlspecialchars($area_opcion['key'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($area_opcion['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php } ?></select></div>
				<div class="ci-filter"><label for="ci-type">Tipo de actividad</label><select id="ci-type" class="form-control"><option value="">Todos los tipos</option></select></div>
				<div class="ci-filter-actions"><button type="button" id="ci-clear-filters" class="btn btn-white"><i class="fa fa-times"></i> Limpiar</button></div>
			</div>
		</div></div>
		<div class="grid simple"><div class="grid-title"><h4>Calendario</h4></div><div class="grid-body">
			<div class="ci-calendar-toolbar"><div class="ci-toolbar-controls">
				<button type="button" class="btn btn-white btn-sm ci-toolbar-arrow" id="ci-prev" aria-label="Período anterior"><i class="fa fa-chevron-left"></i></button>
				<div class="ci-period-selectors"><select class="form-control input-sm" id="ci-month" aria-label="Mes del calendario"><option value="1">Enero</option><option value="2">Febrero</option><option value="3">Marzo</option><option value="4">Abril</option><option value="5">Mayo</option><option value="6">Junio</option><option value="7">Julio</option><option value="8">Agosto</option><option value="9">Septiembre</option><option value="10">Octubre</option><option value="11">Noviembre</option><option value="12">Diciembre</option></select><select class="form-control input-sm" id="ci-year" aria-label="Año del calendario"><?php $ci_year_now=intval(date('Y')); for($ci_year_option=max(1,$ci_year_now-5);$ci_year_option<=$ci_year_now+5;$ci_year_option++) { ?><option value="<?php echo $ci_year_option; ?>"><?php echo $ci_year_option; ?></option><?php } ?></select></div>
				<button type="button" class="btn btn-white btn-sm ci-toolbar-clear" id="ci-clear"><i class="fa fa-refresh"></i> Limpiar</button>
				<button type="button" class="btn btn-white btn-sm ci-toolbar-arrow" id="ci-next" aria-label="Período siguiente"><i class="fa fa-chevron-right"></i></button>
				<div class="ci-toolbar-views" aria-label="Vista del calendario"><button type="button" class="btn btn-white btn-sm ci-view-button" data-view="day">Día</button><button type="button" class="btn btn-white btn-sm ci-view-button" data-view="week">Semana</button><button type="button" class="btn btn-white btn-sm ci-view-button" data-view="month">Mes</button><button type="button" class="btn btn-white btn-sm ci-view-button" data-view="year">Año</button></div>
			</div></div>
			<h4 class="ci-range-title" id="ci-range-title"></h4>
			<div id="ci-error" class="ci-error" role="alert" hidden></div>
			<div id="ci-calendar" class="ci-calendar-wrap" aria-live="polite"></div>
			<div id="ci-selected-day" class="ci-selected-day"></div>
			<div id="ci-legend" class="ci-area-legend" aria-label="Referencias de áreas"></div>
		</div></div>
	</div>
</div>
<div class="ci-modal-backdrop" id="ci-modal" hidden>
	<div class="ci-modal" role="dialog" aria-modal="true" aria-labelledby="ci-modal-title">
		<div class="ci-modal-head"><h3 id="ci-modal-title">Detalle de actividad</h3><button type="button" class="ci-modal-close" id="ci-modal-x" aria-label="Cerrar">&times;</button></div>
		<div class="ci-modal-content"><div id="ci-detail-loading" class="text-muted">Cargando actividad…</div><div id="ci-detail-error" class="ci-error" hidden></div><div id="ci-detail" hidden></div></div>
	</div>
</div>
<script>
(function(){
	'use strict';
	var endpoint=<?php echo json_encode(url_crm('calendario-institucional.php'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
	var calendar=document.getElementById('ci-calendar'), areaFilter=document.getElementById('ci-area'), typeFilter=document.getElementById('ci-type');
	var titleEl=document.getElementById('ci-range-title');
	var selectedDayEl=document.getElementById('ci-selected-day'), legendEl=document.getElementById('ci-legend'), errorEl=document.getElementById('ci-error');
	var monthFilter=document.getElementById('ci-month'), yearFilter=document.getElementById('ci-year');
	var modal=document.getElementById('ci-modal'), modalTitle=document.getElementById('ci-modal-title'), detail=document.getElementById('ci-detail');
	var detailLoading=document.getElementById('ci-detail-loading'), detailError=document.getElementById('ci-detail-error');
	var view='month', current=new Date(), selected=new Date(), events=[], areas=[], types=[], requestVersion=0, palette=['#337ab7','#2e8b57','#8e5aa9','#d18b25','#c6535c','#168b91','#52647a','#b14f7b'];
	current.setHours(0,0,0,0); selected.setTime(current.getTime());
	function pad(n){return n<10?'0'+n:String(n);}
	function iso(d){return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate());}
	function fromIso(value){var p=String(value||'').slice(0,10).split('-');if(p.length!==3){return null;}var d=new Date(Number(p[0]),Number(p[1])-1,Number(p[2]));return isNaN(d.getTime())?null:d;}
	function plusDays(date,n){var d=new Date(date.getFullYear(),date.getMonth(),date.getDate());d.setDate(d.getDate()+n);return d;}
	function monday(date){var d=new Date(date.getFullYear(),date.getMonth(),date.getDate());d.setDate(d.getDate()-((d.getDay()+6)%7));return d;}
	function range(){if(view==='day'){return [new Date(current),new Date(current)];}if(view==='week'){var s=monday(current);return [s,plusDays(s,6)];}if(view==='year'){return [new Date(current.getFullYear(),0,1),new Date(current.getFullYear(),11,31)];}var first=new Date(current.getFullYear(),current.getMonth(),1),last=new Date(current.getFullYear(),current.getMonth()+1,0);return [monday(first),plusDays(last,(7-last.getDay())%7)];}
	function monthName(date,year){return date.toLocaleDateString('es-AR',{month:'long'}).replace(/^./,function(c){return c.toUpperCase();})+(year?' '+date.getFullYear():'');}
	function dateLabel(value){var d=fromIso(value);return d?d.toLocaleDateString('es-AR',{weekday:'long',day:'numeric',month:'long',year:'numeric'}):value;}
	function timeLabel(value){var s=String(value||'');return s.length>10?s.slice(11,16):'';}
	function esc(value){return String(value==null?'':value);}
	function areaColor(key){var idx=0;for(var i=0;i<areas.length;i++){if(areas[i].key===key){idx=i;break;}}return palette[idx%palette.length];}
	function eventForDay(day){return events.filter(function(ev){return ev.day===day;});}
	function setOptions(select,items,placeholder,keyField,labelField){var currentValue=select.value,desired=[{value:'',label:placeholder}];items.forEach(function(item){desired.push({value:String(typeof item==='string'?item:item[keyField]),label:String(typeof item==='string'?item:item[labelField])});});var unchanged=select.options.length===desired.length;if(unchanged){for(var i=0;i<desired.length;i++){if(select.options[i].value!==desired[i].value||select.options[i].textContent!==desired[i].label){unchanged=false;break;}}}if(unchanged){return;}select.innerHTML='';desired.forEach(function(item){var option=document.createElement('option');option.value=item.value;option.textContent=item.label;select.appendChild(option);});select.value=desired.some(function(item){return item.value===currentValue;})?currentValue:'';}
	function renderLegend(){legendEl.innerHTML='';areas.forEach(function(a){var item=document.createElement('span'),dot=document.createElement('i');dot.className='ci-area-dot';dot.style.backgroundColor=areaColor(a.key);item.appendChild(dot);item.appendChild(document.createTextNode(a.name));legendEl.appendChild(item);});}
	function eventButton(ev){var b=document.createElement('button');b.type='button';b.className='ci-event-pill';b.style.setProperty('--ci-area',areaColor(ev.area_key));b.textContent=ev.title||'Actividad sin título';b.title=(ev.area||'')+(timeLabel(ev.date)?' · '+timeLabel(ev.date):'');b.addEventListener('click',function(){openDetail(ev.id);});return b;}
	function renderMonth(){var first=new Date(current.getFullYear(),current.getMonth(),1),last=new Date(current.getFullYear(),current.getMonth()+1,0),start=monday(first),end=plusDays(last,(7-last.getDay())%7);titleEl.textContent=monthName(current,true);var grid=document.createElement('div');grid.className='ci-month-grid';['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'].forEach(function(w){var h=document.createElement('div');h.className='ci-weekday';h.textContent=w;grid.appendChild(h);});for(var d=new Date(start);d<=end;d=plusDays(d,1)){var day=iso(d),cell=document.createElement('div');cell.className='ci-day-cell'+(d.getMonth()!==current.getMonth()?' outside':'')+(day===iso(selected)?' selected':'');cell.addEventListener('click',(function(dayValue){return function(event){if(event.target.closest&&event.target.closest('.ci-event-pill')){return;}chooseDay(dayValue);};})(day));var pick=document.createElement('button');pick.type='button';pick.className='ci-day-select';pick.textContent=String(d.getDate());pick.setAttribute('aria-label','Ver actividades del '+dateLabel(day));pick.addEventListener('click',(function(dayValue){return function(event){event.stopPropagation();chooseDay(dayValue);};})(day));cell.appendChild(pick);var list=eventForDay(day),box=document.createElement('div');box.className='ci-cell-events';list.slice(0,2).forEach(function(ev){box.appendChild(eventButton(ev));});if(list.length>2){var more=document.createElement('div');more.className='ci-more';more.textContent='+'+(list.length-2)+' más';box.appendChild(more);}cell.appendChild(box);grid.appendChild(cell);}calendar.innerHTML='';calendar.appendChild(grid);}
	function renderWeek(){var start=monday(current);titleEl.textContent=start.toLocaleDateString('es-AR',{day:'numeric',month:'short'})+' – '+plusDays(start,6).toLocaleDateString('es-AR',{day:'numeric',month:'short',year:'numeric'});var grid=document.createElement('div');grid.className='ci-week-grid';for(var i=0;i<7;i++){var d=plusDays(start,i),day=iso(d),col=document.createElement('div');col.className='ci-week-day'+(day===iso(selected)?' selected':'');var head=document.createElement('div');head.className='ci-week-head';var label=document.createElement('strong');label.textContent=d.toLocaleDateString('es-AR',{weekday:'short',day:'numeric'});var select=document.createElement('button');select.type='button';select.className='btn btn-white btn-xs';select.textContent='Ver día';select.addEventListener('click',function(value){return function(){chooseDay(value);};}(day));head.appendChild(label);head.appendChild(select);col.appendChild(head);eventForDay(day).forEach(function(ev){col.appendChild(eventButton(ev));});if(eventForDay(day).length===0){var empty=document.createElement('span');empty.className='text-muted';empty.textContent='Sin actividades';col.appendChild(empty);}grid.appendChild(col);}calendar.innerHTML='';calendar.appendChild(grid);}
	function renderDay(){titleEl.textContent=dateLabel(iso(current));var list=eventForDay(iso(current));calendar.innerHTML='';if(!list.length){var empty=document.createElement('div');empty.className='ci-empty';empty.textContent='No hay actividades autorizadas para este día.';calendar.appendChild(empty);return;}var agenda=document.createElement('div');agenda.className='ci-agenda';list.forEach(function(ev){var item=document.createElement('div');item.className='ci-agenda-item';item.style.setProperty('--ci-area',areaColor(ev.area_key));var button=document.createElement('button');button.type='button';button.textContent=ev.title||'Actividad sin título';button.addEventListener('click',function(){openDetail(ev.id);});var meta=document.createElement('div');meta.className='ci-agenda-meta';[timeLabel(ev.date),ev.area,ev.type].forEach(function(v){if(v){var span=document.createElement('span');span.textContent=v;meta.appendChild(span);}});item.appendChild(button);item.appendChild(meta);agenda.appendChild(item);});calendar.appendChild(agenda);}
	function renderYear(){titleEl.textContent=String(current.getFullYear());var grid=document.createElement('div');grid.className='ci-year-grid';for(var m=0;m<12;m++){var month=new Date(current.getFullYear(),m,1),card=document.createElement('div');card.className='ci-year-month';var head=document.createElement('div');head.className='ci-year-month-head';var monthButton=document.createElement('button');monthButton.type='button';monthButton.textContent=monthName(month,false);monthButton.addEventListener('click',function(monthIndex){return function(){current=new Date(current.getFullYear(),monthIndex,1);view='month';selected=new Date(current);syncControls();loadAndRender();};}(m));var count=document.createElement('span');count.className='text-muted';count.textContent=events.filter(function(ev){return Number(ev.day.slice(5,7))===m+1;}).length+' act.';head.appendChild(monthButton);head.appendChild(count);card.appendChild(head);var mini=document.createElement('div');mini.className='ci-mini-grid';['L','M','X','J','V','S','D'].forEach(function(w){var wd=document.createElement('span');wd.className='weekday';wd.textContent=w;mini.appendChild(wd);});var start=monday(month),last=new Date(current.getFullYear(),m+1,0);for(var d=new Date(start);d<=plusDays(last,(7-last.getDay())%7);d=plusDays(d,1)){if(d.getMonth()!==m){mini.appendChild(document.createElement('span'));continue;}var day=iso(d),dayEvents=eventForDay(day),button=document.createElement('button');button.type='button';button.className='ci-mini-day'+(dayEvents.length?' has-events':'');button.textContent=String(d.getDate());button.title=dayEvents.length?dayEvents.length+' actividad(es)':'Sin actividades';button.addEventListener('click',function(value){return function(){chooseDay(value);};}(day));mini.appendChild(button);}card.appendChild(mini);grid.appendChild(card);}calendar.innerHTML='';calendar.appendChild(grid);}
	function renderSelectedDay(){var day=iso(selected),list=eventForDay(day);selectedDayEl.innerHTML='';var heading=document.createElement('h4'),text=document.createElement('span');text.textContent='Actividades del '+dateLabel(day);var count=document.createElement('span');count.className='label label-info';count.textContent=String(list.length);heading.appendChild(text);heading.appendChild(count);selectedDayEl.appendChild(heading);if(!list.length){var empty=document.createElement('div');empty.className='ci-empty';empty.textContent='No hay actividades autorizadas para el día seleccionado.';selectedDayEl.appendChild(empty);return;}var agenda=document.createElement('div');agenda.className='ci-agenda';list.forEach(function(ev){var item=document.createElement('div');item.className='ci-agenda-item';item.style.setProperty('--ci-area',areaColor(ev.area_key));var b=document.createElement('button');b.type='button';b.textContent=ev.title||'Actividad sin título';b.addEventListener('click',function(){openDetail(ev.id);});var meta=document.createElement('div');meta.className='ci-agenda-meta';[timeLabel(ev.date),ev.area,ev.type].forEach(function(v){if(v){var s=document.createElement('span');s.textContent=v;meta.appendChild(s);}});item.appendChild(b);item.appendChild(meta);agenda.appendChild(item);});selectedDayEl.appendChild(agenda);}
	function ensureYearOption(year){if(!Array.prototype.some.call(yearFilter.options,function(option){return Number(option.value)===year;})){var option=document.createElement('option');option.value=String(year);option.textContent=String(year);yearFilter.appendChild(option);var options=Array.prototype.slice.call(yearFilter.options).sort(function(a,b){return Number(a.value)-Number(b.value);});yearFilter.innerHTML='';options.forEach(function(item){yearFilter.appendChild(item);});}}
	function syncControls(){monthFilter.value=String(current.getMonth()+1);ensureYearOption(current.getFullYear());yearFilter.value=String(current.getFullYear());Array.prototype.forEach.call(document.querySelectorAll('[data-view]'),function(button){button.classList.toggle('active',button.getAttribute('data-view')===view);button.setAttribute('aria-pressed',button.getAttribute('data-view')===view?'true':'false');});}
	function setPeriod(month,year){var day=current.getDate(),lastDay=new Date(year,month,0).getDate();current=new Date(year,month-1,Math.min(day,lastDay));selected=new Date(current);loadAndRender();}
	function movePeriod(direction){if(view==='day'){current=plusDays(current,direction);}else if(view==='week'){current=plusDays(current,direction*7);}else{var monthOffset=view==='year'?0:direction,yearOffset=view==='year'?direction:0,targetMonth=current.getMonth()+monthOffset,targetYear=current.getFullYear()+yearOffset;if(targetMonth<0){targetMonth+=12;targetYear--;}else if(targetMonth>11){targetMonth-=12;targetYear++;}var day=current.getDate(),lastDay=new Date(targetYear,targetMonth+1,0).getDate();current=new Date(targetYear,targetMonth,Math.min(day,lastDay));}selected=new Date(current);loadAndRender();}
	function render(){if(view==='day'){renderDay();selectedDayEl.hidden=true;}else{selectedDayEl.hidden=false;if(view==='week'){renderWeek();}else if(view==='year'){renderYear();}else{renderMonth();}renderSelectedDay();}syncControls();}
	function loadAndRender(){var requestId=++requestVersion,r=range(),params=new URLSearchParams({ajax:'1',accion:'eventos',rango_desde:iso(r[0]),rango_hasta:iso(r[1]),area:areaFilter.value,tipo:typeFilter.value});errorEl.hidden=true;fetch(endpoint+'?'+params.toString(),{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(response){return response.json().then(function(data){if(!response.ok){throw new Error(data.error||'No se pudo consultar el calendario.');}return data;});}).then(function(data){if(requestId!==requestVersion){return;}events=data.events||[];areas=data.areas||[];types=data.types||[];setOptions(typeFilter,types,'Todos los tipos');renderLegend();render();}).catch(function(error){if(requestId!==requestVersion){return;}events=[];calendar.innerHTML='';selectedDayEl.innerHTML='';errorEl.textContent=error.message||'No se pudo consultar el calendario.';errorEl.hidden=false;});}
	function chooseDay(value){var d=fromIso(value);if(!d){return;}selected=new Date(d);current=new Date(d);view='day';loadAndRender();}
	function field(parent,label,value){var wrap=document.createElement('div');wrap.className='ci-detail-item';var name=document.createElement('label');name.textContent=label;var content=document.createElement('div');content.textContent=value||'No registrado';wrap.appendChild(name);wrap.appendChild(content);parent.appendChild(wrap);}
	function openDetail(id){modal.hidden=false;document.body.style.overflow='hidden';detail.hidden=true;detail.innerHTML='';detailLoading.hidden=false;detailError.hidden=true;modalTitle.textContent='Detalle de actividad';fetch(endpoint+'?ajax=1&accion=detalle&id='+encodeURIComponent(id),{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(response){return response.json().then(function(data){if(!response.ok){throw new Error(data.error||'La actividad no está disponible.');}return data;});}).then(function(data){var ev=data.event;modalTitle.textContent=ev.title||'Detalle de actividad';detail.innerHTML='';var grid=document.createElement('div');grid.className='ci-detail-grid';field(grid,'Área responsable',ev.area);field(grid,'Tipo de actividad',ev.type);field(grid,'Fecha',dateLabel(ev.date)+(timeLabel(ev.date)?' · '+timeLabel(ev.date):''));field(grid,'Estado',ev.status);field(grid,'Responsable',ev.responsible);detail.appendChild(grid);var description=document.createElement('div');description.className='ci-detail-description ci-detail-item';var label=document.createElement('label');label.textContent='Descripción';var text=document.createElement('div');text.textContent=ev.description||'No hay una descripción registrada.';description.appendChild(label);description.appendChild(text);detail.appendChild(description);detail.hidden=false;detailLoading.hidden=true;document.getElementById('ci-modal-x').focus();}).catch(function(error){detailLoading.hidden=true;detailError.textContent=error.message||'No se pudo consultar la actividad.';detailError.hidden=false;});}
	function closeModal(){modal.hidden=true;document.body.style.overflow='';}
	document.getElementById('ci-modal-x').addEventListener('click',closeModal);modal.addEventListener('click',function(event){if(event.target===modal){closeModal();}});document.addEventListener('keydown',function(event){if(event.key==='Escape'&&!modal.hidden){closeModal();}});
	document.getElementById('ci-prev').addEventListener('click',function(){movePeriod(-1);});
	document.getElementById('ci-next').addEventListener('click',function(){movePeriod(1);});
	monthFilter.addEventListener('change',function(){setPeriod(Number(monthFilter.value),current.getFullYear());});
	yearFilter.addEventListener('change',function(){setPeriod(current.getMonth()+1,Number(yearFilter.value));});
	Array.prototype.forEach.call(document.querySelectorAll('[data-view]'),function(button){button.addEventListener('click',function(){view=button.getAttribute('data-view');selected=new Date(current);loadAndRender();});});
	[areaFilter,typeFilter].forEach(function(control){control.addEventListener('change',loadAndRender);});
	function clearCalendar(){areaFilter.value='';typeFilter.value='';current=new Date();current.setHours(0,0,0,0);selected=new Date(current);view='month';loadAndRender();}
	document.getElementById('ci-clear').addEventListener('click',clearCalendar);
	document.getElementById('ci-clear-filters').addEventListener('click',clearCalendar);
	loadAndRender();
})();
</script>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
