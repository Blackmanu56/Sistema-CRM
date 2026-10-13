<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('rrhh');
acceso_exigir_gestion('rrhh');

// Modificar un empleado en la tabla empleado.

$menu_activo='empleados';
$etiquetas=acceso_etiquetas_area_asignables(isset($empleado['rol']) ? $empleado['rol'] : '');
if(isset($empleado['area']) && $empleado['area']!='' && !isset($etiquetas[$empleado['area']]))
{
	$todas=acceso_etiquetas_area();
	if(isset($todas[$empleado['area']]))
	{
		$etiquetas[$empleado['area']]=$todas[$empleado['area']];
	}
}
$roles=acceso_roles_para_alta();
$usuario=isset($_GET['usuario']) ? trim($_GET['usuario']) : '';
$empleado=acceso_empleado_por_usuario($usuario);

if($empleado==null || $empleado['rol']=='superadmin')
{
	header("Location: empleados.php");
	exit();
}

$es_empleado_comun=acceso_es_empleado();
$es_super=acceso_es_super();
$es_coord_general=acceso_es_coord_general();
$es_coord_area=acceso_es_coord_de_area();

$es_gestion_sistemas=($es_super || $es_coord_general || ((acceso_area()=='sistemas' || acceso_area_pantalla()=='sistemas') && $es_coord_area));
$es_gestion_rrhh=($es_super || $es_coord_general || ((acceso_area()=='rrhh' || acceso_area_pantalla()=='rrhh') && $es_coord_area));

$puede_extras=($es_super || $es_coord_general || $es_gestion_sistemas || $es_gestion_rrhh) && !$es_empleado_comun;

// Presidencia no admite extras.
if(isset($empleado['rol']) && $empleado['rol']==='presidencia')
{
	$puede_extras=false;
}

$id_logueado=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : (isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0);
$es_propio=($id_logueado>0 && intval($empleado['id'])===$id_logueado);

$roles_extra_ok=function_exists('acceso_roles_extras_permitidos') ? acceso_roles_extras_permitidos() : array(
	'empleado'=>'Colaborador',
	'coordinador'=>'Coordinador de área',
	'coordinador_general'=>'Coordinador general'
);
$areas_extra=acceso_etiquetas_area_asignables('empleado');

if(isset($_POST['agregar_rol_extra']))
{
	$rol_extra=isset($_POST['rol_extra']) ? trim($_POST['rol_extra']) : '';
	if(!$puede_extras)
	{
		$_SESSION['msgrrhh']='No tenés permiso para asignar roles o áreas adicionales.';
	}
	else if($empleado['rol']==='superadmin')
	{
		$_SESSION['msgrrhh']='No se pueden modificar roles adicionales a un superadmin.';
	}
	else if($es_propio)
	{
		$_SESSION['msgrrhh']='No podés modificar tus propios roles adicionales.';
	}
	else if($rol_extra==='superadmin' || $rol_extra==='presidencia')
	{
		$_SESSION['msgrrhh']='No se puede asignar superadmin ni presidencia como rol adicional.';
	}
	else
	{
		$msg=crm_rol_adicional_agregar(
			intval($empleado['id']),
			$rol_extra,
			isset($_POST['area_extra']) ? $_POST['area_extra'] : ''
		);
		$_SESSION['msgrrhh']=($msg==='') ? 'Rol adicional asignado. Queda vinculado a esa área.' : $msg;
	}
	header('Location: editar-empleado.php?usuario='.rawurlencode($empleado['usuario']));
	exit();
}
if(isset($_POST['quitar_rol_extra']))
{
	if(!$puede_extras)
	{
		$_SESSION['msgrrhh']='No tenés permiso para quitar roles adicionales.';
	}
	else if($empleado['rol']==='superadmin')
	{
		$_SESSION['msgrrhh']='No se pueden modificar roles adicionales a un superadmin.';
	}
	else if($es_propio)
	{
		$_SESSION['msgrrhh']='No podés modificar tus propios roles adicionales.';
	}
	else
	{
		$msg=crm_rol_adicional_quitar(isset($_POST['id_rol_extra']) ? intval($_POST['id_rol_extra']) : 0, intval($empleado['id']));
		$_SESSION['msgrrhh']=($msg==='') ? 'Rol adicional quitado.' : $msg;
	}
	header('Location: editar-empleado.php?usuario='.rawurlencode($empleado['usuario']));
	exit();
}

if(isset($_POST['guardar']))
{
	$nombres=trim(isset($_POST['nombres']) ? $_POST['nombres'] : '');
	$apellido=trim(isset($_POST['apellido']) ? $_POST['apellido'] : '');
	$correo=trim(isset($_POST['correo']) ? $_POST['correo'] : '');
	
	if($es_propio || $empleado['rol']==='superadmin')
	{
		$rol=$empleado['rol'];
		$area=isset($empleado['area']) ? $empleado['area'] : '';
	}
	else
	{
		$rol=isset($_POST['rol']) ? $_POST['rol'] : $empleado['rol'];
		$area='';
		if($rol==='empleado' || $rol==='coordinador')
		{
			$area=isset($_POST['area']) ? $_POST['area'] : '';
		}
	}
	$usuario_nuevo=trim(isset($_POST['usuario_nuevo']) ? $_POST['usuario_nuevo'] : $usuario);

	if($nombres=='' || $apellido=='')
	{
		$_SESSION['msgrrhh']="Faltan nombre y apellido.";
	}
	else if($correo=='')
	{
		$_SESSION['msgrrhh']="Falta el correo.";
	}
	else if(acceso_correo_existe($correo, $empleado['id']))
	{
		$_SESSION['msgrrhh']="Ese correo ya está cargado.";
	}
	else if(!$es_propio && $empleado['rol']!='superadmin' && !isset($roles[$rol]))
	{
		$_SESSION['msgrrhh']="El rol no es válido.";
	}
	else if(!$es_propio && ($rol==='empleado' || $rol==='coordinador') && $area==='' )
	{
		$_SESSION['msgrrhh']="Indicá el área para ese rol.";
	}
	else
	{
		$msg_user='';
		if($usuario_nuevo!==$usuario && function_exists('crm_cambiar_nombre_usuario'))
		{
			$msg_user=crm_cambiar_nombre_usuario(intval($empleado['id']), $usuario_nuevo);
		}
		if($msg_user!='')
		{
			$_SESSION['msgrrhh']=$msg_user;
			$empleado=acceso_empleado_por_usuario($usuario);
		}
		else
		{
			$usuario_guardar=($msg_user==='' && $usuario_nuevo!=='' ? $usuario_nuevo : $usuario);
			$datos=array(
				'nombres'=>$nombres,
				'apellido'=>$apellido,
				'correo'=>$correo,
				'area'=>$area,
				'telefono'=>isset($_POST['telefono']) ? $_POST['telefono'] : '',
				'telefono_alt'=>isset($_POST['telefono_alt']) ? $_POST['telefono_alt'] : '',
				'direccion'=>isset($_POST['direccion']) ? $_POST['direccion'] : '',
				'fecha_nacimiento'=>isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : '',
				'puesto'=>isset($_POST['puesto']) ? $_POST['puesto'] : '',
				'tipo_vinculo'=>isset($_POST['tipo_vinculo']) ? $_POST['tipo_vinculo'] : '',
				'estado_laboral'=>isset($_POST['estado_laboral']) ? $_POST['estado_laboral'] : 'activo',
				'modalidad'=>isset($_POST['modalidad']) ? $_POST['modalidad'] : '',
				'condicion_fiscal'=>isset($_POST['condicion_fiscal']) ? $_POST['condicion_fiscal'] : ''
			);
			if(!$es_propio && $empleado['rol']!='superadmin')
			{
				$datos['rol']=$rol;
			}
			else
			{
				$datos['rol']=$empleado['rol'];
				$datos['area']=isset($empleado['area']) ? $empleado['area'] : '';
			}
			// Tras posible cambio de username, guardar por el usuario actual en BD
			$ok=acceso_guardar_empleado($usuario_guardar, $datos);
			if(!$ok && $usuario_guardar!==$usuario)
			{
				$ok=acceso_guardar_empleado($usuario, $datos);
			}
			if(!$ok)
			{
				$_SESSION['msgrrhh']="No se pudo guardar. Si pasaste a coordinador, esa área ya tiene uno.";
				$empleado=acceso_empleado_por_id(intval($empleado['id']));
			}
			else
			{
				if(isset($_SESSION['alogin']) && $_SESSION['alogin']===$usuario && $usuario_nuevo!==$usuario && $usuario_nuevo!='')
				{
					$_SESSION['alogin']=$usuario_nuevo;
				}
				$_SESSION['msgrrhh']="Colaborador actualizado.";
				header('Location: empleados.php');
				exit();
			}
		}
	}
	$empleado=acceso_empleado_por_id(intval($empleado['id']));
	if($empleado)
	{
		$usuario=$empleado['usuario'];
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Recursos humanos - Editar colaborador</title>
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
                    <li><a href="empleados.php">Colaboradores</a></li>
                    <li><a href="#" class="active">Editar colaborador</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    	<h3>Editar colaborador</h3>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4>Datos del colaborador</h4>
                        </div>
                        <div class="grid-body no-border">
                        <form method="post" action="" accept-charset="UTF-8">
                                <?php if(isset($_SESSION['msgrrhh']) && $_SESSION['msgrrhh']!='') { ?>
                                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgrrhh']); $_SESSION['msgrrhh']=''; ?></div>
                                <?php } ?>

                                <div class="form-group">
                                    <label>Nombres</label>
                                    <input type="text" name="nombres" required class="form-control" value="<?php echo htmlspecialchars($empleado['nombres']); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Apellido</label>
                                    <input type="text" name="apellido" required class="form-control" value="<?php echo htmlspecialchars($empleado['apellido']); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Usuario</label>
                                    <input type="text" name="usuario_nuevo" required class="form-control" value="<?php echo htmlspecialchars($empleado['usuario']); ?>" pattern="[a-zA-Z0-9._\-]{3,40}" title="3 a 40 caracteres: letras, números, . _ -"/>
                                    <p class="muted">Si lo cambiás, esa persona entra con el nuevo usuario.</p>
                                </div>
                                <div class="form-group">
                                    <label>Correo</label>
                                    <input type="email" name="correo" required class="form-control" value="<?php echo htmlspecialchars($empleado['correo']); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['telefono']) ? $empleado['telefono'] : ''); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono alternativo</label>
                                    <input type="text" name="telefono_alt" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['telefono_alt']) ? $empleado['telefono_alt'] : ''); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['direccion']) ? $empleado['direccion'] : ''); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de nacimiento</label>
                                    <input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['fecha_nacimiento']) ? $empleado['fecha_nacimiento'] : ''); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Puesto / función</label>
                                    <input type="text" name="puesto" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['puesto']) ? $empleado['puesto'] : ''); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Tipo de vínculo</label>
                                    <select name="tipo_vinculo" class="form-control">
                                        <?php
                                        $vinc=array(''=>'—','monotributo'=>'Monotributo','contrato'=>'Contrato','planta'=>'Planta','pasantia'=>'Pasantía','otro'=>'Otro');
                                        $sel=isset($empleado['tipo_vinculo']) ? $empleado['tipo_vinculo'] : '';
                                        foreach($vinc as $k=>$t) { ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php if($sel===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Estado laboral</label>
                                    <select name="estado_laboral" class="form-control">
                                        <?php $el=isset($empleado['estado_laboral']) ? $empleado['estado_laboral'] : 'activo';
                                        foreach(array('activo'=>'Activo','inactivo'=>'Inactivo','licencia'=>'Licencia') as $k=>$t) { ?>
                                        <option value="<?php echo $k; ?>" <?php if($el===$k) echo 'selected'; ?>><?php echo $t; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Modalidad</label>
                                    <select name="modalidad" class="form-control">
                                        <?php $mod=isset($empleado['modalidad']) ? $empleado['modalidad'] : '';
                                        foreach(array(''=>'—','presencial'=>'Presencial','hibrida'=>'Híbrida','remota'=>'Remota') as $k=>$t) { ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php if($mod===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Condición fiscal</label>
                                    <select name="condicion_fiscal" class="form-control">
                                        <?php
                                        $cf_ops=acceso_condiciones_fiscales();
                                        $cf_sel=isset($empleado['condicion_fiscal']) ? trim((string)$empleado['condicion_fiscal']) : '';
                                        if($cf_sel!=='' && !isset($cf_ops[$cf_sel]))
                                        {
                                            $cf_ops[$cf_sel]=$cf_sel;
                                        }
                                        foreach($cf_ops as $k=>$t) { ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php if($cf_sel===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Rol principal</label>
                                    <?php if($es_propio || $empleado['rol']=='superadmin') { ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(acceso_mostrar_rol($empleado['rol'])); ?>" readonly/>
                                    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($empleado['rol']); ?>"/>
                                    <p class="muted">No podés modificar tu propio rol principal.</p>
                                    <?php } else { ?>
                                    <select name="rol" id="rol_empleado" class="form-control" onchange="crmToggleAreaEdit();">
                                        <?php foreach($roles as $clave=>$titulo) { ?>
                                        <option value="<?php echo htmlspecialchars($clave); ?>" <?php if($empleado['rol']==$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                    <?php } ?>
                                </div>
                                <div class="form-group" id="caja_area_edit">
                                    <label>Área principal</label>
                                    <?php if($es_propio || $empleado['rol']=='superadmin') { ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($empleado['area']!='' ? (isset(acceso_etiquetas_area()[$empleado['area']]) ? acceso_etiquetas_area()[$empleado['area']] : $empleado['area']) : 'Sin área'); ?>" readonly/>
                                    <input type="hidden" name="area" value="<?php echo htmlspecialchars(isset($empleado['area']) ? $empleado['area'] : ''); ?>"/>
                                    <p class="muted">No podés modificar tu propia área principal.</p>
                                    <?php } else { ?>
                                    <select name="area" id="area_empleado" class="form-control">
                                        <option value="">Sin área</option>
                                        <?php foreach($areas_extra as $clave=>$titulo) { ?>
                                        <option value="<?php echo htmlspecialchars($clave); ?>" <?php if($empleado['area']==$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p class="muted">Solo para Colaborador o Coordinador de área. Presidencia y Coordinador general quedan sin área.</p>
                                    <?php } ?>
                                </div>
                                <div class="form-actions">
                                    <a href="empleados.php" class="btn btn-white">Cancelar</a>
                                    <input type="submit" value="Guardar cambios" name="guardar" class="btn btn-primary">
                                </div>
                        </form>
                        </div>
                        </div>

                        <div class="grid simple">
                        <div class="grid-title no-border"><h4>Roles / áreas adicionales</h4></div>
                        <div class="grid-body no-border">
                            <p class="muted">RRHH carga el área y rol principales. Sistemas administra permisos extras: si te dan otra área, quedás vinculado a ella.</p>
                            <?php $roles_activos=crm_roles_adicionales_de(intval($empleado['id'])); ?>
                            <?php if(count($roles_activos)==0) { ?>
                            <p class="muted">No tiene roles adicionales.</p>
                            <?php } else { ?>
                            <table class="table">
                                <thead><tr><th>Rol</th><th>Área</th><th>Desde</th><?php if($puede_extras) { ?><th></th><?php } ?></tr></thead>
                                <tbody>
                                <?php foreach($roles_activos as $rx) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rx['rol_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($rx['area_nombre']!='' ? $rx['area_nombre'] : '—'); ?></td>
                                        <td><?php echo htmlspecialchars($rx['fecha_desde']); ?></td>
                                        <?php if($puede_extras) { ?>
                                        <td>
                                            <button type="button" class="btn btn-white btn-xs" onclick="confirmarQuitarRolExtra(<?php echo intval($rx['id']); ?>, '<?php echo htmlspecialchars(addslashes(trim($empleado['nombres'].' '.$empleado['apellido']))); ?>', '<?php echo htmlspecialchars(addslashes($rx['rol_nombre'])); ?>', '<?php echo htmlspecialchars(addslashes($rx['area_nombre']!='' ? $rx['area_nombre'] : '—')); ?>');">Quitar</button>
                                        </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                            <?php } ?>
                            <?php if($puede_extras) { ?>
                            <form method="post" style="margin-top:12px;">
                                <div class="form-group">
                                    <label>Agregar rol</label>
                                    <select name="rol_extra" class="form-control">
                                        <?php foreach($roles_extra_ok as $k=>$t) { ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Área (obligatoria para colaborador/coordinador; vacía si sumás Coordinador general)</label>
                                    <select name="area_extra" class="form-control">
                                        <option value="">Sin área / Coordinador general</option>
                                        <?php foreach($areas_extra as $clave=>$titulo) { ?>
                                        <option value="<?php echo htmlspecialchars($clave); ?>"><?php echo htmlspecialchars($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <p class="muted">Presidencia no se asigna como adicional. Coordinador general sí puede sumarse a alguien de un área.</p>
                                <button type="submit" name="agregar_rol_extra" value="1" class="btn btn-primary">Agregar rol adicional</button>
                            </form>
                            <?php } else { ?>
                            <p class="muted">No tenés permisos para gestionar roles adicionales.</p>
                            <?php } ?>
                        </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
 </div>

<div class="modal fade" id="modalQuitarRolExtra" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Confirmar eliminación de rol adicional</h4>
      </div>
      <div class="modal-body">
        <p>¿Estás seguro de que deseas quitar este rol adicional?</p>
        <p style="margin-bottom:6px;"><strong>Colaborador:</strong> <span id="modalQuitarNombre"></span></p>
        <p style="margin-bottom:0;"><strong>Rol / Área afectada:</strong> <span id="modalQuitarRolArea"></span></p>
      </div>
      <div class="modal-footer">
        <form method="post" id="formQuitarRolExtra" style="display:inline;">
          <input type="hidden" name="id_rol_extra" id="modalQuitarIdRolExtra" value="">
          <button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button>
          <button type="submit" name="quitar_rol_extra" value="1" class="btn btn-danger">Sí, quitar rol</button>
        </form>
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
<script type="text/javascript">
function confirmarQuitarRolExtra(idRolExtra, nombreColaborador, rolNombre, areaNombre) {
    document.getElementById('modalQuitarIdRolExtra').value = idRolExtra;
    document.getElementById('modalQuitarNombre').textContent = nombreColaborador;
    document.getElementById('modalQuitarRolArea').textContent = rolNombre + (areaNombre && areaNombre !== '—' ? ' (' + areaNombre + ')' : '');
    $('#modalQuitarRolExtra').modal('show');
}
</script>
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script type="text/javascript">
function crmToggleAreaEdit(){
	var rol=document.getElementById('rol_empleado');
	var caja=document.getElementById('caja_area_edit');
	var sel=document.getElementById('area_empleado');
	if(!rol || !caja || !sel){ return; }
	var necesita=(rol.value==='empleado' || rol.value==='coordinador');
	caja.style.display=necesita ? 'block' : 'none';
	if(!necesita){ sel.value=''; }
}
crmToggleAreaEdit();
</script>
</body>
</html>
