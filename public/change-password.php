<?php
require_once __DIR__.'/_init.php';
error_reporting(0);

$es_staff=crm_es_staff();
if($es_staff)
{
	crm_proteger_staff();
}
else
{
	crm_proteger_cliente();
}
$forzar_clave=(isset($_SESSION['primer_acceso']) && intval($_SESSION['primer_acceso'])==1);
if(isset($_POST['change']))
{
	$nueva=isset($_POST['newpass']) ? (string)$_POST['newpass'] : '';
	$actual=isset($_POST['oldpass']) ? (string)$_POST['oldpass'] : '';
	$conf=isset($_POST['confirmpassword']) ? (string)$_POST['confirmpassword'] : '';
	if($nueva==='' || strlen($nueva)<6)
	{
		$_SESSION['msg1']='La nueva contraseña debe tener al menos 6 caracteres.';
	}
	else if($nueva!==$conf)
	{
		$_SESSION['msg1']='La contraseña y su confirmación no coinciden.';
	}
	else if($es_staff)
	{
		$id=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
		if($id<=0)
		{
			$_SESSION['msg1']='Sesión inválida. Volvé a iniciar sesión.';
		}
		else
		{
			$row=acceso_consulta_una("SELECT contrasena_hash FROM empleado WHERE id_empleado=? LIMIT 1", 'i', array($id));
			$ok=$forzar_clave || ($row && acceso_verificar_clave($actual, isset($row['contrasena_hash']) ? $row['contrasena_hash'] : ''));
			if(!$ok)
			{
				$_SESSION['msg1']='La contraseña actual no coincide.';
			}
			else
			{
				$hash=acceso_hash_clave($nueva);
				if($hash===false || $hash==='')
				{
					$_SESSION['msg1']='No se pudo generar la nueva contraseña.';
				}
				else if(!acceso_ejecutar("UPDATE empleado SET contrasena_hash=?, primer_acceso=0 WHERE id_empleado=?", 'si', array($hash, $id)))
				{
					$_SESSION['msg1']='No se pudo guardar la nueva contraseña.';
				}
				else
				{
					$_SESSION['primer_acceso']=0;
					if($forzar_clave)
					{
						session_unset();
						session_destroy();
						session_start();
						$_SESSION['errmsg']='Contraseña actualizada. Iniciá sesión con la nueva clave.';
						echo "<script>window.location.href='".url_crm('admin/index.php')."'</script>";
						exit();
					}
					$_SESSION['msg1']='Contraseña cambiada correctamente.';
				}
			}
		}
	}
	else
	{
		$err=acceso_cliente_cambiar_clave($actual, $nueva);
		if($err=='' && $forzar_clave)
		{
			session_unset();
			session_destroy();
			session_start();
			$_SESSION['action1']='Contraseña actualizada. Iniciá sesión con la nueva clave.';
			echo "<script>window.location.href='login.php'</script>";
			exit();
		}
		$_SESSION['msg1']=($err=='') ? 'Contraseña cambiada correctamente.' : $err;
	}
}
$msg=isset($_SESSION['msg1']) ? $_SESSION['msg1'] : '';
$_SESSION['msg1']='';
// Primer ingreso: pantalla simple, sin menú ni campana (evita interferencias).
$layout_simple=$forzar_clave;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>ADEMI | Cambiar contraseña</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<?php if(function_exists('crm_ui_css')) { crm_ui_css(); } ?>
<script type="text/javascript">
var recForzarClave = <?php echo $forzar_clave ? 'true' : 'false'; ?>;
function valid()
{
	if(!recForzarClave && document.form1.oldpass && document.form1.oldpass.value=="")
	{
		alert("El campo de contraseña actual está vacío.");
		document.form1.oldpass.focus();
		return false;
	}
	if(document.form1.newpass.value=="")
	{
		alert("El campo de nueva contraseña está vacío.");
		document.form1.newpass.focus();
		return false;
	}
	if(document.form1.confirmpassword.value=="")
	{
		alert("El campo de confirmación está vacío.");
		document.form1.confirmpassword.focus();
		return false;
	}
	if(document.form1.newpass.value.length<6)
	{
		alert("La contraseña debe tener al menos 6 caracteres.");
		document.form1.newpass.focus();
		return false;
	}
	if(document.form1.newpass.value!= document.form1.confirmpassword.value)
	{
		alert("La contraseña y su confirmación no coinciden.");
		document.form1.newpass.focus();
		return false;
	}
	return true;
}
</script>
</head>
<body class="<?php echo $layout_simple ? 'crm-auth-body' : ''; ?>">
<?php if($layout_simple) { ?>
<div class="crm-auth">
	<div class="crm-auth-card">
		<div class="crm-auth-brand">ADEMI</div>
		<h1 class="crm-auth-title">Elegí tu contraseña</h1>
		<p class="crm-auth-sub">En el primer ingreso tenés que elegir tu contraseña.</p>
		<?php if($msg!='') { ?>
		<div class="alert alert-warning"><?php echo htmlspecialchars($msg); ?></div>
		<?php } ?>
		<form name="form1" method="post" action="" onSubmit="return valid();" class="crm-auth-form">
			<div class="form-group">
				<label class="form-label">Nueva contraseña</label>
				<div class="crm-pass-wrap">
					<input type="password" name="newpass" id="newpass" class="form-control" autocomplete="new-password">
				</div>
			</div>
			<div class="form-group">
				<label class="form-label">Confirmar contraseña</label>
				<div class="crm-pass-wrap">
					<input type="password" name="confirmpassword" id="confirmpassword" class="form-control" autocomplete="new-password">
				</div>
			</div>
			<div class="crm-auth-actions" style="display:flex;gap:10px;">
				<button type="reset" class="btn btn-white" style="flex:1;">Limpiar</button>
				<input type="submit" value="Cambiar" name="change" class="btn btn-primary" style="flex:1;">
			</div>
		</form>
	</div>
</div>
<?php } else { ?>
<?php
if($es_staff) { crm_header_staff(); } else { crm_header_cliente(); }
?>
<div class="page-container row-fluid">
	<?php
	if($es_staff) { crm_menu_staff(); } else { crm_menu_cliente(); }
	?>
	<div class="clearfix"></div>
	</div>
	</div>
	<div class="page-content">
		<div class="clearfix"></div>
		<div class="content">
			<div class="crm-pass-page">
				<div class="crm-pass-card">
					<div class="crm-auth-brand">ADEMI</div>
					<h1 class="crm-auth-title">Cambiar contraseña</h1>
					<?php if($msg!='') { ?>
					<div class="alert alert-warning"><?php echo htmlspecialchars($msg); ?></div>
					<?php } ?>
					<form name="form1" method="post" action="" onSubmit="return valid();" class="crm-auth-form">
						<div class="form-group">
							<label class="form-label">Contraseña actual</label>
							<div class="crm-pass-wrap">
								<input type="password" name="oldpass" id="oldpass" class="form-control" autocomplete="current-password">
							</div>
						</div>
						<div class="form-group">
							<label class="form-label">Nueva contraseña</label>
							<div class="crm-pass-wrap">
								<input type="password" name="newpass" id="newpass" class="form-control" autocomplete="new-password">
							</div>
						</div>
						<div class="form-group">
							<label class="form-label">Confirmar contraseña</label>
							<div class="crm-pass-wrap">
								<input type="password" name="confirmpassword" id="confirmpassword" class="form-control" autocomplete="new-password">
							</div>
						</div>
						<div class="crm-auth-actions" style="display:flex;gap:10px;">
							<button type="reset" class="btn btn-white" style="flex:1;">Limpiar</button>
							<input type="submit" value="Cambiar" name="change" class="btn btn-primary" style="flex:1;">
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="assets/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
<script src="assets/js/core.js?v=20260926" type="text/javascript"></script>
<?php } ?>
</body>
</html>
