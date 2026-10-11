<?php
// Pantalla de una atención. No se mezcla con la ficha del cliente.
$area_cli=acceso_area_pantalla();
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_cli]) ? $etq[$area_cli] : $area_cli;
$menu_activo='atenciones';

$id=0;
if(isset($_POST['id_atencion']))
{
	$id=intval($_POST['id_atencion']);
}
if($id<=0 && isset($_GET['id']))
{
	$id=intval($_GET['id']);
}
if($id<=0)
{
	header('Location: atenciones.php');
	exit();
}

if(function_exists('crm_notificacion_marcar_vista_por_entidad'))
{
	crm_notificacion_marcar_vista_por_entidad('turno', $id);
}

if(isset($_POST['aceptar_solicitud']))
{
	$msg=crm_atencion_aceptar_solicitud(
		$id,
		isset($_POST['fecha']) ? $_POST['fecha'] : '',
		isset($_POST['hora']) ? $_POST['hora'] : ''
	);
	$_SESSION['msgatencion']=($msg=='') ? 'Solicitud aceptada y atención agendada.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['reabrir_atencion']))
{
	$msg=crm_atencion_reabrir($id);
	$_SESSION['msgatencion']=($msg=='') ? 'La atención quedó reabierta.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['iniciar_atencion']))
{
	$msg=crm_atencion_iniciar_atencion($id);
	$_SESSION['msgatencion']=($msg=='') ? 'Cliente presente: la atención está en curso. El área recibió aviso.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['cancelar_atencion']))
{
	$msg=crm_atencion_cancelar($id, isset($_POST['motivo_cancel']) ? $_POST['motivo_cancel'] : '');
	$_SESSION['msgatencion']=($msg=='') ? 'Atención cancelada.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['reprogramar_atencion']))
{
	$msg=crm_atencion_reprogramar($id, isset($_POST['fecha']) ? $_POST['fecha'] : '', isset($_POST['hora']) ? $_POST['hora'] : '');
	$_SESSION['msgatencion']=($msg=='') ? 'Atención reprogramada.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['agendar_otro_turno']))
{
	$at_tmp=crm_atencion_traer($id);
	$msg='No se pudo agendar la nueva atención.';
	if($at_tmp && crm_atencion_puede_operar($at_tmp))
	{
		$fecha_n=isset($_POST['fecha_nuevo']) ? trim($_POST['fecha_nuevo']) : '';
		$hora_n=isset($_POST['hora_nuevo']) ? trim($_POST['hora_nuevo']) : '';
		$err_v=function_exists('crm_turno_validar_alta') ? crm_turno_validar_alta($at_tmp['area_clave'], $fecha_n, $hora_n, 0) : '';
		if($err_v!='')
		{
			$msg=$err_v;
		}
		else
		{
			$id_nuevo=crm_atencion_abrir(array(
				'id_cliente'=>intval($at_tmp['id_cliente']),
				'area'=>$at_tmp['area_clave'],
				'motivo'=>isset($at_tmp['motivo']) ? $at_tmp['motivo'] : 'seguimiento',
				'canal'=>isset($at_tmp['canal']) ? $at_tmp['canal'] : 'presencial',
				'detalle'=>'Agendada desde atención #'.$id,
				'fecha'=>$fecha_n,
				'hora'=>$hora_n,
				'id_origen'=>$id
			));
			if($id_nuevo>0)
			{
				$msg='';
				$_SESSION['msgatencion']='Nueva atención #'.$id_nuevo.' agendada.';
				echo "<script>window.location.href='atencion.php?id=".$id_nuevo."'</script>";
				exit();
			}
		}
	}
	$_SESSION['msgatencion']=$msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['cerrar_atencion']))
{
	$msg=crm_atencion_cerrar($id, isset($_POST['cierre_detalle']) ? $_POST['cierre_detalle'] : '');
	$_SESSION['msgatencion']=($msg=='') ? 'Atención cerrada.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['derivar_atencion']))
{
	$id_nuevo=0;
	$msg=crm_atencion_derivar($id, isset($_POST['area_destino']) ? $_POST['area_destino'] : '', isset($_POST['cierre_detalle']) ? $_POST['cierre_detalle'] : '', $id_nuevo);
	if($msg=='')
	{
		$_SESSION['msgatencion']='Atención derivada. El área destino recibió la notificación'.($id_nuevo>0 ? ' (atención #'.$id_nuevo.')' : '').'.';
	}
	else
	{
		$_SESSION['msgatencion']=$msg;
	}
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['anotar_programa']) && function_exists('crm_programa_aplicar_cliente') && function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa())
{
	$at_tmp=crm_atencion_traer($id);
	$id_cli_tmp=$at_tmp ? intval($at_tmp['id_cliente']) : 0;
	$msg=crm_programa_aplicar_cliente($id_cli_tmp, isset($_POST['id_programa']) ? $_POST['id_programa'] : 0);
	$_SESSION['msgatencion']=($msg=='') ? 'Cliente anotado en el programa. Se generaron las tareas.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}

if(isset($_POST['vincular_tarea']))
{
	$at_tmp=crm_atencion_traer($id);
	$msg='No se pudo vincular la tarea.';
	if($at_tmp && crm_atencion_puede_operar($at_tmp))
	{
		$id_t=isset($_POST['id_tarea_vincular']) ? intval($_POST['id_tarea_vincular']) : 0;
		$ok=acceso_ejecutar(
			"UPDATE tarea SET id_atencion=? WHERE id_tarea=? AND id_cliente=? AND estado<>'cancelada'",
			'iii',
			array($id, $id_t, intval($at_tmp['id_cliente']))
		);
		if($ok) { $msg=''; }
	}
	$_SESSION['msgatencion']=($msg=='') ? 'Tarea vinculada a la atención.' : $msg;
	echo "<script>window.location.href='atencion.php?id=".$id."'</script>";
	exit();
}

$at=crm_atencion_traer($id);
if(!$at)
{
	header('Location: atenciones.php');
	exit();
}
$id_cliente=intval($at['id_cliente']);
if(!crm_cliente_puede_ver($id_cliente, $area_cli) && !crm_puede_gestionar_atencion($at))
{
	header('Location: atenciones.php');
	exit();
}

$puede=crm_atencion_puede_operar($at);
$pendiente=(isset($at['estado']) && $at['estado']=='pendiente');
$espera=(isset($at['estado']) && $at['estado']=='abierta');
$atendiendo=(isset($at['estado']) && $at['estado']=='en_atencion');
$abierta=($espera || $atendiendo || $pendiente);
$cancelado=(isset($at['estado']) && $at['estado']=='cancelada');
$derivado=(isset($at['estado']) && $at['estado']=='derivada');
$siguiente_der=($derivado && function_exists('crm_atencion_derivada_siguiente')) ? crm_atencion_derivada_siguiente($id) : null;
$ft_act=isset($at['fecha_turno']) && $at['fecha_turno']!='' ? $at['fecha_turno'] : $at['fecha'];
$fecha_act=substr($ft_act, 0, 10);
$hora_act=(strlen($ft_act)>=16) ? substr($ft_act, 11, 5) : '';
$horas_rep=crm_turno_horarios_disponibles($at['area_clave'], $fecha_act!='' ? $fecha_act : date('Y-m-d'), $id);
if($hora_act!='' && !in_array($hora_act, $horas_rep, true))
{
	array_unshift($horas_rep, $hora_act);
}
$areas_derivar=function_exists('recepcion_areas_cliente') ? recepcion_areas_cliente() : array();
$tareas_at=crm_tareas_de_atencion($id);
$tareas_para_vincular=acceso_consulta(
	"SELECT t.id_tarea, t.titulo, a.nombre AS area_nombre FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
	WHERE t.id_cliente=? AND (t.id_atencion IS NULL OR t.id_atencion<>?) AND t.estado NOT IN ('cancelada','terminada')
	ORDER BY t.id_tarea DESC LIMIT 50",
	'ii',
	array($id_cliente, $id)
);
$cli=cliente_traer($id_cliente);
$nombre_cli=$cli ? (isset($cli['name']) ? $cli['name'] : $at['cliente_nombre']) : $at['cliente_nombre'];
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Atención</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
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
                    <li><p>ESTÁS AQUÍ</p></li>
                    <li><a href="atenciones.php"><?php echo texto_mostrar($nombre_area); ?> / Atenciones</a></li>
                    <li><a href="#" class="active">Atención #<?php echo intval($id); ?></a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Atención #<?php echo intval($id); ?> · <?php echo texto_mostrar(crm_estado_texto($at['estado'])); ?></h3>
                </div>
                <?php if(isset($_SESSION['msgatencion']) && $_SESSION['msgatencion']!='') { ?>
                <div class="alert alert-info"><?php echo texto_mostrar($_SESSION['msgatencion']); $_SESSION['msgatencion']=''; ?></div>
                <?php } ?>

                <div class="row">
                    <div class="col-md-7">
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Atención</h4></div>
                            <div class="grid-body no-border">
                                <p><strong>Cliente:</strong> <?php echo texto_mostrar($nombre_cli); ?>
                                    · <a href="editar-cliente.php?id=<?php echo $id_cliente; ?>">Ver ficha</a></p>
                                <p><strong>Estado:</strong> <span class="label <?php
                                	$est_lab='label-info';
                                	if($at['estado']=='cerrada') { $est_lab='label-success'; }
                                	else if($at['estado']=='cancelada') { $est_lab='label-important'; }
                                	else if($at['estado']=='en_atencion') { $est_lab='label-warning'; }
                                	else if($at['estado']=='pendiente') { $est_lab='label-inverse'; }
                                	else if($at['estado']=='derivada') { $est_lab='label-inverse'; }
                                	echo $est_lab;
                                ?>"><?php echo texto_mostrar(crm_estado_texto($at['estado'])); ?></span></p>
                                <p><strong>Área:</strong> <?php echo texto_mostrar($at['area_nombre']); ?></p>
                                <p><strong>Motivo:</strong> <?php echo texto_mostrar(crm_motivo_texto($at['motivo'])); ?>
                                    · <strong>Canal:</strong> <?php echo texto_mostrar($at['canal']); ?></p>
                                <p><strong>Fecha del turno:</strong> <?php
                                    $ft=isset($at['fecha_turno']) && $at['fecha_turno']!='' ? $at['fecha_turno'] : $at['fecha'];
                                    echo texto_mostrar(crm_turno_fecha_texto($ft));
                                ?></p>
                                <p><strong>Nota:</strong><br><?php
                                    $nota=isset($at['detalle']) ? trim($at['detalle']) : '';
                                    echo $nota!='' ? nl2br(texto_mostrar($nota)) : '<span class="muted">Sin nota</span>';
                                ?></p>
                            </div>
                        </div>

                        <?php if(!$puede && !$derivado && !$cancelado) { ?>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> Turno derivado al área <strong><?php echo texto_mostrar($at['area_nombre']); ?></strong>. Modo consulta: la atención y gestión del turno corresponde al área de destino.
                        </div>
                        <?php } ?>

                        <?php if($puede && $pendiente) { ?>
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Solicitud web pendiente</h4></div>
                            <div class="grid-body no-border">
                                <p>El cliente pidió una atención. Confirmá fecha y hora para dar turno (presencial).</p>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Fecha y hora del turno</label>
                                        <input type="date" name="fecha" min="<?php echo date('Y-m-d'); ?>" class="form-control crm-turno-fecha" data-hora-target="#hora_solicitud" data-excepto="<?php echo intval($id); ?>" value="<?php echo texto_mostrar($fecha_act!='' ? $fecha_act : date('Y-m-d')); ?>" required style="max-width:180px; display:inline-block;">
                                        <select name="hora" id="hora_solicitud" class="form-control" required style="max-width:120px; display:inline-block;">
                                            <?php foreach($horas_rep as $h) { ?>
                                            <option value="<?php echo texto_mostrar($h); ?>" <?php if($h===$hora_act) echo 'selected'; ?>><?php echo texto_mostrar($h); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="aceptar_solicitud" value="1" class="btn btn-primary">Aceptar y agendar</button>
                                </form>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Rechazar / cancelar solicitud</label>
                                        <input type="text" name="motivo_cancel" class="form-control" placeholder="Motivo (opcional)">
                                    </div>
                                    <button type="submit" name="cancelar_atencion" value="1" class="btn btn-danger">Cancelar solicitud</button>
                                </form>
                            </div>
                        </div>
                        <?php } else if($puede && $espera) { ?>
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>A la espera del cliente</h4></div>
                            <div class="grid-body no-border">
                                <p>Cuando llegue, confirmá la asistencia para dar curso al área.</p>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <button type="submit" name="iniciar_atencion" value="1" class="btn btn-success">Cliente presente · Atender</button>
                                </form>
                                <hr>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Reprogramar</label>
                                        <input type="date" name="fecha" min="<?php echo date('Y-m-d'); ?>" class="form-control crm-turno-fecha" data-hora-target="#hora_reprogramar" data-excepto="<?php echo intval($id); ?>" value="<?php echo texto_mostrar($fecha_act); ?>" required style="max-width:180px; display:inline-block;">
                                        <select name="hora" id="hora_reprogramar" class="form-control" required style="max-width:120px; display:inline-block;">
                                            <?php foreach($horas_rep as $h) { ?>
                                            <option value="<?php echo texto_mostrar($h); ?>" <?php if($h===$hora_act) echo 'selected'; ?>><?php echo texto_mostrar($h); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="reprogramar_atencion" value="1" class="btn btn-white">Reprogramar</button>
                                </form>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Derivar a otra área</label>
                                        <input type="text" name="cierre_detalle" class="form-control" placeholder="Observación al derivar (opcional)" style="margin-bottom:8px;">
                                        <select name="area_destino" class="form-control" required>
                                            <option value="">Elegir área</option>
                                            <?php foreach($areas_derivar as $clave=>$titulo) {
                                                if($clave==$at['area_clave']) { continue; } ?>
                                            <option value="<?php echo texto_mostrar($clave); ?>"><?php echo texto_mostrar($titulo); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="derivar_atencion" value="1" class="btn btn-white">Derivar</button>
                                </form>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Cancelar atención</label>
                                        <input type="text" name="motivo_cancel" class="form-control" placeholder="Motivo (opcional)">
                                    </div>
                                    <button type="submit" name="cancelar_atencion" value="1" class="btn btn-danger">Cancelar atención</button>
                                </form>
                            </div>
                        </div>
                        <?php } else if($puede && $atendiendo) { ?>
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Siendo atendido</h4></div>
                            <div class="grid-body no-border">
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Nota al cerrar (opcional)</label>
                                        <input type="text" name="cierre_detalle" class="form-control" placeholder="Observación">
                                    </div>
                                    <button type="submit" name="cerrar_atencion" value="1" class="btn btn-primary">Cerrar atención</button>
                                    <p class="muted" style="margin-top:8px;">Si el cliente es empresa, municipalidad u organismo, las atenciones abiertas futuras del mismo cliente se cancelan automáticamente con un aviso.</p>
                                </form>
                                <hr>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Reagendar esta atención</label>
                                        <input type="date" name="fecha" min="<?php echo date('Y-m-d'); ?>" class="form-control crm-turno-fecha" data-hora-target="#hora_reagendar" data-excepto="<?php echo intval($id); ?>" value="<?php echo texto_mostrar($fecha_act); ?>" required style="max-width:180px; display:inline-block;">
                                        <select name="hora" id="hora_reagendar" class="form-control" required style="max-width:120px; display:inline-block;">
                                            <?php foreach($horas_rep as $h) { ?>
                                            <option value="<?php echo texto_mostrar($h); ?>" <?php if($h===$hora_act) echo 'selected'; ?>><?php echo texto_mostrar($h); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="reprogramar_atencion" value="1" class="btn btn-white">Reagendar</button>
                                    <p class="muted" style="margin:6px 0 0;">Mueve esta atención a otra fecha/hora y vuelve a dejarla a la espera.</p>
                                </form>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                         <label>Dar otro turno (mismo cliente, esta área)</label>
                                        <input type="date" name="fecha_nuevo" min="<?php echo date('Y-m-d'); ?>" class="form-control crm-turno-fecha" data-hora-target="#hora_nuevo" data-excepto="0" value="<?php echo texto_mostrar(date('Y-m-d')); ?>" required style="max-width:180px; display:inline-block;">
                                        <select name="hora_nuevo" id="hora_nuevo" class="form-control" required style="max-width:120px; display:inline-block;">
                                            <?php
                                            $horas_nuevo=crm_turno_horarios_disponibles($at['area_clave'], date('Y-m-d'), 0);
                                            if(count($horas_nuevo)==0) { $horas_nuevo=$horas_rep; }
                                            foreach($horas_nuevo as $h) { ?>
                                            <option value="<?php echo texto_mostrar($h); ?>"><?php echo texto_mostrar($h); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="agendar_otro_turno" value="1" class="btn btn-white">Dar nuevo turno</button>
                                    <p class="muted" style="margin:6px 0 0;">Deja esta atención como está y crea un nuevo turno para más adelante.</p>
                                </form>
                                <hr>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" style="margin-bottom:14px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <div class="form-group">
                                        <label>Derivar a otra área</label>
                                        <input type="text" name="cierre_detalle" class="form-control" placeholder="Observación al derivar (opcional)" style="margin-bottom:8px;">
                                        <select name="area_destino" class="form-control" required>
                                            <option value="">Elegir área</option>
                                            <?php foreach($areas_derivar as $clave=>$titulo) {
                                                if($clave==$at['area_clave']) { continue; } ?>
                                            <option value="<?php echo texto_mostrar($clave); ?>"><?php echo texto_mostrar($titulo); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="derivar_atencion" value="1" class="btn btn-white">Derivar</button>
                                </form>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <input type="text" name="motivo_cancel" class="form-control" placeholder="Motivo cancelación (opcional)" style="margin-bottom:8px;">
                                    <button type="submit" name="cancelar_atencion" value="1" class="btn btn-danger">Cancelar atención</button>
                                </form>
                            </div>
                        </div>
                        <?php } else if($derivado) { ?>
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Atención derivada</h4></div>
                            <div class="grid-body no-border">
                                <p>Esta atención se derivó a otra área. Queda en el historial de <?php echo texto_mostrar($at['area_nombre']); ?>.</p>
                                <?php if($siguiente_der) { ?>
                                <p>Continúa en <strong><?php echo texto_mostrar($siguiente_der['area_nombre']); ?></strong>
                                    · <a href="<?php echo texto_mostrar(crm_atencion_url(intval($siguiente_der['id_atencion']), $siguiente_der['area_clave'])); ?>">Abrir atención #<?php echo intval($siguiente_der['id_atencion']); ?></a></p>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } else if($puede && !$abierta && !$cancelado) { ?>
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Atención cerrada</h4></div>
                            <div class="grid-body no-border">
                                <p>Esta atención ya está cerrada. Si se cerró por error, se puede reabrir.</p>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <button type="submit" name="reabrir_atencion" value="1" class="btn btn-white">Reabrir atención</button>
                                </form>
                            </div>
                        </div>
                        <?php } else if($cancelado) { ?>
                        <div class="grid simple">
                            <div class="grid-body no-border"><p class="muted">Atención cancelada.</p></div>
                        </div>
                        <?php } ?>
                    </div>
                    <div class="col-md-5">
                        <?php
                        $progs_cli=function_exists('crm_programas_de_cliente') ? crm_programas_de_cliente($id_cliente) : array();
                        $puede_prog=function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa();
                        if($puede_prog || count($progs_cli)>0) { ?>
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Programa</h4></div>
                            <div class="grid-body no-border">
                                <?php if(count($progs_cli)>0) {
                                	foreach($progs_cli as $pc) { ?>
                                <p><?php echo texto_mostrar($pc['nombre']); ?></p>
                                <?php } } else { ?>
                                <p class="muted">El cliente no está en un programa.</p>
                                <?php } ?>
                                <?php if($puede_prog && $puede && $atendiendo && function_exists('crm_programas_lista')) { ?>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <select name="id_programa" class="form-control" required>
                                        <option value="">Anotar en programa…</option>
                                        <?php foreach(crm_programas_lista() as $pr) { ?>
                                        <option value="<?php echo intval($pr['id_programa']); ?>"><?php echo texto_mostrar($pr['nombre']); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p style="margin-top:8px;"><button type="submit" name="anotar_programa" value="1" class="btn btn-white">Anotar y generar tareas</button></p>
                                </form>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Tareas de esta atención</h4></div>
                            <div class="grid-body no-border">
                                <?php if(count($tareas_at)==0) { ?>
                                <p class="muted">No hay tareas vinculadas.</p>
                                <?php } else { foreach($tareas_at as $ta) { ?>
                                <p><a href="<?php echo url_crm($ta['area_clave'].'/ver-tarea.php?id='.intval($ta['id_tarea'])); ?>"><?php echo texto_mostrar($ta['titulo']); ?></a>
                                    · <?php echo texto_mostrar($ta['estado']); ?></p>
                                <?php } } ?>
                                <?php if($puede && !$cancelado) { ?>
                                <p style="margin-top:10px;">
                                    <a class="btn btn-primary" href="nueva-tarea.php?cliente_id=<?php echo $id_cliente; ?>&amp;cliente_nombre=<?php echo urlencode($nombre_cli); ?>&amp;id_atencion=<?php echo intval($id); ?>"><i class="fa fa-plus"></i> Crear tarea desde esta atención</a>
                                </p>
                                <?php if(count($tareas_para_vincular)>0) { ?>
                                <form method="post" action="atencion.php?id=<?php echo intval($id); ?>" class="form-inline" style="margin-top:8px;">
                                    <input type="hidden" name="id_atencion" value="<?php echo intval($id); ?>">
                                    <select name="id_tarea_vincular" class="form-control" required style="max-width:100%; margin-bottom:6px;">
                                        <option value="">Vincular una tarea existente del cliente…</option>
                                        <?php foreach($tareas_para_vincular as $tv) { ?>
                                        <option value="<?php echo intval($tv['id_tarea']); ?>"><?php echo texto_mostrar($tv['titulo'].' · '.$tv['area_nombre']); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" name="vincular_tarea" value="1" class="btn btn-white">Vincular</button>
                                </form>
                                <?php } ?>
                                <?php } ?>
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="atenciones.php" class="btn btn-white">Volver a atenciones</a>
                            <a href="editar-cliente.php?id=<?php echo $id_cliente; ?>" class="btn btn-white">Ficha del cliente</a>
                        </div>
                    </div>
                </div>
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
<script type="text/javascript">
(function() {
    var areaClave = <?php echo json_encode($at['area_clave']); ?>;
    var ajaxUrl = <?php echo json_encode(url_crm('ajax/turnos-horarios.php')); ?>;

    function cargarHorarios(input) {
        var targetSelector = input.getAttribute('data-hora-target');
        var selectHora = targetSelector ? document.querySelector(targetSelector) : null;
        if (!selectHora) return;

        var fecha = input.value;
        var excepto = input.getAttribute('data-excepto') || '0';
        if (!fecha) return;

        var valorPrevio = selectHora.value;
        selectHora.innerHTML = '<option value="">Cargando horarios...</option>';

        var xhr = new XMLHttpRequest();
        xhr.open('GET', ajaxUrl + '?area=' + encodeURIComponent(areaClave) + '&fecha=' + encodeURIComponent(fecha) + '&excepto=' + encodeURIComponent(excepto), true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            selectHora.innerHTML = '';
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp && resp.ok && resp.horarios && resp.horarios.length > 0) {
                        for (var i = 0; i < resp.horarios.length; i++) {
                            var h = resp.horarios[i];
                            var opt = document.createElement('option');
                            opt.value = h;
                            opt.textContent = h;
                            if (h === valorPrevio) {
                                opt.selected = true;
                            }
                            selectHora.appendChild(opt);
                        }
                        if (!selectHora.value && selectHora.options.length > 0) {
                            selectHora.selectedIndex = 0;
                        }
                        return;
                    }
                } catch(e) {}
            }
            var optVacia = document.createElement('option');
            optVacia.value = '';
            optVacia.textContent = 'Sin horarios disponibles';
            selectHora.appendChild(optVacia);
        };
        xhr.send();
    }

    var inputs = document.querySelectorAll('.crm-turno-fecha');
    for (var i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('change', function() {
            cargarHorarios(this);
        });
    }
})();
</script>
</body>
</html>
