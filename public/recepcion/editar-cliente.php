<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
// Ver y modificar un cliente.
crm_proteger_staff('recepcion');
if(!acceso_puede_clientes_de_area('recepcion'))
{
	header('Location: '.acceso_destino_area());
	exit();
}
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

$menu_activo='clientes';

// El id puede venir por la URL o por el formulario (por si al guardar se pierde el ?id=).
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
	header("Location: clientes.php");
	exit();
}

function cliente_dato($fila, $clave)
{
	return (isset($fila[$clave]) && $fila[$clave]!==null) ? $fila[$clave] : '';
}

function cliente_html($texto)
{
	return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function cliente_sql($con, $texto)
{
	return mysqli_real_escape_string($con, $texto);
}

if(isset($_POST['reabrir_atencion']))
{
	$msg=crm_atencion_reabrir(isset($_POST['id_atencion']) ? $_POST['id_atencion'] : 0);
	$_SESSION['msgrecepcion']=($msg=='') ? 'Atención reabierta.' : $msg;
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['cerrar_atencion']))
{
	$msg=crm_atencion_cerrar(isset($_POST['id_atencion']) ? $_POST['id_atencion'] : 0, isset($_POST['cierre_detalle']) ? $_POST['cierre_detalle'] : '');
	$_SESSION['msgrecepcion']=($msg=='') ? 'Atención cerrada.' : $msg;
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['derivar_atencion']))
{
	$id_nuevo=0;
	$msg=crm_atencion_derivar(isset($_POST['id_atencion']) ? $_POST['id_atencion'] : 0, isset($_POST['area_destino']) ? $_POST['area_destino'] : '', isset($_POST['cierre_detalle']) ? $_POST['cierre_detalle'] : '', $id_nuevo);
	if($msg=='')
	{
		$_SESSION['msgrecepcion']='Derivada. El área destino tiene aviso'.($id_nuevo>0 ? ' (turno #'.$id_nuevo.')' : '').'.';
	}
	else
	{
		$_SESSION['msgrecepcion']=$msg;
	}
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['anotar_programa']) && function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa())
{
	$msg=crm_programa_aplicar_cliente($id, isset($_POST['id_programa']) ? $_POST['id_programa'] : 0);
	$_SESSION['msgrecepcion']=($msg=='') ? 'Cliente anotado en el programa.' : $msg;
	echo "<script>window.location.href='editar-cliente.php?id=".$id."&seg=programas'</script>";
	exit();
}
if(isset($_POST['nueva_atencion']))
{
	crm_atencion_abrir(array(
		'id_cliente'=>$id,
		'area'=>isset($_POST['area_nueva']) ? $_POST['area_nueva'] : '',
		'motivo'=>isset($_POST['motivo']) ? $_POST['motivo'] : 'consulta',
		'canal'=>isset($_POST['canal']) ? $_POST['canal'] : 'presencial',
		'detalle'=>isset($_POST['detalle_atencion']) ? $_POST['detalle_atencion'] : ''
	));
	$_SESSION['msgrecepcion']='Atención abierta y aviso enviado al área.';
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}

// Si apretaron Guardar, se actualizan los datos del cliente.
if(isset($_POST['update']))
{
	$err=cliente_actualizar($id, array(
		'nombre'=>isset($_POST['name']) ? $_POST['name'] : '',
		'telefono'=>isset($_POST['mobile']) ? $_POST['mobile'] : '',
		'direccion'=>isset($_POST['address']) ? $_POST['address'] : '',
		'id_localidad'=>isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0,
		'genero'=>isset($_POST['gender']) ? $_POST['gender'] : 'm',
		'cuil_cuit'=>isset($_POST['cuil_cuit']) ? $_POST['cuil_cuit'] : '',
		'area'=>isset($_POST['area_afectada']) ? $_POST['area_afectada'] : '',
		'tipo_cliente'=>isset($_POST['tipo_cliente']) ? $_POST['tipo_cliente'] : 'emprendedor',
		'correo'=>isset($_POST['email']) ? $_POST['email'] : '',
		'representantes'=>cliente_representantes_desde_post()
	));
	if($err=='')
	{
		$_SESSION['msgrecepcion']="Datos actualizados.";
		echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
		exit();
	}
	$_SESSION['msgrecepcion']=$err;
}

$rw=cliente_traer($id);
if(!$rw)
{
	header("Location: clientes.php");
	exit();
}

$genero=cliente_dato($rw, 'gender');
$area_cli=cliente_dato($rw, 'area_afectada');
$tipo_cli=cliente_tipo_normalizar(cliente_dato($rw, 'tipo_cliente'));
$es_org=cliente_es_organizacion($tipo_cli);
$reps=cliente_representantes_de($id);
if(count($reps)==0)
{
	$reps=array(array('id'=>0,'nombre'=>'','telefono'=>'','correo'=>'','id_cliente_persona'=>0));
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Recepción - Editar cliente</title>
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
	else if(document.form1.id_provincia.value=="")
	{
		alert("Falta elegir la provincia.");
		document.form1.id_provincia.focus();
		return false;
	}
	else if(document.form1.id_localidad.value=="")
	{
		alert("Falta elegir la localidad.");
		document.form1.id_localidad.focus();
		return false;
	}
	else if(recEsOrganizacion(document.form1.tipo_cliente.value) && recHayResponsable() && !recRepsDniOk())
	{
		return false;
	}
	else if(document.form1.id_provincia.value=="")
	{
		alert("Falta elegir la provincia.");
		document.form1.id_provincia.focus();
		return false;
	}
	else if(document.form1.id_localidad.value=="")
	{
		alert("Falta elegir la localidad.");
		document.form1.id_localidad.focus();
		return false;
	}
	else if(document.form1.tipo_cliente.value=="persona")
	{
		if(recEraEmpresa && document.form1.clave.value=="")
		{
			alert("Falta la clave de ingreso (mínimo 6 caracteres).");
			document.form1.clave.focus();
			return false;
		}
		if(document.form1.clave.value!="" && document.form1.clave.value.length<6)
		{
			alert("La clave de ingreso debe tener al menos 6 caracteres.");
			document.form1.clave.focus();
			return false;
		}
		if(document.form1.clave.value!=document.form1.clave_confirma.value)
		{
			alert("La clave y su confirmación no coinciden.");
			document.form1.clave_confirma.focus();
			return false;
		}
	}
	else if(document.form1.tipo_cliente.value=="empresa" && !recRepsClaveOk())
	{
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
                    <li><a href="clientes.php">Recepción / Clientes</a></li>
                    <li><a href="#" class="active">Editar</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
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
                        <form name="form1" method="post" action="editar-cliente.php?id=<?php echo intval($id); ?>" onSubmit="return valid();">
                        <input type="hidden" name="id_cliente" value="<?php echo intval($id); ?>">
                                <?php if(isset($_SESSION['msgrecepcion']) && $_SESSION['msgrecepcion']!='') { ?>
                                <div class="alert alert-info"><?php echo cliente_html($_SESSION['msgrecepcion']); $_SESSION['msgrecepcion']=''; ?></div>
                                <?php } ?>

                                <div class="form-group">
                                    <label>Tipo</label>
                                    <select name="tipo_cliente" id="tipo_cliente" class="form-control">
                                        <?php foreach(cliente_tipo_etiquetas() as $clave_t=>$etq_t) { ?>
                                        <option value="<?php echo htmlspecialchars($clave_t); ?>" <?php if($tipo_cli==$clave_t) echo 'selected'; ?>><?php echo htmlspecialchars($etq_t); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label id="etiqueta_nombre"><?php
                                    	if($tipo_cli=='municipalidad') echo 'Nombre de la municipalidad';
                                    	else if($tipo_cli=='organismo') echo 'Nombre del organismo';
                                    	else if($es_org) echo 'Nombre de la empresa';
                                    	else echo 'Nombre completo';
                                    ?></label>
                                    <input type="text" name="name" id="name" value="<?php echo cliente_html(cliente_dato($rw,'name')); ?>" required class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Correo electrónico</label>
                                    <input type="email" name="email" id="email" value="<?php echo cliente_html(cliente_dato($rw,'email')); ?>" required class="form-control"/>
                                </div>
                                <div id="caja_clave" <?php if($tipo_cli=='empresa') echo 'style="display:none;"'; ?>>
                                <div class="form-group">
                                    <label>Nueva clave de ingreso</label>
                                    <input type="password" name="clave" id="clave" value="" class="form-control" autocomplete="new-password"/>
                                </div>
                                <div class="form-group">
                                    <label>Confirmar clave</label>
                                    <input type="password" name="clave_confirma" id="clave_confirma" value="" class="form-control" autocomplete="new-password"/>
                                </div>
                                </div>
                                <div class="form-group">
                                    <label>CUIL / CUIT</label>
                                    <input type="text" name="cuil_cuit" value="<?php echo cliente_html(cliente_dato($rw,'cuil_cuit')); ?>" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="mobile" id="mobile" value="<?php echo cliente_html(cliente_dato($rw,'mobile')); ?>" class="form-control"/>
                                </div>
                                <div class="form-group" id="caja_genero" <?php if($es_org) echo 'style="display:none;"'; ?>>
                                    <label>Género</label>
                                    <select name="gender" class="form-control">
                                        <option value="m" <?php if($genero=='m') echo 'selected'; ?>>Hombre</option>
                                        <option value="f" <?php if($genero=='f') echo 'selected'; ?>>Mujer</option>
                                    </select>
                                </div>
                                <div id="caja_responsable" <?php if(!$es_org) echo 'style="display:none;"'; ?>>
                                <div id="lista_responsables">
                                <?php foreach($reps as $rep) { ?>
                                <div class="bloque-responsable" style="border:1px solid #eee; padding:10px; margin-bottom:8px;">
                                    <input type="hidden" name="representante_id[]" value="<?php echo intval($rep['id']); ?>">
                                    <div class="form-group">
                                        <label>Responsable</label>
                                        <input type="text" name="representante_nombre[]" class="form-control" placeholder="Nombre y apellido" value="<?php echo cliente_html($rep['nombre']); ?>"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Teléfono del responsable</label>
                                        <input type="text" name="representante_telefono[]" class="form-control" value="<?php echo cliente_html($rep['telefono']); ?>"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Correo del responsable</label>
                                        <input type="email" name="representante_correo[]" class="form-control" value="<?php echo cliente_html($rep['correo']); ?>"/>
                                    </div>
                                    <input type="hidden" name="representante_clave[]" value=""/>
                                    <p><button type="button" class="btn btn-white btn-mini btn-quitar-responsable">Quitar</button></p>
                                </div>
                                <?php } ?>
                                </div>
                                <p><button type="button" class="btn btn-white" id="btn_agregar_responsable">Agregar otro responsable</button></p>
                                </div>
                                <div class="form-group">
                                    <label>Provincia</label>
                                    <select name="id_provincia" id="id_provincia" class="form-control">
                                        <option value="">Elegir provincia</option>
                                        <?php
                                        $id_loc_actual=intval(cliente_dato($rw,'id_localidad'));
                                        $id_prov_actual=intval(cliente_dato($rw,'id_provincia'));
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
                                        <option value="<?php echo intval($prov['id_provincia']); ?>" <?php if($id_prov_actual==intval($prov['id_provincia'])) echo 'selected'; ?>><?php echo cliente_html($prov['nombre']); ?></option>
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
                                        <option value="<?php echo intval($loc_item['id']); ?>" <?php if($id_loc_actual==intval($loc_item['id'])) echo 'selected'; ?>><?php echo cliente_html($loc_item['nombre']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo cliente_html(cliente_dato($rw,'address')); ?></textarea>
                                </div>
                                <div class="form-actions">
                                    <a href="clientes.php" class="btn btn-white">Cancelar</a>
                                    <input type="submit" value="Guardar cambios" name="update" class="btn btn-primary">
                                </div>
                        </form>
                        </div>
                        </div>
                        <?php
                        $mostrar_nuevo_turno=true;
                        include BASE_PATH.'/includes/cliente-seguimiento.php';
                        ?>
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
var recEraEmpresa = <?php echo $es_org ? 'true' : 'false'; ?>;
function recEsOrganizacion(tipo)
{
	return (tipo=='empresa' || tipo=='municipalidad' || tipo=='organismo');
}
function recTipoCliente()
{
	var tipo = document.form1.tipo_cliente.value;
	var cajaGen = document.getElementById('caja_genero');
	var cajaRep = document.getElementById('caja_responsable');
	var etiq = document.getElementById('etiqueta_nombre');
	if(recEsOrganizacion(tipo))
	{
		if(cajaGen) { cajaGen.style.display='none'; }
		if(cajaRep) { cajaRep.style.display='block'; }
		if(etiq)
		{
			if(tipo=='municipalidad') { etiq.innerHTML='Nombre de la municipalidad'; }
			else if(tipo=='organismo') { etiq.innerHTML='Nombre del organismo'; }
			else { etiq.innerHTML='Nombre de la empresa'; }
		}
	}
	else
	{
		if(cajaGen) { cajaGen.style.display='block'; }
		if(cajaRep) { cajaRep.style.display='none'; }
		if(etiq) { etiq.innerHTML='Nombre completo'; }
	}
}
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
var recIdLoc = <?php echo intval(cliente_dato($rw,'id_localidad')); ?>;
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
function recHayResponsable()
{
	var noms = document.getElementsByName('representante_nombre[]');
	for(var i=0; i<noms.length; i++)
	{
		if(noms[i].value.replace(/^\s+|\s+$/g,'')!='') { return true; }
	}
	return false;
}
function recRepsDniOk()
{
	var noms = document.getElementsByName('representante_nombre[]');
	var dnis = document.getElementsByName('representante_dni[]');
	var ids = document.getElementsByName('representante_id[]');
	for(var i=0; i<noms.length; i++)
	{
		if(noms[i].value.replace(/^\s+|\s+$/g,'')=='') { continue; }
		var esNuevo = !ids[i] || ids[i].value=='0' || ids[i].value=='';
		var dni = dnis[i] ? dnis[i].value.replace(/\D/g,'') : '';
		if(esNuevo && dni.length<6)
		{
			alert("Falta el DNI del responsable (mínimo 6 números).");
			if(dnis[i]) { dnis[i].focus(); }
			return false;
		}
	}
	return true;
}
function recRepsClaveOk()
{
	return true;
}
function recBloquesResponsable()
{
	var lista = document.getElementById('lista_responsables');
	if(!lista) { return []; }
	var todos = lista.getElementsByClassName('bloque-responsable');
	var arr = [];
	for(var i=0; i<todos.length; i++) { arr.push(todos[i]); }
	return arr;
}
function recArmarBloqueResponsable()
{
	var bloques = recBloquesResponsable();
	if(bloques.length==0) { return null; }
	var n = bloques[0].cloneNode(true);
	var inputs = n.getElementsByTagName('input');
	for(var i=0; i<inputs.length; i++)
	{
		if(inputs[i].name=='representante_id[]') { inputs[i].value='0'; }
		else { inputs[i].value=''; }
	}
	return n;
}
function recAgregarResponsable()
{
	var lista = document.getElementById('lista_responsables');
	var n = recArmarBloqueResponsable();
	if(!lista || !n) { return; }
	lista.appendChild(n);
}
function recQuitarResponsable(btn)
{
	var bloques = recBloquesResponsable();
	if(bloques.length<=1)
	{
		var caja = btn.parentNode.parentNode;
		var inputs = caja.getElementsByTagName('input');
		for(var i=0; i<inputs.length; i++)
		{
			if(inputs[i].name=='representante_id[]') { inputs[i].value='0'; }
			else { inputs[i].value=''; }
		}
		return;
	}
	var caja = btn.parentNode.parentNode;
	if(caja && caja.parentNode) { caja.parentNode.removeChild(caja); }
}
var recBtnAgregar = document.getElementById('btn_agregar_responsable');
if(recBtnAgregar)
{
	recBtnAgregar.onclick = function()
	{
		recAgregarResponsable();
		return false;
	};
}
var recCajaRep = document.getElementById('caja_responsable');
if(recCajaRep)
{
	recCajaRep.onclick = function(ev)
	{
		var t = ev.target || ev.srcElement;
		if(t && t.className && t.className.indexOf('btn-quitar-responsable')>=0)
		{
			recQuitarResponsable(t);
			return false;
		}
	};
}
var recSelTipo = document.getElementById('tipo_cliente');
if(recSelTipo)
{
	recTipoCliente();
	recSelTipo.onchange = recTipoCliente;
}
</script>
</body>
</html>
