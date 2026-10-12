<?php
// Recepción: las tareas salen y se guardan en la base (tablas tarea, responsables, links y comentarios).
// Se usa desde esta área y también cuando otra área le asigna una tarea a Recepción.

if(!function_exists('rec_tarea_alta'))
{

function rec_id_area()
{
	return acceso_id_por_clave('area', 'recepcion');
}

function rec_prioridad_a_base($texto)
{
	$t=strtolower(trim($texto));
	$ok=array('urgente'=>'urgente','alta'=>'alta','media'=>'media','baja'=>'baja');
	return isset($ok[$t]) ? $ok[$t] : 'media';
}

function rec_prioridad_a_pantalla($texto)
{
	$map=array('urgente'=>'Urgente','alta'=>'Alta','media'=>'Media','baja'=>'Baja');
	$t=strtolower(trim($texto));
	return isset($map[$t]) ? $map[$t] : 'Media';
}

function rec_estado_a_base($texto)
{
	$map=array(
		'No iniciada'=>'no_iniciada',
		'Pendiente'=>'no_iniciada',
		'En proceso'=>'en_proceso',
		'Pausada'=>'pausada',
		'Terminada'=>'terminada'
	);
	return isset($map[$texto]) ? $map[$texto] : 'no_iniciada';
}

function rec_estado_a_pantalla($texto)
{
	$map=array(
		'no_iniciada'=>'No iniciada',
		'en_proceso'=>'En proceso',
		'pausada'=>'Pausada',
		'terminada'=>'Terminada',
		'cancelada'=>'Eliminada'
	);
	return isset($map[$texto]) ? $map[$texto] : 'No iniciada';
}

function rec_tipos_listar()
{
	$salida=array();
	$id_area=rec_id_area();
	if($id_area<=0)
	{
		return $salida;
	}
	foreach(acceso_consulta("SELECT nombre FROM tipo_tarea WHERE id_area=? ORDER BY nombre", 'i', array($id_area)) as $row)
	{
		if($row['nombre']!='' && !in_array($row['nombre'], $salida))
		{
			$salida[]=$row['nombre'];
		}
	}
	return $salida;
}

// Carga un tipo nuevo solo para Recepción, desde la pantalla de la tarea.
function rec_tipo_cargar($nombre)
{
	$nombre=trim($nombre);
	if($nombre=='' || strlen($nombre)>80)
	{
		return '';
	}
	$id_area=rec_id_area();
	if($id_area<=0)
	{
		return '';
	}
	$row=acceso_consulta_una("SELECT nombre FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array($id_area, $nombre));
	if($row)
	{
		return $row['nombre'];
	}
	if(!acceso_ejecutar("INSERT INTO tipo_tarea (id_area, nombre) VALUES (?,?)", 'is', array($id_area, $nombre)))
	{
		return '';
	}
	return $nombre;
}

// Busca el tipo en la base. Si no está, no se inventa.
function rec_id_tipo($nombre)
{
	$nombre=trim($nombre);
	$id_area=rec_id_area();
	if($id_area<=0 || $nombre=='')
	{
		return 0;
	}
	$row=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array($id_area, $nombre));
	return $row ? intval($row['id_tipo']) : 0;
}

function rec_id_empleado_por_etiqueta($etiqueta)
{
	$etiqueta=trim($etiqueta);
	if($etiqueta=='')
	{
		return 0;
	}
	foreach(acceso_personal_completo(true) as $p)
	{
		if($p['persona']==$etiqueta || $p['nombre']==$etiqueta)
		{
			return intval($p['id']);
		}
	}
	$base=$etiqueta;
	$pos=strrpos($etiqueta, ' - ');
	if($pos!==false)
	{
		$base=substr($etiqueta, 0, $pos);
	}
	foreach(acceso_personal_completo(true) as $p)
	{
		if($p['nombre']==$base)
		{
			return intval($p['id']);
		}
	}
	return 0;
}

function rec_id_cliente_o_nulo($id)
{
	$id=intval($id);
	if($id<=0)
	{
		return 0;
	}
	$row=acceso_consulta_una("SELECT id_cliente FROM cliente WHERE id_cliente=? LIMIT 1", 'i', array($id));
	return $row ? $id : 0;
}

function rec_fechas_validas($inicio, $cierre)
{
	$inicio=trim($inicio);
	$cierre=trim($cierre);
	if($inicio=='')
	{
		$inicio=date('Y-m-d');
	}
	if($cierre=='')
	{
		$cierre=$inicio;
	}
	if(strtotime($cierre)<strtotime($inicio))
	{
		$cierre=$inicio;
	}
	return array($inicio, $cierre);
}

function rec_empleado_logueado()
{
	if(isset($_SESSION['id_empleado']) && intval($_SESSION['id_empleado'])>0)
	{
		return intval($_SESSION['id_empleado']);
	}
	if(isset($_SESSION['id']) && intval($_SESSION['id'])>0)
	{
		return intval($_SESSION['id']);
	}
	$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
	return rec_id_empleado_por_etiqueta($yo);
}

function rec_registrar_actividad($id_tarea, $tipo, $descripcion)
{
	$id_tarea=intval($id_tarea);
	if($id_tarea<=0 || !acceso_tabla_existe('actividad'))
	{
		return;
	}
	$ok=array('creada'=>1,'proceso'=>1,'completada'=>1,'vencida'=>1);
	if(!isset($ok[$tipo]))
	{
		$tipo='creada';
	}
	$emp=rec_empleado_logueado();
	if($emp>0)
	{
		acceso_ejecutar("INSERT INTO actividad (id_tarea, id_empleado, tipo, descripcion) VALUES (?,?,?,?)", 'iiss', array($id_tarea, $emp, $tipo, $descripcion));
	}
	else
	{
		acceso_ejecutar("INSERT INTO actividad (id_tarea, id_empleado, tipo, descripcion) VALUES (?,NULL,?,?)", 'iss', array($id_tarea, $tipo, $descripcion));
	}
}

function rec_guardar_responsables($id_tarea, $nombres)
{
	$id_tarea=intval($id_tarea);
	if($id_tarea<=0)
	{
		return;
	}
	$ids=array();
	foreach($nombres as $nom)
	{
		$id=rec_id_empleado_por_etiqueta($nom);
		if($id>0 && !in_array($id, $ids))
		{
			$ids[]=$id;
		}
	}
	acceso_ejecutar("DELETE FROM tarea_responsable WHERE id_tarea=?", 'i', array($id_tarea));
	$n=0;
	foreach($ids as $id_emp)
	{
		$principal=($n==0) ? 1 : 0;
		acceso_ejecutar("INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?,?,?)", 'iii', array($id_tarea, $id_emp, $principal));
		$n++;
	}
}

function rec_guardar_iniciaron($id_tarea, $nombres)
{
	$id_tarea=intval($id_tarea);
	if($id_tarea<=0)
	{
		return;
	}
	$ids=array();
	foreach($nombres as $nom)
	{
		$id=rec_id_empleado_por_etiqueta($nom);
		if($id>0 && !in_array($id, $ids))
		{
			$ids[]=$id;
		}
	}
	acceso_ejecutar("DELETE FROM tarea_inicio WHERE id_tarea=?", 'i', array($id_tarea));
	foreach($ids as $id_emp)
	{
		acceso_ejecutar("INSERT INTO tarea_inicio (id_tarea, id_empleado) VALUES (?,?)", 'ii', array($id_tarea, $id_emp));
	}
}

function rec_guardar_enlaces($id_tarea, $enlaces)
{
	$id_tarea=intval($id_tarea);
	if($id_tarea<=0)
	{
		return;
	}
	$urls=array();
	foreach($enlaces as $url)
	{
		if(is_array($url))
		{
			$url=isset($url['url']) ? $url['url'] : '';
		}
		$url=trim($url);
		if($url!='' && !in_array($url, $urls))
		{
			$urls[]=$url;
		}
	}
	$actuales=array();
	foreach(acceso_consulta("SELECT url FROM tarea_enlace WHERE id_tarea=?", 'i', array($id_tarea)) as $row)
	{
		$actuales[]=$row['url'];
	}
	foreach($urls as $url)
	{
		if(!in_array($url, $actuales))
		{
			acceso_ejecutar("INSERT INTO tarea_enlace (id_tarea, url) VALUES (?,?)", 'is', array($id_tarea, $url));
		}
	}
	foreach($actuales as $url)
	{
		if(!in_array($url, $urls))
		{
			acceso_ejecutar("DELETE FROM tarea_enlace WHERE id_tarea=? AND url=?", 'is', array($id_tarea, $url));
		}
	}
}

function rec_guardar_comentarios($id_tarea, $comentarios)
{
	$id_tarea=intval($id_tarea);
	if($id_tarea<=0)
	{
		return;
	}
	$ya=array();
	foreach(acceso_consulta("SELECT texto, id_empleado FROM tarea_comentario WHERE id_tarea=?", 'i', array($id_tarea)) as $row)
	{
		$ya[]=intval($row['id_empleado']).'|'.$row['texto'];
	}
	foreach($comentarios as $com)
	{
		$txt=is_array($com) ? (isset($com['texto']) ? trim($com['texto']) : '') : trim($com);
		if($txt=='')
		{
			continue;
		}
		$quien=is_array($com) && isset($com['quien']) ? $com['quien'] : '';
		$id_emp=rec_id_empleado_por_etiqueta($quien);
		if($id_emp<=0)
		{
			$id_emp=rec_empleado_logueado();
		}
		if($id_emp<=0)
		{
			continue;
		}
		$clave=$id_emp.'|'.$txt;
		if(in_array($clave, $ya))
		{
			continue;
		}
		acceso_ejecutar("INSERT INTO tarea_comentario (id_tarea, id_empleado, texto) VALUES (?,?,?)", 'iis', array($id_tarea, $id_emp, $txt));
		$ya[]=$clave;
	}
}

function rec_tarea_guardar_una($tarea)
{
	global $con;
	if(!$con || !is_array($tarea) || !acceso_tabla_existe('tarea'))
	{
		return $tarea;
	}
	$id_area=rec_id_area();
	$tipo=isset($tarea['tipo']) ? $tarea['tipo'] : (isset($tarea['area']) ? $tarea['area'] : '');
	$id_tipo=rec_id_tipo($tipo);
	if($id_area<=0 || $id_tipo<=0)
	{
		return $tarea;
	}
	list($ini, $cie)=rec_fechas_validas(
		isset($tarea['fecha_inicio']) ? $tarea['fecha_inicio'] : '',
		isset($tarea['fecha_cierre']) ? $tarea['fecha_cierre'] : ''
	);
	$titulo=isset($tarea['titulo']) ? $tarea['titulo'] : '';
	$descripcion=isset($tarea['descripcion']) ? $tarea['descripcion'] : '';
	$prioridad=rec_prioridad_a_base(isset($tarea['prioridad']) ? $tarea['prioridad'] : 'Media');
	$estado=rec_estado_a_base(isset($tarea['estado']) ? $tarea['estado'] : 'No iniciada');
	$avance=intval(isset($tarea['avance']) ? $tarea['avance'] : 0);
	if($avance<0) { $avance=0; }
	if($avance>100) { $avance=100; }
	$id_cliente=rec_id_cliente_o_nulo(isset($tarea['cliente_id']) ? $tarea['cliente_id'] : 0);
	$uid=isset($tarea['uid']) ? substr($tarea['uid'], 0, 20) : '';
	if($uid=='')
	{
		$uid=substr('r'.md5(uniqid('', true)), 0, 20);
	}
	$id=isset($tarea['id']) ? intval($tarea['id']) : 0;
	$estado_antes='';
	if($id>0)
	{
		$row=acceso_consulta_una("SELECT estado FROM tarea WHERE id_tarea=? LIMIT 1", 'i', array($id));
		if($row)
		{
			$estado_antes=isset($row['estado']) ? $row['estado'] : '';
		}
		else
		{
			$id=0;
		}
	}
	if($id>0)
	{
		if($id_cliente>0)
		{
			acceso_ejecutar(
				"UPDATE tarea SET titulo=?, descripcion=?, id_area=?, id_tipo=?, prioridad=?, estado=?, fecha_inicio=?, fecha_cierre=?, avance=?, id_cliente=?, uid=? WHERE id_tarea=?",
				'ssiissssiisi',
				array($titulo, $descripcion, $id_area, $id_tipo, $prioridad, $estado, $ini, $cie, $avance, $id_cliente, $uid, $id)
			);
		}
		else
		{
			acceso_ejecutar(
				"UPDATE tarea SET titulo=?, descripcion=?, id_area=?, id_tipo=?, prioridad=?, estado=?, fecha_inicio=?, fecha_cierre=?, avance=?, id_cliente=NULL, uid=? WHERE id_tarea=?",
				'ssiissssisi',
				array($titulo, $descripcion, $id_area, $id_tipo, $prioridad, $estado, $ini, $cie, $avance, $uid, $id)
			);
		}
	}
	else
	{
		if($id_cliente>0)
		{
			$id=acceso_insertar(
				"INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance, id_cliente, uid)
				VALUES (?,?,?,?,?,?,?,?,?,?,?)",
				'ssiissssiis',
				array($titulo, $descripcion, $id_area, $id_tipo, $prioridad, $estado, $ini, $cie, $avance, $id_cliente, $uid)
			);
		}
		else
		{
			$id=acceso_insertar(
				"INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance, id_cliente, uid)
				VALUES (?,?,?,?,?,?,?,?,?,NULL,?)",
				'ssiissssis',
				array($titulo, $descripcion, $id_area, $id_tipo, $prioridad, $estado, $ini, $cie, $avance, $uid)
			);
		}
		if($id>0)
		{
			rec_registrar_actividad($id, 'creada', $titulo);
		}
	}
	if($id<=0)
	{
		return $tarea;
	}
	crm_aplicar_eje_post($id, $id_tipo);
	if($id_cliente>0)
	{
		crm_vincular_cliente_area($id_cliente, $id_area);
	}
	if(isset($_POST['id_atencion']) && intval($_POST['id_atencion'])>0)
	{
		acceso_ejecutar("UPDATE tarea SET id_atencion=? WHERE id_tarea=?", 'ii', array(intval($_POST['id_atencion']), $id));
	}
	$tarea['id']=$id;
	$tarea['uid']=$uid;
	$tarea['fecha_inicio']=$ini;
	$tarea['fecha_cierre']=$cie;
	$nombres=function_exists('tarea_lista_responsables') ? tarea_lista_responsables($tarea) : (isset($tarea['responsables']) && is_array($tarea['responsables']) ? $tarea['responsables'] : array());
	rec_guardar_responsables($id, $nombres);
	$ini_nom=isset($tarea['iniciaron']) && is_array($tarea['iniciaron']) ? $tarea['iniciaron'] : array();
	rec_guardar_iniciaron($id, $ini_nom);
	$enl=isset($tarea['enlaces']) && is_array($tarea['enlaces']) ? $tarea['enlaces'] : array();
	rec_guardar_enlaces($id, $enl);
	$coms=isset($tarea['comentarios']) && is_array($tarea['comentarios']) ? $tarea['comentarios'] : array();
	rec_guardar_comentarios($id, $coms);
	if($estado_antes!='' && $estado_antes!=$estado)
	{
		if($estado=='en_proceso')
		{
			rec_registrar_actividad($id, 'proceso', $titulo);
		}
		else if($estado=='terminada')
		{
			rec_registrar_actividad($id, 'completada', $titulo);
		}
	}
	return $tarea;
}

function rec_tarea_alta($tarea)
{
	$tarea['id']=0;
	$tarea['estado']=isset($tarea['estado']) ? $tarea['estado'] : 'No iniciada';
	$tarea['avance']=0;
	$tarea['enlaces']=array();
	$tarea['comentarios']=array();
	$tarea['iniciaron']=array();
	return rec_tarea_guardar_una($tarea);
}

function rec_nombres_por_empleados($filas, $campo_id)
{
	$por_id=array();
	foreach(acceso_personal_completo(true) as $p)
	{
		$por_id[intval($p['id'])]=($p['persona']!='' ? $p['persona'] : $p['nombre']);
	}
	$salida=array();
	foreach($filas as $row)
	{
		$id=isset($row[$campo_id]) ? intval($row[$campo_id]) : 0;
		if($id>0 && isset($por_id[$id]))
		{
			$salida[]=$por_id[$id];
		}
	}
	return $salida;
}

function rec_sql_tarea_select()
{
	return "SELECT t.id_tarea, t.titulo, t.descripcion, t.prioridad, t.estado, t.fecha_inicio, t.fecha_cierre, t.avance, t.id_cliente, t.id_atencion, t.id_tarea_padre, t.uid,
		tt.nombre AS tipo_nombre, c.nombre AS cliente_nombre
		FROM tarea t
		INNER JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
		LEFT JOIN cliente c ON c.id_cliente=t.id_cliente";
}

function rec_tarea_armar($row)
{
	$id=intval($row['id_tarea']);
	$tipo=isset($row['tipo_nombre']) ? $row['tipo_nombre'] : '';
	return array(
		'id'=>$id,
		'titulo'=>isset($row['titulo']) ? $row['titulo'] : '',
		'descripcion'=>isset($row['descripcion']) ? $row['descripcion'] : '',
		'area'=>$tipo,
		'prioridad'=>rec_prioridad_a_pantalla(isset($row['prioridad']) ? $row['prioridad'] : 'media'),
		'tipo'=>$tipo,
		'responsable'=>'',
		'responsables'=>array(),
		'enlaces'=>array(),
		'comentarios'=>array(),
		'iniciaron'=>array(),
		'id_atencion'=>isset($row['id_atencion']) ? intval($row['id_atencion']) : 0,
		'cliente_id'=>isset($row['id_cliente']) ? intval($row['id_cliente']) : 0,
		'cliente_nombre'=>isset($row['cliente_nombre']) ? $row['cliente_nombre'] : '',
		'fecha_inicio'=>isset($row['fecha_inicio']) ? $row['fecha_inicio'] : '',
		'fecha_cierre'=>isset($row['fecha_cierre']) ? $row['fecha_cierre'] : '',
		'avance'=>isset($row['avance']) ? intval($row['avance']) : 0,
		'estado'=>rec_estado_a_pantalla(isset($row['estado']) ? $row['estado'] : 'no_iniciada'),
		'id_tarea_padre'=>isset($row['id_tarea_padre']) ? intval($row['id_tarea_padre']) : 0,
		'uid'=>isset($row['uid']) ? $row['uid'] : ''
	);
}

function rec_tareas_completar($tareas)
{
	if(count($tareas)==0)
	{
		return array();
	}
	$ids=array_map('intval', array_keys($tareas));
	$ph=implode(',', array_fill(0, count($ids), '?'));
	$types=str_repeat('i', count($ids));

	$resp=array();
	foreach(acceso_consulta("SELECT id_tarea, id_empleado, es_principal FROM tarea_responsable WHERE id_tarea IN (".$ph.") ORDER BY es_principal DESC, id ASC", $types, $ids) as $row)
	{
		$resp[intval($row['id_tarea'])][]=$row;
	}
	$ini=array();
	foreach(acceso_consulta("SELECT id_tarea, id_empleado FROM tarea_inicio WHERE id_tarea IN (".$ph.")", $types, $ids) as $row)
	{
		$ini[intval($row['id_tarea'])][]=$row;
	}
	$enl=array();
	foreach(acceso_consulta("SELECT id_tarea, url, fecha_carga FROM tarea_enlace WHERE id_tarea IN (".$ph.") ORDER BY id ASC", $types, $ids) as $row)
	{
		$enl[intval($row['id_tarea'])][]=array('url'=>$row['url'], 'fecha_carga'=>isset($row['fecha_carga']) ? $row['fecha_carga'] : '');
	}
	$coms=array();
	foreach(acceso_consulta(
		"SELECT tc.id_tarea, tc.texto, tc.fecha_carga, e.nombres, e.apellido, a.clave AS area_clave
		FROM tarea_comentario tc
		INNER JOIN empleado e ON e.id_empleado=tc.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE tc.id_tarea IN (".$ph.")
		ORDER BY tc.id ASC",
		$types,
		$ids
	) as $row)
	{
		$nombre=trim($row['nombres'].' '.$row['apellido']);
		$quien=function_exists('acceso_armar_persona') ? acceso_armar_persona($nombre, $row['area_clave']) : $nombre;
		$cuando='';
		if(isset($row['fecha_carga']) && $row['fecha_carga']!='')
		{
			$cuando=date('d/m/Y H:i', strtotime($row['fecha_carga']));
		}
		$coms[intval($row['id_tarea'])][]=array(
			'texto'=>$row['texto'],
			'quien'=>$quien,
			'cuando'=>$cuando,
			'fecha_carga'=>isset($row['fecha_carga']) ? $row['fecha_carga'] : ''
		);
	}

	$lista=array();
	foreach($tareas as $id=>$tarea)
	{
		$nombres=isset($resp[$id]) ? rec_nombres_por_empleados($resp[$id], 'id_empleado') : array();
		$tarea['responsables']=$nombres;
		$tarea['responsable']=isset($nombres[0]) ? $nombres[0] : '';
		$tarea['iniciaron']=isset($ini[$id]) ? rec_nombres_por_empleados($ini[$id], 'id_empleado') : array();
		$tarea['enlaces']=isset($enl[$id]) ? $enl[$id] : array();
		$tarea['comentarios']=isset($coms[$id]) ? $coms[$id] : array();
		$lista[]=$tarea;
	}
	return $lista;
}

function rec_tareas_leer($filtros=array())
{
	$lista=array();
	$id_area=rec_id_area();
	if($id_area<=0 || !acceso_tabla_existe('tarea'))
	{
		return $lista;
	}
	$sql=rec_sql_tarea_select()." WHERE t.id_area=? AND t.estado<>'cancelada'";
	$types='i';
	$vals=array($id_area);

	if(isset($filtros['id']) && intval($filtros['id'])>0)
	{
		$sql.=" AND t.id_tarea=?";
		$types.='i';
		$vals[]=intval($filtros['id']);
	}
	if(!empty($filtros['solo_mias']))
	{
		$emp=rec_empleado_logueado();
		if($emp<=0)
		{
			return $lista;
		}
		$sql.=" AND EXISTS (SELECT 1 FROM tarea_responsable tr WHERE tr.id_tarea=t.id_tarea AND tr.id_empleado=?)";
		$types.='i';
		$vals[]=$emp;
	}
	$mes=isset($filtros['mes']) ? trim($filtros['mes']) : '';
	if($mes!='' && preg_match('/^[0-9]{4}-[0-9]{2}$/', $mes))
	{
		$sql.=" AND (DATE_FORMAT(t.fecha_inicio,'%Y-%m') COLLATE utf8mb4_unicode_ci =? OR DATE_FORMAT(t.fecha_cierre,'%Y-%m') COLLATE utf8mb4_unicode_ci =?)";
		$types.='ss';
		$vals[]=$mes;
		$vals[]=$mes;
	}
	$tipo=isset($filtros['tipo']) ? trim($filtros['tipo']) : '';
	if($tipo!='')
	{
		$sql.=" AND tt.nombre=?";
		$types.='s';
		$vals[]=$tipo;
	}
	$area_filtro=isset($filtros['area']) ? trim($filtros['area']) : '';
	if($area_filtro!='' && $area_filtro!=$tipo)
	{
		$sql.=" AND tt.nombre=?";
		$types.='s';
		$vals[]=$area_filtro;
	}
	$prioridad=isset($filtros['prioridad']) ? trim($filtros['prioridad']) : '';
	if($prioridad!='')
	{
		$sql.=" AND t.prioridad=?";
		$types.='s';
		$vals[]=rec_prioridad_a_base($prioridad);
	}
	$responsable=isset($filtros['responsable']) ? trim($filtros['responsable']) : '';
	if($responsable!='')
	{
		$id_emp=rec_id_empleado_por_etiqueta($responsable);
		if($id_emp<=0)
		{
			return $lista;
		}
		$sql.=" AND EXISTS (SELECT 1 FROM tarea_responsable tr2 WHERE tr2.id_tarea=t.id_tarea AND tr2.id_empleado=?)";
		$types.='i';
		$vals[]=$id_emp;
	}
	$estado=isset($filtros['estado']) ? trim($filtros['estado']) : '';
	if($estado=='Vencida')
	{
		$sql.=" AND t.estado<>'terminada' AND t.fecha_cierre < CURDATE()";
	}
	else if($estado!='' && $estado!='Pendiente')
	{
		$sql.=" AND t.estado=?";
		$types.='s';
		$vals[]=rec_estado_a_base($estado);
	}
	$cli=isset($filtros['cliente']) ? trim($filtros['cliente']) : '';
	if($cli!='')
	{
		$sql.=" AND c.nombre LIKE ?";
		$types.='s';
		$vals[]='%'.$cli.'%';
	}
	else if($estado=='Pendiente' || $estado=='No iniciada')
	{
		$sql.=" AND t.estado=?";
		$types.='s';
		$vals[]='no_iniciada';
	}
	$sql.=" ORDER BY t.id_tarea DESC";

	$tareas=array();
	foreach(acceso_consulta($sql, $types, $vals) as $row)
	{
		$tarea=rec_tarea_armar($row);
		$tareas[$tarea['id']]=$tarea;
	}
	return rec_tareas_completar($tareas);
}

function rec_tarea_por_id($id)
{
	$id=intval($id);
	if($id<=0)
	{
		return null;
	}
	$lista=rec_tareas_leer(array(
		'id'=>$id,
		'solo_mias'=>function_exists('acceso_es_empleado') ? acceso_es_empleado() : false
	));
	return isset($lista[0]) ? $lista[0] : null;
}

function rec_tareas_guardar_lista($lista)
{
	if(!is_array($lista))
	{
		return;
	}
	foreach($lista as $tarea)
	{
		if(is_array($tarea))
		{
			rec_tarea_guardar_una($tarea);
		}
	}
}

function rec_actividades_recientes()
{
	$salida=array();
	$id_area=rec_id_area();
	if($id_area<=0 || !acceso_tabla_existe('actividad'))
	{
		return $salida;
	}
	$filas=acceso_consulta(
		"SELECT x.tipo, x.descripcion, x.fecha_actividad, t.titulo, tt.nombre AS tipo_nombre
		FROM actividad x
		INNER JOIN tarea t ON t.id_tarea=x.id_tarea
		INNER JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
		WHERE t.id_area=?
		ORDER BY x.fecha_actividad DESC, x.id_actividad DESC
		LIMIT 8",
		'i',
		array($id_area)
	);
	$titulos=array('creada'=>'Tarea creada','proceso'=>'En proceso','completada'=>'Tarea terminada','vencida'=>'Tarea vencida');
	foreach($filas as $row)
	{
		$tipo=isset($row['tipo']) ? $row['tipo'] : 'creada';
		$cuando='';
		if(isset($row['fecha_actividad']) && $row['fecha_actividad']!='')
		{
			$ts=strtotime($row['fecha_actividad']);
			$cuando=(date('Y-m-d')==date('Y-m-d', $ts)) ? 'Hoy' : date('d/m H:i', $ts);
		}
		$salida[]=array(
			'tipo'=>$tipo,
			'titulo'=>isset($titulos[$tipo]) ? $titulos[$tipo] : 'Actividad',
			'detalle'=>isset($row['descripcion']) && $row['descripcion']!='' ? $row['descripcion'] : (isset($row['titulo']) ? $row['titulo'] : ''),
			'extra'=>isset($row['tipo_nombre']) ? $row['tipo_nombre'] : '',
			'cuando'=>$cuando
		);
	}
	return $salida;
}

}
?>
