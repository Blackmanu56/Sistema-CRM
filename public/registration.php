<?php
require_once __DIR__.'/_init.php';
error_reporting(0);
include_once BASE_PATH.'/includes/provincias.php';

// Alta web corta → solicitud pendiente (evalúa Coordinación general).
$err='';
$ok='';
if(isset($_SESSION['action1']) && $_SESSION['action1']!='')
{
	$ok=$_SESSION['action1'];
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

$sectores=crm_solicitud_sectores();
$anios_ops=crm_solicitud_anios_opciones();
$pers_ops=crm_solicitud_personas_opciones();

if(isset($_POST['submit']))
{
	$clave=isset($_POST['password']) ? (string)$_POST['password'] : '';
	$conf=isset($_POST['cpassword']) ? (string)$_POST['cpassword'] : '';
	if($clave!==$conf)
	{
		$err='La contraseña y su confirmación no coinciden.';
	}
	else
	{
		$err=crm_solicitud_guardar_web(array(
			'nombre'=>isset($_POST['name']) ? $_POST['name'] : '',
			'correo'=>isset($_POST['email']) ? $_POST['email'] : '',
			'telefono'=>isset($_POST['phone']) ? $_POST['phone'] : '',
			'dni'=>isset($_POST['dni']) ? $_POST['dni'] : '',
			'cuil_cuit'=>isset($_POST['cuil_cuit']) ? $_POST['cuil_cuit'] : '',
			'nombre_negocio'=>isset($_POST['nombre_negocio']) ? $_POST['nombre_negocio'] : '',
			'id_localidad'=>isset($_POST['id_localidad']) ? $_POST['id_localidad'] : 0,
			'tipo_autodeclarado'=>isset($_POST['tipo_autodeclarado']) ? $_POST['tipo_autodeclarado'] : 'emprendedor',
			'sector'=>isset($_POST['sector']) ? $_POST['sector'] : '',
			'sector_otro'=>isset($_POST['sector_otro']) ? $_POST['sector_otro'] : '',
			'anios_funcionamiento'=>isset($_POST['anios_funcionamiento']) ? $_POST['anios_funcionamiento'] : '',
			'personas_involucradas'=>isset($_POST['personas_involucradas']) ? $_POST['personas_involucradas'] : '',
			'personal_formal'=>isset($_POST['personal_formal']) && $_POST['personal_formal']=='1',
			'producto_servicio'=>isset($_POST['producto_servicio']) ? $_POST['producto_servicio'] : '',
			'clave'=>$clave
		));
	}
	if($err==='')
	{
		$_SESSION['action_ok']='Solicitud enviada a ADEMI. Cuando la acepten, vas a poder ingresar con este correo y contraseña.';
		echo "<script>window.location.href='login.php'</script>";
		exit();
	}
}

$pv=function($k){ return isset($_POST[$k]) ? htmlspecialchars($_POST[$k]) : ''; };
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>ADEMI | Solicitud de alta</title>
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
  <div class="crm-auth-card" style="max-width:560px;">
    <div style="margin-bottom:12px;">
      <a href="index.php" class="text-muted" style="font-size:13px; text-decoration:none;"><i class="fa fa-arrow-left"></i> Volver atrás</a>
    </div>
    <div class="crm-auth-brand">ADEMI</div>
    <h1 class="crm-auth-title">Solicitud de alta</h1>
    <p class="crm-auth-sub">Completá el formulario y enviá tu solicitud a ADEMI. Si ya tenés cuenta, <a href="login.php">iniciá sesión</a>.</p>
    <?php if($err!='') { ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php } ?>
    <?php if($ok!='') { ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($ok); ?></div>
    <?php } ?>
    <form id="signup" name="signup" class="crm-auth-form" onsubmit="return checkpass();" method="post" autocomplete="off">
      <h5 style="margin:8px 0 12px;">Datos de contacto</h5>
      <div class="form-group">
        <label class="form-label">Nombre y apellido *</label>
        <input type="text" name="name" class="form-control" required value="<?php echo $pv('name'); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Correo electrónico *</label>
        <input type="email" name="email" class="form-control" required value="<?php echo $pv('email'); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Teléfono *</label>
        <input type="text" name="phone" class="form-control" required value="<?php echo $pv('phone'); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Contraseña *</label>
        <div class="crm-pass-wrap">
          <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirmar contraseña *</label>
        <div class="crm-pass-wrap">
          <input type="password" name="cpassword" class="form-control" required minlength="6" autocomplete="new-password">
        </div>
      </div>

      <h5 style="margin:18px 0 12px;">Negocio / emprendimiento</h5>
      <div class="form-group">
        <label class="form-label">Nombre del emprendimiento o empresa *</label>
        <input type="text" name="nombre_negocio" class="form-control" required value="<?php echo $pv('nombre_negocio'); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">¿Cómo te registrás? *</label>
        <div>
          <label class="radio-inline"><input type="radio" name="tipo_autodeclarado" value="emprendedor" <?php echo (!isset($_POST['tipo_autodeclarado']) || $_POST['tipo_autodeclarado']==='emprendedor') ? 'checked' : ''; ?>> Emprendedor / persona</label>
          <label class="radio-inline"><input type="radio" name="tipo_autodeclarado" value="empresa" <?php echo (isset($_POST['tipo_autodeclarado']) && $_POST['tipo_autodeclarado']==='empresa') ? 'checked' : ''; ?>> Empresa</label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">CUIL / CUIT *</label>
        <input type="text" name="cuil_cuit" class="form-control" required placeholder="11 dígitos" value="<?php echo $pv('cuil_cuit'); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">DNI (opcional)</label>
        <input type="text" name="dni" class="form-control" value="<?php echo $pv('dni'); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Provincia *</label>
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
        <label class="form-label">Localidad *</label>
        <select name="id_localidad" id="id_localidad" class="form-control" required>
          <option value="">Elegir localidad</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Sector *</label>
        <select name="sector" id="sector" class="form-control" required>
          <option value="">Elegir…</option>
          <?php foreach($sectores as $k=>$lab) { ?>
          <option value="<?php echo htmlspecialchars($k); ?>" <?php if(isset($_POST['sector']) && $_POST['sector']===$k) echo 'selected'; ?>><?php echo htmlspecialchars($lab); ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="form-group" id="wrap_sector_otro" style="<?php echo (isset($_POST['sector']) && $_POST['sector']==='otro') ? '' : 'display:none;'; ?>">
        <label class="form-label">¿Cuál?</label>
        <input type="text" name="sector_otro" class="form-control" value="<?php echo $pv('sector_otro'); ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Años de funcionamiento *</label>
        <select name="anios_funcionamiento" class="form-control" required>
          <option value="">Elegir…</option>
          <?php foreach($anios_ops as $k=>$lab) { ?>
          <option value="<?php echo htmlspecialchars($k); ?>" <?php if(isset($_POST['anios_funcionamiento']) && $_POST['anios_funcionamiento']===$k) echo 'selected'; ?>><?php echo htmlspecialchars($lab); ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Personas involucradas *</label>
        <select name="personas_involucradas" class="form-control" required>
          <option value="">Elegir…</option>
          <?php foreach($pers_ops as $k=>$lab) { ?>
          <option value="<?php echo htmlspecialchars($k); ?>" <?php if(isset($_POST['personas_involucradas']) && $_POST['personas_involucradas']===$k) echo 'selected'; ?>><?php echo htmlspecialchars($lab); ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">¿Tenés personal contratado de manera formal? *</label>
        <div>
          <label class="radio-inline"><input type="radio" name="personal_formal" value="0" <?php echo (!isset($_POST['personal_formal']) || $_POST['personal_formal']==='0') ? 'checked' : ''; ?>> No</label>
          <label class="radio-inline"><input type="radio" name="personal_formal" value="1" <?php echo (isset($_POST['personal_formal']) && $_POST['personal_formal']==='1') ? 'checked' : ''; ?>> Sí</label>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Productos o servicios (breve)</label>
        <textarea name="producto_servicio" class="form-control" rows="3"><?php echo $pv('producto_servicio'); ?></textarea>
      </div>

      <div class="crm-auth-actions" style="display:flex; gap:10px;">
        <a href="index.php" class="btn btn-white" style="flex:1; text-align:center;">Volver</a>
        <button class="btn btn-primary" style="flex:1;" name="submit" type="submit" value="1">Enviar solicitud a ADEMI</button>
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
var selSector = document.getElementById('sector');
var wrapOtro = document.getElementById('wrap_sector_otro');
if(selSector && wrapOtro)
{
	selSector.onchange = function(){
		wrapOtro.style.display = (this.value==='otro') ? '' : 'none';
	};
}
</script>
</body>
</html>
