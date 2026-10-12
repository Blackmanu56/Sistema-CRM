<?php
require_once __DIR__.'/_init.php';
error_reporting(0);
require_once BASE_PATH.'/includes/cliente/checklogin.php';
check_login();

$cuentas=(isset($_SESSION['cuentas_cliente']) && is_array($_SESSION['cuentas_cliente'])) ? $_SESSION['cuentas_cliente'] : array();
if(count($cuentas)==0)
{
	$id_usuario=isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
	$cuentas=acceso_cliente_cuentas_de($id_usuario);
	$_SESSION['cuentas_cliente']=$cuentas;
}
if(count($cuentas)==1)
{
	acceso_cliente_aplicar_cuenta($cuentas[0]);
	echo "<script>window.location.href='dashboard.php'</script>";
	exit();
}

if(isset($_POST['cuenta']))
{
	$elegida=$_POST['cuenta'];
	foreach($cuentas as $c)
	{
		$clave=$c['tipo'].'-'.intval($c['id']);
		if($clave===$elegida)
		{
			acceso_cliente_aplicar_cuenta($c);
			echo "<script>window.location.href='dashboard.php'</script>";
			exit();
		}
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Elegir cuenta</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<?php if(function_exists('crm_ui_css')) { crm_ui_css(); } ?>
</head>
<body class="crm-auth-body">
<div class="crm-auth">
  <div class="crm-auth-card">
    <div class="crm-auth-brand">ADEMI</div>
    <h1 class="crm-auth-title">Elegí la cuenta</h1>
    <p class="crm-auth-sub">¿Qué perfil querés gestionar?</p>
    <form method="post" action="" class="crm-auth-form">
      <?php foreach($cuentas as $c) {
      	$clave=$c['tipo'].'-'.intval($c['id']);
      	$titulo=($c['tipo']=='persona') ? 'Cliente: '.$c['nombre'] : 'Empresa: '.$c['nombre'];
      ?>
      <div class="crm-auth-actions" style="margin-bottom:10px;">
        <button class="btn btn-primary btn-block" type="submit" name="cuenta" value="<?php echo htmlspecialchars($clave); ?>"><?php echo htmlspecialchars($titulo); ?></button>
      </div>
      <?php } ?>
    </form>
    <p class="crm-auth-foot"><a href="logout.php">Cerrar sesión</a></p>
  </div>
</div>
</body>
</html>
