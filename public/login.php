<?php
require_once __DIR__.'/_init.php';
error_reporting(0);
// Entra cliente persona o responsable. La empresa no inicia sesión.
if(isset($_POST['login']))
{
	$correo=isset($_POST['email']) ? trim($_POST['email']) : '';
	$clave=isset($_POST['password']) ? $_POST['password'] : '';
	$estado=acceso_cliente_entrar($correo, $clave);
	if($estado=='primer')
	{
		echo "<script>window.location.href='change-password.php'</script>";
		exit();
	}
	if($estado=='ok')
	{
		echo "<script>window.location.href='dashboard.php'</script>";
		exit();
	}
	if($estado=='elegir')
	{
		echo "<script>window.location.href='elegir-cuenta.php'</script>";
		exit();
	}
	$_SESSION['action1']="Usuario o contraseña no válidos";
	echo "<script>window.location.href='login.php'</script>";
	exit();
}
$err=isset($_SESSION['action1']) ? $_SESSION['action1'] : '';
$_SESSION['action1']='';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>ADEMI | Inicio de sesión</title>
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
    <h1 class="crm-auth-title">INICIO DE SESIÓN</h1>
    <?php if($err!='') { ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php } ?>
    <form id="login-form" class="crm-auth-form" action="" method="post">
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="email" id="txtusername" class="form-control" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Contraseña</label>
        <div class="crm-pass-wrap">
          <input type="password" name="password" id="txtpassword" class="form-control" required autocomplete="current-password" data-crm-pass="1">
          <button type="button" class="crm-pass-toggle" title="Ver contraseña" aria-label="Ver contraseña"><i class="fa fa-eye"></i></button>
        </div>
      </div>
      <div class="crm-auth-links">
        <a href="forgot-password.php">¿Olvidaste tu contraseña?</a>
      </div>
      <div class="crm-auth-actions">
        <button class="btn btn-primary btn-block" name="login" type="submit">Iniciar sesión</button>
      </div>
    </form>
    <p class="crm-auth-foot">
      <a href="index.php">Volver al inicio</a>
      ·
      <a href="registration.php">Registrarse</a>
    </p>
  </div>
</div>
<script src="assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script>
(function(){
  var btn=document.querySelector('.crm-pass-toggle');
  var input=document.getElementById('txtpassword');
  if(btn && input){
    btn.onclick=function(e){
      e.preventDefault();
      var show=input.type==='password';
      input.type=show?'text':'password';
      btn.innerHTML=show?'<i class="fa fa-eye-slash"></i>':'<i class="fa fa-eye"></i>';
      btn.setAttribute('title', show?'Ocultar contraseña':'Ver contraseña');
    };
  }
})();
</script>
</body>
</html>
