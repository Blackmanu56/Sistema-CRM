<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff('rrhh');
acceso_exigir_gestion('rrhh');
require_once BASE_PATH.'/includes/rrhh/legajo.php';

if(!crm_rrhh_legajo_tabla_disponible())
{
	http_response_code(503);
	exit();
}

$id_raw=isset($_GET['id']) && is_scalar($_GET['id']) ? (string)$_GET['id'] : '';
$modo=isset($_GET['modo']) && is_string($_GET['modo']) ? $_GET['modo'] : 'ver';
if($id_raw==='' || !ctype_digit($id_raw) || intval($id_raw)<=0 || !in_array($modo, array('ver','descargar'), true))
{
	http_response_code(404);
	exit();
}

$documento=crm_rrhh_legajo_documento_por_id(intval($id_raw));
if(!is_array($documento) || !crm_rrhh_legajo_puede_gestionar(intval($documento['id_empleado'])))
{
	http_response_code(404);
	exit();
}

$ruta=crm_rrhh_legajo_ruta_por_key($documento['archivo_key']);
if($ruta==='' || !is_readable($ruta))
{
	http_response_code(404);
	exit();
}

$nombre=isset($documento['nombre_original']) ? (string)$documento['nombre_original'] : 'documento.pdf';
$nombre=preg_replace('/[\x00-\x1F\x7F]/u', '', str_replace(array('\\','/'), '_', $nombre));
if($nombre==='') { $nombre='documento.pdf'; }
$ascii=preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);
if($ascii==='' || $ascii==='.') { $ascii='documento.pdf'; }
$disposition=$modo==='descargar' ? 'attachment' : 'inline';

while(ob_get_level()>0) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Length: '.filesize($ruta));
header('Content-Disposition: '.$disposition.'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($nombre));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Frame-Options: SAMEORIGIN');
readfile($ruta);
exit();
