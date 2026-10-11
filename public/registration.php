<?php
require_once __DIR__.'/_init.php';
error_reporting(0);
include_once BASE_PATH.'/includes/provincias.php';

// Registro público de cliente persona (portal). No usa la tabla vieja "user".
$err='';
$ok='';
if(isset($_SESSION['action1']) && $_SESSION['action1']!='')
{
	$err=$_SESSION['action1'];
	$_SESSION['action1']='';
}

$provincias=function_exists('provincias_todas') ? provincias_todas() : array();
$localidades_por_provincia=array();
foreach(acceso_consulta("SELECT id_localidad, nombre, id_provincia FROM localidad ORDER BY nombre") as $fila_loc)
{
	$pid=intval($fila_loc['id_provincia']);
	if(!isset($localidades_por_provincia[$pid]))
	{
		$localidades_por_provincia[$pid]=array();
	}
	$localidades_por_provincia[$pid][]=array(
		'id'=>intval($fila_loc['id_localidad']),
		'nombre'=>$fila_loc['nombre']
	);
}
$id_prov_misiones=0;
foreach($provincias as $prov)
{
	if(isset($prov['nombre']) && $prov['nombre']=='Misiones')
	{
		$id_prov_misiones=intval($prov['id_provincia']);
		break;
	}
}

if(isset($_POST['submit']))
{
	$nombre=isset($_POST['name']) ? trim($_POST['name']) : '';
	$correo=isset($_POST['email']) ? trim($_POST['email']) : '';
	$clave=isset($_POST['password']) ? (string)$_POST['password'] : '';
	$conf=isset($_POST['cpassword']) ? (string)$_POST['cpassword'] : '';
	$tel=isset($_POST['phone']) ? trim($_POST['phone']) : '';
	$genero=isset($_POST['gender']) ? $_POST['gender'] : 'm';
	$dni=isset($_POST['dni']) ? trim($_POST['dni']) : '';
	$cuil=isset($_POST['cuil_cuit']) ? trim($_POST['cuil_cuit']) : '';
	$id_loc=isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0;

	if($clave!==$conf)
	{
		$err='La contraseña y su confirmación no coinciden.';
	}
	else if(strlen($clave)<6)
	{
		$err='La contraseña debe tener al menos 6 caracteres.';
	}
	else if($cuil=='' && $dni!='')
	{
		// Si no cargó CUIL, se arma uno provisorio con el DNI (11 dígitos).
		$solo=preg_replace('/\D+/', '', $dni);
		$cuil=str_pad($solo, 11, '0', STR_PAD_LEFT);
	}

	if($err=='')
	{
		$err=cliente_alta(array(
			'nombre'=>$nombre,
			'correo'=>$correo,
			'telefono'=>$tel,
			'genero'=>$genero,
			'dni'=>$dni,
			'cuil_cuit'=>$cuil,
			'clave'=>$clave,
			'id_localidad'=>$id_loc,
			'area'=>'recepcion',
			'tipo_cliente'=>'emprendedor',
			'origen'=>'web',
			'motivo'=>'consulta',
			'detalle_atencion'=>'Alta por registro web'
		));
	}
	if($err=='')
	{
		// Eligió su clave: no forzar cambio en el primer ingreso.
		$u=usuario_cliente_por_correo($correo);
		if($u)
		{
			acceso_ejecutar("UPDATE usuario_cliente SET primer_acceso=0 WHERE id_usuario=?", 'i', array(intval($u['id_usuario'])));
		}
		$_SESSION['action1']='Registro completado. Ya podés iniciar sesión.';
		echo "<script>window.location.href='login.php'</script>";
		exit();
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>ADEMI | Registro</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<?php if(function_exists('crm_ui_css')) { crm_ui_css(); } ?>
<script type="text/javascript">
function checkpass()
{
	if(document.signup.password.value!=document.signup.cpassword.value)
	{
		alert('La contraseña y su confirmación no coinciden');
		document.signup.cpassword.focus();
		return false;
	}
	if(document.signup.password.value.length<6)
	{
		alert('La contraseña debe tener al menos 6 caracteres');
		document.signup.password.focus();
		return false;
	}
	if(document.signup.id_localidad.value==='')
	{
		alert('Elegí una localidad');
		document.signup.id_localidad.focus();
		return false;
	}
	return true;
}
</script>
</head>
<body class="crm-auth-body">
<div class="crm-auth">
  <div class="crm-auth-card" style="max-width:480px;">
    <div style="margin-bottom:12px;">
      <a href="index.php" class="text-muted" style="font-size:13px; text-decoration:none;"><i class="fa fa-arrow-left"></i> Volver atrás</a>
    </div>
    <div class="crm-auth-brand">ADEMI</div>
    <h1 class="crm-auth-title">Crear cuenta</h1>
    <p class="crm-auth-sub">Registro de emprendedor. Si ya tenés cuenta, <a href="login.php">iniciá sesión</a>.</p>
    <?php if($err!='') { ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php } ?>
    <form id="signup" name="signup" class="crm-auth-form" onsubmit="return checkpass();" method="post" autocomplete="off">
      <div class="form-group">
        <label class="form-label">Nombre y apellido</label>
        <input type="text" name="name" class="form-control" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Contraseña</label>
        <div class="crm-pass-wrap">
          <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirmar contraseña</label>
        <div class="crm-pass-wrap">
          <input type="password" name="cpassword" class="form-control" required minlength="6" autocomplete="new-password">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Teléfono</label>
        <input type="text" name="phone" class="form-control" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
      </div>
      <div class="form-group">
        <label class="form-label">DNI</label>
        <input type="text" name="dni" class="form-control" required value="<?php echo isset($_POST['dni']) ? htmlspecialchars($_POST['dni']) : ''; ?>">
      </div>
      <div class="form-group">
        <label class="form-label">CUIL</label>
        <input type="text" name="cuil_cuit" class="form-control" placeholder="Opcional si ya cargaste DNI" value="<?php echo isset($_POST['cuil_cuit']) ? htmlspecialchars($_POST['cuil_cuit']) : ''; ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Provincia</label>
        <select name="id_provincia" id="id_provincia" class="form-control" required>
          <option value="">Elegir provincia</option>
          <?php
          $id_prov_sel=isset($_POST['id_provincia']) ? intval($_POST['id_provincia']) : $id_prov_misiones;
          foreach($provincias as $prov) {
          	$pid=intval($prov['id_provincia']);
          ?>
          <option value="<?php echo $pid; ?>" <?php if($id_prov_sel===$pid) echo 'selected'; ?>><?php echo htmlspecialchars($prov['nombre']); ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Localidad</label>
        <select name="id_localidad" id="id_localidad" class="form-control" required>
          <option value="">Elegir localidad</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Género</label>
        <div>
          <label class="radio-inline"><input type="radio" value="m" name="gender" <?php if(!isset($_POST['gender']) || $_POST['gender']==='m') echo 'checked'; ?>> Hombre</label>
          <label class="radio-inline"><input type="radio" value="f" name="gender" <?php if(isset($_POST['gender']) && $_POST['gender']==='f') echo 'checked'; ?>> Mujer</label>
        </div>
      </div>
      <div class="crm-auth-actions" style="display:flex; gap:10px;">
        <a href="index.php" class="btn btn-white" style="flex:1; text-align:center;">Volver</a>
        <button class="btn btn-primary" style="flex:1;" name="submit" type="submit" value="1">Registrarse</button>
      </div>
      <p class="crm-auth-foot"><a href="login.php">Ya tengo cuenta</a></p>
    </form>
  </div>
</div>
<script>
var recLocalidades = <?php echo json_encode($localidades_por_provincia, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var recIdLoc = <?php echo isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0; ?>;
function cargarLocalidades(idProv, idSel)
{
	var sel = document.getElementById('id_localidad');
	if(!sel) { return; }
	sel.innerHTML = '';
	var vacio = document.createElement('option');
	vacio.value = '';
	vacio.text = 'Elegir localidad';
	sel.appendChild(vacio);
	var lista = recLocalidades[String(idProv)] || recLocalidades[idProv] || [];
	for(var i=0;i<lista.length;i++)
	{
		var op = document.createElement('option');
		op.value = lista[i].id;
		op.text = lista[i].nombre;
		if(idSel && parseInt(lista[i].id,10)===parseInt(idSel,10)) { op.selected = true; }
		sel.appendChild(op);
	}
}
var recSelProv = document.getElementById('id_provincia');
if(recSelProv)
{
	recSelProv.onchange = function(){ cargarLocalidades(this.value, 0); };
	cargarLocalidades(recSelProv.value, recIdLoc);
}
</script>
</body>
</html>
