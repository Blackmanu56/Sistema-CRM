<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('vinculaciones');

include("datos-tareas.php");
include("datos-capacitaciones.php");
tareas_iniciar_contexto();
capacitaciones_iniciar();
$menu_activo='Capacitaciones';

if(isset($_POST['guardar']))
{
	capacitaciones_guardar_nueva();
	header("Location: ".tareas_link('capacitaciones.php'));
	exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Vinculaciones - Nueva capacitación</title>
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
                    <li><a href="<?php echo tareas_link('capacitaciones.php'); ?>">Capacitaciones</a></li>
                    <li><a href="#" class="active">Nueva capacitación</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Nueva capacitación</h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <form class="form-horizontal" method="post" action="">
                        <div class="panel panel-default">
                            <div class="panel-body">
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Título</label>
                                    <div class="col-md-6">
                                        <input type="text" name="titulo" required class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Descripción</label>
                                    <div class="col-md-6">
                                        <textarea name="descripcion" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Fecha</label>
                                    <div class="col-md-6">
                                        <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Responsable</label>
                                    <div class="col-md-6">
                                        <?php if(acceso_es_empleado()) { ?>
                                        <input type="hidden" name="responsable" value="<?php echo texto_mostrar(tareas_persona()); ?>">
                                        <input type="text" class="form-control" value="<?php echo texto_mostrar(tareas_persona()); ?>" readonly>
                                        <?php } else { ?>
                                        <select name="responsable" class="form-control">
                                            <?php foreach(acceso_nombres_equipo('vinculaciones') as $nom) { ?>
                                            <option value="<?php echo texto_mostrar($nom); ?>"><?php echo texto_mostrar($nom); ?></option>
                                            <?php } ?>
                                        </select>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-footer">
                                <a href="<?php echo tareas_link('capacitaciones.php'); ?>" class="btn btn-default">Cancelar</a>
                                <input type="submit" value="Guardar capacitación" name="guardar" class="btn btn-primary pull-right">
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
</body>
</html>
