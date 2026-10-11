<?php
require_once __DIR__.'/_init.php';
error_reporting(0);

// Recuperación de clave del portal cliente.
// Las claves están hasheadas: no se pueden "enviar por mail".
// Se restablece al DNI y se fuerza cambio en el próximo ingreso.
$msg='';
$ok=false;
if(isset($_POST['submit']))
{
	$correo=isset($_POST['email']) ? trim($_POST['email']) : '';
	if($correo=='' || !filter_var($correo, FILTER_VALIDATE_EMAIL))
	{
		$msg='Ingresá un correo válido.';
	}
	else
	{
		$u=usuario_cliente_por_correo($correo);
		if(!$u)
		{
			// Misma respuesta genérica para no filtrar cuentas.
			$msg='Si el correo está registrado y tiene DNI, restablecimos la clave al DNI. Entrá y cambiála.';
			$ok=true;
		}
		else
		{
			$err=crm_reset_clave_cliente(intval($u['id_usuario']));
			if($err!='')
			{
				$msg=$err.' Si no tenés DNI cargado, pedí el reset en Recepción o Sistemas.';
			}
			else
			{
				$msg='Clave restablecida al DNI. Iniciá sesión y elegí una nueva contraseña.';
				$ok=true;
			}
		}
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>ADEMI | Recuperar contraseña</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<?php if(function_exists('crm_ui_css')) { crm_ui_css(); } ?>
</head>
<body class="crm-auth-body">
<div class="crm-auth">
  <div class="crm-auth-card">
    <div class="crm-auth-brand">ADEMI</div>
    <h1 class="crm-auth-title">Recuperar contraseña</h1>
    <p class="crm-auth-sub">Restablecemos la clave al DNI. En el próximo ingreso te pedimos cambiarla.</p>
    <?php if($msg!='') { ?>
    <div class="alert <?php echo $ok ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php } ?>
    <form class="crm-auth-form" method="post" action="">
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="email" class="form-control" required autocomplete="username" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
      </div>
      <div class="crm-auth-actions">
        <button class="btn btn-primary btn-block" name="submit" type="submit" value="1">Restablecer</button>
      </div>
      <p class="crm-auth-foot">
        <a href="login.php">Volver al ingreso</a>
        · <a href="registration.php">Registrarse</a>
      </p>
    </form>
  </div>
</div>
</body>
</html>
