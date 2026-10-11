<?php
require_once __DIR__.'/_init.php';
error_reporting(0);
crm_proteger_cliente();

$ok='';
$err='';
if(isset($_POST['guardar_datos']))
{
	$err=crm_cliente_perfil_guardar_datos(
		isset($_POST['nombre']) ? $_POST['nombre'] : '',
		isset($_POST['dni']) ? $_POST['dni'] : '',
		isset($_POST['telefono']) ? $_POST['telefono'] : '',
		isset($_POST['direccion']) ? $_POST['direccion'] : ''
	);
	if($err=='') { $ok='Tus datos se guardaron.'; }
}
if(isset($_POST['cambiar_correo']))
{
	$err=crm_cliente_perfil_cambiar_correo(
		isset($_POST['correo']) ? $_POST['correo'] : '',
		isset($_POST['clave_correo']) ? $_POST['clave_correo'] : ''
	);
	if($err=='') { $ok='Correo de ingreso actualizado. Desde ahora iniciás sesión con el nuevo correo.'; }
}
if(isset($_POST['cambiar_clave']))
{
	$nueva=isset($_POST['nueva']) ? $_POST['nueva'] : '';
	$conf=isset($_POST['confirmar']) ? $_POST['confirmar'] : '';
	if($nueva!==$conf)
	{
		$err='La nueva contraseña y su confirmación no coinciden.';
	}
	else
	{
		$err=acceso_cliente_cambiar_clave(isset($_POST['actual']) ? $_POST['actual'] : '', $nueva);
		if($err=='') { $ok='Contraseña actualizada.'; }
	}
}
$perfil=crm_cliente_perfil();
$cta=$perfil ? $perfil['cuenta'] : null;
$es_persona=($cta && (
	(isset($cta['tipo']) && $cta['tipo']=='persona')
	|| (isset($cta['tipo_cliente']) && !cliente_es_organizacion($cta['tipo_cliente']))
));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>ADEMI | Mi perfil</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
</head>
<body class="">
<?php crm_header_cliente();?>
<div class="page-container row-fluid">
	<?php crm_menu_cliente();?>
	<div class="clearfix"></div>
  </div>
  </div>
  <div class="page-content">
    <div class="clearfix"></div>
    <div class="content">
		<ul class="breadcrumb">
			<li><p>ESTÁS AQUÍ</p></li>
			<li><a href="#" class="active">Mi perfil</a></li>
		</ul>
		<div class="page-title"><h3>Mi perfil</h3></div>
		<?php if($ok!='') { ?><div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div><?php } ?>
		<?php if($err!='') { ?><div class="alert alert-warning"><?php echo htmlspecialchars($err); ?></div><?php } ?>

		<?php if(!$perfil) { ?>
		<div class="grid simple"><div class="grid-body"><p>No se pudo cargar tu cuenta. Volvé a iniciar sesión.</p></div></div>
		<?php } else { ?>
		<div class="row">
			<div class="col-md-7">
				<div class="grid simple">
					<div class="grid-title no-border"><h4><i class="fa fa-user"></i> Mis datos</h4></div>
					<div class="grid-body no-border">
						<form method="post" action="">
							<div class="form-group">
								<label>Nombre y apellido</label>
								<input type="text" name="nombre" class="form-control" maxlength="150" required value="<?php echo htmlspecialchars($perfil['nombre']); ?>">
							</div>
							<div class="row">
								<div class="col-sm-6 form-group">
									<label>DNI</label>
									<input type="text" name="dni" class="form-control" maxlength="10" value="<?php echo htmlspecialchars((string)$perfil['dni']); ?>">
								</div>
								<div class="col-sm-6 form-group">
									<label>Teléfono</label>
									<input type="text" name="telefono" class="form-control" maxlength="30" value="<?php echo htmlspecialchars((string)$perfil['telefono']); ?>">
								</div>
							</div>
							<?php if($es_persona) { ?>
							<div class="form-group">
								<label>Dirección</label>
								<input type="text" name="direccion" class="form-control" maxlength="200" value="<?php echo htmlspecialchars((string)$cta['direccion']); ?>">
							</div>
							<?php } ?>
							<button type="submit" name="guardar_datos" value="1" class="btn btn-primary">Guardar datos</button>
						</form>
					</div>
				</div>

				<?php if($cta) { ?>
				<div class="grid simple">
					<div class="grid-title no-border"><h4><i class="fa fa-list-alt"></i> <?php echo $es_persona ? 'Ficha en ADEMI' : 'Empresa que representás'; ?></h4></div>
					<div class="grid-body no-border">
						<p><strong><?php echo htmlspecialchars($cta['nombre']); ?></strong></p>
						<p class="muted" style="margin:0;">
							CUIL/CUIT: <?php echo htmlspecialchars($cta['cuil_cuit']); ?>
							<?php if(!empty($cta['localidad'])) { ?> · <?php echo htmlspecialchars($cta['localidad']); ?><?php } ?>
							· Alta: <?php echo htmlspecialchars(crm_portal_fecha($cta['fecha_alta'])); ?>
						</p>
						<?php if(!$es_persona) { ?>
						<p class="muted" style="margin-top:8px;">Los datos de la empresa los actualiza el equipo de ADEMI.</p>
						<?php } ?>
					</div>
				</div>
				<?php } ?>
			</div>

			<div class="col-md-5">
				<div class="grid simple">
					<div class="grid-title no-border"><h4><i class="fa fa-envelope"></i> Correo de ingreso</h4></div>
					<div class="grid-body no-border">
						<p class="muted">Es tu usuario para iniciar sesión.</p>
						<form method="post" action="">
							<div class="form-group">
								<label>Correo</label>
								<input type="email" name="correo" class="form-control" maxlength="150" required value="<?php echo htmlspecialchars($perfil['correo']); ?>">
							</div>
							<div class="form-group">
								<label>Contraseña actual (para confirmar)</label>
								<div class="crm-pass-wrap">
									<input type="password" name="clave_correo" class="form-control" required autocomplete="current-password">
								</div>
							</div>
							<button type="submit" name="cambiar_correo" value="1" class="btn btn-white">Cambiar correo</button>
						</form>
					</div>
				</div>

				<div class="grid simple">
					<div class="grid-title no-border"><h4><i class="fa fa-lock"></i> Contraseña</h4></div>
					<div class="grid-body no-border">
						<form method="post" action="" onsubmit="var n=this.nueva.value; if(n.length<6){alert('La contraseña debe tener al menos 6 caracteres.');return false;} if(n!==this.confirmar.value){alert('La confirmación no coincide.');return false;} return true;">
							<div class="form-group">
								<label>Contraseña actual</label>
								<div class="crm-pass-wrap">
									<input type="password" name="actual" class="form-control" required autocomplete="current-password">
								</div>
							</div>
							<div class="form-group">
								<label>Nueva contraseña</label>
								<div class="crm-pass-wrap">
									<input type="password" name="nueva" class="form-control" required minlength="6" autocomplete="new-password">
								</div>
							</div>
							<div class="form-group">
								<label>Confirmar nueva contraseña</label>
								<div class="crm-pass-wrap">
									<input type="password" name="confirmar" class="form-control" required minlength="6" autocomplete="new-password">
								</div>
							</div>
							<button type="submit" name="cambiar_clave" value="1" class="btn btn-white">Cambiar contraseña</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		<?php } ?>
    </div>
  </div>
</div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script>
<script src="assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
