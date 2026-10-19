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
$roles_principales_completos=acceso_roles_para_alta();
$roles_principales=$roles_principales_completos;
$puede_asignar_roles_protegidos=acceso_es_super() || acceso_es_coord_general();
if(!$puede_asignar_roles_protegidos)
{
	unset($roles_principales['presidencia'], $roles_principales['coordinador_general']);
}

$id_ver=isset($_GET['id']) ? intval($_GET['id']) : 0;
if($id_ver<=0 && isset($_POST['id_empleado']))
{
	$id_ver=intval($_POST['id_empleado']);
}

if(isset($_POST['guardar_rol_principal']))
{
	$id=isset($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0;
	$emp=acceso_empleado_por_id($id);
	$id_logueado=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : (isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0);
	$rol_principal=isset($_POST['rol_principal']) && is_string($_POST['rol_principal']) ? trim($_POST['rol_principal']) : '';
	$area_principal=isset($_POST['area_principal']) && is_string($_POST['area_principal']) ? trim($_POST['area_principal']) : '';
	$puede_gestionar_principal=acceso_puede_gestionar('sistemas') && !acceso_es_empleado();

	if(!$puede_gestionar_principal)
	{
		$_SESSION['msgsis']='No tenés permiso para cambiar el rol o el área principal.';
	}
	else if(!$emp || $emp['rol']==='superadmin')
	{
		$_SESSION['msgsis']='Colaborador no válido o Superadmin no modificable.';
	}
	else if($id_logueado>0 && $id===$id_logueado)
	{
		$_SESSION['msgsis']='No podés modificar tu propio rol principal ni tu área principal.';
	}
	else if(($emp['rol']==='presidencia' || $emp['rol']==='coordinador_general') && !$puede_asignar_roles_protegidos)
	{
		$_SESSION['msgsis']='Ese perfil principal está protegido y requiere autorización especial.';
	}
	else if(!isset($roles_principales_completos[$rol_principal]))
	{
		$_SESSION['msgsis']='El rol principal seleccionado no es válido.';
	}
	else if(in_array($rol_principal,array('presidencia','coordinador_general'),true) && !$puede_asignar_roles_protegidos)
	{
		$_SESSION['msgsis']='No tenés autorización para asignar ese rol protegido.';
	}
	else
	{
		if($rol_principal==='presidencia' || $rol_principal==='coordinador_general')
		{
			$area_principal='';
		}
		$error_rol_area=acceso_validar_rol_area($rol_principal,$area_principal,$id);
		if($error_rol_area!=='')
		{
			$_SESSION['msgsis']=$error_rol_area;
		}
		else if(acceso_guardar_empleado($emp['usuario'],array('rol'=>$rol_principal,'area'=>$area_principal)))
		{
			$_SESSION['msgsis']='Rol principal y área principal actualizados. Los permisos y áreas asignadas se reflejarán al volver a iniciar sesión.';
		}
		else
		{
			$_SESSION['msgsis']='No se pudo actualizar el rol y el área. Revisá si ya existe un coordinador asignado a esa área.';
		}
	}
	header('Location: permisos.php?id='.$id);
	exit();
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
$id_logueado_vista=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : (isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0);
$puede_editar_rol_principal=$emp_ver && acceso_puede_gestionar('sistemas') && !acceso_es_empleado()
	&& intval($emp_ver['id'])!==intval($id_logueado_vista)
	&& ((!in_array($emp_ver['rol'],array('presidencia','coordinador_general'),true)) || $puede_asignar_roles_protegidos);
$areas_principales=acceso_etiquetas_area_asignables('empleado');
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
<style>
    /* Compact spacing for the Permisos screen only. */
    .crm-permisos-list .breadcrumb { margin-bottom: 10px; }
    .crm-permisos-list .crm-compact-heading { margin-top: 8px; margin-bottom: 8px; }
    .crm-permisos-list > .muted { margin: 0 0 12px; }
    .crm-permisos-list > .alert { margin-bottom: 12px; }
    .crm-permisos-list .grid.simple { margin-bottom: 12px; }
    .crm-permisos-list .grid-title { padding-top: 10px; padding-bottom: 8px; }
    .crm-permisos-list .grid-body { padding-top: 10px; }

    @media (max-width: 767px) {
        .crm-permisos-list .breadcrumb { margin-bottom: 8px; }
        .crm-permisos-list .crm-compact-heading { margin-top: 6px; margin-bottom: 6px; }
        .crm-permisos-list > .muted { margin-bottom: 10px; }
        .crm-permisos-list .grid-title { padding-top: 8px; padding-bottom: 6px; }
        .crm-permisos-list .grid-body { padding-top: 8px; }
    }
</style>
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
            <div class="content crm-permisos-page <?php echo $emp_ver ? 'crm-permisos-detail' : 'crm-permisos-list'; ?>">
                <ul class="breadcrumb">
                    <li><p>ESTÁS AQUÍ</p></li>
                    <li><a href="#" class="active">Sistemas / Permisos</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title crm-compact-heading"><h3>Roles y permisos</h3></div>
                <p class="muted">Los administradores autorizados pueden cambiar el rol y el área principales de otros empleados, además de sumar o quitar roles adicionales.</p>
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
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Rol principal y área principal</h4></div>
                            <div class="grid-body no-border">
                                <?php if($puede_editar_rol_principal) { ?>
                                <form method="post" action="permisos.php?id=<?php echo intval($emp_ver['id']); ?>" id="formRolPrincipal">
                                    <input type="hidden" name="id_empleado" value="<?php echo intval($emp_ver['id']); ?>" />
                                    <div class="row">
                                        <div class="col-md-5 form-group">
                                            <label for="rol_principal">Rol principal</label>
                                            <select class="form-control" name="rol_principal" id="rol_principal" required>
                                                <?php foreach($roles_principales as $clave=>$titulo) { ?>
                                                <option value="<?php echo htmlspecialchars($clave,ENT_QUOTES,'UTF-8'); ?>" <?php echo $emp_ver['rol']===$clave ? 'selected="selected"' : ''; ?>><?php echo htmlspecialchars($titulo,ENT_QUOTES,'UTF-8'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-md-5 form-group" id="caja_area_principal">
                                            <label for="area_principal">Área principal</label>
                                            <select class="form-control" name="area_principal" id="area_principal">
                                                <option value="">Sin área</option>
                                                <?php foreach($areas_principales as $clave=>$titulo) { ?>
                                                <option value="<?php echo htmlspecialchars($clave,ENT_QUOTES,'UTF-8'); ?>" <?php echo $emp_ver['area']===$clave ? 'selected="selected"' : ''; ?>><?php echo htmlspecialchars($titulo,ENT_QUOTES,'UTF-8'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2 form-group" style="padding-top:24px;">
                                            <button class="btn btn-primary" type="submit" name="guardar_rol_principal" value="1">Guardar rol y área</button>
                                        </div>
                                    </div>
                                    <p class="muted">Cambiar estos campos modifica únicamente la asignación principal; los roles adicionales se conservan.</p>
                                </form>
                                <?php } else { ?>
                                <p class="muted">El rol principal y el área principal no se pueden modificar desde esta cuenta para este colaborador.</p>
                                <?php } ?>
                            </div>
                        </div>
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
function crmToggleAreaPrincipal(){
    var rol=document.getElementById('rol_principal'), area=document.getElementById('area_principal');
    var caja=document.getElementById('caja_area_principal');
    if(!rol || !area || !caja) { return; }
    var necesitaArea=(rol.value==='empleado' || rol.value==='coordinador');
    caja.style.display=necesitaArea ? '' : 'none';
    area.required=necesitaArea;
    if(!necesitaArea) { area.value=''; }
}
var rolPrincipalSelect=document.getElementById('rol_principal');
if(rolPrincipalSelect) { rolPrincipalSelect.addEventListener('change', crmToggleAreaPrincipal); crmToggleAreaPrincipal(); }
</script>
</body>
</html>
