<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
if(isset($_POST['login']))
{
	$interno=acceso_buscar_personal($_POST['email'], $_POST['password']);
	if($interno)
	{
		acceso_iniciar_sesion($interno);
		$extra=acceso_destino_login();
		echo "<script>window.location.href='".$extra."'</script>";
		exit();
	}
	$_SESSION['action1']="Usuario o contraseña no válidos";
	echo "<script>window.location.href='index.php'</script>";
	exit();
}
$err=isset($_SESSION['action1']) ? $_SESSION['action1'] : '';
$_SESSION['action1']='';
if($err=='' && isset($_SESSION['errmsg']) && $_SESSION['errmsg']!='')
{
	$err=$_SESSION['errmsg'];
	$_SESSION['errmsg']='';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>ADEMI | Ingreso del personal</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<?php if(function_exists('crm_ui_css')) { crm_ui_css(); } ?>
</head>
<body class="crm-auth-body">
<div class="crm-auth">
  <div class="crm-auth-card">
    <div class="crm-auth-brand">ADEMI</div>
    <h1 class="crm-auth-title">INGRESO DEL PERSONAL</h1>
    <p class="crm-auth-sub">Para el equipo de ADEMI (colaboradores y coordinación).</p>
    <?php if($err!='') { ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars(ltrim($err, '*')); ?></div>
    <?php } ?>
    <form id="login-form" class="crm-auth-form" action="" method="post">
      <div class="form-group">
        <label class="form-label">Usuario</label>
        <input type="text" name="email" id="txtusername" class="form-control" required autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label">Contraseña</label>
        <div class="crm-pass-wrap">
          <input type="password" name="password" id="txtpassword" class="form-control" required autocomplete="current-password" data-crm-pass="1">
          <button type="button" class="crm-pass-toggle" title="Ver contraseña" aria-label="Ver contraseña"><i class="fa fa-eye"></i></button>
        </div>
      </div>
      <div class="crm-auth-actions">
        <button class="btn btn-primary btn-block" name="login" type="submit">Iniciar sesión</button>
      </div>
    </form>
    <p class="crm-auth-foot">
      <a href="../index.php">Volver al inicio</a>
    </p>
  </div>
</div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
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
