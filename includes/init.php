<?php
// Arranque unico del CRM: rutas, sesion y conexion.
// Asi cada pantalla no tiene que repetir lo mismo ni pelearse con carpetas.

if(defined('CRM_INIT'))
{
	return;
}
define('CRM_INIT', true);

if(!isset($_SESSION))
{
	session_start();
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

@ini_set('default_charset', 'UTF-8');
if(function_exists('mb_internal_encoding'))
{
	@mb_internal_encoding('UTF-8');
}
if(!headers_sent())
{
	header('Content-Type: text/html; charset=UTF-8');
}

define('BASE_PATH', dirname(__DIR__));

$script=str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/public/index.php');
$pos=strpos($script, '/public/');
if($pos!==false)
{
	define('BASE_URL', substr($script, 0, $pos).'/public');
}
else
{
	define('BASE_URL', '/public');
}

function url_crm($ruta)
{
	return BASE_URL.'/'.ltrim($ruta, '/');
}

function asset($ruta)
{
	return url_crm('assets/'.ltrim($ruta, '/'));
}

// Encabezado, menu y control de acceso: un archivo para todos.
function crm_es_staff()
{
	return isset($_SESSION['alogin']) && strlen($_SESSION['alogin'])>0;
}

function crm_proteger_staff($area=null)
{
	require_once BASE_PATH.'/includes/staff/checklogin.php';
	check_login($area);
	$pag=basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
	if($pag=='change-password.php' || $pag=='logout.php')
	{
		return;
	}
	if(isset($_SESSION['primer_acceso']) && intval($_SESSION['primer_acceso'])==1)
	{
		header('Location: '.url_crm('change-password.php'));
		exit();
	}
	// En alta de parte no tocar schema ni notificaciones (evita colgar el POST).
	if(!(isset($_POST['crear_subtarea']) && isset($_POST['id_tarea'])))
	{
		if(function_exists('crm_asegurar_turno_campos'))
		{
			crm_asegurar_turno_campos();
		}
		if(function_exists('crm_notificaciones_procesar'))
		{
			crm_notificaciones_procesar();
		}
	}
}

function crm_proteger_cliente()
{
	require_once BASE_PATH.'/includes/cliente/checklogin.php';
	check_login();
	$pag=basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
	if($pag=='logout.php' || $pag=='login.php' || $pag=='change-password.php')
	{
		return;
	}
	if(isset($_SESSION['primer_acceso']) && intval($_SESSION['primer_acceso'])==1)
	{
		header('Location: '.url_crm('change-password.php'));
		exit();
	}
	if($pag=='elegir-cuenta.php')
	{
		return;
	}
	if(!function_exists('acceso_cliente_cuenta') || !acceso_cliente_cuenta())
	{
		header('Location: '.url_crm('elegir-cuenta.php'));
		exit();
	}
}

function crm_header_staff()
{
	crm_ui_css();
	include BASE_PATH.'/includes/staff/header.php';
}

function crm_menu_staff()
{
	include BASE_PATH.'/includes/staff/leftbar.php';
}

function crm_header_cliente()
{
	crm_ui_css();
	include BASE_PATH.'/includes/cliente/header.php';
}

function crm_menu_cliente()
{
	include BASE_PATH.'/includes/cliente/leftbar.php';
}

function crm_ui_css()
{
	static $ya=false;
	if($ya)
	{
		return;
	}
	$ya=true;
	$v_css=@filemtime(BASE_PATH.'/public/assets/css/crm-ui.css');
	$v_js=@filemtime(BASE_PATH.'/public/assets/js/crm-pass-toggle.js');
	echo '<link href="'.asset('css/crm-ui.css').'?v='.intval($v_css).'" rel="stylesheet" type="text/css"/>'."\n";
	echo '<script src="'.asset('js/crm-pass-toggle.js').'?v='.intval($v_js).'" type="text/javascript"></script>'."\n";
}

// Si una consulta falla, no se rompe la pantalla de inicio.
function crm_filas($resultado)
{
	return $resultado ? mysqli_num_rows($resultado) : 0;
}

require_once BASE_PATH.'/config/conexion.php';
require_once BASE_PATH.'/includes/acceso.php';
require_once BASE_PATH.'/includes/crm_extra.php';
require_once BASE_PATH.'/includes/crm_tareas_flujo.php';
require_once BASE_PATH.'/includes/crm_portal_cliente.php';
require_once BASE_PATH.'/includes/crm_turnos_agenda.php';
require_once BASE_PATH.'/includes/crm_busqueda.php';

// Nunca ALTER/semillas en AJAX ni al crear una parte (camino caliente).
$crm_script=str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
$crm_es_ajax=(strpos($crm_script, '/ajax/')!==false);
$crm_post_subtarea=(isset($_POST['crear_subtarea']) && isset($_POST['id_tarea']));
if(!$crm_es_ajax && !$crm_post_subtarea)
{
	if(function_exists('crm_tareas_flujo_asegurar'))
	{
		crm_tareas_flujo_asegurar();
	}
	if(function_exists('crm_asegurar_evento_portal'))
	{
		crm_asegurar_evento_portal();
	}
	if(function_exists('crm_asegurar_turno_agenda'))
	{
		crm_asegurar_turno_agenda();
	}
	if(function_exists('crm_asegurar_area_flags'))
	{
		crm_asegurar_area_flags();
	}
}
// No agregues acá un if por área. Cada pantalla incluye el datos-tareas.php de su carpeta.
?>
