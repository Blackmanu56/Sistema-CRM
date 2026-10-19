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
$legajo_categorias=crm_rrhh_legajo_categorias();
$legajo_documentos=$legajo_disponible && $puede_gestionar_legajo ? crm_rrhh_legajo_listar($foto_id) : array();
$legajo_error_consulta=($legajo_documentos===false);
if($legajo_error_consulta) { $legajo_documentos=array(); }

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
	html, body { width:auto !important; min-width:0 !important; height:auto !important; margin:0 !important; padding:0 !important; background:#fff !important; color:#222 !important; font-size:10pt !important; }
	.header, #main-menu, .page-sidebar, .page-sidebar-wrapper, .crm-menu-seccion, .breadcrumb, .rrhh-no-print, .page-title, .footer, #footer { display:none !important; }
	.page-container, .page-content, .content, .rrhh-ficha-page { display:block !important; position:static !important; width:100% !important; min-width:0 !important; max-width:none !important; height:auto !important; min-height:0 !important; margin:0 !important; padding:0 !important; float:none !important; overflow:visible !important; }
	.rrhh-ficha { width:100% !important; max-width:none !important; margin:0 !important; border:1px solid #dce3eb !important; border-radius:8px !important; box-shadow:none !important; overflow:visible !important; }
	.rrhh-ficha-head { gap:16px !important; padding:7mm 10mm !important; background:linear-gradient(135deg,#f5f9ff 0%,#edf4fb 100%) !important; border-bottom:1px solid #dce5ef !important; break-inside:avoid; page-break-inside:avoid; print-color-adjust:exact; -webkit-print-color-adjust:exact; }
	.rrhh-ficha-avatar-block { flex:0 0 84px !important; gap:0 !important; }
	.rrhh-ficha-avatar { width:84px !important; height:84px !important; flex-basis:84px !important; border-width:3px !important; background:#dceaff !important; box-shadow:0 2px 8px rgba(33,85,145,.12) !important; font-size:25px !important; }
	.rrhh-ficha-avatar img { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
	.rrhh-ficha-brand { margin-bottom:5px !important; font-size:9pt !important; letter-spacing:1.2px !important; }
	.rrhh-ficha-head h1 { margin-bottom:7px !important; font-size:20pt !important; line-height:1.22 !important; }
	.rrhh-ficha-meta { gap:6px !important; }
	.rrhh-ficha-pill { padding:4px 8px !important; border-color:#d5e1ef !important; background:#fff !important; font-size:9pt !important; }
	.rrhh-ficha-emision { font-size:9pt !important; }
	.rrhh-ficha-body { padding:7mm 10mm !important; }
	.rrhh-ficha-section { break-inside:avoid; page-break-inside:avoid; }
	.rrhh-ficha-section + .rrhh-ficha-section { margin-top:6mm !important; padding-top:5mm !important; border-top:1px solid #e8edf3 !important; }
	.rrhh-ficha-section h2 { margin-bottom:4mm !important; font-size:12pt !important; }
	.rrhh-ficha-grid { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)) !important; gap:5mm 10mm !important; }
	.rrhh-ficha-item { min-width:0 !important; break-inside:avoid; page-break-inside:avoid; }
	.rrhh-ficha-label { margin-bottom:1.5mm !important; font-size:8.5pt !important; }
	.rrhh-ficha-value { font-size:10.5pt !important; line-height:1.3 !important; overflow-wrap:anywhere !important; }
	.rrhh-ficha-roles { gap:5px !important; }
	.rrhh-ficha-role { padding:3px 7px !important; font-size:9pt !important; }
	.rrhh-ficha-footer { margin-top:4mm !important; padding:3mm 10mm !important; border-top:1px solid #e8edf3 !important; font-size:8.5pt !important; }
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
			<button type="button" class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Imprimir ficha</button>
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
				<?php if(!$modo_modal_informe) { ?><section class="rrhh-ficha-section rrhh-legajo-section rrhh-no-print" aria-labelledby="rrhh-legajo-title">
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
							<thead><tr><th>Fecha de carga</th><th>Categoría</th><th>Documento</th><th>Descripción</th><th>Acciones</th></tr></thead>
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
								<td><div class="rrhh-legajo-actions">
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
			</div>
			<footer class="rrhh-ficha-footer">Ficha laboral · ADEMI</footer>
		</article>
	<?php if(!$modo_modal_informe) { ?></div></div><?php } else { ?></main><?php } ?>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<?php if($auto_imprimir) { ?><script>window.addEventListener('load',function(){window.print();});</script><?php } ?>
</body>
</html>
