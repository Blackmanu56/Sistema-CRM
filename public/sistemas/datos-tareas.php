<?php
// El repositorio compartido (MySQL) de tareas: avisos, tablero, etc.
if(defined('BASE_PATH') && is_file(BASE_PATH.'/includes/tareas_repositorio.php'))
{
	require_once BASE_PATH.'/includes/tareas_repositorio.php';
}
// Tareas de Sistemas. Capa de datos real: las tareas viven en MySQL
// (tarea, tarea_responsable, tarea_inicio, tarea_enlace, tarea_comentario
// y actividad). Se mantienen los nombres de las funciones que usan las
// pantallas para no tocar la interfaz.

// Esta area ya trabaja contra la base: acceso.php le delega los guardados.
function tareas_soporta_bd()
{
	return true;
}

// ---------------------------------------------------------------
// Consultas preparadas
// ---------------------------------------------------------------

// SELECT preparado: devuelve las filas como arreglo asociativo.
function tareas_bd_query($sql, $tipos='', $params=array())
{
	global $con;
	if(!$con)
	{
		return array();
	}
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return array();
	}
	$filas=array();
	if($tipos==='' || tareas_bd_vincular($stmt, $tipos, $params))
	{
		if(@mysqli_stmt_execute($stmt))
		{
			$res=@mysqli_stmt_get_result($stmt);
			if($res)
			{
				while($row=mysqli_fetch_assoc($res))
				{
					$filas[]=$row;
				}
			}
		}
	}
	@mysqli_stmt_close($stmt);
	return $filas;
}

// INSERT/UPDATE/DELETE preparado: devuelve si se pudo ejecutar.
function tareas_bd_ejecutar($sql, $tipos='', $params=array())
{
	global $con;
	if(!$con)
	{
		return false;
	}
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return false;
	}
	$ok=false;
	if($tipos==='' || tareas_bd_vincular($stmt, $tipos, $params))
	{
		$ok=@mysqli_stmt_execute($stmt);
	}
	@mysqli_stmt_close($stmt);
	return $ok;
}

// INSERT preparado: devuelve el id recien creado (0 si fallo).
function tareas_bd_insertar($sql, $tipos='', $params=array())
{
	global $con;
	if(!$con)
	{
		return 0;
	}
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return 0;
	}
	$id=0;
	if($tipos==='' || tareas_bd_vincular($stmt, $tipos, $params))
	{
		if(@mysqli_stmt_execute($stmt))
		{
			$id=intval(@mysqli_stmt_insert_id($stmt));
		}
	}
	@mysqli_stmt_close($stmt);
	return $id;
}

// Vincula los parametros por referencia, como pide mysqli.
function tareas_bd_vincular($stmt, $tipos, $params)
{
	$args=array($tipos);
	foreach($params as $clave=>$valor)
	{
		$args[]=&$params[$clave];
	}
	return @call_user_func_array(array($stmt, 'bind_param'), $args);
}

// ---------------------------------------------------------------
// Normalizacion de estados, prioridades y nombres
// ---------------------------------------------------------------

// Quita tildes para comparar nombres con la base (colacion sin acentos).
function tareas_sin_tildes($texto)
{
	$mapa=array('á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N');
	return strtr($texto, $mapa);
}

function tareas_estado_a_bd($texto)
{
	if($texto=='En proceso')
	{
		return 'en_proceso';
	}
	if($texto=='Pausada')
	{
		return 'pausada';
	}
	if($texto=='Terminada')
	{
		return 'terminada';
	}
	return 'no_iniciada';
}

function tareas_estado_a_ui($bd)
{
	if($bd=='en_proceso')
	{
		return 'En proceso';
	}
	if($bd=='pausada')
	{
		return 'Pausada';
	}
	if($bd=='terminada')
	{
		return 'Terminada';
	}
	return 'No iniciada';
}

function tareas_prioridad_a_bd($texto)
{
	$t=strtolower(trim($texto));
	$mapa=array('urgente'=>'urgente', 'alta'=>'alta', 'media'=>'media', 'baja'=>'baja');
	return isset($mapa[$t]) ? $mapa[$t] : 'media';
}

function tareas_prioridad_a_ui($bd)
{
	$mapa=array('urgente'=>'Urgente', 'alta'=>'Alta', 'media'=>'Media', 'baja'=>'Baja');
	return isset($mapa[$bd]) ? $mapa[$bd] : 'Media';
}

// ---------------------------------------------------------------
// Areas y tipos
// ---------------------------------------------------------------

function tareas_id_area_por_clave($clave)
{
	if(!function_exists('acceso_id_por_clave'))
	{
		return 0;
	}
	return acceso_id_por_clave('area', $clave);
}

// Id del area que se esta viendo (la carpeta abierta).
function tareas_id_area_panel()
{
	return tareas_id_area_por_clave(acceso_area_pantalla());
}

// Tipos de tarea del area, directo de la tabla tipo_tarea. Esto es lo
// que acceso.php usa para armar el selector cuando la funcion existe
// (contrato con el includes nuevo).
function tareas_tipos_de_area($area)
{
	$salida=array();
	$id_area=tareas_id_area_por_clave($area);
	if($id_area<=0)
	{
		return $salida;
	}
	foreach(tareas_bd_query("SELECT nombre FROM tipo_tarea WHERE id_area=? ORDER BY nombre", 'i', array($id_area)) as $row)
	{
		if(isset($row['nombre']) && trim($row['nombre'])!='' && !in_array($row['nombre'], $salida))
		{
			$salida[]=$row['nombre'];
		}
	}
	return $salida;
}

// Devuelve el id_tipo para un nombre dentro del area, directo de la
// tabla tipo_tarea (el catalogo del area es el que hay en la base).
function tareas_tipo_id_por_nombre($id_area, $nombre)
{
	global $con;
	$nombre=trim($nombre);
	if($id_area<=0 || $nombre=='' || !$con)
	{
		return 0;
	}
	$filas=tareas_bd_query("SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array($id_area, $nombre));
	return isset($filas[0]['id_tipo']) ? intval($filas[0]['id_tipo']) : 0;
}

// ---------------------------------------------------------------
// Personal: de la persona ("Lionel Garcia Mayoral - Sistemas") al empleado
// ---------------------------------------------------------------

// Indice: nombre de persona -> id_empleado, para resolver responsables.
function tareas_indice_personas_empleados()
{
	static $cache=null;
	if($cache!==null)
	{
		return $cache;
	}
	$cache=array();
	if(!function_exists('acceso_personal_completo'))
	{
		return $cache;
	}
	foreach(acceso_personal_completo(true) as $persona)
	{
		if(isset($persona['id']) && isset($persona['persona']) && $persona['persona']!='')
		{
			$cache[$persona['persona']]=intval($persona['id']);
		}
	}
	return $cache;
}

function tareas_ids_empleados_por_persona($persona)
{
	$persona=trim($persona);
	if($persona=='')
	{
		return array();
	}
	$ids=array();
	$sin_accentos=tareas_sin_tildes($persona);
	foreach(tareas_indice_personas_empleados() as $nombre=>$id)
	{
		if($nombre==$persona || tareas_sin_tildes($nombre)==$sin_accentos)
		{
			$ids[]=$id;
		}
	}
	return $ids;
}

function tareas_empleado_id_por_persona($persona)
{
	$ids=tareas_ids_empleados_por_persona($persona);
	return isset($ids[0]) ? $ids[0] : 0;
}

// Id del empleado de la sesion actual. El includes nuevo todavia no lo
// define; este area lo necesita para registrar inicios, comentarios y
// avisos. Mismo criterio que la version vieja de Lionel.
function tareas_empleado_actual_id()
{
	global $con;
	if(isset($_SESSION['id']) && intval($_SESSION['id'])>0)
	{
		return intval($_SESSION['id']);
	}
	if(isset($_SESSION['id_empleado']) && intval($_SESSION['id_empleado'])>0)
	{
		return intval($_SESSION['id_empleado']);
	}
	if(isset($_SESSION['alogin']) && trim($_SESSION['alogin'])!='' && $con)
	{
		$filas=tareas_bd_query("SELECT id_empleado FROM empleado WHERE nombre_usuario=? AND activo=1 LIMIT 1", 's', array(trim($_SESSION['alogin'])));
		if(isset($filas[0]['id_empleado']))
		{
			return intval($filas[0]['id_empleado']);
		}
	}
	return 0;
}

// ---------------------------------------------------------------
// Alcance de lo que ve cada rol
// ---------------------------------------------------------------

// Parte SQL de la visibilidad: en cada carpeta de area se ven las tareas
// de esa area. El superadmin navega entre areas pero dentro de Sistemas
// ve solo las de Sistemas; el empleado ve solo las que tiene asignadas.
function tareas_scope_where($alias='t')
{
	$w='';
	$id_area=tareas_id_area_panel();
	if($id_area>0)
	{
		$w.=' AND '.$alias.'.id_area='.intval($id_area).' ';
	}
	if(acceso_es_empleado())
	{
		$id=tareas_empleado_actual_id();
		if($id<=0)
		{
			return ' AND 1=0 ';
		}
		$w.=' AND '.$alias.'.id_tarea IN (SELECT r.id_tarea FROM tarea_responsable r WHERE r.id_empleado='.intval($id).') ';
	}
	return $w;
}

// ---------------------------------------------------------------
// Lectura de tareas
// ---------------------------------------------------------------

// Consulta base de tareas con lo que muestran las pantallas.
function tareas_sql_base()
{
	return "SELECT t.id_tarea, t.titulo, t.descripcion, t.id_area, t.id_tipo,
		t.prioridad, t.estado, t.fecha_inicio, t.fecha_cierre, t.avance,
		t.id_cliente, t.uid,
		a.clave AS area_clave, a.nombre AS area_nombre,
		tp.nombre AS tipo_nombre,
		c.nombre AS cliente_nombre
		FROM tarea t
		INNER JOIN area a ON a.id_area=t.id_area
		INNER JOIN tipo_tarea tp ON tp.id_tipo=t.id_tipo
		LEFT JOIN cliente c ON c.id_cliente=t.id_cliente";
}

// Fila de la base -> arreglo que esperan las pantallas
// (estado y prioridad en texto, area igual al tipo, como siempre).
function tareas_fila_a_pantalla($row)
{
	$tipo=isset($row['tipo_nombre']) ? $row['tipo_nombre'] : '';
	return array(
		'id'=>isset($row['id_tarea']) ? intval($row['id_tarea']) : 0,
		'titulo'=>isset($row['titulo']) ? $row['titulo'] : '',
		'descripcion'=>isset($row['descripcion']) ? $row['descripcion'] : '',
		'area'=>$tipo,
		'tipo'=>$tipo,
		'id_area'=>isset($row['id_area']) ? intval($row['id_area']) : 0,
		'area_clave'=>isset($row['area_clave']) && $row['area_clave']!='' ? $row['area_clave'] : (isset($row['clave_area']) ? $row['clave_area'] : 'sistemas'),
		'area_nombre'=>isset($row['area_nombre']) && $row['area_nombre']!='' ? $row['area_nombre'] : (isset($row['nombre_area']) ? $row['nombre_area'] : 'Sistemas'),
		'prioridad'=>tareas_prioridad_a_ui(isset($row['prioridad']) ? $row['prioridad'] : 'media'),
		'estado'=>tareas_estado_a_ui(isset($row['estado']) ? $row['estado'] : 'no_iniciada'),
		'fecha_inicio'=>isset($row['fecha_inicio']) ? $row['fecha_inicio'] : '',
		'fecha_cierre'=>isset($row['fecha_cierre']) ? $row['fecha_cierre'] : '',
		'avance'=>isset($row['avance']) ? intval($row['avance']) : 0,
		'cliente_id'=>isset($row['id_cliente']) ? intval($row['id_cliente']) : 0,
		'cliente_nombre'=>isset($row['cliente_nombre']) ? $row['cliente_nombre'] : '',
		'uid'=>isset($row['uid']) ? $row['uid'] : '',
		'responsable'=>'',
		'responsables'=>array(),
		'enlaces'=>array(),
		'comentarios'=>array(),
		'iniciaron'=>array()
	);
}

function tareas_nombre_persona_fila($f)
{
	$nombres=isset($f['nombres']) ? $f['nombres'] : '';
	$apellido=isset($f['apellido']) ? $f['apellido'] : '';
	$area=isset($f['area_clave']) ? $f['area_clave'] : '';
	if(!function_exists('acceso_armar_persona'))
	{
		return '';
	}
	return acceso_armar_persona(trim($nombres.' '.$apellido), $area);
}

// Agrega los nombres de los responsables a la tarea.
function tareas_adjuntar_responsables(&$tarea)
{
	$id=isset($tarea['id']) ? intval($tarea['id']) : 0;
	if($id<=0)
	{
		return;
	}
	$filas=tareas_bd_query("SELECT e.nombres, e.apellido, a.clave AS area_clave
		FROM tarea_responsable r
		INNER JOIN empleado e ON e.id_empleado=r.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE r.id_tarea=?
		ORDER BY r.es_principal DESC, r.id ASC", 'i', array($id));
	$nombres=array();
	foreach($filas as $f)
	{
		$nom=tareas_nombre_persona_fila($f);
		if($nom!='' && !in_array($nom, $nombres))
		{
			$nombres[]=$nom;
		}
	}
	if(count($nombres)>0)
	{
		$tarea['responsable']=$nombres[0];
	}
	$tarea['responsables']=$nombres;
}

// Quienes ya tocaron Iniciar en esta tarea.
function tareas_adjuntar_iniciaron(&$tarea)
{
	$id=isset($tarea['id']) ? intval($tarea['id']) : 0;
	if($id<=0)
	{
		return;
	}
	$filas=tareas_bd_query("SELECT e.nombres, e.apellido, a.clave AS area_clave
		FROM tarea_inicio ti
		INNER JOIN empleado e ON e.id_empleado=ti.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE ti.id_tarea=?
		ORDER BY ti.fecha_click_inicio ASC", 'i', array($id));
	$nombres=array();
	foreach($filas as $f)
	{
		$nom=tareas_nombre_persona_fila($f);
		if($nom!='' && !in_array($nom, $nombres))
		{
			$nombres[]=$nom;
		}
	}
	$tarea['iniciaron']=$nombres;
}

function tareas_adjuntar_enlaces(&$tarea)
{
	$id=isset($tarea['id']) ? intval($tarea['id']) : 0;
	if($id<=0)
	{
		return;
	}
	$enlace_comentario_disponible=function_exists('crm_tarea_enlace_comentario_preparar') && crm_tarea_enlace_comentario_preparar();
	$columna_enlace_comentario=$enlace_comentario_disponible ? 'id_tarea_comentario' : 'NULL';
	$filas=tareas_bd_query("SELECT id, url, fecha_carga, ".$columna_enlace_comentario." AS id_tarea_comentario FROM tarea_enlace WHERE id_tarea=? ORDER BY fecha_carga ASC, id ASC", 'i', array($id));
	$enlaces=array();
	foreach($filas as $f)
	{
		if(isset($f['url']) && trim($f['url'])!='')
		{
			$enlaces[]=array(
				'id'=>isset($f['id']) ? intval($f['id']) : 0,
				'url'=>$f['url'],
				'fecha_carga'=>isset($f['fecha_carga']) ? $f['fecha_carga'] : '',
				'id_tarea_comentario'=>isset($f['id_tarea_comentario']) ? intval($f['id_tarea_comentario']) : 0
			);
		}
	}
	$tarea['enlaces']=$enlaces;
}

function tareas_adjuntar_comentarios(&$tarea)
{
	$id=isset($tarea['id']) ? intval($tarea['id']) : 0;
	if($id<=0)
	{
		return;
	}
	$filas=tareas_bd_query("SELECT tc.id, tc.id_empleado, tc.id_usuario_actor, tc.texto, tc.fecha_carga,
		e.nombres, e.apellido, a.clave AS area_clave, u.nombre AS nombre_actor
		FROM tarea_comentario tc
		LEFT JOIN empleado e ON e.id_empleado=tc.id_empleado
		LEFT JOIN area a ON a.id_area=e.id_area
		LEFT JOIN usuario_cliente u ON u.id_usuario=tc.id_usuario_actor
		WHERE tc.id_tarea=?
		ORDER BY tc.fecha_carga ASC, tc.id ASC", 'i', array($id));
	$comentarios=array();
	foreach($filas as $f)
	{
		$nombre=tareas_nombre_persona_fila($f);
		if($nombre==='' && isset($f['nombre_actor'])) { $nombre=trim($f['nombre_actor']); }
		$comentarios[]=array(
			'id'=>isset($f['id']) ? intval($f['id']) : 0,
			'id_empleado'=>isset($f['id_empleado']) ? intval($f['id_empleado']) : 0,
			'id_usuario_actor'=>isset($f['id_usuario_actor']) ? intval($f['id_usuario_actor']) : 0,
			'texto'=>isset($f['texto']) ? $f['texto'] : '',
			'quien'=>$nombre,
			'cuando'=>isset($f['fecha_carga']) ? date('d/m/Y H:i', strtotime($f['fecha_carga'])) : ''
		);
	}
	$tarea['comentarios']=$comentarios;
}

// Une todos los detalles de una tarea (responsables, inicios, links, comentarios).
function tareas_adjuntar_detalle(&$tarea)
{
	tareas_adjuntar_responsables($tarea);
	tareas_adjuntar_iniciaron($tarea);
	tareas_adjuntar_enlaces($tarea);
	tareas_adjuntar_comentarios($tarea);
}

// Lista de tareas con el alcance del rol, ya en formato pantalla.
function tareas_buscar_tareas($sql_extra='', $tipos='', $params=array())
{
	global $con;
	if(!$con)
	{
		return array();
	}
	$sql=tareas_sql_base()." WHERE 1=1 ".tareas_scope_where('t').' '.$sql_extra.' ORDER BY t.id_tarea ASC';
	$lista=array();
	foreach(tareas_bd_query($sql, $tipos, $params) as $row)
	{
		$tarea=tareas_fila_a_pantalla($row);
		tareas_adjuntar_detalle($tarea);
		$lista[]=$tarea;
	}
	return $lista;
}

// Una tarea sola, con el alcance del rol aplicado.
function tareas_traer_una($id)
{
	global $con;
	$id=intval($id);
	if($id<=0 || !$con)
	{
		return null;
	}
	$sql=tareas_sql_base()." WHERE 1=1 ".tareas_scope_where('t').' AND t.id_tarea=? LIMIT 1';
	$filas=tareas_bd_query($sql, 'i', array($id));
	if(!isset($filas[0]))
	{
		return null;
	}
	$tarea=tareas_fila_a_pantalla($filas[0]);
	tareas_adjuntar_detalle($tarea);
	return $tarea;
}

// ---------------------------------------------------------------
// Funciones que usan las pantallas (mismos nombres de siempre)
// ---------------------------------------------------------------

// Quien entro: el admin ve todo; el coordinador ve las del equipo; el empleado, solo las suyas.
function tareas_iniciar_contexto()
{
	if(function_exists('acceso_area') && acceso_area()=='todas')
	{
		$_SESSION['tareas_rol']='coordinador';
	}
	else if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']!='')
	{
		$_SESSION['tareas_rol']=$_SESSION['rol_area'];
	}
	if(isset($_SESSION['persona_tareas']) && $_SESSION['persona_tareas']!='')
	{
		$_SESSION['tareas_persona']=$_SESSION['persona_tareas'];
	}
	if(isset($_SESSION['tareas_rol']) && $_SESSION['tareas_rol']=='trabajador')
	{
		$_SESSION['tareas_rol']='empleado';
	}
	if(isset($_SESSION['tareas_rol']) && ($_SESSION['tareas_rol']=='coordinador_general' || $_SESSION['tareas_rol']=='admin'))
	{
		$_SESSION['tareas_rol']='coordinador';
	}
	// Sin fallbacks de identidad: el rol y la persona salen de la sesion
	// que arma acceso_iniciar_sesion en el includes nuevo.
}

function tareas_rol()
{
	return isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : '';
}

function tareas_persona()
{
	return isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
}

// Lee las tareas visibles para la persona, directo de la base.
function tareas_lista()
{
	return tareas_visibles();
}

// En esta area las tareas ya viven en la base: no hay nada que guardar en sesion.
function tareas_guardar($lista)
{
	return true;
}

// Actividad reciente del area: sale de la tabla actividad.
function actividades_lista()
{
	global $con;
	if(!$con)
	{
		return array();
	}
	$filas=tareas_bd_query("SELECT act.id_actividad, act.tipo, act.descripcion, act.fecha_actividad,
			t.titulo AS tarea_titulo, tp.nombre AS tipo_nombre
		FROM actividad act
		INNER JOIN tarea t ON t.id_tarea=act.id_tarea
		INNER JOIN tipo_tarea tp ON tp.id_tipo=t.id_tipo
		WHERE 1=1 ".tareas_scope_where('t')."
		ORDER BY act.id_actividad DESC
		LIMIT 20", '', array());
	$titulos=array(
		'creada'=>'Tarea creada',
		'proceso'=>'Tarea en proceso',
		'completada'=>'Tarea completada',
		'vencida'=>'Tarea vencida'
	);
	$salida=array();
	foreach($filas as $f)
	{
		$tipo=isset($f['tipo']) ? $f['tipo'] : '';
		$detalle=isset($f['descripcion']) && $f['descripcion']!='' ? $f['descripcion'] : (isset($f['tarea_titulo']) ? $f['tarea_titulo'] : '');
		$salida[]=array(
			'tipo'=>$tipo,
			'titulo'=>isset($titulos[$tipo]) ? $titulos[$tipo] : 'Actividad',
			'detalle'=>$detalle,
			'extra'=>isset($f['tipo_nombre']) ? $f['tipo_nombre'] : '',
			'cuando'=>isset($f['fecha_actividad']) ? date('d/m/Y H:i', strtotime($f['fecha_actividad'])) : ''
		);
	}
	return $salida;
}

// Actividades del area para la pantalla local de Actividades (misma
// forma que la pagina compartida: titulo, descripcion, responsable y
// fecha). Salen de la tabla actividad. El alta suelta no existe en el
// esquema (actividad siempre esta atada a una tarea), por eso la
// pantalla local no ofrece el formulario compartido de JSON.
function sis_actividades_pagina()
{
	global $con;
	if(!$con)
	{
		return array();
	}
	$filas=tareas_bd_query("SELECT act.tipo, act.descripcion, act.fecha_actividad,
			t.titulo AS tarea_titulo, t.descripcion AS tarea_descripcion,
			tp.nombre AS tipo_nombre,
			e.nombres, e.apellido, a.clave AS area_clave
		FROM actividad act
		INNER JOIN tarea t ON t.id_tarea=act.id_tarea
		INNER JOIN tipo_tarea tp ON tp.id_tipo=t.id_tipo
		LEFT JOIN empleado e ON e.id_empleado=act.id_empleado
		LEFT JOIN area a ON a.id_area=e.id_area
		WHERE 1=1 ".tareas_scope_where('t')."
		ORDER BY act.id_actividad DESC
		LIMIT 200", '', array());
	$salida=array();
	foreach($filas as $f)
	{
		$descripcion=isset($f['tarea_descripcion']) ? $f['tarea_descripcion'] : '';
		if($descripcion=='' && isset($f['descripcion']) && $f['descripcion']!='')
		{
			$descripcion=$f['descripcion'];
		}
		$salida[]=array(
			'titulo'=>isset($f['tarea_titulo']) ? $f['tarea_titulo'] : '',
			'descripcion'=>$descripcion,
			'responsable'=>tareas_nombre_persona_fila($f),
			'fecha'=>isset($f['fecha_actividad']) ? date('Y-m-d', strtotime($f['fecha_actividad'])) : ''
		);
	}
	return $salida;
}

// Tareas que puede ver la persona, segun su rol, directo de la base.
function tareas_visibles()
{
	return tareas_buscar_tareas();
}

// Una tarea esta vencida si no esta terminada y la fecha de cierre ya paso.
function tarea_esta_vencida($tarea)
{
	if($tarea['estado']=='Terminada')
	{
		return false;
	}
	if($tarea['fecha_cierre']=='')
	{
		return false;
	}
	return (strtotime($tarea['fecha_cierre']) < strtotime(date('Y-m-d')));
}

function tarea_por_id($id)
{
	return tareas_traer_una($id);
}

// Proximo id (solo por compatibilidad: la base usa autoincrement).
function tareas_siguiente_id()
{
	$filas=tareas_bd_query("SELECT COALESCE(MAX(id_tarea),0)+1 AS sig FROM tarea", '', array());
	return isset($filas[0]['sig']) ? intval($filas[0]['sig']) : 1;
}

// Aplica los filtros de la pantalla (mes, area, prioridad, tipo, responsable, estado).
function tareas_filtradas()
{
	$mes=isset($_GET['mes']) ? trim($_GET['mes']) : '';
	$area=isset($_GET['area']) ? trim($_GET['area']) : '';
	$prioridad=isset($_GET['prioridad']) ? trim($_GET['prioridad']) : '';
	$tipo=isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
	$responsable=isset($_GET['responsable']) ? trim($_GET['responsable']) : '';
	$estado=isset($_GET['estado']) ? trim($_GET['estado']) : '';
	$cliente=isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
	$vence=isset($_GET['vence']) ? trim($_GET['vence']) : '';

	$extra='';
	$tipos='';
	$params=array();

	if($vence==='hoy')
	{
		$extra.=" AND t.estado NOT IN ('terminada','cancelada') AND t.fecha_cierre=CURDATE()";
	}

	if($mes!='')
	{
		$extra.=" AND (DATE_FORMAT(t.fecha_inicio,'%Y-%m') COLLATE utf8mb4_unicode_ci =? OR DATE_FORMAT(t.fecha_cierre,'%Y-%m') COLLATE utf8mb4_unicode_ci =?)";
		$tipos.='ss';
		$params[]=$mes;
		$params[]=$mes;
	}
	if($area!='')
	{
		// El filtro Area de esta pantalla filtra por tipo de tarea (igual que antes).
		$extra.=" AND tp.nombre=?";
		$tipos.='s';
		$params[]=$area;
	}
	if($prioridad!='')
	{
		$extra.=" AND t.prioridad=?";
		$tipos.='s';
		$params[]=tareas_prioridad_a_bd($prioridad);
	}
	if($tipo!='')
	{
		$extra.=" AND tp.nombre=?";
		$tipos.='s';
		$params[]=$tipo;
	}
	if($responsable!='')
	{
		$ids=tareas_ids_empleados_por_persona($responsable);
		if(count($ids)>0)
		{
			$extra.=" AND t.id_tarea IN (SELECT r.id_tarea FROM tarea_responsable r WHERE r.id_empleado IN (".implode(',', array_map('intval', $ids))."))";
		}
		else
		{
			$extra.=" AND 1=0";
		}
	}
	if($estado=='Vencida')
	{
		$extra.=" AND t.estado<>'terminada' AND t.fecha_cierre<CURDATE()";
	}
	else if($estado!='')
	{
		if($estado=='Pendiente')
		{
			$estado='No iniciada';
		}
		$extra.=" AND t.estado=?";
		$tipos.='s';
		$params[]=tareas_estado_a_bd($estado);
	}
	if($cliente!='')
	{
		$extra.=" AND c.nombre LIKE ?";
		$tipos.='s';
		$params[]='%'.$cliente.'%';
	}
	return tareas_buscar_tareas($extra, $tipos, $params);
}

// Numeros de las tarjetas de arriba. Se calculan sobre lo que la persona puede ver.
function tareas_contadores($lista)
{
	$cont=array('Pendiente'=>0,'No iniciada'=>0,'En proceso'=>0,'Pausada'=>0,'Terminada'=>0,'Vencida'=>0);
	foreach($lista as $tarea)
	{
		$est_txt=tarea_texto_estado($tarea);
		if($est_txt=='No iniciada')
		{
			$cont['Pendiente']++;
			$cont['No iniciada']++;
		}
		else if(isset($cont[$est_txt]))
		{
			$cont[$est_txt]++;
		}
		if(tarea_esta_vencida($tarea))
		{
			$cont['Vencida']++;
		}
	}
	return $cont;
}

function fecha_mostrar($fecha)
{
	if($fecha=='')
	{
		return '';
	}
	return date('d/m/Y', strtotime($fecha));
}

function texto_mostrar($texto)
{
	return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

// Arma el enlace sumando filtros u otros datos. El rol ya viene de la sesion.
function tareas_link($pagina, $extra=array())
{
	$datos=$extra;
	foreach($datos as $clave=>$valor)
	{
		if($valor==='' || $valor===null)
		{
			unset($datos[$clave]);
		}
	}
	$q=http_build_query($datos);
	return $pagina.($q!='' ? ('?'.$q) : '');
}

// Enlace de filtros: se quedan los que ya estan puestos y se cambia uno.
function tareas_link_filtro($cambios)
{
	$actual=array(
		'mes'=>isset($_GET['mes']) ? $_GET['mes'] : '',
		'area'=>isset($_GET['area']) ? $_GET['area'] : '',
		'prioridad'=>isset($_GET['prioridad']) ? $_GET['prioridad'] : '',
		'tipo'=>isset($_GET['tipo']) ? $_GET['tipo'] : '',
		'responsable'=>isset($_GET['responsable']) ? $_GET['responsable'] : '',
		'estado'=>isset($_GET['estado']) ? $_GET['estado'] : '',
		'cliente'=>isset($_GET['cliente']) ? $_GET['cliente'] : '',
		'orden'=>isset($_GET['orden']) ? $_GET['orden'] : '',
		'cal'=>isset($_GET['cal']) ? $_GET['cal'] : ''
	);
	return tareas_link('tareas.php', array_merge($actual, $cambios));
}

function mes_nombre($numero)
{
	$nombres=array(1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre');
	$n=intval($numero);
	return isset($nombres[$n]) ? $nombres[$n] : '';
}

// Meses que aparecen en las tareas, para el filtro.
function tareas_meses_disponibles()
{
	$sql_base=" FROM tarea t WHERE 1=1 ".tareas_scope_where('t');
	$meses=array();
	foreach(tareas_bd_query("SELECT DISTINCT DATE_FORMAT(t.fecha_inicio,'%Y-%m') AS m".$sql_base, '', array()) as $f)
	{
		if(isset($f['m']) && $f['m']!='')
		{
			$meses[$f['m']]=1;
		}
	}
	foreach(tareas_bd_query("SELECT DISTINCT DATE_FORMAT(t.fecha_cierre,'%Y-%m') AS m".$sql_base, '', array()) as $f)
	{
		if(isset($f['m']) && $f['m']!='')
		{
			$meses[$f['m']]=1;
		}
	}
	krsort($meses);
	return array_keys($meses);
}

function tareas_valores_unicos($campo)
{
	$valores=array();
	if($campo=='responsable')
	{
		$filas=tareas_bd_query("SELECT DISTINCT e.nombres, e.apellido, a.clave AS area_clave
			FROM tarea_responsable r
			INNER JOIN tarea t ON t.id_tarea=r.id_tarea
			INNER JOIN empleado e ON e.id_empleado=r.id_empleado
			INNER JOIN area a ON a.id_area=e.id_area
			WHERE 1=1 ".tareas_scope_where('t'), '', array());
		foreach($filas as $f)
		{
			$nom=tareas_nombre_persona_fila($f);
			if($nom!='' && !in_array($nom, $valores))
			{
				$valores[]=$nom;
			}
		}
	}
	else if($campo=='area' || $campo=='tipo')
	{
		if($campo=='tipo')
		{
			$filas=tareas_bd_query("SELECT nombre AS n FROM tipo_tarea WHERE id_area=? ORDER BY nombre", 'i', array(tareas_id_area_panel()));
		}
		else
		{
			$filas=tareas_bd_query("SELECT DISTINCT tp.nombre AS n
				FROM tarea t
				INNER JOIN tipo_tarea tp ON tp.id_tipo=t.id_tipo
				WHERE 1=1 ".tareas_scope_where('t'), '', array());
		}
		foreach($filas as $f)
		{
			if(isset($f['n']) && $f['n']!='' && !in_array($f['n'], $valores))
			{
				$valores[]=$f['n'];
			}
		}
	}
	else if($campo=='prioridad')
	{
		$filas=tareas_bd_query("SELECT DISTINCT t.prioridad AS p
			FROM tarea t
			WHERE 1=1 ".tareas_scope_where('t'), '', array());
		foreach($filas as $f)
		{
			$txt=tareas_prioridad_a_ui(isset($f['p']) ? $f['p'] : '');
			if($txt!='' && !in_array($txt, $valores))
			{
				$valores[]=$txt;
			}
		}
	}
	sort($valores);
	return $valores;
}

function label_prioridad($prioridad)
{
	if($prioridad=='Urgente') { return 'label-important'; }
	if($prioridad=='Alta' || $prioridad=='Media') { return 'label-warning'; }
	return 'label-success';
}

function label_estado($estado)
{
	if($estado=='Terminada') { return 'label-success'; }
	if($estado=='En proceso') { return 'label-info'; }
	if($estado=='Pendiente' || $estado=='No iniciada') { return 'label-warning'; }
	if($estado=='Pausada') { return 'label-inverse'; }
	return 'label-important';
}

function icono_actividad($tipo)
{
	if($tipo=='completada') { return 'fa-check-circle text-success'; }
	if($tipo=='proceso') { return 'fa-clock-o text-warning'; }
	if($tipo=='creada') { return 'fa-plus-circle text-info'; }
	return 'fa-exclamation-circle text-danger';
}

// Dias del mes que tienen alguna tarea (para marcarlos en el calendario).
function calendario_dias_con_tareas($anio, $mes, $lista)
{
	$dias=array();
	$prefijo=sprintf('%04d-%02d', $anio, $mes);
	foreach($lista as $tarea)
	{
		foreach(array('fecha_inicio','fecha_cierre') as $campo)
		{
			if(substr($tarea[$campo],0,7)==$prefijo)
			{
				$d=intval(substr($tarea[$campo],8,2));
				$dias[$d]=1;
			}
		}
	}
	return $dias;
}

// ---------------------------------------------------------------
// Guardados que delega acceso.php (solo existen aca, en Sistemas)
// ---------------------------------------------------------------

// Contrato con el formulario compartido (tareas-campos-form.php):
// el area permite cargar tipos nuevos desde el formulario.
function tareas_puede_cargar_tipo()
{
	return true;
}

// La fecha de cierre no es obligatoria al cargar (como en la version vieja).
function tareas_fecha_cierre_obligatoria()
{
	return false;
}

// Alta de tarea nueva: arranca No iniciada y en 0. Cada responsable
// tiene que tocarla Iniciar.
function tareas_datos_guardar_nueva($area)
{
	global $con;
	// Siempre devuelve el area: la pantalla redirige a tareas.php con ese valor.
	if(!$con)
	{
		return $area;
	}
	$responsables=tareas_leer_responsables_post($area);
	if(count($responsables)==0)
	{
		return $area;
	}
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
	$id_area=tareas_id_area_por_clave($area);
	$id_tipo=($id_area>0 && $tipo!='') ? tareas_tipo_id_por_nombre($id_area, $tipo) : 0;
	if($id_area<=0 || $id_tipo<=0)
	{
		return $area;
	}
	$titulo=isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
	if($titulo=='')
	{
		return $area;
	}
	$prioridad=tareas_prioridad_a_bd(isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media');
	$fecha_inicio=isset($_POST['fecha_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d');
	$fecha_cierre=isset($_POST['fecha_cierre']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : $fecha_inicio;
	if($fecha_cierre < $fecha_inicio)
	{
		$fecha_cierre=$fecha_inicio;
	}
	$descripcion=isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
	$cliente_id=($cliente['id']>0) ? $cliente['id'] : null;
	$id_empleado=tareas_empleado_actual_id();

	$id_tarea=tareas_bd_insertar("INSERT INTO tarea
		(titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance, id_cliente, uid)
		VALUES (?, ?, ?, ?, ?, 'no_iniciada', ?, ?, 0, ?, ?)",
		'ssiisssis',
		array($titulo, $descripcion, $id_area, $id_tipo, $prioridad, $fecha_inicio, $fecha_cierre, $cliente_id, uniqid('t')));
	if($id_tarea<=0)
	{
		return $area;
	}
	$primero=true;
	foreach($responsables as $nom)
	{
		$id_resp=tareas_empleado_id_por_persona($nom);
		if($id_resp<=0)
		{
			continue;
		}
		if(function_exists('crm_empleado_pertenece_a_area') && !crm_empleado_pertenece_a_area($id_resp, $id_area))
		{
			continue;
		}
		tareas_bd_ejecutar("INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?, ?, ?)", 'iii', array($id_tarea, $id_resp, $primero?1:0));
		$primero=false;
	}
	// Actividad "creada" para el feed de Actividad reciente.
	tareas_bd_ejecutar("INSERT INTO actividad (id_tarea, id_empleado, tipo, descripcion) VALUES (?, ?, 'creada', ?)", 'iis', array($id_tarea, $id_empleado, $titulo));
	// Avisos a los responsables: ahora en MySQL (tabla aviso) via el
	// repositorio compartido del includes, no mas en JSON.
	if($id_empleado>0 && function_exists('repo_avisos_crear'))
	{
		foreach($responsables as $nom)
		{
			$id_para=tareas_empleado_id_por_persona($nom);
			if($id_para>0)
			{
				@repo_avisos_crear($id_tarea, $id_empleado, $id_para);
			}
		}
	}
	return $area;
}

// Guarda lo basico de la edicion (titulo, tipo, responsables, fechas).
// Links y comentarios van en Ver.
function tareas_datos_actualizar($id)
{
	global $con;
	$id=intval($id);
	if(!$con || $id<=0)
	{
		return false;
	}
	$tarea=tareas_traer_una($id);
	if(!is_array($tarea))
	{
		return false;
	}
	if(!function_exists('tarea_puede_editar') || !tarea_puede_editar($tarea))
	{
		return false;
	}
	$area=acceso_area_pantalla();
	$id_area=tareas_id_area_panel();
	$responsables=tareas_leer_responsables_post($area, $tarea);
	if(count($responsables)==0)
	{
		return false;
	}
	$cliente=tareas_leer_cliente_post();
	$tipo=isset($_POST['tipo']) ? trim($_POST['tipo']) : (isset($tarea['tipo']) ? $tarea['tipo'] : '');
	$titulo=isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
	if($titulo=='')
	{
		return false;
	}
	$descripcion=isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
	$prioridad=tareas_prioridad_a_bd(isset($_POST['prioridad']) ? $_POST['prioridad'] : 'Media');
	$fecha_inicio=isset($_POST['fecha_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : $tarea['fecha_inicio'];
	$fecha_cierre=isset($_POST['fecha_cierre']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : $fecha_inicio;
	if($fecha_cierre < $fecha_inicio)
	{
		$fecha_cierre=$fecha_inicio;
	}
	$id_tipo=($id_area>0 && $tipo!='') ? tareas_tipo_id_por_nombre($id_area, $tipo) : 0;
	if($id_tipo<=0)
	{
		return false;
	}
	// Resolver y validar todos los responsables antes de iniciar la transacción.
	$ids_nuevos=array();
	foreach($responsables as $nom)
	{
		$rid=tareas_empleado_id_por_persona($nom);
		if($rid>0)
		{
			if(function_exists('crm_empleado_pertenece_a_area') && !crm_empleado_pertenece_a_area($rid, $id_area))
			{
				continue;
			}
			if(!in_array($rid, $ids_nuevos, true))
			{
				$ids_nuevos[]=$rid;
			}
		}
	}
	if(count($ids_nuevos)==0)
	{
		return false;
	}
	$estado_transaccion=@mysqli_query($con, "SELECT @@in_transaction");
	if(!$estado_transaccion)
	{
		return false;
	}
	$fila_transaccion=mysqli_fetch_row($estado_transaccion);
	@mysqli_free_result($estado_transaccion);
	if(!$fila_transaccion || intval($fila_transaccion[0])!==0 || !@mysqli_begin_transaction($con))
	{
		return false;
	}

	// Bloquear y revalidar el estado actual para no editar una tarea que haya
	// terminado o desaparecido desde la validación inicial.
	$stmt=@mysqli_prepare($con, "SELECT estado FROM tarea WHERE id_tarea=? LIMIT 1 FOR UPDATE");
	if(!$stmt || !@mysqli_stmt_bind_param($stmt, 'i', $id) || !@mysqli_stmt_execute($stmt))
	{
		if($stmt) { @mysqli_stmt_close($stmt); }
		@mysqli_rollback($con);
		return false;
	}
	$resultado=@mysqli_stmt_get_result($stmt);
	$fila_tarea=$resultado ? @mysqli_fetch_assoc($resultado) : null;
	if($resultado) { @mysqli_free_result($resultado); }
	@mysqli_stmt_close($stmt);
	if(!$fila_tarea || !isset($fila_tarea['estado']))
	{
		@mysqli_rollback($con);
		return false;
	}
	$tarea['estado']=$fila_tarea['estado'];
	if(!tarea_puede_editar($tarea))
	{
		@mysqli_rollback($con);
		return false;
	}

	// Leer los responsables actuales dentro de la transacción para conservarlos
	// sin escrituras innecesarias cuando el formulario no los modificó.
	$stmt=@mysqli_prepare($con, "SELECT id_empleado, es_principal FROM tarea_responsable WHERE id_tarea=? ORDER BY es_principal DESC, id_empleado ASC FOR UPDATE");
	if(!$stmt || !@mysqli_stmt_bind_param($stmt, 'i', $id) || !@mysqli_stmt_execute($stmt))
	{
		if($stmt) { @mysqli_stmt_close($stmt); }
		@mysqli_rollback($con);
		return false;
	}
	$resultado=@mysqli_stmt_get_result($stmt);
	if(!$resultado)
	{
		@mysqli_stmt_close($stmt);
		@mysqli_rollback($con);
		return false;
	}
	$ids_actuales=array();
	$id_principal_actual=0;
	while($fila=mysqli_fetch_assoc($resultado))
	{
		$id_actual=intval($fila['id_empleado']);
		$ids_actuales[]=$id_actual;
		if(intval($fila['es_principal'])===1 && $id_principal_actual===0)
		{
			$id_principal_actual=$id_actual;
		}
	}
	@mysqli_free_result($resultado);
	@mysqli_stmt_close($stmt);
	if($id_principal_actual===0 && count($ids_actuales)>0)
	{
		$id_principal_actual=$ids_actuales[0];
	}

	// Actualizar la tarea primero; cualquier error posterior revierte también esto.
	if(!tareas_bd_ejecutar("UPDATE tarea SET titulo=?, descripcion=?, id_tipo=?, prioridad=?, fecha_inicio=?, fecha_cierre=? WHERE id_tarea=?",
		'ssisssi', array($titulo, $descripcion, $id_tipo, $prioridad, $fecha_inicio, $fecha_cierre, $id)))
	{
		@mysqli_rollback($con);
		return false;
	}

	$ids_actuales_ordenados=$ids_actuales;
	$ids_nuevos_ordenados=$ids_nuevos;
	sort($ids_actuales_ordenados, SORT_NUMERIC);
	sort($ids_nuevos_ordenados, SORT_NUMERIC);
	$responsables_sin_cambios=($ids_actuales_ordenados===$ids_nuevos_ordenados && $id_principal_actual===intval($ids_nuevos[0]));
	if(!$responsables_sin_cambios)
	{
		if(!tareas_bd_ejecutar("DELETE FROM tarea_responsable WHERE id_tarea=?", 'i', array($id)))
		{
			@mysqli_rollback($con);
			return false;
		}
		$primero=true;
		foreach($ids_nuevos as $rid)
		{
			if(!tareas_bd_ejecutar("INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?, ?, ?)", 'iii', array($id, $rid, $primero?1:0)))
			{
				@mysqli_rollback($con);
				return false;
			}
			$primero=false;
		}

		// Quitar inicios solo de quienes dejaron de ser responsables.
		$in='('.implode(',', array_map('intval', $ids_nuevos)).')';
		if(!tareas_bd_ejecutar("DELETE FROM tarea_inicio WHERE id_tarea=? AND id_empleado NOT IN ".$in, 'i', array($id)))
		{
			@mysqli_rollback($con);
			return false;
		}
	}

	if(!@mysqli_commit($con))
	{
		@mysqli_rollback($con);
		return false;
	}
	return true;
}

// Procesa lo que manda Ver tarea: links, comentarios y cambios de estado.
function tareas_datos_procesar_accion()
{
	$id=isset($_POST['id_tarea']) ? intval($_POST['id_tarea']) : 0;
	if($id<=0)
	{
		return false;
	}
	if(isset($_POST['agregar_comentario']) || isset($_POST['agregar_enlace']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return false;
		}
		return tareas_datos_guardar_extras($id);
	}
	if(!isset($_POST['accion_tarea']))
	{
		return false;
	}
	$tarea=tareas_traer_una($id);
	if(!is_array($tarea))
	{
		return false;
	}
	$accion=isset($_POST['accion_tarea']) ? $_POST['accion_tarea'] : '';
	if($accion==='reabrir' || isset($_POST['reabrir_tarea']))
	{
		if(function_exists('repo_tarea_cambiar_estado'))
		{
			return repo_tarea_cambiar_estado($id, 'en_proceso', tareas_empleado_actual_id());
		}
	}
	if(!function_exists('tarea_puede_cambiar_estado') || !tarea_puede_cambiar_estado($tarea))
	{
		return false;
	}
	$accion=$_POST['accion_tarea'];
	$est=isset($tarea['estado']) ? $tarea['estado'] : 'No iniciada';
	$id_empleado=tareas_empleado_actual_id();
	$actividad_tipo='';
	$titulo=$tarea['titulo'];
	if($accion=='iniciar' && $est!='Terminada' && $est!='Pausada')
	{
		if($id_empleado>0)
		{
			tareas_bd_ejecutar("INSERT IGNORE INTO tarea_inicio (id_tarea, id_empleado) VALUES (?, ?)", 'ii', array($id, $id_empleado));
		}
		tareas_bd_ejecutar("UPDATE tarea SET estado='en_proceso' WHERE id_tarea=?", 'i', array($id));
		$actividad_tipo='proceso';
	}
	else if($accion=='pausar' && $est=='En proceso')
	{
		tareas_bd_ejecutar("UPDATE tarea SET estado='pausada' WHERE id_tarea=?", 'i', array($id));
	}
	else if($accion=='reanudar' && $est=='Pausada')
	{
		tareas_bd_ejecutar("UPDATE tarea SET estado='en_proceso' WHERE id_tarea=?", 'i', array($id));
		$actividad_tipo='proceso';
	}
	else if($accion=='terminar' && ($est=='En proceso' || $est=='Pausada'))
	{
		tareas_bd_ejecutar("UPDATE tarea SET estado='terminada', avance=100 WHERE id_tarea=?", 'i', array($id));
		$actividad_tipo='completada';
	}
	if($actividad_tipo!='')
	{
		tareas_bd_ejecutar("INSERT INTO actividad (id_tarea, id_empleado, tipo, descripcion) VALUES (?, ?, ?, ?)", 'iiss', array($id, $id_empleado, $actividad_tipo, $titulo));
	}
	if(function_exists('crm_tarea_sync_padre_de'))
	{
		crm_tarea_sync_padre_de($id);
	}
	return true;
}

// Agrega un link o un comentario nuevo a una tarea (desde Ver tarea).
function tareas_datos_guardar_extras($id)
{
	$id=intval($id);
	if($id<=0)
	{
		return false;
	}
	$tarea=tareas_traer_una($id);
	if(!is_array($tarea))
	{
		return false;
	}
	if(!function_exists('tarea_puede_agregar_extras') || !tarea_puede_agregar_extras($tarea))
	{
		return false;
	}
	$id_empleado=tareas_empleado_actual_id();
	$ok=false;
	if(isset($_POST['agregar_enlace']))
	{
		$url=isset($_POST['enlace_nuevo']) ? trim($_POST['enlace_nuevo']) : '';
		if($url!='')
		{
			$ok=tareas_bd_ejecutar("INSERT INTO tarea_enlace (id_tarea, url) VALUES (?, ?)", 'is', array($id, $url)) || $ok;
		}
	}
	if(isset($_POST['agregar_comentario']))
	{
		$txt=isset($_POST['comentario_nuevo']) ? trim($_POST['comentario_nuevo']) : '';
		if($txt!='')
		{
			$ok=tareas_bd_ejecutar("INSERT INTO tarea_comentario (id_tarea, id_empleado, texto) VALUES (?, ?, ?)", 'iis', array($id, $id_empleado, $txt)) || $ok;
		}
	}
	return $ok;
}

// Carga un tipo nuevo para el area desde el formulario de la tarea.
function tareas_tipo_cargar($nombre)
{
	global $con;
	$nombre=trim($nombre);
	if($nombre=='' || strlen($nombre)>80 || !$con)
	{
		return '';
	}
	$id_area=tareas_id_area_panel();
	if($id_area<=0)
	{
		return '';
	}
	$filas=tareas_bd_query("SELECT nombre FROM tipo_tarea WHERE id_area=? AND nombre=? LIMIT 1", 'is', array($id_area, $nombre));
	if(isset($filas[0]['nombre']) && $filas[0]['nombre']!='')
	{
		return $filas[0]['nombre'];
	}
	$id=tareas_bd_insertar("INSERT INTO tipo_tarea (id_area, nombre) VALUES (?, ?)", 'is', array($id_area, $nombre));
	if($id<=0)
	{
		return '';
	}
	return $nombre;
}

// Procesa el boton "Cargar tipo" manteniendo lo que el usuario ya cargo en el formulario.
function tareas_aplicar_carga_tipo($tarea)
{
	if(!isset($_POST['cargar_tipo']))
	{
		return $tarea;
	}
	if(!is_array($tarea))
	{
		$tarea=array();
	}
	$campos=array('titulo','descripcion','tipo','prioridad','fecha_inicio','fecha_cierre','cliente_nombre');
	foreach($campos as $campo)
	{
		if(isset($_POST[$campo]))
		{
			$tarea[$campo]=$_POST[$campo];
		}
	}
	if(isset($_POST['cliente_id']))
	{
		$tarea['cliente_id']=intval($_POST['cliente_id']);
	}
	if(isset($_POST['responsables']) && is_array($_POST['responsables']))
	{
		$tarea['responsables']=$_POST['responsables'];
	}
	$cargado=tareas_tipo_cargar(isset($_POST['tipo_nuevo']) ? $_POST['tipo_nuevo'] : '');
	if($cargado!='')
	{
		$tarea['tipo']=$cargado;
	}
	return $tarea;
}
?>
