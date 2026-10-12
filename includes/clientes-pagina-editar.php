<?php
// Editar un cliente del area. Si el cliente pasa a otra area, deja de verse aca.
$area_cli=acceso_area_pantalla();
if(!acceso_puede_clientes_de_area($area_cli))
{
	header('Location: '.acceso_destino_login());
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
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_cli]) ? $etq[$area_cli] : $area_cli;

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

function cliente_area_dato($fila, $clave)
{
	return (isset($fila[$clave]) && $fila[$clave]!==null) ? $fila[$clave] : '';
}

function cliente_area_sql($con, $texto)
{
	return mysqli_real_escape_string($con, $texto);
}

if(isset($_POST['reabrir_atencion']))
{
	$msg=crm_atencion_reabrir(isset($_POST['id_atencion']) ? $_POST['id_atencion'] : 0);
	$_SESSION['msgcliente']=($msg=='') ? 'Atención reabierta. Se puede atender de nuevo.' : $msg;
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['cerrar_atencion']))
{
	$msg=crm_atencion_cerrar(isset($_POST['id_atencion']) ? $_POST['id_atencion'] : 0, isset($_POST['cierre_detalle']) ? $_POST['cierre_detalle'] : '');
	$_SESSION['msgcliente']=($msg=='') ? 'Atención cerrada.' : $msg;
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['derivar_atencion']))
{
	$id_nuevo=0;
	$msg=crm_atencion_derivar(isset($_POST['id_atencion']) ? $_POST['id_atencion'] : 0, isset($_POST['area_destino']) ? $_POST['area_destino'] : '', isset($_POST['cierre_detalle']) ? $_POST['cierre_detalle'] : '', $id_nuevo);
	if($msg=='')
	{
		$_SESSION['msgcliente']='Se derivó. El área destino tiene aviso'.($id_nuevo>0 ? ' (turno #'.$id_nuevo.')' : '').'.';
	}
	else
	{
		$_SESSION['msgcliente']=$msg;
	}
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['anotar_programa']) && function_exists('crm_puede_anotar_programa') && crm_puede_anotar_programa())
{
	$msg=crm_programa_aplicar_cliente($id, isset($_POST['id_programa']) ? $_POST['id_programa'] : 0);
	$_SESSION['msgcliente']=($msg=='') ? 'Cliente anotado en el programa. Las tareas ya están en cada área.' : $msg;
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}
if(isset($_POST['nueva_atencion']))
{
	crm_atencion_abrir(array(
		'id_cliente'=>$id,
		'area'=>$area_cli,
		'motivo'=>isset($_POST['motivo']) ? $_POST['motivo'] : 'consulta',
		'canal'=>isset($_POST['canal']) ? $_POST['canal'] : 'presencial',
		'detalle'=>isset($_POST['detalle_atencion']) ? $_POST['detalle_atencion'] : ''
	));
	$_SESSION['msgcliente']='Atención abierta. El área quedó avisada.';
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
}

if(isset($_POST['derivar_cliente_directo']))
{
	$area_dest = isset($_POST['area_destino']) ? trim($_POST['area_destino']) : '';
	$motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'consulta';
	$canal = isset($_POST['canal']) ? trim($_POST['canal']) : 'presencial';
	$obs = isset($_POST['observaciones_derivacion']) ? trim($_POST['observaciones_derivacion']) : '';

	$err = crm_cliente_derivar($id, $area_dest, $motivo, $obs, $canal);
	if($err === '')
	{
		$etqs = acceso_etiquetas_area();
		$nom_dest = isset($etqs[$area_dest]) ? $etqs[$area_dest] : $area_dest;
		$_SESSION['msgcliente'] = 'Cliente derivado con éxito a '.$nom_dest.'. La derivación quedó pendiente de recepción.';
	}
	else
	{
		$_SESSION['msgcliente'] = $err;
	}
	echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
	exit();
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
		'area'=>isset($_POST['area_afectada']) ? $_POST['area_afectada'] : '',
		'tipo_cliente'=>isset($_POST['tipo_cliente']) ? $_POST['tipo_cliente'] : ''
	));
	if($err=='')
	{
		$_SESSION['msgcliente']="Datos actualizados.";
		echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
		exit();
	}
	$_SESSION['msgcliente']=$err;
}

$rw=cliente_traer($id);
if(!$rw)
{
	header("Location: clientes.php");
	exit();
}
if(!crm_cliente_puede_ver($id, $area_cli))
{
	header("Location: clientes.php");
	exit();
}

$genero=cliente_area_dato($rw, 'gender');
$area_sel=cliente_area_dato($rw, 'area_afectada');
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Editar cliente</title>
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
	if(document.form1.email && document.form1.email.value=="")
	{
		alert("Falta cargar el correo electrónico del cliente.");
		document.form1.email.focus();
		return false;
	}
	if(document.form1.id_provincia && document.form1.id_provincia.value=="")
	{
		alert("Falta elegir la provincia.");
		document.form1.id_provincia.focus();
		return false;
	}
	if(document.form1.id_localidad && (!document.form1.id_localidad.value || document.form1.id_localidad.value=="0"))
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
            <div class="clearfix"></div>
            <div class="content">
                <ul class="breadcrumb">
                    <li>
                        <p>ESTÁS AQUÍ</p>
                    </li>
                    <li><a href="clientes.php"><?php echo texto_mostrar($nombre_area); ?> / Clientes</a></li>
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
                                <?php if(isset($_SESSION['msg_cliente_exito']) && $_SESSION['msg_cliente_exito']!='') { ?>
                                <div class="alert alert-success"><button class="close" data-dismiss="alert"></button><i class="fa fa-check"></i> <?php echo texto_mostrar($_SESSION['msg_cliente_exito']); unset($_SESSION['msg_cliente_exito']); ?></div>
                                <?php } ?>
                                <?php if(isset($_SESSION['msgcliente']) && $_SESSION['msgcliente']!='') { ?>
                                <div class="alert alert-info"><?php echo texto_mostrar($_SESSION['msgcliente']); $_SESSION['msgcliente']=''; ?></div>
                                <?php } ?>

                                <div class="form-group">
                                    <label>Tipo</label>
                                    <select name="tipo_cliente" class="form-control">
                                        <?php
                                        $tipo_sel=cliente_tipo_normalizar(cliente_area_dato($rw,'tipo_cliente'));
                                        foreach(cliente_tipo_etiquetas() as $clave_t=>$etq_t) { ?>
                                        <option value="<?php echo htmlspecialchars($clave_t); ?>" <?php if($tipo_sel==$clave_t) echo 'selected'; ?>><?php echo htmlspecialchars($etq_t); ?></option>
                                        <?php } ?>
                                    </select>
                                    <p class="muted">El tipo define cómo se agrupa el cliente en todo el CRM.</p>
                                </div>
                                <div class="form-group">
                                    <label>Nombre completo</label>
                                    <input type="text" name="name" id="name" value="<?php echo texto_mostrar(cliente_area_dato($rw,'name')); ?>" required class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Correo electrónico</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars(cliente_area_dato($rw,'email'), ENT_QUOTES, 'UTF-8'); ?>" required class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>CUIL / CUIT</label>
                                    <input type="text" name="cuil_cuit" value="<?php echo texto_mostrar(cliente_area_dato($rw,'cuil_cuit')); ?>" class="form-control"/>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="mobile" id="mobile" value="<?php echo texto_mostrar(cliente_area_dato($rw,'mobile')); ?>" class="form-control"/>
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
                                        $id_loc_actual=intval(cliente_area_dato($rw,'id_localidad'));
                                        $id_prov_actual=intval(cliente_area_dato($rw,'id_provincia'));
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
                                        <option value="<?php echo intval($prov['id_provincia']); ?>" <?php if($id_prov_actual==intval($prov['id_provincia'])) echo 'selected'; ?>><?php echo htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
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
                                        <option value="<?php echo intval($loc_item['id']); ?>" <?php if($id_loc_actual==intval($loc_item['id'])) echo 'selected'; ?>><?php echo htmlspecialchars($loc_item['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <textarea name="address" class="form-control" rows="3"><?php echo texto_mostrar(cliente_area_dato($rw,'address')); ?></textarea>
                                </div>
                                <div class="form-actions">
                                    <a href="clientes.php" class="btn btn-white">Cancelar</a>
                                    <input type="submit" value="Guardar cambios" name="update" class="btn btn-primary">
                                </div>
                        </form>
                        </div>
                        </div>

                        <?php
                        $mostrar_nuevo_turno=false;
                        include BASE_PATH.'/includes/cliente-seguimiento.php';
                        ?>

                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4>Abrir turno en esta área</h4>
                        </div>
                        <div class="grid-body no-border">
                            <form method="post" action="">
                                <input type="hidden" name="id_cliente" value="<?php echo intval($id); ?>">
                                <div class="form-group">
                                    <label>Motivo</label>
                                    <select name="motivo" class="form-control">
                                        <option value="consulta">Consulta</option>
                                        <option value="documentacion">Documentación</option>
                                        <option value="seguimiento">Seguimiento</option>
                                        <option value="tramite">Trámite</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Canal</label>
                                    <select name="canal" class="form-control">
                                        <option value="presencial">Presencial</option>
                                        <option value="web">Web</option>
                                        <option value="redes">Redes</option>
                                        <option value="whatsapp">WhatsApp</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Nota</label>
                                    <input type="text" name="detalle_atencion" class="form-control"/>
                                </div>
                                <button type="submit" name="nueva_atencion" value="1" class="btn btn-white">Abrir turno</button>
                            </form>
                        </div>
                        </div>

                        <?php
                        $puede_derivar_area = function_exists('crm_areas_destino_derivacion');
                        $destinos_disp = $puede_derivar_area ? crm_areas_destino_derivacion($area_cli) : array();
                        if(count($destinos_disp) > 0) {
                        ?>
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4><i class="fa fa-share text-info"></i> Derivar cliente a otra área</h4>
                        </div>
                        <div class="grid-body no-border">
                            <p class="muted">Derivá este cliente a otra área operativa de ADEMI para su atención o seguimiento.</p>
                            <form method="post" action="">
                                <input type="hidden" name="id_cliente" value="<?php echo intval($id); ?>">
                                <div class="form-group">
                                    <label>Área de destino</label>
                                    <select name="area_destino" class="form-control" required>
                                        <option value="">Elegir área destinataria...</option>
                                        <?php foreach($destinos_disp as $c_dest => $n_dest) { ?>
                                        <option value="<?php echo htmlspecialchars($c_dest); ?>"><?php echo htmlspecialchars($n_dest); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Motivo</label>
                                    <select name="motivo" class="form-control">
                                        <option value="consulta">Consulta</option>
                                        <option value="documentacion">Documentación</option>
                                        <option value="seguimiento">Seguimiento</option>
                                        <option value="tramite">Trámite</option>
                                        <option value="otro">Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Canal de contacto</label>
                                    <select name="canal" class="form-control">
                                        <option value="presencial">Presencial</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="web">Web</option>
                                        <option value="redes">Redes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Observaciones / Motivo de derivación</label>
                                    <textarea name="observaciones_derivacion" class="form-control" rows="2" placeholder="Indicá las observaciones para el área de destino..."></textarea>
                                </div>
                                <button type="submit" name="derivar_cliente_directo" value="1" class="btn btn-primary"><i class="fa fa-share"></i> Derivar cliente</button>
                            </form>
                        </div>
                        </div>
                        <?php } ?>
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
var recIdLoc = <?php echo intval(cliente_area_dato($rw,'id_localidad')); ?>;
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
