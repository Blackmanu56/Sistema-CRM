<?php
// Gestión de Áreas y Coordinadores - Lógica de negocio reutilizable.

function crm_area_clave_limpia($nombre)
{
	$s=strtolower(trim($nombre));
	$s=iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
	$s=preg_replace('/[^a-z0-9]+/', '_', $s);
	$s=trim($s, '_');
	if($s==='') { $s='area'; }
	return substr($s, 0, 30);
}

function crm_area_coordinador_activo($id_area)
{
	$id_area=intval($id_area);
	if($id_area<=0) { return null; }
	// activo=1: sigue en la agencia. estado_laboral es situación (vacaciones/licencia/activo), no baja.
	return acceso_consulta_una("
		SELECT e.id_empleado, CONCAT(e.nombres, ' ', e.apellido) AS nombre_completo, e.correo,
		       e.estado_laboral, e.activo
		FROM empleado e
		JOIN rol r ON r.id_rol = e.id_rol
		WHERE e.id_area = ? AND r.clave = 'coordinador' AND e.activo = 1
		LIMIT 1
	", 'i', array($id_area));
}

function crm_area_empleados_disponibles()
{
	// Listar personal de la agencia (activo=1). Incluye vacaciones/licencia; se muestra estado_laboral.
	$sql = "
		SELECT e.id_empleado, CONCAT(e.nombres, ' ', e.apellido) AS nombre_completo,
		       e.id_area, a.nombre AS area_nombre, a.clave AS area_clave,
		       e.id_rol, r.clave AS rol_clave, r.nombre AS rol_nombre,
		       e.estado_laboral, e.activo,
		       (SELECT COUNT(*) FROM tarea t
		        JOIN tarea_responsable tr ON tr.id_tarea = t.id_tarea
		        WHERE tr.id_empleado = e.id_empleado
		          AND t.id_area = e.id_area
		          AND t.estado IN ('no_iniciada','en_proceso','pausada')
		       ) AS tareas_pendientes
		FROM empleado e
		JOIN area a ON a.id_area = e.id_area
		JOIN rol r ON r.id_rol = e.id_rol
		WHERE e.activo = 1
		  AND r.clave NOT IN ('superadmin', 'coordinador_general')
		ORDER BY a.nombre, e.apellido, e.nombres
	";
	return acceso_consulta($sql);
}

function crm_area_empleados_conteo($id_area)
{
	$id_area=intval($id_area);
	if($id_area<=0) { return 0; }
	$row = acceso_consulta_una("
		SELECT COUNT(*) AS total
		FROM empleado
		WHERE id_area = ? AND activo = 1
	", 'i', array($id_area));
	return $row ? intval($row['total']) : 0;
}

function crm_area_tareas_pendientes_empleado($id_empleado, $id_area)
{
	$id_empleado=intval($id_empleado);
	$id_area=intval($id_area);
	if($id_empleado<=0 || $id_area<=0) { return array('total'=>0, 'lista'=>array()); }
	$filas = acceso_consulta("
		SELECT t.id_tarea, t.titulo, t.prioridad, t.estado
		FROM tarea t
		JOIN tarea_responsable tr ON tr.id_tarea = t.id_tarea
		WHERE tr.id_empleado = ? AND t.id_area = ? AND t.estado IN ('no_iniciada','en_proceso','pausada')
		ORDER BY t.id_tarea DESC
	", 'ii', array($id_empleado, $id_area));
	return array('total'=>count($filas), 'lista'=>$filas);
}

function crm_transaccion_abierta()
{
	global $con;
	if(!$con) { return false; }
	try {
		$res = @mysqli_query($con, "SELECT @@in_transaction");
		if($res)
		{
			$row = mysqli_fetch_row($res);
			mysqli_free_result($res);
			return (isset($row[0]) && intval($row[0]) === 1);
		}
	} catch(\Throwable $e) {
		return false;
	}
	return false;
}

/**
 * Valida y asigna un coordinador a un área existente.
 * Retorna array con:
 *   'ok' => bool
 *   'error' => string (si falló o fue bloqueado)
 *   'requiere_confirmacion' => bool
 *   'datos_confirmacion' => array
 *   'msg' => string (si se completó con éxito)
 */
function crm_area_validar_y_asignar_coordinador($id_area, $id_coord, $conf_mov=false, $conf_reemp=false)
{
	global $con;
	$id_area=intval($id_area);
	$id_coord=intval($id_coord);

	$area = acceso_consulta_una("SELECT id_area, clave, nombre, activo FROM area WHERE id_area=?", 'i', array($id_area));
	if(!$area)
	{
		return array('ok'=>false, 'error'=>'El área seleccionada no existe.');
	}

	if($id_coord<=0)
	{
		return array('ok'=>false, 'error'=>'Seleccioná un empleado para asignar como coordinador.');
	}

	$emp = acceso_consulta_una("
		SELECT e.id_empleado, CONCAT(e.nombres, ' ', e.apellido) AS nombre_completo,
		       e.id_area, e.id_rol, a.nombre AS area_nombre, r.clave AS rol_clave,
		       e.estado_laboral, e.activo
		FROM empleado e
		JOIN area a ON a.id_area = e.id_area
		JOIN rol r ON r.id_rol = e.id_rol
		WHERE e.id_empleado = ? AND e.activo = 1
		LIMIT 1
	", 'i', array($id_coord));

	if(!$emp)
	{
		return array('ok'=>false, 'error'=>'El empleado seleccionado no existe o ya no trabaja en la agencia.');
	}

	if(in_array($emp['rol_clave'], array('superadmin', 'coordinador_general'), true))
	{
		return array('ok'=>false, 'error'=>'No se puede asignar a un superadmin o coordinador general como coordinador de área.');
	}

	// Bloqueo estricto si ya es coordinador de otra área
	if($emp['rol_clave']==='coordinador' && intval($emp['id_area'])!==$id_area)
	{
		return array('ok'=>false, 'error'=>"Es coordinador de {$emp['area_nombre']}; primero reasigná esa área.");
	}

	$coord_actual = crm_area_coordinador_activo($id_area);
	$reemplaza = ($coord_actual && intval($coord_actual['id_empleado'])!==$id_coord);
	$cambia_area = (intval($emp['id_area'])!==$id_area);

	// Bloqueo si cambia de área, el área de origen no tiene coordinador activo y el empleado tiene tareas pendientes
	if($cambia_area)
	{
		$coord_origen = crm_area_coordinador_activo($emp['id_area']);
		$pendInfoCheck = crm_area_tareas_pendientes_empleado($id_coord, $emp['id_area']);
		if(!$coord_origen && $pendInfoCheck['total'] > 0)
		{
			return array('ok'=>false, 'error'=>"El área {$emp['area_nombre']} no tiene coordinador; nadie podría reasignar sus tareas.");
		}
	}

	$necesita_reemp = ($reemplaza && !$conf_reemp);
	$necesita_mov = ($cambia_area && !$conf_mov);

	if($necesita_reemp || $necesita_mov)
	{
		$pendInfo = $cambia_area ? crm_area_tareas_pendientes_empleado($id_coord, $emp['id_area']) : array('total'=>0, 'lista'=>array());
		$es_unico = $cambia_area ? (crm_area_empleados_conteo($emp['id_area']) <= 1) : false;

		return array(
			'ok'=>false,
			'requiere_confirmacion'=>true,
			'datos_confirmacion'=>array(
				'tipo'=>'asignar_coord',
				'id_area'=>$id_area,
				'id_coordinador'=>$id_coord,
				'emp_nombre'=>$emp['nombre_completo'],
				'area_nombre'=>$area['nombre'],
				'area_origen'=>$emp['area_nombre'],
				'tareas_pendientes'=>$pendInfo['total'],
				'tareas_lista'=>$pendInfo['lista'],
				'es_unico'=>$es_unico,
				'reemplaza'=>$reemplaza,
				'coord_anterior'=>$coord_actual ? $coord_actual['nombre_completo'] : '',
				'cambia_area'=>$cambia_area
			)
		);
	}

	// Ejecución transaccional (solo hace begin/commit si la transacción no fue abierta externamente)
	$abrio_tx = false;
	if(!crm_transaccion_abierta())
	{
		mysqli_begin_transaction($con);
		$abrio_tx = true;
	}

	try {
		$id_rol_coord = acceso_id_por_clave('rol', 'coordinador');
		$id_rol_emp = acceso_id_por_clave('rol', 'empleado');

		if($reemplaza && $coord_actual)
		{
			$ok1 = acceso_ejecutar("UPDATE empleado SET id_rol = ? WHERE id_empleado = ?", 'ii', array($id_rol_emp, $coord_actual['id_empleado']));
			if(!$ok1)
			{
				throw new Exception('No se pudo actualizar el rol del coordinador anterior.');
			}
		}

		$ok2 = acceso_ejecutar("UPDATE empleado SET id_area = ?, id_rol = ? WHERE id_empleado = ?", 'iii', array($id_area, $id_rol_coord, $id_coord));
		if(!$ok2)
		{
			throw new Exception('No se pudo reasignar al nuevo coordinador.');
		}

		if($abrio_tx)
		{
			mysqli_commit($con);
		}
		$msg = "Coordinador asignado correctamente a {$area['nombre']}." . ($reemplaza ? " ({$coord_actual['nombre_completo']} quedó como empleado en esa área)." : '') . " El cambio se verá cuando el empleado vuelva a iniciar sesión.";
		return array('ok'=>true, 'msg'=>$msg);
	} catch(\Throwable $e) {
		if($abrio_tx)
		{
			mysqli_rollback($con);
		}
		return array('ok'=>false, 'error'=>'Error al asignar coordinador: '.$e->getMessage());
	}
}

/**
 * Quita el coordinador activo de un área: el área queda sin coordinador y el ex-coordinador
 * pasa a ser empleado común de esa área, sin borrarse.
 */
function crm_area_quitar_coordinador($id_area, $confirmar=false)
{
	global $con;
	$id_area=intval($id_area);
	$area = acceso_consulta_una("SELECT id_area, clave, nombre FROM area WHERE id_area=?", 'i', array($id_area));
	if(!$area)
	{
		return array('ok'=>false, 'error'=>'El área seleccionada no existe.');
	}

	$coord_actual = crm_area_coordinador_activo($id_area);
	if(!$coord_actual)
	{
		return array('ok'=>false, 'error'=>'El área ya no tiene un coordinador activo.');
	}

	if(!$confirmar)
	{
		return array(
			'ok'=>false,
			'requiere_confirmacion'=>true,
			'datos_confirmacion'=>array(
				'tipo'=>'quitar_coord',
				'id_area'=>$id_area,
				'area_nombre'=>$area['nombre'],
				'coord_nombre'=>$coord_actual['nombre_completo']
			)
		);
	}

	$abrio_tx = false;
	if(!crm_transaccion_abierta())
	{
		mysqli_begin_transaction($con);
		$abrio_tx = true;
	}

	try {
		$id_rol_emp = acceso_id_por_clave('rol', 'empleado');
		$ok = acceso_ejecutar("UPDATE empleado SET id_rol = ? WHERE id_empleado = ?", 'ii', array($id_rol_emp, $coord_actual['id_empleado']));
		if(!$ok)
		{
			throw new Exception('No se pudo actualizar el rol del coordinador.');
		}
		if($abrio_tx)
		{
			mysqli_commit($con);
		}
		$msg = "Se quitó al coordinador de {$area['nombre']}. {$coord_actual['nombre_completo']} quedó como empleado común en esa área. El cambio se verá cuando el empleado vuelva a iniciar sesión.";
		return array('ok'=>true, 'msg'=>$msg);
	} catch(\Throwable $e) {
		if($abrio_tx)
		{
			mysqli_rollback($con);
		}
		return array('ok'=>false, 'error'=>'Error al quitar coordinador: '.$e->getMessage());
	}
}

/**
 * Crea una nueva área y opcionalmente le asigna un coordinador.
 */
function crm_area_crear($nombre, $clave, $id_coord=0, $conf_mov=false, $atiende_clientes=1, $asignable_empleado=1)
{
	global $con;
	$nombre=trim((string)$nombre);
	$clave=trim((string)$clave);
	if($clave==='') { $clave=crm_area_clave_limpia($nombre); }
	$clave=preg_replace('/[^a-z0-9_]/', '', strtolower($clave));
	$id_coord=intval($id_coord);
	$atiende_clientes=intval($atiende_clientes) ? 1 : 0;
	$asignable_empleado=intval($asignable_empleado) ? 1 : 0;
	if(function_exists('crm_asegurar_area_flags'))
	{
		crm_asegurar_area_flags();
	}

	if($nombre==='' || $clave==='')
	{
		return array('ok'=>false, 'error'=>'Indicá nombre y clave del área.');
	}
	if(acceso_id_por_clave('area', $clave)>0)
	{
		return array('ok'=>false, 'error'=>'Ya existe un área con esa clave.');
	}

	$emp = null;
	if($id_coord>0)
	{
		$emp = acceso_consulta_una("
			SELECT e.id_empleado, CONCAT(e.nombres, ' ', e.apellido) AS nombre_completo,
			       e.id_area, e.id_rol, a.nombre AS area_nombre, r.clave AS rol_clave,
			       e.estado_laboral, e.activo
			FROM empleado e
			JOIN area a ON a.id_area = e.id_area
			JOIN rol r ON r.id_rol = e.id_rol
			WHERE e.id_empleado = ? AND e.activo = 1
			LIMIT 1
		", 'i', array($id_coord));

		if(!$emp)
		{
			return array('ok'=>false, 'error'=>'El empleado seleccionado no existe o ya no trabaja en la agencia.');
		}
		if(in_array($emp['rol_clave'], array('superadmin', 'coordinador_general'), true))
		{
			return array('ok'=>false, 'error'=>'No se puede asignar a un superadmin o coordinador general como coordinador de área.');
		}
		if($emp['rol_clave']==='coordinador')
		{
			return array('ok'=>false, 'error'=>"Es coordinador de {$emp['area_nombre']}; primero reasigná esa área.");
		}

		// Bloqueo si el área origen no tiene coordinador y tiene tareas pendientes
		$coord_origen = crm_area_coordinador_activo($emp['id_area']);
		$pendInfoCheck = crm_area_tareas_pendientes_empleado($id_coord, $emp['id_area']);
		if(!$coord_origen && $pendInfoCheck['total'] > 0)
		{
			return array('ok'=>false, 'error'=>"El área {$emp['area_nombre']} no tiene coordinador; nadie podría reasignar sus tareas.");
		}

		if(!$conf_mov)
		{
			$pendInfo = crm_area_tareas_pendientes_empleado($id_coord, $emp['id_area']);
			$es_unico = (crm_area_empleados_conteo($emp['id_area']) <= 1);
			return array(
				'ok'=>false,
				'requiere_confirmacion'=>true,
				'datos_confirmacion'=>array(
					'tipo'=>'alta_area',
					'nombre'=>$nombre,
					'clave'=>$clave,
					'id_coordinador'=>$id_coord,
					'atiende_clientes'=>$atiende_clientes,
					'asignable_empleado'=>$asignable_empleado,
					'emp_nombre'=>$emp['nombre_completo'],
					'area_origen'=>$emp['area_nombre'],
					'nueva_area'=>$nombre,
					'tareas_pendientes'=>$pendInfo['total'],
					'tareas_lista'=>$pendInfo['lista'],
					'es_unico'=>$es_unico
				)
			);
		}
	}

	$abrio_tx = false;
	if(!crm_transaccion_abierta())
	{
		mysqli_begin_transaction($con);
		$abrio_tx = true;
	}

	try {
		$id_nueva = acceso_insertar(
			"INSERT INTO area (clave, nombre, activo, atiende_clientes, asignable_empleado) VALUES (?,?,1,?,?)",
			'ssii',
			array($clave, $nombre, $atiende_clientes, $asignable_empleado)
		);
		if($id_nueva<=0)
		{
			// Fallback si aún no existen las columnas (migración pendiente).
			$id_nueva = acceso_insertar("INSERT INTO area (clave, nombre, activo) VALUES (?,?,1)", 'ss', array($clave, $nombre));
		}
		if($id_nueva<=0)
		{
			throw new Exception('No se pudo crear el área en la base de datos.');
		}
		if($id_coord>0)
		{
			$id_rol_coord = acceso_id_por_clave('rol', 'coordinador');
			$ok_emp = acceso_ejecutar("UPDATE empleado SET id_area = ?, id_rol = ? WHERE id_empleado = ?", 'iii', array($id_nueva, $id_rol_coord, $id_coord));
			if(!$ok_emp)
			{
				throw new Exception('Área creada, pero no se pudo asignar el coordinador.');
			}
		}
		if($abrio_tx)
		{
			mysqli_commit($con);
		}
		crm_area_asegurar_modulo($clave, $nombre, $id_nueva);
		$msg = 'Área creada correctamente' . ($id_coord > 0 ? ' con su coordinador asignado. El cambio se verá cuando el empleado vuelva a iniciar sesión.' : '.');
		return array('ok'=>true, 'msg'=>$msg, 'id_area'=>$id_nueva);
	} catch(\Throwable $e) {
		if($abrio_tx)
		{
			mysqli_rollback($con);
		}
		return array('ok'=>false, 'error'=>$e->getMessage());
	}
}

/**
 * Asegura la carpeta y archivos del módulo en public/{clave} y el tipo_tarea por defecto ('General').
 */
function crm_area_asegurar_modulo($clave, $nombre, $id_area)
{
	global $con;
	$clave = trim((string)$clave);
	$id_area = intval($id_area);
	if($clave==='' || $id_area<=0) { return; }

	// 1. Asegurar tipo de tarea 'General' en la BD si no tiene ninguno
	$hay_tipo = acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? LIMIT 1", 'i', array($id_area));
	if(!$hay_tipo)
	{
		acceso_insertar("INSERT INTO tipo_tarea (id_area, nombre) VALUES (?, 'General')", 'i', array($id_area));
	}

	// 2. Asegurar carpeta en public/{clave}
	$base_dir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
	$dir_destino = $base_dir . '/public/' . $clave;
	if(!is_dir($dir_destino))
	{
		@mkdir($dir_destino, 0777, true);
	}

	// 3. Generar los archivos base a partir del área modelo (comercio)
	$dir_origen = $base_dir . '/public/comercio';
	if(is_dir($dir_origen) && is_dir($dir_destino))
	{
		$archivos = array(
			'actividades.php',
			'atencion.php',
			'atenciones.php',
			'clientes.php',
			'dashboard.php',
			'datos-tareas.php',
			'editar-cliente.php',
			'editar-tarea.php',
			'menu-seccion.php',
			'nueva-actividad.php',
			'nueva-tarea.php',
			'tareas.php',
			'ver-tarea.php'
		);
		foreach($archivos as $arch)
		{
			$ruta_dest = $dir_destino . '/' . $arch;
			if(!file_exists($ruta_dest) && file_exists($dir_origen . '/' . $arch))
			{
				$contenido = file_get_contents($dir_origen . '/' . $arch);
				$contenido = str_replace(
					array('comercio', 'Comercio Exterior', 'Comercio exterior'),
					array($clave, $nombre, $nombre),
					$contenido
				);
				@file_put_contents($ruta_dest, $contenido);
			}
		}
	}
}

/**
 * Edita el nombre y la clave de un área existente.
 * Actualiza la BD y renombra/asegura la carpeta del módulo si la clave cambió.
 */
function crm_area_editar($id_area, $nombre, $clave)
{
	global $con;
	$id_area = intval($id_area);
	$nombre = trim((string)$nombre);
	$clave = trim((string)$clave);

	if($id_area <= 0)
	{
		return array('ok'=>false, 'error'=>'Área no válida.');
	}
	if($nombre === '')
	{
		return array('ok'=>false, 'error'=>'El nombre del área no puede estar vacío.');
	}
	if($clave === '')
	{
		$clave = crm_area_clave_limpia($nombre);
	}
	$clave = preg_replace('/[^a-z0-9_]/', '', strtolower($clave));
	if($clave === '')
	{
		return array('ok'=>false, 'error'=>'La clave del área no es válida (solo minúsculas, números y guión bajo).');
	}

	$actual = acceso_consulta_una("SELECT id_area, clave, nombre FROM area WHERE id_area=?", 'i', array($id_area));
	if(!$actual)
	{
		return array('ok'=>false, 'error'=>'El área seleccionada no existe.');
	}

	$clave_ant = strtolower(trim($actual['clave']));
	$nombre_ant = trim($actual['nombre']);

	// Si cambió la clave, verificar que no esté duplicada con otra área
	if($clave !== $clave_ant)
	{
		$dup = acceso_consulta_una("SELECT id_area FROM area WHERE clave=? AND id_area!=? LIMIT 1", 'si', array($clave, $id_area));
		if($dup)
		{
			return array('ok'=>false, 'error'=>'Ya existe otra área con la clave "'.$clave.'".');
		}
	}

	// 1. Actualizar en la base de datos
	$ok = acceso_ejecutar("UPDATE area SET nombre=?, clave=? WHERE id_area=?", 'ssi', array($nombre, $clave, $id_area));
	if(!$ok)
	{
		return array('ok'=>false, 'error'=>'No se pudo actualizar el área en la base de datos.');
	}

	// 2. Si la clave cambió, gestionar carpetas y archivos en public/
	if($clave !== $clave_ant)
	{
		$base_dir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
		$dir_ant = $base_dir . '/public/' . $clave_ant;
		$dir_nue = $base_dir . '/public/' . $clave;

		if(is_dir($dir_ant) && !is_dir($dir_nue))
		{
			@rename($dir_ant, $dir_nue);
			$archivos = @scandir($dir_nue);
			if(is_array($archivos))
			{
				foreach($archivos as $arch)
				{
					if($arch==='.' || $arch==='..' || substr($arch, -4)!=='.php') continue;
					$ruta = $dir_nue . '/' . $arch;
					if(is_file($ruta))
					{
						$cont = file_get_contents($ruta);
						$nuevo_cont = str_replace(
							array("'$clave_ant'", "\"$clave_ant\"", "'$nombre_ant'", "\"$nombre_ant\""),
							array("'$clave'", "\"$clave\"", "'$nombre'", "\"$nombre\""),
							$cont
						);
						if($nuevo_cont !== $cont)
						{
							@file_put_contents($ruta, $nuevo_cont);
						}
					}
				}
			}
		}

		crm_area_asegurar_modulo($clave, $nombre, $id_area);

		if(isset($_SESSION['area']) && $_SESSION['area'] === $clave_ant)
		{
			$_SESSION['area'] = $clave;
		}
	}
	else
	{
		crm_area_asegurar_modulo($clave, $nombre, $id_area);
	}

	if(isset($_SESSION['area_nombre']) && isset($_SESSION['area']) && $_SESSION['area'] === $clave)
	{
		$_SESSION['area_nombre'] = $nombre;
	}

	return array('ok'=>true, 'msg'=>'Área actualizada correctamente.');
}


