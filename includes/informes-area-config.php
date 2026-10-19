<?php

/**
 * Reports that can be enabled per area from Systems → Area management.
 * This is availability configuration only; callers must still enforce each
 * report's existing authorization and data scope.
 */
function crm_informes_codigos_configurables()
{
	return array(
		'colaboradores'=>'Colaboradores',
		'clientes'=>'Clientes',
		'actividades'=>'Actividades',
		'tareas'=>'Tareas',
		'atenciones'=>'Atenciones'
	);
}

function crm_informes_codigo_configurable($codigo)
{
	$codigos=crm_informes_codigos_configurables();
	return is_string($codigo) && isset($codigos[$codigo]);
}

function crm_informes_disponible_para_usuario($configuracion, $codigo, $autorizado)
{
	if(!$autorizado || !crm_informes_codigo_configurable($codigo) || !is_array($configuracion))
	{
		return false;
	}
	return isset($configuracion[$codigo]) && $configuracion[$codigo]===true;
}

function crm_informes_area_configuracion($id_area)
{
	$config=array_fill_keys(array_keys(crm_informes_codigos_configurables()),false);
	$id_area=intval($id_area);
	if($id_area<=0 || !function_exists('acceso_tabla_existe') || !acceso_tabla_existe('area_informe'))
	{
		return $config;
	}
	$filas=acceso_consulta(
		"SELECT codigo_informe, habilitado FROM area_informe WHERE id_area=?",
		'i',
		array($id_area)
	);
	foreach($filas as $fila)
	{
		$codigo=isset($fila['codigo_informe']) ? (string)$fila['codigo_informe'] : '';
		if(isset($config[$codigo]))
		{
			$config[$codigo]=(intval($fila['habilitado'])===1);
		}
	}
	return $config;
}

function crm_informes_area_configuracion_clave($clave_area)
{
	$clave_area=is_string($clave_area) ? trim($clave_area) : '';
	$config=array_fill_keys(array_keys(crm_informes_codigos_configurables()),false);
	if($clave_area==='') { return $config; }
	$area=acceso_consulta_una(
		"SELECT id_area FROM area WHERE clave=? AND activo=1 LIMIT 1",
		's',
		array($clave_area)
	);
	return $area ? crm_informes_area_configuracion(intval($area['id_area'])) : $config;
}

/** Module-level availability; this never grants a report's data permission. */
function crm_informes_area_modulo_habilitado($clave_area)
{
	$clave_area=is_string($clave_area) ? trim($clave_area) : '';
	if($clave_area==='') { return true; }
	if(function_exists('crm_asegurar_area_flags')) { crm_asegurar_area_flags(); }
	// Older installations without the new column retain existing access until
	// the unified BDD upgrade is applied.
	if(function_exists('crm_columna_existe') && !crm_columna_existe('area','informes_habilitados')) { return true; }
	$fila=acceso_consulta_una(
		"SELECT a.informes_habilitados AS v FROM area a WHERE a.clave=? AND a.activo=1 LIMIT 1",
		's',
		array($clave_area)
	);
	return $fila && intval($fila['v'])===1;
}

function crm_informes_area_modulo_guardar($id_area, $habilitado)
{
	$id_area=intval($id_area);
	if($id_area<=0 || !is_scalar($habilitado) || !in_array((string)$habilitado,array('0','1'),true)) { return false; }
	if(function_exists('crm_asegurar_area_flags')) { crm_asegurar_area_flags(); }
	if(function_exists('crm_columna_existe') && !crm_columna_existe('area','informes_habilitados')) { return false; }
	return acceso_ejecutar(
		"UPDATE area SET informes_habilitados=? WHERE id_area=?",
		'ii',
		array((int)$habilitado,$id_area)
	);
}

function crm_informes_area_configuraciones_todas()
{
	$config=array();
	if(!function_exists('acceso_tabla_existe') || !acceso_tabla_existe('area_informe'))
	{
		return $config;
	}
	$areas=acceso_consulta("SELECT id_area FROM area ORDER BY nombre");
	foreach($areas as $area)
	{
		$id=intval($area['id_area']);
		$config[$id]=array_fill_keys(array_keys(crm_informes_codigos_configurables()),false);
	}
	$filas=acceso_consulta("SELECT id_area, codigo_informe, habilitado FROM area_informe");
	foreach($filas as $fila)
	{
		$id=intval($fila['id_area']);
		$codigo=isset($fila['codigo_informe']) ? (string)$fila['codigo_informe'] : '';
		if(isset($config[$id][$codigo]))
		{
			$config[$id][$codigo]=(intval($fila['habilitado'])===1);
		}
	}
	return $config;
}

function crm_informes_area_habilitado($clave_area, $codigo)
{
	$clave_area=is_string($clave_area) ? trim($clave_area) : '';
	if($clave_area==='' || !crm_informes_codigo_configurable($codigo) || !function_exists('acceso_tabla_existe') || !acceso_tabla_existe('area_informe'))
	{
		return false;
	}
	$fila=acceso_consulta_una(
		"SELECT ai.habilitado FROM area_informe ai INNER JOIN area a ON a.id_area=ai.id_area WHERE a.clave=? AND a.activo=1 AND ai.codigo_informe=? LIMIT 1",
		'ss',
		array($clave_area,$codigo)
	);
	return $fila && intval($fila['habilitado'])===1;
}

/** Saves all five values in one statement; missing checkboxes mean disabled. */
function crm_informes_area_guardar($id_area, $seleccionados)
{
	$id_area=intval($id_area);
	if($id_area<=0 || !function_exists('acceso_tabla_existe') || !acceso_tabla_existe('area_informe'))
	{
		return false;
	}
	$seleccionados=is_array($seleccionados) ? $seleccionados : array();
	$seleccionados=array_fill_keys(array_filter($seleccionados,'is_string'),true);
	$codigos=crm_informes_codigos_configurables();
	$values=array();
	$params=array();
	$types='';
	foreach($codigos as $codigo=>$etiqueta)
	{
		$values[]='(?,?,?)';
		$params[]=$id_area;
		$params[]=$codigo;
		$params[]=isset($seleccionados[$codigo]) ? 1 : 0;
		$types.='isi';
	}
	$sql="INSERT INTO area_informe (id_area, codigo_informe, habilitado) VALUES ".implode(',', $values)." ON DUPLICATE KEY UPDATE habilitado=VALUES(habilitado)";
	return acceso_ejecutar($sql,$types,$params);
}

function crm_informes_seleccionados_validar($seleccionados)
{
	if(!is_array($seleccionados)) { return array(); }
	$validos=array();
	foreach($seleccionados as $codigo)
	{
		if(crm_informes_codigo_configurable($codigo)) { $validos[$codigo]=$codigo; }
	}
	return array_values($validos);
}

/** Strict validation for configuration updates: reject malformed or unknown codes. */
function crm_informes_seleccionados_validar_estricto($seleccionados)
{
	if(!is_array($seleccionados)) { return false; }
	$validos=array();
	foreach($seleccionados as $codigo)
	{
		if(!is_string($codigo) || !crm_informes_codigo_configurable($codigo)) { return false; }
		$validos[$codigo]=$codigo;
	}
	return array_values($validos);
}

/** Reject an enabled Reports module with no selected report. */
function crm_informes_seleccionados_requeridos_validar($seleccionados, $modulo_habilitado)
{
	$validos=crm_informes_seleccionados_validar_estricto($seleccionados);
	if($validos===false || !is_scalar($modulo_habilitado) || !in_array((string)$modulo_habilitado,array('0','1'),true)) { return false; }
	if((string)$modulo_habilitado==='1' && count($validos)===0) { return false; }
	return $validos;
}
