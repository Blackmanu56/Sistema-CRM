<?php
// Endpoint JSON: búsqueda paginada de empleados.
require_once __DIR__.'/../_init.php';
header('Content-Type: application/json; charset=utf-8');

$logueado=(isset($_SESSION['id']) && strlen((string)$_SESSION['id'])>0)
	|| (isset($_SESSION['id_empleado']) && intval($_SESSION['id_empleado'])>0)
	|| (isset($_SESSION['alogin']) && strlen((string)$_SESSION['alogin'])>0);
if(!$logueado)
{
	http_response_code(401);
	echo json_encode(array('ok'=>false, 'lista'=>array()));
	exit();
}

$q=isset($_GET['q']) ? trim($_GET['q']) : '';
$area=isset($_GET['area']) ? trim($_GET['area']) : '';
$lim=isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if($lim<5) { $lim=5; }
if($lim>40) { $lim=40; }

$bus=crm_empleados_buscar(array(
	'q'=>$q,
	'area'=>$area,
	'pagina'=>1,
	'limite'=>$lim
));

$etq=function_exists('acceso_etiquetas_area') ? acceso_etiquetas_area() : array();
$lista=array();
foreach($bus['lista'] as $p)
{
	$area_clave=isset($p['area']) ? $p['area'] : '';
	$lista[]=array(
		'id'=>isset($p['id']) ? intval($p['id']) : 0,
		'nombre'=>isset($p['nombre']) ? $p['nombre'] : '',
		'usuario'=>isset($p['usuario']) ? $p['usuario'] : '',
		'area'=>$area_clave,
		'area_nombre'=>isset($etq[$area_clave]) ? $etq[$area_clave] : $area_clave,
		'rol'=>isset($p['rol']) ? $p['rol'] : '',
		'dni'=>isset($p['dni']) ? $p['dni'] : '',
		'correo'=>isset($p['correo']) ? $p['correo'] : ''
	);
}

echo json_encode(array(
	'ok'=>true,
	'total'=>intval($bus['total']),
	'lista'=>$lista
), JSON_UNESCAPED_UNICODE);
