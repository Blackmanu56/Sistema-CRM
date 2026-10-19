<?php
/**
 * Navigation-only marker for returning to the Vista General menu from shared
 * pages. This token does not grant access to reports, records, or operations.
 */
function crm_navegacion_general_autorizada()
{
	return function_exists('crm_es_staff') && crm_es_staff()
		&& function_exists('acceso_puede') && acceso_puede('general')
		&& function_exists('acceso_area_activa') && acceso_area_activa()==='general'
		&& ((function_exists('acceso_es_super') && acceso_es_super())
			|| (function_exists('acceso_es_coord_general') && acceso_es_coord_general()));
}

function crm_navegacion_general_token()
{
	if(!crm_navegacion_general_autorizada()) { return ''; }
	$clave='crm_navegacion_contexto_general';
	$area_actual=function_exists('acceso_area_activa') ? (string)acceso_area_activa() : '';
	$area_sesion=isset($_SESSION[$clave.'_area']) && is_string($_SESSION[$clave.'_area']) ? $_SESSION[$clave.'_area'] : null;
	if(!isset($_SESSION[$clave]) || !is_string($_SESSION[$clave]) || !preg_match('/^[a-f0-9]{64}$/', $_SESSION[$clave]) || $area_sesion!==$area_actual)
	{
		$_SESSION[$clave]=bin2hex(random_bytes(32));
		$_SESSION[$clave.'_area']=$area_actual;
	}
	return $_SESSION[$clave];
}

function crm_navegacion_contexto_general_valido($token)
{
	$esperado=isset($_SESSION['crm_navegacion_contexto_general']) ? $_SESSION['crm_navegacion_contexto_general'] : '';
	$area_esperada=isset($_SESSION['crm_navegacion_contexto_general_area']) && is_string($_SESSION['crm_navegacion_contexto_general_area']) ? $_SESSION['crm_navegacion_contexto_general_area'] : null;
	$area_actual=function_exists('acceso_area_activa') ? (string)acceso_area_activa() : '';
	$token=is_string($token) ? $token : '';
	return crm_navegacion_general_autorizada()
		&& preg_match('/^[a-f0-9]{64}$/', $esperado)
		&& preg_match('/^[a-f0-9]{64}$/', $token)
		&& $area_esperada===$area_actual
		&& hash_equals($esperado, $token);
}

function crm_navegacion_general_url($ruta)
{
	$url=url_crm($ruta);
	$token=crm_navegacion_general_token();
	if($token==='') { return $url; }
	$separador=(strpos($url,'?')===false) ? '?' : '&';
	return $url.$separador.'contexto_general='.urlencode($token);
}
?>
