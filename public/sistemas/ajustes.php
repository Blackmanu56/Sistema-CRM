<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');

include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='ajustes';

if(!isset($_SESSION['sis_ajustes']) || !is_array($_SESSION['sis_ajustes']))
{
	$_SESSION['sis_ajustes']=array(
		'nombre_sistema'=>'CRM',
		'correo_aviso'=>'',
		'registro_publico'=>'si',
		'mensaje_login'=>''
	);
}

if(isset($_POST['guardar_ajustes']))
{
	$_SESSION['sis_ajustes']=array(
		'nombre_sistema'=>trim($_POST['nombre_sistema']),
		'correo_aviso'=>trim($_POST['correo_aviso']),
		'registro_publico'=>($_POST['registro_publico']=='no' ? 'no' : 'si'),
		'mensaje_login'=>trim($_POST['mensaje_login'])
	);
	$_SESSION['msgsis']="Ajustes guardados.";
	echo "<script>window.location.href='ajustes.php'</script>";
	exit();
}

$ajustes=$_SESSION['sis_ajustes'];
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Sistemas - Ajustes</title>
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
                    <li><a href="<?php echo url_crm('sistemas/tareas.php'); ?>">Sistemas</a></li>
                    <li><a href="#" class="active">Ajustes</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title crm-compact-heading">
                    <h3>Ajustes del sistema</h3>
                </div>
                <p align="center" style="color:#FF0000"><?php echo isset($_SESSION['msgsis']) ? $_SESSION['msgsis'] : '';?><?php $_SESSION['msgsis']="";?></p>

                <div class="row">
                    <div class="col-md-12">
                        <form class="form-horizontal" method="post" action="">
                        <div class="panel panel-default">
                            <div class="panel-body">
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Nombre del sistema</label>
                                    <div class="col-md-6">
                                        <input type="text" name="nombre_sistema" class="form-control" value="<?php echo htmlspecialchars($ajustes['nombre_sistema']); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Correo de aviso interno</label>
                                    <div class="col-md-6">
                                        <input type="email" name="correo_aviso" class="form-control" value="<?php echo htmlspecialchars($ajustes['correo_aviso']); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Registro público de clientes</label>
                                    <div class="col-md-6">
                                        <select name="registro_publico" class="form-control">
                                            <option value="si" <?php if($ajustes['registro_publico']=='si') echo 'selected'; ?>>Permitido</option>
                                            <option value="no" <?php if($ajustes['registro_publico']=='no') echo 'selected'; ?>>No permitido</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-3 control-label">Mensaje en el acceso admin</label>
                                    <div class="col-md-6">
                                        <textarea name="mensaje_login" class="form-control" rows="3"><?php echo htmlspecialchars($ajustes['mensaje_login']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-footer">
                                <input type="submit" name="guardar_ajustes" value="Guardar ajustes" class="btn btn-primary pull-right">
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
