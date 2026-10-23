<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('rrhh');
acceso_exigir_gestion('rrhh');
require_once BASE_PATH.'/includes/rrhh/fotos.php';
require_once BASE_PATH.'/includes/rrhh/legajo.php';

// Modificar un empleado en la tabla empleado.

$menu_activo='empleados';
$roles=acceso_roles_para_alta();
$usuario=isset($_GET['usuario']) ? trim($_GET['usuario']) : '';
$empleado=acceso_empleado_por_usuario($usuario);

if($empleado==null || $empleado['rol']=='superadmin')
{
	header("Location: empleados.php");
	exit();
}

$id_empleado=intval($empleado['id']);
$url_editar='editar-empleado.php?usuario='.rawurlencode((string)$empleado['usuario']);
$csrf_foto=function_exists('crm_tarea_extra_csrf_token') ? crm_tarea_extra_csrf_token() : '';
$csrf_legajo=$csrf_foto;
$csrf_admin=$csrf_foto;
$csrf_admin_post=isset($_POST['csrf_tarea_extra']) && is_string($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : '';
$csrf_admin_valido=false;
if(isset($_POST['guardar']) || isset($_POST['agregar_rol_extra']) || isset($_POST['quitar_rol_extra']))
{
	$csrf_admin_valido=function_exists('crm_tarea_extra_csrf_valido') && crm_tarea_extra_csrf_valido($csrf_admin_post);
}
if(isset($_POST['accion_foto']) || isset($_POST['accion_legajo']))
{
	$accion_foto=isset($_POST['accion_foto']) && is_string($_POST['accion_foto']) ? $_POST['accion_foto'] : '';
	$accion_legajo=isset($_POST['accion_legajo']) && is_string($_POST['accion_legajo']) ? $_POST['accion_legajo'] : '';
	$token=isset($_POST['csrf_tarea_extra']) && is_string($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : '';
	$token_valido=function_exists('crm_tarea_extra_csrf_valido') && crm_tarea_extra_csrf_valido($token);
	if($accion_foto!=='' || $accion_legajo!=='')
	{
		if(!$token_valido)
		{
			if($accion_legajo!=='') { $_SESSION['rrhh_legajo_mensaje']='La sesión expiró. Actualizá la página e intentá nuevamente.'; $_SESSION['rrhh_legajo_exito']=0; }
			else { $_SESSION['rrhh_foto_mensaje']='La sesión expiró. Actualizá la página e intentá nuevamente.'; $_SESSION['rrhh_foto_exito']=0; }
		}
		else if($accion_foto==='subir')
		{
			$resultado=crm_rrhh_foto_subir($id_empleado, isset($_FILES['foto_empleado']) ? $_FILES['foto_empleado'] : array());
			$_SESSION['rrhh_foto_mensaje']=$resultado[1];
			$_SESSION['rrhh_foto_exito']=$resultado[0] ? 1 : 0;
		}
		else if($accion_foto==='eliminar')
		{
			$resultado=crm_rrhh_foto_eliminar($id_empleado);
			$_SESSION['rrhh_foto_mensaje']=$resultado[1];
			$_SESSION['rrhh_foto_exito']=$resultado[0] ? 1 : 0;
		}
		else if($accion_legajo==='agregar')
		{
			$resultado=crm_rrhh_legajo_agregar($id_empleado, isset($_POST['categoria_documento']) && is_string($_POST['categoria_documento']) ? $_POST['categoria_documento'] : '', isset($_POST['descripcion_documento']) && is_string($_POST['descripcion_documento']) ? $_POST['descripcion_documento'] : '', isset($_FILES['documento_pdf']) ? $_FILES['documento_pdf'] : array());
			$_SESSION['rrhh_legajo_mensaje']=$resultado[1];
			$_SESSION['rrhh_legajo_exito']=$resultado[0] ? 1 : 0;
		}
		else if($accion_legajo==='eliminar')
		{
			$id_documento=isset($_POST['id_documento']) && is_scalar($_POST['id_documento']) && ctype_digit((string)$_POST['id_documento']) ? intval($_POST['id_documento']) : 0;
			$resultado=crm_rrhh_legajo_eliminar($id_empleado, $id_documento);
			$_SESSION['rrhh_legajo_mensaje']=$resultado[1];
			$_SESSION['rrhh_legajo_exito']=$resultado[0] ? 1 : 0;
		}
		else
		{
			if($accion_legajo!=='') { $_SESSION['rrhh_legajo_mensaje']='La operación solicitada no es válida.'; $_SESSION['rrhh_legajo_exito']=0; }
			else { $_SESSION['rrhh_foto_mensaje']='La operación solicitada no es válida.'; $_SESSION['rrhh_foto_exito']=0; }
		}
		header('Location: '.$url_editar);
		exit();
	}
}

$foto_columna_disponible=crm_rrhh_foto_columna_disponible();
$foto_key=$foto_columna_disponible ? crm_rrhh_foto_key_por_empleado($id_empleado) : '';
$foto_url=($foto_key!=='' && crm_rrhh_foto_ruta_por_key($foto_key)!=='') ? url_crm('foto-empleado.php?id='.$id_empleado.'&v='.rawurlencode($foto_key)) : '';
$puede_gestionar_foto=crm_rrhh_foto_autorizado($id_empleado) && $foto_columna_disponible;
$foto_mensaje=isset($_SESSION['rrhh_foto_mensaje']) ? (string)$_SESSION['rrhh_foto_mensaje'] : '';
$foto_exito=!empty($_SESSION['rrhh_foto_exito']);
unset($_SESSION['rrhh_foto_mensaje'], $_SESSION['rrhh_foto_exito']);
$legajo_disponible=crm_rrhh_legajo_tabla_disponible();
$puede_gestionar_legajo=crm_rrhh_legajo_puede_gestionar($id_empleado);
$legajo_categorias=crm_rrhh_legajo_categorias();
$legajo_documentos=$legajo_disponible && $puede_gestionar_legajo ? crm_rrhh_legajo_listar($id_empleado) : array();
$legajo_error_consulta=($legajo_documentos===false);
if($legajo_error_consulta) { $legajo_documentos=array(); }
$legajo_mensaje=isset($_SESSION['rrhh_legajo_mensaje']) ? (string)$_SESSION['rrhh_legajo_mensaje'] : '';
$legajo_exito=!empty($_SESSION['rrhh_legajo_exito']);
unset($_SESSION['rrhh_legajo_mensaje'], $_SESSION['rrhh_legajo_exito']);
$iniciales='';
foreach(array(isset($empleado['nombres']) ? $empleado['nombres'] : '', isset($empleado['apellido']) ? $empleado['apellido'] : '') as $parte_nombre) { $parte_nombre=trim((string)$parte_nombre); if($parte_nombre!=='') { $iniciales.=function_exists('mb_substr') ? mb_substr($parte_nombre,0,1,'UTF-8') : substr($parte_nombre,0,1); } }
$iniciales=function_exists('mb_strtoupper') ? mb_strtoupper($iniciales,'UTF-8') : strtoupper($iniciales);
if($iniciales==='') { $iniciales='E'; }

$es_empleado_comun=acceso_es_empleado();
$es_super=acceso_es_super();
$es_coord_general=acceso_es_coord_general();
$es_coord_area=acceso_es_coord_de_area();

$es_gestion_sistemas=($es_super || $es_coord_general || ((acceso_area()=='sistemas' || acceso_area_pantalla()=='sistemas') && $es_coord_area));
$es_gestion_rrhh=($es_super || $es_coord_general || ((acceso_area()=='rrhh' || acceso_area_pantalla()=='rrhh') && $es_coord_area));
$puede_cambiar_rol_area=!$es_propio && $empleado['rol']!=='superadmin' && ($es_super || $es_coord_general || $es_gestion_rrhh);
if(($empleado['rol']==='presidencia' || $empleado['rol']==='coordinador_general') && !$es_super && !$es_coord_general)
{
	$puede_cambiar_rol_area=false;
}
$puede_asignar_rol_protegido=($es_super || $es_coord_general);
$roles_mostrados=$roles;
if(!$puede_asignar_rol_protegido)
{
	unset($roles_mostrados['presidencia'], $roles_mostrados['coordinador_general']);
}

$puede_extras=($es_super || $es_coord_general || $es_gestion_sistemas || $es_gestion_rrhh) && !$es_empleado_comun;

// Presidencia no admite extras.
if(isset($empleado['rol']) && $empleado['rol']==='presidencia')
{
	$puede_extras=false;
}

$id_logueado=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : (isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : 0);
$es_propio=($id_logueado>0 && intval($empleado['id'])===$id_logueado);

function crm_rrhh_actualizar_roles_adicionales_sesion($id_empleado)
{
	$id_empleado=intval($id_empleado);
	$id_sesion=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
	if($id_empleado<=0 || $id_empleado!==$id_sesion || !function_exists('acceso_cargar_roles_adicionales'))
	{
		return;
	}

	$extras=acceso_cargar_roles_adicionales($id_sesion);
	$_SESSION['roles_adicionales']=$extras;
	$area_activa=function_exists('acceso_area_activa') ? acceso_area_activa() : (isset($_SESSION['area_activa']) ? $_SESSION['area_activa'] : '');
	$area_base=isset($_SESSION['area_base']) ? trim((string)$_SESSION['area_base']) : '';
	$rol_base=isset($_SESSION['rol_base']) ? trim((string)$_SESSION['rol_base']) : 'empleado';
	$rol_area='';

	if($rol_base==='superadmin' || $rol_base==='admin')
	{
		$rol_area='superadmin';
	}
	else if($rol_base==='coordinador_general')
	{
		$rol_area='coordinador_general';
	}
	else
	{
		foreach($extras as $extra)
		{
			$rol_extra=isset($extra['rol']) ? trim((string)$extra['rol']) : '';
			$area_extra=isset($extra['area']) ? trim((string)$extra['area']) : '';
			if($rol_extra==='coordinador_general' && $area_extra==='')
			{
				$rol_area='coordinador_general';
				break;
			}
		}
		if($rol_area==='' && $area_activa!=='' && $area_activa===$area_base)
		{
			$rol_area=$rol_base;
		}
		if($area_activa!=='')
		{
			foreach($extras as $extra)
			{
				if(isset($extra['area']) && trim((string)$extra['area'])===$area_activa)
				{
					$rol_extra=isset($extra['rol']) ? trim((string)$extra['rol']) : 'empleado';
					if($rol_area==='' || ($rol_extra==='coordinador' && $rol_area!=='coordinador'))
					{
						$rol_area=$rol_extra;
					}
					if($rol_area==='coordinador') { break; }
				}
			}
		}
	}

	if($rol_area==='')
	{
		$area_activa=$area_base;
		$rol_area=$rol_base;
	}
	$_SESSION['area_activa']=$area_activa;
	$_SESSION['area_usuario']=$area_activa;
	$_SESSION['rol_area']=$rol_area;
	$_SESSION['tareas_rol']=in_array($rol_area,array('coordinador','coordinador_general','superadmin'),true) ? 'coordinador' : $rol_area;
}

$roles_extra_ok=function_exists('acceso_roles_extras_permitidos') ? acceso_roles_extras_permitidos() : array(
	'empleado'=>'Colaborador',
	'coordinador'=>'Coordinador de área',
	'coordinador_general'=>'Coordinador general'
);
$areas_extra=acceso_etiquetas_area_asignables('empleado');

if(isset($_POST['agregar_rol_extra']))
{
	$rol_extra=isset($_POST['rol_extra']) ? trim($_POST['rol_extra']) : '';
	if(!$csrf_admin_valido)
	{
		$_SESSION['msgrrhh']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
	}
	else if(!$puede_extras)
	{
		$_SESSION['msgrrhh']='No tenés permiso para asignar roles o áreas adicionales.';
	}
	else if($empleado['rol']==='superadmin')
	{
		$_SESSION['msgrrhh']='No se pueden modificar roles adicionales a un superadmin.';
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
		if($msg==='' && $es_propio)
		{
			crm_rrhh_actualizar_roles_adicionales_sesion(intval($empleado['id']));
		}
	}
	header('Location: editar-empleado.php?usuario='.rawurlencode($empleado['usuario']));
	exit();
}
if(isset($_POST['quitar_rol_extra']))
{
	if(!$csrf_admin_valido)
	{
		$_SESSION['msgrrhh']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
	}
	else if(!$puede_extras)
	{
		$_SESSION['msgrrhh']='No tenés permiso para quitar roles adicionales.';
	}
	else if($empleado['rol']==='superadmin')
	{
		$_SESSION['msgrrhh']='No se pueden modificar roles adicionales a un superadmin.';
	}
	else
	{
		$msg=crm_rol_adicional_quitar(isset($_POST['id_rol_extra']) ? intval($_POST['id_rol_extra']) : 0, intval($empleado['id']));
		$_SESSION['msgrrhh']=($msg==='') ? 'Rol adicional quitado.' : $msg;
		if($msg==='' && $es_propio)
		{
			crm_rrhh_actualizar_roles_adicionales_sesion(intval($empleado['id']));
		}
	}
	header('Location: editar-empleado.php?usuario='.rawurlencode($empleado['usuario']));
	exit();
}

if(isset($_POST['guardar']) && !$csrf_admin_valido)
{
	$_SESSION['msgrrhh']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
}
else if(isset($_POST['guardar']))
{
	$nombres=trim(isset($_POST['nombres']) ? $_POST['nombres'] : '');
	$apellido=trim(isset($_POST['apellido']) ? $_POST['apellido'] : '');
	$correo=trim(isset($_POST['correo']) ? $_POST['correo'] : '');

	if(!$puede_cambiar_rol_area)
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
	else if(!$es_propio && $rol!==$empleado['rol'] && in_array($rol,array('presidencia','coordinador_general'),true) && !$puede_asignar_rol_protegido)
	{
		$_SESSION['msgrrhh']="No tenés autorización para asignar ese rol protegido.";
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
			$cambio_rol_area=($datos['rol']!==$empleado['rol'] || (isset($datos['area']) ? $datos['area'] : '')!==(isset($empleado['area']) ? $empleado['area'] : ''));
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
				$_SESSION['msgrrhh']=$cambio_rol_area
					? "Colaborador actualizado. El cambio de rol y área se aplicará al renovar su sesión."
					: "Colaborador actualizado.";
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
<style>
.rrhh-edit-page { margin:0 auto; }
.rrhh-edit-inner { max-width:1280px; margin:0 auto; }
.rrhh-edit-section { margin:0 0 16px; padding:16px 18px 4px; border:1px solid #e2e9f1; border-radius:9px; background:#fff; }
.rrhh-edit-section h4 { margin:0 0 13px; color:#263b53; font-size:15px; font-weight:600; }
.rrhh-edit-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); column-gap:22px; }
.rrhh-edit-fields .form-group { min-width:0; margin-bottom:10px; }
.rrhh-edit-fields .form-group-full { grid-column:1 / -1; }
.rrhh-edit-fields .rrhh-form-group-wide { grid-column:1 / -1; }
.rrhh-edit-actions { display:flex; justify-content:flex-end; gap:8px; padding:4px 0 2px; }
.rrhh-access-layout { display:grid; grid-template-columns:minmax(210px, .72fr) minmax(0, 1.5fr); gap:24px; align-items:start; }
.rrhh-access-photo { min-width:0; padding:12px; border:1px solid #e5ebf2; border-radius:8px; background:#f8fbff; text-align:center; }
.rrhh-access-photo-label { margin:0 0 10px; color:#63778d; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.35px; }
.rrhh-access-photo .rrhh-photo-avatar { margin:0 auto 12px; }
.rrhh-access-fields { display:grid; grid-template-columns:1fr; min-width:0; }
.rrhh-access-fields .form-group { margin-bottom:10px; }
.rrhh-photo-layout { display:flex; flex-direction:column; align-items:center; gap:8px; }
.rrhh-photo-avatar { display:flex; flex:0 0 84px; width:84px; height:84px; align-items:center; justify-content:center; overflow:hidden; border:3px solid #fff; border-radius:50%; background:#dceaff; box-shadow:0 2px 8px rgba(33,85,145,.14); color:#2863b8; font-size:24px; font-weight:700; }
.rrhh-photo-avatar img { width:100%; height:100%; object-fit:cover; }
.rrhh-photo-controls { min-width:0; flex:1; }
.rrhh-photo-actions,.rrhh-edit-doc-actions { display:flex; flex-wrap:wrap; gap:7px; align-items:center; }
.rrhh-edit-doc-actions { flex-wrap:nowrap; gap:4px; }
.rrhh-photo-actions { justify-content:center; }
.rrhh-photo-actions form,.rrhh-edit-doc-actions form { margin:0; }
.rrhh-photo-form,.rrhh-document-form { margin-top:10px; padding:12px; border:1px solid #dce5ef; border-radius:8px; background:#f8fbff; text-align:left; }
.rrhh-photo-form input[type=file] { display:block; width:100%; max-width:100%; }
.rrhh-photo-form[hidden],.rrhh-document-form[hidden] { display:none !important; }
.rrhh-document-table-wrap { width:100%; overflow-x:visible; border:1px solid #e4eaf1; border-radius:8px; }
.rrhh-document-table { width:100%; margin:0; table-layout:fixed; }
.rrhh-document-table th { white-space:nowrap; background:#f5f8fc; color:#53677d; font-size:11px; text-transform:uppercase; }
.rrhh-document-table th:nth-child(1) { width:11%; }
.rrhh-document-table th:nth-child(2) { width:14%; }
.rrhh-document-table th:nth-child(3) { width:19%; }
.rrhh-document-table th:nth-child(4) { width:21%; }
.rrhh-document-table th:nth-child(5) { width:35%; }
.rrhh-document-table td { vertical-align:middle !important; font-size:12px; overflow-wrap:anywhere; }
.rrhh-document-name { overflow-wrap:anywhere; font-weight:600; }
.rrhh-document-table .rrhh-edit-doc-actions { gap:4px; }
.rrhh-edit-doc-actions form { flex:0 0 auto; }
.rrhh-edit-doc-actions .btn { padding:3px 5px; font-size:11px; white-space:nowrap; }
.rrhh-document-form-fields { display:grid; grid-template-columns:minmax(170px, .8fr) minmax(0, 1.4fr); gap:0 14px; }
.rrhh-document-form-fields .form-group:last-child { grid-column:1 / -1; }
.rrhh-legajo-heading { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
.rrhh-legajo-heading h4 { margin:0; }
.rrhh-document-empty { padding:14px; border:1px dashed #d8e1eb; border-radius:8px; background:#f8fafc; color:#6c7d90; text-align:center; }
.rrhh-document-alert { margin:0 0 12px; padding:9px 12px; font-size:12px; }
.rrhh-extra-add-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
@media (max-width:767px) {
	.rrhh-edit-section { padding:15px 14px 4px; }
	.rrhh-edit-fields,.rrhh-access-layout,.rrhh-extra-add-fields,.rrhh-document-form-fields { grid-template-columns:1fr; }
	.rrhh-edit-fields .form-group-full,.rrhh-edit-fields .rrhh-form-group-wide,.rrhh-document-form-fields .form-group:last-child { grid-column:auto; }
	.rrhh-edit-actions { justify-content:flex-start; flex-wrap:wrap; }
	.rrhh-legajo-heading { align-items:flex-start; flex-wrap:wrap; }
	.rrhh-access-photo { display:grid; grid-template-columns:88px minmax(0,1fr); align-items:center; gap:10px 14px; text-align:left; }
	.rrhh-access-photo-label { grid-column:1 / -1; margin-bottom:0; }
	.rrhh-access-photo .rrhh-photo-avatar { margin:0; }
	.rrhh-photo-actions { justify-content:flex-start; }
	.rrhh-document-table-wrap { overflow:visible; border:0; }
	.rrhh-document-table,.rrhh-document-table tbody,.rrhh-document-table tr,.rrhh-document-table td { display:block; width:100%; }
	.rrhh-document-table thead { display:none; }
	.rrhh-document-table tr { margin:0 0 10px; padding:8px 10px; border:1px solid #e4eaf1; border-radius:8px; background:#fff; }
	.rrhh-document-table td { display:grid; grid-template-columns:minmax(86px,30%) minmax(0,1fr); gap:8px; padding:6px 3px !important; border:0 !important; }
	.rrhh-document-table td:before { content:attr(data-label); color:#53677d; font-size:11px; font-weight:600; }
	.rrhh-document-table td:last-child { display:block; }
	.rrhh-document-table td:last-child:before { display:block; margin-bottom:6px; }
	.rrhh-document-table .rrhh-edit-doc-actions { flex-wrap:wrap; }
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
            <div class="content rrhh-edit-page">
                <ul class="breadcrumb">
                    <li>
                        <p>ESTÁS AQUÍ</p>
                    </li>
                    <li><a href="empleados.php">Colaboradores</a></li>
                    <li><a href="#" class="active">Editar colaborador</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="rrhh-edit-inner">
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
                        <form id="rrhh-employee-data-form" method="post" action="<?php echo htmlspecialchars($url_editar, ENT_QUOTES, 'UTF-8'); ?>" accept-charset="UTF-8"><input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_admin, ENT_QUOTES, 'UTF-8'); ?>" /></form>
                                <?php if(isset($_SESSION['msgrrhh']) && $_SESSION['msgrrhh']!='') { ?>
                                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgrrhh']); $_SESSION['msgrrhh']=''; ?></div>
                                <?php } ?>
                                <section class="rrhh-edit-section">
                                    <h4><i class="fa fa-key"></i> Acceso y asignación</h4>
                                    <div class="rrhh-access-layout">
                                        <div class="rrhh-access-photo">
                                            <div class="rrhh-access-photo-label">Fotografía del colaborador</div>
                                            <div class="rrhh-photo-avatar" aria-label="Fotografía de <?php echo htmlspecialchars(trim($empleado['nombres'].' '.$empleado['apellido']), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php if($foto_url!=='') { ?><img src="<?php echo htmlspecialchars($foto_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Fotografía del colaborador" /><?php } else { echo htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8'); } ?>
                                            </div>
                                            <div class="rrhh-photo-controls">
                                                <?php if(!$foto_columna_disponible) { ?>
                                                    <div class="alert alert-info rrhh-document-alert">La gestión de fotografías requiere completar la configuración de base de datos.</div>
                                                <?php } elseif(!$puede_gestionar_foto) { ?>
                                                    <div class="rrhh-document-empty">No tenés autorización para gestionar esta fotografía.</div>
                                                <?php } else { ?>
                                                    <?php if($foto_mensaje!=='') { ?><div class="alert <?php echo $foto_exito ? 'alert-success' : 'alert-warning'; ?> rrhh-document-alert" role="status"><?php echo htmlspecialchars($foto_mensaje, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
                                                    <div class="rrhh-photo-actions">
                                                        <button type="button" class="btn btn-primary btn-sm" id="rrhh-edit-photo-toggle" aria-controls="rrhh-edit-photo-form" aria-expanded="false"><i class="fa fa-camera"></i> <?php echo $foto_key!=='' ? 'Modificar foto' : 'Agregar foto'; ?></button>
                                                        <?php if($foto_key!=='') { ?>
                                                        <form method="post" action="<?php echo htmlspecialchars($url_editar, ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('¿Quitar la fotografía y volver al avatar predeterminado?');">
                                                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_foto, ENT_QUOTES, 'UTF-8'); ?>" />
                                                            <input type="hidden" name="accion_foto" value="eliminar" />
                                                            <button type="submit" class="btn btn-white btn-sm"><i class="fa fa-trash"></i> Quitar foto</button>
                                                        </form>
                                                        <?php } ?>
                                                    </div>
                                                    <form id="rrhh-edit-photo-form" class="rrhh-photo-form" method="post" action="<?php echo htmlspecialchars($url_editar, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" hidden>
                                                        <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_foto, ENT_QUOTES, 'UTF-8'); ?>" />
                                                        <input type="hidden" name="accion_foto" value="subir" />
                                                        <div class="form-group"><label for="rrhh-edit-photo-file">JPG, PNG o WebP · máximo 5 MB</label><input id="rrhh-edit-photo-file" type="file" name="foto_empleado" accept="image/jpeg,image/png,image/webp" required /></div>
                                                        <div class="rrhh-photo-actions"><button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Guardar foto</button><button type="button" class="btn btn-white btn-sm" id="rrhh-edit-photo-cancel">Cancelar</button></div>
                                                    </form>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <div class="rrhh-access-fields">
                                        <div class="form-group">
                                            <label>Usuario</label>
                                            <input type="text" name="usuario_nuevo" form="rrhh-employee-data-form" required class="form-control" value="<?php echo htmlspecialchars($empleado['usuario']); ?>" pattern="[a-zA-Z0-9._\-]{3,40}" title="3 a 40 caracteres: letras, números, . _ -"/>
                                            <p class="muted">Si lo cambiás, esa persona entra con el nuevo usuario.</p>
                                        </div>
                                        <div class="form-group">
                                            <label>Rol principal</label>
                                            <?php if(!$puede_cambiar_rol_area) { ?>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(acceso_mostrar_rol($empleado['rol'])); ?>" readonly/>
                                            <input type="hidden" name="rol" form="rrhh-employee-data-form" value="<?php echo htmlspecialchars($empleado['rol']); ?>"/>
                                            <p class="muted">No podés modificar tu propio rol principal.</p>
                                            <?php } else { ?>
                                            <select name="rol" id="rol_empleado" form="rrhh-employee-data-form" class="form-control" onchange="crmToggleAreaEdit();">
                                                <?php foreach($roles_mostrados as $clave=>$titulo) { ?>
                                                <option value="<?php echo htmlspecialchars($clave); ?>" <?php if($empleado['rol']==$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                                <?php } ?>
                                            </select>
                                            <?php } ?>
                                        </div>
                                        <div class="form-group" id="caja_area_edit">
                                            <label>Área principal</label>
                                            <?php if(!$puede_cambiar_rol_area) { ?>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($empleado['area']!='' ? (isset(acceso_etiquetas_area()[$empleado['area']]) ? acceso_etiquetas_area()[$empleado['area']] : $empleado['area']) : 'Sin área'); ?>" readonly/>
                                            <input type="hidden" name="area" form="rrhh-employee-data-form" value="<?php echo htmlspecialchars(isset($empleado['area']) ? $empleado['area'] : ''); ?>"/>
                                            <p class="muted">No podés modificar tu propia área principal.</p>
                                            <?php } else { ?>
                                            <select name="area" id="area_empleado" form="rrhh-employee-data-form" class="form-control">
                                                <option value="">Sin área</option>
                                                <?php foreach($areas_extra as $clave=>$titulo) { ?>
                                                <option value="<?php echo htmlspecialchars($clave); ?>" <?php if($empleado['area']==$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                                <?php } ?>
                                            </select>
                                            <p class="muted">Solo para Colaborador o Coordinador de área. Presidencia y Coordinador general quedan sin área.</p>
                                            <?php } ?>
                                        </div>
                                        </div>
                                    </div>
                                </section>
                                <section class="rrhh-edit-section">
                                    <h4><i class="fa fa-user"></i> Datos personales</h4>
                                    <div class="rrhh-edit-fields">
                                        <div class="form-group"><label>Nombres</label><input type="text" name="nombres" form="rrhh-employee-data-form" required class="form-control" value="<?php echo htmlspecialchars($empleado['nombres']); ?>"/></div>
                                        <div class="form-group"><label>Apellido</label><input type="text" name="apellido" form="rrhh-employee-data-form" required class="form-control" value="<?php echo htmlspecialchars($empleado['apellido']); ?>"/></div>
                                        <div class="form-group"><label>Correo</label><input type="email" name="correo" form="rrhh-employee-data-form" required class="form-control" value="<?php echo htmlspecialchars($empleado['correo']); ?>"/></div>
                                        <div class="form-group"><label>Teléfono</label><input type="text" name="telefono" form="rrhh-employee-data-form" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['telefono']) ? $empleado['telefono'] : ''); ?>"/></div>
                                        <div class="form-group"><label>Teléfono alternativo</label><input type="text" name="telefono_alt" form="rrhh-employee-data-form" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['telefono_alt']) ? $empleado['telefono_alt'] : ''); ?>"/></div>
                                        <div class="form-group"><label>Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" form="rrhh-employee-data-form" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['fecha_nacimiento']) ? $empleado['fecha_nacimiento'] : ''); ?>"/></div>
                                        <div class="form-group rrhh-form-group-wide"><label>Dirección</label><input type="text" name="direccion" form="rrhh-employee-data-form" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['direccion']) ? $empleado['direccion'] : ''); ?>"/></div>
                                    </div>
                                </section>
                                <section class="rrhh-edit-section">
                                    <h4><i class="fa fa-briefcase"></i> Información laboral</h4>
                                    <div class="rrhh-edit-fields">
                                        <div class="form-group"><label>Puesto / función</label><input type="text" name="puesto" form="rrhh-employee-data-form" class="form-control" value="<?php echo htmlspecialchars(isset($empleado['puesto']) ? $empleado['puesto'] : ''); ?>"/></div>
                                        <div class="form-group">
                                            <label>Tipo de vínculo</label>
                                            <select name="tipo_vinculo" form="rrhh-employee-data-form" class="form-control">
                                                <?php $vinc=array(''=>'—','monotributo'=>'Monotributo','contrato'=>'Contrato','planta'=>'Planta','pasantia'=>'Pasantía','otro'=>'Otro'); $sel=isset($empleado['tipo_vinculo']) ? $empleado['tipo_vinculo'] : ''; foreach($vinc as $k=>$t) { ?>
                                                <option value="<?php echo htmlspecialchars($k); ?>" <?php if($sel===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Estado laboral</label>
                                            <select name="estado_laboral" form="rrhh-employee-data-form" class="form-control">
                                                <?php $el=isset($empleado['estado_laboral']) ? $empleado['estado_laboral'] : 'activo'; foreach(array('activo'=>'Activo','inactivo'=>'Inactivo','licencia'=>'Licencia') as $k=>$t) { ?>
                                                <option value="<?php echo $k; ?>" <?php if($el===$k) echo 'selected'; ?>><?php echo $t; ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Modalidad</label>
                                            <select name="modalidad" form="rrhh-employee-data-form" class="form-control">
                                                <?php $mod=isset($empleado['modalidad']) ? $empleado['modalidad'] : ''; foreach(array(''=>'—','presencial'=>'Presencial','hibrida'=>'Híbrida','remota'=>'Remota') as $k=>$t) { ?>
                                                <option value="<?php echo htmlspecialchars($k); ?>" <?php if($mod===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Condición fiscal</label>
                                            <select name="condicion_fiscal" form="rrhh-employee-data-form" class="form-control">
                                                <?php $cf_ops=acceso_condiciones_fiscales(); $cf_sel=isset($empleado['condicion_fiscal']) ? trim((string)$empleado['condicion_fiscal']) : ''; if($cf_sel!=='' && !isset($cf_ops[$cf_sel])) { $cf_ops[$cf_sel]=$cf_sel; } foreach($cf_ops as $k=>$t) { ?>
                                                <option value="<?php echo htmlspecialchars($k); ?>" <?php if($cf_sel===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </section>
                                <div class="form-actions rrhh-edit-actions">
                                    <a href="empleados.php" class="btn btn-white">Cancelar</a>
                                    <input type="submit" form="rrhh-employee-data-form" value="Guardar cambios" name="guardar" class="btn btn-primary">
                                </div>
                        </div>
                        </div>

                        <section class="rrhh-edit-section rrhh-legajo-section" aria-labelledby="rrhh-docs-title">
                            <div class="rrhh-legajo-heading">
                                <h4 id="rrhh-docs-title"><i class="fa fa-folder-open-o"></i> Legajo digital</h4>
                                <?php if($legajo_disponible && $puede_gestionar_legajo) { ?>
                                <button type="button" class="btn btn-primary btn-sm" id="rrhh-edit-doc-toggle" aria-controls="rrhh-edit-doc-form" aria-expanded="false"><i class="fa fa-plus"></i> Agregar documento</button>
                                <?php } ?>
                            </div>
                            <?php if($legajo_mensaje!=='') { ?><div class="alert <?php echo $legajo_exito ? 'alert-success' : 'alert-warning'; ?> rrhh-document-alert" role="status"><?php echo htmlspecialchars($legajo_mensaje, ENT_QUOTES, 'UTF-8'); ?></div><?php } ?>
                            <?php if(!$legajo_disponible) { ?>
                                <div class="alert alert-info rrhh-document-alert">El legajo digital no está disponible en este momento.</div>
                            <?php } elseif(!$puede_gestionar_legajo) { ?>
                                <div class="rrhh-document-empty">No tenés autorización para gestionar este legajo.</div>
                            <?php } else { ?>
                                <form id="rrhh-edit-doc-form" class="rrhh-document-form" method="post" action="<?php echo htmlspecialchars($url_editar, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" hidden>
                                    <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_legajo, ENT_QUOTES, 'UTF-8'); ?>" />
                                    <input type="hidden" name="accion_legajo" value="agregar" />
                                    <div class="rrhh-document-form-fields">
                                        <div class="form-group"><label for="rrhh-edit-doc-category">Categoría</label><select id="rrhh-edit-doc-category" name="categoria_documento" class="form-control" required><option value="">Seleccioná una categoría</option><?php foreach($legajo_categorias as $categoria_key=>$categoria_nombre) { ?><option value="<?php echo htmlspecialchars($categoria_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($categoria_nombre, ENT_QUOTES, 'UTF-8'); ?></option><?php } ?></select></div>
                                        <div class="form-group"><label for="rrhh-edit-doc-description">Descripción (opcional)</label><input id="rrhh-edit-doc-description" name="descripcion_documento" type="text" class="form-control" maxlength="1000" /></div>
                                        <div class="form-group"><label for="rrhh-edit-doc-file">Documento PDF (máximo 10 MB)</label><input id="rrhh-edit-doc-file" name="documento_pdf" type="file" accept="application/pdf,.pdf" required /></div>
                                    </div>
                                    <div class="rrhh-photo-actions"><button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-upload"></i> Guardar documento</button><button type="button" class="btn btn-white btn-sm" id="rrhh-edit-doc-cancel">Cancelar</button></div>
                                </form>
                                <?php if($legajo_error_consulta) { ?>
                                    <div class="alert alert-warning rrhh-document-alert">No se pudo cargar la lista de documentos. Intentá nuevamente más tarde.</div>
                                <?php } elseif(count($legajo_documentos)===0) { ?>
                                    <div class="rrhh-document-empty">Todavía no hay documentos cargados en este legajo.</div>
                                <?php } else { ?>
                                    <div class="rrhh-document-table-wrap">
                                        <table class="table table-hover rrhh-document-table">
                                            <thead><tr><th>Fecha</th><th>Categoría</th><th>Documento</th><th>Descripción</th><th>Acciones</th></tr></thead>
                                            <tbody><?php foreach($legajo_documentos as $documento) { $categoria_doc=isset($legajo_categorias[$documento['categoria']]) ? $legajo_categorias[$documento['categoria']] : 'Otros documentos'; $fecha_carga='No registrada'; if(isset($documento['fecha_carga'])) { $fecha_parseada=strtotime($documento['fecha_carga']); if($fecha_parseada!==false) { $fecha_carga=date('d/m/Y H:i', $fecha_parseada); } } $url_documento='legajo-documento.php?id='.intval($documento['id_documento']); ?>
                                                <tr>
                                                    <td data-label="Fecha de carga"><?php echo htmlspecialchars($fecha_carga, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td data-label="Categoría"><?php echo htmlspecialchars($categoria_doc, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td data-label="Documento" class="rrhh-document-name"><?php echo htmlspecialchars($documento['nombre_original'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td data-label="Descripción"><?php echo htmlspecialchars(isset($documento['descripcion']) && trim((string)$documento['descripcion'])!=='' ? $documento['descripcion'] : '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td data-label="Acciones"><div class="rrhh-edit-doc-actions">
                                                        <a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($url_documento.'&modo=ver', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fa fa-eye"></i> Ver</a>
                                                        <a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($url_documento.'&modo=ver', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fa fa-print"></i> Imprimir</a>
                                                        <a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($url_documento.'&modo=descargar', ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-download"></i> Descargar</a>
                                                        <form method="post" action="<?php echo htmlspecialchars($url_editar, ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('¿Eliminar este documento del legajo? Esta acción no se puede deshacer.');">
                                                            <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_legajo, ENT_QUOTES, 'UTF-8'); ?>" />
                                                            <input type="hidden" name="accion_legajo" value="eliminar" />
                                                            <input type="hidden" name="id_documento" value="<?php echo intval($documento['id_documento']); ?>" />
                                                            <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> Eliminar</button>
                                                        </form>
                                                    </div></td>
                                                </tr>
                                            <?php } ?></tbody>
                                        </table>
                                    </div>
                                <?php } ?>
                            <?php } ?>
                        </section>

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
                                            <button type="button" class="btn btn-white btn-xs" onclick="confirmarQuitarRolExtra(<?php echo intval($rx['id']); ?>, <?php echo htmlspecialchars(json_encode(trim($empleado['nombres'].' '.$empleado['apellido'])), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($rx['rol_nombre']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($rx['area_nombre']!='' ? $rx['area_nombre'] : 'General'), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $es_propio ? 'true' : 'false'; ?>);">Quitar</button>
                                        </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                            <?php } ?>
                            <?php if($puede_extras) { ?>
                            <form method="post" style="margin-top:12px;">
                                <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_admin, ENT_QUOTES, 'UTF-8'); ?>" />
                                <div class="rrhh-extra-add-fields">
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
 </div>

<div class="modal fade" id="modalQuitarRolExtra" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h4 class="modal-title">Confirmar eliminación de rol adicional</h4>
      </div>
      <div class="modal-body">
        <p id="modalQuitarConfirmacion">¿Estás seguro de que deseas quitar este rol adicional?</p>
      </div>
      <div class="modal-footer">
        <form method="post" id="formQuitarRolExtra" style="display:inline;">
          <input type="hidden" name="csrf_tarea_extra" value="<?php echo htmlspecialchars($csrf_admin, ENT_QUOTES, 'UTF-8'); ?>" />
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
function confirmarQuitarRolExtra(idRolExtra, nombreColaborador, rolNombre, areaNombre, esPropio) {
    document.getElementById('modalQuitarIdRolExtra').value = idRolExtra;
    var confirmacion = esPropio
        ? '¿Estás seguro de que deseas quitarte el rol de ' + rolNombre + ' del área ' + areaNombre + '?'
        : '¿Estás seguro de que deseas quitarle el rol de ' + rolNombre + ' del área ' + areaNombre + ' a ' + nombreColaborador + '?';
    document.getElementById('modalQuitarConfirmacion').textContent = confirmacion;
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
(function(){
	function toggleForm(buttonId, formId, cancelId){
		var button=document.getElementById(buttonId), form=document.getElementById(formId), cancel=document.getElementById(cancelId);
		if(!button || !form) { return; }
		button.addEventListener('click', function(){ form.hidden=false; button.hidden=true; button.setAttribute('aria-expanded','true'); });
		if(cancel) { cancel.addEventListener('click', function(){ form.reset(); form.hidden=true; button.hidden=false; button.setAttribute('aria-expanded','false'); }); }
	}
	toggleForm('rrhh-edit-photo-toggle','rrhh-edit-photo-form','rrhh-edit-photo-cancel');
	toggleForm('rrhh-edit-doc-toggle','rrhh-edit-doc-form','rrhh-edit-doc-cancel');
})();
</script>
</body>
</html>
