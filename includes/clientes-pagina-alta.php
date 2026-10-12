<?php
// Vista reutilizable y centralizada para el alta de clientes por área.
// Todas las áreas operativas autorizadas utilizan esta misma lógica y formulario.
$area_act = function_exists('acceso_area_pantalla') ? acceso_area_pantalla() : '';
if($area_act === '' || $area_act === 'admin')
{
	$area_act = function_exists('acceso_area_activa') ? acceso_area_activa() : (function_exists('acceso_area') ? acceso_area() : '');
}

if(!function_exists('acceso_puede_alta_cliente') || !acceso_puede_alta_cliente($area_act))
{
	header('Location: '.acceso_destino_area());
	exit();
}

clientes_asegurar_columnas();
include_once BASE_PATH.'/includes/provincias.php';

$etq = acceso_etiquetas_area();
$nombre_area = isset($etq[$area_act]) ? $etq[$area_act] : $area_act;
if($area_act === 'rrhh') { $nombre_area = 'Recursos Humanos'; }

$provincias = provincias_todas();
$localidades_por_provincia = array();
foreach(acceso_consulta("SELECT id_localidad, nombre, id_provincia FROM localidad ORDER BY nombre") as $fila_loc)
{
	$pid = intval($fila_loc['id_provincia']);
	if(!isset($localidades_por_provincia[$pid]))
	{
		$localidades_por_provincia[$pid] = array();
	}
	$localidades_por_provincia[$pid][] = array(
		'id' => intval($fila_loc['id_localidad']),
		'nombre' => $fila_loc['nombre']
	);
}

$id_prov_misiones = 0;
foreach($provincias as $prov)
{
	if(isset($prov['nombre']) && $prov['nombre'] === 'Misiones')
	{
		$id_prov_misiones = intval($prov['id_provincia']);
		break;
	}
}

$mensaje_error = '';
$mensaje_dup = null;

// Acción 1: Vincular cliente ya existente al área activa
if(isset($_POST['vincular_existente']))
{
	$id_vinc = intval(isset($_POST['id_cliente']) ? $_POST['id_cliente'] : 0);
	if($id_vinc > 0)
	{
		$cli_exist = cliente_traer($id_vinc);
		if($cli_exist)
		{
			cliente_vincular_area_existente($id_vinc, $area_act);
			$nom_vinc = isset($cli_exist['name']) ? $cli_exist['name'] : (isset($cli_exist['nombre']) ? $cli_exist['nombre'] : 'Cliente');
			$_SESSION['msg_cliente_exito'] = 'El cliente "'.htmlspecialchars($nom_vinc).'" fue vinculado exitosamente al área de '.$nombre_area.'.';
			header('Location: '.url_crm($area_act.'/editar-cliente.php?id='.$id_vinc));
			exit();
		}
	}
}

// Acción 2: Alta de nuevo cliente
if(isset($_POST['alta']))
{
	$cuil_post = isset($_POST['cuil_cuit']) ? trim($_POST['cuil_cuit']) : '';
	$dni_post = isset($_POST['dni']) ? trim($_POST['dni']) : '';

	// Chequeo previo de duplicados con identificador confiable
	$cli_dup = cliente_buscar_por_identificador($cuil_post);
	if(!$cli_dup && $dni_post !== '')
	{
		$cli_dup = cliente_buscar_por_identificador($dni_post);
	}

	if($cli_dup)
	{
		$id_dup = intval($cli_dup['id_cliente']);
		$areas_dup = function_exists('crm_nombres_areas_cliente') ? crm_nombres_areas_cliente($id_dup) : '';
		$mensaje_dup = array(
			'id' => $id_dup,
			'nombre' => $cli_dup['nombre'],
			'cuil_cuit' => $cli_dup['cuil_cuit'],
			'dni' => $cli_dup['dni'],
			'areas' => $areas_dup
		);
	}
	else
	{
		$err = cliente_alta(array(
			'nombre' => isset($_POST['name']) ? trim($_POST['name']) : '',
			'correo' => isset($_POST['email']) ? trim($_POST['email']) : '',
			'telefono' => isset($_POST['mobile']) ? trim($_POST['mobile']) : '',
			'genero' => isset($_POST['gender']) ? $_POST['gender'] : 'm',
			'direccion' => isset($_POST['address']) ? trim($_POST['address']) : '',
			'id_localidad' => isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0,
			'cuil_cuit' => $cuil_post,
			'area' => $area_act,
			'areas' => array($area_act),
			'tipo_cliente' => isset($_POST['tipo_cliente']) ? $_POST['tipo_cliente'] : 'emprendedor',
			'dni' => $dni_post,
			'origen' => isset($_POST['origen']) ? $_POST['origen'] : 'presencial',
			'id_empleado_alta' => function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0,
			'abrir_turno' => false,
			'representantes' => cliente_representantes_desde_post()
		));

		if($err === '')
		{
			$nuevo_id = isset($GLOBALS['crm_ultimo_cliente_alta_id']) ? intval($GLOBALS['crm_ultimo_cliente_alta_id']) : 0;
			$nom_cli = isset($_POST['name']) ? trim($_POST['name']) : 'Nuevo cliente';
			$_SESSION['msg_cliente_exito'] = 'Cliente "'.htmlspecialchars($nom_cli).'" registrado correctamente en '.$nombre_area.'.';
			if($nuevo_id > 0)
			{
				header('Location: '.url_crm($area_act.'/editar-cliente.php?id='.$nuevo_id));
			}
			else
			{
				header('Location: '.url_crm($area_act.'/clientes.php'));
			}
			exit();
		}
		else
		{
			$mensaje_error = $err;
		}
	}
}

$menu_activo = 'clientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo htmlspecialchars($nombre_area); ?> - Nuevo cliente</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.crm-card-busqueda-previa {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 6px;
	padding: 18px 20px;
	margin-bottom: 24px;
}
.crm-res-item {
	background: #ffffff;
	border: 1px solid #cbd5e1;
	border-radius: 5px;
	padding: 12px 14px;
	margin-bottom: 10px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	gap: 10px;
}
.crm-dup-alert {
	background: #fffbeb;
	border: 1px solid #fde047;
	border-radius: 6px;
	padding: 16px 20px;
	margin-bottom: 20px;
	color: #854d0e;
}
</style>
<script language="javascript" type="text/javascript">
function valid()
{
	var f = document.form1;
	if(f.name.value.trim() == "")
	{
		alert("Falta cargar el nombre del cliente o razón social.");
		f.name.focus();
		return false;
	}
	if(f.email.value.trim() == "")
	{
		alert("Falta cargar el correo electrónico del cliente.");
		f.email.focus();
		return false;
	}
	var numCuil = f.cuil_cuit.value.replace(/\D/g, '');
	if(numCuil.length < 8)
	{
		alert("Falta un CUIL o CUIT válido (mínimo 8 números).");
		f.cuil_cuit.focus();
		return false;
	}
	if(f.id_provincia.value == "")
	{
		alert("Falta elegir la provincia.");
		f.id_provincia.focus();
		return false;
	}
	if(f.id_localidad.value == "")
	{
		alert("Falta elegir la localidad.");
		f.id_localidad.focus();
		return false;
	}
	if(f.tipo_cliente.value == "emprendedor" || f.tipo_cliente.value == "persona")
	{
		var numDni = f.dni.value.replace(/\D/g, '');
		if(numDni.length < 6)
		{
			alert("Falta el DNI (mínimo 6 números). Se utiliza para el primer ingreso del cliente.");
			f.dni.focus();
			return false;
		}
	}
	else if(recEsOrganizacion(f.tipo_cliente.value) && recHayResponsable() && !recRepsDniOk())
	{
		return false;
	}
	return true;
}

function recEsOrganizacion(t) {
	return (t === 'empresa' || t === 'organismo' || t === 'municipalidad');
}
function recHayResponsable() {
	var nombres = document.getElementsByName('representante_nombre[]');
	for(var i=0; i<nombres.length; i++) {
		if(nombres[i].value.trim() !== '') return true;
	}
	return false;
}
function recRepsDniOk() {
	var dnis = document.getElementsByName('representante_dni[]');
	for(var i=0; i<dnis.length; i++) {
		var n = dnis[i].value.replace(/\D/g, '');
		if(n.length > 0 && n.length < 6) {
			alert('El DNI de cada responsable debe tener al menos 6 números.');
			dnis[i].focus();
			return false;
		}
	}
	return true;
}
</script>
</head>
<body class="">
<?php crm_header_staff(); ?>
<div class="page-container row">
    <?php crm_menu_staff(); ?>
    <div class="clearfix"></div>
</div>
</div>
<div class="page-content">
    <div class="clearfix"></div>
    <div class="content">
        <ul class="breadcrumb">
            <li><p>ESTÁS AQUÍ</p></li>
            <li><a href="<?php echo url_crm($area_act.'/clientes.php'); ?>"><?php echo htmlspecialchars($nombre_area); ?> / Clientes</a></li>
            <li><a href="#" class="active">Nuevo cliente</a></li>
        </ul>
        <?php
        if(is_file(__DIR__.'/../public/'.$area_act.'/menu-seccion.php'))
        {
            include __DIR__.'/../public/'.$area_act.'/menu-seccion.php';
        }
        ?>
        <div class="page-title">
            <h3>Nuevo cliente · <?php echo htmlspecialchars($nombre_area); ?></h3>
        </div>

        <?php if(!empty($mensaje_error)) { ?>
        <div class="alert alert-error">
            <button class="close" data-dismiss="alert"></button>
            <strong>Error:</strong> <?php echo htmlspecialchars($mensaje_error); ?>
        </div>
        <?php } ?>

        <?php if($mensaje_dup) { ?>
        <div class="crm-dup-alert">
            <h4 style="margin:0 0 8px 0; color:#b45309;"><i class="fa fa-info-circle"></i> Cliente ya existente en el sistema centralizado</h4>
            <p style="margin:0 0 12px 0; font-size:13px;">
                Ya existe un cliente registrado con el identificador ingresado: <strong><?php echo htmlspecialchars($mensaje_dup['nombre']); ?></strong> 
                (CUIL/CUIT: <?php echo htmlspecialchars($mensaje_dup['cuil_cuit']); ?><?php echo !empty($mensaje_dup['dni']) ? ' · DNI: '.htmlspecialchars($mensaje_dup['dni']) : ''; ?>).<br>
                <?php if(!empty($mensaje_dup['areas'])) { ?>
                Áreas donde ya participa actualmente: <strong><?php echo htmlspecialchars($mensaje_dup['areas']); ?></strong>.
                <?php } ?>
            </p>
            <form method="post" action="" style="display:inline-block; margin-right:8px;">
                <input type="hidden" name="id_cliente" value="<?php echo intval($mensaje_dup['id']); ?>">
                <button type="submit" name="vincular_existente" class="btn btn-primary btn-cons">
                    <i class="fa fa-check"></i> Vincular a <?php echo htmlspecialchars($nombre_area); ?> y ver ficha
                </button>
            </form>
            <a href="<?php echo url_crm($area_act.'/editar-cliente.php?id='.intval($mensaje_dup['id'])); ?>" class="btn btn-white btn-cons">Ver ficha actual</a>
            <a href="<?php echo url_crm($area_act.'/alta-cliente.php'); ?>" class="btn btn-white btn-cons" style="margin-left:6px;">Limpiar formulario</a>
        </div>
        <?php } ?>

        <!-- SECCIÓN 1: BÚSQUEDA PREVIA DE CLIENTES EXISTENTES -->
        <div class="crm-card-busqueda-previa">
            <h4 style="margin:0 0 6px 0; color:#1e293b; font-weight:600;"><i class="fa fa-search" style="color:#0284c7;"></i> Búsqueda previa de clientes existentes</h4>
            <p class="muted" style="margin:0 0 14px 0; font-size:12.5px;">
                Para evitar duplicados, verificá si la persona o empresa ya fue atendida en otra área de ADEMI. Si existe, podés seleccionarla y vincularla a <strong><?php echo htmlspecialchars($nombre_area); ?></strong> sin volver a cargarla.
            </p>
            <div class="row">
                <div class="col-md-7 col-sm-8">
                    <div class="input-group">
                        <input type="text" id="txt_buscar_existente" class="form-control" placeholder="Buscar por DNI, CUIL/CUIT, razón social o nombre..." autocomplete="off">
                        <span class="input-group-btn">
                            <button class="btn btn-primary" type="button" id="btn_buscar_existente"><i class="fa fa-search"></i> Buscar</button>
                        </span>
                    </div>
                </div>
                <div class="col-md-5 col-sm-4">
                    <button class="btn btn-white" type="button" id="btn_limpiar_busqueda" style="display:none;">Limpiar resultados</button>
                </div>
            </div>
            <div id="contenedor_resultados_existentes" style="margin-top:14px; display:none;"></div>
        </div>

        <!-- FORMULARIO OCULTO PARA VINCULAR CLIENTE EXISTENTE (EVITA ANIDAR FORMS) -->
        <form id="form_vincular_cliente" method="post" action="" style="display:none;">
            <input type="hidden" name="id_cliente" id="input_vincular_id_cliente" value="">
            <input type="hidden" name="vincular_existente" value="1">
        </form>

        <!-- SECCIÓN 2: FORMULARIO DE ALTA DE NUEVO CLIENTE -->
        <div class="row">
            <div class="col-md-12">
                <div class="grid simple">
                    <div class="grid-title no-border">
                        <h4>Datos del nuevo cliente</h4>
                    </div>
                    <div class="grid-body no-border">
                        <form name="form1" method="post" action="" onSubmit="return valid();">
                            <div class="form-group">
                                <label>Tipo de cliente</label>
                                <select name="tipo_cliente" id="tipo_cliente" class="form-control">
                                    <?php
                                    $tipo_sel = cliente_tipo_normalizar(crm_campo('tipo_cliente','emprendedor'));
                                    foreach(cliente_tipo_etiquetas() as $clave_t=>$etq_t) { ?>
                                    <option value="<?php echo htmlspecialchars($clave_t); ?>" <?php if($tipo_sel==$clave_t) echo 'selected'; ?>><?php echo htmlspecialchars($etq_t); ?></option>
                                    <?php } ?>
                                </select>
                                <p class="muted">El cliente quedará registrado en el padrón institucional y asignado al área de <?php echo htmlspecialchars($nombre_area); ?>.</p>
                            </div>

                            <div class="form-group">
                                <label id="etiqueta_nombre">Nombre completo / Razón social</label>
                                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars(crm_campo('name')); ?>" required class="form-control" placeholder="Ej: María Rodríguez o Yerba Mate San Rafael SRL"/>
                            </div>

                            <div class="form-group">
                                <label>Correo electrónico</label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars(crm_campo('email')); ?>" required class="form-control" placeholder="correo@ejemplo.com"/>
                            </div>

                            <div class="form-group" id="caja_dni">
                                <label>DNI</label>
                                <input type="text" name="dni" id="dni" value="<?php echo htmlspecialchars(crm_campo('dni')); ?>" class="form-control" placeholder="Número de documento sin puntos"/>
                                <p class="muted">Se utiliza como clave inicial para el acceso al portal de clientes.</p>
                            </div>

                            <div class="form-group">
                                <label>De dónde vino (Canal de contacto)</label>
                                <select name="origen" class="form-control">
                                    <option value="presencial" <?php if(crm_campo('origen','presencial')=='presencial') echo 'selected'; ?>>Presencial</option>
                                    <option value="whatsapp" <?php if(crm_campo('origen')=='whatsapp') echo 'selected'; ?>>WhatsApp</option>
                                    <option value="redes" <?php if(crm_campo('origen')=='redes') echo 'selected'; ?>>Redes sociales</option>
                                    <option value="web" <?php if(crm_campo('origen')=='web') echo 'selected'; ?>>Web oficial</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>CUIL / CUIT</label>
                                <input type="text" name="cuil_cuit" id="cuil_cuit" value="<?php echo htmlspecialchars(crm_campo('cuil_cuit')); ?>" class="form-control" placeholder="Ej: 20-34567890-3"/>
                                <div id="alerta_cuit_duplicado" style="display:none; margin-top:8px;"></div>
                            </div>

                            <div class="form-group">
                                <label>Teléfono / Celular</label>
                                <input type="text" name="mobile" id="mobile" value="<?php echo htmlspecialchars(crm_campo('mobile')); ?>" class="form-control" placeholder="Ej: 3764-123456"/>
                            </div>

                            <div class="form-group" id="caja_genero">
                                <label>Género</label>
                                <select name="gender" class="form-control">
                                    <option value="m" <?php if(crm_campo('gender','m')=='m') echo 'selected'; ?>>Hombre</option>
                                    <option value="f" <?php if(crm_campo('gender')=='f') echo 'selected'; ?>>Mujer</option>
                                    <option value="otro" <?php if(crm_campo('gender')=='otro') echo 'selected'; ?>>Otro</option>
                                </select>
                            </div>

                            <!-- BLOQUE RESPONSABLES DE EMPRESAS U ORGANIZACIONES -->
                            <div id="caja_responsable" style="display:none; margin-top:16px; margin-bottom:16px;">
                                <h5 style="font-weight:600; color:#1e293b;">Responsables autorizados de la empresa / organización</h5>
                                <div id="lista_responsables">
                                <?php
                                $reps_post = cliente_representantes_desde_post();
                                if(count($reps_post) == 0)
                                {
                                    $reps_post = array(array('id'=>0, 'nombre'=>'', 'telefono'=>'', 'correo'=>'', 'dni'=>''));
                                }
                                foreach($reps_post as $rep) {
                                ?>
                                <div class="bloque-responsable" style="border:1px solid #e2e8f0; background:#f8fafc; padding:12px 14px; border-radius:5px; margin-bottom:10px;">
                                    <input type="hidden" name="representante_id[]" value="<?php echo intval(isset($rep['id']) ? $rep['id'] : 0); ?>">
                                    <div class="form-group">
                                        <label>Nombre y apellido del responsable</label>
                                        <input type="text" name="representante_nombre[]" class="form-control" value="<?php echo htmlspecialchars(isset($rep['nombre']) ? $rep['nombre'] : ''); ?>" placeholder="Nombre y apellido"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Teléfono</label>
                                        <input type="text" name="representante_telefono[]" class="form-control" value="<?php echo htmlspecialchars(isset($rep['telefono']) ? $rep['telefono'] : ''); ?>"/>
                                    </div>
                                    <div class="form-group">
                                        <label>Correo</label>
                                        <input type="email" name="representante_correo[]" class="form-control" value="<?php echo htmlspecialchars(isset($rep['correo']) ? $rep['correo'] : ''); ?>"/>
                                    </div>
                                    <div class="form-group">
                                        <label>DNI</label>
                                        <input type="text" name="representante_dni[]" class="form-control" value="<?php echo htmlspecialchars(isset($rep['dni']) ? $rep['dni'] : ''); ?>" placeholder="DNI del responsable"/>
                                    </div>
                                    <button type="button" class="btn btn-white btn-mini btn-quitar-responsable">Quitar responsable</button>
                                </div>
                                <?php } ?>
                                </div>
                                <button type="button" class="btn btn-white" id="btn_agregar_responsable"><i class="fa fa-plus"></i> Agregar otro responsable</button>
                            </div>

                            <div class="form-group">
                                <label>Provincia</label>
                                <select name="id_provincia" id="id_provincia" class="form-control">
                                    <option value="">Elegir provincia</option>
                                    <?php
                                    $id_prov_sel = intval(crm_campo('id_provincia', $id_prov_misiones));
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

                            <div class="form-group">
                                <label>Dirección (Calle, número, piso)</label>
                                <input type="text" name="address" id="address" value="<?php echo htmlspecialchars(crm_campo('address')); ?>" class="form-control" placeholder="Ej: Félix de Azara 1890"/>
                            </div>

                            <div class="form-actions" style="margin-top:24px; padding-top:16px; border-top:1px solid #e2e8f0;">
                                <button type="submit" name="alta" class="btn btn-primary btn-cons"><i class="fa fa-save"></i> Guardar cliente</button>
                                <a href="<?php echo url_crm($area_act.'/clientes.php'); ?>" class="btn btn-white btn-cons">Cancelar</a>
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
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>

<script>
var localidadesPorProvincia = <?php echo json_encode($localidades_por_provincia); ?>;
var idLocSel = <?php echo intval(crm_campo('id_localidad', 0)); ?>;
var areaActual = <?php echo json_encode($area_act); ?>;
var nombreAreaActual = <?php echo json_encode($nombre_area); ?>;

function actualizarLocalidades(provId) {
    var $loc = $('#id_localidad');
    $loc.empty().append('<option value="">Elegir localidad</option>');
    if(localidadesPorProvincia[provId]) {
        var lista = localidadesPorProvincia[provId];
        for(var i=0; i<lista.length; i++) {
            var sel = (lista[i].id == idLocSel) ? ' selected' : '';
            $loc.append('<option value="'+lista[i].id+'"'+sel+'>'+lista[i].nombre+'</option>');
        }
    }
}

function ajustarCamposSegunTipo() {
    var tipo = $('#tipo_cliente').val();
    var esOrg = recEsOrganizacion(tipo);
    if(esOrg) {
        $('#caja_dni').hide();
        $('#caja_genero').hide();
        $('#caja_responsable').show();
        $('#etiqueta_nombre').text('Razón social / Nombre de la entidad');
    } else {
        $('#caja_dni').show();
        $('#caja_genero').show();
        $('#caja_responsable').hide();
        $('#etiqueta_nombre').text('Nombre completo');
    }
}

$(document).ready(function() {
    $('#id_provincia').change(function() {
        actualizarLocalidades($(this).val());
    });
    if($('#id_provincia').val()) {
        actualizarLocalidades($('#id_provincia').val());
    }

    $('#tipo_cliente').change(ajustarCamposSegunTipo);
    ajustarCamposSegunTipo();

    // Agregar / quitar responsables de empresas
    $('#btn_agregar_responsable').click(function() {
        var html = '<div class="bloque-responsable" style="border:1px solid #e2e8f0; background:#f8fafc; padding:12px 14px; border-radius:5px; margin-bottom:10px;">' +
            '<input type="hidden" name="representante_id[]" value="0">' +
            '<div class="form-group"><label>Nombre y apellido del responsable</label><input type="text" name="representante_nombre[]" class="form-control" placeholder="Nombre y apellido"/></div>' +
            '<div class="form-group"><label>Teléfono</label><input type="text" name="representante_telefono[]" class="form-control"/></div>' +
            '<div class="form-group"><label>Correo</label><input type="email" name="representante_correo[]" class="form-control"/></div>' +
            '<div class="form-group"><label>DNI</label><input type="text" name="representante_dni[]" class="form-control" placeholder="DNI del responsable"/></div>' +
            '<button type="button" class="btn btn-white btn-mini btn-quitar-responsable">Quitar responsable</button></div>';
        $('#lista_responsables').append(html);
    });

    $(document).on('click', '.btn-quitar-responsable', function() {
        if($('.bloque-responsable').length > 1) {
            $(this).closest('.bloque-responsable').remove();
        } else {
            alert('Debe permanecer al menos un responsable para organizaciones.');
        }
    });

    // Búsqueda previa de clientes existentes (AJAX)
    function ejecutarBusquedaPrevia() {
        var q = $('#txt_buscar_existente').val().trim();
        if(q.length < 2) {
            alert('Ingresá al menos 2 caracteres para buscar.');
            return;
        }
        $('#contenedor_resultados_existentes').html('<p class="muted"><i class="fa fa-spinner fa-spin"></i> Buscando clientes...</p>').show();
        $('#btn_limpiar_busqueda').show();

        $.getJSON('../ajax/clientes-buscar.php', { todas: '1', q: q, limit: 10 }, function(res) {
            if(!res.ok || !res.lista || res.lista.length === 0) {
                $('#contenedor_resultados_existentes').html(
                    '<div class="alert alert-info" style="margin:0;">No se encontraron clientes registrados con ese criterio. Podés completar el formulario de alta abajo para registrarlo.</div>'
                );
                return;
            }

            var html = '<div style="margin-top:10px;"><p style="font-size:12px; font-weight:600; color:#334155; margin-bottom:8px;">Coincidencias encontradas en el padrón central (' + res.lista.length + '):</p>';
            for(var i=0; i<res.lista.length; i++) {
                var c = res.lista[i];
                var areasTxt = c.areas ? c.areas : 'Sin área asignada';
                html += '<div class="crm-res-item">' +
                    '<div>' +
                        '<strong style="font-size:13.5px; color:#0f172a;">' + $('<div>').text(c.nombre).html() + '</strong>' +
                        '<div style="font-size:11.5px; color:#64748b; margin-top:2px;">' +
                            'CUIL/CUIT: ' + (c.cuil_cuit ? c.cuil_cuit : '—') +
                            (c.dni ? ' · DNI: ' + c.dni : '') +
                            (c.localidad ? ' · ' + c.localidad : '') +
                            ' · <span style="color:#0284c7; font-weight:600;">Áreas: ' + $('<div>').text(areasTxt).html() + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div style="display:flex; gap:6px;">' +
                        '<button type="button" class="btn btn-primary btn-mini btn-vincular" data-id="' + c.id + '"><i class="fa fa-check"></i> Seleccionar y vincular a ' + $('<div>').text(nombreAreaActual).html() + '</button>' +
                        '<a href="../' + areaActual + '/editar-cliente.php?id=' + c.id + '" class="btn btn-white btn-mini">Ver ficha</a>' +
                    '</div>' +
                '</div>';
            }
            html += '</div>';
            $('#contenedor_resultados_existentes').html(html);
        }).fail(function() {
            $('#contenedor_resultados_existentes').html('<div class="alert alert-error">Error al consultar el servidor.</div>');
        });
    }

    $('#btn_buscar_existente').click(ejecutarBusquedaPrevia);
    $('#txt_buscar_existente').keypress(function(e) {
        if(e.which === 13) {
            e.preventDefault();
            ejecutarBusquedaPrevia();
        }
    });

    $('#btn_limpiar_busqueda').click(function() {
        $('#txt_buscar_existente').val('');
        $('#contenedor_resultados_existentes').empty().hide();
        $(this).hide();
    });

    // Verificación en vivo de CUIL/CUIT duplicado al tipear en el formulario
    var timeoutCuit = null;
    $('#cuil_cuit, #dni').on('keyup change', function() {
        var num = $(this).val().replace(/\D/g, '');
        if(num.length < 7) {
            $('#alerta_cuit_duplicado').hide();
            return;
        }
        clearTimeout(timeoutCuit);
        timeoutCuit = setTimeout(function() {
            $.getJSON('../ajax/clientes-buscar.php', { todas: '1', exacto: '1', q: num }, function(res) {
                if(res.ok && res.lista && res.lista.length > 0) {
                    var dup = res.lista[0];
                    var html = '<div class="alert alert-warning" style="margin:0; padding:10px 14px; font-size:12px;">' +
                        '<strong>⚠️ Identificador ya registrado:</strong> Pertenece a <strong>' + $('<div>').text(dup.nombre).html() + '</strong> (Áreas: ' + $('<div>').text(dup.areas ? dup.areas : '—').html() + '). ' +
                        '<button type="button" class="btn btn-primary btn-xs btn-vincular" data-id="' + dup.id + '" style="margin-left:8px;"><i class="fa fa-link"></i> Vincular a ' + $('<div>').text(nombreAreaActual).html() + ' y ver ficha</button>' +
                    '</div>';
                    $('#alerta_cuit_duplicado').html(html).show();
                } else {
                    $('#alerta_cuit_duplicado').hide();
                }
            });
        }, 400);
    });

    // Manejador centralizado para vincular cliente existente
    $(document).on('click', '.btn-vincular', function(e) {
        e.preventDefault();
        var idCli = $(this).data('id');
        if(idCli) {
            $('#input_vincular_id_cliente').val(idCli);
            $('#form_vincular_cliente').submit();
        }
    });
});
</script>
</body>
</html>
