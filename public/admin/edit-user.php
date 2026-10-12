<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff();
acceso_exigir_super();
clientes_asegurar_columnas();
include_once BASE_PATH.'/includes/provincias.php';

$provincias=provincias_todas();
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

$id=0;
if(isset($_POST['id_cliente']))
{
	$id=intval($_POST['id_cliente']);
}
if($id<=0 && isset($_GET['id']))
{
	$id=intval($_GET['id']);
}
if($id<=0)
{
	header("Location: manage-users.php");
	exit();
}

function admin_cli_dato($fila, $clave)
{
	return (isset($fila[$clave]) && $fila[$clave]!==null) ? $fila[$clave] : '';
}

function admin_cli_html($texto)
{
	return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function admin_cli_sql($con, $texto)
{
	return mysqli_real_escape_string($con, $texto);
}

if(isset($_POST['update']))
{
	$err=cliente_actualizar($id, array(
		'nombre'=>isset($_POST['name']) ? $_POST['name'] : '',
		'correo'=>isset($_POST['email']) ? $_POST['email'] : '',
		'telefono'=>isset($_POST['mobile']) ? $_POST['mobile'] : '',
		'direccion'=>isset($_POST['address']) ? $_POST['address'] : '',
		'id_localidad'=>isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0,
		'genero'=>isset($_POST['gender']) ? $_POST['gender'] : 'm',
		'cuil_cuit'=>isset($_POST['cuil_cuit']) ? $_POST['cuil_cuit'] : '',
		'area'=>isset($_POST['area_afectada']) ? $_POST['area_afectada'] : ''
	));
	if($err=='')
	{
		$_SESSION['msgadmincli']="Datos actualizados.";
		echo "<script>window.location.href='edit-user.php?id=".$id."'</script>";
		exit();
	}
	$_SESSION['msgadmincli']=$err;
}

$rw=cliente_traer($id);
if(!$rw)
{
	header("Location: manage-users.php");
	exit();
}

$genero=admin_cli_dato($rw, 'gender');
$area_cli=admin_cli_dato($rw, 'area_afectada');
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Editar cliente</title>
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
<script language="javascript" type="text/javascript">
function valid()
{
	if(document.form1.name.value=="")
	{
		alert("Falta cargar el nombre del cliente.");
		document.form1.name.focus();
		return false;
	}
	else if(document.form1.email && document.form1.email.value=="")
	{
		alert("Falta cargar el correo electrónico del cliente.");
		document.form1.email.focus();
		return false;
	}
	else if(document.form1.area_afectada.value=="")
	{
		alert("Falta elegir a qué área va el cliente.");
		document.form1.area_afectada.focus();
		return false;
	}
	else if(document.form1.id_provincia && document.form1.id_provincia.value=="")
	{
		alert("Falta elegir la provincia.");
		document.form1.id_provincia.focus();
		return false;
	}
	else if(document.form1.id_localidad && (!document.form1.id_localidad.value || document.form1.id_localidad.value=="0"))
	{
		alert("Falta elegir la localidad.");
		document.form1.id_localidad.focus();
		return false;
	}
	return true;
}
</script>
</head>
<body class="">
<?php crm_header_staff();?>
<div class="page-container row">
      <?php crm_menu_staff();?>
      <div class="clearfix"></div>
    </div>
  </div>
    <div class="page-content">
        <div id="portlet-config" class="modal hide">
            <div class="modal-header">
                <button data-dismiss="modal" class="close" type="button"></button>
                <h3>Configuración de widgets</h3>
            </div>
            <div class="modal-body">Aquí aparecerá la configuración del widget.</div>
        </div>
            <div class="clearfix"></div>
            <div class="content">
                <ul class="breadcrumb">
                    <li>
                        <p>ESTÁS AQUÍ</p>
                    </li>
                    <li><a href="manage-users.php">Clientes</a></li>
                    <li><a href="#" class="active">Editar</a></li>
                </ul>
                <div class="page-title">
                    <h3>Editar cliente</h3>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4>Datos del cliente</h4>
                        </div>
                        <div class="grid-body no-border">
                        <form name="form1" method="post" action="edit-user.php?id=<?php echo intval($id); ?>" onSubmit="return valid();">
                        <input type="hidden" name="id_cliente" value="<?php echo intval($id); ?>">
                                <?php if(isset($_SESSION['msgadmincli']) && $_SESSION['msgadmincli']!='') { ?>
                                <div class="alert alert-info"><?php echo admin_cli_html($_SESSION['msgadmincli']); $_SESSION['msgadmincli']=''; ?></div>
                                <?php } ?>

                                <div class="form-group">
                                    <label>Nombre completo</label>
                                    <input type="text" name="name" id="name" value="<?php echo admin_cli_html(admin_cli_dato($rw,'name')); ?>" required class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Correo electrónico</label>
                                    <input type="email" name="email" id="email" value="<?php echo admin_cli_html(admin_cli_dato($rw,'email')); ?>" required class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>CUIL / CUIT</label>
                                    <input type="text" name="cuil_cuit" value="<?php echo admin_cli_html(admin_cli_dato($rw,'cuil_cuit')); ?>" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Área a la que va</label>
                                    <select name="area_afectada" id="area_afectada" class="form-control">
                                        <option value="">Elegir área</option>
                                        <?php foreach(recepcion_areas_cliente() as $clave=>$titulo) { ?>
                                        <option value="<?php echo admin_cli_html($clave); ?>" <?php if($area_cli==$clave) echo 'selected'; ?>><?php echo admin_cli_html($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="mobile" value="<?php echo admin_cli_html(admin_cli_dato($rw,'mobile')); ?>" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Género</label>
                                    <select name="gender" class="form-control">
                                        <option value="m" <?php if($genero=='m') echo 'selected'; ?>>Hombre</option>
                                        <option value="f" <?php if($genero=='f') echo 'selected'; ?>>Mujer</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Provincia</label>
                                    <select name="id_provincia" id="id_provincia" class="form-control">
                                        <option value="">Elegir provincia</option>
                                        <?php
                                        $id_loc_actual=intval(admin_cli_dato($rw,'id_localidad'));
                                        $id_prov_actual=intval(admin_cli_dato($rw,'id_provincia'));
                                        if($id_prov_actual<=0 && $id_loc_actual>0)
                                        {
                                            $r_lp=acceso_consulta_una("SELECT id_provincia FROM localidad WHERE id_localidad=? LIMIT 1", 'i', array($id_loc_actual));
                                            if($r_lp && intval($r_lp['id_provincia'])>0)
                                            {
                                                $id_prov_actual=intval($r_lp['id_provincia']);
                                            }
                                        }
                                        if($id_prov_actual<=0)
                                        {
                                            $id_prov_actual=$id_prov_misiones;
                                        }
                                        foreach($provincias as $prov) {
                                        ?>
                                        <option value="<?php echo intval($prov['id_provincia']); ?>" <?php if($id_prov_actual==intval($prov['id_provincia'])) echo 'selected'; ?>><?php echo admin_cli_html($prov['nombre']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Localidad</label>
                                    <select name="id_localidad" id="id_localidad" class="form-control">
                                        <option value="">Elegir localidad</option>
                                        <?php
                                        $locs_prov=isset($localidades_por_provincia[$id_prov_actual]) ? $localidades_por_provincia[$id_prov_actual] : (isset($localidades_por_provincia[14]) ? $localidades_por_provincia[14] : array());
                                        foreach($locs_prov as $loc_item) {
                                        ?>
                                        <option value="<?php echo intval($loc_item['id']); ?>" <?php if($id_loc_actual==intval($loc_item['id'])) echo 'selected'; ?>><?php echo admin_cli_html($loc_item['nombre']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo admin_cli_html(admin_cli_dato($rw,'address')); ?></textarea>
                                </div>
                                <div class="form-actions">
                                    <a href="manage-users.php" class="btn btn-white">Cancelar</a>
                                    <input type="submit" value="Guardar cambios" name="update" class="btn btn-primary">
                                </div>
                        </form>
                        </div>
                        </div>
                    </div>
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
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
<script type="text/javascript">
var recLocalidades = <?php echo json_encode($localidades_por_provincia, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function recLlenarLocalidades(idProv, idElegido)
{
	var sel = document.getElementById('id_localidad');
	if(!sel) { return; }
	var lista = recLocalidades[idProv] || recLocalidades[String(idProv)] || [];
	sel.innerHTML = '';
	var vacio = document.createElement('option');
	vacio.value = '';
	vacio.text = 'Elegir localidad';
	sel.appendChild(vacio);
	for(var i=0; i<lista.length; i++)
	{
		var o = document.createElement('option');
		o.value = lista[i].id;
		o.text = lista[i].nombre;
		if(String(lista[i].id) === String(idElegido)) { o.selected = true; }
		sel.appendChild(o);
	}
}
var recSelProv = document.getElementById('id_provincia');
var recIdLoc = <?php echo intval(admin_cli_dato($rw,'id_localidad')); ?>;
if(recSelProv)
{
	var selLocInit = document.getElementById('id_localidad');
	if(!selLocInit || selLocInit.options.length <= 1)
	{
		recLlenarLocalidades(recSelProv.value, recIdLoc);
	}
	recSelProv.onchange = function()
	{
		recLlenarLocalidades(this.value, '');
	};
}
</script>
</body>
</html>
