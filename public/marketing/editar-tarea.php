<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('marketing');

include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='tareas';

$id=isset($_GET['id']) ? intval($_GET['id']) : 0;
$tarea=tarea_por_id($id);

if($tarea==null || !tarea_puede_editar($tarea))
{
	if($tarea && (tarea_estado_finalizada($tarea) || tarea_padre_finalizada($tarea)) && tarea_puede_ver($tarea))
	{
		$_SESSION['msgtarea']='No se puede modificar una tarea finalizada. Un coordinador autorizado debe reabrirla primero.';
		header("Location: ".tareas_link('ver-tarea.php', array('id'=>$id)));
	}
	else
	{
		header("Location: ".tareas_link('tareas.php'));
	}
	exit();
}

if(isset($_POST['cargar_tipo']))
{
    $tarea=tareas_aplicar_carga_tipo($tarea);
}

// Se actualiza la tarea en el arreglo de la sesion. Cuando armes tu base, aca va el UPDATE.
if(isset($_POST['guardar']))
{
	tareas_actualizar_desde_form($id);
	header("Location: ".tareas_link('ver-tarea.php', array('id'=>$id)));
	exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Marketing - Editar tarea</title>
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
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>">Tareas</a></li>
                    <li><a href="#" class="active">Editar tarea</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Editar tarea</h3>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <form class="form-horizontal" method="post" action="">
                        <div class="panel panel-default">
                            <div class="panel-body">
                                <?php $modo='editar'; include BASE_PATH.'/includes/tareas-campos-form.php'; ?>
                            </div>
                            <div class="panel-footer">
                                <a href="<?php echo tareas_link('tareas.php'); ?>" class="btn btn-default">Cancelar</a>
                                <input type="submit" value="Guardar cambios" name="guardar" class="btn btn-primary pull-right">
                            </div>
                        </div>
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
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
<?php include BASE_PATH.'/includes/tareas-form-js.php'; ?>
</body>
</html>
