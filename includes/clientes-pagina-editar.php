<?php
// Editar un cliente del area. Si el cliente pasa a otra area, deja de verse aca.
$area_cli=acceso_area_pantalla();
if(!acceso_puede_clientes_de_area($area_cli))
{
	header('Location: '.acceso_destino_login());
	exit();
}
$modo_ficha_solo_lectura=isset($_GET['vista']) && is_string($_GET['vista']) && $_GET['vista']==='ficha';
if($modo_ficha_solo_lectura && isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD'])==='POST')
{
	http_response_code(403);
	header('Content-Type: text/plain; charset=UTF-8');
	exit('Acceso denegado.');
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
if(!$modo_ficha_solo_lectura && isset($_POST['id_cliente']))
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

// Autorizar el registro antes de procesar cualquier operación POST.
$rw=cliente_traer($id);
if(!$rw || !crm_cliente_puede_ver($id, $area_cli))
{
	header("Location: clientes.php");
	exit();
}
$clientes_solo_lectura=crm_cliente_area_solo_lectura($area_cli);
if(($clientes_solo_lectura || $modo_ficha_solo_lectura) && isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD'])==='POST')
{
	http_response_code(403);
	header('Content-Type: text/plain; charset=UTF-8');
	exit('Acceso denegado.');
}
$empresa_seguimiento_post_resultado=array('error'=>'', 'valores'=>array(), 'accion'=>'');
if($area_cli==='empresas')
{
	require_once BASE_PATH.'/includes/empresas-seguimiento.php';
	$empresa_seguimiento_post_resultado=empresa_seguimiento_procesar_post($id, $area_cli, $clientes_solo_lectura);
}

function cliente_area_dato($fila, $clave)
{
	return (isset($fila[$clave]) && $fila[$clave]!==null) ? $fila[$clave] : '';
}

function cliente_area_sql($con, $texto)
{
	return mysqli_real_escape_string($con, $texto);
}

$cliente_modal_abierto='';
$cliente_modal_error='';
$cliente_modal_valores=array();

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
	unset($_SESSION['msgrecepcion']);
	$id_atencion_nueva=crm_atencion_abrir(array(
		'id_cliente'=>$id,
		'area'=>$area_cli,
		'motivo'=>isset($_POST['motivo']) ? $_POST['motivo'] : 'consulta',
		'canal'=>isset($_POST['canal']) ? $_POST['canal'] : 'presencial',
		'detalle'=>isset($_POST['detalle_atencion']) ? $_POST['detalle_atencion'] : ''
	));
	if($id_atencion_nueva>0)
	{
		$_SESSION['msgcliente']='Atención abierta. El área quedó avisada.';
		echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
		exit();
	}
	$cliente_modal_abierto='turno';
	$cliente_modal_error=(isset($_SESSION['msgrecepcion']) && trim((string)$_SESSION['msgrecepcion'])!=='') ? $_SESSION['msgrecepcion'] : 'No se pudo abrir el turno. Revisá los datos e intentá nuevamente.';
	unset($_SESSION['msgrecepcion']);
	$cliente_modal_valores=$_POST;
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
		$cliente_modal_abierto='derivacion';
		$cliente_modal_error=$err;
		$cliente_modal_valores=$_POST;
	}
	if($err==='')
	{
		echo "<script>window.location.href='editar-cliente.php?id=".$id."'</script>";
		exit();
	}
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

$genero=cliente_area_dato($rw, 'gender');
$area_sel=cliente_area_dato($rw, 'area_afectada');
$id_provincia_cliente=intval(cliente_area_dato($rw, 'id_provincia'));
if($id_provincia_cliente<=0 && intval(cliente_area_dato($rw, 'id_localidad'))>0)
{
	$provincia_cliente=acceso_consulta_una("SELECT id_provincia FROM localidad WHERE id_localidad=? LIMIT 1", 'i', array(intval(cliente_area_dato($rw, 'id_localidad'))));
	$id_provincia_cliente=$provincia_cliente ? intval($provincia_cliente['id_provincia']) : 0;
}
$nombre_provincia_cliente='No registrada';
foreach($provincias as $provincia_item)
{
	if(intval($provincia_item['id_provincia'])===$id_provincia_cliente)
	{
		$nombre_provincia_cliente=$provincia_item['nombre'];
		break;
	}
}
$etiquetas_area_cliente=acceso_etiquetas_area();
$area_cliente_texto=isset($etiquetas_area_cliente[$area_sel]) ? $etiquetas_area_cliente[$area_sel] : $area_sel;

// La ficha de consulta se resuelve antes de cargar historial y acciones operativas.
// Comparte la autorización del cliente, pero no expone formularios ni controles de edición.
if($modo_ficha_solo_lectura)
{
	$valor_ficha=function($valor) {
		return ($valor===null || trim((string)$valor)==='') ? 'No registrado' : (string)$valor;
	};
	$tipo_cliente_ficha=$valor_ficha(cliente_tipo_etiqueta(cliente_area_dato($rw, 'tipo_cliente')));
	$nombre_cliente_ficha=$valor_ficha(cliente_area_dato($rw, 'name'));
	$dni_cliente_ficha=$valor_ficha(cliente_area_dato($rw, 'dni'));
	$correo_cliente_ficha=$valor_ficha(cliente_area_dato($rw, 'email'));
	$cuil_cliente_ficha=$valor_ficha(cliente_area_dato($rw, 'cuil_cuit'));
	$telefono_cliente_ficha=$valor_ficha(cliente_area_dato($rw, 'mobile'));
	$provincia_cliente_ficha=$valor_ficha($nombre_provincia_cliente);
	$localidad_cliente_ficha=$valor_ficha(cliente_area_dato($rw, 'localidad'));
	$direccion_cliente_ficha=$valor_ficha(cliente_area_dato($rw, 'address'));
	$area_cliente_ficha=$valor_ficha($area_cliente_texto);
	$editar_cliente_url='editar-cliente.php?id='.intval($id);
	$cliente_legajo_modo_consulta=true;
	?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>CRM | <?php echo texto_mostrar($nombre_cliente_ficha); ?> - Ficha del cliente</title>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css" />
<link href="../assets/css/style.css" rel="stylesheet" type="text/css" />
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css" />
<style>
.cliente-ficha-lectura { width:100%; max-width:none; margin:0; }
.cliente-ficha-seccion { margin-bottom:18px; }
.cliente-ficha-seccion .grid-title h4 { margin:0; }
.cliente-ficha-datos { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 28px; }
.cliente-ficha-dato { padding:11px 0; border-bottom:1px solid #e7edf3; min-width:0; }
.cliente-ficha-dato dt { color:#64748b; font-size:12px; font-weight:600; margin-bottom:4px; }
.cliente-ficha-dato dd { color:#24364b; margin:0; overflow-wrap:anywhere; white-space:pre-wrap; }
.cliente-ficha-acciones { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 18px; }
#clienteFichaPrint { display:none; }
@media (max-width:767px) { .cliente-ficha-datos { grid-template-columns:1fr; } }
@media print {
	@page { size:A4 portrait; margin:13mm 14mm; }
	* { box-sizing:border-box; }
	body.crm-cliente-ficha-lectura { background:#fff !important; color:#1e293b !important; font:10.5pt/1.4 Arial,sans-serif !important; }
	body.crm-cliente-ficha-lectura > *:not(#clienteFichaPrint) { display:none !important; }
	body.crm-cliente-ficha-lectura #clienteFichaPrint { display:block !important; width:100% !important; margin:0 !important; padding:0 !important; color:#1e293b !important; font:10.5pt/1.4 Arial,sans-serif !important; }
	#clienteFichaPrint .cliente-ficha-print-cabecera { border-bottom:2px solid #2563eb; padding-bottom:9px; margin-bottom:0; }
	#clienteFichaPrint .cliente-ficha-marca { color:#334155; font-size:9pt; letter-spacing:.14em; font-weight:700; }
	#clienteFichaPrint h1 { color:#17365d; font-size:19pt; margin:4px 0 2px; }
	#clienteFichaPrint .cliente-ficha-print-subtitulo { margin:0; color:#64748b; }
	#clienteFichaPrint .cliente-ficha-print-emision { margin-top:7px; color:#64748b; font-size:8.5pt; }
	#clienteFichaPrint h2 { color:#1e3a5f; font-size:11pt; border-bottom:1px solid #cbd5e1; padding-bottom:5px; margin:17px 0 9px; }
	#clienteFichaPrint .cliente-ficha-print-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 18px; }
	#clienteFichaPrint .cliente-ficha-print-item { min-width:0; overflow-wrap:anywhere; break-inside:avoid; page-break-inside:avoid; }
	#clienteFichaPrint .cliente-ficha-print-label { display:block; color:#64748b; font-size:8pt; text-transform:uppercase; letter-spacing:.06em; margin-bottom:2px; }
	#clienteFichaPrint .cliente-ficha-print-footer { margin-top:22px; padding-top:7px; border-top:1px solid #cbd5e1; color:#64748b; font-size:8pt; text-align:center; }
}
</style>
</head>
<body class="crm-cliente-ficha-lectura">
<?php crm_header_staff(); ?>
<div class="page-container row">
	<?php crm_menu_staff(); ?>
	<div class="clearfix"></div>
</div>
</div>
<div class="page-content">
		<div class="content cliente-ficha-lectura">
			<ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="clientes.php"><?php echo texto_mostrar($nombre_area); ?> / Clientes</a></li><li><a href="#" class="active">Ficha</a></li></ul>
			<?php include("menu-seccion.php"); ?>
			<div class="page-title"><h3>Ficha del cliente</h3></div>
			<div class="cliente-ficha-acciones no-print">
				<button type="button" class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Imprimir ficha</button>
				<?php if(!$clientes_solo_lectura) { ?><a class="btn btn-white" href="<?php echo htmlspecialchars($editar_cliente_url, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-pencil"></i> Editar cliente</a><?php } ?>
				<a class="btn btn-white" href="clientes.php"><i class="fa fa-arrow-left"></i> Volver</a>
			</div>
			<div class="grid simple cliente-ficha-seccion">
				<div class="grid-title"><h4>Datos personales</h4></div>
				<div class="grid-body"><dl class="cliente-ficha-datos">
					<div class="cliente-ficha-dato"><dt>Nombre completo</dt><dd><?php echo texto_mostrar($nombre_cliente_ficha); ?></dd></div>
					<div class="cliente-ficha-dato"><dt>Tipo de cliente</dt><dd><?php echo texto_mostrar($tipo_cliente_ficha); ?></dd></div>
					<div class="cliente-ficha-dato"><dt>DNI</dt><dd><?php echo texto_mostrar($dni_cliente_ficha); ?></dd></div>
					<div class="cliente-ficha-dato"><dt>CUIL / CUIT</dt><dd><?php echo texto_mostrar($cuil_cliente_ficha); ?></dd></div>
				</dl></div>
			</div>
			<div class="grid simple cliente-ficha-seccion">
				<div class="grid-title"><h4>Contacto</h4></div>
				<div class="grid-body"><dl class="cliente-ficha-datos">
					<div class="cliente-ficha-dato"><dt>Correo electrónico</dt><dd><?php echo texto_mostrar($correo_cliente_ficha); ?></dd></div>
					<div class="cliente-ficha-dato"><dt>Teléfono</dt><dd><?php echo texto_mostrar($telefono_cliente_ficha); ?></dd></div>
					<div class="cliente-ficha-dato"><dt>Dirección</dt><dd><?php echo texto_mostrar($direccion_cliente_ficha); ?></dd></div>
					<div class="cliente-ficha-dato"><dt>Localidad</dt><dd><?php echo texto_mostrar($localidad_cliente_ficha); ?></dd></div>
					<div class="cliente-ficha-dato"><dt>Provincia</dt><dd><?php echo texto_mostrar($provincia_cliente_ficha); ?></dd></div>
				</dl></div>
			</div>
			<?php include BASE_PATH.'/includes/clientes-areas-involucradas.php'; ?>
			<?php include BASE_PATH.'/includes/clientes-legajo-seccion.php'; ?>
			<?php include BASE_PATH.'/includes/clientes-historial-institucional.php'; ?>
			<?php if($area_cli==='empresas' && cliente_tipo_normalizar(cliente_area_dato($rw, 'tipo_cliente'))==='empresa') { empresa_seguimiento_render($id,$rw,$area_cli,false,array(),true); } ?>
		</div>
</div>
</div>
<main id="clienteFichaPrint" aria-hidden="true">
	<header class="cliente-ficha-print-cabecera"><div class="cliente-ficha-marca">ADEMI · INFORME DE CLIENTES</div><h1><?php echo texto_mostrar($nombre_cliente_ficha); ?></h1><p class="cliente-ficha-print-subtitulo"><?php echo texto_mostrar($tipo_cliente_ficha); ?></p><div class="cliente-ficha-print-emision">Emitida el <?php echo date('d/m/Y'); ?></div></header>
	<section><h2>Datos personales</h2><div class="cliente-ficha-print-grid">
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Nombre completo</span><?php echo texto_mostrar($nombre_cliente_ficha); ?></div>
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Tipo de cliente</span><?php echo texto_mostrar($tipo_cliente_ficha); ?></div>
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">DNI</span><?php echo texto_mostrar($dni_cliente_ficha); ?></div>
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">CUIL / CUIT</span><?php echo texto_mostrar($cuil_cliente_ficha); ?></div>
	</div></section>
	<section><h2>Contacto</h2><div class="cliente-ficha-print-grid">
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Correo electrónico</span><?php echo texto_mostrar($correo_cliente_ficha); ?></div>
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Teléfono</span><?php echo texto_mostrar($telefono_cliente_ficha); ?></div>
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Dirección</span><?php echo texto_mostrar($direccion_cliente_ficha); ?></div>
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Localidad</span><?php echo texto_mostrar($localidad_cliente_ficha); ?></div>
		<div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Provincia</span><?php echo texto_mostrar($provincia_cliente_ficha); ?></div>
	</div></section>
	<section><h2>Área de referencia registrada</h2><div class="cliente-ficha-print-grid"><div class="cliente-ficha-print-item"><span class="cliente-ficha-print-label">Área asignada al cliente</span><?php echo texto_mostrar($area_cliente_ficha); ?></div></div></section>
	<footer class="cliente-ficha-print-footer">ADEMI · Documento generado por el sistema institucional</footer>
</main>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script>window.addEventListener('beforeprint', function(){ document.body.classList.add('crm-cliente-ficha-lectura'); });</script>
</body>
</html>
	<?php
	exit();
}

// El historial institucional se consulta y pagina en el punto de renderizado.
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
<style type="text/css">
.cliente-acciones-operativas { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px; }
#modalAbrirTurno .modal-body, #modalDerivarCliente .modal-body { max-height:calc(100vh - 190px); overflow-y:auto; }
@media (max-width:767px) {
	.cliente-acciones-operativas { align-items:stretch; flex-direction:column; }
	.cliente-acciones-operativas .btn { width:100%; }
	#modalAbrirTurno .modal-dialog, #modalDerivarCliente .modal-dialog { margin:10px; }
	#modalAbrirTurno .modal-body, #modalDerivarCliente .modal-body { max-height:calc(100vh - 150px); }
}
</style>
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
                    <li><a href="#" class="active"><?php echo $clientes_solo_lectura ? 'Ficha' : 'Editar'; ?></a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3><?php echo $clientes_solo_lectura ? 'Ficha del cliente' : 'Editar cliente'; ?></h3>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                        <div class="grid-title no-border">
                            <h4>Datos del cliente</h4>
                        </div>
                        <div class="grid-body no-border">
                        <?php if($clientes_solo_lectura) { ?>
                        <dl class="dl-horizontal">
                            <dt>Tipo</dt><dd><?php echo texto_mostrar(cliente_tipo_etiqueta(cliente_area_dato($rw, 'tipo_cliente'))); ?></dd>
                            <dt>Nombre completo</dt><dd><?php echo texto_mostrar(cliente_area_dato($rw, 'name')); ?></dd>
                            <dt>Correo electrónico</dt><dd><?php echo texto_mostrar(cliente_area_dato($rw, 'email')); ?></dd>
                            <dt>CUIL / CUIT</dt><dd><?php echo texto_mostrar(cliente_area_dato($rw, 'cuil_cuit')); ?></dd>
                            <dt>Teléfono</dt><dd><?php echo texto_mostrar(cliente_area_dato($rw, 'mobile')); ?></dd>
                            <dt>Provincia</dt><dd><?php echo texto_mostrar($nombre_provincia_cliente); ?></dd>
                            <dt>Localidad</dt><dd><?php echo texto_mostrar(cliente_area_dato($rw, 'localidad')); ?></dd>
                            <dt>Área afectada</dt><dd><?php echo texto_mostrar($area_cliente_texto); ?></dd>
                            <dt>Dirección</dt><dd><?php echo nl2br(texto_mostrar(cliente_area_dato($rw, 'address'))); ?></dd>
                        </dl>
                        <?php } else { ?>
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
                        <?php } ?>
                        </div>
                        </div>
						<?php $cliente_legajo_modo_consulta=!empty($clientes_solo_lectura); include BASE_PATH.'/includes/clientes-legajo-seccion.php'; ?>

                        <?php if($area_cli==='empresas' && cliente_tipo_normalizar(cliente_area_dato($rw, 'tipo_cliente'))==='empresa') { empresa_seguimiento_render($id,$rw,$area_cli,!$clientes_solo_lectura,$empresa_seguimiento_post_resultado,false); } ?>

                        <?php if(!$clientes_solo_lectura) { ?>
                        <?php
                        $puede_derivar_area = function_exists('crm_areas_destino_derivacion');
                        $destinos_disp = $puede_derivar_area ? crm_areas_destino_derivacion($area_cli) : array();
                        ?>
                        <div class="cliente-acciones-operativas">
                            <button type="button" class="btn btn-white" data-toggle="modal" data-target="#modalAbrirTurno"><i class="fa fa-calendar"></i> Abrir turno en esta área</button>
                            <?php if(count($destinos_disp)>0) { ?><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalDerivarCliente"><i class="fa fa-share"></i> Derivar cliente a otra área</button><?php } ?>
                        </div>
                        <div class="modal fade" id="modalAbrirTurno" tabindex="-1" role="dialog" aria-labelledby="modalAbrirTurnoTitulo" aria-hidden="true">
                            <div class="modal-dialog" role="document"><div class="modal-content">
                                <form method="post" action="editar-cliente.php?id=<?php echo intval($id); ?>">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                                        <h4 class="modal-title" id="modalAbrirTurnoTitulo">Abrir turno en esta área</h4>
                                    </div>
                                    <div class="modal-body">
                                        <?php if($cliente_modal_abierto==='turno' && $cliente_modal_error!=='') { ?><div class="alert alert-danger" role="alert"><?php echo texto_mostrar($cliente_modal_error); ?></div><?php } ?>
                                        <input type="hidden" name="id_cliente" value="<?php echo intval($id); ?>">
                                        <div class="form-group">
                                            <label>Motivo</label>
                                            <select name="motivo" class="form-control">
                                                <?php foreach(array('consulta'=>'Consulta','documentacion'=>'Documentación','seguimiento'=>'Seguimiento','tramite'=>'Trámite','otro'=>'Otro') as $valor_motivo=>$etiqueta_motivo) { ?>
                                                <option value="<?php echo htmlspecialchars($valor_motivo, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($cliente_modal_valores['motivo']) && $cliente_modal_valores['motivo']===$valor_motivo) ? 'selected' : ''; ?>><?php echo htmlspecialchars($etiqueta_motivo, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Canal</label>
                                            <select name="canal" class="form-control">
                                                <?php foreach(array('presencial'=>'Presencial','web'=>'Web','redes'=>'Redes','whatsapp'=>'WhatsApp') as $valor_canal=>$etiqueta_canal) { ?>
                                                <option value="<?php echo htmlspecialchars($valor_canal, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($cliente_modal_valores['canal']) && $cliente_modal_valores['canal']===$valor_canal) ? 'selected' : ''; ?>><?php echo htmlspecialchars($etiqueta_canal, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Nota</label>
                                            <input type="text" name="detalle_atencion" class="form-control" value="<?php echo isset($cliente_modal_valores['detalle_atencion']) ? htmlspecialchars((string)$cliente_modal_valores['detalle_atencion'], ENT_QUOTES, 'UTF-8') : ''; ?>" />
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="nueva_atencion" value="1" class="btn btn-primary">Abrir turno</button>
                                    </div>
                                </form>
                            </div></div>
                        </div>

                        <?php if(count($destinos_disp)>0) { ?>
                        <div class="modal fade" id="modalDerivarCliente" tabindex="-1" role="dialog" aria-labelledby="modalDerivarClienteTitulo" aria-hidden="true">
                            <div class="modal-dialog" role="document"><div class="modal-content">
                                <form method="post" action="editar-cliente.php?id=<?php echo intval($id); ?>">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                                        <h4 class="modal-title" id="modalDerivarClienteTitulo"><i class="fa fa-share text-info"></i> Derivar cliente a otra área</h4>
                                    </div>
                                    <div class="modal-body">
                                        <?php if($cliente_modal_abierto==='derivacion' && $cliente_modal_error!=='') { ?><div class="alert alert-danger" role="alert"><?php echo texto_mostrar($cliente_modal_error); ?></div><?php } ?>
                                        <p class="muted">Derivá este cliente a otra área operativa de ADEMI para su atención o seguimiento.</p>
                                        <input type="hidden" name="id_cliente" value="<?php echo intval($id); ?>">
                                        <div class="form-group">
                                            <label>Área de destino</label>
                                            <select name="area_destino" class="form-control" required>
                                                <option value="">Elegir área destinataria...</option>
                                                <?php foreach($destinos_disp as $c_dest=>$n_dest) { ?>
                                                <option value="<?php echo htmlspecialchars($c_dest, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($cliente_modal_valores['area_destino']) && $cliente_modal_valores['area_destino']===$c_dest) ? 'selected' : ''; ?>><?php echo htmlspecialchars($n_dest, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Motivo</label>
                                            <select name="motivo" class="form-control">
                                                <?php foreach(array('consulta'=>'Consulta','documentacion'=>'Documentación','seguimiento'=>'Seguimiento','tramite'=>'Trámite','otro'=>'Otro') as $valor_motivo=>$etiqueta_motivo) { ?>
                                                <option value="<?php echo htmlspecialchars($valor_motivo, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($cliente_modal_valores['motivo']) && $cliente_modal_valores['motivo']===$valor_motivo) ? 'selected' : ''; ?>><?php echo htmlspecialchars($etiqueta_motivo, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Canal de contacto</label>
                                            <select name="canal" class="form-control">
                                                <?php foreach(array('presencial'=>'Presencial','whatsapp'=>'WhatsApp','web'=>'Web','redes'=>'Redes') as $valor_canal=>$etiqueta_canal) { ?>
                                                <option value="<?php echo htmlspecialchars($valor_canal, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($cliente_modal_valores['canal']) && $cliente_modal_valores['canal']===$valor_canal) ? 'selected' : ''; ?>><?php echo htmlspecialchars($etiqueta_canal, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Observaciones / Motivo de derivación</label>
                                            <textarea name="observaciones_derivacion" class="form-control" rows="2" placeholder="Indicá las observaciones para el área de destino..."><?php echo isset($cliente_modal_valores['observaciones_derivacion']) ? htmlspecialchars((string)$cliente_modal_valores['observaciones_derivacion'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" name="derivar_cliente_directo" value="1" class="btn btn-primary"><i class="fa fa-share"></i> Derivar cliente</button>
                                    </div>
                                </form>
                            </div></div>
                        </div>
                        <?php } ?>
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
<?php if($cliente_modal_abierto==='turno') { ?>
$(function() { $('#modalAbrirTurno').modal('show'); });
<?php } elseif($cliente_modal_abierto==='derivacion') { ?>
$(function() { $('#modalDerivarCliente').modal('show'); });
<?php } ?>
</script>
</body>
</html>
