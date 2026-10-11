<?php
// Functions for domain and expiration management module in Sistemas.
require_once __DIR__ . '/acceso.php';
if (file_exists(__DIR__ . '/init.php'))
{
	require_once __DIR__ . '/init.php';
}
require_once __DIR__ . '/crm_tareas_flujo.php';

/**
 * Calculates domain expiration status based on expiration date.
 */
function crm_dominio_calcular_estado($fecha_vencimiento, $fecha_inicio = null)
{
	$fecha_venc = trim((string)$fecha_vencimiento);
	if ($fecha_venc === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_venc))
	{
		return array(
			'clave' => 'indefinido',
			'etiqueta' => 'Sin fecha',
			'badge' => 'label-inverse',
			'badge_css' => 'danger',
			'dias' => 0,
			'progreso' => 0
		);
	}

	$hoy = new DateTime(date('Y-m-d'));
	$target = new DateTime($fecha_venc);
	$diff = $hoy->diff($target);
	$dias = (int)$diff->format('%r%a');

	// Calculate percentage progress for progress bar
	if ($fecha_inicio && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio))
	{
		$start = new DateTime($fecha_inicio);
		$total_days = (int)$start->diff($target)->format('%r%a');
		$elapsed_days = (int)$start->diff($hoy)->format('%r%a');
		if ($total_days > 0)
		{
			$progreso = max(0, min(100, intval(($elapsed_days / (float)$total_days) * 100)));
		}
		else
		{
			$progreso = 100;
		}
	}
	else
	{
		// Fallback percentage based on 365 days window
		if ($dias < 0)
		{
			$progreso = 100;
		}
		else if ($dias > 365)
		{
			$progreso = 10;
		}
		else
		{
			$progreso = max(0, min(100, intval(((365 - $dias) / 365.0) * 100)));
		}
	}

	if ($dias < 0)
	{
		return array(
			'clave' => 'vencido',
			'etiqueta' => 'Vencido',
			'badge' => 'label-important',
			'badge_css' => 'danger',
			'dias' => $dias,
			'progreso' => 100
		);
	}
	else if ($dias <= 30)
	{
		return array(
			'clave' => 'proximo',
			'etiqueta' => 'Por vencer',
			'badge' => 'label-warning',
			'badge_css' => 'warning',
			'dias' => $dias,
			'progreso' => max($progreso, 75)
		);
	}
	else
	{
		return array(
			'clave' => 'vigente',
			'etiqueta' => 'Vencimiento lejano',
			'badge' => 'label-success',
			'badge_css' => 'success',
			'dias' => $dias,
			'progreso' => $progreso
		);
	}
}

/**
 * Processes domain expiration notifications for Sistemas users and target employees.
 * Deactivates alerts for renewed domains (vigente) and soft-deleted domains.
 */
function crm_dominios_procesar_notificaciones()
{
	global $con;
	if (!$con || !acceso_tabla_existe('dominio') || !acceso_tabla_existe('crm_aviso'))
	{
		return;
	}

	// 1. Deactivate unconfirmed notifications for domains that are now VIGENTE (> 30 days) or INACTIVE (activo = 0)
	$resueltos = acceso_consulta(
		"SELECT nombre FROM dominio WHERE activo = 0 OR DATEDIFF(fecha_vencimiento, CURDATE()) > 30"
	);
	foreach ($resueltos as $dom_ok)
	{
		$nom_dom = $dom_ok['nombre'];
		$pat_url = '%dominios.php?q=' . $nom_dom . '%';
		$pat_det = '%' . $nom_dom . '%';
		$pat_tit = '%' . $nom_dom . '%';
		$params_upd = array($pat_url, $pat_det, $pat_tit);
		acceso_ejecutar(
			"UPDATE crm_aviso SET confirmado = 1 WHERE (url LIKE ? OR detalle LIKE ? OR titulo LIKE ?) AND confirmado = 0",
			'sss',
			$params_upd
		);
	}

	// 2. Fetch active domains needing alerts (PROXIMO <= 30 days or VENCIDO < 0 days)
	$alertas_dominios = acceso_consulta(
		"SELECT id_dominio, nombre, url, proveedor, responsable, fecha_vencimiento, 
		        DATEDIFF(fecha_vencimiento, CURDATE()) AS dias_restantes 
		 FROM dominio 
		 WHERE activo = 1 AND DATEDIFF(fecha_vencimiento, CURDATE()) <= 30 
		 ORDER BY fecha_vencimiento ASC"
	);

	if (empty($alertas_dominios))
	{
		return;
	}

	// 3. Find target employees who should receive domain alerts:
	$target_ids = array();
	
	$supers = acceso_consulta("SELECT e.id_empleado FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol WHERE e.activo=1 AND r.clave IN ('superadmin', 'coordinador_general')");
	foreach ($supers as $s)
	{
		$target_ids[intval($s['id_empleado'])] = true;
	}

	$id_area_sistemas = acceso_id_por_clave('area', 'sistemas');
	if ($id_area_sistemas > 0)
	{
		$params_sis = array($id_area_sistemas);
		$sis_staff = acceso_consulta("SELECT id_empleado FROM empleado WHERE id_area=? AND activo=1", 'i', $params_sis);
		foreach ($sis_staff as $stf)
		{
			$target_ids[intval($stf['id_empleado'])] = true;
		}

		if (acceso_tabla_existe('empleado_rol_adicional'))
		{
			$params_ext = array($id_area_sistemas);
			$extras_sis = acceso_consulta("SELECT x.id_empleado FROM empleado_rol_adicional x WHERE x.id_area=? AND x.fecha_hasta IS NULL", 'i', $params_ext);
			foreach ($extras_sis as $ex)
			{
				$target_ids[intval($ex['id_empleado'])] = true;
			}
		}
	}

	// 4. For each domain needing alert and each target employee:
	foreach ($alertas_dominios as $dom)
	{
		$dias = intval($dom['dias_restantes']);
		$f_venc_fmt = date('d/m/Y', strtotime($dom['fecha_vencimiento']));
		$nom_dom = $dom['nombre'];

		if ($dias < 0)
		{
			$titulo = 'Dominio vencido · ' . $nom_dom;
			$detalle = 'El dominio "' . $nom_dom . '" venció el ' . $f_venc_fmt . ' (hace ' . abs($dias) . ' días).';
			$tipo_notif = 'vencimiento';
		}
		else
		{
			$titulo = 'Dominio próximo a vencer · ' . $nom_dom;
			$detalle = 'El dominio "' . $nom_dom . '" vence el ' . $f_venc_fmt . ' (faltan ' . $dias . ' días).';
			$tipo_notif = 'dominio';
		}

		$url_destino = function_exists('url_crm') ? url_crm('sistemas/dominios.php?q=' . urlencode($nom_dom)) : 'sistemas/dominios.php?q=' . urlencode($nom_dom);

		foreach (array_keys($target_ids) as $emp_id)
		{
			if ($emp_id <= 0) continue;

			$pat_url = '%dominios.php?q=' . $nom_dom . '%';
			$pat_det = '%' . $nom_dom . '%';
			$pat_tit = '%' . $nom_dom . '%';
			$params_chk = array($emp_id, $pat_url, $pat_det, $pat_tit);
			$ya = acceso_consulta_una(
				"SELECT id FROM crm_aviso WHERE id_empleado_para = ? AND (url LIKE ? OR detalle LIKE ? OR titulo LIKE ?) AND confirmado = 0 LIMIT 1",
				'isss',
				$params_chk
			);

			if (!$ya)
			{
				crm_aviso_enviar($emp_id, $titulo, $detalle, $url_destino, 0, $tipo_notif);
			}
		}
	}
}

/**
 * Returns distinct values for dropdown filters (years, providers, responsables).
 */
function crm_dominios_opciones_filtros()
{
	$opciones = array(
		'anios' => array(),
		'proveedores' => array(),
		'responsables' => array()
	);

	if (!acceso_tabla_existe('dominio'))
	{
		return $opciones;
	}

	$rows_a = acceso_consulta("SELECT DISTINCT YEAR(fecha_vencimiento) AS anio FROM dominio WHERE activo = 1 AND fecha_vencimiento IS NOT NULL ORDER BY anio DESC");
	foreach ($rows_a as $r)
	{
		if (!empty($r['anio'])) $opciones['anios'][] = (int)$r['anio'];
	}

	$rows_p = acceso_consulta("SELECT DISTINCT proveedor FROM dominio WHERE activo = 1 AND proveedor IS NOT NULL AND proveedor != '' ORDER BY proveedor ASC");
	foreach ($rows_p as $r)
	{
		$opciones['proveedores'][] = $r['proveedor'];
	}

	$rows_r = acceso_consulta("SELECT DISTINCT responsable FROM dominio WHERE activo = 1 AND responsable IS NOT NULL AND responsable != '' ORDER BY responsable ASC");
	foreach ($rows_r as $r)
	{
		$opciones['responsables'][] = $r['responsable'];
	}

	return $opciones;
}

/**
 * Returns list of active domains filtered by search query and calculated status.
 */
function crm_dominios_listar($filtros = array())
{
	global $con;
	$salida = array();
	if (!$con || !acceso_tabla_existe('dominio'))
	{
		return $salida;
	}

	crm_dominios_procesar_notificaciones();

	$where = array("activo = 1");
	$params = array();
	$types = '';

	$q = isset($filtros['q']) ? trim($filtros['q']) : '';
	if ($q !== '')
	{
		$where[] = "(nombre LIKE ? OR url LIKE ? OR proveedor LIKE ? OR responsable LIKE ?)";
		$like = '%' . $q . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$types .= 'ssss';
	}

	$anio = isset($filtros['anio']) ? trim($filtros['anio']) : '';
	if ($anio !== '' && $anio !== 'todos')
	{
		$where[] = "YEAR(fecha_vencimiento) = ?";
		$params[] = (int)$anio;
		$types .= 'i';
	}

	$proveedor = isset($filtros['proveedor']) ? trim($filtros['proveedor']) : '';
	if ($proveedor !== '' && $proveedor !== 'todos')
	{
		$where[] = "proveedor = ?";
		$params[] = $proveedor;
		$types .= 's';
	}

	$responsable = isset($filtros['responsable']) ? trim($filtros['responsable']) : '';
	if ($responsable !== '' && $responsable !== 'todos')
	{
		$where[] = "responsable = ?";
		$params[] = $responsable;
		$types .= 's';
	}

	$fecha = isset($filtros['fecha']) ? trim($filtros['fecha']) : '';
	if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
	{
		$where[] = "fecha_vencimiento = ?";
		$params[] = $fecha;
		$types .= 's';
	}

	$sql = "SELECT id_dominio, nombre, url, proveedor, responsable, fecha_inicio, fecha_vencimiento, notas, activo, creado_en, actualizado_en 
	        FROM dominio 
	        WHERE " . implode(' AND ', $where) . " 
	        ORDER BY fecha_vencimiento ASC, nombre ASC";

	$rows = acceso_consulta($sql, $types, $params);
	$estado_filtro = isset($filtros['estado']) ? trim($filtros['estado']) : '';

	foreach ($rows as $row)
	{
		$st = crm_dominio_calcular_estado($row['fecha_vencimiento'], $row['fecha_inicio']);
		$row['estado_info'] = $st;
		$row['estado_clave'] = $st['clave'];
		$row['dias_restantes'] = $st['dias'];

		if ($estado_filtro !== '' && $estado_filtro !== 'todos')
		{
			if ($estado_filtro === 'vigente' && $st['clave'] !== 'vigente')
			{
				continue;
			}
			else if ($estado_filtro !== 'vigente' && $st['clave'] !== $estado_filtro)
			{
				continue;
			}
		}

		$salida[] = $row;
	}

	return $salida;
}

/**
 * Returns statistics counts for domain statuses.
 */
function crm_dominios_estadisticas()
{
	$todos = crm_dominios_listar(array());
	$stats = array(
		'total' => count($todos),
		'vigente' => 0,
		'proximo' => 0,
		'vencen_mes' => 0,
		'vencido' => 0
	);

	$mes_actual = date('Y-m');

	foreach ($todos as $d)
	{
		$st = isset($d['estado_clave']) ? $d['estado_clave'] : '';
		if ($st === 'vigente')
		{
			$stats['vigente']++;
		}
		else if ($st === 'proximo')
		{
			$stats['proximo']++;
		}
		else if ($st === 'vencido')
		{
			$stats['vencido']++;
		}

		if (isset($d['fecha_vencimiento']) && substr($d['fecha_vencimiento'], 0, 7) === $mes_actual)
		{
			$stats['vencen_mes']++;
		}
	}

	return $stats;
}

/**
 * Returns upcoming domain expirations for sidebar widget.
 */
function crm_dominios_proximos_vencimientos($limite = 5)
{
	$limite = intval($limite);
	if ($limite <= 0) $limite = 5;

	$rows = acceso_consulta(
		"SELECT id_dominio, nombre, url, proveedor, responsable, fecha_inicio, fecha_vencimiento 
		 FROM dominio 
		 WHERE activo = 1 
		 ORDER BY fecha_vencimiento ASC 
		 LIMIT " . $limite
	);

	$proximos = array();
	foreach ($rows as $r)
	{
		$st = crm_dominio_calcular_estado($r['fecha_vencimiento'], $r['fecha_inicio']);
		$r['estado_info'] = $st;
		$r['dias_restantes'] = $st['dias'];
		$proximos[] = $r;
	}

	return $proximos;
}

/**
 * Fetches domain events for a given month/year indexed by day of month (YYYY-MM-DD), with optional filters.
 */
function crm_dominios_calendario_eventos($anio, $mes, $filtros = array())
{
	$anio = intval($anio);
	$mes = intval($mes);
	if ($anio <= 0) $anio = intval(date('Y'));
	if ($mes <= 0 || $mes > 12) $mes = intval(date('m'));

	$inicio = sprintf('%04d-%02d-01', $anio, $mes);
	$fin = date('Y-m-t', strtotime($inicio));

	$where = array("activo = 1", "fecha_vencimiento >= ?", "fecha_vencimiento <= ?");
	$params = array($inicio, $fin);
	$types = 'ss';

	$q = isset($filtros['q']) ? trim($filtros['q']) : '';
	if ($q !== '')
	{
		$where[] = "(nombre LIKE ? OR url LIKE ? OR proveedor LIKE ? OR responsable LIKE ?)";
		$like = '%' . $q . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$types .= 'ssss';
	}

	$sql = "SELECT id_dominio, nombre, url, proveedor, responsable, fecha_inicio, fecha_vencimiento, notas 
		 FROM dominio 
		 WHERE " . implode(' AND ', $where) . " 
		 ORDER BY fecha_vencimiento ASC, nombre ASC";

	$rows = acceso_consulta($sql, $types, $params);
	$estado_filtro = isset($filtros['estado']) ? trim($filtros['estado']) : '';

	$eventos = array();
	foreach ($rows as $row)
	{
		$st = crm_dominio_calcular_estado($row['fecha_vencimiento'], $row['fecha_inicio']);
		$row['estado_info'] = $st;
		$row['estado_clave'] = $st['clave'];
		$row['dias_restantes'] = $st['dias'];

		if ($estado_filtro !== '' && $estado_filtro !== 'todos')
		{
			if ($estado_filtro === 'vigente' && $st['clave'] !== 'vigente')
			{
				continue;
			}
			else if ($estado_filtro !== 'vigente' && $st['clave'] !== $estado_filtro)
			{
				continue;
			}
		}

		$dia_num = intval(date('j', strtotime($row['fecha_vencimiento'])));
		if (!isset($eventos[$dia_num]))
		{
			$eventos[$dia_num] = array();
		}
		$eventos[$dia_num][] = $row;
	}

	return $eventos;
}

/**
 * Fetches a single domain by ID with full details and renovation history.
 */
function crm_dominio_traer($id_dominio)
{
	$id = intval($id_dominio);
	if ($id <= 0)
	{
		return null;
	}

	$params = array($id);
	$row = acceso_consulta_una(
		"SELECT id_dominio, nombre, url, proveedor, responsable, fecha_inicio, fecha_vencimiento, notas, activo, creado_en, actualizado_en 
		 FROM dominio 
		 WHERE id_dominio = ? AND activo = 1 LIMIT 1",
		'i',
		$params
	);

	if ($row)
	{
		$st = crm_dominio_calcular_estado($row['fecha_vencimiento'], $row['fecha_inicio']);
		$row['estado_info'] = $st;
		$row['estado_clave'] = $st['clave'];
		$row['dias_restantes'] = $st['dias'];
		$row['renovaciones'] = crm_dominio_historial_renovaciones($id);
	}

	return $row;
}

/**
 * Fetches domain renovation history list.
 */
function crm_dominio_historial_renovaciones($id_dominio)
{
	$id = intval($id_dominio);
	if ($id <= 0 || !acceso_tabla_existe('dominio_renovacion'))
	{
		return array();
	}

	$sql = "SELECT r.id_renovacion, r.id_dominio, r.fecha_renovacion, r.fecha_vencimiento_anterior, 
	               r.fecha_vencimiento_nueva, r.id_usuario, r.notas, r.creado_en,
	               CONCAT(COALESCE(e.nombres, ''), ' ', COALESCE(e.apellido, '')) AS usuario_nombre 
	        FROM dominio_renovacion r
	        LEFT JOIN empleado e ON e.id_empleado = r.id_usuario
	        WHERE r.id_dominio = ?
	        ORDER BY r.fecha_renovacion DESC, r.id_renovacion DESC";

	$params = array($id);
	return acceso_consulta($sql, 'i', $params);
}

/**
 * Registers or updates a domain with server-side validation and duplicate check.
 */
function crm_dominio_guardar($datos, $id_dominio = 0)
{
	if (!acceso_puede_gestionar('sistemas'))
	{
		return 'No tenés permisos para gestionar dominios.';
	}

	$id = intval($id_dominio);
	$nombre = isset($datos['nombre']) ? strtolower(trim($datos['nombre'])) : '';
	$url = isset($datos['url']) ? trim($datos['url']) : '';
	$proveedor = isset($datos['proveedor']) ? trim($datos['proveedor']) : '';
	$responsable = isset($datos['responsable']) ? trim($datos['responsable']) : '';
	$fecha_inicio = isset($datos['fecha_inicio']) && trim($datos['fecha_inicio']) !== '' ? trim($datos['fecha_inicio']) : null;
	$fecha_vencimiento = isset($datos['fecha_vencimiento']) ? trim($datos['fecha_vencimiento']) : '';
	$notas = isset($datos['notas']) ? trim($datos['notas']) : '';

	if ($nombre === '')
	{
		return 'El nombre del dominio es obligatorio.';
	}

	if ($url !== '' && !preg_match('/^https?:\/\//i', $url))
	{
		$url = 'https://' . $url;
	}

	if ($fecha_vencimiento === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_vencimiento))
	{
		return 'La fecha de vencimiento es obligatoria y debe ser válida (AAAA-MM-DD).';
	}

	if ($fecha_inicio && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio))
	{
		$fecha_inicio = null;
	}

	if ($id > 0)
	{
		$params_dup = array($nombre, $id);
		$dup = acceso_consulta_una(
			"SELECT id_dominio FROM dominio WHERE nombre = ? AND activo = 1 AND id_dominio != ? LIMIT 1",
			'si',
			$params_dup
		);
	}
	else
	{
		$params_dup = array($nombre);
		$dup = acceso_consulta_una(
			"SELECT id_dominio FROM dominio WHERE nombre = ? AND activo = 1 LIMIT 1",
			's',
			$params_dup
		);
	}

	if ($dup)
	{
		return 'Ya existe un dominio activo registrado con el nombre "' . $nombre . '".';
	}

	if ($id > 0)
	{
		$params_upd = array($nombre, $url, $proveedor, $responsable, $fecha_inicio, $fecha_vencimiento, $notas, $id);
		$ok = acceso_ejecutar(
			"UPDATE dominio 
			 SET nombre = ?, url = ?, proveedor = ?, responsable = ?, fecha_inicio = ?, fecha_vencimiento = ?, notas = ? 
			 WHERE id_dominio = ? AND activo = 1",
			'sssssssi',
			$params_upd
		);
		$msg_res = $ok ? '' : 'No se pudo actualizar el dominio.';
	}
	else
	{
		$params_ins = array($nombre, $url, $proveedor, $responsable, $fecha_inicio, $fecha_vencimiento, $notas);
		$new_id = acceso_insertar(
			"INSERT INTO dominio (nombre, url, proveedor, responsable, fecha_inicio, fecha_vencimiento, notas, activo) 
			 VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
			'sssssss',
			$params_ins
		);
		$msg_res = $new_id > 0 ? '' : 'No se pudo registrar el dominio.';
	}

	if ($msg_res === '')
	{
		crm_dominios_procesar_notificaciones();
	}

	return $msg_res;
}

/**
 * Renews a domain, records historical renewal log, updates expiration date & clears pending notifications.
 */
function crm_dominio_renovar($id_dominio, $fecha_renovacion, $fecha_vencimiento_nueva, $notas = '')
{
	if (!acceso_puede_gestionar('sistemas'))
	{
		return 'No tenés permisos para renovar dominios.';
	}

	$id = intval($id_dominio);
	if ($id <= 0)
	{
		return 'Dominio no válido.';
	}

	$params_sel = array($id);
	$dom = acceso_consulta_una("SELECT * FROM dominio WHERE id_dominio = ? AND activo = 1 LIMIT 1", 'i', $params_sel);
	if (!$dom)
	{
		return 'El dominio no existe o ya no está activo.';
	}

	$fecha_renov = trim((string)$fecha_renovacion);
	$fecha_nueva = trim((string)$fecha_vencimiento_nueva);

	if ($fecha_renov === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_renov))
	{
		$fecha_renov = date('Y-m-d');
	}

	if ($fecha_nueva === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nueva))
	{
		return 'La nueva fecha de vencimiento es obligatoria y debe ser válida (AAAA-MM-DD).';
	}

	$fecha_venc_ant = $dom['fecha_vencimiento'];

	// 1. Get current logged in user ID
	$id_usuario = 0;
	if (function_exists('crm_empleado_actual'))
	{
		$emp = crm_empleado_actual();
		if ($emp && isset($emp['id_empleado']))
		{
			$id_usuario = intval($emp['id_empleado']);
		}
	}
	if ($id_usuario <= 0 && isset($_SESSION['id_empleado']))
	{
		$id_usuario = intval($_SESSION['id_empleado']);
	}

	// 2. Insert into renewal log table
	$notas_str = trim($notas);
	$params_ins = array($id, $fecha_renov, $fecha_venc_ant, $fecha_nueva, $id_usuario, $notas_str);
	$ins = acceso_insertar(
		"INSERT INTO dominio_renovacion (id_dominio, fecha_renovacion, fecha_vencimiento_anterior, fecha_vencimiento_nueva, id_usuario, notas) 
		 VALUES (?, ?, ?, ?, ?, ?)",
		'isssis',
		$params_ins
	);

	if ($ins <= 0)
	{
		return 'No se pudo registrar la renovación en el historial.';
	}

	// 3. Update domain new expiration date
	$params_upd = array($fecha_nueva, $id);
	$upd = acceso_ejecutar(
		"UPDATE dominio SET fecha_vencimiento = ?, actualizado_en = NOW() WHERE id_dominio = ? AND activo = 1",
		'si',
		$params_upd
	);

	if (!$upd)
	{
		return 'Se registró el historial pero ocurrió un error actualizando la fecha del dominio.';
	}

	// 4. Update notifications (clear old alerts if domain renewed >30d)
	crm_dominios_procesar_notificaciones();

	return '';
}

/**
 * Soft deletes a domain setting activo = 0.
 */
function crm_dominio_baja($id_dominio)
{
	if (!acceso_puede_gestionar('sistemas'))
	{
		return 'No tenés permisos para dar de baja dominios.';
	}

	$id = intval($id_dominio);
	if ($id <= 0)
	{
		return 'Identificador de dominio inválido.';
	}

	$params_baja = array($id);
	$ok = acceso_ejecutar(
		"UPDATE dominio SET activo = 0 WHERE id_dominio = ? AND activo = 1",
		'i',
		$params_baja
	);

	if ($ok)
	{
		crm_dominios_procesar_notificaciones();
		return '';
	}

	return 'No se pudo dar de baja el dominio o ya estaba inactivo.';
}
