<?php
require_once __DIR__.'/_init.php';
crm_proteger_staff();
require_once BASE_PATH.'/includes/rrhh/fotos.php';

$id_raw=isset($_GET['id']) && is_scalar($_GET['id']) ? (string)$_GET['id'] : '';
if($id_raw==='' || !ctype_digit($id_raw) || intval($id_raw)<=0 || !crm_rrhh_foto_autorizado(intval($id_raw)))
{
	http_response_code(404);
	exit();
}
$key=crm_rrhh_foto_key_por_empleado(intval($id_raw));
$ruta=crm_rrhh_foto_ruta_por_key($key);
$extension=pathinfo($key, PATHINFO_EXTENSION);
$mime_por_extension=array('jpg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp');
if($ruta==='' || !isset($mime_por_extension[$extension]))
{
	http_response_code(404);
	exit();
}
header('Content-Type: '.$mime_por_extension[$extension]);
header('Content-Length: '.filesize($ruta));
header('Content-Disposition: inline; filename="profile.'.$extension.'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600, must-revalidate');
readfile($ruta);
exit();
