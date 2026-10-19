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
include_once BASE_PATH.'/includes/provincias.php';
$menu_activo='provincias';

$id_ver=isset($_GET['provincia']) ? intval($_GET['provincia']) : 0;
if(isset($_POST['alta_provincia']))
{
	$msg=provincia_guardar(isset($_POST['nombre_provincia']) ? $_POST['nombre_provincia'] : '');
	if($msg=='')
	{
		$_SESSION['msgprov']='Provincia cargada.';
	}
	else
	{
		$_SESSION['msgprov']=$msg;
	}
	header('Location: provincias.php');
	exit();
}
if(isset($_POST['alta_localidad']))
{
	$id_ver=isset($_POST['id_provincia']) ? intval($_POST['id_provincia']) : 0;
	$msg=localidad_guardar(isset($_POST['nombre_localidad']) ? $_POST['nombre_localidad'] : '', $id_ver);
	if($msg=='')
	{
		$_SESSION['msgprov']='Localidad cargada.';
	}
	else
	{
		$_SESSION['msgprov']=$msg;
	}
	header('Location: provincias.php?provincia='.$id_ver);
	exit();
}
if(isset($_POST['editar_provincia']))
{
	$id_p=isset($_POST['id_provincia']) ? intval($_POST['id_provincia']) : 0;
	$nom=isset($_POST['nombre_provincia']) ? $_POST['nombre_provincia'] : '';
	$msg=provincia_actualizar($id_p, $nom);
	if($msg=='')
	{
		$_SESSION['msgprov']='Provincia actualizada correctamente.';
		$_SESSION['msgprov_tipo']='alert-success';
	}
	else
	{
		$_SESSION['msgprov']=$msg;
		$_SESSION['msgprov_tipo']='alert-danger';
	}
	header('Location: provincias.php?provincia='.$id_p);
	exit();
}
if(isset($_POST['borrar_provincia']))
{
	$id_p=isset($_POST['id_provincia']) ? intval($_POST['id_provincia']) : 0;
	$msg=provincia_eliminar($id_p);
	if($msg=='')
	{
		$_SESSION['msgprov']='Provincia eliminada correctamente.';
		$_SESSION['msgprov_tipo']='alert-success';
	}
	else
	{
		$_SESSION['msgprov']=$msg;
		$_SESSION['msgprov_tipo']='alert-danger';
	}
	header('Location: provincias.php');
	exit();
}
if(isset($_POST['editar_localidad']))
{
	$id_l=isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0;
	$id_p=isset($_POST['id_provincia']) ? intval($_POST['id_provincia']) : 0;
	$nom=isset($_POST['nombre_localidad']) ? $_POST['nombre_localidad'] : '';
	$msg=localidad_actualizar($id_l, $nom);
	if($msg=='')
	{
		$_SESSION['msgprov']='Localidad actualizada correctamente.';
		$_SESSION['msgprov_tipo']='alert-success';
	}
	else
	{
		$_SESSION['msgprov']=$msg;
		$_SESSION['msgprov_tipo']='alert-danger';
	}
	header('Location: provincias.php?provincia='.$id_p);
	exit();
}
if(isset($_POST['borrar_localidad']))
{
	$id_l=isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0;
	$id_p=isset($_POST['id_provincia']) ? intval($_POST['id_provincia']) : 0;
	$msg=localidad_eliminar($id_l);
	if($msg=='')
	{
		$_SESSION['msgprov']='Localidad eliminada correctamente.';
		$_SESSION['msgprov_tipo']='alert-success';
	}
	else
	{
		$_SESSION['msgprov']=$msg;
		$_SESSION['msgprov_tipo']='alert-danger';
	}
	header('Location: provincias.php?provincia='.$id_p);
	exit();
}

$provincias=provincias_todas();
if($id_ver<=0 && count($provincias)>0)
{
	$id_ver=intval($provincias[0]['id_provincia']);
}
$localidades=localidades_de_provincia($id_ver);
$nombre_prov='';
foreach($provincias as $p)
{
	if(intval($p['id_provincia'])==$id_ver)
	{
		$nombre_prov=$p['nombre'];
		break;
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Provincias y localidades</title>
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
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>">Coordinación general</a></li>
                    <li><a href="#" class="active">Provincias y localidades</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Provincias y localidades</h3>
                </div>
                <p>Primero se carga la provincia. Después, las localidades de esa provincia.</p>
                <?php if(isset($_SESSION['msgprov']) && $_SESSION['msgprov']!='') { 
                    $alert_tipo = isset($_SESSION['msgprov_tipo']) ? $_SESSION['msgprov_tipo'] : 'alert-info';
                ?>
                <div class="alert <?php echo htmlspecialchars($alert_tipo); ?>">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <?php echo htmlspecialchars($_SESSION['msgprov']); $_SESSION['msgprov']=''; unset($_SESSION['msgprov_tipo']); ?>
                </div>
                <?php } ?>

                <div class="row">
                    <div class="col-md-5">
                        <div class="grid simple">
                            <div class="grid-title no-border">
                                <h4>Provincias</h4>
                            </div>
                            <div class="grid-body no-border">
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label>Nueva provincia</label>
                                        <input type="text" name="nombre_provincia" required class="form-control"/>
                                    </div>
                                    <button type="submit" name="alta_provincia" class="btn btn-primary">Agregar provincia</button>
                                </form>
                                <hr>
                                <table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>Provincia</th>
                                            <th style="width: 140px; text-align: right;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($provincias as $p) { ?>
                                        <tr>
                                            <td style="vertical-align: middle;"><?php echo htmlspecialchars($p['nombre']); ?></td>
                                            <td style="text-align: right; white-space: nowrap; vertical-align: middle;">
                                                <a href="provincias.php?provincia=<?php echo intval($p['id_provincia']); ?>" class="btn btn-white btn-xs" title="Ver localidades"><i class="fa fa-folder-open-o"></i></a>
                                                <button type="button" class="btn btn-white btn-xs btn-editar-prov" data-id="<?php echo intval($p['id_provincia']); ?>" data-nombre="<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>" title="Editar provincia"><i class="fa fa-pencil"></i></button>
                                                <form method="post" action="" style="display: inline-block; margin: 0;" onsubmit="return confirm('¿Seguro que querés eliminar la provincia &quot;<?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>&quot;?');">
                                                    <input type="hidden" name="id_provincia" value="<?php echo intval($p['id_provincia']); ?>">
                                                    <button type="submit" name="borrar_provincia" class="btn btn-danger btn-xs" title="Eliminar provincia"><i class="fa fa-trash-o"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if(count($provincias)==0) { ?>
                                        <tr><td colspan="2">Todavía no hay provincias.</td></tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="grid simple">
                            <div class="grid-title no-border">
                                <h4>Localidades<?php if($nombre_prov!='') { echo ' de '.htmlspecialchars($nombre_prov); } ?></h4>
                            </div>
                            <div class="grid-body no-border">
                                <?php if($id_ver<=0) { ?>
                                <p>Elegí una provincia para cargar sus localidades.</p>
                                <?php } else { ?>
                                <form method="post" action="">
                                    <input type="hidden" name="id_provincia" value="<?php echo intval($id_ver); ?>">
                                    <div class="form-group">
                                        <label>Nueva localidad</label>
                                        <input type="text" name="nombre_localidad" required class="form-control"/>
                                    </div>
                                    <button type="submit" name="alta_localidad" class="btn btn-primary">Agregar localidad</button>
                                </form>
                                <hr>
                                <table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>Localidad</th>
                                            <th style="width: 100px; text-align: right;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($localidades as $loc) { ?>
                                        <tr>
                                            <td style="vertical-align: middle;"><?php echo htmlspecialchars($loc['nombre']); ?></td>
                                            <td style="text-align: right; white-space: nowrap; vertical-align: middle;">
                                                <button type="button" class="btn btn-white btn-xs btn-editar-loc" data-id="<?php echo intval($loc['id_localidad']); ?>" data-nombre="<?php echo htmlspecialchars($loc['nombre'], ENT_QUOTES, 'UTF-8'); ?>" title="Editar localidad"><i class="fa fa-pencil"></i></button>
                                                <form method="post" action="" style="display: inline-block; margin: 0;" onsubmit="return confirm('¿Seguro que querés eliminar la localidad &quot;<?php echo htmlspecialchars($loc['nombre'], ENT_QUOTES, 'UTF-8'); ?>&quot;?');">
                                                    <input type="hidden" name="id_localidad" value="<?php echo intval($loc['id_localidad']); ?>">
                                                    <input type="hidden" name="id_provincia" value="<?php echo intval($id_ver); ?>">
                                                    <button type="submit" name="borrar_localidad" class="btn btn-danger btn-xs" title="Eliminar localidad"><i class="fa fa-trash-o"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    <?php if(count($localidades)==0) { ?>
                                        <tr><td colspan="2">Todavía no hay localidades en esta provincia.</td></tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
 </div>

<!-- Modal Editar Provincia -->
<div class="modal fade" id="modalEditarProvincia" tabindex="-1" role="dialog" aria-labelledby="lblModalProv" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="lblModalProv">Editar provincia</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_provincia" id="edit_id_provincia" value="0">
                    <div class="form-group">
                        <label for="edit_nombre_provincia">Nombre de la provincia</label>
                        <input type="text" name="nombre_provincia" id="edit_nombre_provincia" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_provincia" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Localidad -->
<div class="modal fade" id="modalEditarLocalidad" tabindex="-1" role="dialog" aria-labelledby="lblModalLoc" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="lblModalLoc">Editar localidad</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_localidad" id="edit_id_localidad" value="0">
                    <input type="hidden" name="id_provincia" value="<?php echo intval($id_ver); ?>">
                    <div class="form-group">
                        <label for="edit_nombre_localidad">Nombre de la localidad</label>
                        <input type="text" name="nombre_localidad" id="edit_nombre_localidad" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_localidad" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
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
$(document).ready(function() {
    $('.btn-editar-prov').on('click', function() {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        $('#edit_id_provincia').val(id);
        $('#edit_nombre_provincia').val(nombre);
        $('#modalEditarProvincia').modal('show');
    });

    $('.btn-editar-loc').on('click', function() {
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        $('#edit_id_localidad').val(id);
        $('#edit_nombre_localidad').val(nombre);
        $('#modalEditarLocalidad').modal('show');
    });
});
</script>
</body>
</html>
