<?php
/**
 * Shared, read-only calendar data. Existing activity authorization remains the
 * source of truth for both the calendar list and individual details.
 */
function crm_calendario_institucional_fecha_valida($fecha)
{
	if(!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { return false; }
	$partes=explode('-', $fecha);
	return checkdate(intval($partes[1]), intval($partes[2]), intval($partes[0]));
}

function crm_calendario_institucional_eventos_autorizados()
{
	if(!function_exists('crm_es_staff') || !crm_es_staff()
		|| !function_exists('acceso_consulta') || !function_exists('crm_eventos_de_area')
		|| !function_exists('actividad_puede_ver_en_area') || !function_exists('actividad_puede_ver_en_areas_autorizadas')) { return array(); }

	$areas=acceso_consulta("SELECT clave FROM area WHERE clave IS NOT NULL AND clave<>'' ORDER BY nombre");
	$por_id=array();
	foreach((array)$areas as $area)
	{
		$clave=isset($area['clave']) ? trim((string)$area['clave']) : '';
		if($clave==='' || !preg_match('/^[a-z0-9_-]+$/i', $clave)) { continue; }
		foreach((array)crm_eventos_de_area($clave, true) as $evento)
		{
			$id=intval(isset($evento['id_evento']) ? $evento['id_evento'] : (isset($evento['id']) ? $evento['id'] : 0));
			if($id<=0 || isset($por_id[$id]) || !actividad_puede_ver_en_area($evento, $clave, true)) { continue; }
			$fecha=isset($evento['fecha']) ? trim((string)$evento['fecha']) : '';
			$fecha_dia=substr($fecha, 0, 10);
			if(!crm_calendario_institucional_fecha_valida($fecha_dia)) { continue; }
			$por_id[$id]=array(
				'id'=>$id,
				'title'=>isset($evento['titulo']) ? trim((string)$evento['titulo']) : '',
				'date'=>$fecha,
				'day'=>$fecha_dia,
				'area_key'=>isset($evento['area_clave']) ? (string)$evento['area_clave'] : $clave,
				'area'=>isset($evento['area_nombre']) ? trim((string)$evento['area_nombre']) : $clave,
				'type'=>isset($evento['tipo']) ? trim((string)$evento['tipo']) : '',
				'status'=>function_exists('actividad_estado_lista') ? actividad_estado_lista($evento) : '',
				'active'=>isset($evento['activo']) ? intval($evento['activo']) : 1
			);
		}
	}
	$salida=array_values($por_id);
	usort($salida, function($a, $b) {
		$comparacion=strcmp($a['date'], $b['date']);
		return $comparacion!==0 ? $comparacion : strcasecmp($a['title'], $b['title']);
	});
	return $salida;
}

function crm_calendario_institucional_filtrar($eventos, $filtros=array())
{
	$area=isset($filtros['area']) ? (string)$filtros['area'] : '';
	$tipo=isset($filtros['tipo']) ? (string)$filtros['tipo'] : '';
	$desde=isset($filtros['desde']) && crm_calendario_institucional_fecha_valida($filtros['desde']) ? $filtros['desde'] : '';
	$hasta=isset($filtros['hasta']) && crm_calendario_institucional_fecha_valida($filtros['hasta']) ? $filtros['hasta'] : '';
	$rango_desde=isset($filtros['rango_desde']) && crm_calendario_institucional_fecha_valida($filtros['rango_desde']) ? $filtros['rango_desde'] : '';
	$rango_hasta=isset($filtros['rango_hasta']) && crm_calendario_institucional_fecha_valida($filtros['rango_hasta']) ? $filtros['rango_hasta'] : '';
	$salida=array();
	foreach((array)$eventos as $evento)
	{
		if($area!=='' && $evento['area_key']!==$area) { continue; }
		if($tipo!=='' && $evento['type']!==$tipo) { continue; }
		if($desde!=='' && $evento['day']<$desde) { continue; }
		if($hasta!=='' && $evento['day']>$hasta) { continue; }
		if($rango_desde!=='' && $evento['day']<$rango_desde) { continue; }
		if($rango_hasta!=='' && $evento['day']>$rango_hasta) { continue; }
		$salida[]=$evento;
	}
	return $salida;
}

function crm_calendario_institucional_detalle_autorizado($id_evento)
{
	$id=intval($id_evento);
	if($id<=0 || !function_exists('crm_es_staff') || !crm_es_staff()
		|| !function_exists('crm_evento_por_id') || !function_exists('actividad_puede_ver_en_areas_autorizadas')) { return null; }
	$evento=crm_evento_por_id($id);
	if(!$evento || !actividad_puede_ver_en_areas_autorizadas($evento)) { return null; }
	$fecha=isset($evento['fecha']) ? trim((string)$evento['fecha']) : '';
	$dia=substr($fecha, 0, 10);
	if(!crm_calendario_institucional_fecha_valida($dia)) { return null; }
	return array(
		'id'=>$id,
		'title'=>isset($evento['titulo']) ? trim((string)$evento['titulo']) : '',
		'date'=>$fecha,
		'day'=>$dia,
		'area'=>isset($evento['area_nombre']) ? trim((string)$evento['area_nombre']) : '',
		'type'=>isset($evento['tipo']) ? trim((string)$evento['tipo']) : '',
		'status'=>function_exists('actividad_estado_lista') ? actividad_estado_lista($evento) : '',
		'responsible'=>isset($evento['responsable']) ? trim((string)$evento['responsable']) : '',
		'description'=>isset($evento['descripcion']) ? trim((string)$evento['descripcion']) : ''
	);
}
?>
