<?php
// Repositorio de tareas: migra el manejo de sesiones/JSON a MySQL.
// Todas las funciones usan global $con (mysqli procedural) y prepared statements
// para proteger contra inyeccion SQL en los campos que reciben datos externos.
// Se usa @ en llamadas mysqli porque el proyecto tiene MYSQLI_REPORT_OFF.

if(!function_exists('repo_estado_a_display'))
{

// =========================================================================
// A. Conversion de estados y prioridades entre display y base de datos
// =========================================================================

/**
 * Convierte el estado de la base de datos al texto que se muestra en pantalla.
 * Por ejemplo: 'no_iniciada' → 'No iniciada'.
 * Esto centraliza la logica de presentacion para que no quede duplicada en la UI.
 */
function repo_estado_a_display($estado_db)
{
	$map=array(
		'no_iniciada'=>'No iniciada',
		'en_proceso'=>'En proceso',
		'pausada'=>'Pausada',
		'terminada'=>'Terminada',
		'cancelada'=>'Eliminada',
		'eliminada'=>'Eliminada'
	);
	return isset($map[$estado_db]) ? $map[$estado_db] : $estado_db;
}

/**
 * Convierte el texto de pantalla del estado al valor de la base de datos.
 * Por ejemplo: 'No iniciada' → 'no_iniciada'.
 * Se usa al recibir datos del formulario para guardarlos correctamente.
 */
function repo_estado_a_db($estado_display)
{
	$map=array(
		'No iniciada'=>'no_iniciada',
		'En proceso'=>'en_proceso',
		'Pausada'=>'pausada',
		'Terminada'=>'terminada',
		'Eliminada'=>'cancelada',
		'eliminada'=>'cancelada',
		'Cancelada'=>'cancelada',
		'cancelada'=>'cancelada'
	);
	return isset($map[$estado_display]) ? $map[$estado_display] : strtolower($estado_display);
}

/**
 * Convierte la prioridad de pantalla al valor de la base de datos.
 * Por ejemplo: 'Urgente' → 'urgente'.
 */
function repo_prioridad_a_db($prioridad_display)
{
	return strtolower(trim($prioridad_display));
}

/**
 * Convierte la prioridad de la base de datos al texto de pantalla.
 * Por ejemplo: 'urgente' → 'Urgente'.
 */
function repo_prioridad_a_display($prioridad_db)
{
	$map=array(
		'urgente'=>'Urgente',
		'alta'=>'Alta',
		'media'=>'Media',
		'baja'=>'Baja'
	);
	return isset($map[$prioridad_db]) ? $map[$prioridad_db] : ucfirst($prioridad_db);
}


// =========================================================================
// B. Resolucion de empleados
// =========================================================================

/**
 * Arma el texto de display de un empleado: "Nombres Apellido - Area".
 * Recibe una fila con las claves 'nombres', 'apellido' y 'nombre_area' (o 'area_clave').
 * Se usa tanto para listar responsables como para mostrar quién hizo una accion.
 */
function repo_nombre_empleado($row)
{
	$nombres=trim(isset($row['nombres']) ? $row['nombres'] : '');
	$apellido=trim(isset($row['apellido']) ? $row['apellido'] : '');
	$nombre_completo=trim($nombres.' '.$apellido);
	// Priorizar clave_area + acceso_etiqueta_corta_area() sobre nombre_area.
	// nombre_area es el nombre completo de la BD (ej: "Coordinación de comercio exterior"),
	// mientras que acceso_etiqueta_corta_area() retorna la etiqueta de display
	// (ej: "Comercio Exterior"). El resto del sistema usa la etiqueta corta para
	// los nombres de display, asi que debemos usarla aqui para que tarea_es_responsable()
	// pueda comparar correctamente los nombres.
	$area='';
	if(isset($row['clave_area']) && $row['clave_area']!='')
	{
		$area=acceso_etiqueta_corta_area($row['clave_area']);
	}
	if($area=='' && isset($row['nombre_area']))
	{
		$area=trim($row['nombre_area']);
	}
	if($area=='' && isset($row['area_clave']))
	{
		$area=acceso_etiqueta_corta_area($row['area_clave']);
	}
	if($area=='')
	{
		return $nombre_completo;
	}
	return $nombre_completo.' - '.$area;
}

/**
 * Convierte un nombre de display tipo "Juan Perez - Comercio Exterior" a su ID de empleado.
 * Hace JOIN con area para comparar el nombre del area tambien.
 * Retorna 0 si no lo encuentra.
 */
function repo_nombre_a_id($nombre_display)
{
	global $con;
	$nombre_display=trim($nombre_display);
	if($nombre_display=='' || !$con)
	{
		return 0;
	}
	// Los formularios de responsables envían IDs opacos para evitar ambigüedades
	// cuando una persona tiene más de un rol o varias personas comparten nombre.
	if(preg_match('/^id:(\\d+)$/', $nombre_display, $match))
	{
		return intval($match[1]);
	}
	// Separar nombre y area por el separador " - "
	$partes=explode(' - ', $nombre_display, 2);
	$nombre_persona=trim($partes[0]);
	$nombre_area=isset($partes[1]) ? trim($partes[1]) : '';
	// Buscar por CONCAT(nombres,' ',apellido) y JOIN con area para filtrar por nombre de area
	$sql="SELECT e.id_empleado
		FROM empleado e
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE CONCAT(e.nombres,' ',e.apellido)=?
		AND e.activo=1";
	$types='s';
	$params=array($nombre_persona);
	if($nombre_area!='')
	{
		// Buscar el area por: nombre completo en BD, nombre corto (display), o clave.
		// El display usa el nombre corto (ej: "Comercio Exterior") pero la BD guarda
		// el nombre largo ("Coordinación de comercio exterior"). Por eso se prueban las 3 opciones.
		$sql.=" AND (a.nombre=? OR a.clave=? OR a.nombre LIKE ?)";
		$types.='sss';
		$params[]=$nombre_area;  // intento con el nombre tal cual viene del display
		// Buscar la clave del area a partir del nombre corto
		$clave_area='';
		foreach(acceso_etiquetas_area() as $clave=>$titulo)
		{
			if(acceso_etiqueta_corta_area($clave)===$nombre_area)
			{
				$clave_area=$clave;
				break;
			}
		}
		$params[]=$clave_area;  // intento con la clave (ej: "comercio")
		// LIKE para cubrir casos donde el nombre corto es parte del nombre largo
		$params[]='%'.$nombre_area.'%';
	}
	$sql.=" LIMIT 1";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return 0;
	}
	@mysqli_stmt_bind_param($stmt, $types, ...$params);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	$row=mysqli_fetch_assoc($result);
	@mysqli_stmt_close($stmt);
	if(!$row && $nombre_area!='' && function_exists('acceso_tabla_existe') && acceso_tabla_existe('empleado_rol_adicional'))
	{
		$sql_ad="SELECT e.id_empleado FROM empleado_rol_adicional x
			INNER JOIN empleado e ON e.id_empleado=x.id_empleado
			INNER JOIN area a ON a.id_area=x.id_area
			WHERE CONCAT(e.nombres,' ',e.apellido)=? AND e.activo=1 AND x.fecha_desde<=CURDATE() AND x.fecha_hasta IS NULL
			AND (a.nombre=? OR a.clave=? OR a.nombre LIKE ?) LIMIT 1";
		$stmt_ad=@mysqli_prepare($con, $sql_ad);
		if($stmt_ad)
		{
			@mysqli_stmt_bind_param($stmt_ad, 'ssss', $nombre_persona, $nombre_area, $clave_area, $params[3]);
			@mysqli_stmt_execute($stmt_ad); $res_ad=@mysqli_stmt_get_result($stmt_ad);
			$row=$res_ad ? @mysqli_fetch_assoc($res_ad) : null;
			@mysqli_stmt_close($stmt_ad);
		}
	}
	return $row ? intval($row['id_empleado']) : 0;
}

/**
 * Convierte un ID de empleado a su nombre de display "Nombres Apellido - Area".
 * Retorna string vacio si no lo encuentra.
 */
function repo_id_a_nombre($id_empleado)
{
	global $con;
	$id=intval($id_empleado);
	if($id<=0 || !$con)
	{
		return '';
	}
	$sql="SELECT e.nombres, e.apellido, a.nombre AS nombre_area
		FROM empleado e
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE e.id_empleado=? AND e.activo=1
		LIMIT 1";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return '';
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	$row=mysqli_fetch_assoc($result);
	@mysqli_stmt_close($stmt);
	if(!$row)
	{
		return '';
	}
	return repo_nombre_empleado($row);
}

/**
 * Retorna el ID del empleado logueado (desde la sesion).
 * Se usa como referencia en creaciones y acciones sobre tareas.
 */
function repo_empleado_logueado()
{
	return isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
}

/**
 * Retorna el nombre de display del empleado logueado.
 * Se usa para mostrar "quien hizo X" en la UI.
 */
function repo_nombre_logueado()
{
	if(isset($_SESSION['persona_tareas']) && $_SESSION['persona_tareas']!='')
	{
		return $_SESSION['persona_tareas'];
	}
	if(isset($_SESSION['nombre_mostrado']) && $_SESSION['nombre_mostrado']!='')
	{
		return $_SESSION['nombre_mostrado'];
	}
	$id=repo_empleado_logueado();
	if($id>0)
	{
		return repo_id_a_nombre($id);
	}
	return '';
}


// =========================================================================
// C. Listado de tareas
// =========================================================================

/**
 * Construye la consulta base para traer tareas con todos sus JOINs necesarios.
 * Se usa como sub-consulta en listar y en por_id para no repetir el SQL.
 * El WHERE se agrega despues segun los filtros y el rol del usuario.
 */
function _repo_tareas_sql_base()
{
	return "SELECT t.id_tarea, t.titulo, t.descripcion, t.id_area, t.id_tipo,
		t.prioridad, t.estado, t.fecha_inicio, t.fecha_cierre, t.avance,
		t.id_cliente, t.id_atencion, t.id_tarea_padre, t.uid, t.creado_en,
		tt.nombre AS tipo_nombre,
		a.clave AS clave_area, a.nombre AS nombre_area,
		CASE WHEN c.nombre IS NOT NULL AND c.nombre!='' THEN c.nombre ELSE '' END AS cliente_nombre,
		IFNULL(c.cuil_cuit, '') AS cliente_cuit,
		IFNULL(cp.dni, '') AS cliente_dni
		FROM tarea t
		INNER JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
		INNER JOIN area a ON a.id_area=t.id_area
		LEFT JOIN cliente c ON c.id_cliente=t.id_cliente
		LEFT JOIN cliente_persona cp ON cp.id_cliente=c.id_cliente";
}

/**
 * Carga los datos relacionados de una tarea (responsables, enlaces, comentarios, quiénes iniciaron).
 * Se llama por cada tarea del listado para armar el array completo como lo tenia la sesion.
 * Se usa referencia ($tarea) para modificar el array original sin copiarlo.
 */
function _repo_tareas_cargar_relacionados($id_tarea, &$tarea)
{
	global $con;
	$id=intval($id_tarea);

	// Responsables: el primero es el "principal"
	// Se usa prepared statement a pesar de que $id es intval, por consistencia
	// con la politica de Prepared Statements del proyecto.
	// IMPORTANTE: Se usa a.clave para formatear el nombre del area con
	// acceso_etiqueta_corta_area(), que es la misma funcion que usa el resto
	// del sistema para los nombres de display. Sin esto, los nombres no coinciden
	// (ej: "Comercio Exterior" vs "Coordinación de comercio exterior") y
	// tarea_es_responsable() falla, making the employee unable to see or change tasks.
	$tarea['responsables']=array();
	$tarea['responsable']='';
	$sql_r="SELECT CONCAT(e.nombres,' ',e.apellido) AS nombre_completo, a.clave AS clave_area
		FROM tarea_responsable tr
		INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE tr.id_tarea=? ORDER BY tr.es_principal DESC, e.apellido";
	$stmt_r=@mysqli_prepare($con, $sql_r);
	if($stmt_r)
	{
		@mysqli_stmt_bind_param($stmt_r, 'i', $id);
		@mysqli_stmt_execute($stmt_r);
		$result_r=@mysqli_stmt_get_result($stmt_r);
		if($result_r)
		{
			while($row=mysqli_fetch_assoc($result_r))
			{
				$area_label=acceso_etiqueta_corta_area($row['clave_area']);
				$nom=trim($row['nombre_completo']).' - '.$area_label;
				if(!in_array($nom, $tarea['responsables']))
				{
					$tarea['responsables'][]=$nom;
				}
			}
		}
		@mysqli_stmt_close($stmt_r);
	}
	if(count($tarea['responsables'])>0)
	{
		$tarea['responsable']=$tarea['responsables'][0];
	}

	// Enlaces de la tarea
	$tarea['enlaces']=array();
	$enlace_comentario_disponible=function_exists('crm_tarea_enlace_comentario_preparar') && crm_tarea_enlace_comentario_preparar();
	$columna_enlace_comentario=$enlace_comentario_disponible ? 'id_tarea_comentario' : 'NULL';
	$sql_e="SELECT id, url, fecha_carga, ".$columna_enlace_comentario." AS id_tarea_comentario FROM tarea_enlace WHERE id_tarea=? ORDER BY fecha_carga";
	$stmt_e=@mysqli_prepare($con, $sql_e);
	if($stmt_e)
	{
		@mysqli_stmt_bind_param($stmt_e, 'i', $id);
		@mysqli_stmt_execute($stmt_e);
		$result_e=@mysqli_stmt_get_result($stmt_e);
		if($result_e)
		{
			while($row=mysqli_fetch_assoc($result_e))
			{
				$tarea['enlaces'][]=array(
					'id'=>intval($row['id']),
					'url'=>$row['url'],
					'fecha_carga'=>$row['fecha_carga'],
					'id_tarea_comentario'=>isset($row['id_tarea_comentario']) ? intval($row['id_tarea_comentario']) : 0
				);
			}
		}
		@mysqli_stmt_close($stmt_e);
	}

	// Comentarios: JOIN con empleado para saber quién los hizo
	// Se usa a.clave + acceso_etiqueta_corta_area() para consistencia con
	// los nombres de display del sistema (ej: "Juan - Comercio Exterior").
	$tarea['comentarios']=array();
	$sql_c="SELECT tc.id, tc.id_empleado, tc.id_usuario_actor, tc.texto,
		NULLIF(TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))), '') AS nombre_completo,
		a.clave AS clave_area, u.nombre AS nombre_actor, tc.fecha_carga
		FROM tarea_comentario tc
		LEFT JOIN empleado e ON e.id_empleado=tc.id_empleado
		LEFT JOIN area a ON a.id_area=e.id_area
		LEFT JOIN usuario_cliente u ON u.id_usuario=tc.id_usuario_actor
		WHERE tc.id_tarea=? ORDER BY tc.fecha_carga";
	$stmt_c=@mysqli_prepare($con, $sql_c);
	if($stmt_c)
	{
		@mysqli_stmt_bind_param($stmt_c, 'i', $id);
		@mysqli_stmt_execute($stmt_c);
		$result_c=@mysqli_stmt_get_result($stmt_c);
		if($result_c)
		{
			while($row=mysqli_fetch_assoc($result_c))
			{
				$nombre=isset($row['nombre_completo']) ? trim($row['nombre_completo']) : '';
				$area_label=isset($row['clave_area']) && $row['clave_area']!==null ? acceso_etiqueta_corta_area($row['clave_area']) : '';
				$quien=$nombre!=='' ? $nombre.($area_label!=='' ? ' - '.$area_label : '') : (isset($row['nombre_actor']) && trim($row['nombre_actor'])!=='' ? trim($row['nombre_actor']) : 'Usuario');
				$tarea['comentarios'][]=array(
					'id'=>intval($row['id']),
					'id_empleado'=>isset($row['id_empleado']) ? intval($row['id_empleado']) : 0,
					'id_usuario_actor'=>isset($row['id_usuario_actor']) ? intval($row['id_usuario_actor']) : 0,
					'texto'=>$row['texto'],
					'quien'=>$quien,
					'cuando'=>($row['fecha_carga']!='' ? date('d/m/Y H:i', strtotime($row['fecha_carga'])) : ''),
					'fecha_carga'=>$row['fecha_carga']
				);
			}
		}
		@mysqli_stmt_close($stmt_c);
	}

	// Quiénes tocaron "Iniciar"
	// Mismo patron: a.clave + acceso_etiqueta_corta_area() para nombres consistentes.
	$tarea['iniciaron']=array();
	$sql_i="SELECT CONCAT(e.nombres,' ',e.apellido) AS nombre_completo, a.clave AS clave_area
		FROM tarea_inicio ti
		INNER JOIN empleado e ON e.id_empleado=ti.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE ti.id_tarea=? ORDER BY ti.fecha_click_inicio";
	$stmt_i=@mysqli_prepare($con, $sql_i);
	if($stmt_i)
	{
		@mysqli_stmt_bind_param($stmt_i, 'i', $id);
		@mysqli_stmt_execute($stmt_i);
		$result_i=@mysqli_stmt_get_result($stmt_i);
		if($result_i)
		{
			while($row=mysqli_fetch_assoc($result_i))
			{
				$area_label=acceso_etiqueta_corta_area($row['clave_area']);
				$nom=trim($row['nombre_completo']).' - '.$area_label;
				if(!in_array($nom, $tarea['iniciaron']))
				{
					$tarea['iniciaron'][]=$nom;
				}
			}
		}
		@mysqli_stmt_close($stmt_i);
	}

	// Quién creó/asignó la tarea (registro actividad tipo creada)
	$tarea['creada_por']='';
	$sql_cr="SELECT CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS nombre_completo
		FROM actividad act
		INNER JOIN empleado e ON e.id_empleado=act.id_empleado
		WHERE act.id_tarea=? AND act.tipo='creada' AND act.id_empleado>0
		ORDER BY act.fecha_actividad ASC LIMIT 1";
	$stmt_cr=@mysqli_prepare($con, $sql_cr);
	if($stmt_cr)
	{
		@mysqli_stmt_bind_param($stmt_cr, 'i', $id);
		@mysqli_stmt_execute($stmt_cr);
		$result_cr=@mysqli_stmt_get_result($stmt_cr);
		if($result_cr && ($row_cr=mysqli_fetch_assoc($result_cr)))
		{
			$tarea['creada_por']=trim($row_cr['nombre_completo']);
		}
		@mysqli_stmt_close($stmt_cr);
	}
}

/**
 * Lista las tareas aplicando filtros y reglas de visibilidad segun el rol.
 * $id_area: area de la pantalla actual.
 * $filtros: array con claves mes, prioridad, tipo, responsable, estado, area.
 * $rol_efectivo: 'empleado' filtra solo tareas donde el usuario es responsable.
 * $id_empleado: ID del empleado logueado, usado cuando rol_efectivo='empleado'.
 * Retorna array de arrays con la estructura que espera la UI.
 */
function repo_tareas_listar($id_area, $filtros, $rol_efectivo, $id_empleado)
{
	global $con;
	$salida=array();
	if(!$con)
	{
		return $salida;
	}

	$sql=_repo_tareas_sql_base();
	$wheres=array();
	$types='';
	$params=array();

	// Filtro por area: solo si no es coordinador general/superadmin que ve todo
	if($id_area>0)
	{
		$wheres[]="t.id_area=?";
		$types.='i';
		$params[]=$id_area;
	}

	// Filtro de visibilidad para empleado: solo ver tareas donde soy responsable
	if($rol_efectivo==='empleado' && $id_empleado>0)
	{
		$wheres[]="EXISTS(SELECT 1 FROM tarea_responsable tr WHERE tr.id_tarea=t.id_tarea AND tr.id_empleado=?)";
		$types.='i';
		$params[]=$id_empleado;
	}

	// Filtros de la UI
	if(isset($filtros['mes']) && $filtros['mes']!='')
	{
		// Mes en formato YYYY-MM: buscar en fecha_inicio y fecha_cierre
		$wheres[]="(DATE_FORMAT(t.fecha_inicio,'%Y-%m') COLLATE utf8mb4_unicode_ci =? OR DATE_FORMAT(t.fecha_cierre,'%Y-%m') COLLATE utf8mb4_unicode_ci =?)";
		$types.='ss';
		$params[]=$filtros['mes'];
		$params[]=$filtros['mes'];
	}
	if(isset($filtros['prioridad']) && $filtros['prioridad']!='')
	{
		$wheres[]="t.prioridad=?";
		$types.='s';
		$params[]=repo_prioridad_a_db($filtros['prioridad']);
	}
	if(isset($filtros['tipo']) && $filtros['tipo']!='')
	{
		$wheres[]="tt.nombre=?";
		$types.='s';
		$params[]=$filtros['tipo'];
	}
	if(isset($filtros['responsable']) && $filtros['responsable']!='')
	{
		// Buscar el ID del responsable por su nombre de display
		$id_resp=repo_nombre_a_id($filtros['responsable']);
		if($id_resp>0)
		{
			$wheres[]="EXISTS(SELECT 1 FROM tarea_responsable tr2 WHERE tr2.id_tarea=t.id_tarea AND tr2.id_empleado=?)";
			$types.='i';
			$params[]=$id_resp;
		}
	}
	if(isset($filtros['estado']) && $filtros['estado']!='')
	{
		if($filtros['estado']==='Vencida')
		{
			// Vencida: fecha de cierre pasó y la tarea no está terminada
			$wheres[]="t.fecha_cierre<CURDATE() AND t.estado!='terminada'";
		}
		else
		{
			$wheres[]="t.estado=?";
			$types.='s';
			$params[]=repo_estado_a_db($filtros['estado']);
		}
	}
	if(isset($filtros['area']) && $filtros['area']!='' && $filtros['area']!=='Vencida')
	{
		$area_val = trim($filtros['area']);
		$clave_match = '';
		if(function_exists('acceso_etiquetas_area'))
		{
			foreach(acceso_etiquetas_area() as $c => $etq)
			{
				if($c === $area_val || (function_exists('acceso_etiqueta_corta_area') && acceso_etiqueta_corta_area($c) === $area_val) || $etq === $area_val)
				{
					$clave_match = $c;
					break;
				}
			}
		}
		if($clave_match !== '')
		{
			$wheres[] = "(a.clave=? OR a.nombre=?)";
			$types .= 'ss';
			$params[] = $clave_match;
			$params[] = $area_val;
		}
		else
		{
			$wheres[] = "(a.clave=? OR a.nombre=?)";
			$types .= 'ss';
			$params[] = $area_val;
			$params[] = $area_val;
		}
	}
	if(isset($filtros['cliente']) && trim($filtros['cliente'])!='')
	{
		$wheres[]="(c.nombre LIKE ? OR c.cuil_cuit LIKE ? OR IFNULL(c.correo,'') LIKE ?)";
		$types.='sss';
		$like='%'.trim($filtros['cliente']).'%';
		$params[]=$like;
		$params[]=$like;
		$params[]=$like;
	}

	if(count($wheres)>0)
	{
		$sql.=" WHERE ".implode(' AND ', $wheres);
	}
	$sql.=" ORDER BY FIELD(t.prioridad,'urgente','alta','media','baja'), t.fecha_cierre ASC, t.titulo";

	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	if(count($params)>0)
	{
		@mysqli_stmt_bind_param($stmt, $types, ...$params);
	}
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	@mysqli_stmt_close($stmt);

	if(!$result)
	{
		return $salida;
	}

	while($row=mysqli_fetch_assoc($result))
	{
		$tarea=array(
			'id'=>intval($row['id_tarea']),
			'titulo'=>$row['titulo'],
			'descripcion'=>$row['descripcion'],
			'area'=>$row['nombre_area'],
			'area_nombre'=>$row['nombre_area'],
			'clave_area'=>$row['clave_area'],
			'area_clave'=>$row['clave_area'],
			'prioridad'=>repo_prioridad_a_display($row['prioridad']),
			'tipo'=>$row['tipo_nombre'],
			'responsable'=>'',
			'responsables'=>array(),
			'enlaces'=>array(),
			'comentarios'=>array(),
			'iniciaron'=>array(),
			'cliente_id'=>intval($row['id_cliente']),
			'id_atencion'=>isset($row['id_atencion']) ? intval($row['id_atencion']) : 0,
			'cliente_nombre'=>$row['cliente_nombre'],
			'cliente_cuit'=>isset($row['cliente_cuit']) ? $row['cliente_cuit'] : '',
			'cliente_dni'=>isset($row['cliente_dni']) ? $row['cliente_dni'] : '',
			'fecha_inicio'=>$row['fecha_inicio'],
			'fecha_cierre'=>$row['fecha_cierre'],
			'avance'=>intval($row['avance']),
			'estado'=>repo_estado_a_display($row['estado']),
			'id_area'=>intval($row['id_area']),
			'uid'=>$row['uid'],
			'creado_en'=>isset($row['creado_en']) ? $row['creado_en'] : ''
		);
		// Cargar responsables, enlaces, comentarios e iniciaron
		_repo_tareas_cargar_relacionados($tarea['id'], $tarea);
		$salida[]=$tarea;
	}
	return $salida;
}

/**
 * Trae una tarea por su ID con todos los datos relacionados.
 * $rol_efectivo e $id_empleado se pasan para filtrar por visibilidad (igual que listar).
 * Retorna el array de la tarea o null si no existe o no tiene permiso.
 */
function repo_tarea_por_id($id_tarea, $rol_efectivo='', $id_empleado=0)
{
	global $con;
	$id=intval($id_tarea);
	if($id<=0 || !$con)
	{
		return null;
	}

	$sql=_repo_tareas_sql_base()." WHERE t.id_tarea=?";
	$types='i';
	$params=array($id);

	// Filtro de visibilidad para empleado
	if($rol_efectivo==='empleado' && $id_empleado>0)
	{
		$sql.=" AND EXISTS(SELECT 1 FROM tarea_responsable tr WHERE tr.id_tarea=t.id_tarea AND tr.id_empleado=?)";
		$types.='i';
		$params[]=$id_empleado;
	}
	$sql.=" LIMIT 1";

	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return null;
	}
	@mysqli_stmt_bind_param($stmt, $types, ...$params);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	$row=mysqli_fetch_assoc($result);
	@mysqli_stmt_close($stmt);

	if(!$row)
	{
		return null;
	}
	$tarea=array(
		'id'=>intval($row['id_tarea']),
		'titulo'=>$row['titulo'],
		'descripcion'=>$row['descripcion'],
		'area'=>$row['nombre_area'],
		'prioridad'=>repo_prioridad_a_display($row['prioridad']),
		'tipo'=>$row['tipo_nombre'],
		'responsable'=>'',
		'responsables'=>array(),
		'enlaces'=>array(),
		'comentarios'=>array(),
		'iniciaron'=>array(),
		'cliente_id'=>intval($row['id_cliente']),
		'id_atencion'=>isset($row['id_atencion']) ? intval($row['id_atencion']) : 0,
		'cliente_nombre'=>$row['cliente_nombre'],
		'cliente_cuit'=>isset($row['cliente_cuit']) ? $row['cliente_cuit'] : '',
		'cliente_dni'=>isset($row['cliente_dni']) ? $row['cliente_dni'] : '',
		'fecha_inicio'=>$row['fecha_inicio'],
		'fecha_cierre'=>$row['fecha_cierre'],
		'avance'=>intval($row['avance']),
		'estado'=>repo_estado_a_display($row['estado']),
		'id_area'=>intval($row['id_area']),
		'area_clave'=>$row['clave_area'],
		'area_nombre'=>$row['nombre_area'],
		'uid'=>$row['uid'],
		'creado_en'=>isset($row['creado_en']) ? $row['creado_en'] : ''
	);
	_repo_tareas_cargar_relacionados($id, $tarea);
	if(function_exists('tarea_puede_ver') && !tarea_puede_ver($tarea))
	{
		return null;
	}
	return $tarea;
}


// =========================================================================
// D. Contadores para los filtros de estado
// =========================================================================

/**
 * Cuenta las tareas por estado para el area dada, respetando la visibilidad del rol.
 * Retorna array con claves: Pendiente, No iniciada, En proceso, Pausada, Terminada, Vencida.
 * "Vencida" no es un estado de BD sino una condicion: fecha_cierre < hoy AND no terminada.
 */
function repo_tareas_contadores($id_area, $rol_efectivo, $id_empleado)
{
	global $con;
	$contadores=array(
		'Pendiente'=>0,
		'No iniciada'=>0,
		'En proceso'=>0,
		'Pausada'=>0,
		'Terminada'=>0,
		'Vencida'=>0
	);
	if(!$con)
	{
		return $contadores;
	}

	// Condiciones base de visibilidad: area y rol empleado
	$wheres_base=array();
	$types_base='';
	$params_base=array();

	if($id_area>0)
	{
		$wheres_base[]="t.id_area=?";
		$types_base.='i';
		$params_base[]=$id_area;
	}
	if($rol_efectivo==='empleado' && $id_empleado>0)
	{
		$wheres_base[]="EXISTS(SELECT 1 FROM tarea_responsable tr WHERE tr.id_tarea=t.id_tarea AND tr.id_empleado=?)";
		$types_base.='i';
		$params_base[]=$id_empleado;
	}

	// Contar por cada estado usando prepared statements
	$estados=array(
		'No iniciada'=>'no_iniciada',
		'En proceso'=>'en_proceso',
		'Pausada'=>'pausada',
		'Terminada'=>'terminada'
	);
	foreach($estados as $display=>$db_valor)
	{
		$wheres=$wheres_base;
		$types=$types_base;
		$params=$params_base;
		// Agregar condicion de estado
		$wheres[]="t.estado=?";
		$types.='s';
		$params[]=$db_valor;
		$sql="SELECT COUNT(*) AS total FROM tarea t WHERE ".implode(' AND ', $wheres);
		$stmt=@mysqli_prepare($con, $sql);
		if(!$stmt)
		{
			continue;
		}
		@mysqli_stmt_bind_param($stmt, $types, ...$params);
		@mysqli_stmt_execute($stmt);
		$result=@mysqli_stmt_get_result($stmt);
		$fila=mysqli_fetch_assoc($result);
		@mysqli_stmt_close($stmt);
		$contadores[$display]=$fila ? intval($fila['total']) : 0;
	}

	// Pendiente es synonym de No iniciada
	$contadores['Pendiente']=$contadores['No iniciada'];

	// Vencida: fecha_cierre pasó y no está terminada
	$wheres_venc=$wheres_base;
	$wheres_venc[]="t.fecha_cierre<CURDATE()";
	$wheres_venc[]="t.estado!='terminada'";
	$sql_venc="SELECT COUNT(*) AS total FROM tarea t WHERE ".implode(' AND ', $wheres_venc);
	$stmt=@mysqli_prepare($con, $sql_venc);
	if($stmt)
	{
		@mysqli_stmt_bind_param($stmt, $types_base, ...$params_base);
		@mysqli_stmt_execute($stmt);
		$result=@mysqli_stmt_get_result($stmt);
		$fila=mysqli_fetch_assoc($result);
		@mysqli_stmt_close($stmt);
		$contadores['Vencida']=$fila ? intval($fila['total']) : 0;
	}

	return $contadores;
}


// =========================================================================
// E. Valores unicos para los filtros
// =========================================================================

/**
 * Retorna los meses (YYYY-MM) que tienen tareas en el area dada.
 * Se toma de fecha_inicio y fecha_cierre para cubrir tareas que empezaron un mes
 * y se cierran en otro.
 */
function repo_tareas_meses_disponibles($id_area)
{
	global $con;
	$salida=array();
	if(!$con)
	{
		return $salida;
	}
	$area_id = intval($id_area);
	$sql="SELECT DISTINCT mes FROM (
		SELECT DATE_FORMAT(fecha_inicio,'%Y-%m') AS mes FROM tarea WHERE (?=0 OR id_area=?)
		UNION
		SELECT DATE_FORMAT(fecha_cierre,'%Y-%m') AS mes FROM tarea WHERE (?=0 OR id_area=?) AND fecha_cierre IS NOT NULL
	) AS meses WHERE mes IS NOT NULL ORDER BY mes DESC";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'iiii', $area_id, $area_id, $area_id, $area_id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	if(!$result)
	{
		@mysqli_stmt_close($stmt);
		return $salida;
	}
	while($row=mysqli_fetch_assoc($result))
	{
		if($row['mes']!='')
		{
			$salida[]=$row['mes'];
		}
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}

/**
 * Retorna los nombres de tipo de tarea disponibles para el area dada.
 * Estos son los valores que se muestran en el filtro "Tipo".
 */
function repo_tareas_tipos_disponibles($id_area)
{
	global $con;
	$salida=array();
	if(!$con)
	{
		return $salida;
	}
	$area_id = intval($id_area);
	$sql="SELECT DISTINCT nombre FROM tipo_tarea WHERE (?=0 OR id_area=?) ORDER BY nombre";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'ii', $area_id, $area_id);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_bind_result($stmt, $nombre);
	while(@mysqli_stmt_fetch($stmt))
	{
		if(trim($nombre)!='')
		{
			$salida[]=trim($nombre);
		}
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}

/**
 * Retorna los nombres de display de empleados que son responsables de tareas en el area.
 * Se usa para popular el filtro "Responsable" en la UI.
 */
function repo_tareas_responsables_disponibles($id_area)
{
	global $con;
	$salida=array();
	if(!$con)
	{
		return $salida;
	}
	$area_id = intval($id_area);
	$sql="SELECT DISTINCT e.nombres, e.apellido, a.clave AS clave_area, a.nombre AS nombre_area
		FROM tarea_responsable tr
		INNER JOIN tarea t ON t.id_tarea=tr.id_tarea
		INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE (?=0 OR t.id_area=?) AND e.activo=1
		ORDER BY e.apellido, e.nombres";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'ii', $area_id, $area_id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	if(!$result)
	{
		@mysqli_stmt_close($stmt);
		return $salida;
	}
	while($row=mysqli_fetch_assoc($result))
	{
		$nombre=repo_nombre_empleado($row);
		if($nombre!='' && !in_array($nombre, $salida))
		{
			$salida[]=$nombre;
		}
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}


// =========================================================================
// F. ABM de tareas (crear, actualizar, cambiar estado)
// =========================================================================

/**
 * Crea una tarea nueva con todos sus datos basicos, la asocia a los responsables
 * y registra la actividad de creacion. Usa transaccion para asegurar consistencia.
 * $datos: titulo, descripcion, id_area, id_tipo, prioridad, fecha_inicio, fecha_cierre, id_cliente
 * $responsables_ids: array de IDs de empleados responsables
 * $id_empleado_creador: ID de quien crea la tarea (para el registro de actividad)
 * Retorna el ID de la tarea creada o 0 si fallo.
 */
function repo_tarea_crear($datos, $responsables_ids, $id_empleado_creador)
{
	global $con;
	if(!$con || !empty($GLOBALS['tareas_responsable_post_invalido']))
	{
		return 0;
	}

	$titulo=trim(isset($datos['titulo']) ? $datos['titulo'] : '');
	$descripcion=trim(isset($datos['descripcion']) ? $datos['descripcion'] : '');
	$id_area=intval(isset($datos['id_area']) ? $datos['id_area'] : 0);
	$id_tipo=intval(isset($datos['id_tipo']) ? $datos['id_tipo'] : 0);
	$prioridad=repo_prioridad_a_db(isset($datos['prioridad']) ? $datos['prioridad'] : 'media');
	$fecha_inicio=isset($datos['fecha_inicio']) ? $datos['fecha_inicio'] : null;
	$fecha_cierre=isset($datos['fecha_cierre']) ? $datos['fecha_cierre'] : null;
	$id_cliente=intval(isset($datos['id_cliente']) ? $datos['id_cliente'] : 0);

	// Validaciones basicas: el titulo es obligatorio y el area/tipo deben existir
	if($titulo=='' || $id_area<=0 || $id_tipo<=0)
	{
		return 0;
	}
	if($fecha_inicio==='') { $fecha_inicio=null; }
	if($fecha_cierre==='') { $fecha_cierre=null; }
	if($id_cliente<=0) { $id_cliente=null; }
	if(function_exists('tareas_validar_fechas_alta'))
	{
		$err=tareas_validar_fechas_alta($fecha_inicio ? $fecha_inicio : '', $fecha_cierre ? $fecha_cierre : '');
		if($err!='')
		{
			if(function_exists('tareas_mensaje_form_set'))
			{
				tareas_mensaje_form_set($err);
			}
			return 0;
		}
	}
	// Nunca crear una tarea con responsable omitido, duplicado o no autorizado.
	$responsables_validos=array();
	foreach((array)$responsables_ids as $rid)
	{
		$rid=intval($rid);
		if($rid<=0 || !repo_responsable_autorizado_area($rid, $id_area)) { return 0; }
		if(!in_array($rid, $responsables_validos, true)) { $responsables_validos[]=$rid; }
	}
	if(count($responsables_validos)===0) { return 0; }
	$responsables_ids=$responsables_validos;

	// Iniciar transaccion: si falla algun INSERT, se revierte todo
	@mysqli_begin_transaction($con);

	// 1. Insertar la tarea
	$sql_tarea="INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, fecha_inicio, fecha_cierre, id_cliente)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt=@mysqli_prepare($con, $sql_tarea);
	if(!$stmt)
	{
		@mysqli_rollback($con);
		return 0;
	}
	@mysqli_stmt_bind_param($stmt, 'ssiissss',
		$titulo, $descripcion, $id_area, $id_tipo, $prioridad, $fecha_inicio, $fecha_cierre, $id_cliente);
	if(!@mysqli_stmt_execute($stmt))
	{
		@mysqli_stmt_close($stmt);
		@mysqli_rollback($con);
		return 0;
	}
	$id_tarea=mysqli_insert_id($con);
	@mysqli_stmt_close($stmt);
	if($id_tarea>0 && function_exists('crm_aplicar_eje_post'))
	{
		if(!isset($datos['eje']) && isset($_POST['eje']))
		{
			$datos['eje']=$_POST['eje'];
		}
		crm_aplicar_eje_post($id_tarea, $id_tipo);
	}
	if($id_tarea>0 && isset($_POST['id_atencion']) && intval($_POST['id_atencion'])>0)
	{
		@mysqli_query($con, "UPDATE tarea SET id_atencion='".intval($_POST['id_atencion'])."' WHERE id_tarea='".intval($id_tarea)."'");
	}
	if($id_tarea>0 && $id_cliente && function_exists('crm_vincular_cliente_area'))
	{
		crm_vincular_cliente_area(intval($id_cliente), $id_area);
	}

	if($id_tarea<=0)
	{
		@mysqli_rollback($con);
		return 0;
	}

	// 2. Asociar responsables
	if(count($responsables_ids)>0)
	{
		$sql_resp="INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?, ?, ?)";
		$stmt=@mysqli_prepare($con, $sql_resp);
		if(!$stmt)
		{
			@mysqli_rollback($con);
			return 0;
		}
		$primero=true;
		foreach($responsables_ids as $rid)
		{
			$rid=intval($rid);
			if($rid<=0) { @mysqli_stmt_close($stmt); @mysqli_rollback($con); return 0; }
			$es_principal=$primero ? 1 : 0;
			@mysqli_stmt_bind_param($stmt, 'iii', $id_tarea, $rid, $es_principal);
			if(!@mysqli_stmt_execute($stmt))
			{
				@mysqli_stmt_close($stmt);
				@mysqli_rollback($con);
				return 0;
			}
			$primero=false;
		}
		@mysqli_stmt_close($stmt);
	}

	// 3. Registrar actividad de creacion
	$sql_act="INSERT INTO actividad (id_tarea, id_empleado, tipo, descripcion, fecha_actividad)
		VALUES (?, ?, 'creada', ?, NOW())";
	$stmt=@mysqli_prepare($con, $sql_act);
	if($stmt)
	{
		$desc=$titulo;
		@mysqli_stmt_bind_param($stmt, 'iis', $id_tarea, $id_empleado_creador, $desc);
		@mysqli_stmt_execute($stmt);
		@mysqli_stmt_close($stmt);
	}

	@mysqli_commit($con);
	return $id_tarea;
}

/**
 * Actualiza los datos basicos de una tarea y reemplaza sus responsables.
 * Usa transaccion: si falla, se revierte todo.
 * Retorna true si se actualizo correctamente, false si fallo.
 */
function repo_tarea_actualizar($id_tarea, $datos, $responsables_ids)
{
	global $con;
	$id=intval($id_tarea);
	if($id<=0 || !$con || !empty($GLOBALS['tareas_responsable_post_invalido']))
	{
		return false;
	}
	$tarea_actual=function_exists('repo_tarea_por_id') ? repo_tarea_por_id($id) : null;
	if(!$tarea_actual || !function_exists('tarea_puede_editar') || !tarea_puede_editar($tarea_actual))
	{
		return false;
	}

	$titulo=trim(isset($datos['titulo']) ? $datos['titulo'] : '');
	$descripcion=trim(isset($datos['descripcion']) ? $datos['descripcion'] : '');
	$id_area=intval(isset($datos['id_area']) ? $datos['id_area'] : 0);
	if($id_area<=0)
	{
		$stmt_a=@mysqli_prepare($con, "SELECT id_area FROM tarea WHERE id_tarea=? LIMIT 1");
		if($stmt_a)
		{
			@mysqli_stmt_bind_param($stmt_a, 'i', $id);
			@mysqli_stmt_execute($stmt_a);
			$res_a=@mysqli_stmt_get_result($stmt_a);
			$row_a=$res_a ? @mysqli_fetch_assoc($res_a) : null;
			@mysqli_stmt_close($stmt_a);
			if($row_a && isset($row_a['id_area']))
			{
				$id_area=intval($row_a['id_area']);
			}
		}
	}
	$id_tipo=intval(isset($datos['id_tipo']) ? $datos['id_tipo'] : 0);
	$prioridad=repo_prioridad_a_db(isset($datos['prioridad']) ? $datos['prioridad'] : 'media');
	$fecha_inicio=isset($datos['fecha_inicio']) ? $datos['fecha_inicio'] : null;
	$fecha_cierre=isset($datos['fecha_cierre']) ? $datos['fecha_cierre'] : null;
	$id_cliente=intval(isset($datos['id_cliente']) ? $datos['id_cliente'] : 0);

	if($titulo=='' || $id_area<=0 || $id_tipo<=0)
	{
		return false;
	}
	// Conservar responsables históricos aunque hayan perdido autorización, pero
	// impedir que se añadan responsables inactivos o ajenos al área de la tarea.
	$anteriores=function_exists('repo_tarea_responsable_ids') ? repo_tarea_responsable_ids($id) : array();
	$responsables_validos=array();
	foreach((array)$responsables_ids as $rid)
	{
		$rid=intval($rid);
		if($rid<=0) { return false; }
		$es_historico=in_array($rid, $anteriores, true);
		if(!$es_historico && !repo_responsable_autorizado_area($rid, $id_area)) { return false; }
		if(!in_array($rid, $responsables_validos, true)) { $responsables_validos[]=$rid; }
	}
	foreach($anteriores as $rid_anterior)
	{
		if(!repo_responsable_autorizado_area($rid_anterior, $id_area) && !in_array($rid_anterior, $responsables_validos, true))
		{
			$responsables_validos[]=$rid_anterior;
		}
	}
	if(count($responsables_validos)===0) { return false; }
	$responsables_ids=$responsables_validos;
	if($fecha_inicio==='') { $fecha_inicio=null; }
	if($fecha_cierre==='') { $fecha_cierre=null; }
	if($id_cliente<=0) { $id_cliente=null; }

	@mysqli_begin_transaction($con);

	// 1. Actualizar datos de la tarea
	$sql_upd="UPDATE tarea SET titulo=?, descripcion=?, id_area=?, id_tipo=?,
		prioridad=?, fecha_inicio=?, fecha_cierre=?, id_cliente=?
		WHERE id_tarea=?";
	$stmt=@mysqli_prepare($con, $sql_upd);
	if(!$stmt)
	{
		@mysqli_rollback($con);
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'ssiissssi',
		$titulo, $descripcion, $id_area, $id_tipo, $prioridad, $fecha_inicio, $fecha_cierre, $id_cliente, $id);
	if(!@mysqli_stmt_execute($stmt))
	{
		@mysqli_stmt_close($stmt);
		@mysqli_rollback($con);
		return false;
	}
	@mysqli_stmt_close($stmt);

	// 2. Eliminar responsables anteriores y asociar los nuevos
	$sql_del="DELETE FROM tarea_responsable WHERE id_tarea=?";
	$stmt=@mysqli_prepare($con, $sql_del);
	if(!$stmt)
	{
		@mysqli_rollback($con);
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_close($stmt);

	if(count($responsables_ids)>0)
	{
		$sql_resp="INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?, ?, ?)";
		$stmt=@mysqli_prepare($con, $sql_resp);
		if(!$stmt)
		{
			@mysqli_rollback($con);
			return false;
		}
		$primero=true;
		foreach($responsables_ids as $rid)
		{
			$rid=intval($rid);
			if($rid<=0) { @mysqli_stmt_close($stmt); @mysqli_rollback($con); return false; }
			$es_principal=$primero ? 1 : 0;
			@mysqli_stmt_bind_param($stmt, 'iii', $id, $rid, $es_principal);
			if(!@mysqli_stmt_execute($stmt))
			{
				@mysqli_stmt_close($stmt);
				@mysqli_rollback($con);
				return false;
			}
			$primero=false;
		}
		@mysqli_stmt_close($stmt);
	}

	@mysqli_commit($con);
	return true;
}

/**
 * Cambia el estado de una tarea y ejecuta las acciones correspondientes:
 * - 'en_proceso' (iniciar): registra quien inicio, pone avance minimo 10
 * - 'terminada': pone avance en 100
 * Tambien registra la actividad del cambio.
 * Retorna true/false segun si tuvo exito.
 */
function repo_tarea_cambiar_estado($id_tarea, $nuevo_estado, $id_empleado)
{
	global $con;
	$id=intval($id_tarea);
	$id_emp=intval($id_empleado);
	$nuevo_estado=trim($nuevo_estado);
	if($id<=0 || $id_emp<=0 || $nuevo_estado=='' || !$con)
	{
		return false;
	}
	$db_estado=repo_estado_a_db($nuevo_estado);

	// -------------------------------------------------------------------
	// Validar transiciones de estado permitidas.
	// Se obtiene el estado actual ANTES de permitir el cambio.
	// Esto evita que se ejecute un POST manual con una transicion invalida
	// (por ejemplo, pausar una tarea que esta "no_iniciada").
	// -------------------------------------------------------------------
	$sql_check="SELECT estado FROM tarea WHERE id_tarea=?";
	$stmt_check=@mysqli_prepare($con, $sql_check);
	if(!$stmt_check) { return false; }
	@mysqli_stmt_bind_param($stmt_check, 'i', $id);
	@mysqli_stmt_execute($stmt_check);
	$result_check=@mysqli_stmt_get_result($stmt_check);
	$fila_check=$result_check ? @mysqli_fetch_assoc($result_check) : null;
	@mysqli_stmt_close($stmt_check);
	if(!$fila_check) { return false; }

	$estado_actual=$fila_check['estado'];
	$tarea_actual=repo_tarea_por_id($id);
	if(!$tarea_actual || (function_exists('tarea_padre_finalizada') && tarea_padre_finalizada($tarea_actual)))
	{
		return false;
	}

	// Mapa de transiciones validas: estado_actual => array de estados destino permitidos
	$transiciones_validas=array(
		'no_iniciada'=>array('en_proceso'),           // solo se puede iniciar
		'en_proceso' =>array('pausada','terminada'),  // pausar o terminar
		'pausada'    =>array('en_proceso','terminada'),// reanudar o terminar
		'terminada'  =>array('en_proceso')             // reabrir tarea
	);
	if(!isset($transiciones_validas[$estado_actual]) || !in_array($db_estado, $transiciones_validas[$estado_actual]))
	{
		// Transicion no permitida: no se ejecuta nada
		return false;
	}

	// Si se esta reabriendo una tarea terminada, validar permisos especificos
	if($estado_actual==='terminada' && $db_estado==='en_proceso')
	{
		$tarea_full=repo_tarea_por_id($id);
		if(!$tarea_full || !function_exists('tarea_puede_reabrir') || !tarea_puede_reabrir($tarea_full))
		{
			return false;
		}
	}

	@mysqli_begin_transaction($con);

	// Actualizar el estado de la tarea
	if($estado_actual==='terminada' && $db_estado==='en_proceso')
	{
		$sql="UPDATE tarea SET estado=?, fecha_cierre_real=NULL WHERE id_tarea=?";
	}
	else if($db_estado==='terminada')
	{
		// Guardar estado, avance y fecha real en una sola operación. La columna
		// fecha_cierre_real es DATE, por lo que CURDATE() respeta su tipo.
		$sql="UPDATE tarea SET estado=?, avance=100, fecha_cierre_real=CURDATE() WHERE id_tarea=?";
	}
	else
	{
		$sql="UPDATE tarea SET estado=? WHERE id_tarea=?";
	}
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		@mysqli_rollback($con);
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'si', $db_estado, $id);
	if(!@mysqli_stmt_execute($stmt))
	{
		@mysqli_stmt_close($stmt);
		@mysqli_rollback($con);
		return false;
	}
	@mysqli_stmt_close($stmt);

	// Acciones especiales segun el nuevo estado
	if($db_estado==='en_proceso')
	{
		// Registrar quien inicio la tarea (si no estaba ya registrado)
		// INSERT IGNORE evita duplicados si el empleado ya habia iniciado antes
		$sql_ini="INSERT IGNORE INTO tarea_inicio (id_tarea, id_empleado, fecha_click_inicio)
			VALUES (?, ?, NOW())";
		$stmt=@mysqli_prepare($con, $sql_ini);
		if($stmt)
		{
			@mysqli_stmt_bind_param($stmt, 'ii', $id, $id_emp);
			@mysqli_stmt_execute($stmt);
			@mysqli_stmt_close($stmt);
		}
		// El avance no se modifica automaticamente al iniciar; permanece en su valor actual (ej. 0%).
		// Desaparecer notificacion de campanita (marcada como leida al iniciar la tarea)
		if(function_exists('crm_aviso_tarea_marcar_leido_por_tarea'))
		{
			crm_aviso_tarea_marcar_leido_por_tarea($id, $id_emp);
		}
		else
		{
			@mysqli_query($con, "UPDATE aviso SET confirmado=1 WHERE id_tarea=".intval($id)." AND (id_empleado_para=".intval($id_emp)." OR id_empleado_para=0)");
			$chk_crm=@mysqli_query($con, "SHOW TABLES LIKE 'crm_aviso'");
			if($chk_crm && @mysqli_num_rows($chk_crm)>0)
			{
				@mysqli_query($con, "UPDATE crm_aviso SET confirmado=1 WHERE id_tarea=".intval($id)." AND (id_empleado_para=".intval($id_emp)." OR id_empleado_para=0)");
			}
		}
	}

	// -------------------------------------------------------------------
	// Registrar actividad del cambio de estado.
	// La columna actividad.tipo es un ENUM con valores fijos:
	// 'creada', 'proceso', 'completada', 'vencida'.
	// Hay que mapear el estado de la tarea al valor valido del ENUM.
	// No se puede guardar 'en_proceso', 'pausada' o 'terminada' directamente.
	// -------------------------------------------------------------------
	$map_estado_a_actividad=array(
		'en_proceso'  =>'proceso',
		'pausada'     =>'proceso',     // pausada se registra como "proceso" con descripcion
		'terminada'   =>'completada',
		'no_iniciada' =>'creada'
	);
	$tipo_act=isset($map_estado_a_actividad[$db_estado]) ? $map_estado_a_actividad[$db_estado] : 'proceso';

	// Construir descripcion descriptiva del cambio
	$desc='Estado cambiado a '.repo_estado_a_display($db_estado);
	if($db_estado==='pausada')
	{
		$desc='Tarea pausada por el responsable';
	}
	else if($db_estado==='en_proceso' && $estado_actual==='pausada')
	{
		$desc='Tarea reanudada por el responsable';
	}
	else if($db_estado==='en_proceso' && $estado_actual==='terminada')
	{
		$desc='Tarea reabierta por el coordinador';
	}

	$sql_act="INSERT INTO actividad (id_tarea, id_empleado, tipo, descripcion, fecha_actividad)
		VALUES (?, ?, ?, ?, NOW())";
	$stmt=@mysqli_prepare($con, $sql_act);
	if($stmt)
	{
		@mysqli_stmt_bind_param($stmt, 'iiss', $id, $id_emp, $tipo_act, $desc);
		@mysqli_stmt_execute($stmt);
		@mysqli_stmt_close($stmt);
	}

	@mysqli_commit($con);
	if(function_exists('crm_tarea_sync_padre_de'))
	{
		crm_tarea_sync_padre_de($id);
	}
	return true;
}


// =========================================================================
// G. Enlaces, comentarios, actividad
// =========================================================================

/**
 * Agrega un enlace (URL) a una tarea.
 * Retorna true si se inserto correctamente.
 */
function repo_tarea_agregar_enlace($id_tarea, $url)
{
	global $con;
	$id=intval($id_tarea);
	$url=trim($url);
	if($id<=0 || $url=='' || !$con)
	{
		return false;
	}
	$chk=@mysqli_query($con, "SELECT estado FROM tarea WHERE id_tarea=".$id);
	if($chk && ($f=@mysqli_fetch_assoc($chk)))
	{
		$est=isset($f['estado']) ? strtolower(trim($f['estado'])) : '';
		if($est==='no_iniciada' || $est==='terminada' || $est==='cancelada')
		{
			return false;
		}
	}
	$sql="INSERT INTO tarea_enlace (id_tarea, url, fecha_carga) VALUES (?, ?, NOW())";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'is', $id, $url);
	$ok=@mysqli_stmt_execute($stmt);
	@mysqli_stmt_close($stmt);
	return $ok;
}

/**
 * Retorna los enlaces de una tarea como array de arrays con id, url y fecha_carga.
 */
function repo_tarea_enlaces($id_tarea)
{
	global $con;
	$id=intval($id_tarea);
	$salida=array();
	if($id<=0 || !$con)
	{
		return $salida;
	}
	$sql="SELECT id, url, fecha_carga FROM tarea_enlace WHERE id_tarea=? ORDER BY fecha_carga";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	while($row=mysqli_fetch_assoc($result))
	{
		$salida[]=array(
			'id'=>intval($row['id']),
			'url'=>$row['url'],
			'fecha_carga'=>$row['fecha_carga']
		);
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}

/**
 * Agrega un comentario a una tarea. El autor es el empleado que lo escribe.
 * Retorna true si se inserto correctamente.
 */
function repo_tarea_agregar_comentario($id_tarea, $id_empleado, $texto)
{
	global $con;
	$id_t=intval($id_tarea);
	$id_e=intval($id_empleado);
	$texto=trim($texto);
	if($id_t<=0 || $id_e<=0 || $texto=='' || !$con)
	{
		return false;
	}
	$chk=@mysqli_query($con, "SELECT estado FROM tarea WHERE id_tarea=".$id_t);
	if($chk && ($f=@mysqli_fetch_assoc($chk)))
	{
		$est=isset($f['estado']) ? strtolower(trim($f['estado'])) : '';
		if($est==='no_iniciada' || $est==='terminada' || $est==='cancelada')
		{
			return false;
		}
	}
	$sql="INSERT INTO tarea_comentario (id_tarea, id_empleado, texto, fecha_carga) VALUES (?, ?, ?, NOW())";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'iis', $id_t, $id_e, $texto);
	$ok=@mysqli_stmt_execute($stmt);
	@mysqli_stmt_close($stmt);
	return $ok;
}

/**
 * Retorna los comentarios de una tarea con el nombre de quien los hizo.
 * Cada item tiene: texto, quien (display name), cuándo (fecha).
 */
function repo_tarea_comentarios($id_tarea)
{
	global $con;
	$id=intval($id_tarea);
	$salida=array();
	if($id<=0 || !$con)
	{
		return $salida;
	}
	$sql="SELECT tc.texto,
		CONCAT(e.nombres,' ',e.apellido) AS nombre_completo,
		a.nombre AS nombre_area,
		tc.fecha_carga
		FROM tarea_comentario tc
		INNER JOIN empleado e ON e.id_empleado=tc.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE tc.id_tarea=?
		ORDER BY tc.fecha_carga";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	while($row=mysqli_fetch_assoc($result))
	{
		$salida[]=array(
			'texto'=>$row['texto'],
			'quien'=>trim($row['nombre_completo']).' - '.trim($row['nombre_area']),
			'cuando'=>$row['fecha_carga']
		);
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}

/**
 * Retorna la actividad reciente del area (ultimas 10 entradas).
 * Se usa para el feed de actividad en la pantalla principal del area.
 * JOIN con tarea para filtrar por area y con empleado para el nombre.
 */
function repo_tarea_actividad($id_area)
{
	global $con;
	$salida=array();
	$id_a=intval($id_area);
	if($id_a<=0 || !$con)
	{
		return $salida;
	}
	$sql="SELECT act.tipo, t.titulo, act.descripcion,
		CASE WHEN e.nombres IS NOT NULL
			THEN CONCAT(e.nombres,' ',e.apellido,' - ',a2.nombre)
			ELSE '' END AS nombre_empleado,
		act.fecha_actividad
		FROM actividad act
		INNER JOIN tarea t ON t.id_tarea=act.id_tarea
		LEFT JOIN empleado e ON e.id_empleado=act.id_empleado
		LEFT JOIN area a2 ON a2.id_area=e.id_area
		WHERE t.id_area=?
		ORDER BY act.fecha_actividad DESC
		LIMIT 10";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id_a);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	while($row=mysqli_fetch_assoc($result))
	{
		$tipo=$row['tipo'];
		$titulo_text='';
		$detalle='';
		$extra='';
		if($tipo==='creada')
		{
			$titulo_text='Tarea creada';
			$detalle=$row['titulo'];
		}
		else if($tipo==='proceso')
		{
			$titulo_text='En proceso';
			$detalle=$row['titulo'];
		}
		else if($tipo==='completada')
		{
			$titulo_text='Tarea completada';
			$detalle=$row['titulo'];
		}
		else if($tipo==='vencida')
		{
			$titulo_text='Tarea vencida';
			$detalle=$row['titulo'];
		}
		else
		{
			$titulo_text=$row['titulo'];
			$detalle=isset($row['descripcion']) ? $row['descripcion'] : '';
		}
		$salida[]=array(
			'tipo'=>$tipo,
			'titulo'=>$titulo_text,
			'detalle'=>$detalle,
			'extra'=>$row['nombre_empleado'],
			'cuando'=>$row['fecha_actividad']
		);
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}

/** Historial legible, limitado estrictamente a los IDs de tareas visibles. */
function repo_tarea_historial_por_ids($tareas)
{
	global $con;
	$ids=array();
	foreach((array)$tareas as $tarea)
	{
		$id=isset($tarea['id_tarea']) ? intval($tarea['id_tarea']) : (isset($tarea['id']) ? intval($tarea['id']) : 0);
		if($id>0) { $ids[$id]=$id; }
	}
	if(!$con || count($ids)===0) { return array(); }
	$lista_ids=implode(',',array_values($ids));
	$sql="SELECT MAX(act.id_actividad) AS id_actividad, act.id_tarea, t.titulo AS tarea_titulo,
		act.tipo, act.descripcion, act.fecha_actividad, act.id_empleado,
		t.estado AS tarea_estado_actual, t.prioridad AS tarea_prioridad_actual,
		t.descripcion AS tarea_descripcion, t.fecha_inicio AS tarea_fecha_inicio,
		t.fecha_cierre AS tarea_fecha_vencimiento, ar.nombre AS tarea_area_nombre,
		(SELECT GROUP_CONCAT(DISTINCT NULLIF(TRIM(CONCAT(IFNULL(er.nombres,''),' ',IFNULL(er.apellido,''))),'') ORDER BY tr.es_principal DESC, er.apellido, er.nombres SEPARATOR ', ')
			FROM tarea_responsable tr INNER JOIN empleado er ON er.id_empleado=tr.id_empleado
			WHERE tr.id_tarea=t.id_tarea) AS tarea_responsables,
		NULLIF(TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))),'') AS usuario_nombre
		FROM actividad act
		INNER JOIN tarea t ON t.id_tarea=act.id_tarea
		LEFT JOIN area ar ON ar.id_area=t.id_area
		LEFT JOIN empleado e ON e.id_empleado=act.id_empleado
		WHERE act.id_tarea IN ($lista_ids)
		GROUP BY act.id_tarea, t.titulo, t.estado, t.prioridad, t.descripcion, t.fecha_inicio, t.fecha_cierre,
			ar.nombre, act.tipo, act.descripcion, act.fecha_actividad, act.id_empleado, usuario_nombre
		ORDER BY act.fecha_actividad DESC, MAX(act.id_actividad) DESC LIMIT 20";
	$result=@mysqli_query($con,$sql);
	if(!$result) { return array(); }
	$salida=array();
	while($row=mysqli_fetch_assoc($result))
	{
		$acciones=array('creada'=>'Tarea creada','proceso'=>'Estado cambiado a En proceso','completada'=>'Tarea finalizada');
		if(!isset($acciones[$row['tipo']])) { continue; }
		$row['accion']=$acciones[$row['tipo']];
		$row['usuario_nombre']=trim((string)$row['usuario_nombre']);
		$salida[]=$row;
	}
	return $salida;
}

/**
 * Retorna los nombres de display de quienes tocaron "Iniciar" en una tarea.
 * Se usa para mostrar en la pantalla de ver tarea.
 */
function repo_tarea_iniciaron($id_tarea)
{
	global $con;
	$id=intval($id_tarea);
	$salida=array();
	if($id<=0 || !$con)
	{
		return $salida;
	}
	$sql="SELECT CONCAT(e.nombres,' ',e.apellido) AS nombre_completo, a.nombre AS nombre_area
		FROM tarea_inicio ti
		INNER JOIN empleado e ON e.id_empleado=ti.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE ti.id_tarea=?
		ORDER BY ti.fecha_click_inicio";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	while($row=mysqli_fetch_assoc($result))
	{
		$nom=trim($row['nombre_completo']).' - '.trim($row['nombre_area']);
		if(!in_array($nom, $salida))
		{
			$salida[]=$nom;
		}
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}

/**
 * Verifica si un empleado ya toco "Iniciar" en una tarea.
 * Retorna true si ya la inicio, false si no.
 */
function repo_tarea_verificar_inicio($id_tarea, $id_empleado)
{
	global $con;
	$id_t=intval($id_tarea);
	$id_e=intval($id_empleado);
	if($id_t<=0 || $id_e<=0 || !$con)
	{
		return false;
	}
	$sql="SELECT COUNT(*) AS total FROM tarea_inicio WHERE id_tarea=? AND id_empleado=?";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'ii', $id_t, $id_e);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_bind_result($stmt, $total);
	@mysqli_stmt_fetch($stmt);
	@mysqli_stmt_close($stmt);
	return intval($total) > 0;
}


// =========================================================================
// H. Avisos
// =========================================================================

/**
 * Crea un aviso para notificar a un empleado que se le asigno una tarea.
 * Se llama cuando un coordinador crea o modifica una tarea con responsables nuevos.
 * Retorna true si se inserto correctamente.
 */
function repo_avisos_crear($id_tarea, $id_empleado_de, $id_empleado_para)
{
	global $con;
	$id_t=intval($id_tarea);
	$de=intval($id_empleado_de);
	$para=intval($id_empleado_para);
	if($id_t<=0 || $de<=0 || $para<=0 || $de==$para || !$con)
	{
		return false;
	}
	// No crear aviso si ya existe uno sin confirmar para esta tarea y empleado
	$sql_check="SELECT COUNT(*) AS total FROM aviso
		WHERE id_tarea=? AND id_empleado_para=? AND confirmado=0";
	$stmt=@mysqli_prepare($con, $sql_check);
	if(!$stmt)
	{
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'ii', $id_t, $para);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_bind_result($stmt, $total);
	@mysqli_stmt_fetch($stmt);
	@mysqli_stmt_close($stmt);
	if(intval($total)>0)
	{
		return true; // Ya tiene aviso pendiente, no duplicar
	}

	$sql="INSERT INTO aviso (id_tarea, id_empleado_de, id_empleado_para, fecha_aviso, confirmado)
		VALUES (?, ?, ?, NOW(), 0)";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'iii', $id_t, $de, $para);
	$ok=@mysqli_stmt_execute($stmt);
	@mysqli_stmt_close($stmt);
	return $ok;
}

/**
 * Retorna los avisos pendientes (sin confirmar) de un empleado.
 * JOIN con tarea para el titulo y con empleado para saber quien asigno.
 * Retorna array de arrays: id, titulo, de (nombre del que asigno), tarea_id, area (clave).
 */
function repo_avisos_mios($id_empleado)
{
	global $con;
	$id=intval($id_empleado);
	$salida=array();
	if($id<=0 || !$con)
	{
		return $salida;
	}
	$sql="SELECT av.id_aviso, t.titulo, t.id_tarea, a.clave AS area_clave,
		CONCAT(ed.nombres,' ',ed.apellido) AS nombre_de,
		ad.nombre AS area_nombre_de
		FROM aviso av
		INNER JOIN tarea t ON t.id_tarea=av.id_tarea
		INNER JOIN area a ON a.id_area=t.id_area
		INNER JOIN empleado ed ON ed.id_empleado=av.id_empleado_de
		INNER JOIN area ad ON ad.id_area=ed.id_area
		WHERE av.id_empleado_para=? AND av.confirmado=0
		ORDER BY av.fecha_aviso DESC";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	while($row=mysqli_fetch_assoc($result))
	{
		$nombre_de=trim($row['nombre_de']).' - '.trim($row['area_nombre_de']);
		$salida[]=array(
			'id'=>intval($row['id_aviso']),
			'id_aviso'=>intval($row['id_aviso']),
			'titulo'=>$row['titulo'],
			'de'=>$nombre_de,
			'tarea_id'=>intval($row['id_tarea']),
			'area'=>$row['area_clave']
		);
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}

/**
 * Marca un aviso como confirmado (leido). El empleado ya vio la notificacion.
 * Retorna true si se actualizo correctamente.
 */
function repo_aviso_confirmar($id_aviso)
{
	global $con;
	$id=intval($id_aviso);
	if($id<=0 || !$con)
	{
		return false;
	}
	$sql="UPDATE aviso SET confirmado=1 WHERE id_aviso=?";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	$ok=@mysqli_stmt_execute($stmt);
	@mysqli_stmt_close($stmt);
	return $ok;
}


// =========================================================================
// I. Calendario: dias con tareas en un mes
// =========================================================================

/**
 * Retorna un array con los numeros de dia que tienen al menos una tarea en el mes/anio dado.
 * Se usa para resaltar dias en el calendario de la UI.
 * Aplica las mismas reglas de visibilidad que el listado.
 */
function repo_calendario_dias_con_tareas($anio, $mes, $id_area, $rol_efectivo, $id_empleado)
{
	global $con;
	$salida=array();
	if(!$con)
	{
		return $salida;
	}
	$anio=intval($anio);
	$mes=intval($mes);
	if($anio<=0 || $mes<1 || $mes>12)
	{
		return $salida;
	}

	// Buscar dias unicos donde hay tareas que inician o se cierran en ese mes
	$sql="SELECT DISTINCT DAY(fecha) AS dia FROM (
		SELECT fecha_inicio AS fecha FROM tarea WHERE YEAR(fecha_inicio)=? AND MONTH(fecha_inicio)=?
		UNION
		SELECT fecha_cierre AS fecha FROM tarea WHERE YEAR(fecha_cierre)=? AND MONTH(fecha_cierre)=? AND fecha_cierre IS NOT NULL
	) AS fechas";
	$params=array($anio, $mes, $anio, $mes);
	$types='iiii';

	// Filtro por area
	if($id_area>0)
	{
		// Rehacer la consulta con filtro de area
		$sql="SELECT DISTINCT DAY(fecha) AS dia FROM (
			SELECT t.fecha_inicio AS fecha FROM tarea t WHERE t.fecha_inicio IS NOT NULL AND YEAR(t.fecha_inicio)=? AND MONTH(t.fecha_inicio)=? AND t.id_area=?
			UNION
			SELECT t.fecha_cierre AS fecha FROM tarea t WHERE t.fecha_cierre IS NOT NULL AND YEAR(t.fecha_cierre)=? AND MONTH(t.fecha_cierre)=? AND t.id_area=?
		) AS fechas";
		$params=array($anio, $mes, $id_area, $anio, $mes, $id_area);
		$types='iiiiii';
	}

	// Filtro de visibilidad para empleado
	if($rol_efectivo==='empleado' && $id_empleado>0)
	{
		// Agregar subconsulta de visibilidad en cada branch del UNION
		$sql="SELECT DISTINCT DAY(fecha) AS dia FROM (
			SELECT t.fecha_inicio AS fecha FROM tarea t
			WHERE t.fecha_inicio IS NOT NULL AND YEAR(t.fecha_inicio)=? AND MONTH(t.fecha_inicio)=?
			AND EXISTS(SELECT 1 FROM tarea_responsable tr WHERE tr.id_tarea=t.id_tarea AND tr.id_empleado=?)
			".($id_area>0 ? "AND t.id_area=?" : "")."
			UNION
			SELECT t.fecha_cierre AS fecha FROM tarea t
			WHERE t.fecha_cierre IS NOT NULL AND YEAR(t.fecha_cierre)=? AND MONTH(t.fecha_cierre)=?
			AND EXISTS(SELECT 1 FROM tarea_responsable tr WHERE tr.id_tarea=t.id_tarea AND tr.id_empleado=?)
			".($id_area>0 ? "AND t.id_area=?" : "")."
		) AS fechas";
		if($id_area>0)
		{
			$params=array($anio, $mes, $id_empleado, $id_area, $anio, $mes, $id_empleado, $id_area);
			$types='iiiiiiii';
		}
		else
		{
			$params=array($anio, $mes, $id_empleado, $anio, $mes, $id_empleado);
			$types='iiiiii';
		}
	}

	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, $types, ...$params);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_bind_result($stmt, $dia);
	while(@mysqli_stmt_fetch($stmt))
	{
		$salida[]=intval($dia);
	}
	@mysqli_stmt_close($stmt);
	return $salida;
}


// =========================================================================
// J. Validacion de tipos de tarea
// =========================================================================

/**
 * Verifica que un tipo de tarea pertenezca al area indicada.
 * Se usa antes de guardar para evitar que alguien manipule el POST
 * y asigne un tipo de otra area a la tarea.
 * Retorna true si el tipo existe y pertenece al area.
 */
function repo_tipo_pertenece_area($id_tipo, $id_area)
{
	global $con;
	$id_t=intval($id_tipo);
	$id_a=intval($id_area);
	if($id_t<=0 || $id_a<=0 || !$con)
	{
		return false;
	}
	$sql="SELECT COUNT(*) AS total FROM tipo_tarea WHERE id_tipo=? AND id_area=?";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return false;
	}
	@mysqli_stmt_bind_param($stmt, 'ii', $id_t, $id_a);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_bind_result($stmt, $total);
	@mysqli_stmt_fetch($stmt);
	@mysqli_stmt_close($stmt);
	return ($total>0);
}

/**
 * Carga un tipo de tarea nuevo para el area en la tabla tipo_tarea si no existe.
 * Realiza comprobacion previa para evitar duplicados en la misma area.
 * Retorna el nombre del tipo (existente o recién insertado) o '' si fallo.
 */
function repo_tipo_cargar($id_area, $nombre)
{
	global $con;
	$nombre=trim($nombre);
	$id=intval($id_area);
	if($nombre=='' || strlen($nombre)>80 || $id<=0 || !$con)
	{
		return '';
	}
	// 1. Verificar si ya existe en esa area (case insensitive)
	$stmt=@mysqli_prepare($con, "SELECT nombre FROM tipo_tarea WHERE id_area=? AND LOWER(nombre)=LOWER(?) LIMIT 1");
	if($stmt)
	{
		$lower_nom=strtolower($nombre);
		@mysqli_stmt_bind_param($stmt, 'is', $id, $lower_nom);
		@mysqli_stmt_execute($stmt);
		$result=@mysqli_stmt_get_result($stmt);
		$row=$result ? @mysqli_fetch_assoc($result) : null;
		@mysqli_stmt_close($stmt);
		if($row && isset($row['nombre']) && $row['nombre']!='')
		{
			return $row['nombre'];
		}
	}

	// 2. Insertar nuevo registro en MySQL
	$stmt_ins=@mysqli_prepare($con, "INSERT INTO tipo_tarea (id_area, nombre) VALUES (?, ?)");
	if(!$stmt_ins)
	{
		return '';
	}
	@mysqli_stmt_bind_param($stmt_ins, 'is', $id, $nombre);
	$ok=@mysqli_stmt_execute($stmt_ins);
	@mysqli_stmt_close($stmt_ins);
	return $ok ? $nombre : '';
}


// =========================================================================
// K. Helpers para areas, empleados y clientes
// =========================================================================

/**
 * Retorna el id_area dado el string clave del area (ej: 'comercio', 'marketing').
 * Retorna 0 si no lo encuentra.
 */
function repo_id_area_por_clave($clave)
{
	global $con;
	$clave=trim($clave);
	if($clave=='' || !$con)
	{
		return 0;
	}
	$sql="SELECT id_area FROM area WHERE clave=? LIMIT 1";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return 0;
	}
	@mysqli_stmt_bind_param($stmt, 's', $clave);
	@mysqli_stmt_execute($stmt);
	@mysqli_stmt_bind_result($stmt, $id);
	@mysqli_stmt_fetch($stmt);
	@mysqli_stmt_close($stmt);
	return intval($id);
}

/**
 * Retorna los empleados de un area con su nombre de display.
 * Si $solo_activos es true, solo devuelve empleados activos.
 * Se usa para poblar selects de responsables en formularios.
 */
function repo_empleados_por_area($id_area, $solo_activos=true)
{
	global $con;
	$id_a=intval($id_area);
	$salida=array();
	if($id_a<=0 || !$con)
	{
		return $salida;
	}
	$filtro=$solo_activos ? " AND e.activo=1" : "";
	$sql="SELECT e.id_empleado, e.nombres, e.apellido, a.nombre AS nombre_area, a.clave AS clave_area
		FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE e.id_area=? AND r.clave<>'superadmin' ".$filtro."
		ORDER BY e.apellido, e.nombres";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return $salida;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id_a);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	$ya=array();
	while($row=mysqli_fetch_assoc($result))
	{
		$id_e=intval($row['id_empleado']);
		$ya[$id_e]=1;
		$salida[]=array(
			'id'=>$id_e,
			'nombre'=>trim($row['nombres'].' '.$row['apellido']),
			'etiqueta'=>trim($row['nombres'].' '.$row['apellido']).' - '.acceso_etiqueta_corta_area($row['clave_area'])
		);
	}
	@mysqli_stmt_close($stmt);

	if(function_exists('acceso_tabla_existe') && acceso_tabla_existe('empleado_rol_adicional'))
	{
		$sql_ad="SELECT e.id_empleado, e.nombres, e.apellido, a.nombre AS nombre_area, a.clave AS clave_area
			FROM empleado_rol_adicional x
			INNER JOIN empleado e ON e.id_empleado=x.id_empleado
			INNER JOIN rol r ON r.id_rol=x.id_rol
			INNER JOIN area a ON a.id_area=x.id_area
			WHERE x.id_area=? AND x.fecha_desde<=CURDATE() AND x.fecha_hasta IS NULL AND r.clave<>'superadmin' ".$filtro."
			ORDER BY e.apellido, e.nombres";
		$stmt_ad=@mysqli_prepare($con, $sql_ad);
		if($stmt_ad)
		{
			@mysqli_stmt_bind_param($stmt_ad, 'i', $id_a);
			@mysqli_stmt_execute($stmt_ad);
			$res_ad=@mysqli_stmt_get_result($stmt_ad);
			if($res_ad)
			{
			while($row_ad=mysqli_fetch_assoc($res_ad))
				{
					$id_e=intval($row_ad['id_empleado']);
					if(!isset($ya[$id_e]))
					{
						$ya[$id_e]=1;
						$salida[]=array(
							'id'=>$id_e,
						'nombre'=>trim($row_ad['nombres'].' '.$row_ad['apellido']),
						'etiqueta'=>trim($row_ad['nombres'].' '.$row_ad['apellido']).' - '.acceso_etiqueta_corta_area($row_ad['clave_area'])
						);
					}
				}
			}
			@mysqli_stmt_close($stmt_ad);
		}
	}
	return $salida;
}

/** IDs de responsables actualmente vinculados a una tarea, incluidos vínculos históricos. */
function repo_tarea_responsable_ids($id_tarea)
{
	global $con;
	$id=intval($id_tarea);
	$ids=array();
	if($id<=0 || !$con) { return $ids; }
	$stmt=@mysqli_prepare($con, "SELECT id_empleado FROM tarea_responsable WHERE id_tarea=? ORDER BY es_principal DESC, id_empleado");
	if(!$stmt) { return $ids; }
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	$res=@mysqli_stmt_get_result($stmt);
	if($res) { while($row=mysqli_fetch_assoc($res)) { $ids[]=intval($row['id_empleado']); } }
	@mysqli_stmt_close($stmt);
	return array_values(array_unique($ids));
}

/** Nombre de un empleado para el área de la tarea, también para vínculos históricos. */
function repo_responsable_etiqueta_area($id_empleado, $id_area)
{
	global $con;
	$id_e=intval($id_empleado); $id_a=intval($id_area);
	if($id_e<=0 || $id_a<=0 || !$con) { return ''; }
	$stmt=@mysqli_prepare($con, "SELECT e.nombres, e.apellido, a.clave FROM empleado e INNER JOIN area a ON a.id_area=? WHERE e.id_empleado=? LIMIT 1");
	if(!$stmt) { return ''; }
	@mysqli_stmt_bind_param($stmt, 'ii', $id_a, $id_e);
	@mysqli_stmt_execute($stmt);
	$res=@mysqli_stmt_get_result($stmt); $row=$res ? @mysqli_fetch_assoc($res) : null;
	@mysqli_stmt_close($stmt);
	if(!$row) { return ''; }
	return trim($row['nombres'].' '.$row['apellido']).' - '.acceso_etiqueta_corta_area($row['clave']);
}

/** Validación de servidor: personal activo con rol principal/adicional vigente en el área. */
function repo_responsable_autorizado_area($id_empleado, $id_area)
{
	global $con;
	$id_e=intval($id_empleado); $id_a=intval($id_area);
	if($id_e<=0 || $id_a<=0 || !$con) { return false; }
	$stmt=@mysqli_prepare($con, "SELECT e.id_empleado FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol WHERE e.id_empleado=? AND e.id_area=? AND e.activo=1 AND r.clave<>'superadmin' LIMIT 1");
	if($stmt)
	{
		@mysqli_stmt_bind_param($stmt, 'ii', $id_e, $id_a); @mysqli_stmt_execute($stmt);
		$res=@mysqli_stmt_get_result($stmt); $ok=$res && mysqli_num_rows($res)>0;
		@mysqli_stmt_close($stmt);
		if($ok) { return true; }
	}
	if(function_exists('acceso_tabla_existe') && acceso_tabla_existe('empleado_rol_adicional'))
	{
		$stmt=@mysqli_prepare($con, "SELECT e.id_empleado FROM empleado_rol_adicional x INNER JOIN empleado e ON e.id_empleado=x.id_empleado INNER JOIN rol r ON r.id_rol=x.id_rol WHERE e.id_empleado=? AND x.id_area=? AND x.fecha_desde<=CURDATE() AND x.fecha_hasta IS NULL AND e.activo=1 AND r.clave<>'superadmin' LIMIT 1");
		if($stmt)
		{
			@mysqli_stmt_bind_param($stmt, 'ii', $id_e, $id_a); @mysqli_stmt_execute($stmt);
			$res=@mysqli_stmt_get_result($stmt); $ok=$res && mysqli_num_rows($res)>0;
			@mysqli_stmt_close($stmt);
			return $ok;
		}
	}
	return false;
}

/**
 * Retorna la informacion basica de un cliente por su ID.
 * Retorna array con los datos o null si no existe.
 */
function repo_cliente_por_id($id_cliente)
{
	global $con;
	$id=intval($id_cliente);
	if($id<=0 || !$con)
	{
		return null;
	}
	$sql="SELECT c.id_cliente, c.nombre, c.correo, c.telefono, c.tipo_cliente,
		c.direccion, c.cuil_cuit,
		l.nombre AS localidad_nombre
		FROM cliente c
		LEFT JOIN localidad l ON l.id_localidad=c.id_localidad
		WHERE c.id_cliente=? AND c.activo=1
		LIMIT 1";
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		return null;
	}
	@mysqli_stmt_bind_param($stmt, 'i', $id);
	@mysqli_stmt_execute($stmt);
	$result=@mysqli_stmt_get_result($stmt);
	$row=mysqli_fetch_assoc($result);
	@mysqli_stmt_close($stmt);
	if(!$row)
	{
		return null;
	}
	return array(
		'id'=>intval($row['id_cliente']),
		'nombre'=>$row['nombre'],
		'correo'=>$row['correo'],
		'telefono'=>$row['telefono'],
		'tipo_cliente'=>$row['tipo_cliente'],
		'direccion'=>$row['direccion'],
		'cuil_cuit'=>$row['cuil_cuit'],
		'localidad'=>$row['localidad_nombre']
	);
}

/**
 * Garantiza que consultar u obtener una tarea no modifique automaticamente su avance ni su estado.
 * Permanece en 0% y No iniciada hasta que el usuario registre explicitamente un avance.
 */
function tareas_avanzar_automatico_al_abrir($id_tarea=0)
{
	return false;
}

} // cierra if(!function_exists('repo_estado_a_display'))
?>
