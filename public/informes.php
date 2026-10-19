<?php
require_once __DIR__.'/_init.php';
crm_proteger_staff();
require_once BASE_PATH.'/includes/informes-autorizacion.php';
$crm_informe_permisos=crm_informes_autorizaciones_usuario();
extract($crm_informe_permisos, EXTR_SKIP);
if(!$crm_informe_tiene_permiso_nativo)
{
	header('Location: '.acceso_destino_login());
	exit();
}
$reporte=isset($_GET['reporte']) && is_scalar($_GET['reporte']) ? trim((string)$_GET['reporte']) : '';
$reporte_no_disponible=($reporte!=='' && !in_array($reporte, array('colaboradores','clientes','actividades','tareas','atenciones','dominios'), true));
if($reporte==='colaboradores' && !$puede_ver_colaboradores)
{
    header('Location: '.acceso_destino_login());
    exit();
}
if($reporte==='clientes' && !$puede_ver_clientes)
{
    http_response_code(403);
    exit('No estás autorizado para consultar este informe.');
}
if($reporte==='actividades' && !$puede_ver_actividades)
{
    http_response_code(403);
    exit('No estás autorizado para consultar este informe.');
}
if($reporte==='tareas' && !$puede_ver_tareas)
{
	http_response_code(403);
	exit('No estás autorizado para consultar este informe.');
}
if($reporte==='atenciones' && !$puede_ver_atenciones)
{
	http_response_code(403);
	exit('No estás autorizado para consultar este informe.');
}
if($reporte==='dominios' && !$puede_ver_dominios)
{
	http_response_code(403);
	exit('No estás autorizado para consultar este informe en el área activa.');
}
if($reporte_no_disponible)
{
    $reporte='';
}
if($reporte==='' && !$reporte_no_disponible)
{
	$reporte=$puede_ver_colaboradores ? 'colaboradores' : ($puede_ver_clientes ? 'clientes' : ($puede_ver_actividades ? 'actividades' : ($puede_ver_tareas ? 'tareas' : ($puede_ver_atenciones ? 'atenciones' : ''))));
}
$impresion_fichas=false;
$cliente_ficha=false;
$ajax_resultados=isset($_GET['ajax']) && is_scalar($_GET['ajax']) && $_GET['ajax']==='resultados';
if($ajax_resultados)
{
	if($_SERVER['REQUEST_METHOD']!=='GET' || !in_array($reporte, array('colaboradores','clientes','actividades','tareas','atenciones','dominios'), true) || ($reporte==='colaboradores' && !$puede_ver_colaboradores) || ($reporte==='clientes' && !$puede_ver_clientes) || ($reporte==='actividades' && !$puede_ver_actividades) || ($reporte==='tareas' && !$puede_ver_tareas) || ($reporte==='atenciones' && !$puede_ver_atenciones) || ($reporte==='dominios' && !$puede_ver_dominios))
    {
        http_response_code(403);
        exit();
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	require BASE_PATH.'/includes/informes-'.$reporte.'.php';
    exit();
}
$impresion_fichas=isset($_GET['imprimir_fichas']) && is_scalar($_GET['imprimir_fichas']) && $_GET['imprimir_fichas']==='1';
$cliente_ficha=isset($_GET['cliente_ficha']) && is_scalar($_GET['cliente_ficha']) && $_GET['cliente_ficha']!=='0';
if($cliente_ficha)
{
    if($_SERVER['REQUEST_METHOD']!=='GET' || $reporte!=='clientes' || !$puede_ver_clientes)
    {
        http_response_code(403);
        exit('No estás autorizado para consultar este cliente.');
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    require BASE_PATH.'/includes/informes-clientes.php';
    exit();
}
$actividad_ficha=isset($_GET['actividad_ficha']) && is_scalar($_GET['actividad_ficha']) && $_GET['actividad_ficha']!=='0';
if($actividad_ficha)
{
    if($_SERVER['REQUEST_METHOD']!=='GET' || $reporte!=='actividades' || !$puede_ver_actividades)
    {
        http_response_code(403);
        exit('No estás autorizado para consultar esta actividad.');
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    require BASE_PATH.'/includes/informes-actividades.php';
    exit();
}
$tarea_ficha=isset($_GET['tarea_ficha']) && is_scalar($_GET['tarea_ficha']) && $_GET['tarea_ficha']!=='0';
if($tarea_ficha)
{
	if($_SERVER['REQUEST_METHOD']!=='GET' || $reporte!=='tareas' || !$puede_ver_tareas)
	{
		http_response_code(403);
		exit('No estás autorizado para consultar esta tarea.');
	}
	header('Content-Type: text/html; charset=UTF-8');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	require BASE_PATH.'/includes/informes-tareas.php';
	exit();
}
$atencion_ficha=isset($_GET['atencion_ficha']) && is_scalar($_GET['atencion_ficha']) && $_GET['atencion_ficha']!=='0';
if($atencion_ficha)
{
	if($_SERVER['REQUEST_METHOD']!=='GET' || $reporte!=='atenciones' || !$puede_ver_atenciones)
	{
		http_response_code(403);
		exit('No estás autorizado para consultar esta atención.');
	}
	header('Content-Type: text/html; charset=UTF-8');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	require BASE_PATH.'/includes/informes-atenciones.php';
	exit();
}
$dominio_ficha=isset($_GET['dominio_ficha']) && is_scalar($_GET['dominio_ficha']) && $_GET['dominio_ficha']!=='0';
if($dominio_ficha)
{
	if($_SERVER['REQUEST_METHOD']!=='GET' || $reporte!=='dominios' || !$puede_ver_dominios)
	{
		http_response_code(403);
		exit('No estás autorizado para consultar este dominio.');
	}
	header('Content-Type: text/html; charset=UTF-8');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	require BASE_PATH.'/includes/informes-dominios.php';
	exit();
}
if($impresion_fichas)
{
	if($_SERVER['REQUEST_METHOD']!=='GET' || !in_array($reporte, array('colaboradores','clientes','actividades','tareas'), true) || ($reporte==='colaboradores' && (!$puede_ver_colaboradores || !acceso_puede_gestionar('rrhh'))) || ($reporte==='clientes' && !$puede_ver_clientes) || ($reporte==='actividades' && !$puede_ver_actividades) || ($reporte==='tareas' && !$puede_ver_tareas))
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Impresión no disponible</title><body><p>No estás autorizado para imprimir estas fichas.</p></body></html>';
        exit();
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    require BASE_PATH.'/includes/informes-'.$reporte.'.php';
    exit();
}
$menu_activo='informes';
$navbar_area=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
$navbar_path='';
if(acceso_es_super() || acceso_es_coord_general())
{
    $navbar_path=BASE_PATH.'/public/general/menu-seccion.php';
}
elseif($puede_ver_colaboradores)
{
    $navbar_path=BASE_PATH.'/public/rrhh/menu-seccion.php';
}
elseif($navbar_area!=='' && $navbar_area!=='admin' && is_file(BASE_PATH.'/public/'.$navbar_area.'/menu-seccion.php'))
{
    $navbar_path=BASE_PATH.'/public/'.$navbar_area.'/menu-seccion.php';
}
elseif($puede_ver_presidencia)
{
    $navbar_path=BASE_PATH.'/public/presidencia/menu-seccion.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>CRM | Informes</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.crm-informes-heading{margin:0 0 12px}.crm-informes-heading h3{margin:0 0 4px}.crm-informes-description{margin:0;color:#64748b;font-size:13px}
.crm-informes-tabs-card{margin:0 0 14px;padding:8px 10px;border:1px solid #dce3eb;border-radius:7px;background:#fff;box-shadow:0 1px 3px rgba(24,39,58,.05)}.crm-informes-tabs{display:flex;justify-content:flex-start;align-items:center;gap:7px;flex-wrap:wrap;margin:0}.crm-informes-tabs .disabled{opacity:.55;cursor:not-allowed}
.crm-informes-other{margin:-8px 0 12px;text-align:right}
.rrhh-report-wrap{width:100%;max-width:none;margin:0 auto}.rrhh-report-filters .form-group{margin-bottom:0}.rrhh-report-count{font-size:15px;font-weight:600;margin:0 0 10px}.rrhh-report-table{width:100%;table-layout:auto}.rrhh-report-table th,.rrhh-report-table td{vertical-align:middle!important}.rrhh-report-table th{white-space:nowrap}.rrhh-report-table td{overflow-wrap:anywhere}.rrhh-report-empty{text-align:center;padding:28px!important;color:#64748b}.rrhh-report-print-only{display:none}
.rrhh-report-filters-grid{display:grid;grid-template-columns:minmax(130px,1.25fr) minmax(80px,.75fr) minmax(92px,.9fr) minmax(105px,1fr) minmax(95px,.9fr) minmax(120px,1.1fr) minmax(120px,1.1fr) auto;gap:8px;align-items:end}
.rrhh-report-filters-grid .form-group{min-width:0}.rrhh-report-filters-grid label{display:flex;align-items:flex-end;min-height:28px;margin-bottom:4px;font-size:11px;line-height:1.2}.rrhh-report-filters-grid .form-control{height:32px;padding:5px 7px;font-size:12px}
.rrhh-report-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px;min-height:32px;white-space:nowrap}.rrhh-report-actions .btn{padding:6px 9px;font-size:12px}
.rrhh-report-results-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.rrhh-report-results-heading h4{margin:0}.rrhh-report-total-count{margin-left:8px;color:#475569;font-size:13px;font-weight:600}.rrhh-report-result-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.rrhh-report-result-actions .btn{white-space:nowrap}.rrhh-report-print-fichas-status{color:#64748b;font-size:12px}.rrhh-report-print-fichas-status[hidden],[data-print-fichas-retry][hidden]{display:none!important}.rrhh-report-row-link{cursor:pointer}.rrhh-report-row-link:hover{background:#f5f9ff!important}.rrhh-report-row-link:focus{outline:2px solid #2563eb;outline-offset:-2px}
.rrhh-report-print-footer{display:none}
.rrhh-report-async-response{display:contents}.rrhh-report-async-error{margin:0 0 12px}.rrhh-report-modal[hidden]{display:none!important}.rrhh-report-modal{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;width:100vw;height:100vh;height:100dvh;box-sizing:border-box;overflow:hidden;padding:22px;background:rgba(15,23,42,.68)}.rrhh-report-modal-dialog{display:flex;flex-direction:column;width:min(1120px,100%);height:min(900px,calc(100vh - 44px));height:min(900px,calc(100dvh - 44px));max-height:calc(100dvh - 44px);min-height:0;box-sizing:border-box;overflow:hidden;background:#fff;border:1px solid #d6deea;border-radius:12px;box-shadow:0 24px 70px rgba(0,0,0,.3)}.rrhh-report-modal-head,.rrhh-report-modal-foot{display:flex;flex:0 0 auto;align-items:center;justify-content:space-between;gap:12px;padding:11px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0}.rrhh-report-modal-head h2{margin:0;font-size:16px;font-weight:700;color:#23364d}.rrhh-report-modal-foot{justify-content:flex-end;border-top:1px solid #e2e8f0;border-bottom:0}.rrhh-report-modal-body{position:relative;flex:1 1 auto;min-height:0;overflow:auto;background:#fff;overscroll-behavior:contain}.rrhh-report-modal-frame{display:block;width:100%;height:100%;min-height:0;border:0;background:#fff}.rrhh-report-modal-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;color:#64748b}.rrhh-report-modal-loading[hidden]{display:none!important}.rrhh-report-modal-hint{margin:0 auto 0 0;color:#64748b;font-size:12px}
@page{size:A4 landscape;margin:8mm 10mm}
@media print{html,body{display:block!important;width:auto!important;height:auto!important;min-height:0!important;margin:0!important;padding:0!important;overflow:visible!important;background:#fff!important;color:#1f2937!important;font-size:9pt!important}.page-container,.page-content,.content{position:static!important;float:none!important;display:block!important;overflow:visible!important;height:auto!important;min-height:0!important;margin:0!important;padding:0!important;width:100%!important;background:#fff!important}.page-sidebar,.header,.navbar,.breadcrumb,.crm-menu-seccion,.crm-informes-tabs-card,.crm-informes-other,.rrhh-report-filters,.page-title,.no-print{display:none!important}.crm-informes-heading{display:none!important}.rrhh-report-wrap{display:block!important;position:static!important;max-width:none!important;margin:0!important;width:100%!important;overflow:visible!important}.rrhh-report-print-only{display:block!important}.rrhh-report-print-header{padding:0 0 5px;margin:0 0 6px;border:0!important}.rrhh-report-print-header .institution{font-size:9pt;letter-spacing:.12em;color:#334155;font-weight:700}.rrhh-report-print-header h1{font-size:15pt;margin:2px 0}.rrhh-report-meta{font-size:8pt;color:#475569}.rrhh-report-grid{border:0!important;box-shadow:none!important;margin:0!important;width:100%!important;overflow:visible!important}.rrhh-report-grid .grid-title{display:none!important}.rrhh-report-grid .grid-body{padding:0!important}.rrhh-report-grid .table-responsive{display:block!important;overflow:visible!important;width:100%!important;max-width:100%!important;border:0!important}.rrhh-report-table{display:table!important;width:100%!important;max-width:100%!important;table-layout:fixed!important;font-size:7.5pt;border-collapse:collapse!important;white-space:normal!important}.rrhh-report-table th,.rrhh-report-table td{box-sizing:border-box!important;padding:3px 4px!important;border:1px solid #cbd5e1!important;white-space:normal!important;overflow-wrap:anywhere!important;word-break:normal!important;line-height:1.15!important}.rrhh-report-table th{background:#eef1f5!important;color:#334155!important;font-weight:700!important;print-color-adjust:exact;-webkit-print-color-adjust:exact}.rrhh-report-table th:nth-child(1){width:19%}.rrhh-report-table th:nth-child(2){width:9%}.rrhh-report-table th:nth-child(3){width:14%}.rrhh-report-table th:nth-child(4){width:13%}.rrhh-report-table th:nth-child(5){width:13%}.rrhh-report-table th:nth-child(6){width:12%}.rrhh-report-table th:nth-child(7){width:10%}.rrhh-report-table th:nth-child(8){width:10%}.rrhh-report-table thead{display:table-header-group}.rrhh-report-table tr{break-inside:avoid!important;page-break-inside:avoid!important}.rrhh-report-row-link{cursor:default!important;outline:0!important;background:transparent!important}.rrhh-report-count{display:none!important}.rrhh-report-filters-print{font-size:8pt;margin:4px 0}.rrhh-report-empty{padding:8px!important}.rrhh-report-print-footer{display:block!important;margin-top:5px;padding-top:4px;border-top:1px solid #cbd5e1;font-size:7.5pt;color:#64748b;break-inside:avoid;page-break-inside:avoid}}
@media(max-width:1100px){.rrhh-report-filters-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.rrhh-report-actions{justify-content:flex-start}}
@media(max-width:767px){.crm-informes-tabs-card{padding:7px}.crm-informes-tabs{justify-content:flex-start}.rrhh-report-filters-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.rrhh-report-filters-grid .form-control{font-size:13px}.rrhh-report-actions{grid-column:1/-1}.rrhh-report-results-heading{align-items:flex-start}.rrhh-report-result-actions{justify-content:flex-start}.rrhh-report-table{display:block;overflow-x:auto;white-space:nowrap}.rrhh-report-modal{padding:0}.rrhh-report-modal-dialog{width:100%;height:100%;height:100dvh;max-height:100dvh;min-height:0;border-radius:0}.rrhh-report-modal-head,.rrhh-report-modal-foot{padding:9px 12px}.rrhh-report-modal-foot{gap:6px;flex-wrap:wrap}.rrhh-report-modal-hint{flex-basis:100%;order:2}}
</style>
</head>
<body>
<?php crm_header_staff(); ?>
<div class="page-container row">
<?php crm_menu_staff(); ?>
<div class="clearfix"></div>
</div>
</div>
<div class="page-content"><div class="clearfix"></div><div class="content">
<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Informes</a></li></ul>
<?php if($navbar_path!=='') { require $navbar_path; } ?>
<div class="crm-informes-heading"><div class="page-title"><h3>Informes</h3></div><p class="crm-informes-description">Consultá, filtrá e imprimí información de las distintas áreas</p></div>
<div class="crm-informes-tabs-card"><div class="crm-informes-tabs" aria-label="Informes disponibles">
<?php if($puede_ver_colaboradores) { ?><a class="btn <?php echo $reporte==='colaboradores' ? 'btn-primary' : 'btn-white'; ?>" href="<?php echo htmlspecialchars(url_crm('informes.php?reporte=colaboradores'), ENT_QUOTES, 'UTF-8'); ?>">Colaboradores</a><?php } ?>
<?php if($puede_ver_clientes) { ?><a class="btn <?php echo $reporte==='clientes' ? 'btn-primary' : 'btn-white'; ?>" href="<?php echo htmlspecialchars(url_crm('informes.php?reporte=clientes'), ENT_QUOTES, 'UTF-8'); ?>">Clientes</a><?php } ?>
<?php if($puede_ver_actividades) { ?><a class="btn <?php echo $reporte==='actividades' ? 'btn-primary' : 'btn-white'; ?>" href="<?php echo htmlspecialchars(url_crm('informes.php?reporte=actividades'), ENT_QUOTES, 'UTF-8'); ?>">Actividades</a><?php } ?>
<?php if($puede_ver_tareas) { ?><a class="btn <?php echo $reporte==='tareas' ? 'btn-primary' : 'btn-white'; ?>" href="<?php echo htmlspecialchars(url_crm('informes.php?reporte=tareas'), ENT_QUOTES, 'UTF-8'); ?>">Tareas</a><?php } ?>
<?php if($puede_ver_atenciones) { ?><a class="btn <?php echo $reporte==='atenciones' ? 'btn-primary' : 'btn-white'; ?>" href="<?php echo htmlspecialchars(url_crm('informes.php?reporte=atenciones'), ENT_QUOTES, 'UTF-8'); ?>">Atenciones</a><?php } ?>
<?php if($puede_ver_dominios) { ?><a class="btn <?php echo $reporte==='dominios' ? 'btn-primary' : 'btn-white'; ?>" href="<?php echo htmlspecialchars(url_crm('informes.php?reporte=dominios'), ENT_QUOTES, 'UTF-8'); ?>">Dominios</a><?php } ?>
</div></div>
<?php if($puede_ver_presidencia) { ?><div class="crm-informes-other"><a class="btn btn-link" href="<?php echo htmlspecialchars(url_crm('admin/informes-presidencia.php'), ENT_QUOTES, 'UTF-8'); ?>">Ir a Informes para Presidencia <i class="fa fa-arrow-right"></i></a></div><?php } ?>
<?php if($reporte==='colaboradores' && $puede_ver_colaboradores) { require BASE_PATH.'/includes/informes-colaboradores.php'; } elseif($reporte_no_disponible) { ?>
<div class="alert alert-info">Este informe todavía no está disponible.</div>
<?php } elseif($reporte==='clientes' && $puede_ver_clientes) { require BASE_PATH.'/includes/informes-clientes.php'; } elseif($reporte==='actividades' && $puede_ver_actividades) { require BASE_PATH.'/includes/informes-actividades.php'; } elseif($reporte==='tareas' && $puede_ver_tareas) { require BASE_PATH.'/includes/informes-tareas.php'; } elseif($reporte==='atenciones' && $puede_ver_atenciones) { require BASE_PATH.'/includes/informes-atenciones.php'; } elseif($reporte==='dominios' && $puede_ver_dominios) { require BASE_PATH.'/includes/informes-dominios.php'; } elseif(!$puede_ver_colaboradores && !$puede_ver_clientes && !$puede_ver_actividades && !$puede_ver_tareas && !$puede_ver_atenciones && !$puede_ver_dominios && $puede_ver_presidencia) { ?>
<div class="alert alert-info">Los informes disponibles para tu perfil se encuentran en Informes para Presidencia.</div>
<?php } elseif(!$puede_ver_colaboradores && !$puede_ver_clientes && !$puede_ver_actividades && !$puede_ver_tareas && !$puede_ver_atenciones && !$puede_ver_dominios) { ?>
<div class="alert alert-info">No hay informes habilitados para el área activa que estén disponibles para tu perfil.</div>
<?php } ?>
</div></div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
</body></html>
