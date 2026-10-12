<?php
// Endpoint JSON: horarios disponibles de turnos por área y fecha.
require_once __DIR__.'/../_init.php';
header('Content-Type: application/json; charset=utf-8');

$logueado=(isset($_SESSION['id']) && strlen((string)$_SESSION['id'])>0)
	|| (isset($_SESSION['id_empleado']) && intval($_SESSION['id_empleado'])>0)
	|| (isset($_SESSION['alogin']) && strlen((string)$_SESSION['alogin'])>0);
if(!$logueado)
{
	http_response_code(401);
	echo json_encode(array('ok'=>false, 'horarios'=>array()));
	exit();
}

$area=isset($_GET['area']) ? trim($_GET['area']) : '';
$fecha=isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
$excepto=isset($_GET['excepto']) ? intval($_GET['excepto']) : 0;

if($area==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
{
	echo json_encode(array('ok'=>false, 'horarios'=>array()));
	exit();
}

require_once BASE_PATH.'/includes/crm_turnos_agenda.php';
$horarios=crm_turno_horarios_disponibles($area, $fecha, $excepto);

echo json_encode(array('ok'=>true, 'horarios'=>$horarios), JSON_UNESCAPED_UNICODE);
