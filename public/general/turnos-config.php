<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('general', true);
if(!acceso_es_coord_general() && !acceso_es_super())
{
	header('Location: '.acceso_destino_login());
	exit();
}
include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='turnos_config';
if(isset($_POST['guardar_cupo']))
{
	$msg=crm_turno_guardar_config_form();
	$_SESSION['msgturno']=($msg=='') ? 'Configuración de turnos actualizada.' : $msg;
	header('Location: turnos-config.php');
	exit();
}
$cupo=crm_turno_cupo_diario();
$desde=crm_turno_config('hora_desde','07:00');
$hasta=crm_turno_config('hora_hasta','19:30');

$f_desde=isset($_GET['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde']) ? $_GET['desde'] : date('Y-m-d');
$f_hasta=isset($_GET['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d', strtotime('+30 days'));
if($f_hasta<$f_desde)
{
	$f_hasta=$f_desde;
}
$turnos=crm_turnos_asignados($f_desde, $f_hasta);
$estados_txt=array('abierta'=>'Asignado','en_atencion'=>'En atención','derivada'=>'Derivado');
$dias_semana=array('Dom','Lun','Mar','Mié','Jue','Vie','Sáb');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Turnos asignados</title>
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
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>">Coordinación general</a></li>
                    <li><a href="#" class="active">Turnos asignados</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title"><h3>Turnos asignados</h3></div>
                <?php if(isset($_SESSION['msgturno']) && $_SESSION['msgturno']!='') { ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgturno']); $_SESSION['msgturno']=''; ?></div>
                <?php } ?>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Horarios ocupados</h4></div>
                    <div class="grid-body no-border">
                        <p class="muted">Solo se listan los turnos ya dados. Esos horarios no están disponibles en su área; cualquier otro horario del día está libre.</p>
                        <form method="get" class="form-inline" style="margin-bottom:14px;">
                            <label>Desde</label>
                            <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($f_desde); ?>" style="margin:0 10px 0 6px;">
                            <label>Hasta</label>
                            <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($f_hasta); ?>" style="margin:0 10px 0 6px;">
                            <button type="submit" class="btn btn-primary">Ver</button>
                        </form>
                        <?php if(count($turnos)==0) { ?>
                        <p class="muted">No hay turnos asignados en ese período.</p>
                        <?php } else { ?>
                        <table class="table table-striped table-condensed">
                            <thead>
                                <tr><th>Fecha</th><th>Hora</th><th>Área</th><th>Cliente</th><th>Estado</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach($turnos as $t) {
                                $ts=strtotime($t['fecha_turno']);
                            ?>
                                <tr>
                                    <td><?php echo $dias_semana[intval(date('w', $ts))].' '.date('d/m/Y', $ts); ?></td>
                                    <td><strong><?php echo date('H:i', $ts); ?></strong></td>
                                    <td><?php echo htmlspecialchars($t['area_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($t['cliente_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars(isset($estados_txt[$t['estado']]) ? $estados_txt[$t['estado']] : $t['estado']); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Configuración</h4></div>
                    <div class="grid-body no-border">
                        <p class="muted">Tope de turnos por área y día, y rango horario en el que se pueden dar (cada media hora).</p>
                        <form method="post" action="" class="form-inline">
                            <label>Turnos por día</label>
                            <input type="number" name="turnos_por_dia" class="form-control" min="1" max="48" value="<?php echo intval($cupo); ?>" required style="width:90px; margin:0 14px 0 6px;">
                            <?php
                            $base=array();
                            for($h=6;$h<=21;$h++){ foreach(array(0,30) as $m){ $base[]=sprintf('%02d:%02d',$h,$m);} }
                            ?>
                            <label>Desde</label>
                            <select name="hora_desde" class="form-control" style="margin:0 14px 0 6px;">
                                <?php foreach($base as $h) { ?>
                                <option value="<?php echo $h; ?>" <?php if($desde===$h) echo 'selected'; ?>><?php echo $h; ?></option>
                                <?php } ?>
                            </select>
                            <label>Hasta</label>
                            <select name="hora_hasta" class="form-control" style="margin:0 14px 0 6px;">
                                <?php foreach($base as $h) { ?>
                                <option value="<?php echo $h; ?>" <?php if($hasta===$h) echo 'selected'; ?>><?php echo $h; ?></option>
                                <?php } ?>
                            </select>
                            <button type="submit" name="guardar_cupo" value="1" class="btn btn-white">Guardar</button>
                        </form>
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
</body>
</html>
