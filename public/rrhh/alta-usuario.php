<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('rrhh');
acceso_exigir_gestion('rrhh');

// Alta: 1) rol  2) area solo si empleado/coordinador  3) usuario
// Extras / permisos adicionales: Sistemas.

$roles=acceso_roles_para_alta();
$areas_operativas=acceso_etiquetas_area_asignables('empleado');
$menu_activo='alta';
$rol_sel=isset($_POST['rol']) ? $_POST['rol'] : 'empleado';
if(!isset($roles[$rol_sel]))
{
	$rol_sel='empleado';
}

if(isset($_POST['alta']))
{
	$rol_post=isset($_POST['rol']) ? $_POST['rol'] : '';
	$area_post='';
	if($rol_post==='empleado' || $rol_post==='coordinador')
	{
		$area_post=isset($_POST['area']) ? $_POST['area'] : '';
	}
	$msg=acceso_alta_empleado(array(
		'nombres'=>isset($_POST['nombres']) ? $_POST['nombres'] : '',
		'apellido'=>isset($_POST['apellido']) ? $_POST['apellido'] : '',
		'cuil'=>isset($_POST['cuil']) ? $_POST['cuil'] : '',
		'dni'=>isset($_POST['dni']) ? $_POST['dni'] : '',
		'correo'=>isset($_POST['correo']) ? $_POST['correo'] : '',
		'telefono'=>isset($_POST['telefono']) ? $_POST['telefono'] : '',
		'telefono_alt'=>isset($_POST['telefono_alt']) ? $_POST['telefono_alt'] : '',
		'direccion'=>isset($_POST['direccion']) ? $_POST['direccion'] : '',
		'fecha_nacimiento'=>isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : '',
		'fecha_ingreso'=>isset($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : date('Y-m-d'),
		'puesto'=>isset($_POST['puesto']) ? $_POST['puesto'] : '',
		'tipo_vinculo'=>isset($_POST['tipo_vinculo']) ? $_POST['tipo_vinculo'] : '',
		'estado_laboral'=>isset($_POST['estado_laboral']) ? $_POST['estado_laboral'] : 'activo',
		'modalidad'=>isset($_POST['modalidad']) ? $_POST['modalidad'] : '',
		'condicion_fiscal'=>isset($_POST['condicion_fiscal']) ? $_POST['condicion_fiscal'] : '',
		'usuario'=>isset($_POST['usuario']) ? $_POST['usuario'] : '',
		'area'=>$area_post,
		'rol'=>$rol_post
	));
	if($msg=='')
	{
		$_SESSION['msgrrhh']='Empleado dado de alta correctamente.';
		echo "<script>window.location.href='empleados.php'</script>";
		exit();
	}
	$_SESSION['msgrrhh']=$msg;
	$rol_sel=$rol_post;
}
$mostrar_area=($rol_sel==='empleado' || $rol_sel==='coordinador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Recursos humanos - Alta de empleado</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<script type="text/javascript">
function crmToggleAreaPorRol()
{
	var rol=document.getElementById('rol_empleado');
	var caja=document.getElementById('caja_area');
	var sel=document.getElementById('area_empleado');
	if(!rol || !caja || !sel) { return; }
	var necesita=(rol.value==='empleado' || rol.value==='coordinador');
	caja.style.display=necesita ? 'block' : 'none';
	sel.required=necesita;
	if(!necesita) { sel.value=''; }
}
function valid()
{
	var f=document.form1;
	if(f.nombres.value==='') { alert('Falta el nombre.'); f.nombres.focus(); return false; }
	if(f.apellido.value==='') { alert('Falta el apellido.'); f.apellido.focus(); return false; }
	if(f.rol.value==='') { alert('Elegí el rol.'); f.rol.focus(); return false; }
	if((f.rol.value==='empleado' || f.rol.value==='coordinador') && f.area.value==='')
	{
		alert('Elegí el área para ese rol.');
		f.area.focus();
		return false;
	}
	if(f.usuario.value==='') { alert('Falta el usuario.'); f.usuario.focus(); return false; }
	if(f.cuil.value.replace(/\D/g,'').length!=11) { alert('El CUIL tiene que tener 11 números.'); f.cuil.focus(); return false; }
	if(f.dni.value.replace(/\D/g,'').length<6) { alert('Falta el DNI (mínimo 6 números). Se usa como clave.'); f.dni.focus(); return false; }
	if(f.correo.value==='') { alert('Falta el correo.'); f.correo.focus(); return false; }
	return true;
}
</script>
</head>
<body class="" onload="crmToggleAreaPorRol();">
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
                    <li><a href="#" class="active">Recursos humanos / Alta de empleado</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title"><h3>Alta de empleado</h3></div>
                <p>Primero elegí el rol. El área solo aplica a Empleado o Coordinador de área. Presidencia y Coordinador general van sin área. Los permisos extras los carga Sistemas.</p>

                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                        <div class="grid-title no-border"><h4>Datos del empleado</h4></div>
                        <div class="grid-body no-border">
                        <form name="form1" method="post" action="" accept-charset="UTF-8" onSubmit="return valid();">
                                <?php if(isset($_SESSION['msgrrhh']) && $_SESSION['msgrrhh']!='') { ?>
                                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgrrhh']); $_SESSION['msgrrhh']=''; ?></div>
                                <?php } ?>

                                <div class="form-group">
                                    <label>Rol</label>
                                    <select name="rol" id="rol_empleado" class="form-control" required onchange="crmToggleAreaPorRol();">
                                        <?php foreach($roles as $clave=>$titulo) { ?>
                                        <option value="<?php echo htmlspecialchars($clave); ?>" <?php if($rol_sel===$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group" id="caja_area" style="<?php echo $mostrar_area ? '' : 'display:none;'; ?>">
                                    <label>Área</label>
                                    <select name="area" id="area_empleado" class="form-control" <?php if($mostrar_area) echo 'required'; ?>>
                                        <option value="">Elegir área</option>
                                        <?php foreach($areas_operativas as $clave=>$titulo) { ?>
                                        <option value="<?php echo htmlspecialchars($clave); ?>" <?php if(crm_campo('area')==$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Usuario</label>
                                    <input type="text" name="usuario" id="usuario" required class="form-control" value="<?php echo htmlspecialchars(crm_campo('usuario')); ?>"/>
                                </div>

                                <hr>
                                <div class="form-group">
                                    <label>Nombres</label>
                                    <input type="text" name="nombres" id="nombres" required class="form-control" value="<?php echo htmlspecialchars(crm_campo('nombres')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Apellido</label>
                                    <input type="text" name="apellido" id="apellido" required class="form-control" value="<?php echo htmlspecialchars(crm_campo('apellido')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>CUIL (11 números)</label>
                                    <input type="text" name="cuil" required class="form-control" value="<?php echo htmlspecialchars(crm_campo('cuil')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>DNI</label>
                                    <input type="text" name="dni" required class="form-control" value="<?php echo htmlspecialchars(crm_campo('dni')); ?>"/>
                                    <p class="muted">La clave inicial es el DNI.</p>
                                </div>
                                <div class="form-group">
                                    <label>Correo</label>
                                    <input type="email" name="correo" required class="form-control" value="<?php echo htmlspecialchars(crm_campo('correo')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars(crm_campo('telefono')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono alternativo</label>
                                    <input type="text" name="telefono_alt" class="form-control" value="<?php echo htmlspecialchars(crm_campo('telefono_alt')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars(crm_campo('direccion')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de nacimiento</label>
                                    <input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo htmlspecialchars(crm_campo('fecha_nacimiento')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de ingreso</label>
                                    <input type="date" name="fecha_ingreso" value="<?php echo htmlspecialchars(crm_campo('fecha_ingreso', date('Y-m-d'))); ?>" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Puesto / función</label>
                                    <input type="text" name="puesto" class="form-control" value="<?php echo htmlspecialchars(crm_campo('puesto')); ?>"/>
                                </div>
                                <div class="form-group">
                                    <label>Tipo de vínculo</label>
                                    <select name="tipo_vinculo" class="form-control">
                                        <?php
                                        $vinc=array(''=>'—','monotributo'=>'Monotributo','contrato'=>'Contrato','planta'=>'Planta','pasantia'=>'Pasantía','otro'=>'Otro');
                                        $sel=crm_campo('tipo_vinculo');
                                        foreach($vinc as $k=>$t) { ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php if($sel===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Estado laboral</label>
                                    <select name="estado_laboral" class="form-control">
                                        <?php foreach(array('activo'=>'Activo','inactivo'=>'Inactivo','licencia'=>'Licencia') as $k=>$t) { ?>
                                        <option value="<?php echo $k; ?>" <?php if(crm_campo('estado_laboral','activo')===$k) echo 'selected'; ?>><?php echo $t; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Modalidad</label>
                                    <select name="modalidad" class="form-control">
                                        <?php foreach(array(''=>'—','presencial'=>'Presencial','hibrida'=>'Híbrida','remota'=>'Remota') as $k=>$t) { ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php if(crm_campo('modalidad')===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Condición fiscal</label>
                                    <select name="condicion_fiscal" class="form-control">
                                        <?php
                                        $cf_ops=acceso_condiciones_fiscales();
                                        $cf_sel=crm_campo('condicion_fiscal');
                                        foreach($cf_ops as $k=>$t) { ?>
                                        <option value="<?php echo htmlspecialchars($k); ?>" <?php if($cf_sel===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-actions">
                                    <a href="empleados.php" class="btn btn-white">Cancelar</a>
                                    <input type="submit" value="Dar de alta" name="alta" class="btn btn-primary">
                                </div>
                        </form>
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
</body>
</html>
