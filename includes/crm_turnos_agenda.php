<?php
// Agenda de turnos: horarios fijos, cupo diario, sin duplicados, cancelar/reprogramar.

function crm_asegurar_turno_agenda()
{
	global $con;
	static $listo=false;
	if($listo || !$con)
	{
		return;
	}
	$listo=true;

	if(crm_schema_al_dia('turno_agenda_v3'))
	{
		return;
	}
	if(!acceso_tabla_existe('atencion'))
	{
		return;
	}
	crm_asegurar_turno_campos();
	if(!crm_atencion_estado_enum_ok())
	{
		@mysqli_query($con, "ALTER TABLE atencion MODIFY estado ENUM('abierta','en_atencion','cerrada','derivada','cancelada') NOT NULL DEFAULT 'abierta'");
		if(!crm_atencion_estado_enum_ok())
		{
			error_log('CRM: no se pudo ampliar atencion.estado: '.mysqli_error($con));
			return;
		}
	}
	// Turnos que quedaron "Sin estado" cuando la columna no aceptaba en_atencion/cancelada.
	@mysqli_query($con, "UPDATE atencion SET estado='cancelada' WHERE estado='' AND detalle LIKE '%Cancelado%'");
	@mysqli_query($con, "UPDATE atencion SET estado='en_atencion' WHERE estado=''");
	if(!acceso_tabla_existe('turno_config'))
	{
		@mysqli_query($con, "CREATE TABLE turno_config (
			clave VARCHAR(40) NOT NULL PRIMARY KEY,
			valor VARCHAR(120) NOT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		@mysqli_query($con, "INSERT INTO turno_config (clave, valor) VALUES
			('turnos_por_dia','16'),
			('hora_desde','07:00'),
			('hora_hasta','19:30')");
	}
	crm_schema_marcar('turno_agenda_v3');
}

function crm_atencion_estado_enum_ok()
{
	global $con;
	$q=@mysqli_query($con, "SHOW COLUMNS FROM atencion LIKE 'estado'");
	$row=$q ? mysqli_fetch_assoc($q) : null;
	$type=$row && isset($row['Type']) ? strtolower($row['Type']) : '';
	return ($type!=='' && strpos($type, "'en_atencion'")!==false && strpos($type, "'cancelada'")!==false && strpos($type, "'cerrada'")!==false);
}

function crm_turno_config($clave, $def='')
{
	crm_asegurar_turno_agenda();
	if(!acceso_tabla_existe('turno_config'))
	{
		return $def;
	}
	$row=acceso_consulta_una("SELECT valor FROM turno_config WHERE clave=? LIMIT 1", 's', array($clave));
	return $row ? trim($row['valor']) : $def;
}

function crm_turno_config_set($clave, $valor)
{
	crm_asegurar_turno_agenda();
	$clave=trim($clave);
	$valor=trim((string)$valor);
	if($clave=='' || !acceso_tabla_existe('turno_config'))
	{
		return false;
	}
	$hay=acceso_consulta_una("SELECT clave FROM turno_config WHERE clave=? LIMIT 1", 's', array($clave));
	if($hay)
	{
		acceso_ejecutar("UPDATE turno_config SET valor=? WHERE clave=?", 'ss', array($valor, $clave));
	}
	else
	{
		acceso_insertar("INSERT INTO turno_config (clave, valor) VALUES (?,?)", 'ss', array($clave, $valor));
	}
	return true;
}

function crm_turno_cupo_diario()
{
	$n=intval(crm_turno_config('turnos_por_dia', '16'));
	if($n<1)
	{
		$n=1;
	}
	if($n>48)
	{
		$n=48;
	}
	return $n;
}

function crm_turno_horarios_fijos()
{
	$desde=crm_turno_config('hora_desde', '07:00');
	$hasta=crm_turno_config('hora_hasta', '19:30');
	if(!preg_match('/^\d{2}:\d{2}$/', $desde))
	{
		$desde='07:00';
	}
	if(!preg_match('/^\d{2}:\d{2}$/', $hasta))
	{
		$hasta='19:30';
	}
	$out=array();
	$cur=strtotime('2000-01-01 '.$desde.':00');
	$fin=strtotime('2000-01-01 '.$hasta.':00');
	if($cur===false || $fin===false || $cur>$fin)
	{
		$cur=strtotime('2000-01-01 07:00:00');
		$fin=strtotime('2000-01-01 19:30:00');
	}
	while($cur<=$fin)
	{
		$out[]=date('H:i', $cur);
		$cur+=30*60;
	}
	return $out;
}

function crm_turno_armar_fecha($fecha, $hora)
{
	$fecha=trim((string)$fecha);
	$hora=trim((string)$hora);
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
	{
		return '';
	}
	if(!preg_match('/^\d{2}:\d{2}$/', $hora))
	{
		return '';
	}
	$ok=crm_turno_horarios_fijos();
	if(!in_array($hora, $ok, true))
	{
		return '';
	}
	return $fecha.' '.$hora.':00';
}

function crm_turno_estados_ocupan_cupo()
{
	// Un turno derivado ya no ocupa lugar: el cliente pasó a otra área.
	return array('abierta','en_atencion');
}

function crm_turno_slot_ocupado($id_area, $fecha_turno, $excepto_id=0)
{
	$id_area=intval($id_area);
	$fecha_turno=trim((string)$fecha_turno);
	$excepto_id=intval($excepto_id);
	if($id_area<=0 || $fecha_turno=='')
	{
		return false;
	}
	$sql="SELECT id_atencion FROM atencion
		WHERE id_area=? AND fecha_turno=? AND estado IN ('abierta','en_atencion')";
	$types='is';
	$vals=array($id_area, $fecha_turno);
	if($excepto_id>0)
	{
		$sql.=" AND id_atencion<>?";
		$types.='i';
		$vals[]=$excepto_id;
	}
	$sql.=" LIMIT 1";
	return (bool)acceso_consulta_una($sql, $types, $vals);
}

function crm_turno_cantidad_dia($id_area, $fecha, $excepto_id=0)
{
	$id_area=intval($id_area);
	$fecha=trim((string)$fecha);
	$excepto_id=intval($excepto_id);
	if($id_area<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
	{
		return 0;
	}
	$sql="SELECT COUNT(*) AS n FROM atencion
		WHERE id_area=? AND DATE(fecha_turno)=? AND estado IN ('abierta','en_atencion')";
	$types='is';
	$vals=array($id_area, $fecha);
	if($excepto_id>0)
	{
		$sql.=" AND id_atencion<>?";
		$types.='i';
		$vals[]=$excepto_id;
	}
	$row=acceso_consulta_una($sql, $types, $vals);
	return $row ? intval($row['n']) : 0;
}

function crm_turno_horarios_disponibles($area_clave, $fecha, $excepto_id=0)
{
	crm_asegurar_turno_agenda();
	if(!ini_get('date.timezone'))
	{
		date_default_timezone_set('America/Argentina/Buenos_Aires');
	}
	$id_area=acceso_id_por_clave('area', $area_clave);
	$fecha=trim((string)$fecha);
	if($id_area<=0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))
	{
		return array();
	}
	$hoy=date('Y-m-d');
	if($fecha < $hoy)
	{
		return array();
	}
	$ahora_hi=date('H:i');
	$cupo=crm_turno_cupo_diario();
	$usados=crm_turno_cantidad_dia($id_area, $fecha, $excepto_id);
	$libres_cupo=max(0, $cupo-$usados);
	$todos=crm_turno_horarios_fijos();
	$ocupados=array();
	$rows=acceso_consulta(
		"SELECT DATE_FORMAT(fecha_turno,'%H:%i') AS hora FROM atencion
		WHERE id_area=? AND DATE(fecha_turno)=? AND estado IN ('abierta','en_atencion')
		".($excepto_id>0 ? " AND id_atencion<>".intval($excepto_id) : ""),
		'is',
		array($id_area, $fecha)
	);
	foreach($rows as $r)
	{
		if(isset($r['hora']) && $r['hora']!='')
		{
			$ocupados[$r['hora']]=true;
		}
	}
	$disp=array();
	foreach($todos as $h)
	{
		if(isset($ocupados[$h]))
		{
			continue;
		}
		// Si es para hoy, no permitir horarios que ya hayan pasado
		if($fecha===$hoy && $h<=$ahora_hi)
		{
			continue;
		}
		$disp[]=$h;
	}
	if($libres_cupo<=0)
	{
		return array();
	}
	return $disp;
}

function crm_turno_validar_alta($area_clave, $fecha, $hora, $excepto_id=0)
{
	crm_asegurar_turno_agenda();
	if(!ini_get('date.timezone'))
	{
		date_default_timezone_set('America/Argentina/Buenos_Aires');
	}
	$id_area=acceso_id_por_clave('area', $area_clave);
	if($id_area<=0)
	{
		return 'Área no válida.';
	}
	$ft=crm_turno_armar_fecha($fecha, $hora);
	if($ft=='')
	{
		return 'Elegí una fecha y un horario válido (cada media hora).';
	}
	$hoy=date('Y-m-d');
	if($fecha < $hoy)
	{
		return 'No se pueden registrar turnos para fechas pasadas.';
	}
	$ahora_hi=date('H:i');
	if($fecha===$hoy && $hora<=$ahora_hi)
	{
		return 'El horario seleccionado ('.$hora.') ya pasó. Elegí un horario posterior a las '.$ahora_hi.'.';
	}
	if(crm_turno_slot_ocupado($id_area, $ft, $excepto_id))
	{
		return 'Ese horario ya tiene un turno en el área.';
	}
	if(crm_turno_cantidad_dia($id_area, $fecha, $excepto_id)>=crm_turno_cupo_diario())
	{
		return 'Se alcanzó el máximo de turnos del día ('.crm_turno_cupo_diario().').';
	}
	return '';
}

function crm_atencion_iniciar_atencion($id_atencion, $id_empleado=0)
{
	crm_asegurar_turno_agenda();
	$id=intval($id_atencion);
	$at=crm_atencion_traer($id);
	if(!$at)
	{
		return 'No se encontró el turno.';
	}
	$puede=crm_atencion_puede_operar($at);
	if(!$puede)
	{
		return 'No podés iniciar la atención de este turno.';
	}
	if($at['estado']=='cancelada' || $at['estado']=='cerrada')
	{
		return 'Ese turno ya no está activo.';
	}
	if($at['estado']=='en_atencion')
	{
		return '';
	}
	$id_emp = (intval($id_empleado) > 0) ? intval($id_empleado) : (intval($at['id_empleado']) > 0 ? intval($at['id_empleado']) : crm_id_empleado_sesion());
	acceso_ejecutar("UPDATE atencion SET estado='en_atencion', id_empleado=? WHERE id_atencion=?", 'ii', array($id_emp > 0 ? $id_emp : null, $id));
	if(function_exists('crm_avisos_atencion_cerrar_todos'))
	{
		crm_avisos_atencion_cerrar_todos($id);
	}
	if(function_exists('crm_atencion_seguimiento_agregar'))
	{
		crm_atencion_seguimiento_agregar(
			$id,
			intval($at['id_area']),
			$id_emp > 0 ? $id_emp : crm_id_empleado_sesion(),
			$at['estado'],
			'en_atencion',
			'inicio_atencion',
			'Cliente presente · Atención iniciada'
		);
	}
	return '';
}

function crm_atencion_cancelar($id_atencion, $motivo='')
{
	crm_asegurar_turno_agenda();
	$id=intval($id_atencion);
	$at=crm_atencion_traer($id);
	if(!$at)
	{
		return 'No se encontró el turno.';
	}
	$puede=crm_atencion_puede_operar($at);
	if(!$puede)
	{
		return 'No podés cancelar este turno.';
	}
	if($at['estado']=='cancelada' || $at['estado']=='cerrada')
	{
		return 'Ese turno ya está cerrado o cancelado.';
	}
	$txt=trim($motivo);
	$viejo=isset($at['detalle']) ? trim($at['detalle']) : '';
	$paso='Cancelado'.($txt!='' ? ': '.$txt : '');
	$detalle=($viejo!='' ? $viejo.' | ' : '').$paso;
	acceso_ejecutar(
		"UPDATE atencion SET estado='cancelada', detalle=?, id_empleado=? WHERE id_atencion=?",
		'sii',
		array($detalle, crm_id_empleado_sesion(), $id)
	);
	if(function_exists('crm_avisos_atencion_cerrar_todos'))
	{
		crm_avisos_atencion_cerrar_todos($id);
	}
	if(function_exists('crm_atencion_seguimiento_agregar'))
	{
		crm_atencion_seguimiento_agregar(
			$id,
			intval($at['id_area']),
			crm_id_empleado_sesion(),
			$at['estado'],
			'cancelada',
			'cancelacion',
			'Atención o turno cancelado' . ($txt != '' ? '. Motivo: ' . $txt : '')
		);
	}
	return '';
}

function crm_atencion_reprogramar($id_atencion, $fecha, $hora, $motivo='')
{
	crm_asegurar_turno_agenda();
	$id=intval($id_atencion);
	$at=crm_atencion_traer($id);
	if(!$at)
	{
		return 'No se encontró el turno.';
	}
	$puede=function_exists('crm_atencion_puede_operar') ? crm_atencion_puede_operar($at)
		: ((function_exists('crm_puede_gestionar_atencion') && crm_puede_gestionar_atencion($at))
			|| acceso_area_pantalla()=='recepcion' || acceso_es_super() || acceso_es_coord_general());
	if(!$puede)
	{
		return 'No podés reprogramar este turno.';
	}
	if($at['estado']=='cancelada' || $at['estado']=='cerrada' || $at['estado']=='derivada')
	{
		return 'No se puede reprogramar un turno cerrado, cancelado o derivado.';
	}
	$err=crm_turno_validar_alta($at['area_clave'], $fecha, $hora, $id);
	if($err!='')
	{
		return $err;
	}
	$ft=crm_turno_armar_fecha($fecha, $hora);
	$viejo=isset($at['detalle']) ? trim($at['detalle']) : '';
	$antes=isset($at['fecha_turno']) && $at['fecha_turno']!='' ? crm_turno_fecha_texto($at['fecha_turno']) : '';
	$txt_mot=trim($motivo);
	$paso='Reprogramado'.($antes!='' ? ' (antes '.$antes.')' : '').($txt_mot!='' ? ' - Motivo: '.$txt_mot : '');
	$detalle=($viejo!='' ? $viejo.' | ' : '').$paso;
	acceso_ejecutar(
		"UPDATE atencion SET fecha_turno=?, estado='abierta', detalle=? WHERE id_atencion=?",
		'ssi',
		array($ft, $detalle, $id)
	);
	if(function_exists('crm_atencion_seguimiento_agregar'))
	{
		crm_atencion_seguimiento_agregar(
			$id,
			intval($at['id_area']),
			crm_id_empleado_sesion(),
			$at['estado'],
			'abierta',
			'reprogramacion',
			'Turno reprogramado para ' . (function_exists('crm_turno_fecha_texto') ? crm_turno_fecha_texto($ft) : $ft) . ($antes != '' ? ' (anterior: ' . $antes . ')' : '') . ($txt_mot != '' ? '. Motivo: ' . $txt_mot : '')
		);
	}
	return '';
}

function crm_calendario_dias_con_turnos($anio, $mes, $area_clave='')
{
	crm_asegurar_turno_agenda();
	$anio=intval($anio);
	$mes=intval($mes);
	if($anio<2000 || $mes<1 || $mes>12 || !acceso_tabla_existe('atencion'))
	{
		return array();
	}
	if($area_clave=='')
	{
		$area_clave=acceso_area_pantalla();
	}
	$sql="SELECT DAY(COALESCE(at.fecha_turno, at.fecha)) AS d, COUNT(*) AS n
		FROM atencion at
		INNER JOIN area a ON a.id_area=at.id_area
		WHERE YEAR(COALESCE(at.fecha_turno, at.fecha))=?
			AND MONTH(COALESCE(at.fecha_turno, at.fecha))=?
			AND at.estado IN ('abierta','en_atencion')";
	$types='ii';
	$vals=array($anio, $mes);
	if(!crm_area_ve_todo($area_clave))
	{
		$sql.=" AND a.clave=?";
		$types.='s';
		$vals[]=$area_clave;
	}
	$sql.=" GROUP BY DAY(COALESCE(at.fecha_turno, at.fecha))";
	$marcados=array();
	foreach(acceso_consulta($sql, $types, $vals) as $r)
	{
		$marcados[intval($r['d'])]=intval($r['n']);
	}
	return $marcados;
}

function crm_turnos_asignados($fecha_desde, $fecha_hasta)
{
	crm_asegurar_turno_agenda();
	if(!acceso_tabla_existe('atencion')
		|| !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)
		|| !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT at.id_atencion, at.fecha_turno, at.estado, a.clave AS area_clave, a.nombre AS area_nombre, c.nombre AS cliente_nombre
		FROM atencion at
		INNER JOIN area a ON a.id_area=at.id_area
		INNER JOIN cliente c ON c.id_cliente=at.id_cliente
		WHERE at.fecha_turno IS NOT NULL
			AND DATE(at.fecha_turno) BETWEEN ? AND ?
			AND at.estado IN ('abierta','en_atencion')
		ORDER BY at.fecha_turno, a.nombre",
		'ss',
		array($fecha_desde, $fecha_hasta)
	);
}

function crm_turno_guardar_config_form()
{
	if(!acceso_es_coord_general() && !acceso_es_super())
	{
		return 'Solo el coordinador general puede cambiar esto.';
	}
	$cupo=isset($_POST['turnos_por_dia']) ? intval($_POST['turnos_por_dia']) : 0;
	$desde=isset($_POST['hora_desde']) ? trim($_POST['hora_desde']) : '07:00';
	$hasta=isset($_POST['hora_hasta']) ? trim($_POST['hora_hasta']) : '19:30';
	if($cupo<1 || $cupo>48)
	{
		return 'El cupo diario debe ser entre 1 y 48.';
	}
	if(!preg_match('/^\d{2}:\d{2}$/', $desde) || !preg_match('/^\d{2}:\d{2}$/', $hasta))
	{
		return 'Horario desde/hasta inválido.';
	}
	if(strtotime('2000-01-01 '.$desde) > strtotime('2000-01-01 '.$hasta))
	{
		return 'La hora desde no puede ser mayor que hasta.';
	}
	crm_turno_config_set('turnos_por_dia', (string)$cupo);
	crm_turno_config_set('hora_desde', $desde);
	crm_turno_config_set('hora_hasta', $hasta);
	return '';
}

function crm_cambiar_nombre_usuario($id_empleado, $nuevo)
{
	$id=intval($id_empleado);
	$nuevo=trim((string)$nuevo);
	if($id<=0)
	{
		return 'Colaborador no válido.';
	}
	if($nuevo=='' || !preg_match('/^[a-zA-Z0-9._-]{3,40}$/', $nuevo))
	{
		return 'Usuario: 3 a 40 caracteres (letras, números, . _ -).';
	}
	$emp=acceso_empleado_por_id($id);
	if(!$emp)
	{
		return 'No se encontró el colaborador.';
	}
	if(isset($emp['rol']) && $emp['rol']=='superadmin')
	{
		return 'No se cambia el usuario del superadmin acá.';
	}
	$otro=acceso_consulta_una(
		"SELECT id_empleado FROM empleado WHERE nombre_usuario=? AND id_empleado<>? LIMIT 1",
		'si',
		array($nuevo, $id)
	);
	if($otro)
	{
		return 'Ese nombre de usuario ya está en uso.';
	}
	acceso_ejecutar("UPDATE empleado SET nombre_usuario=? WHERE id_empleado=?", 'si', array($nuevo, $id));
	return '';
}
