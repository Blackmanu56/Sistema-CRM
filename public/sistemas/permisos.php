<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff('sistemas');
acceso_exigir_gestion('sistemas');
$menu_activo='permisos';

$roles_extra_ok=function_exists('acceso_roles_extras_permitidos') ? acceso_roles_extras_permitidos() : array(
	'empleado'=>'Colaborador',
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
	$id_logueado=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : (isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0);
	$es_propio=($id_logueado>0 && $id===$id_logueado);
	$rol_extra=isset($_POST['rol_extra']) ? trim($_POST['rol_extra']) : '';

	if(!$emp || $emp['rol']==='superadmin')
	{
		$_SESSION['msgsis']='Colaborador no válido o Superadmin no modificable.';
	}
	else if($es_propio)
	{
		$_SESSION['msgsis']='No podés modificar tus propios permisos adicionales.';
	}
	else if(isset($emp['rol']) && $emp['rol']==='presidencia')
	{
		$_SESSION['msgsis']='Presidencia no admite permisos adicionales.';
	}
	else if($rol_extra==='superadmin' || $rol_extra==='presidencia')
	{
		$_SESSION['msgsis']='No se puede asignar superadmin ni presidencia como rol adicional.';
	}
	else
	{
		$msg=crm_rol_adicional_agregar(
			$id,
			$rol_extra,
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
	$emp=acceso_empleado_por_id($id);
	$id_logueado=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : (isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0);
	$es_propio=($id_logueado>0 && $id===$id_logueado);

	if(!$emp || $emp['rol']==='superadmin')
	{
		$_SESSION['msgsis']='Colaborador no válido o Superadmin no modificable.';
	}
	else if($es_propio)
	{
		$_SESSION['msgsis']='No podés modificar tus propios permisos adicionales.';
	}
	else
	{
		$msg=crm_rol_adicional_quitar(isset($_POST['id_rol_extra']) ? intval($_POST['id_rol_extra']) : 0, $id);
		$_SESSION['msgsis']=($msg==='') ? 'Permiso adicional quitado.' : $msg;
	}
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
                <div class="page-title crm-compact-heading"><h3>Permisos adicionales</h3></div>
                <p class="muted">RRHH carga el rol y el área principales. Acá Sistemas suma o quita excepciones: otra área como colaborador/coordinador, o Coordinador general.</p>
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
                                        <button type="button" class="btn btn-white btn-xs" onclick="confirmarQuitarRolExtra(<?php echo intval($emp_ver['id']); ?>, <?php echo intval($rx['id']); ?>, '<?php echo htmlspecialchars(addslashes(trim(isset($emp_ver['nombre']) ? $emp_ver['nombre'] : $emp_ver['persona']))); ?>', '<?php echo htmlspecialchars(addslashes($rx['rol_nombre'])); ?>', '<?php echo htmlspecialchars(addslashes($rx['area_nombre']!='' ? $rx['area_nombre'] : '—')); ?>');">Quitar</button>
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
                                <label>Área (obligatoria para colaborador/coordinador; vacía si sumás Coordinador general)</label>
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

<div class="modal fade" id="modalQuitarRolExtra" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Confirmar eliminación de permiso adicional</h4>
      </div>
      <div class="modal-body">
        <p>¿Estás seguro de que deseas quitar este permiso adicional?</p>
        <p style="margin-bottom:6px;"><strong>Colaborador:</strong> <span id="modalQuitarNombre"></span></p>
        <p style="margin-bottom:0;"><strong>Rol / Área afectada:</strong> <span id="modalQuitarRolArea"></span></p>
      </div>
      <div class="modal-footer">
        <form method="post" id="formQuitarRolExtra" style="display:inline;">
          <input type="hidden" name="id_empleado" id="modalQuitarIdEmpleado" value="">
          <input type="hidden" name="id_rol_extra" id="modalQuitarIdRolExtra" value="">
          <button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button>
          <button type="submit" name="quitar_rol_extra" value="1" class="btn btn-danger">Sí, quitar rol</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script type="text/javascript">
function confirmarQuitarRolExtra(idEmpleado, idRolExtra, nombreColaborador, rolNombre, areaNombre) {
    document.getElementById('modalQuitarIdEmpleado').value = idEmpleado;
    document.getElementById('modalQuitarIdRolExtra').value = idRolExtra;
    document.getElementById('modalQuitarNombre').textContent = nombreColaborador;
    document.getElementById('modalQuitarRolArea').textContent = rolNombre + (areaNombre && areaNombre !== '—' ? ' (' + areaNombre + ')' : '');
    $('#modalQuitarRolExtra').modal('show');
}
</script>
</body>
</html>
