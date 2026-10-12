<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
// Alta de clientes. El personal interno se carga en RRHH.
crm_proteger_staff('recepcion');
acceso_exigir_gestion('recepcion');
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

// Cuando se aprieta el boton "Dar de alta" se procesa el formulario.
if(isset($_POST['alta']))
{
	$err=cliente_alta(array(
		'nombre'=>isset($_POST['name']) ? $_POST['name'] : '',
		'correo'=>isset($_POST['email']) ? $_POST['email'] : '',
		'telefono'=>isset($_POST['mobile']) ? $_POST['mobile'] : '',
		'genero'=>isset($_POST['gender']) ? $_POST['gender'] : 'm',
		'direccion'=>isset($_POST['address']) ? $_POST['address'] : '',
		'id_localidad'=>isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0,
		'cuil_cuit'=>isset($_POST['cuil_cuit']) ? $_POST['cuil_cuit'] : '',
		'area'=>isset($_POST['area_afectada']) ? $_POST['area_afectada'] : '',
		'areas'=>isset($_POST['areas']) && is_array($_POST['areas']) ? $_POST['areas'] : array(),
		'tipo_cliente'=>isset($_POST['tipo_cliente']) ? $_POST['tipo_cliente'] : 'emprendedor',
		'dni'=>isset($_POST['dni']) ? $_POST['dni'] : '',
		'origen'=>isset($_POST['origen']) ? $_POST['origen'] : 'presencial',
		'abrir_turno'=>false,
		'representantes'=>cliente_representantes_desde_post()
	));
	if($err=='')
	{
		$_SESSION['msgrecepcion']='Cliente registrado. La atención se carga aparte (Dar turno).';
		echo "<script>window.location.href='clientes.php'</script>";
		exit();
	}
	$_SESSION['msgrecepcion']=$err;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Recepción - Nuevo cliente</title>
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
// Controles que se hacen antes de mandar el formulario,
// para avisarle a la persona sin tener que recargar la pagina.
function valid()
{
	if(document.form1.name.value=="")
	{
		alert("Falta cargar el nombre del cliente.");
		document.form1.name.focus();
		return false;
	}
	else if(document.form1.tipo_cliente.value=="empresa" && !recHayResponsable())
	{
		alert("Falta al menos un responsable de la empresa.");
		return false;
	}
	else if(document.form1.email.value=="")
	{
		alert("Falta cargar el correo del cliente.");
		document.form1.email.focus();
		return false;
	}
	else if(document.form1.id_provincia.value=="")
	{
		alert("Falta elegir la provincia.");
		document.form1.id_provincia.focus();
		return false;
	}
	else if(document.form1.cuil_cuit.value.replace(/\D/g,'').length<8)
	{
		alert("Falta el CUIL o CUIT.");
		document.form1.cuil_cuit.focus();
		return false;
	}
	else if(document.form1.id_localidad.value=="")
	{
		alert("Falta elegir la localidad.");
		document.form1.id_localidad.focus();
		return false;
	}
	else if(document.form1.tipo_cliente.value=="emprendedor")
	{
		if(document.form1.dni.value=="" || document.form1.dni.value.replace(/\D/g,'').length<6)
		{
			alert("Falta el DNI (mínimo 6 números).");
			document.form1.dni.focus();
			return false;
		}
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
		if(document.form1.clave.value=="" || document.form1.clave.value.length<6)
		{
			alert("Falta la clave de ingreso (mínimo 6 caracteres).");
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
      <!-- END SIDEBAR MENU -->
    </div>
  </div>
    <div class="page-content">
        <!-- BEGIN SAMPLE PORTLET CONFIGURATION MODAL FORM-->
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
                    <li><a href="#" class="active"><span class="fa fa-phone"></span> Recepción / Alta de cliente</a>

                    </li>
                </ul>
                <?php
                $menu_activo='clientes';
                include("menu-seccion.php");
                ?>
                <div class="page-title">
                    	<h3>Nuevo cliente</h3>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4>Datos del cliente</h4>
                        </div>
                        <div class="grid-body no-border">
                        <form name="form1" method="post" action="" onSubmit="return valid();">
                                <?php if(isset($_SESSION['msgrecepcion']) && $_SESSION['msgrecepcion']!='') { ?>
                                <div class="alert alert-info"><?php echo htmlspecialchars($_SESSION['msgrecepcion']); $_SESSION['msgrecepcion']=''; ?></div>
                                <?php } ?>

                                <div class="form-group">
                                    <label>Tipo de cliente</label>
                                    <select name="tipo_cliente" id="tipo_cliente" class="form-control">
                                        <?php
                                        $tipo_sel=cliente_tipo_normalizar(crm_campo('tipo_cliente','emprendedor'));
                                        foreach(cliente_tipo_etiquetas() as $clave_t=>$etq_t) { ?>
                                        <option value="<?php echo htmlspecialchars($clave_t); ?>" <?php if($tipo_sel==$clave_t) echo 'selected'; ?>><?php echo htmlspecialchars($etq_t); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p class="muted">El listado se agrupa por este tipo. La atención (turno) se carga después, aparte del alta.</p>
                                </div>
                                <div class="form-group">
                                    <label id="etiqueta_nombre">Nombre completo</label>
                                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars(crm_campo('name')); ?>" required class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Correo electrónico</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars(crm_campo('email')); ?>" required class="form-control"/>
                                </div>
                                <div class="form-group" id="caja_dni">
                                    <label>DNI</label>
                                    <input type="text" name="dni" id="dni" value="<?php echo htmlspecialchars(crm_campo('dni')); ?>" class="form-control"/>
                                    <p class="muted">El DNI se usa para el primer ingreso.</p>
                                </div>
                                <div class="form-group">
                                    <label>De dónde vino</label>
                                    <select name="origen" class="form-control">
                                        <option value="presencial" <?php if(crm_campo('origen','presencial')=='presencial') echo 'selected'; ?>>Presencial</option>
                                        <option value="redes" <?php if(crm_campo('origen')=='redes') echo 'selected'; ?>>Redes</option>
                                        <option value="web" <?php if(crm_campo('origen')=='web') echo 'selected'; ?>>Web</option>
                                        <option value="whatsapp" <?php if(crm_campo('origen')=='whatsapp') echo 'selected'; ?>>WhatsApp</option>
                                    </select>
                                </div>
                                <div id="caja_clave">
                                <div class="form-group">
                                    <label>Clave de ingreso</label>
                                    <input type="password" name="clave" id="clave" value="" class="form-control" autocomplete="new-password"/>
                                </div>
                                <div class="form-group">
                                    <label>Confirmar clave</label>
                                    <input type="password" name="clave_confirma" id="clave_confirma" value="" class="form-control" autocomplete="new-password"/>
                                </div>
                                </div>
                                <div class="form-group">
                                    <label>CUIL / CUIT</label>
                                    <input type="text" name="cuil_cuit" id="cuil_cuit" value="<?php echo htmlspecialchars(crm_campo('cuil_cuit')); ?>" class="form-control" placeholder="Ej: 20-12345678-9"/>
                                </div>
                                <div class="form-group">
                                    <label>Área de interés (opcional)</label>
                                    <select name="area_afectada" id="area_afectada" class="form-control">
                                        <option value="">Ninguna</option>
                                        <option value="recepcion" <?php if(crm_campo('area_afectada')=='recepcion') echo 'selected'; ?>>Recepción</option>
                                        <?php foreach(recepcion_areas_cliente() as $clave=>$titulo) {
                                        	if($clave==='recepcion') { continue; } ?>
                                        <option value="<?php echo htmlspecialchars($clave); ?>" <?php if(crm_campo('area_afectada')==$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p class="muted">No genera atención. Para atenderlo usá Agenda → Agendar atención.</p>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="mobile" id="mobile" value="<?php echo htmlspecialchars(crm_campo('mobile')); ?>" class="form-control"/>
                                </div>
                                <div class="form-group" id="caja_genero">
                                    <label>Género</label>
                                    <select name="gender" class="form-control">
                                        <option value="m" <?php if(crm_campo('gender','m')=='m') echo 'selected'; ?>>Hombre</option>
                                        <option value="f" <?php if(crm_campo('gender')=='f') echo 'selected'; ?>>Mujer</option>
                                    </select>
                                </div>
                                <div id="caja_responsable" style="display:none;">
                                <div id="lista_responsables">
                                <?php
                                $reps_post=cliente_representantes_desde_post();
                                if(count($reps_post)==0)
                                {
                                	$reps_post=array(array('id'=>0,'nombre'=>'','telefono'=>'','correo'=>'','dni'=>''));
                                }
                                foreach($reps_post as $rep) {
                                ?>
                                <div class="bloque-responsable" style="border:1px solid #eee; padding:10px; margin-bottom:8px;">
                                    <input type="hidden" name="representante_id[]" value="<?php echo intval(isset($rep['id']) ? $rep['id'] : 0); ?>">
                                    <div class="form-group">
                                        <label>Responsable</label>
                                        <input type="text" name="representante_nombre[]" class="form-control" placeholder="Nombre y apellido" value="<?php echo htmlspecialchars(isset($rep['nombre']) ? $rep['nombre'] : ''); ?>"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Teléfono del responsable</label>
                                        <input type="text" name="representante_telefono[]" class="form-control" value="<?php echo htmlspecialchars(isset($rep['telefono']) ? $rep['telefono'] : ''); ?>"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Correo del responsable</label>
                                        <input type="email" name="representante_correo[]" class="form-control" value="<?php echo htmlspecialchars(isset($rep['correo']) ? $rep['correo'] : ''); ?>"/>
                                    </div>
                                    <div class="form-group">
                                        <label>DNI del responsable</label>
                                        <input type="text" name="representante_dni[]" class="form-control" value="<?php echo htmlspecialchars(isset($rep['dni']) ? $rep['dni'] : ''); ?>"/>
                                        <p class="muted">El DNI se usa para el primer ingreso.</p>
                                    </div>
                                    <p><button type="button" class="btn btn-white btn-quitar-responsable">Quitar</button></p>
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
                                        $id_prov_sel=intval(crm_campo('id_provincia', $id_prov_misiones));
                                        foreach($provincias as $prov) { ?>
                                        <option value="<?php echo intval($prov['id_provincia']); ?>" <?php if($id_prov_sel==intval($prov['id_provincia'])) echo 'selected'; ?>><?php echo htmlspecialchars($prov['nombre']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Localidad</label>
                                    <select name="id_localidad" id="id_localidad" class="form-control">
                                        <option value="">Elegir localidad</option>
                                    </select>
                                </div>
                                <div id="caja_responsable" style="display:none;">
                                <div id="lista_responsables">
                                <div class="bloque-responsable" style="border:1px solid #eee; padding:10px; margin-bottom:8px;">
                                    <input type="hidden" name="representante_id[]" value="0">
                                    <div class="form-group">
                                        <label>Responsable</label>
                                        <input type="text" name="representante_nombre[]" class="form-control" placeholder="Nombre y apellido"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Teléfono del responsable</label>
                                        <input type="text" name="representante_telefono[]" class="form-control"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Correo del responsable</label>
                                        <input type="email" name="representante_correo[]" class="form-control"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Clave de ingreso del responsable</label>
                                        <input type="password" name="representante_clave[]" class="form-control" autocomplete="new-password"/>
                                    </div>
                                    <p><button type="button" class="btn btn-white btn-quitar-responsable">Quitar</button></p>
                                </div>
                                </div>
                                <p><button type="button" class="btn btn-white" id="btn_agregar_responsable">Agregar otro responsable</button></p>
                                </div>
                                <div class="form-group">
                                    <label>Provincia</label>
                                    <select name="id_provincia" id="id_provincia" class="form-control">
                                        <option value="">Elegir provincia</option>
                                        <?php foreach($provincias as $prov) { ?>
                                        <option value="<?php echo intval($prov['id_provincia']); ?>" <?php if($id_prov_misiones==intval($prov['id_provincia'])) echo 'selected'; ?>><?php echo htmlspecialchars($prov['nombre']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <textarea name="address" id="address" class="form-control" rows="3"><?php echo htmlspecialchars(crm_campo('address')); ?></textarea>
                                </div>
                                <div class="form-actions">
                                    <a href="clientes.php" class="btn btn-white">Cancelar</a>
                                    <input type="submit" value="Dar de alta" name="alta" class="btn btn-primary">
                                </div>
                        </form>
                        </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- END PAGE -->
        </div>

 </div>
<!-- END CONTAINER -->
<!-- BEGIN CORE JS FRAMEWORK--> 
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script> 
<script src="../assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script> 
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/breakpoints.js" type="text/javascript"></script> 
<script src="../assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script> 
<!-- END CORE JS FRAMEWORK --> 
<!-- BEGIN PAGE LEVEL JS --> 	
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>  
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script> 
<!-- END PAGE LEVEL PLUGINS --> 	
<!-- BEGIN CORE TEMPLATE JS --> 
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
if(recSelProv)
{
	recLlenarLocalidades(recSelProv.value, '<?php echo htmlspecialchars(crm_campo('id_localidad')); ?>');
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
	var avisos = n.getElementsByClassName('muted');
	while(avisos.length>0) { avisos[0].parentNode.removeChild(avisos[0]); }
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
function recEsOrganizacion(tipo)
{
	return (tipo=='empresa' || tipo=='municipalidad' || tipo=='organismo');
}
function recTipoCliente()
{
	var tipo = document.form1.tipo_cliente.value;
	var cajaGen = document.getElementById('caja_genero');
	var cajaRep = document.getElementById('caja_responsable');
	var cajaDni = document.getElementById('caja_dni');
	var etiq = document.getElementById('etiqueta_nombre');
	if(recEsOrganizacion(tipo))
	{
		if(cajaGen) { cajaGen.style.display='none'; }
		if(cajaRep) { cajaRep.style.display='block'; }
		if(cajaDni) { cajaDni.style.display='none'; }
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
		if(cajaDni) { cajaDni.style.display='block'; }
		if(etiq) { etiq.innerHTML='Nombre completo'; }
	}
}
var recSelTipo = document.getElementById('tipo_cliente');
if(recSelTipo)
{
	recTipoCliente();
	recSelTipo.onchange = recTipoCliente;
}
</script> 
<!-- END CORE TEMPLATE JS --> 
</body>

</html>
