<?php
// Agenda compartida de salas de reuniones. Las reservas son globales: no dependen del área activa.

function salas_reuniones_asegurar()
{
	global $con;
	static $listo=false;
	if($listo || !$con)
	{
		return;
	}
	$listo=true;
	@mysqli_query($con, "CREATE TABLE IF NOT EXISTS sala_reunion (
		id_sala INT NOT NULL AUTO_INCREMENT,
		nombre VARCHAR(120) NOT NULL,
		activo TINYINT(1) NOT NULL DEFAULT 1,
		PRIMARY KEY (id_sala),
		UNIQUE KEY uq_sala_reunion_nombre (nombre)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	@mysqli_query($con, "CREATE TABLE IF NOT EXISTS sala_reunion_reserva (
		id_reserva INT NOT NULL AUTO_INCREMENT,
		id_sala INT NOT NULL,
		id_area INT NOT NULL,
		id_empleado INT NOT NULL,
		fecha DATE NOT NULL,
		hora_inicio TIME NOT NULL,
		hora_fin TIME NOT NULL,
		motivo VARCHAR(500) NOT NULL,
		estado VARCHAR(20) NOT NULL DEFAULT 'activa',
		creado_en DATETIME NOT NULL,
		actualizado_en DATETIME NOT NULL,
		PRIMARY KEY (id_reserva),
		KEY idx_sala_reserva_agenda (id_sala, fecha, estado, hora_inicio, hora_fin),
		KEY idx_sala_reserva_area (id_area, fecha),
		KEY idx_sala_reserva_empleado (id_empleado, fecha)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	@mysqli_query($con, "INSERT INTO sala_reunion (nombre, activo)
		SELECT 'Sala de reuniones', 1
		WHERE NOT EXISTS (SELECT 1 FROM sala_reunion WHERE nombre='Sala de reuniones')");
}

function salas_reuniones_salas_activas()
{
	salas_reuniones_asegurar();
	return acceso_consulta("SELECT id_sala, nombre FROM sala_reunion WHERE activo=1 ORDER BY nombre");
}

/** The user-facing turnera currently books its configured single room. */
function salas_reuniones_sala_para_reserva()
{
	$salas=salas_reuniones_salas_activas();
	if(!is_array($salas) || count($salas)===0)
	{
		return null;
	}
	if(count($salas)===1)
	{
		return $salas[0];
	}
	foreach($salas as $sala)
	{
		if(isset($sala['nombre']) && $sala['nombre']==='Sala de reuniones')
		{
			return $sala;
		}
	}
	return null;
}

function salas_reuniones_areas_disponibles()
{
	$etiquetas=acceso_etiquetas_area();
	$permitidas=array();
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return $etiquetas;
	}
	$base=isset($_SESSION['area_base']) ? $_SESSION['area_base'] : '';
	if(isset($etiquetas[$base]))
	{
		$permitidas[$base]=$etiquetas[$base];
	}
	foreach(acceso_roles_adicionales_sesion() as $extra)
	{
		$area=isset($extra['area']) ? $extra['area'] : '';
		if(isset($etiquetas[$area]))
		{
			$permitidas[$area]=$etiquetas[$area];
		}
	}
	$activa=acceso_area_activa();
	if(isset($etiquetas[$activa]))
	{
		$permitidas[$activa]=$etiquetas[$activa];
	}
	return $permitidas;
}

function salas_reuniones_area_valida_para_reservar($area)
{
	$area=trim((string)$area);
	$permitidas=salas_reuniones_areas_disponibles();
	return isset($permitidas[$area]);
}

function salas_reuniones_area_activa_valida_para_reservar($area)
{
	$area=trim((string)$area);
	$activa=acceso_area_activa();
	$permitidas=salas_reuniones_areas_disponibles();
	return $area!=='' && $area===$activa && isset($permitidas[$area]);
}

function salas_reuniones_hora_valida($hora)
{
	$hora=trim((string)$hora);
	return (bool)preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $hora);
}

function salas_reuniones_fecha_valida($fecha)
{
	$fecha=trim((string)$fecha);
	$dt=DateTime::createFromFormat('Y-m-d', $fecha);
	return $dt && $dt->format('Y-m-d')===$fecha;
}

function salas_reuniones_intervalos_superpuestos($inicio_a, $fin_a, $inicio_b, $fin_b)
{
	$inicio_a=substr(trim((string)$inicio_a), 0, 5);
	$fin_a=substr(trim((string)$fin_a), 0, 5);
	$inicio_b=substr(trim((string)$inicio_b), 0, 5);
	$fin_b=substr(trim((string)$fin_b), 0, 5);
	return $inicio_a<$fin_b && $fin_a>$inicio_b;
}

function salas_reuniones_fecha_hora_pasada($fecha, $hora, $ahora=null)
{
	if(!salas_reuniones_fecha_valida($fecha) || !salas_reuniones_hora_valida(substr(trim((string)$hora), 0, 5)))
	{
		return true;
	}
	$zona=$ahora instanceof DateTimeImmutable ? $ahora->getTimezone() : new DateTimeZone(date_default_timezone_get());
	$inicio=DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fecha.' '.substr(trim((string)$hora), 0, 5), $zona);
	if(!$inicio)
	{
		return true;
	}
	$ahora=$ahora instanceof DateTimeImmutable ? $ahora : new DateTimeImmutable('now', $zona);
	return $inicio<$ahora;
}

function salas_reuniones_estado_visual($reserva, $ahora=null)
{
	$estado=isset($reserva['estado']) ? $reserva['estado'] : '';
	if($estado==='cancelada') { return 'Cancelada'; }
	if(!in_array($estado,array('activa','confirmada'),true)) { return 'Pendiente'; }
	$fecha=isset($reserva['fecha']) ? $reserva['fecha'] : '';
	$fin=isset($reserva['hora_fin']) ? $reserva['hora_fin'] : '';
	if(salas_reuniones_fecha_valida($fecha) && salas_reuniones_hora_valida(substr((string)$fin,0,5)))
	{
		$zona=$ahora instanceof DateTimeImmutable ? $ahora->getTimezone() : new DateTimeZone(date_default_timezone_get());
		$fin_dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fecha.' '.substr((string)$fin,0,5), $zona);
		$ahora=$ahora instanceof DateTimeImmutable ? $ahora : new DateTimeImmutable('now', $zona);
		if($fin_dt && $fin_dt<=$ahora) { return 'Horario finalizado'; }
		$inicio=isset($reserva['hora_inicio']) ? substr((string)$reserva['hora_inicio'],0,5) : '';
		if(salas_reuniones_hora_valida($inicio))
		{
			$inicio_dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fecha.' '.$inicio, $zona);
			if($inicio_dt && $inicio_dt<=$ahora) { return 'En curso'; }
		}
	}
	return $estado==='confirmada' ? 'Confirmada' : 'Reservada';
}

function salas_reuniones_reserva_por_id($id_reserva)
{
	salas_reuniones_asegurar();
	return acceso_consulta_una("SELECT r.*, s.nombre AS sala_nombre, a.clave AS area_clave, a.nombre AS area_nombre,
		CONCAT(IFNULL(e.nombres,''), ' ', IFNULL(e.apellido,'')) AS empleado_nombre
		FROM sala_reunion_reserva r
		INNER JOIN sala_reunion s ON s.id_sala=r.id_sala
		INNER JOIN area a ON a.id_area=r.id_area
		INNER JOIN empleado e ON e.id_empleado=r.id_empleado
		WHERE r.id_reserva=? LIMIT 1", 'i', array(intval($id_reserva)));
}

function salas_reuniones_puede_gestionar_area($area)
{
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return true;
	}
	$area=trim((string)$area);
	if($area==='')
	{
		return false;
	}
	if(isset($_SESSION['rol_base'], $_SESSION['area_base']) && $_SESSION['rol_base']==='coordinador' && $_SESSION['area_base']===$area)
	{
		return true;
	}
	foreach(acceso_roles_adicionales_sesion() as $extra)
	{
		if(isset($extra['rol'], $extra['area']) && $extra['rol']==='coordinador' && $extra['area']===$area)
		{
			return true;
		}
	}
	return false;
}

function salas_reuniones_puede_crear()
{
	return salas_reuniones_puede_gestionar_area(acceso_area_activa());
}

function salas_reuniones_puede_gestionar($reserva)
{
	if(!$reserva || !isset($reserva['area_clave']))
	{
		return false;
	}
	return salas_reuniones_puede_gestionar_area($reserva['area_clave']);
}

function salas_reuniones_hay_superposicion($id_sala, $fecha, $hora_inicio, $hora_fin, $excepto_id=0, $bloquear=false)
{
	$id_sala=intval($id_sala);
	$excepto_id=intval($excepto_id);
	$sql="SELECT id_reserva FROM sala_reunion_reserva
		WHERE id_sala=? AND fecha=? AND estado IN ('activa','confirmada')
		AND hora_inicio < ? AND hora_fin > ?";
	$tipos='isss';
	$valores=array($id_sala, $fecha, $hora_fin.':00', $hora_inicio.':00');
	if($excepto_id>0)
	{
		$sql.=" AND id_reserva<>?";
		$tipos.='i';
		$valores[]=$excepto_id;
	}
	$sql.=" LIMIT 1".($bloquear ? ' FOR UPDATE' : '');
	return (bool)acceso_consulta_una($sql, $tipos, $valores);
}

function salas_reuniones_validar_datos($datos, $excepto_id=0)
{
	salas_reuniones_asegurar();
	$id_sala=isset($datos['id_sala']) ? intval($datos['id_sala']) : 0;
	$area=isset($datos['area']) ? trim($datos['area']) : '';
	$fecha=isset($datos['fecha']) ? trim($datos['fecha']) : '';
	$inicio=isset($datos['hora_inicio']) ? trim($datos['hora_inicio']) : '';
	$fin=isset($datos['hora_fin']) ? trim($datos['hora_fin']) : '';
	$motivo=isset($datos['motivo']) ? trim($datos['motivo']) : '';
	if(!acceso_consulta_una("SELECT id_sala FROM sala_reunion WHERE id_sala=? AND activo=1 LIMIT 1", 'i', array($id_sala)))
	{
		return 'La sala seleccionada no está disponible.';
	}
	if(!salas_reuniones_area_valida_para_reservar($area))
	{
		return 'No podés reservar una sala para esa área.';
	}
	if(!salas_reuniones_fecha_valida($fecha))
	{
		return 'Indicá una fecha válida.';
	}
	if(!salas_reuniones_hora_valida($inicio) || !salas_reuniones_hora_valida($fin) || $inicio>=$fin)
	{
		return 'La hora de finalización debe ser posterior a la de inicio.';
	}
	if(salas_reuniones_fecha_hora_pasada($fecha, $inicio))
	{
		return 'No se pueden reservar horarios que ya pasaron.';
	}
	if($motivo==='')
	{
		return 'Indicá el motivo de la reserva.';
	}
	if(strlen($motivo)>500)
	{
		return 'El motivo no puede superar los 500 caracteres.';
	}
	return '';
}

function salas_reuniones_guardar($datos, $id_reserva=0)
{
	global $con;
	salas_reuniones_asegurar();
	$id_reserva=intval($id_reserva);
	if(!$con)
	{
		return 'No se pudo conectar con la agenda de salas.';
	}
	if($id_reserva>0)
	{
		$actual=salas_reuniones_reserva_por_id($id_reserva);
		if(!$actual || !salas_reuniones_puede_gestionar($actual)) { return 'No tenés permiso para modificar esta reserva.'; }
		if(!in_array($actual['estado'], array('activa','confirmada'), true)) { return 'La reserva ya no se puede editar.'; }
		// La edición sólo cambia fecha, horario y motivo; sala y área originales quedan protegidas.
		$datos['id_sala']=intval($actual['id_sala']);
		$datos['area']=$actual['area_clave'];
	}
	else if(!salas_reuniones_puede_crear())
	{
		return 'Solo coordinadores autorizados, Coordinación General y Superadmin pueden crear reservas.';
	}
	else if(!salas_reuniones_area_activa_valida_para_reservar(isset($datos['area']) ? $datos['area'] : ''))
	{
		return 'La reserva nueva debe corresponder al área activa seleccionada.';
	}
	else
	{
		$sala_fija=salas_reuniones_sala_para_reserva();
		if(!$sala_fija)
		{
			return 'No hay una sala única configurada para nuevas reservas.';
		}
		$datos['id_sala']=intval($sala_fija['id_sala']);
	}
	$err=salas_reuniones_validar_datos($datos, $id_reserva);
	if($err!=='')
	{
		return $err;
	}
	$id_sala=intval($datos['id_sala']);
	$id_area=acceso_id_por_clave('area', $datos['area']);
	$fecha=trim($datos['fecha']);
	$inicio=trim($datos['hora_inicio']);
	$fin=trim($datos['hora_fin']);
	$motivo=trim($datos['motivo']);
	$id_empleado=crm_id_empleado_sesion();
	if(($id_reserva===0 && $id_empleado<=0) || $id_area<=0)
	{
		return 'No se pudo identificar al empleado o al área solicitante.';
	}
	if(!@mysqli_query($con, 'START TRANSACTION'))
	{
		return 'No se pudo iniciar la reserva.';
	}
	try
	{
		// Serializa las escrituras de una sala: el chequeo de superposición ocurre bajo este bloqueo.
		$bloqueo=acceso_consulta_una("SELECT id_sala FROM sala_reunion WHERE id_sala=? AND activo=1 FOR UPDATE", 'i', array($id_sala));
		if(!$bloqueo)
		{
			@mysqli_query($con, 'ROLLBACK');
			return 'La sala seleccionada ya no está disponible.';
		}
		if($id_reserva>0 && !acceso_consulta_una("SELECT id_reserva FROM sala_reunion_reserva WHERE id_reserva=? AND estado IN ('activa','confirmada') FOR UPDATE", 'i', array($id_reserva)))
		{
			@mysqli_query($con, 'ROLLBACK');
			return 'La reserva ya no está activa y no se puede editar.';
		}
		if(salas_reuniones_hay_superposicion($id_sala, $fecha, $inicio, $fin, $id_reserva, true))
		{
			@mysqli_query($con, 'ROLLBACK');
			return 'La sala ya está reservada en ese horario.';
		}
		if($id_reserva>0)
		{
			$ok=acceso_ejecutar("UPDATE sala_reunion_reserva SET id_sala=?, id_area=?, fecha=?, hora_inicio=?, hora_fin=?, motivo=?, actualizado_en=NOW()
				WHERE id_reserva=? AND estado IN ('activa','confirmada')", 'iissssi', array($id_sala, $id_area, $fecha, $inicio.':00', $fin.':00', $motivo, $id_reserva));
		}
		else
		{
			$ok=acceso_ejecutar("INSERT INTO sala_reunion_reserva (id_sala, id_area, id_empleado, fecha, hora_inicio, hora_fin, motivo, estado, creado_en, actualizado_en)
				VALUES (?,?,?,?,?,?,?,'activa',NOW(),NOW())", 'iiissss', array($id_sala, $id_area, $id_empleado, $fecha, $inicio.':00', $fin.':00', $motivo));
		}
		if(!$ok)
		{
			@mysqli_query($con, 'ROLLBACK');
			return 'No se pudo guardar la reserva.';
		}
		@mysqli_query($con, 'COMMIT');
		return '';
	}
	catch(Throwable $e)
	{
		@mysqli_query($con, 'ROLLBACK');
		return 'No se pudo guardar la reserva.';
	}
}

function salas_reuniones_cancelar($id_reserva)
{
	global $con;
	$reserva=salas_reuniones_reserva_por_id($id_reserva);
	if(!$reserva)
	{
		return 'No se encontró la reserva.';
	}
	if(!salas_reuniones_puede_gestionar($reserva))
	{
		return 'No tenés permiso para cancelar esta reserva.';
	}
	if(!in_array($reserva['estado'], array('activa','confirmada'), true))
	{
		return 'La reserva ya no está activa.';
	}
	if(!$con || !@mysqli_query($con,'START TRANSACTION')) { return 'No se pudo cancelar la reserva.'; }
	$actual=acceso_consulta_una("SELECT estado FROM sala_reunion_reserva WHERE id_reserva=? FOR UPDATE", 'i', array(intval($id_reserva)));
	if(!$actual || !in_array($actual['estado'],array('activa','confirmada'),true))
	{
		@mysqli_query($con,'ROLLBACK');
		return 'La reserva ya no está activa.';
	}
	$ok=acceso_ejecutar("UPDATE sala_reunion_reserva SET estado='cancelada', actualizado_en=NOW() WHERE id_reserva=? AND estado IN ('activa','confirmada')", 'i', array(intval($id_reserva)));
	if(!$ok) { @mysqli_query($con,'ROLLBACK'); return 'No se pudo cancelar la reserva.'; }
	@mysqli_query($con,'COMMIT');
	return '';
}

function salas_reuniones_reservas_mes($anio, $mes)
{
	salas_reuniones_asegurar();
	$desde=sprintf('%04d-%02d-01', intval($anio), intval($mes));
	$hasta=date('Y-m-d', strtotime($desde.' +1 month'));
	return acceso_consulta("SELECT r.*, s.nombre AS sala_nombre, a.nombre AS area_nombre,
		CONCAT(IFNULL(e.nombres,''), ' ', IFNULL(e.apellido,'')) AS empleado_nombre
		FROM sala_reunion_reserva r
		INNER JOIN sala_reunion s ON s.id_sala=r.id_sala
		INNER JOIN area a ON a.id_area=r.id_area
		INNER JOIN empleado e ON e.id_empleado=r.id_empleado
		WHERE r.fecha>=? AND r.fecha<? AND r.estado IN ('activa','confirmada')
		ORDER BY r.fecha, r.hora_inicio, r.hora_fin", 'ss', array($desde, $hasta));
}

function salas_reuniones_reservas_fecha($fecha, $id_sala=0)
{
	salas_reuniones_asegurar();
	$sql="SELECT r.*, s.nombre AS sala_nombre, a.nombre AS area_nombre,
		CONCAT(IFNULL(e.nombres,''), ' ', IFNULL(e.apellido,'')) AS empleado_nombre
		FROM sala_reunion_reserva r
		INNER JOIN sala_reunion s ON s.id_sala=r.id_sala
		INNER JOIN area a ON a.id_area=r.id_area
		INNER JOIN empleado e ON e.id_empleado=r.id_empleado
		WHERE r.fecha=? AND r.estado IN ('activa','confirmada')";
	$tipos='s';
	$valores=array($fecha);
	if(intval($id_sala)>0)
	{
		$sql.=' AND r.id_sala=?';
		$tipos.='i';
		$valores[]=intval($id_sala);
	}
	$sql.=' ORDER BY r.hora_inicio, r.hora_fin';
	return acceso_consulta($sql, $tipos, $valores);
}

function salas_reuniones_listar($filtros=array())
{
	$desde=isset($filtros['desde']) ? trim($filtros['desde']) : '';
	$hasta=isset($filtros['hasta']) ? trim($filtros['hasta']) : '';
	$fecha=isset($filtros['fecha']) ? trim($filtros['fecha']) : '';
	$area=isset($filtros['area']) ? trim($filtros['area']) : '';
	$id_sala=isset($filtros['id_sala']) ? intval($filtros['id_sala']) : 0;
	$estado=isset($filtros['estado']) ? trim($filtros['estado']) : 'todas';
	$hora_desde=isset($filtros['hora_desde']) ? trim($filtros['hora_desde']) : '';
	$hora_hasta=isset($filtros['hora_hasta']) ? trim($filtros['hora_hasta']) : '';
	$historial=!empty($filtros['historial']);
	$limite_historial=max(20, min(1000, intval(isset($filtros['limite']) ? $filtros['limite'] : 20)));
	$wheres=array(); $tipos=''; $valores=array();
	if(salas_reuniones_fecha_valida($fecha)) { $wheres[]='r.fecha=?'; $tipos.='s'; $valores[]=$fecha; }
	if(salas_reuniones_fecha_valida($desde)) { $wheres[]='r.fecha>=?'; $tipos.='s'; $valores[]=$desde; }
	if(salas_reuniones_fecha_valida($hasta)) { $wheres[]='r.fecha<=?'; $tipos.='s'; $valores[]=$hasta; }
	if($area!=='') { $wheres[]='a.clave=?'; $tipos.='s'; $valores[]=$area; }
	if($id_sala>0) { $wheres[]='r.id_sala=?'; $tipos.='i'; $valores[]=$id_sala; }
	if(salas_reuniones_hora_valida($hora_desde)) { $wheres[]='r.hora_fin>?'; $tipos.='s'; $valores[]=$hora_desde.':00'; }
	if(salas_reuniones_hora_valida($hora_hasta)) { $wheres[]='r.hora_inicio<?'; $tipos.='s'; $valores[]=$hora_hasta.':00'; }
	if($historial) { $wheres[]="(TIMESTAMP(r.fecha,r.hora_fin)<=? OR r.estado='cancelada')"; $tipos.='s'; $valores[]=date('Y-m-d H:i:s'); }
	if($estado==='activa') { $wheres[]="r.estado='activa'"; }
	else if($estado==='confirmada') { $wheres[]="r.estado='confirmada'"; }
	else if($estado==='cancelada') { $wheres[]="r.estado='cancelada'"; }
	else if($estado==='finalizada') { $wheres[]="r.estado IN ('activa','confirmada') AND TIMESTAMP(r.fecha,r.hora_fin)<=?"; $tipos.='s'; $valores[]=date('Y-m-d H:i:s'); }
	else if($estado==='vigente') { $wheres[]="r.estado IN ('activa','confirmada') AND TIMESTAMP(r.fecha,r.hora_fin)>?"; $tipos.='s'; $valores[]=date('Y-m-d H:i:s'); }
	$sql="SELECT r.*, s.nombre AS sala_nombre, a.clave AS area_clave, a.nombre AS area_nombre,
		CONCAT(IFNULL(e.nombres,''), ' ', IFNULL(e.apellido,'')) AS empleado_nombre
		FROM sala_reunion_reserva r
		INNER JOIN sala_reunion s ON s.id_sala=r.id_sala
		INNER JOIN area a ON a.id_area=r.id_area
		INNER JOIN empleado e ON e.id_empleado=r.id_empleado";
	if(count($wheres)>0) { $sql.=' WHERE '.implode(' AND ', $wheres); }
	$sql.=$historial ? ' ORDER BY r.fecha DESC, r.hora_inicio DESC, r.id_reserva DESC LIMIT '.$limite_historial : ' ORDER BY r.hora_inicio, r.id_reserva LIMIT 200';
	return acceso_consulta($sql, $tipos, $valores);
}

function salas_reuniones_historial($filtros=array())
{
	$filtros['historial']=true;
	return salas_reuniones_listar($filtros);
}

function salas_reuniones_horarios_disponibles($fecha, $id_sala)
{
	$ocupadas=salas_reuniones_reservas_fecha($fecha, $id_sala);
	$libres=array();
	if(!is_array($ocupadas)) { $ocupadas=array(); }
	for($min=8*60; $min<20*60; $min+=30)
	{
		$inicio=sprintf('%02d:%02d', floor($min/60), $min%60);
		$fin=sprintf('%02d:%02d', floor(($min+30)/60), ($min+30)%60);
		if(salas_reuniones_fecha_hora_pasada($fecha,$inicio)) { continue; }
		$ocupado=false;
		foreach($ocupadas as $r)
		{
			if(salas_reuniones_intervalos_superpuestos($inicio,$fin,$r['hora_inicio'],$r['hora_fin'])) { $ocupado=true; break; }
		}
		if(!$ocupado)
		{
			$libres[]=$inicio.'–'.$fin;
		}
	}
	return $libres;
}
