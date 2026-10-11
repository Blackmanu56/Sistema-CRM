<?php
// Endpoint JSON: búsqueda paginada de clientes (nombre, DNI, CUIL/CUIT, localidad).
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

$todas=(isset($_GET['todas']) && strval($_GET['todas'])==='1');
$area='';
if(!$todas)
{
	$area=acceso_area_pantalla();
	if($area=='' || $area=='admin' || $area=='ajax')
	{
		$area=acceso_area();
	}
}
// Recepción / general / sistemas / coord: todos los clientes
if($todas || $area==='recepcion' || $area==='general' || $area==='sistemas' || (function_exists('crm_area_ve_todo') && crm_area_ve_todo($area)))
{
	$area='recepcion';
}

$q=isset($_GET['q']) ? trim($_GET['q']) : '';
$id_loc=isset($_GET['id_localidad']) ? intval($_GET['id_localidad']) : 0;
$lim=isset($_GET['limit']) ? intval($_GET['limit']) : 20;
if($lim<5) { $lim=5; }
if($lim>40) { $lim=40; }
$solo_org=(isset($_GET['organismos']) && strval($_GET['organismos'])==='1');

// Búsqueda rápida si hay texto; si no, lista corta
if($q!=='' && function_exists('crm_clientes_buscar_rapido'))
{
	$bus=crm_clientes_buscar_rapido(array(
		'area'=>$area,
		'q'=>$q,
		'id_localidad'=>$id_loc,
		'limite'=>$lim,
		'solo_organismos'=>$solo_org
	));
}
else
{
	$bus=crm_clientes_buscar(array(
		'area'=>$area,
		'q'=>$q,
		'id_localidad'=>$id_loc,
		'pagina'=>1,
		'limite'=>$lim,
		'sin_total'=>true
	));
}

$lista=array();
foreach($bus['lista'] as $cli)
{
	$tipo=isset($cli['tipo_cliente']) ? $cli['tipo_cliente'] : '';
	if($solo_org && function_exists('cliente_es_organizacion') && !cliente_es_organizacion($tipo))
	{
		continue;
	}
	$lista[]=array(
		'id'=>intval($cli['id']),
		'nombre'=>isset($cli['name']) ? $cli['name'] : (isset($cli['nombre']) ? $cli['nombre'] : ''),
		'email'=>isset($cli['email']) ? $cli['email'] : '',
		'localidad'=>isset($cli['localidad']) ? $cli['localidad'] : '',
		'cuil_cuit'=>isset($cli['cuil_cuit']) ? $cli['cuil_cuit'] : '',
		'dni'=>isset($cli['dni']) ? $cli['dni'] : '',
		'tipo'=>$tipo
	);
}

echo json_encode(array(
	'ok'=>true,
	'total'=>isset($bus['total']) ? intval($bus['total']) : count($lista),
	'lista'=>$lista
), JSON_UNESCAPED_UNICODE);
