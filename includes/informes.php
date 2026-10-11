<?php

function informes_html($texto)
{
	return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function informes_contar_vacio()
{
	return array(
		'total'=>0,
		'No iniciada'=>0,
		'En proceso'=>0,
		'Pausada'=>0,
		'Terminada'=>0,
		'Vencida'=>0
	);
}

function informes_estado_texto($estado)
{
	$map=array(
		'no_iniciada'=>'No iniciada',
		'en_proceso'=>'En proceso',
		'pausada'=>'Pausada',
		'terminada'=>'Terminada'
	);
	return isset($map[$estado]) ? $map[$estado] : 'No iniciada';
}

function informes_tareas_por_area($filtro='')
{
	global $con;
	$etq=acceso_etiquetas_area();
	$salida=array();
	$suma=informes_contar_vacio();
	foreach($etq as $area=>$titulo)
	{
		if($filtro!='' && $filtro!='todas' && $filtro!=$area)
		{
			continue;
		}
		$cont=informes_contar_vacio();
		$cont['area']=$area;
		$cont['titulo']=$titulo;
		$salida[$area]=$cont;
	}
	if(!$con || !acceso_tabla_existe('tarea'))
	{
		return array('areas'=>$salida, 'total'=>$suma);
	}
	$sql="SELECT a.clave AS area, t.estado, t.fecha_cierre
		FROM tarea t
		INNER JOIN area a ON a.id_area=t.id_area";
	if($filtro!='' && $filtro!='todas')
	{
		$sql.=" WHERE a.clave='".acceso_sql($filtro)."'";
	}
	$q=@mysqli_query($con, $sql);
	if($q)
	{
		while($row=mysqli_fetch_assoc($q))
		{
			$area=isset($row['area']) ? $row['area'] : '';
			if(!isset($salida[$area]))
			{
				continue;
			}
			$est=informes_estado_texto(isset($row['estado']) ? $row['estado'] : '');
			$salida[$area]['total']++;
			if(isset($salida[$area][$est]))
			{
				$salida[$area][$est]++;
			}
			$cierre=isset($row['fecha_cierre']) ? $row['fecha_cierre'] : '';
			if($est!='Terminada' && $cierre!='' && strtotime($cierre)<strtotime(date('Y-m-d')))
			{
				$salida[$area]['Vencida']++;
			}
		}
	}
	foreach($salida as $fila)
	{
		foreach(array('total','No iniciada','En proceso','Pausada','Terminada','Vencida') as $k)
		{
			$suma[$k]+=$fila[$k];
		}
	}
	return array('areas'=>$salida, 'total'=>$suma);
}

function informes_clientes_resumen()
{
	global $con;
	$r=array(
		'total'=>0,
		'mes'=>0,
		'por_area'=>array(),
		'por_tipo'=>array(),
		'recientes'=>array()
	);
	foreach(cliente_tipo_etiquetas() as $k=>$etq)
	{
		$r['por_tipo'][$k]=array('titulo'=>$etq, 'total'=>0);
	}
	if(!$con || !acceso_tabla_existe('cliente'))
	{
		return $r;
	}
	$q=@mysqli_query($con,"SELECT c.nombre, c.tipo_cliente, a.nombre AS area_nombre, c.fecha_alta
		FROM cliente c
		LEFT JOIN area a ON a.id_area=c.id_area_afectada
		WHERE c.activo=1
		ORDER BY c.id_cliente DESC");
	if(!$q)
	{
		return $r;
	}
	$inicio_mes=date('Y-m-01');
	while($row=mysqli_fetch_assoc($q))
	{
		$r['total']++;
		$tit=isset($row['area_nombre']) && $row['area_nombre']!='' ? $row['area_nombre'] : 'Sin área asignada';
		if(!isset($r['por_area'][$tit]))
		{
			$r['por_area'][$tit]=0;
		}
		$r['por_area'][$tit]++;
		$tipo=cliente_tipo_normalizar(isset($row['tipo_cliente']) ? $row['tipo_cliente'] : 'emprendedor');
		if(!isset($r['por_tipo'][$tipo]))
		{
			$r['por_tipo'][$tipo]=array('titulo'=>cliente_tipo_etiqueta($tipo), 'total'=>0);
		}
		$r['por_tipo'][$tipo]['total']++;
		$alta=isset($row['fecha_alta']) ? $row['fecha_alta'] : '';
		if($alta!='' && substr($alta, 0, 10)>=$inicio_mes)
		{
			$r['mes']++;
		}
		if(count($r['recientes'])<8)
		{
			$r['recientes'][]=array(
				'nombre'=>isset($row['nombre']) ? $row['nombre'] : '',
				'area'=>$tit,
				'tipo'=>cliente_tipo_etiqueta($tipo),
				'fecha'=>$alta
			);
		}
	}
	return $r;
}

function informes_actividades_por_area()
{
	global $con;
	$etq=acceso_etiquetas_area();
	$salida=array();
	$suma=0;
	foreach($etq as $area=>$titulo)
	{
		$salida[$area]=array('titulo'=>$titulo, 'total'=>0);
	}
	if(!$con || !acceso_tabla_existe('actividad'))
	{
		return array('areas'=>$salida, 'total'=>0);
	}
	$q=@mysqli_query($con,"SELECT a.clave AS area, COUNT(*) AS total
		FROM actividad x
		INNER JOIN tarea t ON t.id_tarea=x.id_tarea
		INNER JOIN area a ON a.id_area=t.id_area
		GROUP BY a.clave");
	if($q)
	{
		while($row=mysqli_fetch_assoc($q))
		{
			$area=isset($row['area']) ? $row['area'] : '';
			$n=isset($row['total']) ? intval($row['total']) : 0;
			if(isset($salida[$area]))
			{
				$salida[$area]['total']=$n;
				$suma+=$n;
			}
		}
	}
	return array('areas'=>$salida, 'total'=>$suma);
}

function informes_plantillas()
{
	if(!acceso_tabla_existe('informe_plantilla'))
	{
		return array();
	}
	return acceso_consulta("SELECT id_plantilla, clave, nombre, descripcion FROM informe_plantilla WHERE activo=1 ORDER BY id_plantilla");
}

function informes_armar_resumen($clave, $filtros=array())
{
	$clave=trim($clave);
	$desde=isset($filtros['desde']) ? $filtros['desde'] : '';
	$hasta=isset($filtros['hasta']) ? $filtros['hasta'] : '';
	$localidad=isset($filtros['localidad']) ? trim($filtros['localidad']) : '';
	$tipo_cli=isset($filtros['tipo_cliente']) ? cliente_tipo_normalizar($filtros['tipo_cliente']) : '';
	$tipo_act=isset($filtros['tipo_actividad']) ? trim($filtros['tipo_actividad']) : '';
	$out=array('filas'=>array(), 'total'=>0, 'titulo'=>'');

	if($clave==='clientes_por_tipo')
	{
		$cli=informes_clientes_resumen();
		$out['titulo']='Clientes por tipo';
		foreach($cli['por_tipo'] as $k=>$fila)
		{
			$out['filas'][]=array('concepto'=>$fila['titulo'], 'detalle'=>'', 'cantidad'=>intval($fila['total']));
			$out['total']+=intval($fila['total']);
		}
		return $out;
	}

	if(!acceso_tabla_existe('evento'))
	{
		return $out;
	}
	$sql="SELECT ev.titulo, ev.tipo, ev.fecha, l.nombre AS localidad, COUNT(DISTINCT ei.id_cliente) AS n_part
		FROM evento ev
		LEFT JOIN evento_inscripcion ei ON ei.id_evento=ev.id_evento
		LEFT JOIN cliente c ON c.id_cliente=ei.id_cliente
		LEFT JOIN localidad l ON l.id_localidad=c.id_localidad
		WHERE ev.activo=1";
	$types='';
	$vals=array();
	if($desde!='' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde))
	{
		$sql.=" AND ev.fecha>=?";
		$types.='s';
		$vals[]=$desde;
	}
	if($hasta!='' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta))
	{
		$sql.=" AND ev.fecha<=?";
		$types.='s';
		$vals[]=$hasta;
	}
	if($tipo_act!='')
	{
		$sql.=" AND ev.tipo=?";
		$types.='s';
		$vals[]=$tipo_act;
	}
	if($clave==='rondas_municipalidades' || $tipo_cli==='municipalidad')
	{
		$sql.=" AND (c.tipo_cliente='municipalidad' OR ev.publico='municipalidad')";
	}
	else if($tipo_cli!='' && function_exists('cliente_tipos_validos') && in_array($tipo_cli, cliente_tipos_validos(), true))
	{
		$sql.=" AND c.tipo_cliente=?";
		$types.='s';
		$vals[]=$tipo_cli;
	}
	if($localidad!='')
	{
		$sql.=" AND l.nombre=?";
		$types.='s';
		$vals[]=$localidad;
	}
	$sql.=" GROUP BY ev.id_evento ORDER BY ev.fecha DESC LIMIT 200";
	$rows=acceso_consulta($sql, $types, $vals);
	$map_tit=array(
		'actividades_tipo_localidad'=>'Actividades por tipo y localidad',
		'actividades_tipo_cliente'=>'Actividades por tipo de cliente',
		'rondas_municipalidades'=>'Actividades / rondas para municipalidades'
	);
	$out['titulo']=isset($map_tit[$clave]) ? $map_tit[$clave] : 'Informe de actividades';
	foreach($rows as $r)
	{
		$det=trim(
			(isset($r['tipo']) ? $r['tipo'] : '').
			(isset($r['localidad']) && $r['localidad']!='' ? ' · '.$r['localidad'] : '').
			(isset($r['fecha']) ? ' · '.$r['fecha'] : '')
		);
		$out['filas'][]=array(
			'concepto'=>isset($r['titulo']) ? $r['titulo'] : '',
			'detalle'=>$det,
			'cantidad'=>intval(isset($r['n_part']) ? $r['n_part'] : 0)
		);
		$out['total']++;
	}
	return $out;
}

function informes_publicar($id_plantilla, $titulo, $filtros=array())
{
	if(!acceso_tabla_existe('informe_publicado') || !acceso_tabla_existe('informe_plantilla'))
	{
		return 'Faltan las tablas de informes en la base.';
	}
	$id_plantilla=intval($id_plantilla);
	$titulo=trim($titulo);
	$pl=acceso_consulta_una("SELECT id_plantilla, clave, nombre FROM informe_plantilla WHERE id_plantilla=? AND activo=1 LIMIT 1", 'i', array($id_plantilla));
	if(!$pl)
	{
		return 'Plantilla inválida.';
	}
	if($titulo=='')
	{
		$titulo=$pl['nombre'];
	}
	$resumen=informes_armar_resumen($pl['clave'], $filtros);
	$id_emp=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;
	if($id_emp<=0)
	{
		return 'Sesión inválida.';
	}
	$id_area=acceso_id_por_clave('area', acceso_area_pantalla());
	$ok=acceso_insertar(
		"INSERT INTO informe_publicado (id_plantilla, titulo, filtros_json, resumen_json, id_empleado_de, id_area, publicado, fecha_desde, fecha_hasta)
		VALUES (?,?,?,?,?,?,1,?,?)",
		'isssiiss',
		array(
			$id_plantilla,
			mb_substr($titulo, 0, 200),
			json_encode($filtros),
			json_encode($resumen),
			$id_emp,
			$id_area>0 ? $id_area : null,
			isset($filtros['desde']) && $filtros['desde']!='' ? $filtros['desde'] : null,
			isset($filtros['hasta']) && $filtros['hasta']!='' ? $filtros['hasta'] : null
		)
	);
	if($ok<=0)
	{
		return 'No se pudo publicar el informe.';
	}
	// Notificar a Presidencia
	if(function_exists('crm_aviso_enviar'))
	{
		$pres=acceso_consulta(
			"SELECT e.id_empleado FROM empleado e
			INNER JOIN rol r ON r.id_rol=e.id_rol
			WHERE e.activo=1 AND r.clave='presidencia'
			UNION
			SELECT x.id_empleado FROM empleado_rol_adicional x
			INNER JOIN rol r2 ON r2.id_rol=x.id_rol
			WHERE x.fecha_hasta IS NULL AND r2.clave='presidencia'"
		);
		$url=url_crm('admin/informes-presidencia.php?id='.$ok);
		foreach($pres as $p)
		{
			$id_p=intval($p['id_empleado']);
			if($id_p>0 && $id_p!==$id_emp)
			{
				crm_aviso_enviar($id_p, 'Nuevo informe para Presidencia', $titulo, $url, 0, 'informe');
			}
		}
	}
	return '';
}

function informes_publicados($solo_presidencia=true)
{
	if(!acceso_tabla_existe('informe_publicado'))
	{
		return array();
	}
	$sql="SELECT p.*, pl.clave AS plantilla_clave, pl.nombre AS plantilla_nombre,
			CONCAT(e.nombres,' ',e.apellido) AS autor, a.nombre AS area_nombre
		FROM informe_publicado p
		INNER JOIN informe_plantilla pl ON pl.id_plantilla=p.id_plantilla
		INNER JOIN empleado e ON e.id_empleado=p.id_empleado_de
		LEFT JOIN area a ON a.id_area=p.id_area
		WHERE p.publicado=1";
	if($solo_presidencia && function_exists('acceso_es_presidencia') && acceso_es_presidencia() && !acceso_es_super())
	{
		// Presidencia solo ve los ya elevados (publicado=1). Parametro reservado para filtros futuros.
		$sql.=" AND p.publicado=1";
	}
	$sql.=" ORDER BY p.creado_en DESC LIMIT 100";
	return acceso_consulta($sql);
}

function informes_publicado_traer($id)
{
	$id=intval($id);
	if($id<=0 || !acceso_tabla_existe('informe_publicado'))
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT p.*, pl.clave AS plantilla_clave, pl.nombre AS plantilla_nombre,
			CONCAT(e.nombres,' ',e.apellido) AS autor
		FROM informe_publicado p
		INNER JOIN informe_plantilla pl ON pl.id_plantilla=p.id_plantilla
		INNER JOIN empleado e ON e.id_empleado=p.id_empleado_de
		WHERE p.id_informe=? LIMIT 1",
		'i',
		array($id)
	);
}
