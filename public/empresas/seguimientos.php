<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('empresas');
require_once BASE_PATH.'/includes/empresas-seguimiento.php';

if(!empresa_seguimiento_puede_ver_area('empresas'))
{
	http_response_code(403);
	exit('No tenés permiso para consultar seguimientos empresariales.');
}

$menu_activo='seguimientos';
$tabla_disponible=function_exists('acceso_tabla_existe') && acceso_tabla_existe('empresa_seguimiento');
$clasificacion_disponible=$tabla_disponible && function_exists('crm_columna_existe') && crm_columna_existe('empresa_seguimiento','resultado_clasificacion');
$filtros=array(
	'q'=>isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '',
	'estado'=>isset($_GET['estado']) && is_string($_GET['estado']) ? $_GET['estado'] : '',
	'hito'=>isset($_GET['hito']) && is_string($_GET['hito']) ? $_GET['hito'] : '',
	'desde'=>isset($_GET['desde']) && is_string($_GET['desde']) ? $_GET['desde'] : '',
	'hasta'=>isset($_GET['hasta']) && is_string($_GET['hasta']) ? $_GET['hasta'] : ''
);
$estados_validos=array('','pendiente','proximo','vencido','realizado');
$hitos_validos=array('','3_meses','6_meses','manual');
if(!in_array($filtros['estado'],$estados_validos,true)) { $filtros['estado']=''; }
if(!in_array($filtros['hito'],$hitos_validos,true)) { $filtros['hito']=''; }
foreach(array('desde','hasta') as $campo_fecha)
{
	if($filtros[$campo_fecha]!=='' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$filtros[$campo_fecha],$partes) || !checkdate(intval($partes[2]),intval($partes[3]),intval($partes[1])))) { $filtros[$campo_fecha]=''; }
}
$todos=$tabla_disponible ? empresa_seguimiento_tablero_datos() : array();
$detalle_id=isset($_GET['seguimiento_id']) && is_string($_GET['seguimiento_id']) && ctype_digit($_GET['seguimiento_id']) ? intval($_GET['seguimiento_id']) : 0;
$detalle=null;
if($detalle_id>0) { foreach($todos as $fila_detalle) { if(intval($fila_detalle['id_seguimiento'])===$detalle_id) { $detalle=$fila_detalle; break; } } }
$resultado_post=array('error'=>'','valores'=>array(),'accion'=>'');
$url_guardado=url_crm('empresas/seguimientos.php?seguimiento_id='.$detalle_id.'&seguimiento_guardado=1');
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['empresa_seguimiento_accion']))
{
	$accion_post=isset($_POST['empresa_seguimiento_accion']) && is_string($_POST['empresa_seguimiento_accion']) ? trim($_POST['empresa_seguimiento_accion']) : '';
	if($accion_post==='crear_manual')
	{
		$id_cliente_post=isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
		$resultado_post=empresa_seguimiento_procesar_post($id_cliente_post,'empresas',false,url_crm('empresas/seguimientos.php?seguimiento_guardado=1'));
		$resultado_post['formulario']='manual';
	}
	else
	{
	if(!$detalle) { http_response_code(404); exit('No se encontró el seguimiento solicitado.'); }
	$formulario_detalle=isset($_POST['seguimiento_modo']) && is_string($_POST['seguimiento_modo']) ? trim($_POST['seguimiento_modo']) : '';
	if(!in_array($formulario_detalle,array('programacion','resultado'),true))
	{
		// Reutiliza la protección de permisos y CSRF, pero fuerza una acción inválida para evitar escrituras parciales.
		$_POST['empresa_seguimiento_accion']='accion_detalle_invalida';
		$resultado_post=empresa_seguimiento_procesar_post(intval($detalle['id_cliente']),'empresas',false,$url_guardado);
		$resultado_post['error']='La acción del seguimiento no es válida.';
	}
	else
	{
		// La fecha prevista sigue derivándose de la atención y el hito, por eso no se acepta desde el formulario.
		$responsable_publicado=isset($_POST['id_empleado_responsable']) ? $_POST['id_empleado_responsable'] : '';
		$_POST['empresa_seguimiento_accion']='actualizar';
		$_POST['id_atencion_referencia']=intval($detalle['id_atencion_referencia']);
		$_POST['tipo_seguimiento']=(string)$detalle['tipo_seguimiento'];
		$_POST['id_seguimiento']=$detalle_id;
		if($formulario_detalle==='programacion')
		{
			$_POST['estado']=(string)$detalle['estado'];
			$_POST['fecha_realizacion']=isset($detalle['fecha_realizacion']) ? (string)$detalle['fecha_realizacion'] : '';
			$_POST['resultado_clasificacion']=isset($detalle['resultado_clasificacion']) ? (string)$detalle['resultado_clasificacion'] : '';
			$_POST['situacion_resultado']=isset($detalle['situacion_resultado']) ? (string)$detalle['situacion_resultado'] : '';
			$_POST['observaciones']=isset($detalle['observaciones']) ? (string)$detalle['observaciones'] : '';
			$_POST['id_empleado_responsable']=$responsable_publicado;
		}
		else
		{
			$_POST['id_empleado_responsable']=intval($detalle['id_empleado_responsable']);
			$_POST['estado']='realizado';
		}
		if($detalle['tipo_seguimiento']==='manual' && $formulario_detalle==='programacion')
		{
			$_POST['fecha_prevista']=isset($_POST['fecha_prevista']) ? $_POST['fecha_prevista'] : '';
			$_POST['motivo']=isset($_POST['motivo']) ? $_POST['motivo'] : '';
			$resultado_post=empresa_seguimiento_procesar_post(intval($detalle['id_cliente']),'empresas',false,$url_guardado);
			$resultado_post['formulario']=$formulario_detalle;
		}
		else
		{
			$meses_seguimiento=$detalle['tipo_seguimiento']==='3_meses' ? 3 : 6;
			$fecha_prevista_calculada=empresa_seguimiento_fecha_prevista($detalle['atencion_fecha'],$meses_seguimiento);
			if($detalle['tipo_seguimiento']!=='manual' && ($fecha_prevista_calculada===false || $fecha_prevista_calculada!==(string)$detalle['fecha_prevista']))
			{
				$resultado_post=array('error'=>'No se guardó el cambio: la fecha prevista almacenada no coincide con la que resulta de la atención y el hito. No se modificaron los datos.', 'valores'=>$_POST, 'accion'=>'actualizar', 'formulario'=>$formulario_detalle);
			}
			else
			{
				$resultado_post=empresa_seguimiento_procesar_post(intval($detalle['id_cliente']),'empresas',false,$url_guardado);
				$resultado_post['formulario']=$formulario_detalle;
			}
		}
	}
	}
}
$filtros_sin_estado=$filtros;
$filtros_sin_estado['estado']='';
$base_filtrada=empresa_seguimiento_tablero_filtrar($todos,$filtros_sin_estado);
$seguimientos=empresa_seguimiento_tablero_filtrar($todos,$filtros);
$contadores=array('pendiente'=>0,'proximo'=>0,'vencido'=>0,'realizado'=>0);
foreach($base_filtrada as $fila) { if(isset($contadores[$fila['estado_tablero']])) { $contadores[$fila['estado_tablero']]++; } }
$manual_tablero_empresas=array();
$manual_tablero_atenciones=array();
$manual_tablero_csrf=crm_tarea_extra_csrf_token();
$manual_post_error=isset($resultado_post['accion']) && $resultado_post['accion']==='crear_manual' && !empty($resultado_post['error']);
$manual_tablero_valores=$manual_post_error && isset($resultado_post['valores']) && is_array($resultado_post['valores']) ? $resultado_post['valores'] : array();
if($manual_post_error) { $manual_tablero_valores['_error']=$resultado_post['error']; }
if(!$detalle && $tabla_disponible && empresa_seguimiento_puede_ver_area('empresas'))
{
	foreach(empresa_seguimiento_empresas_visibles() as $empresa_manual)
	{
		$id_empresa_manual=intval($empresa_manual['id_cliente']);
		if(!empresa_seguimiento_puede_editar($id_empresa_manual,'empresas')) { continue; }
		$atenciones_empresa=empresa_seguimiento_lista_atenciones($id_empresa_manual);
		$manual_tablero_empresas[]=$empresa_manual;
		foreach($atenciones_empresa as $atencion_empresa) { $atencion_empresa['id_cliente']=$id_empresa_manual; $manual_tablero_atenciones[]=$atencion_empresa; }
	}
}
$manual_tablero_token=isset($manual_tablero_valores['empresa_seguimiento_token_manual']) && empresa_seguimiento_token_manual_valido($manual_tablero_valores['empresa_seguimiento_token_manual']) ? $manual_tablero_valores['empresa_seguimiento_token_manual'] : empresa_seguimiento_token_manual();
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Empresas - Seguimientos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.empresa-seguimientos-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:14px 0 20px}.empresa-seguimientos-stat{display:block;padding:15px 17px;border:1px solid #dbe4ef;border-radius:6px;background:#fff;color:#1e293b}.empresa-seguimientos-stat:hover,.empresa-seguimientos-stat:focus{text-decoration:none;border-color:#93c5fd}.empresa-seguimientos-stat strong{display:block;font-size:24px;line-height:1.25}.empresa-seguimientos-stat span{color:#64748b}.empresa-seguimientos-filtros{display:grid;grid-template-columns:minmax(145px,1.45fr) minmax(100px,.8fr) minmax(118px,.9fr) minmax(132px,1fr) minmax(132px,1fr) auto auto;gap:8px;align-items:end}.empresa-seguimientos-filtros .form-group{margin:0;min-width:0}.empresa-seguimientos-filtros label{white-space:nowrap}.empresa-seguimientos-filtros .form-control{min-width:0;padding-left:8px;padding-right:8px}.empresa-seguimientos-filtros .btn{white-space:nowrap}.empresa-seguimientos-table-wrap{overflow-x:auto}.empresa-seguimientos-table{min-width:920px}.empresa-seguimientos-table td,.empresa-seguimientos-table th{vertical-align:middle!important}.empresa-seguimiento-chip{display:inline-block;border-radius:12px;padding:4px 8px;font-size:11px;font-weight:600;white-space:nowrap}.empresa-seguimiento-chip--pendiente{background:#e2e8f0;color:#334155}.empresa-seguimiento-chip--proximo{background:#dbeafe;color:#1d4ed8}.empresa-seguimiento-chip--vencido{background:#fee2e2;color:#b91c1c}.empresa-seguimiento-chip--realizado{background:#dcfce7;color:#166534}.empresa-seguimiento-detalle{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.empresa-seguimiento-detalle-item{padding:12px;border:1px solid #dbe4ef;border-radius:5px;background:#fff}.empresa-seguimiento-detalle-item strong{display:block;margin-bottom:4px;color:#64748b;font-size:12px}.empresa-seguimiento-detalle-form{max-width:900px}.empresa-seguimiento-detalle-form textarea{resize:vertical}
.empresa-seguimiento-detalle-actions{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0}.empresa-seguimiento-status{font-weight:600}.empresa-seguimiento-status--vencido{color:#b91c1c}.empresa-seguimiento-modal .modal-body{max-height:calc(100vh - 220px);overflow-y:auto}.empresa-seguimiento-modal .form-group{margin-bottom:14px}
@media(max-width:1100px){.empresa-seguimientos-filtros{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:991px){.empresa-seguimientos-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.empresa-seguimientos-filtros{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:600px){.empresa-seguimientos-stats{grid-template-columns:1fr 1fr}.empresa-seguimientos-filtros,.empresa-seguimiento-detalle{grid-template-columns:1fr}}
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
		<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="<?php echo htmlspecialchars(url_crm('empresas/dashboard.php'),ENT_QUOTES,'UTF-8'); ?>">Empresas</a></li><li><a href="#" class="active">Seguimientos</a></li></ul>
		<?php include __DIR__.'/menu-seccion.php'; ?>
		<div class="page-title"><h3><?php echo $detalle ? 'Detalle del seguimiento' : 'Seguimiento empresarial'; ?></h3></div>
		<p>Consultá los seguimientos automáticos y manuales vinculados a empresas.</p>
		<?php if(isset($_GET['seguimiento_guardado']) && $_GET['seguimiento_guardado']==='1' && !empty($_SESSION['msgcliente'])) { ?><div class="alert alert-success" role="status"><?php echo empresa_seguimiento_h($_SESSION['msgcliente']); ?></div><?php $_SESSION['msgcliente']=''; } ?>
		<?php if(!$tabla_disponible) { ?>
		<div class="alert alert-info">La tabla de seguimiento empresarial todavía no está habilitada en esta base de pruebas.</div>
		<?php } else { ?>
		<?php if(!$clasificacion_disponible) { ?><div class="alert alert-warning">La consulta del tablero está disponible; para clasificar resultados hace falta aplicar la migración de clasificación del seguimiento.</div><?php } ?>
		<?php if($detalle_id>0 && !$detalle) { http_response_code(404); ?><div class="alert alert-warning">No se encontró el seguimiento o no tenés permiso para consultarlo.</div><a class="btn btn-white" href="seguimientos.php">Volver al tablero</a><?php } elseif($detalle) { ?>
		<?php $estado_detalle=empresa_seguimiento_estado_visible($detalle['estado'],$detalle['fecha_prevista'],$detalle['fecha_realizacion']); $estado_detalle_texto=$estado_detalle==='realizado'?'Realizado':($estado_detalle==='vencido'?'Vencido':'Pendiente'); $puede_editar_detalle=empresa_seguimiento_puede_editar(intval($detalle['id_cliente']),'empresas'); $modal_error=isset($resultado_post['error']) && $resultado_post['error']!==''; $modo_error=isset($resultado_post['formulario']) ? $resultado_post['formulario'] : ''; $valores_error=isset($resultado_post['valores']) && is_array($resultado_post['valores']) ? $resultado_post['valores'] : array(); $csrf_detalle=crm_tarea_extra_csrf_token(); $empleados_detalle=$puede_editar_detalle ? empresa_seguimiento_empleados_autorizados() : array(); $id_responsable_detalle=isset($valores_error['id_empleado_responsable']) && $modo_error==='programacion' ? intval($valores_error['id_empleado_responsable']) : intval($detalle['id_empleado_responsable']); $responsable_activo=false; foreach($empleados_detalle as $empleado_detalle) { if(intval($empleado_detalle['id'])===$id_responsable_detalle) { $responsable_activo=true; break; } } ?>
		<p><a class="btn btn-white btn-sm" href="seguimientos.php"><i class="fa fa-arrow-left"></i> Volver a Seguimientos</a></p>
		<?php if($modal_error && !in_array($modo_error,array('programacion','resultado'),true)) { ?><div class="alert alert-danger" role="alert"><?php echo empresa_seguimiento_h($resultado_post['error']); ?></div><?php } ?>
		<div class="grid simple"><div class="grid-title"><h4><?php echo empresa_seguimiento_h($detalle['empresa_nombre']); ?></h4></div><div class="grid-body">
			<div class="empresa-seguimiento-detalle">
				<div class="empresa-seguimiento-detalle-item"><strong>Atención de referencia</strong><?php echo !empty($detalle['atencion_fecha']) ? date('d/m/Y',strtotime($detalle['atencion_fecha'])) : 'Sin atención de referencia'; ?><?php if(!empty($detalle['atencion_area'])) { ?> · <?php echo empresa_seguimiento_h($detalle['atencion_area']); ?><?php } ?></div>
				<div class="empresa-seguimiento-detalle-item"><strong>Hito</strong><?php echo empresa_seguimiento_h(empresa_seguimiento_tipo_etiqueta($detalle['tipo_seguimiento'])); ?></div>
				<?php if($detalle['tipo_seguimiento']==='manual') { ?><div class="empresa-seguimiento-detalle-item"><strong>Motivo</strong><?php echo trim((string)$detalle['motivo'])!==''?nl2br(empresa_seguimiento_h($detalle['motivo'])):'—'; ?></div><?php } ?>
				<div class="empresa-seguimiento-detalle-item"><strong>Fecha prevista</strong><?php echo date('d/m/Y',strtotime($detalle['fecha_prevista'])); ?></div>
				<div class="empresa-seguimiento-detalle-item"><strong>Responsable</strong><?php echo trim((string)$detalle['responsable_nombre'])!==''?empresa_seguimiento_h(trim($detalle['responsable_nombre'])):'Sin asignar'; ?></div>
				<div class="empresa-seguimiento-detalle-item"><strong>Estado</strong><span class="empresa-seguimiento-chip empresa-seguimiento-chip--<?php echo empresa_seguimiento_h($estado_detalle); ?>"><?php echo $estado_detalle_texto; ?></span></div>
				<?php if($estado_detalle==='realizado') { ?>
				<div class="empresa-seguimiento-detalle-item"><strong>Fecha de realización</strong><?php echo !empty($detalle['fecha_realizacion'])?date('d/m/Y',strtotime($detalle['fecha_realizacion'])):'—'; ?></div>
				<div class="empresa-seguimiento-detalle-item"><strong>Clasificación</strong><?php echo empresa_seguimiento_h(isset($detalle['resultado_etiqueta'])?$detalle['resultado_etiqueta']:'') ?: '—'; ?></div>
				<div class="empresa-seguimiento-detalle-item"><strong>Resultado descriptivo</strong><?php echo trim((string)$detalle['situacion_resultado'])!==''?nl2br(empresa_seguimiento_h($detalle['situacion_resultado'])):'—'; ?></div>
				<div class="empresa-seguimiento-detalle-item"><strong>Observaciones</strong><?php echo trim((string)$detalle['observaciones'])!==''?nl2br(empresa_seguimiento_h($detalle['observaciones'])):'—'; ?></div>
				<?php } else { ?><div class="empresa-seguimiento-detalle-item"><strong>Resultado</strong>Aún no se registró la realización.</div><?php } ?>
			</div>
		</div></div>
		<?php if($puede_editar_detalle && $clasificacion_disponible) { ?>
		<?php $atenciones_programables=empresa_seguimiento_lista_atenciones(intval($detalle['id_cliente'])); $empresa_programable=array('id_cliente'=>intval($detalle['id_cliente']),'nombre'=>$detalle['empresa_nombre']); $manual_detalle_values=$manual_post_error && intval(isset($manual_tablero_valores['id_cliente'])?$manual_tablero_valores['id_cliente']:0)===intval($detalle['id_cliente']) ? $manual_tablero_valores : array(); $manual_detalle_token=isset($manual_detalle_values['empresa_seguimiento_token_manual']) && empresa_seguimiento_token_manual_valido($manual_detalle_values['empresa_seguimiento_token_manual']) ? $manual_detalle_values['empresa_seguimiento_token_manual'] : empresa_seguimiento_token_manual(); ?>
		<div class="empresa-seguimiento-detalle-actions">
			<button type="button" class="btn btn-white" data-toggle="modal" data-target="#modal-editar-programacion"><i class="fa fa-calendar"></i> Editar seguimiento</button>
			<button type="button" class="btn btn-white" data-toggle="modal" data-target="#modal-programar-seguimiento-manual"><i class="fa fa-calendar-plus-o"></i> Programar nuevo seguimiento</button>
			<?php if($clasificacion_disponible && $estado_detalle!=='realizado') { ?><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-registrar-resultado"><i class="fa fa-check"></i> Registrar resultado</button><?php } elseif($clasificacion_disponible) { ?><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-registrar-resultado"><i class="fa fa-pencil"></i> Editar resultado</button><?php } ?>
		</div>
		<?php empresa_seguimiento_render_formulario_manual(array($empresa_programable),intval($detalle['id_cliente']),$atenciones_programables,$csrf_detalle,$manual_detalle_token,'seguimientos.php',$manual_detalle_values,$empresa_programable); ?>
		<div class="modal fade empresa-seguimiento-modal" id="modal-editar-programacion" tabindex="-1" role="dialog" aria-labelledby="modal-editar-programacion-titulo" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content">
			<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="modal-editar-programacion-titulo">Editar seguimiento</h4></div>
			<form method="post" action="seguimientos.php?seguimiento_id=<?php echo intval($detalle['id_seguimiento']); ?>" class="empresa-seguimiento-form"><div class="modal-body">
				<?php if($modal_error && $modo_error==='programacion') { ?><div class="alert alert-danger" role="alert"><?php echo empresa_seguimiento_h($resultado_post['error']); ?></div><?php } ?>
				<input type="hidden" name="csrf_tarea_extra" value="<?php echo empresa_seguimiento_h($csrf_detalle); ?>"><input type="hidden" name="empresa_seguimiento_accion" value="actualizar"><input type="hidden" name="seguimiento_modo" value="programacion">
				<?php if($detalle['tipo_seguimiento']==='manual') { ?><div class="form-group"><label for="programacion-fecha-prevista">Fecha prevista</label><input type="date" class="form-control" id="programacion-fecha-prevista" name="fecha_prevista" value="<?php echo empresa_seguimiento_h(isset($valores_error['fecha_prevista']) && $modo_error==='programacion' ? $valores_error['fecha_prevista'] : $detalle['fecha_prevista']); ?>" required></div><div class="form-group"><label for="programacion-motivo">Motivo</label><input class="form-control" id="programacion-motivo" name="motivo" maxlength="255" value="<?php echo empresa_seguimiento_h(isset($valores_error['motivo']) && $modo_error==='programacion' ? $valores_error['motivo'] : $detalle['motivo']); ?>" required></div><?php } else { ?><div class="form-group"><label>Fecha prevista</label><input type="text" class="form-control" value="<?php echo date('d/m/Y',strtotime($detalle['fecha_prevista'])); ?>" readonly><small class="help-block">Se calcula desde la atención de referencia y el hito. No se modifica manualmente para conservar esa regla.</small></div><?php } ?>
				<div class="form-group"><label for="programacion-responsable">Responsable</label><select class="form-control" id="programacion-responsable" name="id_empleado_responsable" <?php echo $detalle['tipo_seguimiento']==='manual'?'required':''; ?>><option value="<?php echo $detalle['tipo_seguimiento']==='manual'?'':'0'; ?>" <?php echo $id_responsable_detalle<=0?'selected':''; ?>><?php echo $detalle['tipo_seguimiento']==='manual'?'Seleccionar responsable':'Sin asignar'; ?></option><?php if($id_responsable_detalle>0 && !$responsable_activo) { ?><option value="<?php echo $id_responsable_detalle; ?>" selected><?php echo empresa_seguimiento_h(trim((string)$detalle['responsable_nombre'])!=='' ? trim((string)$detalle['responsable_nombre']).' (asignación existente)' : 'Responsable existente'); ?></option><?php } ?><?php foreach($empleados_detalle as $empleado) { ?><option value="<?php echo intval($empleado['id']); ?>" <?php echo intval($empleado['id'])===$id_responsable_detalle?'selected':''; ?>><?php echo empresa_seguimiento_h($empleado['nombre']); ?></option><?php } ?></select></div>
			</div><div class="modal-footer"><button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button></div></form>
		</div></div></div>
		<?php if($clasificacion_disponible) { $fecha_form=isset($valores_error['fecha_realizacion']) && $modo_error==='resultado' ? $valores_error['fecha_realizacion'] : ($detalle['fecha_realizacion'] ?: date('Y-m-d')); $clasificacion_form=isset($valores_error['resultado_clasificacion']) && $modo_error==='resultado' ? $valores_error['resultado_clasificacion'] : (string)$detalle['resultado_clasificacion']; $situacion_form=isset($valores_error['situacion_resultado']) && $modo_error==='resultado' ? $valores_error['situacion_resultado'] : (string)$detalle['situacion_resultado']; $observaciones_form=isset($valores_error['observaciones']) && $modo_error==='resultado' ? $valores_error['observaciones'] : (string)$detalle['observaciones']; ?>
		<div class="modal fade empresa-seguimiento-modal" id="modal-registrar-resultado" tabindex="-1" role="dialog" aria-labelledby="modal-registrar-resultado-titulo" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content">
			<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="modal-registrar-resultado-titulo"><?php echo $estado_detalle==='realizado'?'Editar resultado':'Registrar resultado'; ?></h4></div>
			<form method="post" action="seguimientos.php?seguimiento_id=<?php echo intval($detalle['id_seguimiento']); ?>" class="empresa-seguimiento-form"><div class="modal-body">
				<?php if($modal_error && $modo_error==='resultado') { ?><div class="alert alert-danger" role="alert"><?php echo empresa_seguimiento_h($resultado_post['error']); ?></div><?php } ?>
				<input type="hidden" name="csrf_tarea_extra" value="<?php echo empresa_seguimiento_h($csrf_detalle); ?>"><input type="hidden" name="empresa_seguimiento_accion" value="actualizar"><input type="hidden" name="seguimiento_modo" value="resultado">
				<input type="hidden" name="estado" value="realizado">
				<div class="form-group"><label for="resultado-fecha">Fecha de realización</label><input class="form-control" type="date" id="resultado-fecha" name="fecha_realizacion" value="<?php echo empresa_seguimiento_h($fecha_form); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
				<div class="form-group"><label for="resultado-clasificacion">Clasificación</label><select class="form-control" id="resultado-clasificacion" name="resultado_clasificacion" required><option value="">Seleccionar</option><?php foreach(array('positivo'=>'Positivo','neutro'=>'Neutro','negativo'=>'Negativo') as $key=>$label) { ?><option value="<?php echo $key; ?>" <?php echo $clasificacion_form===$key?'selected':''; ?>><?php echo $label; ?></option><?php } ?></select></div>
				<div class="form-group"><label for="resultado-situacion">Descripción del resultado</label><textarea class="form-control" id="resultado-situacion" name="situacion_resultado" rows="3"><?php echo empresa_seguimiento_h($situacion_form); ?></textarea></div>
				<div class="form-group"><label for="resultado-observaciones">Observaciones</label><textarea class="form-control" id="resultado-observaciones" name="observaciones" rows="3"><?php echo empresa_seguimiento_h($observaciones_form); ?></textarea></div>
			</div><div class="modal-footer"><button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar resultado</button></div></form>
		</div></div></div>
		<?php } ?>
		<?php } elseif($puede_editar_detalle) { ?><div class="alert alert-warning">La administración requiere la columna de clasificación habilitada en la base; no se modificaron datos.</div><?php } ?>
		<?php } else { ?>
		<?php if($manual_tablero_empresas && $clasificacion_disponible) { ?><div class="empresa-seguimiento-detalle-actions"><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-programar-seguimiento-manual"><i class="fa fa-calendar-plus-o"></i> Programar nuevo seguimiento</button></div><?php empresa_seguimiento_render_formulario_manual($manual_tablero_empresas,0,$manual_tablero_atenciones,$manual_tablero_csrf,$manual_tablero_token,'seguimientos.php',$manual_tablero_valores,null); } ?>
		<div class="empresa-seguimientos-stats">
			<?php foreach(array('pendiente'=>'Pendientes','proximo'=>'Próximos a vencer','vencido'=>'Vencidos','realizado'=>'Realizados') as $clave=>$rotulo) { $params=$filtros; $params['estado']=$clave; $href='seguimientos.php?'.http_build_query($params).'#resultados'; ?>
			<a class="empresa-seguimientos-stat" href="<?php echo htmlspecialchars($href,ENT_QUOTES,'UTF-8'); ?>"><strong><?php echo intval($contadores[$clave]); ?></strong><span><?php echo htmlspecialchars($rotulo,ENT_QUOTES,'UTF-8'); ?></span></a>
			<?php } ?>
		</div>
		<div class="grid simple"><div class="grid-title"><h4><i class="fa fa-filter text-info"></i> Filtros</h4></div><div class="grid-body">
			<form method="get" action="seguimientos.php" class="empresa-seguimientos-filtros">
				<div class="form-group"><label for="filtro-empresa">Empresa</label><input class="form-control" type="search" id="filtro-empresa" name="q" value="<?php echo empresa_seguimiento_h($filtros['q']); ?>" placeholder="Nombre de la empresa"></div>
				<div class="form-group"><label for="filtro-estado">Estado</label><select class="form-control" id="filtro-estado" name="estado"><option value="">Todos</option><?php foreach(array('pendiente'=>'Pendiente','proximo'=>'Próximo a vencer','vencido'=>'Vencido','realizado'=>'Realizado') as $valor=>$texto) { ?><option value="<?php echo $valor; ?>" <?php echo $filtros['estado']===$valor?'selected':''; ?>><?php echo $texto; ?></option><?php } ?></select></div>
				<div class="form-group"><label for="filtro-hito">Hito</label><select class="form-control" id="filtro-hito" name="hito"><option value="">Todos</option><option value="3_meses" <?php echo $filtros['hito']==='3_meses'?'selected':''; ?>>3 meses</option><option value="6_meses" <?php echo $filtros['hito']==='6_meses'?'selected':''; ?>>6 meses</option><option value="manual" <?php echo $filtros['hito']==='manual'?'selected':''; ?>>Manual</option></select></div>
				<div class="form-group"><label for="filtro-desde">Previstos desde</label><input class="form-control" type="date" id="filtro-desde" name="desde" value="<?php echo empresa_seguimiento_h($filtros['desde']); ?>"></div>
				<div class="form-group"><label for="filtro-hasta">Previstos hasta</label><input class="form-control" type="date" id="filtro-hasta" name="hasta" value="<?php echo empresa_seguimiento_h($filtros['hasta']); ?>"></div>
				<button type="submit" class="btn btn-primary">Filtrar</button><a class="btn btn-white" href="seguimientos.php">Limpiar</a>
			</form>
		</div></div>
		<div class="grid simple" id="resultados"><div class="grid-title"><h4>Seguimientos <small><?php echo count($seguimientos); ?> resultado(s)</small></h4></div><div class="grid-body">
			<?php if(!$seguimientos) { ?><p class="muted">No hay seguimientos que coincidan con los filtros seleccionados.</p><?php } else { ?>
			<div class="empresa-seguimientos-table-wrap"><table class="table table-hover empresa-seguimientos-table"><thead><tr><th>Empresa</th><th>Atención de referencia</th><th>Hito</th><th>Fecha prevista</th><th>Responsable</th><th>Estado</th><th>Resultado</th><th>Acceso</th></tr></thead><tbody>
			<?php foreach($seguimientos as $fila) { $estado=$fila['estado_tablero']; $estado_texto=array('pendiente'=>'Pendiente','proximo'=>'Próximo a vencer','vencido'=>'Vencido','realizado'=>'Realizado'); $url=url_crm('empresas/seguimientos.php?seguimiento_id='.intval($fila['id_seguimiento'])); ?>
			<tr><td><strong><?php echo empresa_seguimiento_h($fila['empresa_nombre']); ?></strong></td><td><?php echo !empty($fila['atencion_fecha']) ? date('d/m/Y',strtotime($fila['atencion_fecha'])) : 'Sin atención de referencia'; ?><?php if(!empty($fila['atencion_area'])) { ?><br><small><?php echo empresa_seguimiento_h($fila['atencion_area']); ?></small><?php } ?></td><td><?php echo empresa_seguimiento_h(empresa_seguimiento_tipo_etiqueta($fila['tipo_seguimiento'])); ?><?php if($fila['tipo_seguimiento']==='manual' && trim((string)$fila['motivo'])!=='') { ?><br><small><?php echo empresa_seguimiento_h($fila['motivo']); ?></small><?php } ?></td><td><?php echo date('d/m/Y',strtotime($fila['fecha_prevista'])); ?></td><td><?php echo trim((string)$fila['responsable_nombre'])!==''?empresa_seguimiento_h(trim($fila['responsable_nombre'])):'Sin asignar'; ?></td><td><span class="empresa-seguimiento-chip empresa-seguimiento-chip--<?php echo empresa_seguimiento_h($estado); ?>"><?php echo $estado_texto[$estado]; ?></span></td><td><?php echo $estado==='realizado' ? (empresa_seguimiento_h(isset($fila['resultado_etiqueta'])?$fila['resultado_etiqueta']:'') ?: '—') : '—'; ?></td><td><a class="btn btn-white btn-sm" href="<?php echo htmlspecialchars($url,ENT_QUOTES,'UTF-8'); ?>"><i class="fa fa-external-link"></i> Ver seguimiento</a></td></tr>
			<?php } ?>
			</tbody></table></div>
			<?php } ?>
		</div></div>
		<?php } ?>
		<?php } ?>
	</div>
</div>
</div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
<?php if(isset($detalle) && $detalle && isset($modal_error) && $modal_error && in_array($modo_error,array('programacion','resultado'),true)) { $modal_error_id=$modo_error==='programacion'?'modal-editar-programacion':'modal-registrar-resultado'; ?>
<script>(function(){var abrir=function(){if(window.jQuery&&jQuery.fn&&jQuery.fn.modal){jQuery('#<?php echo $modal_error_id; ?>').modal('show');}};if(document.readyState==='complete'){abrir();}else{window.addEventListener('load',abrir);}})();</script>
<?php } ?>
<?php if(isset($manual_post_error) && $manual_post_error) { ?>
<script>(function(){var abrir=function(){if(window.jQuery&&jQuery.fn&&jQuery.fn.modal){jQuery('#modal-programar-seguimiento-manual').modal('show');}};if(document.readyState==='complete'){abrir();}else{window.addEventListener('load',abrir);}})();</script>
<?php } ?>
</body>
</html>
