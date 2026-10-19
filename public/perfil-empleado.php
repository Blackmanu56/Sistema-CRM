<?php
require_once __DIR__.'/_init.php';
crm_proteger_staff();
require_once BASE_PATH.'/includes/rrhh/fotos.php';

$id=crm_id_empleado_sesion();
$empleado=$id>0 ? acceso_empleado_por_id($id) : null;
if(!$empleado)
{
	header('Location: '.url_crm('admin/index.php'));
	exit();
}

if($_SERVER['REQUEST_METHOD']==='POST')
{
	$token=isset($_POST['csrf_tarea_extra']) && is_string($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : '';
	if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido($token))
	{
		$_SESSION['foto_perfil_mensaje']='La sesión expiró. Actualizá la página e intentá nuevamente.';
		$_SESSION['foto_perfil_exito']=0;
	}
	else if(isset($_POST['accion_foto']) && $_POST['accion_foto']==='subir')
	{
		$resultado=crm_rrhh_foto_subir($id, isset($_FILES['foto_empleado']) ? $_FILES['foto_empleado'] : array());
		$_SESSION['foto_perfil_mensaje']=$resultado[1];
		$_SESSION['foto_perfil_exito']=$resultado[0] ? 1 : 0;
	}
	else if(isset($_POST['accion_foto']) && $_POST['accion_foto']==='eliminar')
	{
		$resultado=crm_rrhh_foto_eliminar($id);
		$_SESSION['foto_perfil_mensaje']=$resultado[1];
		$_SESSION['foto_perfil_exito']=$resultado[0] ? 1 : 0;
	}
	header('Location: '.url_crm('perfil-empleado.php'));
	exit();
}

$mensaje=isset($_SESSION['foto_perfil_mensaje']) ? (string)$_SESSION['foto_perfil_mensaje'] : '';
$exito=!empty($_SESSION['foto_perfil_exito']);
unset($_SESSION['foto_perfil_mensaje'], $_SESSION['foto_perfil_exito']);
$columna_disponible=crm_rrhh_foto_columna_disponible();
$foto_key=$columna_disponible ? crm_rrhh_foto_key_por_empleado($id) : '';
$foto_url=($foto_key!=='' && crm_rrhh_foto_ruta_por_key($foto_key)!=='') ? url_crm('foto-empleado.php?id='.$id.'&v='.rawurlencode($foto_key)) : '';
$nombre=trim((isset($empleado['nombres']) ? $empleado['nombres'] : '').' '.(isset($empleado['apellido']) ? $empleado['apellido'] : ''));
$area_perfil=isset($empleado['area']) && trim((string)$empleado['area'])!=='' ? (string)$empleado['area'] : 'No registrado';
$rol_perfil=isset($empleado['rol']) && trim((string)$empleado['rol'])!=='' ? acceso_mostrar_rol($empleado['rol']) : 'No registrado';
$correo_perfil=isset($empleado['correo']) && trim((string)$empleado['correo'])!=='' ? (string)$empleado['correo'] : 'No registrado';
$telefono_perfil=isset($empleado['telefono']) && trim((string)$empleado['telefono'])!=='' ? (string)$empleado['telefono'] : 'No registrado';
$puesto_perfil=isset($empleado['puesto']) && trim((string)$empleado['puesto'])!=='' ? (string)$empleado['puesto'] : 'No registrado';
$iniciales='';
foreach(preg_split('/\s+/', $nombre, -1, PREG_SPLIT_NO_EMPTY) as $parte)
{
	$primera=function_exists('mb_substr') ? mb_substr($parte, 0, 1, 'UTF-8') : substr($parte, 0, 1);
	$iniciales.=function_exists('mb_strtoupper') ? mb_strtoupper($primera, 'UTF-8') : strtoupper($primera);
	if(strlen($iniciales)>=2) { break; }
}
if($iniciales==='') { $iniciales='E'; }
$csrf=function_exists('crm_tarea_extra_csrf_token') ? crm_tarea_extra_csrf_token() : '';
$esc=function($valor) { return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>ADEMI | Mi perfil</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css" />
<link href="assets/css/style.css" rel="stylesheet" type="text/css" />
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css" />
<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css" />
<style>
.crm-foto-perfil { max-width:760px; margin:22px auto; background:#fff; border:1px solid #dce3eb; border-radius:10px; box-shadow:0 4px 16px rgba(24,39,58,.07); overflow:hidden; }
.crm-foto-perfil-head { display:flex; flex-wrap:wrap; align-items:center; gap:20px; padding:22px 26px; background:linear-gradient(135deg,#f5f9ff 0%,#edf4fb 100%); border-bottom:1px solid #dce5ef; }
.crm-perfil-photo-block { order:1; flex:0 0 120px; display:flex; flex-direction:column; align-items:center; gap:9px; min-width:0; }
.crm-foto-perfil-avatar { width:100px; height:100px; flex:0 0 100px; display:flex; align-items:center; justify-content:center; overflow:hidden; border:4px solid #fff; border-radius:50%; background:#dceaff; color:#2464ad; font-size:28px; font-weight:700; box-shadow:0 2px 10px rgba(33,85,145,.14); }
.crm-foto-perfil-avatar img { width:100%; height:100%; display:block; object-fit:cover; }
.crm-perfil-photo-controls { display:flex; justify-content:center; align-items:center; gap:5px; flex-wrap:wrap; }
.crm-perfil-photo-controls .btn[hidden] { display:none !important; }
.crm-perfil-photo-form { order:3; flex:0 0 100%; width:100%; max-width:460px; min-width:0; box-sizing:border-box; margin:0; padding:12px; border:1px solid #dce5ef; border-radius:7px; background:rgba(255,255,255,.86); }
.crm-perfil-photo-form[hidden] { display:none !important; }
.crm-perfil-photo-form label { display:block; margin:0 0 5px; color:#60758b; font-size:11px; font-weight:600; }
.crm-perfil-photo-form input[type=file] { display:block; width:100%; max-width:100%; min-width:0; box-sizing:border-box; font-size:12px; }
.crm-perfil-photo-form-actions { display:flex; gap:6px; margin-top:8px; }
.crm-perfil-head-main { order:2; flex:1 1 260px; min-width:0; }
.crm-foto-perfil-title { margin:0 0 5px; color:#1f3147; font-size:22px; font-weight:700; }
.crm-foto-perfil-subtitle { margin:0; color:#718096; }
.crm-foto-perfil-body { padding:22px 26px 26px; }
.crm-foto-perfil-help { margin:6px 0 0; color:#718096; font-size:11px; }
.crm-perfil-meta { display:flex; flex-wrap:wrap; gap:7px; margin-top:10px; }
.crm-perfil-meta span { display:inline-flex; align-items:center; gap:5px; padding:5px 9px; border:1px solid #d9e4f0; border-radius:14px; background:#fff; color:#46627f; font-size:12px; }
.crm-perfil-data { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-bottom:22px; }
.crm-perfil-data-item { padding:12px 14px; border:1px solid #e4ebf2; border-radius:7px; background:#f8fafc; min-width:0; }
.crm-perfil-data-item small { display:block; margin-bottom:4px; color:#718096; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
.crm-perfil-data-item strong { display:block; color:#243b53; font-weight:600; overflow-wrap:anywhere; }
@media(max-width:600px) { .crm-foto-perfil-head { padding:18px; gap:14px; align-items:flex-start; } .crm-perfil-photo-block { flex-basis:96px; } .crm-foto-perfil-avatar { width:76px; height:76px; flex-basis:76px; } .crm-perfil-photo-controls { flex-direction:column; width:100%; } .crm-perfil-photo-controls .btn { width:100%; padding:5px 7px; } .crm-perfil-head-main { flex-basis:calc(100% - 110px); } .crm-foto-perfil-body { padding:18px; } }
@media(max-width:480px) { .crm-perfil-data { grid-template-columns:1fr; gap:8px; } }
</style>
</head>
<body>
<?php crm_header_staff(); ?>
<div class="page-container row">
	<?php crm_menu_staff(); ?>
	<div class="clearfix"></div>
</div>
</div>
<div class="page-content">
	<div class="content">
		<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a class="active" href="#">Mi perfil</a></li></ul>
		<div class="page-title"><h3>Mi perfil</h3></div>
		<?php if($mensaje!=='') { ?><div class="alert <?php echo $exito ? 'alert-success' : 'alert-warning'; ?>"><?php echo $esc($mensaje); ?></div><?php } ?>
		<section class="crm-foto-perfil">
			<header class="crm-foto-perfil-head">
				<div class="crm-perfil-photo-block">
					<div class="crm-foto-perfil-avatar" aria-label="Fotografía de <?php echo $esc($nombre); ?>">
						<?php if($foto_url!=='') { ?><img src="<?php echo $esc($foto_url); ?>" alt="Fotografía de <?php echo $esc($nombre); ?>" /><?php } else { echo $esc($iniciales); } ?>
					</div>
					<?php if($columna_disponible) { ?>
					<div class="crm-perfil-photo-controls">
						<button type="button" class="btn btn-primary btn-xs" id="crm-perfil-photo-toggle" aria-controls="crm-perfil-photo-form" aria-expanded="false"><i class="fa fa-camera"></i> <?php echo $foto_key!=='' ? 'Modificar foto' : 'Agregar foto'; ?></button>
						<?php if($foto_key!=='') { ?>
						<form method="post" onsubmit="return confirm('¿Quitar tu fotografía y volver al avatar predeterminado?');" style="margin:0;">
							<input type="hidden" name="csrf_tarea_extra" value="<?php echo $esc($csrf); ?>" />
							<input type="hidden" name="accion_foto" value="eliminar" />
							<button type="submit" class="btn btn-white btn-xs"><i class="fa fa-trash"></i> Quitar foto</button>
						</form>
						<?php } ?>
					</div>
					<?php } else { ?><p class="crm-foto-perfil-help" role="status">La gestión de fotografías está pendiente de habilitación.</p><?php } ?>
				</div>
				<div class="crm-perfil-head-main">
					<h1 class="crm-foto-perfil-title"><?php echo $esc($nombre); ?></h1>
					<p class="crm-foto-perfil-subtitle">Tu información y fotografía de perfil</p>
					<div class="crm-perfil-meta"><span><i class="fa fa-folder-o"></i><?php echo $esc($area_perfil); ?></span><span><i class="fa fa-user"></i><?php echo $esc($rol_perfil); ?></span></div>
				</div>
				<?php if($columna_disponible) { ?>
				<form id="crm-perfil-photo-form" class="crm-perfil-photo-form" method="post" enctype="multipart/form-data" hidden>
					<input type="hidden" name="csrf_tarea_extra" value="<?php echo $esc($csrf); ?>" />
					<input type="hidden" name="accion_foto" value="subir" />
					<label for="foto_empleado">Imagen JPG, PNG o WebP · máximo 5 MB</label>
					<input id="foto_empleado" type="file" name="foto_empleado" accept="image/jpeg,image/png,image/webp" required />
					<div class="crm-perfil-photo-form-actions">
						<button type="submit" class="btn btn-primary btn-xs"><i class="fa fa-save"></i> Guardar foto</button>
						<button type="button" class="btn btn-white btn-xs" id="crm-perfil-photo-cancel">Cancelar</button>
					</div>
				</form>
				<?php } ?>
			</header>
			<div class="crm-foto-perfil-body">
				<?php if(!$columna_disponible) { ?><div class="alert alert-info" role="status">La carga de fotografías quedará habilitada cuando se aplique la migración pendiente de la base de datos.</div><?php } ?>
				<div class="crm-perfil-data">
					<div class="crm-perfil-data-item"><small>Correo electrónico</small><strong><?php echo $esc($correo_perfil); ?></strong></div>
					<div class="crm-perfil-data-item"><small>Teléfono</small><strong><?php echo $esc($telefono_perfil); ?></strong></div>
					<div class="crm-perfil-data-item"><small>Puesto</small><strong><?php echo $esc($puesto_perfil); ?></strong></div>
					<div class="crm-perfil-data-item"><small>Estado</small><strong><?php echo intval($empleado['activo'])===1 ? 'Activo' : 'Inactivo'; ?></strong></div>
				</div>
			</div>
		</section>
	</div>
</div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script>
(function(){
  var toggle=document.getElementById('crm-perfil-photo-toggle');
  var form=document.getElementById('crm-perfil-photo-form');
  var cancel=document.getElementById('crm-perfil-photo-cancel');
  if(!toggle || !form) return;
  toggle.addEventListener('click',function(){ form.hidden=false; toggle.hidden=true; toggle.setAttribute('aria-expanded','true'); });
  if(cancel) cancel.addEventListener('click',function(){ form.reset(); form.hidden=true; toggle.hidden=false; toggle.setAttribute('aria-expanded','false'); });
})();
</script>
</body>
</html>
