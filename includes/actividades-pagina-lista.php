<?php
// Listado de actividades del area que esta abierta.
include_once BASE_PATH.'/includes/actividades.php';
require_once BASE_PATH.'/includes/navegacion-contexto.php';
$menu_activo='Actividades';
$area_act=acceso_area_pantalla();
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_act]) ? $etq[$area_act] : $area_act;
$crm_contexto_mapa_path=BASE_PATH.'/public/general/mapa-interactivo/contexto.php';
if(is_file($crm_contexto_mapa_path)) { require_once $crm_contexto_mapa_path; }
$crm_actividad_contexto_general = isset($_GET['contexto']) && $_GET['contexto'] === 'general'
	&& isset($_GET['origen_general'])
	&& function_exists('mapa_interactivo_contexto_general_valido')
	&& mapa_interactivo_contexto_general_valido($_GET['origen_general']);
$crm_actividad_nav_token_general = isset($_GET['contexto_general']) && is_string($_GET['contexto_general'])
	&& crm_navegacion_contexto_general_valido($_GET['contexto_general'])
	? $_GET['contexto_general']
	: '';
$crm_mapa_retorno_url = '';
if(isset($_GET['mapa_retorno']) && function_exists('mapa_interactivo_url_retorno'))
{
	$crm_mapa_retorno_url=mapa_interactivo_url_retorno(
		$_GET['mapa_retorno'],
		$crm_actividad_contexto_general ? $_GET['origen_general'] : ''
	);
	if($crm_mapa_retorno_url!=='' && $crm_actividad_nav_token_general!=='')
	{
		$crm_mapa_retorno_url.='&contexto_general='.urlencode($crm_actividad_nav_token_general);
	}
}
$crm_actividad_url_lista = function($extra=array()) use ($crm_actividad_contexto_general, $crm_actividad_nav_token_general) {
	if($crm_actividad_contexto_general)
	{
		$extra['contexto']='general';
		$extra['origen_general']=$_GET['origen_general'];
		if($crm_actividad_nav_token_general!=='') { $extra['contexto_general']=$crm_actividad_nav_token_general; }
		return url_crm('general/actividades.php?'.http_build_query($extra));
	}
	return tareas_link('actividades.php', $extra);
};

$ver_id=isset($_GET['id']) ? intval($_GET['id']) : 0;
if($_SERVER['REQUEST_METHOD']==='POST')
{
	$msg=null;
	$ir=$crm_actividad_url_lista($ver_id>0 ? array('id'=>$ver_id) : array());
	if(isset($_POST['marcar_asistencia']) && isset($_POST['id_inscripcion']))
	{
		$msg=actividades_marcar_asistencia(intval($_POST['id_inscripcion']), $_POST['marcar_asistencia']);
		if($msg==='') { $msg='Asistencia guardada.'; }
	}
	else if(isset($_POST['agregar_asistente']))
	{
		$msg=actividades_agregar_asistente($ver_id, isset($_POST['cliente_id']) ? $_POST['cliente_id'] : 0);
		if($msg==='') { $msg='Asistente registrado como presente.'; }
	}
	else if(isset($_POST['eliminar_actividad']))
	{
		$msg=actividades_eliminar($ver_id);
		if($msg==='') { $msg='Actividad eliminada.'; $ir=$crm_actividad_url_lista(); }
	}
	if($msg!==null)
	{
		$_SESSION['msgactividad']=$msg;
		header('Location: '.$ir);
		exit();
	}
}

$detalle=null;
$inscriptos=array();
$tareas_evento=array();
$puede_gestionar=false;
if($ver_id>0)
{
	$detalle=crm_evento_por_id($ver_id);
	if($detalle && !actividad_puede_ver($detalle))
	{
		$detalle=null;
	}
	if($detalle)
	{
		$inscriptos=crm_evento_inscriptos($ver_id);
		$tareas_evento=function_exists('crm_evento_tareas') ? crm_evento_tareas($ver_id) : array();
		$avances_info=function_exists('actividad_calcular_avances') ? actividad_calcular_avances($ver_id, $tareas_evento) : array('total_tareas'=>0, 'avance_general'=>null, 'areas'=>array(), 'tareas_calculadas'=>array());
		$puede_gestionar=actividad_puede_gestionar($detalle);
		$ver_seguimiento_completo=function_exists('actividad_puede_ver_seguimiento') ? actividad_puede_ver_seguimiento($detalle) : false;
		$id_emp_sesion=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;

		$tareas_evento_filtradas=array();
		foreach($tareas_evento as $tar)
		{
			$id_t_padre=intval($tar['id_tarea']);
			$es_resp_padre=false;
			if($id_emp_sesion>0 && function_exists('acceso_consulta_una'))
			{
				$chk_r=acceso_consulta_una("SELECT id FROM tarea_responsable WHERE id_tarea=? AND id_empleado=? LIMIT 1", 'ii', array($id_t_padre, $id_emp_sesion));
				if($chk_r) { $es_resp_padre=true; }
			}

			$subtareas_filtradas=array();
			if(isset($tar['subtareas']) && is_array($tar['subtareas']))
			{
				foreach($tar['subtareas'] as $sub)
				{
					$id_sub=intval($sub['id_tarea']);
					$es_resp_sub=false;
					if($id_emp_sesion>0 && function_exists('acceso_consulta_una'))
					{
						$chk_sr=acceso_consulta_una("SELECT id FROM tarea_responsable WHERE id_tarea=? AND id_empleado=? LIMIT 1", 'ii', array($id_sub, $id_emp_sesion));
						if($chk_sr) { $es_resp_sub=true; }
					}

					if($ver_seguimiento_completo || $es_resp_sub || $es_resp_padre)
					{
						$subtareas_filtradas[]=$sub;
					}
				}
			}

			if($ver_seguimiento_completo || $es_resp_padre || count($subtareas_filtradas)>0)
			{
				$tar['subtareas']=$subtareas_filtradas;
				$tareas_evento_filtradas[]=$tar;
			}
		}

		if(function_exists('crm_notificacion_marcar_vista_por_entidad'))
		{
			crm_notificacion_marcar_vista_por_entidad('actividad', $ver_id);
		}
	}
}
$lista_total=actividades_de_area($area_act);
$lista=actividades_filtrar($lista_total);
$tipos_act=array('reunion'=>'Reunión','capacitacion'=>'Capacitación','ronda'=>'Ronda de negocios','otro'=>'Otro');
$f_q=isset($_GET['q']) ? trim($_GET['q']) : '';
$f_tipo=isset($_GET['tipo']) ? $_GET['tipo'] : '';
$f_desde=isset($_GET['desde']) ? $_GET['desde'] : '';
$f_hasta=isset($_GET['hasta']) ? $_GET['hasta'] : '';
$f_estado=isset($_GET['estado']) ? $_GET['estado'] : '';
$f_area=isset($_GET['area']) ? $_GET['area'] : '';
$f_responsable=isset($_GET['responsable']) ? $_GET['responsable'] : '';
$f_orden=isset($_GET['orden']) && $_GET['orden']==='antiguas' ? 'antiguas' : 'recientes';
$dia_cal=isset($_GET['dia']) && actividad_fecha_valida($_GET['dia']) ? $_GET['dia'] : '';
$cal=isset($_GET['cal']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',(string)$_GET['cal']) && checkdate(1,1,intval(substr($_GET['cal'],0,4))) ? $_GET['cal'] : date('Y-m');
if($dia_cal!=='') { $cal=substr($dia_cal,0,7); }
$cal_partes=explode('-',$cal); $cal_anio=intval($cal_partes[0]); $cal_mes=intval($cal_partes[1]);
$cal_primer_dia=intval(date('N',strtotime($cal.'-01')));
$cal_dias_mes=intval(date('t',strtotime($cal.'-01')));
$eventos_calendario=actividades_eventos_calendario($lista_total,$cal_anio,$cal_mes);
$areas_filtro=array(); $responsables_filtro=array();
foreach($lista_total as $actividad)
{
	$ak=isset($actividad['area_clave']) ? $actividad['area_clave'] : '';
	if($ak!=='') { $areas_filtro[$ak]=isset($actividad['area_nombre']) && $actividad['area_nombre']!=='' ? $actividad['area_nombre'] : $ak; }
	$rr=trim(isset($actividad['responsable']) ? $actividad['responsable'] : '');
	if($rr!=='') { $responsables_filtro[$rr]=$rr; }
}
asort($areas_filtro); ksort($responsables_filtro);
$hay_filtro=($f_q!=='' || $f_tipo!=='' || $f_estado!=='' || $f_area!=='' || $f_responsable!=='' || $f_desde!=='' || $f_hasta!=='' || $dia_cal!=='');
$hoy=date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Actividades</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<meta content="" name="description" />
<meta content="" name="author" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
</head>
<body class="crm-actividades-page">
<?php crm_header_staff();?>
<div class="page-container row">
      <?php crm_menu_staff();?>
      <div class="clearfix"></div>
    </div>
  </div>
    <div class="page-content">
            <div class="clearfix"></div>
            <div class="content">
                <ul class="breadcrumb">
                    <li>
                        <p>ESTÁS AQUÍ</p>
                    </li>
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>"><?php echo texto_mostrar($nombre_area); ?></a></li>
                    <li><a href="<?php echo htmlspecialchars($crm_actividad_url_lista(), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $detalle ? '' : 'active'; ?>">Actividades</a></li>
                    <?php if($detalle) { ?>
                    <li><a href="#" class="active"><?php echo texto_mostrar($detalle['titulo']); ?></a></li>
                    <?php } ?>
                </ul>
                <?php if($crm_actividad_contexto_general) { include BASE_PATH.'/public/general/menu-seccion.php'; } else { include("menu-seccion.php"); } ?>
                <?php if(isset($_SESSION['msgactividad']) && $_SESSION['msgactividad']!='') { ?>
                <div class="alert alert-info"><?php echo texto_mostrar($_SESSION['msgactividad']); $_SESSION['msgactividad']=''; ?></div>
                <?php } ?>

                <?php if($detalle) { ?>
                <div class="page-title" style="overflow:visible;">
                    <h3 style="max-width:70%;"><?php echo texto_mostrar($detalle['titulo']); ?></h3>
                    <div class="pull-right" style="white-space:nowrap;">
                        <?php if($puede_gestionar) { ?>
                        <a href="<?php echo tareas_link('nueva-actividad.php', array('id'=>intval($detalle['id_evento']))); ?>" class="btn btn-primary btn-small">Editar</a>
                        <?php } ?>
                        <?php if($crm_mapa_retorno_url!=='') { ?>
                        <a href="<?php echo htmlspecialchars($crm_mapa_retorno_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-white btn-small">Volver al Mapa de eventos</a>
                        <?php } ?>
                        <a href="<?php echo htmlspecialchars($crm_actividad_url_lista(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-white btn-small">Volver</a>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div class="grid simple">
                    <div class="grid-body">
                        <p><?php echo texto_mostrar(isset($detalle['descripcion']) ? $detalle['descripcion'] : ''); ?></p>
                        <p><strong>Fecha:</strong> <?php echo fecha_mostrar($detalle['fecha']); ?>
                        · <strong>Tipo:</strong> <?php echo texto_mostrar(function_exists('crm_portal_tipo_actividad') ? crm_portal_tipo_actividad($detalle['tipo']) : $detalle['tipo']); ?></p>
                        <p><strong>Responsable:</strong> <?php echo texto_mostrar(trim(isset($detalle['responsable']) ? $detalle['responsable'] : '')!=='' ? $detalle['responsable'] : 'Sin asignar'); ?>
                        <?php
                        $org_txt=trim((string)(isset($detalle['organismo']) ? $detalle['organismo'] : ''));
                        if($org_txt=='' && isset($detalle['cliente_nombre'])) { $org_txt=trim((string)$detalle['cliente_nombre']); }
                        if($org_txt!='') { ?>
                        · <strong>Organismo / aliado:</strong> <?php echo texto_mostrar($org_txt); ?>
                        <?php } ?></p>
                        <p>
                            <?php if(intval(isset($detalle['autoinscripcion']) ? $detalle['autoinscripcion'] : 0)==1) { ?>
                            <span class="label label-success">Autoinscripción permitida</span>
                            · Público: <?php
                            $pub=isset($detalle['publico']) ? $detalle['publico'] : 'todos';
                            if($pub==='todos' || $pub==='') { echo 'Todos'; }
                            else if(function_exists('cliente_tipo_etiqueta')) { echo htmlspecialchars(cliente_tipo_etiqueta($pub==='persona' ? 'emprendedor' : $pub)); }
                            else { echo htmlspecialchars($pub); }
                            ?>
                            <?php } else { ?>
                            <span class="label">Sin autoinscripción</span>
                            · Solo el personal registra participantes
                            <?php } ?>
                        </p>
                        <hr style="margin:16px 0 14px; border:0; border-top:1px solid #e5e7eb;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <span style="font-weight:600; font-size:13px; color:#2c3e50;">Avance general:</span>
                                <?php if($avances_info['total_tareas'] > 0) { ?>
                                <span style="font-weight:bold; font-size:14px; color:#2c3e50;"><?php echo intval($avances_info['avance_general']); ?>%</span>
                                <?php } else { ?>
                                <span class="muted" style="font-size:13px; font-style:italic;">Sin tareas asignadas</span>
                                <?php } ?>
                            </div>
                            <?php if($avances_info['total_tareas'] > 0) { ?>
                            <div class="progress" style="margin-bottom:14px; height:10px; background:#e9ecef; border-radius:3px;">
                                <div class="progress-bar progress-bar-success" style="width:<?php echo intval($avances_info['avance_general']); ?>%;"></div>
                            </div>
                            
                            <?php if(count($avances_info['areas']) > 0) { ?>
                            <div style="margin-top:10px;">
                                <div style="font-weight:600; font-size:12px; color:#555; margin-bottom:8px;">Áreas participantes:</div>
                                <div class="row" style="margin-left:-8px; margin-right:-8px;">
                                    <?php foreach($avances_info['areas'] as $ar_c) { ?>
                                    <div class="col-md-3 col-sm-6" style="padding-left:8px; padding-right:8px; margin-bottom:10px;">
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:8px 10px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; font-size:12px;">
                                                <span style="font-weight:600; color:#334155; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo texto_mostrar($ar_c['nombre']); ?>">
                                                    <?php echo texto_mostrar($ar_c['nombre']); ?>
                                                </span>
                                                <span style="font-weight:700; color:#0f172a; margin-left:6px;"><?php echo intval($ar_c['avance']); ?>%</span>
                                            </div>
                                            <div class="progress progress-small" style="margin-bottom:0; height:6px; background:#e2e8f0;">
                                                <div class="progress-bar progress-bar-success" style="width:<?php echo intval($ar_c['avance']); ?>%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="grid simple">
                    <div class="grid-title"><h4>Seguimiento de tareas generadas (<?php echo count($tareas_evento_filtradas); ?>)</h4></div>
                    <div class="grid-body">
                        <?php if(count($tareas_evento_filtradas)==0) { ?>
                        <p class="muted"><?php echo ($avances_info['total_tareas']==0 ? 'Sin tareas asignadas.' : 'No tenés tareas asignadas en esta actividad para consultar.'); ?></p>
                        <?php } else {
                        	$mapa_est=array('no_iniciada'=>'No iniciada','en_proceso'=>'En proceso','pausada'=>'Pausada','terminada'=>'Terminada','cancelada'=>'Eliminada');
                        	$grupos_area=array();
                        	foreach($tareas_evento_filtradas as $tar)
                        	{
                        		$area_k=isset($tar['area_clave']) && $tar['area_clave']!='' ? $tar['area_clave'] : 'sin_area';
                        		$grupos_area[$area_k][]=$tar;
                        	}
                        	foreach($grupos_area as $k_area_g=>$lista_tar_g) {
                        		$nom_area_g=isset($lista_tar_g[0]['area_nombre']) && $lista_tar_g[0]['area_nombre']!='' ? $lista_tar_g[0]['area_nombre'] : 'Sin área';
                        		$av_area_header=isset($avances_info['areas'][$k_area_g]['avance']) ? $avances_info['areas'][$k_area_g]['avance'] : 0;
                        ?>
                        <div style="margin-bottom:16px; border:1px solid #e5e7eb; border-radius:4px; overflow:hidden;">
                            <div style="background:#f4f5f7; padding:8px 12px; color:#2c3e50; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <i class="fa fa-folder-o"></i> <strong>Área participante: <?php echo texto_mostrar($nom_area_g); ?></strong>
                                    <span class="muted" style="font-size:12px; margin-left:4px;">(<?php echo count($lista_tar_g); ?> tarea<?php echo count($lista_tar_g)==1?'':'s'; ?>)</span>
                                </div>
                                <div style="display:flex; align-items:center;">
                                    <span style="font-size:12px; font-weight:600; color:#334155; margin-right:8px;">Avance: <?php echo intval($av_area_header); ?>%</span>
                                    <div class="progress progress-small" style="margin-bottom:0; height:8px; width:90px; display:inline-block; background:#e2e8f0; vertical-align:middle;">
                                        <div class="progress-bar progress-bar-success" style="width:<?php echo intval($av_area_header); ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                            <table class="table table-hover" style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>Tarea / Subtarea</th>
                                        <th>Responsables</th>
                                        <th>Estado</th>
                                        <th>Avance</th>
                                        <th style="width:70px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($lista_tar_g as $tar) {
                                	$id_t=intval($tar['id_tarea']);
                                	$est=isset($mapa_est[$tar['estado']]) ? $mapa_est[$tar['estado']] : $tar['estado'];
                                	$resp=trim((string)(isset($tar['responsables']) ? $tar['responsables'] : ''));
                                	$subt=isset($tar['subtareas']) && is_array($tar['subtareas']) ? $tar['subtareas'] : array();
                                	$av_tar=isset($avances_info['tareas_calculadas'][$id_t]['avance_calculado']) ? $avances_info['tareas_calculadas'][$id_t]['avance_calculado'] : ($tar['estado']==='terminada' ? 100 : max(0, min(100, intval($tar['avance']))));
                                ?>
                                <tr style="background:#fff;">
                                    <td>
                                        <strong><?php echo texto_mostrar($tar['titulo']); ?></strong>
                                        <?php if(count($subt)>0) { ?>
                                        <button type="button" class="btn btn-xs btn-mini btn-white" onclick="var subs=document.querySelectorAll('.crm-sub-<?php echo $id_t; ?>'); subs.forEach(function(s){ s.style.display=(s.style.display==='none'?'':'none'); });" style="margin-left:8px; color:#2563eb;" title="Ver subtareas">
                                            <i class="fa fa-level-down"></i> <?php echo count($subt); ?> subtarea(s)
                                        </button>
                                        <?php } ?>
                                    </td>
                                    <td><?php echo texto_mostrar($resp!='' ? $resp : 'Sin responsable'); ?></td>
                                    <td><span class="label <?php echo $tar['estado']==='terminada' ? 'label-success' : ($tar['estado']==='en_proceso' ? 'label-info' : ''); ?>"><?php echo texto_mostrar($est); ?></span></td>
                                    <td>
                                        <div class="progress progress-small" style="margin-bottom:2px; height:8px; width:90px; display:inline-block; vertical-align:middle; background:#e2e8f0;">
                                            <div class="progress-bar progress-bar-success" style="width: <?php echo $av_tar; ?>%;"></div>
                                        </div>
                                        <span style="font-size:12px; font-weight:600; margin-left:4px;"><?php echo $av_tar; ?>%</span>
                                    </td>
                                    <td>
                                        <?php if(function_exists('acceso_puede') && acceso_puede($tar['area_clave'])) { ?>
                                        <a href="<?php echo url_crm($tar['area_clave'].'/ver-tarea.php?id='.$id_t); ?>" class="btn btn-white btn-xs">Ver</a>
                                        <?php } else { ?>
                                        <span class="muted" style="font-size:11px;" title="Solo lectura">Solo lectura</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php foreach($subt as $sub) {
                                	$id_s=intval($sub['id_tarea']);
                                	$est_s=isset($mapa_est[$sub['estado']]) ? $mapa_est[$sub['estado']] : $sub['estado'];
                                	$resp_s=trim((string)(isset($sub['responsables']) ? $sub['responsables'] : ''));
                                	$av_sub=($sub['estado']==='terminada' ? 100 : max(0, min(100, intval($sub['avance']))));
                                ?>
                                <tr class="crm-sub-<?php echo $id_t; ?>" style="display:none; background:#f8fafc;">
                                    <td style="padding-left:32px;">
                                        <i class="fa fa-angle-right text-muted"></i> <span><?php echo texto_mostrar($sub['titulo']); ?></span>
                                    </td>
                                    <td style="font-size:12px; color:#64748b;"><?php echo texto_mostrar($resp_s!='' ? $resp_s : 'Sin responsable'); ?></td>
                                    <td><span class="label" style="background:#e2e8f0; color:#334155; font-size:11px;"><?php echo texto_mostrar($est_s); ?></span></td>
                                    <td>
                                        <div class="progress progress-small" style="margin-bottom:2px; height:6px; width:70px; display:inline-block; vertical-align:middle; background:#e2e8f0;">
                                            <div class="progress-bar progress-bar-info" style="width: <?php echo $av_sub; ?>%;"></div>
                                        </div>
                                        <span style="font-size:11px; margin-left:4px; color:#475569;"><?php echo $av_sub; ?>%</span>
                                    </td>
                                    <td>
                                        <?php if(function_exists('acceso_puede') && acceso_puede($sub['area_clave'])) { ?>
                                        <a href="<?php echo url_crm($sub['area_clave'].'/ver-tarea.php?id='.$id_s); ?>" class="btn btn-white btn-xs">Ver</a>
                                        <?php } else { ?>
                                        <span class="muted" style="font-size:11px;" title="Solo lectura">Solo lectura</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>
                        <?php } ?>
                    </div>
                </div>
                <div class="grid simple">
                    <div class="grid-title"><h4>Participantes (<?php echo count($inscriptos); ?>)</h4></div>
                    <div class="grid-body">
                        <?php if($puede_gestionar) { ?>
                        <form method="post" action="" class="crm-asistente-form" autocomplete="off">
                            <input type="hidden" name="cliente_id" id="cliente_id" value="">
                            <label>Registrar asistente (presente)</label>
                            <div class="crm-asistente-fila">
                                <input type="text" id="buscar_cliente" class="form-control" placeholder="Buscar cliente por nombre, DNI o CUIT…">
                                <input type="text" id="cliente_nombre" class="form-control" readonly placeholder="Ningún cliente elegido">
                                <button type="submit" name="agregar_asistente" value="1" class="btn btn-primary">Marcar presente</button>
                            </div>
                            <div id="resultados_cliente" style="display:none; border:1px solid #ddd; max-height:180px; overflow:auto; background:#fff;"></div>
                            <p class="muted" style="margin:6px 0 0;">Aunque no haya autoinscripción, podés registrar acá a quienes asistieron.</p>
                        </form>
                        <?php } ?>
                        <?php if(count($inscriptos)==0) { ?>
                        <p style="margin-top:12px;">Todavía no hay participantes.</p>
                        <?php } else { ?>
                        <table class="table table-hover" style="margin-top:12px;">
                            <thead><tr><th>Cliente</th><th>Tipo</th><th>Estado</th><th>Fecha</th><?php if($puede_gestionar) { ?><th></th><?php } ?></tr></thead>
                            <tbody>
                            <?php foreach($inscriptos as $ins) { ?>
                            <tr>
                                <td><?php echo texto_mostrar($ins['cliente_nombre']); ?></td>
                                <td><?php echo htmlspecialchars(function_exists('cliente_tipo_etiqueta') ? cliente_tipo_etiqueta($ins['tipo_cliente']) : $ins['tipo_cliente']); ?></td>
                                <td><?php
                                if($ins['estado']=='asistio') { echo '<span class="label label-success">Presente</span>'; }
                                else if($ins['estado']=='ausente') { echo '<span class="label label-important">Ausente</span>'; }
                                else { echo '<span class="label">Inscripto</span>'; }
                                ?></td>
                                <td><?php echo fecha_mostrar($ins['fecha_inscripcion']); ?></td>
                                <?php if($puede_gestionar) { ?>
                                <td>
                                    <form method="post" action="" style="display:inline;">
                                        <input type="hidden" name="id_inscripcion" value="<?php echo intval($ins['id']); ?>">
                                        <button type="submit" name="marcar_asistencia" value="asistio" class="btn btn-success btn-xs">Presente</button>
                                        <button type="submit" name="marcar_asistencia" value="ausente" class="btn btn-white btn-xs">Ausente</button>
                                    </form>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>
                <?php if(function_exists('actividad_puede_eliminar') && actividad_puede_eliminar($detalle)) {
                	$n_tar_ev=count($tareas_evento);
                ?>
                <div class="grid simple" style="margin-bottom:20px; border:1px solid #ebccd1;">
                    <div class="grid-title no-border" style="background:#f2dede;"><h4 style="color:#a94442;"><i class="fa fa-warning"></i> Zona de peligro</h4></div>
                    <div class="grid-body no-border">
                        <p>Eliminar la actividad la oculta del CRM y del portal<?php if($n_tar_ev>0) { ?> y <strong>cancela <?php echo intval($n_tar_ev); ?> tarea(s)</strong> vinculadas<?php } ?>.</p>
                        <form method="post" action="" style="max-width:420px;" onsubmit="return confirm('¿Confirmás eliminar esta actividad? Esta acción no se puede deshacer fácilmente.');">
                            <div class="form-group">
                                <label>Escribí <strong>ELIMINAR</strong> para confirmar</label>
                                <input type="text" name="confirm_eliminar" class="form-control" autocomplete="off" required placeholder="ELIMINAR">
                            </div>
                            <div class="checkbox check-danger" style="padding-left:0;">
                                <input type="checkbox" name="acepto_baja_tareas" value="1" id="chk_baja_tareas" required>
                                <label for="chk_baja_tareas">Entiendo que se cancelarán las tareas vinculadas</label>
                            </div>
                            <button type="submit" name="eliminar_actividad" value="1" class="btn btn-danger"><i class="fa fa-trash-o"></i> Eliminar actividad</button>
                        </form>
                    </div>
                </div>
                <?php } ?>

                <?php } else { ?>
                <div class="page-title crm-actividades-page-title crm-compact-heading"><h3>Actividades</h3><?php if(actividad_puede_crear()) { ?><a href="<?php echo tareas_link('nueva-actividad.php'); ?>" class="btn btn-primary btn-cons">Nueva actividad</a><?php } ?></div>
                <div class="grid simple crm-actividades-filter-card"><div class="grid-body no-border" style="padding:12px 14px;">
                            <form method="get" action="actividades.php" class="form-inline crm-filtros-act">
                                <input type="hidden" name="cal" value="<?php echo texto_mostrar($cal); ?>"><input type="hidden" name="dia" value="<?php echo texto_mostrar($dia_cal); ?>">
                                <div class="crm-filtros-act-row crm-filtros-act-principal<?php echo count($areas_filtro)>1?' crm-filtros-act-con-area':''; ?>">
                                    <div class="form-group"><label>Buscar</label><input type="search" name="q" value="<?php echo texto_mostrar($f_q); ?>" class="form-control" placeholder="Nombre o descripción" autocomplete="off" oninput="clearTimeout(window.crmActividadesBuscarTimer);var f=this.form;window.crmActividadesBuscarTimer=setTimeout(function(){f.submit();},450);"></div>
                                    <div class="form-group"><label>Estado</label><select name="estado" class="form-control" onchange="this.form.submit()"><option value="">Todos</option><option value="proxima" <?php if($f_estado==='proxima') echo 'selected'; ?>>Próxima</option><option value="realizada" <?php if($f_estado==='realizada') echo 'selected'; ?>>Realizada</option><option value="pasada" <?php if($f_estado==='pasada') echo 'selected'; ?>>Pasada sin registro de realización</option><option value="cancelada" <?php if($f_estado==='cancelada') echo 'selected'; ?>>Cancelada</option></select></div>
                                    <div class="form-group"><label>Tipo</label><select name="tipo" class="form-control" onchange="this.form.submit()"><option value="">Todos</option><?php foreach($tipos_act as $k=>$t) { ?><option value="<?php echo texto_mostrar($k); ?>" <?php if($f_tipo===$k) echo 'selected'; ?>><?php echo texto_mostrar($t); ?></option><?php } ?></select></div>
                                    <div class="form-group"><label>Responsable</label><select name="responsable" class="form-control" onchange="this.form.submit()"><option value="">Todos</option><?php foreach($responsables_filtro as $nombre) { ?><option value="<?php echo texto_mostrar($nombre); ?>" <?php if($f_responsable===$nombre) echo 'selected'; ?>><?php echo texto_mostrar($nombre); ?></option><?php } ?></select></div>
                                    <?php if(count($areas_filtro)>1) { ?><div class="form-group"><label>Área</label><select name="area" class="form-control" onchange="this.form.submit()"><option value="">Todas las visibles</option><?php foreach($areas_filtro as $clave=>$nombre) { ?><option value="<?php echo texto_mostrar($clave); ?>" <?php if($f_area===$clave) echo 'selected'; ?>><?php echo texto_mostrar($nombre); ?></option><?php } ?></select></div><?php } ?>
                                    <div class="form-group"><label>Ordenar por</label><select name="orden" class="form-control" onchange="this.form.submit()"><option value="recientes" <?php if($f_orden==='recientes') echo 'selected'; ?>>Más recientes primero</option><option value="antiguas" <?php if($f_orden==='antiguas') echo 'selected'; ?>>Más antiguas primero</option></select></div>
                                    <a href="<?php echo htmlspecialchars(tareas_link('actividades.php'),ENT_QUOTES,'UTF-8'); ?>" class="btn btn-white crm-act-limpiar"><i class="fa fa-times"></i> Limpiar</a>
                                </div>
                            </form>
                            <p class="muted" style="margin:10px 0 0;">Mostrando <?php echo count($lista); ?> de <?php echo count($lista_total); ?> actividades visibles.</p>
                </div></div>
                <div class="row crm-actividades-layout">
                    <div class="col-md-8">
                        <?php if(count($lista)==0) { ?><div class="grid simple"><div class="grid-body"><p><?php echo $hay_filtro ? 'No hay actividades con esos filtros.' : 'Todavía no hay actividades cargadas.'; ?></p></div></div><?php } ?>
                        <?php foreach($lista as $item) { $puede_item=actividad_puede_gestionar($item); $estado_item=actividad_estado_lista($item,$hoy); $etiquetas=array('proxima'=>'Próxima','realizada'=>'Realizada','pasada'=>'Pasada · sin registro de realización','cancelada'=>'Cancelada'); $clases_estado=array('proxima'=>'label-info','realizada'=>'label-success','pasada'=>'label-default','cancelada'=>'label-important'); ?>
                        <div class="grid simple"><div class="grid-title"><h4><a href="<?php echo htmlspecialchars($crm_actividad_url_lista(array('id'=>intval($item['id']))), ENT_QUOTES, 'UTF-8'); ?>"><?php echo texto_mostrar($item['titulo']); ?></a></h4><div class="tools"><span class="label <?php echo $clases_estado[$estado_item]; ?>"><?php echo texto_mostrar($etiquetas[$estado_item]); ?></span></div></div>
                            <div class="grid-body"><p><?php echo texto_mostrar(isset($item['descripcion'])?$item['descripcion']:''); ?></p>
                                <div class="crm-actividad-card-meta">
                                    <div class="crm-actividad-card-persona">
                                        <span class="crm-actividad-card-responsable"><i class="fa fa-user" aria-hidden="true"></i><?php echo texto_mostrar(isset($item['responsable']) && trim($item['responsable'])!==''?$item['responsable']:'Sin asignar'); ?></span>
                                        <span class="crm-actividad-card-area"><i class="fa fa-folder-o" aria-hidden="true"></i><?php echo texto_mostrar(isset($item['area_nombre']) && $item['area_nombre']!==''?$item['area_nombre']:$item['area_clave']); ?><?php if(isset($item['organismo']) && $item['organismo']!=='') { ?> <span class="crm-actividad-card-organismo"><i class="fa fa-institution" aria-hidden="true"></i><?php echo texto_mostrar($item['organismo']); ?></span><?php } ?></span>
                                    </div>
                                    <div class="crm-actividad-card-datos">
                                        <span class="crm-actividad-card-fecha"><i class="fa fa-calendar" aria-hidden="true"></i><span><strong>Fecha de la actividad</strong><span><?php echo fecha_mostrar(isset($item['fecha'])?$item['fecha']:''); ?></span></span></span>
                                        <?php $icono_tipo_act=isset($item['tipo']) && $item['tipo']==='capacitacion' ? 'fa-graduation-cap' : (isset($item['tipo']) && $item['tipo']==='reunion' ? 'fa-users' : (isset($item['tipo']) && $item['tipo']==='ronda' ? 'fa-briefcase' : 'fa-calendar-o')); ?>
                                        <span class="crm-actividad-card-dato"><i class="fa <?php echo $icono_tipo_act; ?>" aria-hidden="true"></i><?php echo texto_mostrar(isset($tipos_act[$item['tipo']])?$tipos_act[$item['tipo']]:$item['tipo']); ?></span>
                                        <?php if(intval($item['autoinscripcion'])===1) { ?><span class="crm-actividad-card-dato crm-actividad-card-dato--auto">Autoinscripción</span><?php } ?>
                                        <span class="crm-actividad-card-dato"><?php echo intval($item['n_inscriptos']); ?> participante(s)</span>
                                    </div>
                                </div>
                                <div class="text-right"><a href="<?php echo htmlspecialchars($crm_actividad_url_lista(array('id'=>intval($item['id']))), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-white btn-small">Ver</a><?php if($puede_item) { ?> <a href="<?php echo tareas_link('nueva-actividad.php',array('id'=>intval($item['id']))); ?>" class="btn btn-white btn-small"><i class="fa fa-pencil"></i> Editar</a><?php } ?></div>
                            </div>
                        </div><?php } ?>
                    </div>
                    <div class="col-md-4"><div class="grid simple"><div class="grid-title crm-act-cal-title"><h4><i class="fa fa-calendar text-info"></i>Calendario de actividades</h4><?php echo actividades_calendario_controles($cal_anio,$cal_mes); ?></div>
                        <div class="grid-body">
                            <style>
                            body.crm-actividades-page .crm-actividades-page-title{margin-bottom:6px}
                            body.crm-actividades-page .crm-actividades-filter-card{margin-bottom:14px}
                            body.crm-actividades-page .crm-actividades-filter-card .grid-body{padding:10px 14px!important}
                            body.crm-actividades-page .crm-actividades-filter-card .grid-body>p.muted{margin:8px 0 0!important}
                            body.crm-actividades-page .crm-actividades-layout>.col-md-8>.grid.simple,
                            body.crm-actividades-page .crm-actividades-layout>.col-md-4>.grid.simple{margin-bottom:14px}
                            @media(max-width:767px){body.crm-actividades-page .crm-actividades-filter-card{margin-bottom:12px}body.crm-actividades-page .crm-actividades-filter-card .grid-body{padding:10px 12px!important}body.crm-actividades-page .crm-actividades-layout>.col-md-8>.grid.simple,body.crm-actividades-page .crm-actividades-layout>.col-md-4>.grid.simple{margin-bottom:12px}}
                            .crm-actividades-page-title{display:flex;align-items:center;justify-content:space-between;gap:12px}.crm-actividades-page-title h3{margin:0}
                            .crm-actividad-card-meta{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:7px 0 10px;padding:10px 12px;border:1px solid #e9edf2;border-radius:6px;background:#f8fafc}.crm-actividad-card-persona{display:flex;flex-direction:column;gap:4px;min-width:0}.crm-actividad-card-responsable,.crm-actividad-card-area,.crm-actividad-card-organismo{display:inline-flex;align-items:center;gap:6px;min-width:0}.crm-actividad-card-responsable{font-weight:600;color:#334155}.crm-actividad-card-area{font-size:12px;color:#64748b}.crm-actividad-card-organismo{margin-left:8px}.crm-actividad-card-datos{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:6px;min-width:0}.crm-actividad-card-dato{display:inline-flex;align-items:center;gap:4px;padding:5px 8px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;color:#475569;font-size:11px;line-height:1.2;white-space:nowrap}.crm-actividad-card-dato strong{color:#334155}.crm-actividad-card-dato--auto{border-color:#bbf7d0;background:#f0fdf4;color:#15803d}
                            .crm-filtros-act{display:grid;gap:10px;width:100%;min-width:0;margin:0}.crm-filtros-act-row{display:grid;align-items:end;gap:10px;min-width:0}.crm-filtros-act-principal{grid-template-columns:minmax(200px,2.2fr) minmax(88px,.9fr) minmax(100px,1fr) minmax(125px,1.25fr) minmax(135px,1.35fr) auto}.crm-filtros-act-principal.crm-filtros-act-con-area{grid-template-columns:minmax(185px,2fr) minmax(82px,.85fr) minmax(95px,1fr) minmax(112px,1.2fr) minmax(95px,1fr) minmax(125px,1.3fr) auto}.crm-filtros-act .form-group{margin:0;min-width:0}.crm-filtros-act label{display:block;white-space:nowrap;margin:0 0 4px;font-size:11px;line-height:1.2;color:#64748b}.crm-filtros-act .form-control{height:32px;width:100%;max-width:none;min-width:0;padding:5px 7px;font-size:12px;box-sizing:border-box}.crm-act-limpiar{display:inline-flex;align-self:end;align-items:center;justify-content:center;box-sizing:border-box;height:36px;line-height:20px;padding:5px 9px;margin:0!important;white-space:nowrap}
                            @media(min-width:768px) and (max-width:1199px){.crm-filtros-act-principal,.crm-filtros-act-principal.crm-filtros-act-con-area{grid-template-columns:minmax(160px,1.6fr) repeat(3,minmax(90px,1fr))}.crm-filtros-act-principal .form-group:first-child{grid-column:span 2}}
                            .cal-compact-grid-mini{width:100%;border-collapse:collapse;table-layout:fixed}.cal-compact-grid-mini th{text-align:center;font-size:12px;color:#64748b;padding:8px 0;font-weight:700;text-transform:uppercase}.cal-compact-grid-mini td{text-align:center;padding:4px 0;height:40px;vertical-align:top;border-radius:6px;position:relative;transition:background-color .15s ease}.cal-compact-grid-mini td:hover{background-color:#f1f5f9}.cal-compact-grid-mini td a{display:flex;flex-direction:column;align-items:center;width:100%;color:inherit;text-decoration:none}.cal-mini-num{font-size:13px;color:#334155;font-weight:600;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%}.cal-mini-hoy .cal-mini-num{background-color:#0088cc;color:#fff;font-weight:700;box-shadow:0 2px 4px rgba(0,136,204,.25)}.cal-mini-sel{background-color:#d1fae5!important;border:1px solid #10b981!important}.cal-mini-dots{display:flex;justify-content:center;gap:3px;margin-top:2px;height:7px}.dot{width:6px;height:6px;border-radius:50%;display:inline-block}.dot-blue{background-color:#0088cc}.dot-green{background-color:#0aa699}.dot-gray{background-color:#94a3b8}.dot-red{background-color:#f35958}
.cal-legend{display:flex;justify-content:space-around;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;font-size:12px;color:#64748b;font-weight:500}.crm-act-cal-leyenda{gap:8px;flex-wrap:wrap;text-align:center}.crm-act-cal-leyenda span{white-space:nowrap}.cal-compact-grid-mini+.crm-act-cal-leyenda+p{text-align:center}.crm-act-cal-title{display:block;padding:14px 16px!important;border-bottom:1px solid #e5e9ec;background:#fafbfc}.crm-act-cal-title+.grid-body{padding:10px 15px!important}.crm-act-cal-title h4{margin:0 0 10px;font-size:14px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px}.crm-act-cal-controles{display:flex;align-items:center;gap:6px;width:100%;margin:0;white-space:nowrap;box-sizing:border-box}.crm-act-cal-selectores{display:flex;align-items:center;gap:6px;flex:1;min-width:0;margin:0}.crm-act-cal-controles .btn{height:32px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dce1e5;border-radius:4px;padding:0 9px;flex:0 0 auto}.crm-act-cal-controles>a:first-child,.crm-act-cal-controles>a:last-child{width:34px;padding:0}.crm-act-cal-controles>a:nth-last-child(2){min-width:75px;justify-content:center;font-size:12px;font-weight:600}.crm-act-cal-selectores select{height:32px;width:auto;max-width:none;padding:4px 8px;font-size:12px;font-weight:600;border:1px solid #dce1e5;border-radius:4px;color:#1e293b;background:#fff;cursor:pointer}.crm-act-cal-selectores select:first-of-type{flex:1;min-width:0}.crm-act-cal-selectores select:last-of-type{width:72px;flex:0 0 72px}
                            @media(max-width:767px){.crm-filtros-act-principal,.crm-filtros-act-principal.crm-filtros-act-con-area{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.crm-filtros-act-principal .form-group:first-child{grid-column:span 2}.crm-act-limpiar{justify-self:start}.crm-act-cal-title{padding:12px!important}.crm-act-cal-controles,.crm-act-cal-selectores{gap:4px}.crm-act-cal-controles>a:nth-last-child(2){min-width:70px}.crm-act-cal-selectores select:last-of-type{width:64px;flex-basis:64px}.crm-actividad-card-meta{align-items:flex-start;flex-direction:column;gap:9px}.crm-actividad-card-datos{justify-content:flex-start}}
                            @media(max-width:480px){.crm-filtros-act-principal,.crm-filtros-act-principal.crm-filtros-act-con-area{grid-template-columns:minmax(0,1fr)}.crm-filtros-act-principal .form-group:first-child{grid-column:auto}.crm-actividades-page-title{align-items:flex-start}}
                            .crm-actividad-card-datos{justify-content:flex-start;gap:7px;padding:7px 9px;border:1px solid #e3ebf7;border-radius:6px;background:#f5f8fd}
                            .crm-actividad-card-fecha{display:flex;align-items:center;gap:7px;padding:1px 10px 1px 1px;border-right:1px solid #d9e3f1;color:#334155;white-space:nowrap}
                            .crm-actividad-card-fecha>i{color:#4d7fca;font-size:14px}
                            .crm-actividad-card-fecha>span{display:flex;flex-direction:column;gap:1px;line-height:1.25}
                            .crm-actividad-card-fecha strong{font-size:10px;font-weight:600;color:#64748b}
                            .crm-actividad-card-fecha>span>span{font-size:13px;font-weight:600;color:#1e293b}
                            .crm-actividad-card-dato{border-radius:5px;padding:5px 8px}
                            @media(max-width:767px){.crm-actividad-card-datos{width:100%;box-sizing:border-box;flex-wrap:wrap}.crm-actividad-card-fecha{flex:1 1 100%;border-right:0;border-bottom:1px solid #d9e3f1;padding:1px 0 7px}}
                            .crm-calendar-controls{display:flex;align-items:center;gap:6px;width:100%;margin:0;white-space:nowrap;box-sizing:border-box;overflow-x:auto}.crm-calendar-selectors{display:flex;align-items:center;gap:6px;flex:0 0 auto;min-width:0;margin:0}.crm-calendar-controls .btn{height:32px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dce1e5;border-radius:4px;padding:0 9px;flex:0 0 auto}.crm-calendar-arrow{width:34px!important;padding:0!important}.crm-calendar-clear{min-width:75px;justify-content:center;font-size:12px;font-weight:600}.crm-calendar-selectors select{height:32px;width:auto;max-width:none;padding:4px 8px;font-size:12px;font-weight:600;border:1px solid #dce1e5;border-radius:4px;color:#1e293b;background:#fff;cursor:pointer}.crm-calendar-selectors select:first-of-type{width:130px;flex:0 0 130px}.crm-calendar-selectors select:last-of-type{width:84px;flex:0 0 84px}
                            </style>
                            <table class="cal-compact-grid-mini"><thead><tr><th>Lu</th><th>Ma</th><th>Mi</th><th>Ju</th><th>Vi</th><th>Sá</th><th>Do</th></tr></thead><tbody>
                            <?php $celda=1; $dia=1; echo '<tr>'; while($celda<$cal_primer_dia) { echo '<td class="cal-mini-empty"></td>'; $celda++; } while($dia<=$cal_dias_mes) { if($celda===1) echo '<tr>'; $fecha_celda=sprintf('%04d-%02d-%02d',$cal_anio,$cal_mes,$dia); $estados=isset($eventos_calendario[$dia])?$eventos_calendario[$dia]:array(); $clases=array('cal-mini-day'); if(count($estados)) $clases[]='cal-mini-has-events'; if($fecha_celda===$dia_cal) $clases[]='cal-mini-sel'; if($fecha_celda===$hoy) $clases[]='cal-mini-hoy'; $href=actividades_link_filtro(array('dia'=>$fecha_celda,'cal'=>sprintf('%04d-%02d',$cal_anio,$cal_mes))); $title=count($estados)?implode(', ',array_keys($estados)):'Sin actividades'; $dots=''; $mapa_dots=array('proxima'=>'blue','realizada'=>'green','pasada'=>'gray','cancelada'=>'red'); foreach($mapa_dots as $estado=>$color) { if(isset($estados[$estado])) $dots.='<span class="dot dot-'.$color.'"></span>'; } echo '<td class="'.implode(' ',$clases).'"><a href="'.htmlspecialchars($href,ENT_QUOTES,'UTF-8').'" title="'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'"><span class="cal-mini-num">'.$dia.'</span><div class="cal-mini-dots">'.$dots.'</div></a></td>'; if($celda===7) { echo '</tr>'; $celda=0; } $dia++; $celda++; } if($celda>1) { while($celda<=7) { echo '<td class="cal-mini-empty"></td>'; $celda++; } echo '</tr>'; } ?>
                            </tbody></table>
                            <div class="cal-legend crm-act-cal-leyenda"><span><span class="dot dot-blue"></span> Próxima</span><span><span class="dot dot-green"></span> Realizada</span><span><span class="dot dot-gray"></span> Pasada sin registro</span><span><span class="dot dot-red"></span> Cancelada</span></div>
                            <p class="muted" style="font-size:11px;margin:8px 0 0;">Una fecha pasada sin resultado no confirma realización.</p>
                        </div>
                    </div>
                    <div class="grid simple crm-historial-actividades">
                        <div class="grid-title"><h4>Historial de actividades</h4></div>
                        <div class="grid-body">
                            <?php
                            include_once BASE_PATH.'/includes/crm_historiales.php';
                            $etiquetas_historial=array('proxima'=>'Próxima','realizada'=>'Realizada','pasada'=>'Pasada · sin registro de realización','cancelada'=>'Cancelada');
                            $historial_actividades=array();
                            foreach($lista_total as $actividad_historial)
                            {
                                $fecha_creacion=isset($actividad_historial['creado_en']) ? $actividad_historial['creado_en'] : '';
                                $fecha_legible=crm_historial_fecha_legible($fecha_creacion);
                                if(intval($actividad_historial['id'])<=0 || $fecha_legible==='') { continue; }
                                $historial_actividades[intval($actividad_historial['id'])]=array('item'=>$actividad_historial,'fecha'=>$fecha_creacion,'fecha_legible'=>$fecha_legible);
                            }
                            usort($historial_actividades,function($a,$b){ $cmp=strcmp($b['fecha'],$a['fecha']); return $cmp!==0 ? $cmp : intval($b['item']['id'])-intval($a['item']['id']); });
                            $historial_actividades=array_slice($historial_actividades,0,10);
                            if(!$historial_actividades) { ?><p class="muted">No hay registros de creación de actividades disponibles.</p><?php }
                            foreach($historial_actividades as $registro_historial) {
                                $actividad_historial=$registro_historial['item'];
                                $estado_historial=isset($etiquetas_historial[$actividad_historial['estado']]) ? $etiquetas_historial[$actividad_historial['estado']] : 'Estado no disponible';
                                $tipo_historial=isset($tipos_act[$actividad_historial['tipo']]) ? $tipos_act[$actividad_historial['tipo']] : (trim((string)$actividad_historial['tipo'])!=='' ? $actividad_historial['tipo'] : 'Tipo no disponible');
                                $area_historial=trim((string)(isset($actividad_historial['area_nombre']) ? $actividad_historial['area_nombre'] : ''));
                                if($area_historial==='') { $area_historial=trim((string)(isset($actividad_historial['area_clave']) ? $actividad_historial['area_clave'] : '')); }
                                $fecha_actividad_historial=trim((string)(isset($actividad_historial['fecha']) ? fecha_mostrar($actividad_historial['fecha']) : ''));
                                $descripcion_historial=trim((string)(isset($actividad_historial['descripcion']) ? $actividad_historial['descripcion'] : ''));
                                $datos_historial=array(
                                    'kind'=>'activity',
                                    'heading'=>'Historial de actividades',
                                    'name'=>$actividad_historial['titulo'],
                                    'status'=>$estado_historial,
                                    'type'=>$tipo_historial,
                                    'action'=>'Actividad registrada',
                                    'movementDescription'=>'Registro de creación disponible. Los registros actuales no identifican cambios posteriores ni a su autor.',
                                    'date'=>$registro_historial['fecha_legible'],
                                    'area'=>$area_historial!=='' ? $area_historial : 'No disponible',
                                    'responsible'=>trim((string)(isset($actividad_historial['responsable']) ? $actividad_historial['responsable'] : '')) ?: 'Sin asignar',
                                    'activityDate'=>$fecha_actividad_historial!=='' ? $fecha_actividad_historial : 'No disponible',
                                    'description'=>$descripcion_historial!=='' ? $descripcion_historial : 'Sin descripción registrada.'
                                );
                            ?>
                            <button type="button" class="crm-historial-row" data-crm-historial="<?php echo htmlspecialchars(json_encode($datos_historial,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),ENT_QUOTES,'UTF-8'); ?>">
                                <strong><?php echo texto_mostrar($actividad_historial['titulo']); ?></strong>
                                <span class="crm-historial-meta">Actividad registrada · <?php echo texto_mostrar($registro_historial['fecha_legible']); ?></span>
                            </button>
                            <?php } ?>
                        </div>
                    </div>
                    <?php include BASE_PATH.'/includes/crm-historial-modal.php'; ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script>
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
<?php if($detalle && $puede_gestionar) { ?>
<script>
(function(){
	var url = <?php echo json_encode(url_crm('ajax/clientes-buscar.php'), JSON_UNESCAPED_UNICODE); ?>;
	var bus = document.getElementById('buscar_cliente');
	var caja = document.getElementById('resultados_cliente');
	var timer = null;
	function pintar(lista)
	{
		caja.innerHTML = '';
		if(!lista || !lista.length) { caja.style.display = 'none'; return; }
		caja.style.display = 'block';
		for(var i=0; i<lista.length; i++)
		{
			(function(cli){
				var f = document.createElement('div');
				f.style.padding = '8px 10px'; f.style.cursor = 'pointer'; f.style.borderBottom = '1px solid #eee';
				f.appendChild(document.createTextNode(cli.nombre + (cli.dni ? ' · DNI ' + cli.dni : (cli.cuil_cuit ? ' · ' + cli.cuil_cuit : ''))));
				f.onclick = function(){
					document.getElementById('cliente_id').value = cli.id;
					document.getElementById('cliente_nombre').value = cli.nombre;
					bus.value = ''; pintar([]);
				};
				caja.appendChild(f);
			})(lista[i]);
		}
	}
	bus.onkeyup = function(){
		var q = bus.value.replace(/^\s+|\s+$/g, '');
		if(timer) { clearTimeout(timer); }
		if(q.length < 2) { pintar([]); return; }
		timer = setTimeout(function(){
			var xhr = new XMLHttpRequest();
			xhr.open('GET', url + '?q=' + encodeURIComponent(q) + '&limit=15', true);
			xhr.onreadystatechange = function(){
				if(xhr.readyState !== 4) { return; }
				try { pintar((JSON.parse(xhr.responseText || '{}')).lista || []); } catch(e) { pintar([]); }
			};
			xhr.send();
		}, 280);
	};
})();
</script>
<?php } ?>
</body>
</html>
