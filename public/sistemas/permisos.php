<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');
$menu_activo='permisos';

$roles_extra_ok=function_exists('acceso_roles_extras_permitidos') ? acceso_roles_extras_permitidos() : array(
	'empleado'=>'Empleado',
	'coordinador'=>'Coordinador de área',
	'coordinador_general'=>'Coordinador general'
);
$areas_extra=acceso_etiquetas_area_asignables('empleado');

$id_ver=isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id_ver<=0 && isset($_POST['id_empleado']))
{
	$id_ver=intval($_POST['id_empleado']);
}

if(isset($_POST['agregar_rol_extra']))
{
	$id=isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0;
	$emp=acceso_empleado_por_id($id);
	if(!$emp || $emp['rol']==='superadmin')
	{
		$_SESSION['msgsis']='Empleado no válido.';
	}
	else if(isset($emp['rol']) && $emp['rol']==='presidencia')
	{
		$_SESSION['msgsis']='Presidencia no admite permisos adicionales.';
	}
	else
	{
		$msg=crm_rol_adicional_agregar(
			$id,
			isset($_POST['rol_extra']) ? $_POST['rol_extra'] : '',
			isset($_POST['area_extra']) ? $_POST['area_extra'] : ''
		);
		$_SESSION['msgsis']=($msg==='') ? 'Permiso adicional asignado. El usuario ve esa área al volver a entrar.' : $msg;
	}
	header('Location: permisos.php?id='.$id);
	exit();
}
if(isset($_POST['quitar_rol_extra']))
{
	$id=isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0;
	$msg=crm_rol_adicional_quitar(isset($_POST['id_rol_extra']) ? intval($_POST['id_rol_extra']) : 0, $id);
	$_SESSION['msgsis']=($msg==='') ? 'Permiso adicional quitado.' : $msg;
	header('Location: permisos.php?id='.$id);
	exit();
}

$personal=array();
foreach(crm_personal_sin_super() as $p)
{
	$personal[]=array(
		'id'=>intval($p['id_empleado']),
		'nombre'=>trim($p['nombres'].' '.$p['apellido']),
		'usuario'=>$p['nombre_usuario'],
		'area'=>isset($p['area_nombre']) && $p['area_nombre']!='' ? $p['area_nombre'] : 'Sin área',
		'area_clave'=>isset($p['area_clave']) ? $p['area_clave'] : '',
		'rol'=>isset($p['rol_clave']) ? $p['rol_clave'] : '',
		'activo'=>intval($p['activo'])
	);
}

$emp_ver=null;
$extras=array();
if($id_ver>0)
{
	$emp_ver=acceso_empleado_por_id($id_ver);
	if(!$emp_ver || $emp_ver['rol']==='superadmin')
	{
		$emp_ver=null;
		$id_ver=0;
	}
	else
	{
		$extras=crm_roles_adicionales_de($id_ver);
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Sistemas - Permisos</title>
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
                    <li><a href="#" class="active">Sistemas / Permisos</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title"><h3>Permisos adicionales</h3></div>
                <p class="muted">RRHH carga el rol y el área principales. Acá Sistemas suma o quita excepciones: otra área como empleado/coordinador, o Coordinador general.</p>
                <?php if(isset($_SESSION['msgsis']) && $_SESSION['msgsis']!='') { ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgsis']); $_SESSION['msgsis']=''; ?></div>
                <?php } ?>

                <?php if($emp_ver) { ?>
                <p><a href="permisos.php" class="btn btn-white btn-xs">&larr; Volver al listado</a></p>
                <div class="grid simple">
                    <div class="grid-title no-border"><h4><?php echo htmlspecialchars($emp_ver['nombre']!='' ? $emp_ver['nombre'] : $emp_ver['persona']); ?></h4></div>
                    <div class="grid-body no-border">
                        <p>
                            Usuario: <strong><?php echo htmlspecialchars($emp_ver['usuario']); ?></strong>
                            · Rol principal: <strong><?php echo htmlspecialchars(acceso_mostrar_rol($emp_ver['rol'])); ?></strong>
                            · Área: <strong><?php echo htmlspecialchars($emp_ver['area']!='' ? (isset(acceso_etiquetas_area()[$emp_ver['area']]) ? acceso_etiquetas_area()[$emp_ver['area']] : $emp_ver['area']) : 'Sin área'); ?></strong>
                        </p>
                        <?php if($emp_ver['rol']==='presidencia') { ?>
                        <p class="muted">Presidencia es exclusiva: no admite permisos adicionales.</p>
                        <?php } else { ?>
                        <?php if(count($extras)==0) { ?>
                        <p class="muted">No tiene permisos adicionales.</p>
                        <?php } else { ?>
                        <table class="table">
                            <thead><tr><th>Rol</th><th>Área</th><th>Desde</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach($extras as $rx) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rx['rol_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($rx['area_nombre']!='' ? $rx['area_nombre'] : '—'); ?></td>
                                    <td><?php echo htmlspecialchars($rx['fecha_desde']); ?></td>
                                    <td>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Quitar este permiso adicional?');">
                                            <input type="hidden" name="id_empleado" value="<?php echo intval($emp_ver['id']); ?>">
                                            <input type="hidden" name="id_rol_extra" value="<?php echo intval($rx['id']); ?>">
                                            <button type="submit" name="quitar_rol_extra" value="1" class="btn btn-white btn-xs">Quitar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                        <form method="post" style="margin-top:14px;">
                            <input type="hidden" name="id_empleado" value="<?php echo intval($emp_ver['id']); ?>">
                            <div class="form-group">
                                <label>Agregar rol</label>
                                <select name="rol_extra" class="form-control" style="max-width:360px;">
                                    <?php foreach($roles_extra_ok as $k=>$t) { ?>
                                    <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($t); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Área (obligatoria para empleado/coordinador; vacía si sumás Coordinador general)</label>
                                <select name="area_extra" class="form-control" style="max-width:360px;">
                                    <option value="">Sin área / Coordinador general</option>
                                    <?php foreach($areas_extra as $clave=>$titulo) { ?>
                                    <option value="<?php echo htmlspecialchars($clave); ?>"><?php echo htmlspecialchars($titulo); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <button type="submit" name="agregar_rol_extra" value="1" class="btn btn-primary">Agregar permiso</button>
                        </form>
                        <?php } ?>
                    </div>
                </div>
                <?php } else { ?>
                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Personal</h4></div>
                    <div class="grid-body no-border">
                        <table class="table table-hover">
                            <thead><tr><th>Nombre</th><th>Usuario</th><th>Área</th><th>Rol</th><th>Extras</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach($personal as $persona) {
                            	$n_ex=count(crm_roles_adicionales_de($persona['id']));
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($persona['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($persona['usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($persona['area']); ?></td>
                                    <td><?php echo htmlspecialchars(acceso_mostrar_rol($persona['rol'])); ?></td>
                                    <td><?php echo intval($n_ex); ?></td>
                                    <td><a href="permisos.php?id=<?php echo intval($persona['id']); ?>" class="btn btn-primary btn-xs">Gestionar</a></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
