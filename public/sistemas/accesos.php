<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');
$menu_activo='accesos';

if(isset($_POST['cambiar_usuario']))
{
	$msg=crm_cambiar_nombre_usuario(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0, isset($_POST['nuevo_usuario']) ? $_POST['nuevo_usuario'] : '');
	$_SESSION['msgsis']=($msg=='') ? 'Nombre de usuario actualizado.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['marcar_coord']))
{
	$msg=crm_marcar_coord_general(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0);
	$_SESSION['msgsis']=($msg=='') ? 'Coordinador general actualizado.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['reset_empleado']))
{
	$msg=crm_reset_clave_empleado(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0);
	$_SESSION['msgsis']=($msg=='') ? 'Clave restablecida al DNI. El usuario la cambia en el primer ingreso.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['reset_cliente']))
{
	$msg=crm_reset_clave_cliente(isset($_POST['id_usuario']) ? intval($_POST['id_usuario']) : 0);
	$_SESSION['msgsis']=($msg=='') ? 'Clave del cliente restablecida al DNI.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['clave_super']))
{
	$msg=crm_cambiar_clave_super(isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0, isset($_POST['nueva_clave']) ? $_POST['nueva_clave'] : '');
	$_SESSION['msgsis']=($msg=='') ? 'Clave del superadmin actualizada.' : $msg;
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}
if(isset($_POST['toggle_activo']))
{
	$id=isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0;
	$act=isset($_POST['activo']) ? intval($_POST['activo']) : 0;
	crm_set_empleado_activo($id, $act ? 0 : 1);
	$_SESSION['msgsis']='Acceso actualizado.';
	echo "<script>window.location.href='accesos.php'</script>";
	exit();
}

$coord=crm_coord_general_actual();
$resto=array();
foreach(crm_personal_sin_super() as $p)
{
	$resto[]=array(
		'id'=>intval($p['id_empleado']),
		'nombre'=>trim($p['nombres'].' '.$p['apellido']),
		'usuario'=>$p['nombre_usuario'],
		'area'=>isset($p['area_nombre']) ? $p['area_nombre'] : $p['area_clave'],
		'rol'=>isset($p['rol_clave']) ? $p['rol_clave'] : '',
		'activo'=>intval($p['activo'])
	);
}
$supers=array();
foreach(acceso_consulta("SELECT e.id_empleado, e.nombres, e.apellido, e.nombre_usuario FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol WHERE r.clave='superadmin'") as $p)
{
	$supers[]=array(
		'id'=>intval($p['id_empleado']),
		'nombre'=>trim($p['nombres'].' '.$p['apellido']),
		'usuario'=>$p['nombre_usuario']
	);
}
$clientes=acceso_consulta("SELECT id_usuario, nombre, correo, dni, primer_acceso, activo FROM usuario_cliente ORDER BY nombre");
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Sistemas - Accesos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
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
                    <li><a href="#" class="active">Sistemas / Accesos</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title"><h3>Accesos</h3></div>
                <p>Acá se restablecen claves (vuelve el DNI y el primer acceso). Coordinador general es una marca extra. Un solo a la vez.</p>
                <?php if(isset($_SESSION['msgsis']) && $_SESSION['msgsis']!='') { ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgsis']); $_SESSION['msgsis']=''; ?></div>
                <?php } ?>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Superadmin</h4></div>
                    <div class="grid-body no-border">
                        <?php foreach($supers as $persona) { ?>
                        <p><?php echo htmlspecialchars($persona['nombre']); ?> · <?php echo htmlspecialchars($persona['usuario']); ?></p>
                        <form method="post" action="" class="form-inline">
                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                            <input type="text" name="nueva_clave" class="form-control" placeholder="Nueva clave (mínimo 6)" style="width:220px; display:inline-block;">
                            <button type="submit" name="clave_super" value="1" class="btn btn-primary">Cambiar clave</button>
                        </form>
                        <?php } ?>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Coordinador general</h4></div>
                    <div class="grid-body no-border">
                        <p>Actual: <?php echo $coord ? htmlspecialchars(trim($coord['nombres'].' '.$coord['apellido']).' ('.$coord['nombre_usuario'].')') : 'ninguno'; ?></p>
                        <?php if(count($resto)==0) { ?>
                        <p>No hay personal cargado. Primero dales de alta en RRHH.</p>
                        <?php } else { ?>
                        <form method="post" action="">
                            <select name="id_empleado" class="form-control" style="max-width:360px;" required>
                                <option value="">Seleccionar empleado</option>
                                <?php foreach($resto as $persona) {
                                	$es_actual=($coord && intval($coord['id_empleado'])==intval($persona['id']));
                                ?>
                                <option value="<?php echo intval($persona['id']); ?>" <?php if($es_actual) echo 'selected'; ?>><?php echo htmlspecialchars($persona['nombre'].' — '.$persona['area'].($es_actual ? ' (actual)' : '')); ?></option>
                                <?php } ?>
                            </select>
                            <p style="margin-top:8px;"><button type="submit" name="marcar_coord" value="1" class="btn btn-primary">Asignar coordinador general</button></p>
                        </form>
                        <?php } ?>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Personal</h4></div>
                    <div class="grid-body no-border">
                        <table class="table table-hover">
                            <thead><tr><th>Nombre</th><th>Usuario</th><th>Área</th><th>Rol</th><th>Activo</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach($resto as $persona) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($persona['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($persona['usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($persona['area']); ?></td>
                                    <td><?php echo htmlspecialchars(acceso_mostrar_rol($persona['rol'])); ?></td>
                                    <td><?php echo $persona['activo'] ? 'Sí' : 'No'; ?></td>
                                    <td>
                                        <form method="post" action="" style="display:inline-block; margin-bottom:4px;">
                                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                                            <input type="text" name="nuevo_usuario" value="<?php echo htmlspecialchars($persona['usuario']); ?>" class="form-control input-sm" style="width:130px; display:inline-block;" required>
                                            <button type="submit" name="cambiar_usuario" value="1" class="btn btn-white btn-mini">Cambiar usuario</button>
                                        </form>
                                        <form method="post" action="" style="display:inline;">
                                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                                            <button type="submit" name="reset_empleado" value="1" class="btn btn-white btn-mini">Restablecer clave</button>
                                        </form>
                                        <form method="post" action="" style="display:inline;">
                                            <input type="hidden" name="id_empleado" value="<?php echo intval($persona['id']); ?>">
                                            <input type="hidden" name="activo" value="<?php echo intval($persona['activo']); ?>">
                                            <button type="submit" name="toggle_activo" value="1" class="btn btn-white btn-mini"><?php echo $persona['activo'] ? 'Desactivar' : 'Activar'; ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Clientes (ingreso web)</h4></div>
                    <div class="grid-body no-border">
                        <table class="table table-hover">
                            <thead><tr><th>Nombre</th><th>Correo</th><th>Primer acceso</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach($clientes as $u) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($u['correo']); ?></td>
                                    <td><?php echo intval($u['primer_acceso']) ? 'Sí' : 'No'; ?></td>
                                    <td>
                                        <form method="post" action="">
                                            <input type="hidden" name="id_usuario" value="<?php echo intval($u['id_usuario']); ?>">
                                            <button type="submit" name="reset_cliente" value="1" class="btn btn-white btn-xs">Restablecer clave</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
