<?php
require_once __DIR__.'/../_init.php';
require_once BASE_PATH.'/includes/salas_reuniones.php';
crm_proteger_staff();
header('Content-Type: application/json; charset=utf-8');
if(!acceso_puede_salas_reuniones())
{
	http_response_code(403);
	echo json_encode(array('ok'=>false,'error'=>'No tenés autorización para consultar la disponibilidad.'));
	exit();
}
salas_reuniones_asegurar();

$fecha=isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
$sala_fija=salas_reuniones_sala_para_reserva();
$id_sala=$sala_fija ? intval($sala_fija['id_sala']) : 0;
	$excepto_solicitado=isset($_GET['excepto']) ? intval($_GET['excepto']) : 0;
	$excepto=0;
	if(!salas_reuniones_fecha_valida($fecha) || $id_sala<=0)
{
	echo json_encode(array('ok'=>false,'error'=>'Seleccioná una fecha y sala válidas.'));
	exit();
}
	if($excepto_solicitado>0)
	{
		$reserva_a_editar=salas_reuniones_reserva_por_id($excepto_solicitado);
		if($reserva_a_editar
			&& salas_reuniones_puede_gestionar($reserva_a_editar)
			&& in_array($reserva_a_editar['estado'], array('activa','confirmada'), true)
			&& intval($reserva_a_editar['id_sala'])===$id_sala
			&& $reserva_a_editar['fecha']===$fecha)
		{
			$excepto=$excepto_solicitado;
		}
	}
$reservas=salas_reuniones_reservas_fecha($fecha,$id_sala);
$salida=array();
if(is_array($reservas))
{
	foreach($reservas as $r)
	{
		if(intval($r['id_reserva'])===$excepto) { continue; }
		$salida[]=array('inicio'=>substr($r['hora_inicio'],0,5),'fin'=>substr($r['hora_fin'],0,5));
	}
}
echo json_encode(array('ok'=>true,'reservas'=>$salida));
