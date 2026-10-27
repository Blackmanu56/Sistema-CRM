<?php
// Plantilla modular compartida: Alta de nuevo turno o atención para cualquier área autorizada.
$area_cli = acceso_area_pantalla();
$etq = acceso_etiquetas_area();
$nombre_area = isset($etq[$area_cli]) ? $etq[$area_cli] : $area_cli;

// Validar permisos del área actual.
// 1) Crear turnos requiere la capacidad 'recibe_turnos' del área:
//    tener clientes habilitados NO activa turnos por sí solo.
$atiende_ext = function_exists('acceso_area_atiende_clientes') ? acceso_area_atiende_clientes($area_cli) : 1;
if(!$atiende_ext && !acceso_es_super() && !acceso_es_coord_general())
{
	// El área no admite turnos: volvemos a la home de la sección. Ojo:
	// no todas las áreas tienen 'atenciones.php', por eso no redirigimos ahí.
	header('Location: tareas.php');
	exit();
}
// 2) Habilitación general del módulo de clientes/área.
if(!acceso_puede_clientes_de_area($area_cli) && !crm_area_ve_todo($area_cli) && !acceso_puede($area_cli))
{
	header('Location: '.acceso_destino_login());
	exit();
}

$menu_activo = 'atenciones';
$areas_disp = function_exists('recepcion_areas_cliente') ? recepcion_areas_cliente() : array();
$areas_walkin = $area_cli === 'recepcion' && function_exists('crm_areas_destino_derivacion') ? crm_areas_destino_derivacion('recepcion') : array();

// Reutilizar el padrón de provincias/localidades del alta principal de clientes.
require_once BASE_PATH.'/includes/provincias.php';
$provincias_cliente_rapido = provincias_todas();
$localidades_cliente_rapido = array();
foreach(acceso_consulta("SELECT id_localidad, nombre, id_provincia FROM localidad ORDER BY nombre") as $fila_localidad)
{
	$id_provincia_localidad = intval($fila_localidad['id_provincia']);
	if(!isset($localidades_cliente_rapido[$id_provincia_localidad]))
	{
		$localidades_cliente_rapido[$id_provincia_localidad] = array();
	}
	$localidades_cliente_rapido[$id_provincia_localidad][] = array(
		'id' => intval($fila_localidad['id_localidad']),
		'nombre' => $fila_localidad['nombre']
	);
}

if(!isset($_SESSION['crm_walkin_issued']) || !is_array($_SESSION['crm_walkin_issued'])) { $_SESSION['crm_walkin_issued']=array(); }
if(!isset($_SESSION['crm_walkin_done']) || !is_array($_SESSION['crm_walkin_done'])) { $_SESSION['crm_walkin_done']=array(); }
$walkin_token_random=function() {
	if(function_exists('random_bytes')) { return bin2hex(random_bytes(16)); }
	if(function_exists('openssl_random_pseudo_bytes')) { return bin2hex(openssl_random_pseudo_bytes(16)); }
	return md5(uniqid((string)mt_rand(), true));
};
$walkin_token=isset($_POST['walkin_token']) && is_scalar($_POST['walkin_token']) ? trim((string)$_POST['walkin_token']) : '';
$walkin_token_valid=($walkin_token!=='' && isset($_SESSION['crm_walkin_issued'][$walkin_token]));
if($walkin_token!=='' && isset($_SESSION['crm_walkin_done'][$walkin_token]))
{
	$id_done=intval($_SESSION['crm_walkin_done'][$walkin_token]);
	if($id_done>0)
	{
		header('Location: atencion.php?id='.$id_done);
		exit();
	}
}
if(!$walkin_token_valid)
{
	$walkin_token=$walkin_token_random();
	$_SESSION['crm_walkin_issued'][$walkin_token]=1;
	if(count($_SESSION['crm_walkin_issued'])>30) { array_shift($_SESSION['crm_walkin_issued']); }
}

// Cliente preseleccionado si viene por GET
$id_pre = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['id_cliente']) ? intval($_GET['id_cliente']) : (isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0));
if($id_pre <= 0 && isset($_POST['id_cliente']) && is_scalar($_POST['id_cliente']))
{
	$id_pre = intval($_POST['id_cliente']);
}
$cli_pre = $id_pre > 0 ? cliente_traer($id_pre) : null;
$cli_nombre_pre = $cli_pre ? (isset($cli_pre['name']) ? $cli_pre['name'] : (isset($cli_pre['nombre']) ? $cli_pre['nombre'] : '')) : '';

// Área preseleccionada
$area_def = isset($_GET['area']) ? trim($_GET['area']) : (isset($_POST['area']) ? trim($_POST['area']) : $area_cli);
if($area_cli !== 'recepcion' && !acceso_es_super() && !acceso_es_coord_general())
{
	$area_def = $area_cli;
}
if($area_def === '' || !isset($areas_disp[$area_def]))
{
	$area_def = isset($areas_disp[$area_cli]) ? $area_cli : key($areas_disp);
}

// Fecha y hora
$hoy = date('Y-m-d');
$fecha_def = isset($_GET['fecha']) ? trim($_GET['fecha']) : (isset($_POST['fecha']) ? trim($_POST['fecha']) : $hoy);
if($fecha_def == '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_def))
{
	$fecha_def = $hoy;
}
$hora_def = isset($_GET['hora']) ? trim($_GET['hora']) : (isset($_POST['hora']) ? trim($_POST['hora']) : '');
$modalidad_def = isset($_POST['modalidad']) && in_array($_POST['modalidad'], array('turno_programado','atencion_espontanea'), true) ? $_POST['modalidad'] : 'turno_programado';

// Procesar formulario POST
if(isset($_POST['guardar_atencion']))
{
	$id_cli = isset($_POST['id_cliente']) && is_scalar($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
	$modalidad = isset($_POST['modalidad']) && is_scalar($_POST['modalidad']) ? trim($_POST['modalidad']) : '';
	$area_walkin_form = isset($_POST['walkin_destino']) && is_scalar($_POST['walkin_destino']) ? trim($_POST['walkin_destino']) : '';
	$es_walkin_recepcion = ($area_cli === 'recepcion' && $modalidad === 'atencion_espontanea');
	$area_form = $es_walkin_recepcion ? 'recepcion' : (isset($_POST['area']) && is_scalar($_POST['area']) ? trim($_POST['area']) : $area_cli);
	if($area_cli !== 'recepcion' && !acceso_es_super() && !acceso_es_coord_general())
	{
		$area_form = $area_cli;
	}
	$motivo = isset($_POST['motivo']) && is_scalar($_POST['motivo']) ? trim($_POST['motivo']) : 'consulta';
	$canal = isset($_POST['canal']) && is_scalar($_POST['canal']) ? trim($_POST['canal']) : 'presencial';
	$detalle = isset($_POST['detalle']) && is_scalar($_POST['detalle']) ? trim($_POST['detalle']) : '';
	$id_empleado_asig = isset($_POST['id_empleado']) && is_scalar($_POST['id_empleado']) ? intval($_POST['id_empleado']) : 0;
	if($es_walkin_recepcion) { $id_empleado_asig=0; }

	$errores = array();
	if(!in_array($modalidad, array('turno_programado','atencion_espontanea'), true))
	{
		$errores[] = 'Seleccioná una modalidad de atención válida.';
	}
	if($es_walkin_recepcion && !$walkin_token_valid)
	{
		$errores[] = 'El formulario venció. Actualizá la página e intentá nuevamente.';
	}
	if($area_cli === 'recepcion' && !acceso_es_super() && !acceso_es_coord_general() && $id_empleado_asig > 0)
	{
		$errores[] = 'Recepción no puede asignar un colaborador. El área destinataria lo asignará desde Atenciones.';
		$id_empleado_asig = 0;
	}
	if($id_cli <= 0 || !cliente_traer($id_cli))
	{
		$errores[] = 'Seleccioná un cliente registrado de la lista.';
	}
	if($es_walkin_recepcion && $area_walkin_form !== 'recepcion' && !isset($areas_walkin[$area_walkin_form]))
	{
		$errores[] = 'Seleccioná Recepción o un área habilitada para recibir derivaciones.';
	}
	else if(!$es_walkin_recepcion && ($area_form === '' || !isset($areas_disp[$area_form])))
	{
		$errores[] = 'Seleccioná un área válida para el turno o atención.';
	}
	else if($id_empleado_asig > 0 && function_exists('crm_colaborador_del_area'))
	{
		// El colaborador que va a atender debe pertenecer al área destino.
		// Sin esto, un POST directo asigna a cualquiera de la organización.
		$id_area_form = acceso_id_por_clave('area', $area_form);
		if(!crm_colaborador_del_area($id_area_form, $id_empleado_asig))
		{
			$errores[] = 'Ese colaborador no pertenece al área seleccionada.';
		}
	}

	$datos_alta = array(
		'id_cliente' => $id_cli,
		'area' => $area_form,
		'motivo' => $motivo,
		'canal' => $canal,
		'detalle' => $detalle,
		'id_empleado' => $id_empleado_asig > 0 ? $id_empleado_asig : null
	);
	if($area_form !== $area_cli && !$es_walkin_recepcion)
	{
		$datos_alta['id_area_origen'] = acceso_id_por_clave('area', $area_cli);
		$datos_alta['id_empleado_origen'] = crm_id_empleado_sesion();
	}

	if($modalidad === 'atencion_espontanea')
	{
		// Recepción siempre registra la visita en su propia área antes de derivarla.
		$iniciar_ya = !empty($_POST['iniciar_inmediato']);
		$datos_alta['estado'] = $iniciar_ya ? 'en_atencion' : 'abierta';
		$datos_alta['espontanea'] = 1;
		$datos_alta['modalidad'] = 'atencion_espontanea';
		$datos_alta['tipo_ingreso'] = 'sin_turno';
		$datos_alta['fecha'] = date('Y-m-d');
		$datos_alta['hora'] = date('H:i');
	}
	else
	{
		// Turno programado con agenda
		$fecha_form = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
		$hora_form = isset($_POST['hora']) ? trim($_POST['hora']) : '';
		if($fecha_form == '' || $hora_form == '')
		{
			$errores[] = 'Indicá la fecha y el horario del turno programado.';
		}
		else
		{
			$err_turno = crm_turno_validar_alta($area_form, $fecha_form, $hora_form, 0);
			if($err_turno != '')
			{
				$errores[] = $err_turno;
			}
			else
			{
				$datos_alta['fecha'] = $fecha_form;
				$datos_alta['hora'] = $hora_form;
				$datos_alta['estado'] = 'abierta';
				$datos_alta['tipo_ingreso'] = 'turno';
			}
		}
	}

	if(count($errores) === 0)
	{
		if($es_walkin_recepcion)
		{
			$id_origen_walkin=crm_atencion_abrir($datos_alta);
			$id_nueva=$id_origen_walkin;
			if($id_origen_walkin>0 && $area_walkin_form !== 'recepcion')
			{
				$origen_walkin=crm_atencion_traer($id_origen_walkin);
				if($origen_walkin && $origen_walkin['estado']==='derivada')
				{
					$derivada_existente=crm_atencion_derivada_siguiente($id_origen_walkin);
					$id_nueva=$derivada_existente ? intval($derivada_existente['id_atencion']) : 0;
				}
				else
				{
					$id_nueva=0;
					$error_derivacion=crm_atencion_derivar($id_origen_walkin, $area_walkin_form, $detalle, $id_nueva);
					if($error_derivacion!=='') { $errores[]=$error_derivacion; }
				}
			}
		}
		else
		{
			$id_nueva = crm_atencion_abrir($datos_alta);
		}
		if($id_nueva > 0)
		{
			if($es_walkin_recepcion)
			{
				$_SESSION['crm_walkin_done'][$walkin_token]=$id_nueva;
				unset($_SESSION['crm_walkin_issued'][$walkin_token]);
				if(count($_SESSION['crm_walkin_done'])>30) { array_shift($_SESSION['crm_walkin_done']); }
			}
			$_SESSION['msgatencion'] = ($modalidad === 'atencion_espontanea')
				? ($es_walkin_recepcion && $area_walkin_form!=='recepcion' ? 'Visita registrada y derivada correctamente (#'.$id_nueva.').' : 'Atención registrada con éxito (#'.$id_nueva.').')
				: 'Turno programado correctamente (#'.$id_nueva.').';
			if(!headers_sent())
			{
				header('Location: atencion.php?id='.$id_nueva);
				exit();
			}
			echo "<script>window.location.href='atencion.php?id=".$id_nueva."'</script>";
			exit();
		}
		else if(count($errores)===0)
		{
			$errores[] = 'No se pudo guardar la atención en el sistema.';
		}
	}

	$_SESSION['msgerror'] = implode(' ', $errores);
	$id_pre = $id_cli;
	if(!$es_walkin_recepcion) { $area_def = $area_form; }
	$cli_pre = cliente_traer($id_pre);
	$cli_nombre_pre = $cli_pre ? (isset($cli_pre['name']) ? $cli_pre['name'] : '') : '';
}

// Colaboradores disponibles del área seleccionada
$id_area_act = acceso_id_por_clave('area', $area_def);
$colaboradores_disp = ($id_area_act > 0 && function_exists('crm_empleados_de_area_id')) ? crm_empleados_de_area_id($id_area_act) : array();
$puede_seleccionar_area_atencion = ($area_cli === 'recepcion' || acceso_es_super() || acceso_es_coord_general());
$puede_asignar_colaborador_atencion = !($area_cli === 'recepcion' && !acceso_es_super() && !acceso_es_coord_general());
$colaboradores_por_area = array();
if($puede_seleccionar_area_atencion && $puede_asignar_colaborador_atencion && function_exists('crm_empleados_de_area_id'))
{
	foreach($areas_disp as $clave_area_disp => $titulo_area_disp)
	{
		$id_area_disp = acceso_id_por_clave('area', $clave_area_disp);
		if($id_area_disp > 0)
		{
			$colaboradores_por_area[$clave_area_disp] = crm_empleados_de_area_id($id_area_disp);
		}
	}
}

// Horarios disponibles para el área y fecha
$horarios_disp = function_exists('crm_turno_horarios_disponibles') ? crm_turno_horarios_disponibles($area_def, $fecha_def) : array();
$url_ajax_clientes = url_crm('ajax/clientes-buscar.php');
$url_ajax_horarios = url_crm('ajax/turnos-horarios.php');
$url_ajax_alta_cliente = url_crm('recepcion/ajax/cliente-rapido.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Nuevo Turno / Atención</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.crm-cli-suggest {
    position: absolute;
    z-index: 1000;
    width: 100%;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    max-height: 220px;
    overflow-y: auto;
}
.crm-cli-suggest-item {
    display: block;
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    padding: 8px 12px;
    font-size: 13px;
    cursor: pointer;
    color: #1e293b;
}
.crm-cli-suggest-item:hover {
    background: #f8fafc;
    color: #0284c7;
}
.modalidad-card {
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px 14px;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s ease;
}
.modalidad-card.active {
    border-color: #0056b3;
    background: #f0f7ff;
}
</style>
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
            <li><p>ESTÁS AQUÍ</p></li>
            <li><a href="atenciones.php"><?php echo texto_mostrar($nombre_area); ?> / Atenciones</a></li>
            <li><a href="#" class="active">Nuevo turno o atención</a></li>
        </ul>
        <?php include("menu-seccion.php"); ?>
        <div class="page-title">
            <h3><i class="fa fa-calendar-check-o text-primary"></i> Registrar turno o atención</h3>
        </div>

        <?php if(isset($_SESSION['msgerror']) && $_SESSION['msgerror']!='') { ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo texto_mostrar($_SESSION['msgerror']); $_SESSION['msgerror']=''; ?></div>
        <?php } ?>

        <div class="row">
            <div class="col-md-9">
                <div class="grid simple">
                    <div class="grid-title no-border">
                        <h4>Datos del agendamiento</h4>
                    </div>
                    <div class="grid-body no-border">
                        <form name="form_turno" id="form_turno" method="post" action="" onsubmit="return validarFormulario();">
                            <?php if($area_cli === 'recepcion') { ?><input type="hidden" name="walkin_token" value="<?php echo htmlspecialchars($walkin_token, ENT_QUOTES, 'UTF-8'); ?>"><?php } ?>
                            
                            <!-- 1. IDENTIFICACIÓN Y BÚSQUEDA DE CLIENTE -->
                            <div class="form-group" style="position:relative; margin-bottom:20px;">
                                <label style="font-weight:600; font-size:13.5px;"><i class="fa fa-user"></i> Cliente destinatario *</label>
                                <input type="hidden" name="id_cliente" id="id_cliente" value="<?php echo intval($id_pre); ?>">
                                
                                <div id="caja_busqueda_cli" style="<?php echo $id_pre>0 ? 'display:none;' : ''; ?>">
                                    <div style="position:relative;">
                                        <input type="text" id="buscar_cliente" class="form-control" autocomplete="off"
                                               placeholder="Escribí nombre, apellido, DNI o CUIL/CUIT para buscar...">
                                        <div id="resultados_cliente" class="crm-cli-suggest" style="display:none;"></div>
                                    </div>
                                    <p class="muted" style="margin-top:6px; font-size:12px;">
                                        Escribí al menos 2 caracteres y seleccioná al cliente de la lista desplegable. 
                                        ¿No está registrado? <button type="button" class="btn btn-link btn-xs" data-toggle="modal" data-target="#nuevoClienteModal" style="font-weight:600;padding:0 2px;"><i class="fa fa-plus-circle"></i> Nuevo cliente</button>
                                    </p>
                                </div>

                                <div id="caja_cliente_elegido" style="<?php echo $id_pre>0 ? '' : 'display:none;'; ?> background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; padding:10px 14px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <span class="badge badge-success" style="font-size:11px;">CLIENTE SELECCIONADO</span>
                                            <strong id="label_cliente_nombre" style="font-size:14px; margin-left:8px; color:#0f172a;"><?php echo texto_mostrar($cli_nombre_pre); ?></strong>
                                        </div>
                                        <button type="button" class="btn btn-white btn-xs" onclick="cambiarCliente();"><i class="fa fa-times"></i> Cambiar cliente</button>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. MODALIDAD: TURNO PROGRAMADO VS ATENCIÓN ESPONTÁNEA -->
                            <div class="form-group" style="margin-bottom:20px;">
                                <label style="font-weight:600; font-size:13.5px;"><i class="fa fa-clock-o"></i> Modalidad de atención</label>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="modalidad-card active" id="card_modalidad_turno" onclick="seleccionarModalidad('turno_programado');">
                                            <div style="display:flex; align-items:flex-start; gap:10px;">
                                                <input type="radio" name="modalidad" id="opt_turno" value="turno_programado" checked style="margin-top:3px;">
                                                <div>
                                                    <strong style="color:#0f172a; font-size:13.5px;">Turno programado (con agenda)</strong>
                                                    <p class="muted" style="margin:2px 0 0; font-size:12px;">Se reserva un día y horario específico con control de cupos y disponibilidad.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="modalidad-card" id="card_modalidad_inmediata" onclick="seleccionarModalidad('atencion_espontanea');">
                                            <div style="display:flex; align-items:flex-start; gap:10px;">
                                                <input type="radio" name="modalidad" id="opt_inmediata" value="atencion_espontanea" style="margin-top:3px;">
                                                <div>
                                                    <strong style="color:#0f172a; font-size:13.5px;"><?php echo $area_cli === 'recepcion' ? 'Atender sin turno' : 'Atención espontánea (sin turno previo)'; ?></strong>
                                                    <p class="muted" style="margin:2px 0 0; font-size:12px;"><?php echo $area_cli === 'recepcion' ? 'Registrá la llegada del cliente y derivala al área que corresponda.' : 'El cliente se encuentra presente en la fundación y requiere atención directa inmediata.'; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. ÁREA DESTINO Y COLABORADOR RESPONSABLE -->
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group" id="area_turno_wrap">
                                        <label style="font-weight:600;"><i class="fa fa-building-o"></i> Área de atención *</label>
                                        <?php if($area_cli === 'recepcion' || acceso_es_super() || acceso_es_coord_general()) { ?>
                                        <select name="area" id="area_turno" class="form-control" onchange="actualizarAreaYHorarios();">
                                            <?php foreach($areas_disp as $clave => $titulo) { ?>
                                            <option value="<?php echo htmlspecialchars($clave); ?>" <?php if($area_def === $clave) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($titulo); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                        <?php } else { ?>
                                        <input type="hidden" name="area" id="area_turno" value="<?php echo htmlspecialchars($area_def); ?>">
                                        <p class="form-control-static" style="font-weight:600; color:#0f172a;">
                                            <i class="fa fa-tag text-info"></i> <?php echo texto_mostrar(isset($areas_disp[$area_def]) ? $areas_disp[$area_def] : $nombre_area); ?>
                                        </p>
                                        <?php } ?>
                                    </div>
                                    <?php if($area_cli === 'recepcion') { ?>
                                    <div class="form-group" id="area_walkin_wrap" style="display:none;">
                                        <label style="font-weight:600;"><i class="fa fa-share"></i> Derivación de la visita</label>
                                        <select name="walkin_destino" id="area_walkin" class="form-control">
                                            <option value="recepcion" <?php if(isset($_POST['walkin_destino']) && $_POST['walkin_destino']==='recepcion') echo 'selected'; ?>>Mantener en Recepción</option>
                                            <?php foreach($areas_walkin as $clave => $titulo) { ?>
                                            <option value="<?php echo htmlspecialchars($clave); ?>" <?php if(isset($_POST['walkin_destino']) && $_POST['walkin_destino']===$clave) echo 'selected'; ?>><?php echo htmlspecialchars($titulo); ?></option>
                                            <?php } ?>
                                        </select>
                                        <p class="muted" style="margin-top:4px; font-size:11.5px;">Solo aparecen áreas habilitadas para recibir derivaciones.</p>
                                    </div>
                                    <?php } ?>
                                </div>
                                <div class="col-sm-6" id="empleado_wrap">
                                    <div class="form-group">
                                        <label style="font-weight:600;"><i class="fa fa-user-circle-o"></i> Colaborador responsable</label>
                                        <?php if($area_cli === 'recepcion' && !acceso_es_super() && !acceso_es_coord_general()) { ?>
                                        <div class="form-control-static" style="color:#64748b;">Recepción deriva la atención al área. El coordinador destinatario asignará al responsable.</div>
                                        <?php } else if(empty($colaboradores_disp) && !$puede_seleccionar_area_atencion) { ?>
                                        <input type="hidden" name="id_empleado" value="">
                                        <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:6px; padding:8px 12px; font-size:12px; color:#92400e;">
                                            <i class="fa fa-info-circle"></i> Esta área no tiene colaboradores activos. El turno queda <strong>sin asignar</strong> y se coordina después desde Atenciones.
                                        </div>
                                        <?php } else { ?>
                                        <select name="id_empleado" id="id_empleado" class="form-control">
                                            <option value="">(Sin asignar / Por asignar en el área)</option>
                                            <?php foreach($colaboradores_disp as $emp) {
                                                $nom_completo = trim($emp['nombres'].' '.$emp['apellido']);
                                                $es_yo = (intval($emp['id_empleado']) === crm_id_empleado_sesion());
                                            ?>
                                            <option value="<?php echo intval($emp['id_empleado']); ?>" <?php if($es_yo) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($nom_completo); ?><?php if($es_yo) echo ' (vos)'; ?>
                                            </option>
                                            <?php } ?>
                                            <?php if(empty($colaboradores_disp)) { ?><option value="" disabled>No hay colaboradores activos en esta área.</option><?php } ?>
                                        </select>
                                        <p class="muted" style="margin-top:4px; font-size:11.5px;">Podés preasignar un colaborador del equipo o dejarlo para coordinar.</p>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. BLOQUE DE FECHA Y HORARIO (CONDICIONAL POR MODALIDAD) -->
                            <div id="bloque_programado" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 14px; margin-bottom:18px;">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label style="font-weight:600;"><i class="fa fa-calendar"></i> Fecha del turno *</label>
                                            <input type="date" name="fecha" id="fecha_turno" class="form-control"
                                                   value="<?php echo htmlspecialchars($fecha_def); ?>"
                                                   min="<?php echo date('Y-m-d'); ?>" onchange="cargarHorariosDisponibles();">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group" style="margin-bottom:0;">
                                            <label style="font-weight:600;"><i class="fa fa-clock-o"></i> Horario disponible *</label>
                                            <select name="hora" id="hora_turno" class="form-control">
                                                <option value="">Seleccionar horario...</option>
                                                <?php foreach($horarios_disp as $h) { ?>
                                                <option value="<?php echo htmlspecialchars($h); ?>" <?php if($hora_def === $h) echo 'selected'; ?>>
                                                    <?php echo htmlspecialchars($h); ?> hs
                                                </option>
                                                <?php } ?>
                                            </select>
                                            <span id="horarios_info" class="muted" style="font-size:11.5px;">Intervalos cada 30 minutos según disponibilidad del área.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="bloque_inmediata" style="display:none; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:12px 14px; margin-bottom:18px;">
                                <div class="checkbox check-success" style="margin:0;">
                                    <input type="checkbox" name="iniciar_inmediato" id="iniciar_inmediato" value="1" checked>
                                    <label for="iniciar_inmediato" style="font-weight:600; color:#166534;">
                                        <i class="fa fa-play text-success"></i> Iniciar atención inmediatamente (pasa a estado «En atención»)
                                    </label>
                                </div>
                                <p class="muted" style="margin:4px 0 0 24px; font-size:11.5px;">Si desmarcás esta casilla, la atención quedará abierta a la espera en recepción.</p>
                            </div>

                            <!-- 5. MOTIVO, CANAL Y NOTAS -->
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label style="font-weight:600;">Motivo</label>
                                        <select name="motivo" class="form-control">
                                            <option value="consulta">Consulta</option>
                                            <option value="documentacion">Documentación</option>
                                            <option value="seguimiento">Seguimiento</option>
                                            <option value="tramite">Trámite</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label style="font-weight:600;">Canal de contacto</label>
                                        <select name="canal" class="form-control">
                                            <option value="presencial">Presencial</option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="web">Web</option>
                                            <option value="redes">Redes / Teléfono</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label style="font-weight:600;">Nota inicial / Observaciones (opcional)</label>
                                <textarea name="detalle" class="form-control" rows="2" placeholder="Indicá detalles sobre el requerimiento o asunto que vino a tratar el cliente..."></textarea>
                            </div>

                            <div class="form-actions" style="margin-top:20px; display:flex; gap:10px;">
                                <a href="atenciones.php" class="btn btn-white">Cancelar</a>
                                <button type="submit" name="guardar_atencion" value="1" class="btn btn-primary" style="font-weight:600;">
                                    <i class="fa fa-save"></i> Guardar turno / atención
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- COLUMNA LATERAL INFORMATIVA -->
            <div class="col-md-3">
                <div class="grid simple">
                    <div class="grid-title no-border"><h4>Reglas del área</h4></div>
                    <div class="grid-body no-border" style="font-size:12.5px; color:#475569;">
                        <p><i class="fa fa-info-circle text-info"></i> <strong>Cupo diario:</strong> hasta <?php echo intval(crm_turno_cupo_diario()); ?> turnos agendados por día en cada área.</p>
                        <p><i class="fa fa-check text-success"></i> <strong>Sin duplicados:</strong> el sistema previene la superposición de horarios en el mismo slot.</p>
                        <p><i class="fa fa-history text-primary"></i> <strong>Trazabilidad:</strong> cada turno queda asentado en la línea de tiempo inalterable del cliente y del área.</p>
                        <p><i class="fa fa-shield text-warning"></i> <strong>Privacidad:</strong> las demás áreas solo pueden consultar las derivaciones autorizadas.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="nuevoClienteModal" tabindex="-1" role="dialog" aria-labelledby="nuevoClienteTitulo">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <form id="formNuevoClienteRapido" novalidate>
      <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">&times;</button><h4 class="modal-title" id="nuevoClienteTitulo">Nuevo cliente</h4></div>
      <div class="modal-body">
        <div id="nuevoClienteError" class="alert alert-danger" style="display:none;" role="alert"></div>
        <div class="form-group"><label for="nuevoClienteNombre">Nombre completo / Razón social *</label><input class="form-control" id="nuevoClienteNombre" name="name" required maxlength="180"></div>
        <div class="form-group"><label for="nuevoClienteEmail">Correo electrónico *</label><input class="form-control" type="email" id="nuevoClienteEmail" name="email" required maxlength="180"></div>
        <div class="form-group"><label for="nuevoClienteTipo">Tipo de cliente</label><select class="form-control" id="nuevoClienteTipo" name="tipo_cliente"><option value="emprendedor">Emprendedor</option><option value="empresa">Empresa</option><option value="municipalidad">Municipalidad</option><option value="organismo">Organismo</option></select></div>
        <div class="form-group"><label for="nuevoClienteTelefono">Teléfono</label><input class="form-control" id="nuevoClienteTelefono" name="mobile" maxlength="80"></div>
        <div class="form-group"><label for="nuevoClienteDni">DNI (opcional)</label><input class="form-control" id="nuevoClienteDni" name="dni" maxlength="30"></div>
        <div class="form-group"><label for="nuevoClienteCuit">CUIL / CUIT (opcional)</label><input class="form-control" id="nuevoClienteCuit" name="cuil_cuit" maxlength="30"></div>
        <div class="form-group"><label for="nuevoClienteDireccion">Dirección (opcional)</label><input class="form-control" id="nuevoClienteDireccion" name="address" maxlength="180"></div>
        <div class="form-group"><label for="nuevoClienteProvincia">Provincia *</label><select class="form-control" id="nuevoClienteProvincia" name="id_provincia" required><option value="">Elegir provincia</option><?php foreach($provincias_cliente_rapido as $provincia) { ?><option value="<?php echo intval($provincia['id_provincia']); ?>"><?php echo htmlspecialchars($provincia['nombre'], ENT_QUOTES, 'UTF-8'); ?></option><?php } ?></select></div>
        <div class="form-group"><label for="nuevoClienteLocalidad">Localidad *</label><select class="form-control" id="nuevoClienteLocalidad" name="id_localidad" required disabled><option value="">Elegir localidad</option></select></div>
        <input type="hidden" name="gender" value="m"><input type="hidden" name="origen" value="presencial">
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button><button type="submit" id="guardarClienteRapido" class="btn btn-primary"><i class="fa fa-save"></i> Guardar cliente</button></div>
    </form>
  </div></div>
</div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
<script type="text/javascript">
var urlBuscarCli = <?php echo json_encode($url_ajax_clientes, JSON_UNESCAPED_UNICODE); ?>;
var urlHorariosAjax = <?php echo json_encode($url_ajax_horarios, JSON_UNESCAPED_UNICODE); ?>;
var urlAltaClienteRapido = <?php echo json_encode($url_ajax_alta_cliente, JSON_UNESCAPED_UNICODE); ?>;
var localidadesClienteRapidoPorProvincia = <?php echo json_encode($localidades_cliente_rapido, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var colaboradoresPorAreaTurno = <?php echo json_encode($colaboradores_por_area, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var timerBuscar = null;

function actualizarLocalidadesClienteRapido(idProvincia) {
    var $localidad = $('#nuevoClienteLocalidad');
    $localidad.empty().append($('<option>').val('').text('Elegir localidad'));
    $localidad.prop('disabled', true);
    var localidades = localidadesClienteRapidoPorProvincia[idProvincia] || [];
    for(var i = 0; i < localidades.length; i++) {
        $localidad.append($('<option>').val(localidades[i].id).text(localidades[i].nombre));
    }
    $localidad.prop('disabled', localidades.length === 0);
}

$('#nuevoClienteProvincia').on('change', function() {
    actualizarLocalidadesClienteRapido(this.value);
});

function seleccionarModalidad(mod) {
    var areaTurnoWrap = document.getElementById('area_turno_wrap');
    var areaWalkinWrap = document.getElementById('area_walkin_wrap');
    var empleadoWrap = document.getElementById('empleado_wrap');
    if(mod === 'atencion_espontanea') {
        document.getElementById('opt_inmediata').checked = true;
        document.getElementById('card_modalidad_inmediata').className = 'modalidad-card active';
        document.getElementById('card_modalidad_turno').className = 'modalidad-card';
        document.getElementById('bloque_programado').style.display = 'none';
        document.getElementById('bloque_inmediata').style.display = 'block';
        if(areaWalkinWrap) { areaWalkinWrap.style.display = 'block'; }
        if(areaTurnoWrap && areaWalkinWrap) { areaTurnoWrap.style.display = 'none'; }
        if(empleadoWrap && areaWalkinWrap) { empleadoWrap.style.display = 'none'; }
    } else {
        document.getElementById('opt_turno').checked = true;
        document.getElementById('card_modalidad_turno').className = 'modalidad-card active';
        document.getElementById('card_modalidad_inmediata').className = 'modalidad-card';
        document.getElementById('bloque_programado').style.display = 'block';
        document.getElementById('bloque_inmediata').style.display = 'none';
        if(areaWalkinWrap) { areaWalkinWrap.style.display = 'none'; }
        if(areaTurnoWrap) { areaTurnoWrap.style.display = 'block'; }
        if(empleadoWrap) { empleadoWrap.style.display = 'block'; }
    }
}

function cambiarCliente() {
    document.getElementById('id_cliente').value = '0';
    document.getElementById('caja_cliente_elegido').style.display = 'none';
    document.getElementById('caja_busqueda_cli').style.display = 'block';
    document.getElementById('buscar_cliente').value = '';
    document.getElementById('buscar_cliente').focus();
}

function elegirCliente(cli) {
    document.getElementById('id_cliente').value = cli.id;
    var t = cli.nombre || '';
    if(cli.dni) { t += ' (DNI: ' + cli.dni + ')'; }
    else if(cli.cuil_cuit) { t += ' (CUIT: ' + cli.cuil_cuit + ')'; }
    document.getElementById('label_cliente_nombre').textContent = t;
    document.getElementById('caja_cliente_elegido').style.display = 'block';
    document.getElementById('caja_busqueda_cli').style.display = 'none';
    document.getElementById('resultados_cliente').style.display = 'none';
}

$('#formNuevoClienteRapido').on('submit', function(e) {
    e.preventDefault();
    var form = this;
    if(!form.checkValidity()) { form.reportValidity(); return; }
    var $button = $('#guardarClienteRapido');
    $('#nuevoClienteError').hide().text('');
    $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando…');
    $.ajax({
        url: urlAltaClienteRapido,
        type: 'POST',
        data: $(form).serialize(),
        dataType: 'json'
    }).done(function(r) {
        if(!r || !r.ok) {
            $('#nuevoClienteError').text(r && r.error ? r.error : 'No se pudo guardar el cliente.').show();
            return;
        }
        elegirCliente({id: r.id, nombre: r.nombre});
        $('#nuevoClienteModal').modal('hide');
        form.reset();
        actualizarLocalidadesClienteRapido('');
    }).fail(function() {
        $('#nuevoClienteError').text('No se pudo guardar el cliente. Revisá la conexión e intentá nuevamente.').show();
    }).always(function() {
        $button.prop('disabled', false).html('<i class="fa fa-save"></i> Guardar cliente');
    });
});

function buscarClientes() {
    var val = document.getElementById('buscar_cliente').value.trim();
    if(timerBuscar) clearTimeout(timerBuscar);
    if(val.length < 2) {
        document.getElementById('resultados_cliente').style.display = 'none';
        return;
    }
    timerBuscar = setTimeout(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', urlBuscarCli + '?q=' + encodeURIComponent(val) + '&todas=1&limit=15', true);
        xhr.onreadystatechange = function() {
            if(xhr.readyState !== 4 || xhr.status !== 200) return;
            try {
                var data = JSON.parse(xhr.responseText || '{}');
                var lista = data.lista || [];
                var caja = document.getElementById('resultados_cliente');
                caja.innerHTML = '';
                if(lista.length === 0) {
                    caja.innerHTML = '<div style="padding:8px 12px; color:#64748b; font-size:12.5px;">No se encontraron clientes coincidentes.</div>';
                    caja.style.display = 'block';
                    return;
                }
                for(var i=0; i<lista.length; i++) {
                    (function(item) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'crm-cli-suggest-item';
                        var desc = item.nombre;
                        if(item.dni) desc += ' · DNI ' + item.dni;
                        else if(item.cuil_cuit) desc += ' · CUIT ' + item.cuil_cuit;
                        if(item.localidad) desc += ' (' + item.localidad + ')';
                        btn.textContent = desc;
                        btn.onclick = function() { elegirCliente(item); };
                        caja.appendChild(btn);
                    })(lista[i]);
                }
                caja.style.display = 'block';
            } catch(e) {}
        };
        xhr.send();
    }, 250);
}

function cargarHorariosDisponibles() {
    var area = document.getElementById('area_turno').value;
    var fecha = document.getElementById('fecha_turno').value;
    var selectHora = document.getElementById('hora_turno');
    var info = document.getElementById('horarios_info');
    if(!area || !fecha) return;

    selectHora.innerHTML = '<option value="">Cargando horarios libres...</option>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', urlHorariosAjax + '?area=' + encodeURIComponent(area) + '&fecha=' + encodeURIComponent(fecha), true);
    xhr.onreadystatechange = function() {
        if(xhr.readyState !== 4) return;
        selectHora.innerHTML = '';
        if(xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if(resp && resp.ok && resp.horarios && resp.horarios.length > 0) {
                    var opt0 = document.createElement('option');
                    opt0.value = '';
                    opt0.textContent = 'Seleccionar horario...';
                    selectHora.appendChild(opt0);
                    for(var i=0; i<resp.horarios.length; i++) {
                        var h = resp.horarios[i];
                        var opt = document.createElement('option');
                        opt.value = h;
                        opt.textContent = h + ' hs';
                        selectHora.appendChild(opt);
                    }
                    info.textContent = resp.horarios.length + ' horarios libres para esta fecha.';
                    info.className = 'text-success';
                    return;
                }
            } catch(e) {}
        }
        var optV = document.createElement('option');
        optV.value = '';
        optV.textContent = 'Sin horarios libres disponibles';
        selectHora.appendChild(optV);
        info.textContent = 'No quedan turnos disponibles para este día en el área.';
        info.className = 'text-danger';
    };
    xhr.send();
}

function actualizarAreaYHorarios() {
    actualizarColaboradoresDelArea();
    cargarHorariosDisponibles();
}

function actualizarColaboradoresDelArea() {
    var select = document.getElementById('id_empleado');
    var area = document.getElementById('area_turno');
    if(!select || !area || area.tagName.toLowerCase() !== 'select') return;
    var valorAnterior = select.value;
    var lista = colaboradoresPorAreaTurno[area.value] || [];
    select.innerHTML = '';
    var sinAsignar = document.createElement('option');
    sinAsignar.value = '';
    sinAsignar.textContent = '(Sin asignar / Por asignar en el área)';
    select.appendChild(sinAsignar);
    var conservaAnterior = false;
    for(var i = 0; i < lista.length; i++) {
        var opcion = document.createElement('option');
        opcion.value = String(lista[i].id_empleado);
        opcion.textContent = lista[i].name || (lista[i].nombres + ' ' + lista[i].apellido);
        if(opcion.value === valorAnterior) conservaAnterior = true;
        select.appendChild(opcion);
    }
    select.value = conservaAnterior ? valorAnterior : '';
}

function validarFormulario() {
    var idCli = parseInt(document.getElementById('id_cliente').value || '0', 10);
    if(idCli <= 0) {
        alert('Por favor, buscá y seleccioná un cliente de la lista.');
        document.getElementById('buscar_cliente').focus();
        return false;
    }
    var mod = document.querySelector('input[name="modalidad"]:checked').value;
    if(mod === 'turno_programado') {
        var f = document.getElementById('fecha_turno').value;
        var h = document.getElementById('hora_turno').value;
        if(!f || !h) {
            alert('Por favor seleccioná la fecha y el horario del turno.');
            return false;
        }
    }
    return true;
}

document.getElementById('buscar_cliente').onkeyup = buscarClientes;
seleccionarModalidad(<?php echo json_encode($modalidad_def); ?>);
document.addEventListener('click', function(e) {
    var caja = document.getElementById('resultados_cliente');
    var bus = document.getElementById('buscar_cliente');
    if(e.target !== caja && e.target !== bus) {
        if(caja) caja.style.display = 'none';
    }
});
</script>
</body>
</html>
