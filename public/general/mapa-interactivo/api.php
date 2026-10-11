<?php
// GeoJSON de eventos activos agrupados por localidad de Misiones.
require_once __DIR__.'/../../_init.php';
header('Content-Type: application/geo+json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if(!crm_es_staff())
{
	http_response_code(401);
	echo json_encode(array('ok'=>false, 'error'=>'La sesión expiró.'));
	exit();
}
if(!acceso_es_coord_general() && !acceso_es_super() && !acceso_es_general())
{
	http_response_code(403);
	echo json_encode(array('ok'=>false, 'error'=>'No tenés permiso para consultar el mapa.'));
	exit();
}

$sql="SELECT
		l.id_localidad,
		l.nombre AS localidad,
		l.latitud,
		l.longitud,
		ev.id_evento,
		ev.titulo,
		ev.fecha
	FROM evento ev
	INNER JOIN localidad l ON l.id_localidad=ev.id_localidad
	INNER JOIN provincia p ON p.id_provincia=l.id_provincia
	WHERE ev.activo=1 AND p.nombre='Misiones'
	ORDER BY l.id_localidad, ev.fecha, ev.id_evento";
$resultado=mysqli_query($con, $sql);
if(!$resultado)
{
	error_log('mapa-interactivo/api.php: '.mysqli_error($con));
	http_response_code(500);
	echo json_encode(array('ok'=>false, 'error'=>'No se pudieron cargar los eventos del mapa.'));
	exit();
}

$localidades=array();
$eventos_sin_coordenadas=0;
while($fila=mysqli_fetch_assoc($resultado))
{
	$id=intval($fila['id_localidad']);
	if(!isset($localidades[$id]))
	{
		$localidades[$id]=array(
			'id'=>$id,
			'nombre'=>$fila['localidad'],
			'latitud'=>$fila['latitud'],
			'longitud'=>$fila['longitud'],
			'eventos'=>array()
		);
	}
	$localidades[$id]['eventos'][]=array(
		'id'=>intval($fila['id_evento']),
		'titulo'=>$fila['titulo'],
		'fecha'=>$fila['fecha']
	);
}
mysqli_free_result($resultado);

$features=array();
foreach($localidades as $localidad)
{
	if($localidad['latitud']===null || $localidad['longitud']===null)
	{
		$eventos_sin_coordenadas+=count($localidad['eventos']);
		continue;
	}
	$features[]=array(
		'type'=>'Feature',
		'geometry'=>array(
			'type'=>'Point',
			'coordinates'=>array((float)$localidad['longitud'], (float)$localidad['latitud'])
		),
		'properties'=>array(
			'id_localidad'=>$localidad['id'],
			'localidad'=>$localidad['nombre'],
			'cantidad_eventos'=>count($localidad['eventos']),
			'eventos'=>$localidad['eventos']
		)
	);
}

echo json_encode(array(
	'type'=>'FeatureCollection',
	'features'=>$features,
	'meta'=>array(
		'localidades_sin_coordenadas'=>count($localidades)-count($features),
		'eventos_sin_coordenadas'=>$eventos_sin_coordenadas
	)
), JSON_UNESCAPED_UNICODE);
