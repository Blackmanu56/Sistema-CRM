<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('sistemas');
include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='Actividades';
$nombre_area='Sistemas';
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Nueva actividad</title>
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
                    <li><a href="<?php echo tareas_link('actividades.php'); ?>">Actividades</a></li>
                    <li><a href="#" class="active">Nueva actividad</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Nueva actividad</h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4>Las actividades se registran solas</h4>
                        </div>
                        <div class="grid-body no-border">
                            <p>Las actividades del área Sistemas ya no se cargan a mano: se registran automáticamente cuando se crea, se pone en proceso o se termina una tarea.</p>
                            <p>Para dejar constancia de una tarea, cargala desde <a href="<?php echo tareas_link('nueva-tarea.php'); ?>">Nueva tarea</a> y su historial queda en <a href="<?php echo tareas_link('actividades.php'); ?>">Actividades</a>.</p>
                            <p>
                                <a href="<?php echo tareas_link('actividades.php'); ?>" class="btn btn-primary">Volver a Actividades</a>
                            </p>
                        </div>
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
</body>
</html>