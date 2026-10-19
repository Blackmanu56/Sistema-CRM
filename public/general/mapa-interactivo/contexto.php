<?php
function mapa_interactivo_contexto_general_autorizado()
{
	return function_exists('acceso_puede_ver_mapa_eventos') && acceso_puede_ver_mapa_eventos()
		&& function_exists('acceso_puede') && acceso_puede('general')
		&& ((function_exists('acceso_es_super') && acceso_es_super())
			|| (function_exists('acceso_es_coord_general') && acceso_es_coord_general()));
}

function mapa_interactivo_contexto_general_token()
{
	if(!mapa_interactivo_contexto_general_autorizado()) { return ''; }
	$clave='crm_mapa_general_origen';
	if(!isset($_SESSION[$clave]) || !is_string($_SESSION[$clave]) || !preg_match('/^[a-f0-9]{64}$/', $_SESSION[$clave]))
	{
		$_SESSION[$clave]=bin2hex(random_bytes(32));
	}
	return $_SESSION[$clave];
}

function mapa_interactivo_contexto_general_valido($token)
{
	$esperado=isset($_SESSION['crm_mapa_general_origen']) ? $_SESSION['crm_mapa_general_origen'] : '';
	$token=is_string($token) ? $token : '';
	return mapa_interactivo_contexto_general_autorizado()
		&& preg_match('/^[a-f0-9]{64}$/', $esperado)
		&& preg_match('/^[a-f0-9]{64}$/', $token)
		&& hash_equals($esperado, $token);
}

function mapa_interactivo_token_retorno()
{
	if(!function_exists('crm_es_staff') || !crm_es_staff()
		|| !function_exists('acceso_puede_ver_mapa_eventos') || !acceso_puede_ver_mapa_eventos()) { return ''; }
	$clave='crm_mapa_retorno_token';
	if(!isset($_SESSION[$clave]) || !is_string($_SESSION[$clave]) || !preg_match('/^[a-f0-9]{64}$/', $_SESSION[$clave]))
	{
		$_SESSION[$clave]=bin2hex(random_bytes(32));
	}
	return $_SESSION[$clave];
}

function mapa_interactivo_token_retorno_valido($token)
{
	$esperado=isset($_SESSION['crm_mapa_retorno_token']) ? $_SESSION['crm_mapa_retorno_token'] : '';
	$token=is_string($token) ? $token : '';
	return function_exists('crm_es_staff') && crm_es_staff()
		&& function_exists('acceso_puede_ver_mapa_eventos') && acceso_puede_ver_mapa_eventos()
		&& preg_match('/^[a-f0-9]{64}$/', $esperado)
		&& preg_match('/^[a-f0-9]{64}$/', $token)
		&& hash_equals($esperado, $token);
}

function mapa_interactivo_url_retorno($token, $token_general='')
{
	if(!mapa_interactivo_token_retorno_valido($token)) { return ''; }
	$params=array('mapa_retorno'=>$token);
	if($token_general!=='' && mapa_interactivo_contexto_general_valido($token_general))
	{
		$params['origen_general']=$token_general;
	}
	return url_crm('general/mapa-interactivo/index.php?'.http_build_query($params));
}
?>
