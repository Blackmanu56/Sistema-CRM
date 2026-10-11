<?php
// Listado de actividades del area que esta abierta.
include_once BASE_PATH.'/includes/actividades.php';
$menu_activo='Actividades';
$area_act=acceso_area_pantalla();
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_act]) ? $etq[$area_act] : $area_act;

$ver_id=isset($_GET['id']) ? intval($_GET['id']) : 0;
if($_SERVER['REQUEST_METHOD']==='POST')
{
	$msg=null;
	$ir=tareas_link('actividades.php', $ver_id>0 ? array('id'=>$ver_id) : array());
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
		if($msg==='') { $msg='Actividad eliminada.'; $ir=tareas_link('actividades.php'); }
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
	if($detalle && (intval(isset($detalle['activo']) ? $detalle['activo'] : 1)!==1 || !actividad_puede_ver($detalle)))
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
$f_cuando=isset($_GET['cuando']) ? $_GET['cuando'] : '';
$f_desde=isset($_GET['desde']) ? $_GET['desde'] : '';
$f_hasta=isset($_GET['hasta']) ? $_GET['hasta'] : '';
$hay_filtro=($f_q!=='' || $f_tipo!=='' || $f_cuando!=='' || $f_desde!=='' || $f_hasta!=='');
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
<body class="">
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
                    <li><a href="<?php echo tareas_link('actividades.php'); ?>" class="<?php echo $detalle ? '' : 'active'; ?>">Actividades</a></li>
                    <?php if($detalle) { ?>
                    <li><a href="#" class="active"><?php echo texto_mostrar($detalle['titulo']); ?></a></li>
                    <?php } ?>
                </ul>
                <?php include("menu-seccion.php"); ?>
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
                        <a href="<?php echo tareas_link('actividades.php'); ?>" class="btn btn-white btn-small">Volver</a>
                    </div>
                    <div class="clearfix"></div>
                </div>
                <div class="grid simple">
                    <div class="grid-body">
                        <p><?php echo texto_mostrar(isset($detalle['descripcion']) ? $detalle['descripcion'] : ''); ?></p>
                        <p><strong>Fecha:</strong> <?php echo fecha_mostrar($detalle['fecha']); ?>
                        · <strong>Tipo:</strong> <?php echo texto_mostrar(function_exists('crm_portal_tipo_actividad') ? crm_portal_tipo_actividad($detalle['tipo']) : $detalle['tipo']); ?></p>
                        <p><strong>Responsable:</strong> <?php echo texto_mostrar(trim($detalle['responsable'])!='' ? $detalle['responsable'] : '—'); ?>
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
                <div class="page-title">
                    <h3>Actividades</h3>
                    <?php if(actividad_puede_crear()) { ?>
                    <a href="<?php echo tareas_link('nueva-actividad.php'); ?>" class="btn btn-primary btn-cons pull-right">Nueva actividad</a>
                    <?php } ?>
                </div>

                <div class="grid simple">
                    <div class="grid-body no-border" style="padding-top:16px;">
                        <form method="get" action="actividades.php" class="form-inline crm-filtros-act">
                            <div class="form-group">
                                <label>Buscar</label>
                                <input type="text" name="q" value="<?php echo texto_mostrar($f_q); ?>" class="form-control" placeholder="Título, organismo, responsable…" style="min-width:220px;">
                            </div>
                            <div class="form-group">
                                <label>Tipo</label>
                                <select name="tipo" class="form-control">
                                    <option value="">Todos</option>
                                    <?php foreach($tipos_act as $k=>$t) { ?>
                                    <option value="<?php echo $k; ?>" <?php if($f_tipo===$k) echo 'selected'; ?>><?php echo $t; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Cuándo</label>
                                <select name="cuando" class="form-control">
                                    <option value="">Todas</option>
                                    <option value="proximas" <?php if($f_cuando==='proximas') echo 'selected'; ?>>Próximas</option>
                                    <option value="pasadas" <?php if($f_cuando==='pasadas') echo 'selected'; ?>>Pasadas</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Desde</label>
                                <input type="date" name="desde" value="<?php echo texto_mostrar($f_desde); ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Hasta</label>
                                <input type="date" name="hasta" value="<?php echo texto_mostrar($f_hasta); ?>" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <?php if($hay_filtro) { ?><a href="actividades.php" class="btn btn-white">Limpiar</a><?php } ?>
                        </form>
                        <p class="muted" style="margin:10px 0 0;">Mostrando <?php echo count($lista); ?> de <?php echo count($lista_total); ?> actividades.</p>
                    </div>
                </div>

                <?php if(count($lista)==0){ ?>
                <div class="grid simple">
                    <div class="grid-body">
                        <p><?php echo $hay_filtro ? 'No hay actividades con esos filtros.' : 'Todavía no hay actividades cargadas.'; ?></p>
                    </div>
                </div>
                <?php } ?>

                <?php foreach($lista as $item){
                	$puede_item=actividad_puede_gestionar($item);
                	$pasada=(substr((string)$item['fecha'],0,10)<$hoy);
                ?>
                <div class="grid simple">
                    <div class="grid-title">
                        <h4><a href="<?php echo tareas_link('actividades.php', array('id'=>intval($item['id']))); ?>"><?php echo texto_mostrar($item['titulo']); ?></a></h4>
                        <div class="tools">
                            <?php if($pasada) { ?><span class="label">Pasada</span><?php } else { ?><span class="label label-info">Próxima</span><?php } ?>
                        </div>
                    </div>
                    <div class="grid-body">
                        <p><?php echo texto_mostrar(isset($item['descripcion']) ? $item['descripcion'] : ''); ?></p>
                        <p><i class="fa fa-user"></i> <?php echo texto_mostrar(isset($item['responsable']) ? $item['responsable'] : ''); ?>
                        <?php if($item['organismo']!='') { ?> · <i class="fa fa-institution"></i> <?php echo texto_mostrar($item['organismo']); ?><?php } ?></p>
                        <p><strong>Fecha:</strong> <?php echo fecha_mostrar(isset($item['fecha']) ? $item['fecha'] : ''); ?>
                        · <?php echo texto_mostrar(isset($tipos_act[$item['tipo']]) ? $tipos_act[$item['tipo']] : $item['tipo']); ?>
                        <?php if(intval($item['autoinscripcion'])==1) { ?>
                        · <span class="label label-success">Autoinscripción</span>
                        <?php } ?>
                        · <?php echo intval($item['n_inscriptos']); ?> participante(s)
                        </p>
                        <div class="text-right">
                            <a href="<?php echo tareas_link('actividades.php', array('id'=>intval($item['id']))); ?>" class="btn btn-white btn-small">Ver</a>
                            <?php if($puede_item) { ?>
                            <a href="<?php echo tareas_link('nueva-actividad.php', array('id'=>intval($item['id']))); ?>" class="btn btn-white btn-small"><i class="fa fa-pencil"></i> Editar</a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
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
