<?php
require_once __DIR__.'/../../_init.php';
error_reporting(0);
crm_proteger_staff();
require_once BASE_PATH.'/includes/clientes_alta_proceso.php';

header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
	echo json_encode(array('ok' => false, 'error' => 'Método no permitido.'));
	exit();
}

$area_act = acceso_area_pantalla();
if(!function_exists('acceso_puede_alta_cliente') || !acceso_puede_alta_cliente($area_act))
{
	echo json_encode(array('ok' => false, 'error' => 'No tenés permiso para dar de alta clientes en esta área.'));
	exit();
}

clientes_asegurar_columnas();
$resultado = clientes_procesar_alta_formulario($area_act);
if($resultado['duplicate'])
{
	$duplicado = $resultado['duplicate'];
	echo json_encode(array(
		'ok' => false,
		'duplicate' => true,
		'error' => 'Ya existe un cliente con ese DNI o CUIL/CUIT: '.(isset($duplicado['nombre']) ? $duplicado['nombre'] : 'registro existente').'. Revisá el padrón antes de continuar.'
	), JSON_UNESCAPED_UNICODE);
	exit();
}
if($resultado['error'] !== '' || intval($resultado['id']) <= 0)
{
	echo json_encode(array('ok' => false, 'error' => $resultado['error'] !== '' ? $resultado['error'] : 'No se pudo identificar el cliente recién creado.'), JSON_UNESCAPED_UNICODE);
	exit();
}

$cliente = cliente_traer(intval($resultado['id']));
$nombre = $cliente && isset($cliente['name']) ? $cliente['name'] : (isset($_POST['name']) ? trim($_POST['name']) : '');
echo json_encode(array('ok' => true, 'id' => intval($resultado['id']), 'nombre' => $nombre), JSON_UNESCAPED_UNICODE);
