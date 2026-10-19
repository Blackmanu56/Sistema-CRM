<?php
require_once __DIR__.'/_init.php';
require_once BASE_PATH.'/includes/salas_reuniones.php';
crm_proteger_staff();
if(!acceso_puede_salas_reuniones())
{
	http_response_code(403);
	exit('No tenés autorización para acceder a esta sección.');
}
$csrf_modulo_token=function_exists('crm_tarea_extra_csrf_token') ? crm_tarea_extra_csrf_token() : '';
if($_SERVER['REQUEST_METHOD']==='POST')
{
	$csrf_post=isset($_POST['csrf_tarea_extra']) && is_string($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : '';
	if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido($csrf_post))
	{
		http_response_code(403);
		exit('La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.');
	}
}
salas_reuniones_asegurar();

function salas_reuniones_h($valor)
{
	return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function salas_reuniones_url($parametros)
{
	return 'salas-reuniones.php?'.http_build_query($parametros);
}

function salas_reuniones_listar_pantalla($filtros)
{
	$wheres=array(); $tipos=''; $valores=array();
	if(isset($filtros['fecha']) && salas_reuniones_fecha_valida($filtros['fecha'])) { $wheres[]='r.fecha=?'; $tipos.='s'; $valores[]=$filtros['fecha']; }
	if(!empty($filtros['area'])) { $wheres[]='a.clave=?'; $tipos.='s'; $valores[]=$filtros['area']; }
	if(isset($filtros['estado']) && $filtros['estado']==='activa') { $wheres[]="r.estado='activa'"; }
	else if(isset($filtros['estado']) && $filtros['estado']==='cancelada') { $wheres[]="r.estado='cancelada'"; }
	if(isset($filtros['hora_desde']) && salas_reuniones_hora_valida($filtros['hora_desde'])) { $wheres[]='r.hora_fin>?'; $tipos.='s'; $valores[]=$filtros['hora_desde'].':00'; }
	if(isset($filtros['hora_hasta']) && salas_reuniones_hora_valida($filtros['hora_hasta'])) { $wheres[]='r.hora_inicio<?'; $tipos.='s'; $valores[]=$filtros['hora_hasta'].':00'; }
	$sql="SELECT r.*, s.nombre AS sala_nombre, a.clave AS area_clave, a.nombre AS area_nombre,
		CONCAT(IFNULL(e.nombres,''), ' ', IFNULL(e.apellido,'')) AS empleado_nombre
		FROM sala_reunion_reserva r
		INNER JOIN sala_reunion s ON s.id_sala=r.id_sala
		INNER JOIN area a ON a.id_area=r.id_area
		INNER JOIN empleado e ON e.id_empleado=r.id_empleado";
	if(count($wheres)>0) { $sql.=' WHERE '.implode(' AND ', $wheres); }
	$orden=isset($filtros['orden']) && in_array($filtros['orden'],array('recientes','antiguas','proximas'),true) ? $filtros['orden'] : 'recientes';
	if($orden==='antiguas') { $sql.=' ORDER BY r.fecha ASC, r.hora_inicio ASC, r.id_reserva ASC'; }
	else if($orden==='proximas')
	{
		$ahora=date('Y-m-d H:i:s');
		$sql.=' ORDER BY CASE WHEN TIMESTAMP(r.fecha,r.hora_inicio)>=? THEN 0 ELSE 1 END ASC,
			CASE WHEN TIMESTAMP(r.fecha,r.hora_inicio)>=? THEN TIMESTAMP(r.fecha,r.hora_inicio) END ASC,
			TIMESTAMP(r.fecha,r.hora_inicio) DESC, r.id_reserva DESC';
		$tipos.='ss'; $valores[]=$ahora; $valores[]=$ahora;
	}
	else { $sql.=' ORDER BY r.fecha DESC, r.hora_inicio DESC, r.id_reserva DESC'; }
	return acceso_consulta($sql, $tipos, $valores);
}

function salas_reuniones_barra_modulos()
{
	if(!function_exists('tareas_link'))
	{
		function tareas_link($pagina, $extra=array())
		{
			$area=acceso_area_activa();
			if($area==='' || $area==='admin') { $area='general'; }
			return url_crm($area.'/'.$pagina).(count($extra)>0 ? '?'.http_build_query($extra) : '');
		}
	}
	$area=acceso_area_activa();
	$menu_path=BASE_PATH.'/public/'.$area.'/menu-seccion.php';
	if(preg_match('/^[a-z0-9_-]+$/i',(string)$area) && is_file($menu_path))
	{
		$menu_activo='salas_reuniones';
		ob_start();
		include $menu_path;
		return ob_get_clean();
	}
	return '';
}

$mensaje='';
$modal_abierto=false;
$modal_id=0;
$valores_form=array();
if(isset($_POST['guardar_reserva']))
{
	$modal_id=isset($_POST['id_reserva']) ? intval($_POST['id_reserva']) : 0;
	$mensaje=salas_reuniones_guardar($_POST, $modal_id);
	if($mensaje==='')
	{
		$_SESSION['msg_salas']=$modal_id>0 ? 'Reserva actualizada.' : 'Turno reservado.';
		$redir_cal=isset($_POST['cal']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_POST['cal']) ? $_POST['cal'] : date('Y-m');
		$redir_fecha=isset($_POST['fecha']) && $_POST['fecha']==='' ? '' : (isset($_POST['fecha']) && salas_reuniones_fecha_valida($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d'));
		$redir_orden=isset($_POST['orden']) && in_array($_POST['orden'],array('recientes','antiguas','proximas'),true) ? $_POST['orden'] : 'recientes';
		header('Location: '.salas_reuniones_url(array('cal'=>$redir_cal,'fecha'=>$redir_fecha,'orden'=>$redir_orden)));
		exit();
	}
	$modal_abierto=true;
	$valores_form=$_POST;
}
else if(isset($_POST['cancelar_reserva']))
{
	$id=isset($_POST['id_reserva']) ? intval($_POST['id_reserva']) : 0;
	$mensaje=salas_reuniones_cancelar($id);
	if($mensaje==='')
	{
		$_SESSION['msg_salas']='Reserva cancelada.';
		$redir_cal=isset($_POST['cal']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_POST['cal']) ? $_POST['cal'] : date('Y-m');
		$redir_fecha=isset($_POST['fecha']) && $_POST['fecha']==='' ? '' : (isset($_POST['fecha']) && salas_reuniones_fecha_valida($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d'));
		$redir_orden=isset($_POST['orden']) && in_array($_POST['orden'],array('recientes','antiguas','proximas'),true) ? $_POST['orden'] : 'recientes';
		header('Location: '.salas_reuniones_url(array('cal'=>$redir_cal,'fecha'=>$redir_fecha,'orden'=>$redir_orden)));
		exit();
	}
}

$cal=isset($_GET['cal']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['cal']) ? $_GET['cal'] : date('Y-m');
$fecha_solicitada=isset($_GET['fecha']) && salas_reuniones_fecha_valida($_GET['fecha']) ? $_GET['fecha'] : '';
if($fecha_solicitada!=='') { $cal=substr($fecha_solicitada,0,7); }
$partes=explode('-', $cal);
$anio=intval($partes[0]);
$mes=intval($partes[1]);
$fecha=$fecha_solicitada;
$fecha_reserva_default=$fecha!=='' ? $fecha : date('Y-m-d');
$salas=salas_reuniones_salas_activas();
if(!is_array($salas)) { $salas=array(); }
$areas_todas=acceso_etiquetas_area();
$areas_reserva=salas_reuniones_areas_disponibles();
$f_area=isset($_GET['area']) && isset($areas_todas[$_GET['area']]) ? $_GET['area'] : '';
$f_estado=isset($_GET['estado']) && in_array($_GET['estado'], array('todas','activa','cancelada'), true) ? $_GET['estado'] : 'todas';
$orden=isset($_GET['orden']) && in_array($_GET['orden'],array('recientes','antiguas','proximas'),true) ? $_GET['orden'] : 'recientes';
$f_hora_desde=isset($_GET['hora_desde']) && salas_reuniones_hora_valida($_GET['hora_desde']) ? $_GET['hora_desde'] : '';
$f_hora_hasta=isset($_GET['hora_hasta']) && salas_reuniones_hora_valida($_GET['hora_hasta']) ? $_GET['hora_hasta'] : '';
$filtros_dia=array('fecha'=>$fecha,'area'=>$f_area,'estado'=>$f_estado,'hora_desde'=>$f_hora_desde,'hora_hasta'=>$f_hora_hasta,'orden'=>$orden);
$reservas_dia=salas_reuniones_listar_pantalla($filtros_dia);
if(!is_array($reservas_dia)) { $reservas_dia=array(); }
$sala_fija=salas_reuniones_sala_para_reserva();
$salas_disp_id=$sala_fija ? intval($sala_fija['id_sala']) : 0;
$reservas_mes=salas_reuniones_reservas_mes($anio,$mes);
if(!is_array($reservas_mes)) { $reservas_mes=array(); }
$dias_reservados=array();
foreach($reservas_mes as $r)
{
	if(!isset($dias_reservados[$r['fecha']])) { $dias_reservados[$r['fecha']]=0; }
	$dias_reservados[$r['fecha']]++;
}

$historial_limite=isset($_GET['historial_limite']) ? max(20,min(1000,intval($_GET['historial_limite']))) : 20;
$reservas_historial=salas_reuniones_historial(array('limite'=>$historial_limite));
if(!is_array($reservas_historial)) { $reservas_historial=array(); }

$area_preseleccionada=acceso_area_activa();
if(!isset($areas_reserva[$area_preseleccionada])) { $area_preseleccionada=''; }
$area_formulario=$area_preseleccionada;
if($modal_abierto && $modal_id>0)
{
	$reserva_edicion=salas_reuniones_reserva_por_id($modal_id);
	if($reserva_edicion) { $area_formulario=$reserva_edicion['area_clave']; }
}
$area_formulario_nombre=isset($areas_todas[$area_formulario]) ? $areas_todas[$area_formulario] : 'Área activa no disponible';
$valores_form=array(
	'id_reserva'=>0,
	'id_sala'=>$salas_disp_id,
	'area'=>$area_preseleccionada,
 'fecha'=>$fecha_reserva_default,
	'hora_inicio'=>'09:00',
	'hora_fin'=>'10:00',
	'motivo'=>''
);
if($modal_abierto)
{
	foreach($valores_form as $k=>$v) { if(isset($_POST[$k])) { $valores_form[$k]=$_POST[$k]; } }
	$modal_id=isset($_POST['id_reserva']) ? intval($_POST['id_reserva']) : 0;
}
$msg_sesion=isset($_SESSION['msg_salas']) ? $_SESSION['msg_salas'] : '';
$_SESSION['msg_salas']='';
$primer_dia=intval(date('N',strtotime(sprintf('%04d-%02d-01',$anio,$mes))));
$dias_mes=intval(date('t',strtotime(sprintf('%04d-%02d-01',$anio,$mes))));
$nombres_meses=array(1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre');
$mes_anterior=date('Y-m',strtotime(sprintf('%04d-%02d-01 -1 month',$anio,$mes)));
$mes_siguiente=date('Y-m',strtotime(sprintf('%04d-%02d-01 +1 month',$anio,$mes)));
$anio_actual=intval(date('Y'));
$mes_actual=date('Y-m');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>CRM | Sala de reuniones</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link href="assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.sala-page-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 14px;flex-wrap:wrap}
.sala-page-title h3{margin:0;font-weight:600;color:#26364a}
.sala-filter-card{background:#fff;border:1px solid #e5e9ec;border-radius:4px;padding:12px 13px;margin-bottom:14px}
.sala-filter-grid{display:grid;grid-template-columns:minmax(130px,1.15fr) minmax(130px,1.2fr) minmax(105px,.95fr) minmax(75px,.7fr) minmax(75px,.7fr) minmax(200px,1.4fr) auto auto;gap:8px;align-items:end}
.sala-filter-grid label,.sala-modal-grid label{display:block;font-size:11px;color:#64748b;font-weight:700;margin-bottom:4px}
.sala-filter-grid .form-control{height:34px;padding:6px 9px}
.sala-filter-date{display:flex;gap:5px}.sala-filter-date .form-control{min-width:0;flex:1}.sala-filter-actions{height:34px;display:flex;align-items:flex-end;gap:6px;white-space:nowrap}.sala-filter-actions .btn{box-sizing:border-box;height:34px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;gap:5px;font-size:12px;line-height:1}
.sala-reserva-card{background:#fff;border:1px solid #e5e9ec;border-left:4px solid #0ea5e9;border-radius:4px;padding:13px 15px;margin-bottom:10px}
.sala-reserva-card.confirmada{border-left-color:#22c55e}.sala-reserva-card.cancelada{border-left-color:#94a3b8}.sala-reserva-hora{font-size:18px;font-weight:700;color:#1e293b}
.sala-reserva-meta{font-size:12px;color:#475569;line-height:1.65;margin-top:5px}.sala-reserva-motivo{margin-top:6px;color:#334155}
.sala-estado{display:inline-block;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:700;background:#e0f2fe;color:#075985}
.sala-estado.confirmada{background:#dcfce7;color:#166534}.sala-estado.cancelada{background:#f1f5f9;color:#475569}.sala-estado.finalizada{background:#fef3c7;color:#92400e}
.sala-cal-header{display:flex;gap:6px;align-items:center;width:100%;box-sizing:border-box}.sala-cal-header form{display:flex;gap:6px;align-items:center;flex:1;min-width:0;margin:0}.sala-cal-header .sala-month,.sala-cal-header .sala-year{height:32px;padding:4px 8px;font-size:12px;font-weight:600;border:1px solid #dce1e5;border-radius:4px;color:#1e293b;background:#fff;cursor:pointer}.sala-cal-header .sala-month{flex:1;min-width:0}.sala-cal-header .sala-year{width:72px;flex-shrink:0}.sala-cal-header .btn{height:32px;padding:0 10px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dce1e5;border-radius:4px;flex-shrink:0}.sala-cal-header .sala-arrow{width:34px;padding:0}
.sala-cal{width:100%;border-collapse:collapse;table-layout:fixed}.sala-cal th{text-align:center;font-size:12px;color:#64748b;padding:8px 0;font-weight:700;text-transform:uppercase}.sala-cal td{text-align:center;padding:4px 0;height:40px;vertical-align:top;border-radius:6px;cursor:pointer;position:relative;transition:background-color .15s ease}.sala-cal td:hover{background-color:#f1f5f9}.sala-cal td a{display:flex;flex-direction:column;align-items:center;width:100%;color:inherit;text-decoration:none}.sala-cal .cal-mini-num{font-size:13px;color:#334155;font-weight:600;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%}.sala-cal .cal-mini-hoy .cal-mini-num{background-color:#0088cc;color:#fff;font-weight:700;box-shadow:0 2px 4px rgba(0,136,204,.25)}.sala-cal .cal-mini-sel{background-color:#d1fae5!important;border:1px solid #10b981!important}.sala-cal .cal-mini-dots{display:flex;align-items:center;justify-content:center;gap:4px;margin-top:2px;min-height:8px}.sala-cal .dot{width:6px;height:6px;border-radius:50%;display:inline-block}.sala-cal .dot-blue{background-color:#0088cc}.sala-cal .cal-mini-count{font-size:10px;line-height:10px;color:#0088cc;font-weight:600}.sala-cal-legend{display:flex;justify-content:center;align-items:center;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;font-size:13px;color:#64748b;font-weight:500}.sala-cal-legend .dot{margin-right:5px}
.sala-history-table td,.sala-history-table th{font-size:11px;padding:7px!important;vertical-align:middle!important}
.sala-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.sala-modal-grid .span-two{grid-column:1/-1}.sala-modal-info{background:#f8fafc;border:1px solid #e2e8f0;padding:9px 11px;border-radius:4px;font-size:12px;color:#475569}
.sala-detail-modal .modal-header{display:flex;align-items:center;gap:12px;background:#f8fafc;border-bottom:1px solid #e2e8f0}.sala-detail-modal .modal-title{font-size:18px;font-weight:700;color:#1e293b;flex:1}.sala-detail-modal .modal-body{padding:14px 18px}.sala-detail-hero{background:linear-gradient(135deg,#eff6ff,#f0f9ff);border:1px solid #bfdbfe;border-radius:8px;padding:12px 15px;margin-bottom:8px}.sala-detail-date{font-size:13px;color:#475569;font-weight:600}.sala-detail-hours{font-size:26px;font-weight:800;color:#0f172a;margin-top:2px}.sala-detail-section{padding:7px 0;border-bottom:1px solid #edf0f2}.sala-detail-section:last-child{border-bottom:0}.sala-detail-label{font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:3px}.sala-detail-value{font-size:14px;color:#1e293b;font-weight:600;line-height:1.4}.sala-detail-icon{color:#0284c7;width:22px;text-align:center;margin-right:5px}.sala-detail-motivo{font-weight:400;white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:5px;padding:9px 11px}
@media(max-width:900px){.sala-filter-grid{grid-template-columns:repeat(3,minmax(120px,1fr))}.sala-filter-actions{grid-column:span 1}}
@media(max-width:767px){.sala-filter-grid{grid-template-columns:repeat(2,minmax(120px,1fr))}.sala-filter-actions{grid-column:span 2}.sala-modal-grid{grid-template-columns:1fr}.sala-modal-grid .span-two{grid-column:auto}.sala-history-scroll{overflow-x:auto}}
</style>
</head>
<body class="">
<?php crm_header_staff();?>
<div class="page-container row">
  <?php crm_menu_staff();?>
  <div class="clearfix"></div>
</div>
</div>
<div class="page-content"><div class="clearfix"></div><div class="content">
  <ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Sala de reuniones</a></li></ul>
  <?php echo salas_reuniones_barra_modulos(); ?>
  <div class="sala-page-title"><h3><i class="fa fa-calendar"></i> Sala de reuniones</h3><?php if(salas_reuniones_puede_crear()) { ?><button type="button" class="btn btn-primary" id="btnNuevoTurno"><i class="fa fa-plus"></i> Nuevo turno</button><?php } ?></div>
  <?php if($mensaje!=='') { ?><div class="alert alert-danger"><?php echo salas_reuniones_h($mensaje); ?></div><?php } ?>
  <?php if($msg_sesion!=='') { ?><div class="alert alert-info"><?php echo salas_reuniones_h($msg_sesion); ?></div><?php } ?>
  <form method="get" class="sala-filter-card">
    <input type="hidden" name="cal" value="<?php echo salas_reuniones_h($cal); ?>">
    <div class="sala-filter-grid">
      <div><label for="fFecha">Fecha</label><input id="fFecha" class="form-control" type="date" name="fecha" value="<?php echo salas_reuniones_h($fecha); ?>"></div>
      <div><label for="fArea">Área</label><select id="fArea" class="form-control" name="area"><option value="">Todas las áreas</option><?php foreach($areas_todas as $clave=>$nombre) { ?><option value="<?php echo salas_reuniones_h($clave); ?>" <?php if($f_area===$clave) echo 'selected'; ?>><?php echo salas_reuniones_h($nombre); ?></option><?php } ?></select></div>
      <div><label for="fEstado">Estado</label><select id="fEstado" class="form-control" name="estado"><option value="todas" <?php if($f_estado==='todas') echo 'selected'; ?>>Todos</option><option value="activa" <?php if($f_estado==='activa') echo 'selected'; ?>>Reservada</option><option value="cancelada" <?php if($f_estado==='cancelada') echo 'selected'; ?>>Cancelada</option></select></div>
      <div><label for="fHoraDesde">Desde</label><input id="fHoraDesde" class="form-control" type="time" name="hora_desde" value="<?php echo salas_reuniones_h($f_hora_desde); ?>"></div>
      <div><label for="fHoraHasta">Hasta</label><input id="fHoraHasta" class="form-control" type="time" name="hora_hasta" value="<?php echo salas_reuniones_h($f_hora_hasta); ?>"></div>
      <div><label for="fOrden">Ordenar por</label><select id="fOrden" class="form-control" name="orden"><option value="recientes" <?php if($orden==='recientes') echo 'selected'; ?>>Más recientes primero</option><option value="antiguas" <?php if($orden==='antiguas') echo 'selected'; ?>>Más antiguas primero</option><option value="proximas" <?php if($orden==='proximas') echo 'selected'; ?>>Próximas reuniones primero</option></select></div>
      <div class="sala-filter-actions"><a class="btn btn-white btn-sm sala-hoy" href="<?php echo salas_reuniones_h(salas_reuniones_url(array('cal'=>date('Y-m'),'fecha'=>date('Y-m-d'),'area'=>$f_area,'estado'=>$f_estado,'hora_desde'=>$f_hora_desde,'hora_hasta'=>$f_hora_hasta,'orden'=>$orden))); ?>">Hoy</a></div>
      <div class="sala-filter-actions"><a class="btn btn-white btn-sm" href="<?php echo salas_reuniones_h(salas_reuniones_url(array('cal'=>$cal))); ?>"><i class="fa fa-times" aria-hidden="true"></i> Limpiar</a></div>
    </div>
  </form>
  <div class="row">
    <div class="col-md-8 col-sm-12">
      <div class="grid simple">
        <div class="grid-title" style="padding:12px 15px;border-bottom:1px solid #e5e9ec;background:#fafbfc;"><h4 style="margin:0;font-size:14px;font-weight:700;color:#1e293b;"><?php echo $fecha!=='' ? 'Reservas del '.salas_reuniones_h(date('d/m/Y',strtotime($fecha))) : 'Todas las reservas'; ?> <small>(<?php echo count($reservas_dia); ?>)</small></h4></div>
        <div class="grid-body" style="padding:14px;">
          <?php if(count($reservas_dia)===0) { ?><div class="text-center" style="padding:18px 8px;color:#64748b;">No hay reservas con estos filtros.</div><?php } else { foreach($reservas_dia as $r) { $estado_ui=salas_reuniones_estado_visual($r); $clase_estado=$r['estado']==='cancelada' ? 'cancelada' : ($r['estado']==='confirmada' ? 'confirmada' : ($estado_ui==='Horario finalizado' ? 'finalizada' : '')); $r['estado_visual']=$estado_ui; $puede=salas_reuniones_puede_gestionar($r); $paso=salas_reuniones_fecha_hora_pasada($r['fecha'],substr($r['hora_inicio'],0,5)); $r['puede_editar_ui']=$puede && !$paso && in_array($r['estado'],array('activa','confirmada'),true); $r['puede_cancelar_ui']=$r['puede_editar_ui']; $payload=salas_reuniones_h(json_encode($r)); ?>
          <article class="sala-reserva-card <?php echo salas_reuniones_h($clase_estado); ?>">
            <div class="row"><div class="col-sm-8"><div class="sala-reserva-hora"><?php echo salas_reuniones_h(substr($r['hora_inicio'],0,5).' – '.substr($r['hora_fin'],0,5)); ?></div><div class="sala-reserva-meta"><strong><?php echo salas_reuniones_h($r['area_nombre']); ?></strong> · <?php echo salas_reuniones_h($r['sala_nombre']); ?><br><i class="fa fa-user"></i> <?php echo salas_reuniones_h(trim($r['empleado_nombre'])); ?></div></div><div class="col-sm-4 text-right"><span class="sala-estado <?php echo salas_reuniones_h($clase_estado); ?>"><?php echo salas_reuniones_h($estado_ui); ?></span></div></div>
            <div class="sala-reserva-motivo"><?php echo nl2br(salas_reuniones_h($r['motivo'])); ?></div>
            <div style="margin-top:10px;"><button type="button" class="btn btn-white btn-xs btn-ver-reserva" data-reserva="<?php echo $payload; ?>"><i class="fa fa-eye"></i> Ver</button>
            <?php if($puede && !$paso && in_array($r['estado'],array('activa','confirmada'),true)) { ?><button type="button" class="btn btn-white btn-xs btn-editar-reserva" data-reserva="<?php echo $payload; ?>"><i class="fa fa-pencil"></i> Editar</button><?php } ?>
            <?php if($puede && !$paso && in_array($r['estado'],array('activa','confirmada'),true)) { ?><form method="post" style="display:inline;" onsubmit="return confirm('¿Cancelar esta reserva? Se conservará en el historial.');"><input type="hidden" name="csrf_tarea_extra" value="<?php echo salas_reuniones_h($csrf_modulo_token); ?>"><input type="hidden" name="id_reserva" value="<?php echo intval($r['id_reserva']); ?>"><input type="hidden" name="fecha" value="<?php echo salas_reuniones_h($fecha); ?>"><input type="hidden" name="cal" value="<?php echo salas_reuniones_h($cal); ?>"><input type="hidden" name="orden" value="<?php echo salas_reuniones_h($orden); ?>"><button class="btn btn-danger btn-xs" type="submit" name="cancelar_reserva" value="1"><i class="fa fa-times"></i> Cancelar</button></form><?php } ?>
            </div>
          </article>
          <?php } } ?>
        </div>
      </div>

    </div>

    <div class="col-md-4 col-sm-12">
      <div class="grid simple">
        <div class="grid-title" style="padding:14px 16px;border-bottom:1px solid #e5e9ec;background:#fafbfc;">
          <h4 style="margin:0 0 10px;font-size:14px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px;"><i class="fa fa-calendar text-info" style="font-size:15px;"></i> Calendario de reuniones</h4>
          <div class="sala-cal-header">
            <a class="btn btn-white sala-arrow" href="<?php echo salas_reuniones_h(salas_reuniones_url(array('cal'=>$mes_anterior,'fecha'=>$mes_anterior.'-01','area'=>$f_area,'estado'=>$f_estado,'hora_desde'=>$f_hora_desde,'hora_hasta'=>$f_hora_hasta,'orden'=>$orden))); ?>" title="Mes anterior"><i class="fa fa-chevron-left"></i></a>
            <form method="get" id="formCalendario">
              <input type="hidden" name="cal" id="calCalendario" value="<?php echo salas_reuniones_h($cal); ?>"><input type="hidden" name="fecha" id="fechaCalendario" value="<?php echo salas_reuniones_h($fecha); ?>"><input type="hidden" name="area" value="<?php echo salas_reuniones_h($f_area); ?>"><input type="hidden" name="estado" value="<?php echo salas_reuniones_h($f_estado); ?>"><input type="hidden" name="hora_desde" value="<?php echo salas_reuniones_h($f_hora_desde); ?>"><input type="hidden" name="hora_hasta" value="<?php echo salas_reuniones_h($f_hora_hasta); ?>"><input type="hidden" name="orden" value="<?php echo salas_reuniones_h($orden); ?>">
              <select class="sala-month" id="selectorMes" aria-label="Mes" onchange="salaCambiarCalendario()"><option value="1" <?php if($mes===1) echo 'selected'; ?>>Enero</option><option value="2" <?php if($mes===2) echo 'selected'; ?>>Febrero</option><option value="3" <?php if($mes===3) echo 'selected'; ?>>Marzo</option><option value="4" <?php if($mes===4) echo 'selected'; ?>>Abril</option><option value="5" <?php if($mes===5) echo 'selected'; ?>>Mayo</option><option value="6" <?php if($mes===6) echo 'selected'; ?>>Junio</option><option value="7" <?php if($mes===7) echo 'selected'; ?>>Julio</option><option value="8" <?php if($mes===8) echo 'selected'; ?>>Agosto</option><option value="9" <?php if($mes===9) echo 'selected'; ?>>Septiembre</option><option value="10" <?php if($mes===10) echo 'selected'; ?>>Octubre</option><option value="11" <?php if($mes===11) echo 'selected'; ?>>Noviembre</option><option value="12" <?php if($mes===12) echo 'selected'; ?>>Diciembre</option></select>
              <select class="sala-year" id="selectorAnio" aria-label="Año" onchange="salaCambiarCalendario()"><?php $anio_hoy=intval(date('Y')); for($y=min($anio_hoy-2,$anio);$y<=max($anio_hoy+5,$anio);$y++) { ?><option value="<?php echo $y; ?>" <?php if($anio===$y) echo 'selected'; ?>><?php echo $y; ?></option><?php } ?></select>
            </form>
            <button type="button" class="btn btn-white" onclick="salaLimpiarCalendario()" title="Volver al día actual"><i class="fa fa-refresh"></i> Limpiar</button>
            <a class="btn btn-white sala-arrow" href="<?php echo salas_reuniones_h(salas_reuniones_url(array('cal'=>$mes_siguiente,'fecha'=>$mes_siguiente.'-01','area'=>$f_area,'estado'=>$f_estado,'hora_desde'=>$f_hora_desde,'hora_hasta'=>$f_hora_hasta,'orden'=>$orden))); ?>" title="Mes siguiente"><i class="fa fa-chevron-right"></i></a>
          </div>
        </div>
        <div class="grid-body" style="padding:10px 15px;">
          <table class="sala-cal"><thead><tr><th>Lu</th><th>Ma</th><th>Mi</th><th>Ju</th><th>Vi</th><th>Sá</th><th>Do</th></tr></thead><tbody>
            <?php $dia=1; for($fila=0;$fila<6 && $dia<=$dias_mes;$fila++) { ?><tr><?php for($col=1;$col<=7;$col++) { if(($fila===0 && $col<$primer_dia) || $dia>$dias_mes) { ?><td class="cal-mini-empty"></td><?php } else { $fecha_dia=sprintf('%04d-%02d-%02d',$anio,$mes,$dia); $n_reservas=isset($dias_reservados[$fecha_dia]) ? intval($dias_reservados[$fecha_dia]) : 0; $clases=array('cal-mini-day'); if($fecha_dia===$fecha) $clases[]='cal-mini-sel'; if($fecha_dia===date('Y-m-d')) $clases[]='cal-mini-hoy'; if($n_reservas>0) $clases[]='cal-mini-has-events'; $params=array('cal'=>$cal,'fecha'=>$fecha_dia,'area'=>$f_area,'estado'=>$f_estado,'hora_desde'=>$f_hora_desde,'hora_hasta'=>$f_hora_hasta,'orden'=>$orden); ?><td class="<?php echo salas_reuniones_h(implode(' ',$clases)); ?>"><a href="<?php echo salas_reuniones_h(salas_reuniones_url($params)); ?>" title="<?php echo $n_reservas>0 ? intval($n_reservas).' reserva(s) activa(s) o confirmada(s)' : 'Sin reservas'; ?>"><span class="cal-mini-num"><?php echo $dia; ?></span><div class="cal-mini-dots"><?php if($n_reservas>0) { ?><span class="dot dot-blue"></span><span class="cal-mini-count"><?php echo $n_reservas; ?></span><?php } ?></div></a></td><?php $dia++; } } ?></tr><?php } ?>
          </tbody></table>
          <div class="sala-cal-legend"><span><span class="dot dot-blue"></span>Día con reservas activas/confirmadas</span></div>
        </div>
      </div>

      <div class="grid simple">
        <div class="grid-title" style="padding:12px 15px;border-bottom:1px solid #e5e9ec;background:#fafbfc;"><h4 style="margin:0;font-size:14px;font-weight:700;color:#1e293b;"><i class="fa fa-history text-warning"></i> Historial de reuniones</h4></div>
        <div class="grid-body" style="padding:12px;">
          <div class="sala-history-scroll"><?php if(count($reservas_historial)===0) { ?><p class="muted text-center" style="padding:10px 0;">No hay reuniones anteriores ni reservas canceladas.</p><?php } else { ?><table class="table table-hover sala-history-table"><thead><tr><th>Fecha</th><th>Horario</th><th>Área</th><th>Solicitante</th></tr></thead><tbody><?php foreach($reservas_historial as $r) { ?><tr><td><?php echo salas_reuniones_h(date('d/m/Y',strtotime($r['fecha']))); ?></td><td><?php echo salas_reuniones_h(substr($r['hora_inicio'],0,5).'–'.substr($r['hora_fin'],0,5)); ?></td><td><?php echo salas_reuniones_h($r['area_nombre']); ?></td><td><?php echo salas_reuniones_h(trim($r['empleado_nombre'])); ?></td></tr><?php } ?></tbody></table><?php if(count($reservas_historial)>=$historial_limite && $historial_limite<1000) { ?><div class="text-center"><a class="btn btn-white btn-sm" href="<?php echo salas_reuniones_h(salas_reuniones_url(array('cal'=>$cal,'fecha'=>$fecha,'area'=>$f_area,'estado'=>$f_estado,'hora_desde'=>$f_hora_desde,'hora_hasta'=>$f_hora_hasta,'orden'=>$orden,'historial_limite'=>min(1000,$historial_limite+20)))); ?>">Ver más</a></div><?php } ?><?php } ?></div>
        </div>
      </div>
    </div>
  </div>
</div></div>

<div class="modal fade" id="modalReserva" tabindex="-1" role="dialog" aria-hidden="true">
 <div class="modal-dialog"><div class="modal-content">
  <form method="post" id="formReserva">
   <input type="hidden" name="csrf_tarea_extra" value="<?php echo salas_reuniones_h($csrf_modulo_token); ?>">
   <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button><h4 class="modal-title" id="modalReservaTitulo">Nuevo turno</h4></div>
   <div class="modal-body">
    <?php if($modal_abierto && $mensaje!=='') { ?><div class="alert alert-danger"><?php echo salas_reuniones_h($mensaje); ?></div><?php } ?>
   <input type="hidden" name="id_reserva" id="reservaId" value="<?php echo intval($modal_id); ?>"><input type="hidden" name="cal" value="<?php echo salas_reuniones_h($cal); ?>"><input type="hidden" name="orden" value="<?php echo salas_reuniones_h($orden); ?>">
    <div class="sala-modal-grid">
      <div><label>Sala</label><div class="sala-modal-info">Sala de reuniones</div></div>
      <div><label>Área solicitante</label><div class="sala-modal-info" id="modalAreaTexto"><?php echo salas_reuniones_h($area_formulario_nombre); ?></div><input type="hidden" id="modalArea" name="area" value="<?php echo salas_reuniones_h($area_formulario); ?>"></div>
      <div class="span-two"><label>Solicitante</label><div class="sala-modal-info"><i class="fa fa-user"></i> <?php echo salas_reuniones_h(acceso_nombre()); ?></div></div>
      <div><label for="modalFecha">Fecha</label><input class="form-control" type="date" name="fecha" id="modalFecha" value="<?php echo salas_reuniones_h($valores_form['fecha']); ?>" min="<?php echo salas_reuniones_h(date('Y-m-d')); ?>" required></div>
      <div><label>Duración</label><div class="sala-modal-info" id="duracionReserva">—</div></div>
      <div><label for="modalInicio">Hora de inicio</label><input class="form-control" type="time" name="hora_inicio" id="modalInicio" value="<?php echo salas_reuniones_h(substr($valores_form['hora_inicio'],0,5)); ?>" step="300" required></div>
      <div><label for="modalFin">Hora de finalización</label><input class="form-control" type="time" name="hora_fin" id="modalFin" value="<?php echo salas_reuniones_h(substr($valores_form['hora_fin'],0,5)); ?>" step="300" required></div>
      <div class="span-two"><label for="modalMotivo">Motivo</label><textarea class="form-control" name="motivo" id="modalMotivo" maxlength="500" rows="3" required><?php echo salas_reuniones_h($valores_form['motivo']); ?></textarea></div>
      <div class="span-two"><div id="disponibilidadModal" class="sala-modal-info" role="status">Elegí fecha y horario para consultar disponibilidad.</div></div>
    </div>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" name="guardar_reserva" value="1" id="guardarReserva"><i class="fa fa-save"></i> Guardar turno</button></div>
  </form>
 </div></div>
</div>

<div class="modal fade" id="modalDetalleReserva" tabindex="-1" role="dialog" aria-labelledby="tituloDetalleReserva" aria-hidden="true">
 <div class="modal-dialog sala-detail-modal"><div class="modal-content">
  <div class="modal-header"><h4 class="modal-title" id="tituloDetalleReserva"><i class="fa fa-calendar-check-o text-info"></i> Detalle de la reunión</h4><span id="detalleReservaEstado" class="sala-estado"></span><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button></div>
  <div class="modal-body" id="detalleReservaBody"></div>
  <div class="modal-footer"><button type="button" class="btn btn-white" id="btnDetalleEditar" style="display:none;"><i class="fa fa-pencil"></i> Editar</button><button type="button" class="btn btn-danger" id="btnDetalleCancelar" style="display:none;"><i class="fa fa-times"></i> Cancelar reserva</button><form method="post" id="formCancelarDesdeDetalle" style="display:none;"><input type="hidden" name="csrf_tarea_extra" value="<?php echo salas_reuniones_h($csrf_modulo_token); ?>"><input type="hidden" name="cancelar_reserva" value="1"><input type="hidden" name="id_reserva" id="idReservaCancelarDetalle"><input type="hidden" name="fecha" id="fechaCancelarDetalle"><input type="hidden" name="cal" id="calCancelarDetalle"><input type="hidden" name="orden" value="<?php echo salas_reuniones_h($orden); ?>"></form><button type="button" class="btn btn-white" data-dismiss="modal">Cerrar</button></div>
 </div></div>
</div>

<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
<script>
(function($){
  var disponibilidadUrl=<?php echo json_encode(url_crm('ajax/salas-reuniones-disponibilidad.php')); ?>;
  var modoEdicion=<?php echo $modal_id>0 ? 'true' : 'false'; ?>;
  var reservaDetalleActual=null;
  function minutos(h){ if(!h || !/^\d{2}:\d{2}$/.test(h)) return null; var p=h.split(':'); return parseInt(p[0],10)*60+parseInt(p[1],10); }
  function actualizarDuracion(){ var a=minutos($('#modalInicio').val()), b=minutos($('#modalFin').val()); if(a===null||b===null||b<=a){$('#duracionReserva').text('Ingresá un horario válido');return null;} var m=b-a; $('#duracionReserva').text(Math.floor(m/60)+' h '+(m%60)+' min'); return {inicio:a,fin:b}; }
  function horaTxt(m){var h=Math.floor(m/60),n=m%60;return (h<10?'0':'')+h+':'+(n<10?'0':'')+n;}
  window.salaCambiarCalendario=function(){var mes=$('#selectorMes').val(),anio=$('#selectorAnio').val(),cal=anio+'-'+('0'+mes).slice(-2);$('#calCalendario').val(cal);$('#fechaCalendario').val(cal+'-01');$('#formCalendario').submit();};
  window.salaLimpiarCalendario=function(){var hoy=<?php echo json_encode(date('Y-m-d')); ?>,p=hoy.split('-');$('#selectorMes').val(String(parseInt(p[1],10)));$('#selectorAnio').val(String(parseInt(p[0],10)));$('#calCalendario').val(p[0]+'-'+p[1]);$('#fechaCalendario').val(hoy);$('#formCalendario').submit();};
  $('#fFecha,#fArea,#fEstado,#fHoraDesde,#fHoraHasta,#fOrden').on('change',function(){if((this.name==='hora_desde'||this.name==='hora_hasta')&&this.value!==''&&!/^\d{2}:\d{2}$/.test(this.value))return;this.form.submit();});
  function consultarDisponibilidad(){
    var intervalo=actualizarDuracion(), fecha=$('#modalFecha').val(), sala=<?php echo intval($salas_disp_id); ?>, excluir=$('#reservaId').val()||0;
    if(!intervalo||!fecha||!sala){$('#disponibilidadModal').text('Elegí fecha y horario para consultar disponibilidad.');$('#guardarReserva').prop('disabled',true);return;}
    $('#disponibilidadModal').text('Consultando disponibilidad…');$('#guardarReserva').prop('disabled',true);
    $.getJSON(disponibilidadUrl,{fecha:fecha,id_sala:sala,excepto:excluir},function(r){
      if(!r||!r.ok){$('#disponibilidadModal').text(r&&r.error?r.error:'No se pudo consultar la disponibilidad.');return;}
      var choque=false, horas=[]; $.each(r.reservas,function(_,x){var a=minutos(x.inicio),b=minutos(x.fin); if(a!==null&&b!==null){horas.push(x.inicio+'–'+x.fin); if(intervalo.inicio<b&&intervalo.fin>a) choque=true;}});
      if(choque){$('#disponibilidadModal').html('<strong style="color:#b91c1c;">Ese horario se superpone con una reserva.</strong> '+(horas.length?'Ocupados: '+$('<div>').text(horas.join(', ')).html():''));}
      else {$('#disponibilidadModal').html('<strong style="color:#166534;">Horario disponible.</strong> '+(horas.length?'Ocupados: '+$('<div>').text(horas.join(', ')).html():'No hay reservas activas en esta fecha.'));$('#guardarReserva').prop('disabled',false);}
    }).fail(function(){ $('#disponibilidadModal').text('No se pudo consultar la disponibilidad; el servidor volverá a validarla al guardar.'); });
  }
  function llenarReserva(r, editar){
    $('#reservaId').val(editar?r.id_reserva:0); $('#modalReservaTitulo').text(editar?'Editar turno':'Nuevo turno');
    var areaClave=editar?r.area_clave:<?php echo json_encode($area_preseleccionada); ?>,areaNombre=editar?r.area_nombre:<?php echo json_encode($area_formulario_nombre); ?>;
    $('#modalArea').val(areaClave);$('#modalAreaTexto').text(areaNombre||'Área activa no disponible');
    $('#modalFecha').val(editar?r.fecha:<?php echo json_encode($fecha_reserva_default); ?>);
    $('#modalInicio').val(editar?String(r.hora_inicio).substr(0,5):'09:00');$('#modalFin').val(editar?String(r.hora_fin).substr(0,5):'10:00');$('#modalMotivo').val(editar?r.motivo:'');
    consultarDisponibilidad(); $('#modalReserva').modal('show');
  }
  function escapeDetalle(valor){return $('<div>').text(valor==null?'':String(valor)).html();}
  function fechaDetalle(fecha){var p=String(fecha||'').split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:escapeDetalle(fecha);}
  function mostrarDetalleReserva(r){
    reservaDetalleActual=r;
    var horaInicio=String(r.hora_inicio||'').substr(0,5),horaFin=String(r.hora_fin||'').substr(0,5);
    var estado=r.estado_visual||r.estado||'Reservada';
    var claseEstado=r.estado==='cancelada'?'cancelada':(r.estado==='confirmada'?'confirmada':(estado==='Horario finalizado'?'finalizada':''));
    $('#detalleReservaEstado').attr('class','sala-estado '+claseEstado).text(estado);
    var html='<div class="sala-detail-hero"><div class="sala-detail-date"><i class="fa fa-calendar sala-detail-icon"></i>'+fechaDetalle(r.fecha)+'</div><div class="sala-detail-hours">'+escapeDetalle(horaInicio)+' <span style="font-size:17px;color:#64748b;">—</span> '+escapeDetalle(horaFin)+'</div></div>'+
      '<div class="row"><div class="col-sm-6"><div class="sala-detail-section"><div class="sala-detail-label"><i class="fa fa-building sala-detail-icon"></i>Sala</div><div class="sala-detail-value">'+escapeDetalle(r.sala_nombre)+'</div></div></div>'+
      '<div class="col-sm-6"><div class="sala-detail-section"><div class="sala-detail-label"><i class="fa fa-sitemap sala-detail-icon"></i>Área solicitante</div><div class="sala-detail-value">'+escapeDetalle(r.area_nombre)+'</div></div></div>'+
      '<div class="col-sm-6"><div class="sala-detail-section"><div class="sala-detail-label"><i class="fa fa-user sala-detail-icon"></i>Solicitante</div><div class="sala-detail-value">'+escapeDetalle(r.empleado_nombre)+'</div></div></div>'+
      '<div class="col-sm-12"><div class="sala-detail-section"><div class="sala-detail-label"><i class="fa fa-align-left sala-detail-icon"></i>Motivo</div><div class="sala-detail-value sala-detail-motivo">'+escapeDetalle(r.motivo)+'</div></div></div></div>';
    $('#detalleReservaBody').html(html);
    $('#btnDetalleEditar').toggle(!!r.puede_editar_ui);
    $('#btnDetalleCancelar').toggle(!!r.puede_cancelar_ui);
    $('#modalDetalleReserva').modal('show');
  }
  $('#btnNuevoTurno').on('click',function(){llenarReserva({},false);});
  $('.btn-editar-reserva').on('click',function(){try{llenarReserva($.parseJSON($(this).attr('data-reserva')),true);}catch(e){alert('No se pudieron cargar los datos de esta reserva.');}});
  $('.btn-ver-reserva').on('click',function(){try{mostrarDetalleReserva($.parseJSON($(this).attr('data-reserva')));}catch(e){alert('No se pudieron cargar los detalles.');}});
  $('#btnDetalleEditar').on('click',function(){if(!reservaDetalleActual)return;var r=reservaDetalleActual;$('#modalDetalleReserva').one('hidden.bs.modal',function(){llenarReserva(r,true);});$('#modalDetalleReserva').modal('hide');});
  $('#btnDetalleCancelar').on('click',function(){if(!reservaDetalleActual||!window.confirm('¿Cancelar esta reserva? Se conservará en el historial.'))return;$('#idReservaCancelarDetalle').val(reservaDetalleActual.id_reserva);$('#fechaCancelarDetalle').val(reservaDetalleActual.fecha);$('#calCancelarDetalle').val(String(reservaDetalleActual.fecha).substr(0,7));$('#formCancelarDesdeDetalle').submit();});
  $('#modalFecha,#modalInicio,#modalFin').on('change keyup',consultarDisponibilidad);
  $('#modalReserva').on('hidden.bs.modal',function(){if(!modoEdicion){$('#modalArea').prop('disabled',false);}});
  <?php if($modal_abierto) { ?>$('#modalReserva').modal('show');<?php } ?>
})(jQuery);
</script>
</body>
</html>
