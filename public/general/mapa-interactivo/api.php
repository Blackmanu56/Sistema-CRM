<?php
// GeoJSON de eventos de Misiones agrupados por localidad.
require_once __DIR__.'/../../_init.php';
header('Content-Type: application/geo+json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if(!crm_es_staff())
{
	http_response_code(401);
	echo json_encode(array('ok'=>false, 'error'=>'La sesión expiró.'));
	exit();
}
if(!acceso_puede_ver_mapa_eventos())
{
	http_response_code(403);
	echo json_encode(array('ok'=>false, 'error'=>'No tenés permiso para consultar el mapa.'));
	exit();
}

function mapa_interactivo_error($mensaje, $detalle='')
{
	if($detalle!=='') { error_log('mapa-interactivo/api.php: '.$detalle); }
	http_response_code(500);
	echo json_encode(array('ok'=>false, 'error'=>$mensaje), JSON_UNESCAPED_UNICODE);
	exit();
}

function mapa_interactivo_parametro_id($nombre, $etiqueta)
{
	if(!isset($_GET[$nombre]) || $_GET[$nombre]==='') { return 0; }
	if(!is_string($_GET[$nombre]) || !ctype_digit($_GET[$nombre]) || intval($_GET[$nombre])<=0)
	{
		http_response_code(400);
		echo json_encode(array('ok'=>false, 'error'=>'El filtro de '.$etiqueta.' no es válido.'), JSON_UNESCAPED_UNICODE);
		exit();
	}
	return intval($_GET[$nombre]);
}

$id_area=mapa_interactivo_parametro_id('id_area', 'área');
$id_localidad=mapa_interactivo_parametro_id('id_localidad', 'localidad');
$tipo='';
if(isset($_GET['tipo']))
{
	if(!is_string($_GET['tipo']) || (function_exists('mb_strlen') ? mb_strlen($_GET['tipo'], 'UTF-8') : strlen($_GET['tipo']))>40)
	{
		http_response_code(400);
		echo json_encode(array('ok'=>false, 'error'=>'El filtro de tipo no es válido.'), JSON_UNESCAPED_UNICODE);
		exit();
	}
	$tipo=trim($_GET['tipo']);
}
$activo=isset($_GET['activo']) ? $_GET['activo'] : '1';
if(!is_string($activo) || !in_array($activo, array('0', '1', 'todos'), true))
{
	http_response_code(400);
	echo json_encode(array('ok'=>false, 'error'=>'El filtro de estado no es válido.'), JSON_UNESCAPED_UNICODE);
	exit();
}

$areas=acceso_consulta("SELECT id_area AS id, nombre FROM area ORDER BY nombre");
if(!empty($GLOBALS['acceso_ultimo_error'])) { mapa_interactivo_error('No se pudieron cargar las áreas del mapa.', $GLOBALS['acceso_ultimo_error']); }
$localidades_opciones=acceso_consulta("SELECT l.id_localidad AS id, l.nombre
	FROM localidad l INNER JOIN provincia p ON p.id_provincia=l.id_provincia
	WHERE p.nombre='Misiones' ORDER BY l.nombre");
if(!empty($GLOBALS['acceso_ultimo_error'])) { mapa_interactivo_error('No se pudieron cargar las localidades del mapa.', $GLOBALS['acceso_ultimo_error']); }
$tipos=acceso_consulta("SELECT DISTINCT ev.tipo
	FROM evento ev INNER JOIN localidad l ON l.id_localidad=ev.id_localidad
	INNER JOIN provincia p ON p.id_provincia=l.id_provincia
	WHERE p.nombre='Misiones'
	ORDER BY ev.tipo");
if(!empty($GLOBALS['acceso_ultimo_error'])) { mapa_interactivo_error('No se pudieron cargar los tipos de evento.', $GLOBALS['acceso_ultimo_error']); }
$tipos_opciones=array();
foreach($tipos as $fila_tipo) { $tipos_opciones[]=$fila_tipo['tipo']; }

if($id_area>0)
{
	$existe=acceso_consulta_una("SELECT id_area FROM area WHERE id_area=? LIMIT 1", 'i', array($id_area));
	if(!$existe)
	{
		http_response_code(400);
		echo json_encode(array('ok'=>false, 'error'=>'El filtro de área no es válido.'), JSON_UNESCAPED_UNICODE);
		exit();
	}
}
if($id_localidad>0)
{
	$existe=acceso_consulta_una("SELECT l.id_localidad FROM localidad l INNER JOIN provincia p ON p.id_provincia=l.id_provincia WHERE l.id_localidad=? AND p.nombre='Misiones' LIMIT 1", 'i', array($id_localidad));
	if(!$existe)
	{
		http_response_code(400);
		echo json_encode(array('ok'=>false, 'error'=>'El filtro de localidad no es válido.'), JSON_UNESCAPED_UNICODE);
		exit();
	}
}
if($tipo!=='' && !in_array($tipo, $tipos_opciones, true))
{
	http_response_code(400);
	echo json_encode(array('ok'=>false, 'error'=>'El filtro de tipo no es válido.'), JSON_UNESCAPED_UNICODE);
	exit();
}

$condiciones=array("p.nombre='Misiones'");
$parametros=array();
$tipos_parametros='';
if($activo!=='todos') { $condiciones[]='ev.activo=?'; $parametros[]=intval($activo); $tipos_parametros.='i'; }
if($id_area>0) { $condiciones[]='ev.id_area=?'; $parametros[]=$id_area; $tipos_parametros.='i'; }
if($tipo!=='') { $condiciones[]='ev.tipo=?'; $parametros[]=$tipo; $tipos_parametros.='s'; }
if($id_localidad>0) { $condiciones[]='ev.id_localidad=?'; $parametros[]=$id_localidad; $tipos_parametros.='i'; }
$where=implode(' AND ', $condiciones);

$filas=acceso_consulta("SELECT l.id_localidad, l.nombre AS localidad, l.latitud, l.longitud,
		ev.id_evento, ev.titulo, ev.fecha, ev.activo,
		a.nombre AS area, a.clave AS area_clave
	FROM evento ev
	INNER JOIN localidad l ON l.id_localidad=ev.id_localidad
	INNER JOIN provincia p ON p.id_provincia=l.id_provincia
	LEFT JOIN area a ON a.id_area=ev.id_area
	WHERE $where
	ORDER BY l.id_localidad, ev.fecha, ev.id_evento", $tipos_parametros, $parametros);
if(!empty($GLOBALS['acceso_ultimo_error'])) { mapa_interactivo_error('No se pudieron cargar los eventos del mapa.', $GLOBALS['acceso_ultimo_error']); }

$localidades=array();
$eventos_sin_coordenadas=0;
foreach($filas as $fila)
{
	$id=intval($fila['id_localidad']);
	if(!isset($localidades[$id]))
	{
		$localidades[$id]=array('id'=>$id, 'nombre'=>$fila['localidad'], 'latitud'=>$fila['latitud'], 'longitud'=>$fila['longitud'], 'eventos'=>array());
	}
	$url='';
	if(!empty($fila['area_clave']) && preg_match('/^[a-z0-9_-]+$/i', $fila['area_clave']))
	{
		$url=url_crm($fila['area_clave'].'/actividades.php?id='.intval($fila['id_evento']));
	}
	$localidades[$id]['eventos'][]=array(
		'id'=>intval($fila['id_evento']), 'titulo'=>$fila['titulo'], 'fecha'=>$fila['fecha'],
		'activo'=>intval($fila['activo']), 'area'=>$fila['area'], 'url'=>$url
	);
}

$eventos_sin_localidad=0;
if($id_localidad===0)
{
	$condiciones_sin_localidad=array('ev.id_localidad IS NULL');
	$parametros_sin_localidad=array();
	$tipos_sin_localidad='';
	if($activo!=='todos') { $condiciones_sin_localidad[]='ev.activo=?'; $parametros_sin_localidad[]=intval($activo); $tipos_sin_localidad.='i'; }
	if($id_area>0) { $condiciones_sin_localidad[]='ev.id_area=?'; $parametros_sin_localidad[]=$id_area; $tipos_sin_localidad.='i'; }
	if($tipo!=='') { $condiciones_sin_localidad[]='ev.tipo=?'; $parametros_sin_localidad[]=$tipo; $tipos_sin_localidad.='s'; }
	$fila_sin_localidad=acceso_consulta_una('SELECT COUNT(*) AS total FROM evento ev WHERE '.implode(' AND ', $condiciones_sin_localidad), $tipos_sin_localidad, $parametros_sin_localidad);
	if(!empty($GLOBALS['acceso_ultimo_error'])) { mapa_interactivo_error('No se pudo comprobar la cantidad de eventos sin localidad.', $GLOBALS['acceso_ultimo_error']); }
	$eventos_sin_localidad=$fila_sin_localidad ? intval($fila_sin_localidad['total']) : 0;
}

$features=array();
foreach($localidades as $localidad)
{
	$latitud_valida=is_numeric($localidad['latitud']) && (float)$localidad['latitud']>=-90 && (float)$localidad['latitud']<=90;
	$longitud_valida=is_numeric($localidad['longitud']) && (float)$localidad['longitud']>=-180 && (float)$localidad['longitud']<=180;
	if(!$latitud_valida || !$longitud_valida)
	{
		$eventos_sin_coordenadas+=count($localidad['eventos']);
		continue;
	}
	$features[]=array(
		'type'=>'Feature',
		'geometry'=>array('type'=>'Point', 'coordinates'=>array((float)$localidad['longitud'], (float)$localidad['latitud'])),
		'properties'=>array('id_localidad'=>$localidad['id'], 'localidad'=>$localidad['nombre'], 'cantidad_eventos'=>count($localidad['eventos']), 'eventos'=>$localidad['eventos'])
	);
}

echo json_encode(array(
	'type'=>'FeatureCollection', 'features'=>$features,
	'meta'=>array(
		'localidades_sin_coordenadas'=>count($localidades)-count($features),
		'eventos_sin_coordenadas'=>$eventos_sin_coordenadas,
		'eventos_sin_localidad'=>$eventos_sin_localidad
	),
	'filters'=>array('areas'=>$areas, 'tipos'=>$tipos_opciones, 'localidades'=>$localidades_opciones)
), JSON_UNESCAPED_UNICODE);
