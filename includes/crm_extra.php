<?php
// Extensiones CRM: turnos, programas, eventos, avisos, coordinador general.

function crm_campo($nombre, $def='')
{
	if(!isset($_POST[$nombre]))
	{
		return $def;
	}
	if(is_array($_POST[$nombre]))
	{
		return $_POST[$nombre];
	}
	return $_POST[$nombre];
}

function crm_tarea_avances_juntos($enlaces, $comentarios)
{
	$links=array();
	if(!is_array($enlaces))
	{
		$enlaces=array();
	}
	if(!is_array($comentarios))
	{
		$comentarios=array();
	}
	foreach($enlaces as $link)
	{
		$url=is_array($link) ? (isset($link['url']) ? trim($link['url']) : '') : trim($link);
		if($url=='')
		{
			continue;
		}
		$cuando=is_array($link) && isset($link['fecha_carga']) ? $link['fecha_carga'] : '';
		$links[]=array('url'=>$url, 'ts'=>($cuando!='' ? strtotime($cuando) : 0), 'usado'=>false);
	}
	$salida=array();
	foreach($comentarios as $com)
	{
		$txt=is_array($com) ? (isset($com['texto']) ? $com['texto'] : '') : $com;
		$quien=is_array($com) && isset($com['quien']) ? $com['quien'] : '';
		$cuando=is_array($com) && isset($com['cuando']) ? $com['cuando'] : '';
		$raw=is_array($com) && isset($com['fecha_carga']) ? $com['fecha_carga'] : '';
		$ts=($raw!='' ? strtotime($raw) : ($cuando!='' ? strtotime(str_replace('/', '-', $cuando)) : 0));
		$url='';
		// Solo emparejar link con comentario si fueron cargados casi juntos (mismo avance).
		foreach($links as $k=>$l)
		{
			if($l['usado'])
			{
				continue;
			}
			if($ts>0 && $l['ts']>0 && abs($l['ts']-$ts)<=30)
			{
				$url=$l['url'];
				$links[$k]['usado']=true;
				break;
			}
		}
		$salida[]=array('texto'=>$txt, 'quien'=>$quien, 'cuando'=>$cuando, 'url'=>$url);
	}
	foreach($links as $l)
	{
		if(!$l['usado'])
		{
			$salida[]=array('texto'=>'','quien'=>'','cuando'=>'','url'=>$l['url']);
		}
	}
	return $salida;
}

function crm_tarea_agregar_enlace($id_tarea, $url)
{
	$id=intval($id_tarea);
	$url=trim($url);
	if($id<=0 || $url=='' || !acceso_tabla_existe('tarea_enlace'))
	{
		return false;
	}
	$chk=acceso_consulta_una("SELECT estado FROM tarea WHERE id_tarea=? LIMIT 1", 'i', array($id));
	if($chk && isset($chk['estado']))
	{
		$est=strtolower(trim($chk['estado']));
		if($est==='no_iniciada' || $est==='terminada' || $est==='cancelada')
		{
			return false;
		}
	}
	$hay=acceso_consulta_una("SELECT id FROM tarea_enlace WHERE id_tarea=? AND url=? LIMIT 1", 'is', array($id, $url));
	if($hay)
	{
		return true;
	}
	return acceso_insertar("INSERT INTO tarea_enlace (id_tarea, url) VALUES (?,?)", 'is', array($id, $url))>0;
}

function crm_tarea_agregar_comentario($id_tarea, $texto)
{
	$id=intval($id_tarea);
	$texto=trim($texto);
	if($id<=0 || $texto=='' || !acceso_tabla_existe('tarea_comentario'))
	{
		return false;
	}
	$emp=crm_id_empleado_sesion();
	if($emp<=0)
	{
		return false;
	}
	$chk=acceso_consulta_una("SELECT estado FROM tarea WHERE id_tarea=? LIMIT 1", 'i', array($id));
	if($chk && isset($chk['estado']))
	{
		$est=strtolower(trim($chk['estado']));
		if($est==='no_iniciada' || $est==='terminada' || $est==='cancelada')
		{
			return false;
		}
	}
	// Evitar duplicado exacto en los últimos segundos (doble submit / doble handler).
	$dup=acceso_consulta_una(
		"SELECT id FROM tarea_comentario WHERE id_tarea=? AND id_empleado=? AND texto=? AND fecha_carga >= (NOW() - INTERVAL 15 SECOND) LIMIT 1",
		'iis',
		array($id, $emp, $texto)
	);
	if($dup)
	{
		return true;
	}
	return acceso_insertar("INSERT INTO tarea_comentario (id_tarea, id_empleado, texto) VALUES (?,?,?)", 'iis', array($id, $emp, $texto))>0;
}

function crm_solo_numeros($texto)
{
	return preg_replace('/\D+/', '', isset($texto) ? $texto : '');
}

function crm_clave_desde_dni($dni)
{
	$dni=crm_solo_numeros($dni);
	if(strlen($dni)<6)
	{
		return '';
	}
	return $dni;
}

function crm_id_empleado_sesion()
{
	if(isset($_SESSION['id_empleado']) && intval($_SESSION['id_empleado'])>0)
	{
		return intval($_SESSION['id_empleado']);
	}
	return isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
}

function crm_vincular_cliente_area($id_cliente, $id_area)
{
	$id_cliente=intval($id_cliente);
	$id_area=intval($id_area);
	if($id_cliente<=0 || $id_area<=0 || !acceso_tabla_existe('cliente_area'))
	{
		return;
	}
	$hay=acceso_consulta_una("SELECT id FROM cliente_area WHERE id_cliente=? AND id_area=? LIMIT 1", 'ii', array($id_cliente, $id_area));
	if($hay)
	{
		return;
	}
	acceso_insertar("INSERT INTO cliente_area (id_cliente, id_area) VALUES (?,?)", 'ii', array($id_cliente, $id_area));
}

function crm_nombres_areas_cliente($id_cliente)
{
	$nombres=array();
	foreach(crm_areas_del_cliente($id_cliente) as $a)
	{
		if(isset($a['nombre']) && $a['nombre']!='')
		{
			$nombres[]=$a['nombre'];
		}
	}
	return count($nombres) ? implode(', ', $nombres) : '—';
}

function crm_areas_del_cliente($id_cliente)
{
	$id=intval($id_cliente);
	if($id<=0 || !acceso_tabla_existe('cliente_area'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT a.id_area, a.clave, a.nombre
		FROM cliente_area ca
		INNER JOIN area a ON a.id_area=ca.id_area
		WHERE ca.id_cliente=?
		ORDER BY a.nombre",
		'i',
		array($id)
	);
}

function crm_empleados_de_area_id($id_area)
{
	$id_area=intval($id_area);
	if($id_area<=0)
	{
		return array();
	}
	$principal=acceso_consulta(
		"SELECT e.id_empleado, e.nombres, e.apellido, e.nombre_usuario, r.clave AS rol_clave
		FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE e.id_area=? AND e.activo=1 AND r.clave<>'superadmin'
		ORDER BY e.apellido, e.nombres",
		'i',
		array($id_area)
	);
	$extra=array();
	if(acceso_tabla_existe('empleado_rol_adicional'))
	{
		$extra=acceso_consulta(
			"SELECT e.id_empleado, e.nombres, e.apellido, e.nombre_usuario, r.clave AS rol_clave
			FROM empleado_rol_adicional x
			INNER JOIN empleado e ON e.id_empleado=x.id_empleado
			INNER JOIN rol r ON r.id_rol=x.id_rol
			WHERE x.id_area=? AND x.fecha_hasta IS NULL AND e.activo=1 AND r.clave<>'superadmin'
			ORDER BY e.apellido, e.nombres",
			'i',
			array($id_area)
		);
	}
	$ya=array();
	$salida=array();
	foreach(array_merge($principal, $extra) as $row)
	{
		$id=intval($row['id_empleado']);
		if($id<=0 || isset($ya[$id]))
		{
			continue;
		}
		$ya[$id]=1;
		$salida[]=$row;
	}
	return $salida;
}

function crm_personal_sin_super()
{
	return acceso_consulta(
		"SELECT e.id_empleado, e.nombres, e.apellido, e.nombre_usuario, e.activo, e.dni, e.primer_acceso,
			r.clave AS rol_clave, a.clave AS area_clave, a.nombre AS area_nombre
		FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		LEFT JOIN area a ON a.id_area=e.id_area
		WHERE r.clave<>'superadmin'
		ORDER BY IFNULL(a.nombre,'ZZZ'), e.apellido, e.nombres"
	);
}

function crm_avisos_atencion_cerrar_todos($id_atencion)
{
	$id=intval($id_atencion);
	if($id<=0 || !acceso_tabla_existe('aviso_atencion'))
	{
		return;
	}
	acceso_ejecutar("UPDATE aviso_atencion SET confirmado=1 WHERE id_atencion=? AND confirmado=0", 'i', array($id));
}

function crm_avisos_atencion_crear($id_atencion, $id_area)
{
	$id_atencion=intval($id_atencion);
	$id_area=intval($id_area);
	if($id_atencion<=0 || $id_area<=0 || !acceso_tabla_existe('aviso_atencion'))
	{
		return;
	}
	$de=crm_id_empleado_sesion();
	$destinatarios=crm_empleados_de_area_id($id_area);
	$coord=crm_coord_general_actual();
	if($coord && isset($coord['id_empleado']))
	{
		$destinatarios[]=array('id_empleado'=>intval($coord['id_empleado']));
	}
	$ya=array();
	foreach($destinatarios as $emp)
	{
		$id_para=intval($emp['id_empleado']);
		if($id_para<=0 || isset($ya[$id_para]))
		{
			continue;
		}
		// Si el creador es el único del área, igual recibe el aviso (bandeja).
		// Si hay más gente, no se auto-avisa a sí mismo.
		if($id_para==$de && count($destinatarios)>1)
		{
			continue;
		}
		$ya[$id_para]=1;
		$pendiente=acceso_consulta_una(
			"SELECT id_aviso FROM aviso_atencion WHERE id_atencion=? AND id_empleado_para=? AND confirmado=0 LIMIT 1",
			'ii',
			array($id_atencion, $id_para)
		);
		if($pendiente)
		{
			continue;
		}
		if($de>0)
		{
			acceso_insertar(
				"INSERT INTO aviso_atencion (id_atencion, id_empleado_de, id_empleado_para) VALUES (?,?,?)",
				'iii',
				array($id_atencion, $de, $id_para)
			);
		}
		else
		{
			acceso_insertar(
				"INSERT INTO aviso_atencion (id_atencion, id_empleado_de, id_empleado_para) VALUES (?,NULL,?)",
				'ii',
				array($id_atencion, $id_para)
			);
		}
	}
}

function crm_asegurar_turno_campos()
{
	global $con;
	static $listo=false;
	if($listo || !$con)
	{
		return;
	}
	$listo=true;
	if(crm_schema_al_dia('atencion_fecha_turno'))
	{
		return;
	}
	if(!acceso_tabla_existe('atencion'))
	{
		return;
	}
	$q=@mysqli_query($con, "SHOW COLUMNS FROM atencion LIKE 'fecha_turno'");
	if($q && mysqli_num_rows($q)==0)
	{
		@mysqli_query($con, "ALTER TABLE atencion ADD COLUMN fecha_turno DATETIME NULL AFTER fecha");
	}
	crm_schema_marcar('atencion_fecha_turno');
}

function crm_turno_fecha_de_form($datos)
{
	if(isset($datos['fecha_turno']) && trim($datos['fecha_turno'])!='')
	{
		$ft=trim($datos['fecha_turno']);
		if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $ft))
		{
			return $ft.' 09:00:00';
		}
		if(preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $ft))
		{
			return str_replace('T', ' ', substr($ft, 0, 16)).':00';
		}
	}
	$cuando=isset($datos['cuando']) ? trim($datos['cuando']) : '';
	if($cuando=='fecha' && isset($datos['fecha']) && trim($datos['fecha'])!='')
	{
		return trim($datos['fecha']).' 09:00:00';
	}
	return date('Y-m-d H:i:s');
}

function crm_turno_es_ahora($fecha_turno)
{
	$ft=trim((string)$fecha_turno);
	if($ft=='')
	{
		return true;
	}
	return (strtotime($ft)<=time()+60);
}

function crm_turno_fecha_texto($fecha_turno)
{
	$ft=trim((string)$fecha_turno);
	if($ft=='' || $ft=='0000-00-00 00:00:00')
	{
		return date('d/m/Y H:i');
	}
	$ts=strtotime($ft);
	if($ts===false)
	{
		return $ft;
	}
	return date('d/m/Y H:i', $ts);
}

function crm_turno_cuando_texto($fecha_turno)
{
	return crm_turno_fecha_texto($fecha_turno);
}

function crm_atencion_abrir($datos)
{
	crm_asegurar_turno_campos();
	if(function_exists('crm_asegurar_turno_agenda'))
	{
		crm_asegurar_turno_agenda();
	}
	$id_cliente=isset($datos['id_cliente']) ? intval($datos['id_cliente']) : 0;
	$area=isset($datos['area']) ? $datos['area'] : '';
	$id_area=acceso_id_por_clave('area', $area);
	if($id_area<=0 && isset($datos['id_area']))
	{
		$id_area=intval($datos['id_area']);
	}
	if($id_cliente<=0 || $id_area<=0 || !acceso_tabla_existe('atencion'))
	{
		return 0;
	}
	// Preferir fecha+hora fijas si vienen
	if(isset($datos['fecha']) && isset($datos['hora']) && function_exists('crm_turno_armar_fecha'))
	{
		$ft=crm_turno_armar_fecha($datos['fecha'], $datos['hora']);
		if($ft=='')
		{
			return 0;
		}
		$err=crm_turno_validar_alta($area, $datos['fecha'], $datos['hora'], 0);
		if($err!='')
		{
			if(isset($datos['error_ref']) && is_array($datos['error_ref']))
			{
				$datos['error_ref'][0]=$err;
			}
			$_SESSION['msgrecepcion']=$err;
			return 0;
		}
		$fecha_turno=$ft;
	}
	else
	{
		$fecha_turno=crm_turno_fecha_de_form($datos);
		// Normalizar a slot de media hora si se puede
		$ts=strtotime($fecha_turno);
		if($ts!==false && function_exists('crm_turno_armar_fecha'))
		{
			$f=date('Y-m-d', $ts);
			$min=intval(date('i', $ts));
			$h=intval(date('H', $ts));
			if($min<15) { $min=0; }
			else if($min<45) { $min=30; }
			else { $min=0; $h++; }
			$hora=sprintf('%02d:%02d', $h, $min);
			$norm=crm_turno_armar_fecha($f, $hora);
			if($norm!='')
			{
				$fecha_turno=$norm;
				$err=crm_turno_validar_alta($area, $f, $hora, 0);
				if($err!='')
				{
					$_SESSION['msgrecepcion']=$err;
					return 0;
				}
			}
		}
	}
	$es_ahora=crm_turno_es_ahora($fecha_turno);
	if($es_ahora)
	{
		$ya_abierta=acceso_consulta_una(
			"SELECT id_atencion FROM atencion WHERE id_cliente=? AND id_area=? AND estado IN ('abierta','en_atencion') AND DATE(COALESCE(fecha_turno, fecha))=CURDATE() ORDER BY id_atencion DESC LIMIT 1",
			'ii',
			array($id_cliente, $id_area)
		);
		if($ya_abierta && isset($ya_abierta['id_atencion']))
		{
			crm_avisos_atencion_crear(intval($ya_abierta['id_atencion']), $id_area);
			return intval($ya_abierta['id_atencion']);
		}
	}
	$motivo=isset($datos['motivo']) ? $datos['motivo'] : 'consulta';
	$ok_motivo=array('consulta','documentacion','seguimiento','tramite','otro');
	if(!in_array($motivo, $ok_motivo))
	{
		$motivo='consulta';
	}
	$canal=isset($datos['canal']) ? $datos['canal'] : 'presencial';
	$ok_canal=array('web','presencial','whatsapp','redes');
	if(!in_array($canal, $ok_canal, true))
	{
		$canal='presencial';
	}
	$detalle=isset($datos['detalle']) ? trim($datos['detalle']) : '';
	$origen=isset($datos['id_origen']) ? intval($datos['id_origen']) : 0;
	$estado_ini=isset($datos['estado']) ? $datos['estado'] : 'abierta';
	$ok_est=array('pendiente','abierta','en_atencion','cerrada','derivada','cancelada');
	if(!in_array($estado_ini, $ok_est, true))
	{
		$estado_ini='abierta';
	}
	if($origen>0)
	{
		$id=acceso_insertar(
			"INSERT INTO atencion (id_cliente, id_area, motivo, canal, detalle, estado, id_atencion_origen, fecha_turno) VALUES (?,?,?,?,?,?,?,?)",
			'iissssis',
			array($id_cliente, $id_area, $motivo, $canal, $detalle, $estado_ini, $origen, $fecha_turno)
		);
	}
	else
	{
		$id=acceso_insertar(
			"INSERT INTO atencion (id_cliente, id_area, motivo, canal, detalle, estado, fecha_turno) VALUES (?,?,?,?,?,?,?)",
			'iisssss',
			array($id_cliente, $id_area, $motivo, $canal, $detalle, $estado_ini, $fecha_turno)
		);
	}
	if($id<=0)
	{
		return 0;
	}
	crm_vincular_cliente_area($id_cliente, $id_area);
	// Avisar siempre al área destino (programado o inmediato).
	crm_avisos_atencion_crear($id, $id_area);
	return $id;
}

function crm_atencion_guardar($datos)
{
	return crm_atencion_abrir($datos);
}

/** Recepción acepta una solicitud web y la agenda (pendiente → abierta). */
function crm_atencion_aceptar_solicitud($id_atencion, $fecha, $hora)
{
	$id=intval($id_atencion);
	$at=crm_atencion_traer($id);
	if(!$at || !crm_atencion_puede_operar($at))
	{
		return 'No podés operar ese turno.';
	}
	if($at['estado']!=='pendiente')
	{
		return 'Esa solicitud ya fue procesada.';
	}
	$area=isset($at['area_clave']) ? $at['area_clave'] : 'recepcion';
	$err=function_exists('crm_turno_validar_alta') ? crm_turno_validar_alta($area, $fecha, $hora, $id) : '';
	if($err!='')
	{
		return $err;
	}
	$ft=function_exists('crm_turno_armar_fecha') ? crm_turno_armar_fecha($fecha, $hora) : trim($fecha.' '.$hora.':00');
	if($ft=='')
	{
		return 'Fecha u hora inválida.';
	}
	$id_emp=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;
	$ok=acceso_ejecutar(
		"UPDATE atencion SET estado='abierta', canal='presencial', fecha_turno=?, id_empleado=? WHERE id_atencion=? AND estado='pendiente'",
		'sii',
		array($ft, $id_emp>0 ? $id_emp : null, $id)
	);
	if(!$ok)
	{
		return 'No se pudo aceptar la solicitud.';
	}
	crm_avisos_atencion_crear($id, intval($at['id_area']));
	return '';
}

function crm_atencion_traer($id_atencion)
{
	$id=intval($id_atencion);
	if($id<=0)
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT at.*, a.clave AS area_clave, a.nombre AS area_nombre, c.nombre AS cliente_nombre
		FROM atencion at
		INNER JOIN area a ON a.id_area=at.id_area
		INNER JOIN cliente c ON c.id_cliente=at.id_cliente
		WHERE at.id_atencion=? LIMIT 1",
		'i',
		array($id)
	);
}

function crm_atencion_puede_operar($at)
{
	if(!$at)
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return true;
	}
	$area=acceso_area_pantalla();
	// Recepción registra y consulta turnos, pero NO opera ni atiende los turnos derivados a otras áreas.
	return (isset($at['area_clave']) && $at['area_clave']===$area);
}

function crm_atencion_cerrar($id_atencion, $detalle='')
{
	$id=intval($id_atencion);
	$at=crm_atencion_traer($id);
	if(!$at)
	{
		return 'No se encontró la atención.';
	}
	if(!crm_atencion_puede_operar($at))
	{
		return 'No podés cerrar este turno.';
	}
	if($at['estado']!='abierta' && $at['estado']!='en_atencion')
	{
		return 'Ese turno ya está cerrado.';
	}
	$txt=trim($detalle);
	$id_emp=crm_id_empleado_sesion();
	if($txt!='')
	{
		$viejo=isset($at['detalle']) ? trim($at['detalle']) : '';
		$txt=($viejo!='' ? $viejo.' | ' : '').$txt;
		if($id_emp>0)
		{
			acceso_ejecutar("UPDATE atencion SET estado='cerrada', detalle=?, id_empleado=? WHERE id_atencion=?", 'sii', array($txt, $id_emp, $id));
		}
		else
		{
			acceso_ejecutar("UPDATE atencion SET estado='cerrada', detalle=? WHERE id_atencion=?", 'si', array($txt, $id));
		}
	}
	else if($id_emp>0)
	{
		acceso_ejecutar("UPDATE atencion SET estado='cerrada', id_empleado=? WHERE id_atencion=?", 'ii', array($id_emp, $id));
	}
	else
	{
		acceso_ejecutar("UPDATE atencion SET estado='cerrada' WHERE id_atencion=?", 'i', array($id));
	}
	crm_avisos_atencion_cerrar_todos($id);
	crm_atencion_cancelar_pendientes_empresa($id);
	return '';
}

// Al cerrar un turno de cliente organización, cancela turnos abiertos futuros del mismo cliente con mensaje claro.
function crm_atencion_cancelar_pendientes_empresa($id_atencion_origen)
{
	$id_origen=intval($id_atencion_origen);
	$at=crm_atencion_traer($id_origen);
	if(!$at)
	{
		return;
	}
	$id_cli=isset($at['id_cliente']) ? intval($at['id_cliente']) : 0;
	if($id_cli<=0)
	{
		return;
	}
	$cli=acceso_consulta_una("SELECT tipo_cliente FROM cliente WHERE id_cliente=? LIMIT 1", 'i', array($id_cli));
	if(!$cli || !cliente_es_organizacion(isset($cli['tipo_cliente']) ? $cli['tipo_cliente'] : ''))
	{
		return;
	}
	$msg='Cancelado automáticamente al cerrar el turno #'.$id_origen.'. La organización ya fue atendida en esa visita.';
	$pend=acceso_consulta(
		"SELECT id_atencion, detalle FROM atencion
		WHERE id_cliente=? AND id_atencion<>? AND estado IN ('abierta','en_atencion')
			AND (fecha_turno IS NULL OR fecha_turno >= NOW())",
		'ii',
		array($id_cli, $id_origen)
	);
	foreach($pend as $p)
	{
		$id_p=intval($p['id_atencion']);
		$det=isset($p['detalle']) ? trim($p['detalle']) : '';
		$nuevo=($det!='' ? $det.' | ' : '').$msg;
		acceso_ejecutar("UPDATE atencion SET estado='cancelada', detalle=? WHERE id_atencion=?", 'si', array($nuevo, $id_p));
		crm_avisos_atencion_cerrar_todos($id_p);
	}
}

function crm_atencion_reabrir($id_atencion)
{
	$id=intval($id_atencion);
	$at=crm_atencion_traer($id);
	if(!$at)
	{
		return 'No se encontró la atención.';
	}
	if(!crm_atencion_puede_operar($at))
	{
		return 'No podés reabrir este turno.';
	}
	if($at['estado']=='derivada')
	{
		return 'Un turno derivado no se reabre acá. Continuá en el turno del área destino.';
	}
	if($at['estado']!='cerrada')
	{
		return 'Solo se puede reabrir un turno cerrado.';
	}
	acceso_ejecutar("UPDATE atencion SET estado='abierta' WHERE id_atencion=?", 'i', array($id));
	crm_avisos_atencion_crear($id, intval($at['id_area']));
	return '';
}

function crm_atencion_derivar($id_atencion, $area_destino, $detalle='', &$id_nuevo=null)
{
	$id=intval($id_atencion);
	$id_nuevo=0;
	$at=crm_atencion_traer($id);
	if(!$at)
	{
		return 'No se encontró la atención.';
	}
	if(!crm_atencion_puede_operar($at))
	{
		return 'No podés derivar esta atención.';
	}
	// Se puede derivar en espera o mientras se atiende (la UI usa ambos flujos).
	if($at['estado']!='abierta' && $at['estado']!='en_atencion')
	{
		return 'Solo se puede derivar una atención activa (en espera o en atención).';
	}
	$area_destino=trim((string)$area_destino);
	$id_dest=acceso_id_por_clave('area', $area_destino);
	if($id_dest<=0)
	{
		return 'Elegí un área para derivar.';
	}
	if($id_dest==intval($at['id_area']))
	{
		return 'Esa atención ya está en esa área.';
	}
	$areas_ok=function_exists('recepcion_areas_cliente') ? recepcion_areas_cliente() : array();
	if(count($areas_ok)>0 && !isset($areas_ok[$area_destino]))
	{
		return 'Esa área no admite derivaciones de atención.';
	}
	$dest=acceso_consulta_una("SELECT nombre, clave FROM area WHERE id_area=? LIMIT 1", 'i', array($id_dest));
	$nombre_dest=$dest && isset($dest['nombre']) ? $dest['nombre'] : $area_destino;
	$nota=trim($detalle);
	$viejo=isset($at['detalle']) ? trim($at['detalle']) : '';
	$paso='Derivado a '.$nombre_dest.($nota!='' ? ': '.$nota : '');
	$txt_origen=($viejo!='' ? $viejo.' | ' : '').$paso;
	$txt_nuevo='Derivado desde '.(isset($at['area_nombre']) ? $at['area_nombre'] : 'otra área');
	if($nota!='')
	{
		$txt_nuevo.=': '.$nota;
	}
	// Si el cliente ya estaba siendo atendido, el área destino continúa en atención.
	$estado_nuevo=($at['estado']=='en_atencion') ? 'en_atencion' : 'abierta';
	$fecha_nueva=date('Y-m-d H:i:s');
	$motivo=isset($at['motivo']) ? $at['motivo'] : 'consulta';
	$canal=isset($at['canal']) ? $at['canal'] : 'presencial';
	// Primero el turno nuevo (si falla, el original sigue activo).
	$nuevo=acceso_insertar(
		"INSERT INTO atencion (id_cliente, id_area, motivo, canal, detalle, estado, id_atencion_origen, fecha_turno) VALUES (?,?,?,?,?,?,?,?)",
		'iissssis',
		array(intval($at['id_cliente']), $id_dest, $motivo, $canal, $txt_nuevo, $estado_nuevo, $id, $fecha_nueva)
	);
	if($nuevo<=0)
	{
		return 'No se pudo crear el turno en el área destino.';
	}
	$id_emp=crm_id_empleado_sesion();
	if($id_emp>0)
	{
		$ok=acceso_ejecutar(
			"UPDATE atencion SET estado='derivada', detalle=?, id_empleado=? WHERE id_atencion=?",
			'sii',
			array($txt_origen, $id_emp, $id)
		);
	}
	else
	{
		$ok=acceso_ejecutar(
			"UPDATE atencion SET estado='derivada', detalle=? WHERE id_atencion=?",
			'si',
			array($txt_origen, $id)
		);
	}
	if(!$ok)
	{
		acceso_ejecutar("DELETE FROM aviso_atencion WHERE id_atencion=?", 'i', array($nuevo));
		acceso_ejecutar("DELETE FROM atencion WHERE id_atencion=?", 'i', array($nuevo));
		return 'No se pudo marcar el turno como derivado.';
	}
	crm_vincular_cliente_area(intval($at['id_cliente']), $id_dest);
	crm_avisos_atencion_cerrar_todos($id);
	crm_avisos_atencion_crear($nuevo, $id_dest);
	$id_nuevo=$nuevo;
	return '';
}

function crm_atencion_derivada_siguiente($id_atencion)
{
	$id=intval($id_atencion);
	if($id<=0)
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT at.id_atencion, at.estado, a.clave AS area_clave, a.nombre AS area_nombre
		FROM atencion at
		INNER JOIN area a ON a.id_area=at.id_area
		WHERE at.id_atencion_origen=?
		ORDER BY at.id_atencion DESC LIMIT 1",
		'i',
		array($id)
	);
}

function crm_avisos_atencion_mios()
{
	crm_asegurar_turno_campos();
	$id=crm_id_empleado_sesion();
	if($id<=0 || !acceso_tabla_existe('aviso_atencion'))
	{
		return array();
	}

	// Auto-confirmar avisos de atenciones que ya no están abiertas (en_atencion, cerrada, cancelada, derivada)
	if(acceso_tabla_existe('atencion'))
	{
		acceso_ejecutar(
			"UPDATE aviso_atencion av
			INNER JOIN atencion at ON at.id_atencion=av.id_atencion
			SET av.confirmado=1
			WHERE av.id_empleado_para=? AND av.confirmado=0 AND at.estado IN ('en_atencion','cerrada','cancelada','derivada')",
			'i',
			array($id)
		);
	}

	$area=acceso_area();
	$sql="SELECT MIN(av.id_aviso) AS id_aviso, av.id_atencion, c.nombre AS cliente_nombre, a.nombre AS area_nombre, a.clave AS area_clave, at.motivo, at.id_cliente,
			COALESCE(at.fecha_turno, at.fecha) AS fecha_turno,
			MAX(av.fecha_aviso) AS fecha_aviso
		FROM aviso_atencion av
		INNER JOIN atencion at ON at.id_atencion=av.id_atencion
		INNER JOIN cliente c ON c.id_cliente=at.id_cliente
		INNER JOIN area a ON a.id_area=at.id_area
		WHERE av.id_empleado_para=? AND av.confirmado=0 AND at.estado = 'abierta'";
	$types='i';
	$vals=array($id);
	if($area!='' && $area!='recepcion' && $area!='sistemas' && $area!='general' && !acceso_es_super())
	{
		$sql.=" AND a.clave=?";
		$types.='s';
		$vals[]=$area;
	}
	$sql.=" GROUP BY av.id_atencion, c.nombre, a.nombre, a.clave, at.motivo, at.id_cliente, COALESCE(at.fecha_turno, at.fecha) ORDER BY MIN(av.fecha_aviso) DESC";
	return acceso_consulta($sql, $types, $vals);
}

function crm_aviso_atencion_ok($id_aviso)
{
	$id=intval($id_aviso);
	$yo=crm_id_empleado_sesion();
	if($id<=0 || $yo<=0)
	{
		return;
	}
	$av=acceso_consulta_una("SELECT id_atencion FROM aviso_atencion WHERE id_aviso=? AND id_empleado_para=? LIMIT 1", 'ii', array($id, $yo));
	if($av && isset($av['id_atencion']))
	{
		acceso_ejecutar("UPDATE aviso_atencion SET confirmado=1 WHERE id_atencion=? AND id_empleado_para=? AND confirmado=0", 'ii', array(intval($av['id_atencion']), $yo));
		return;
	}
	acceso_ejecutar("UPDATE aviso_atencion SET confirmado=1 WHERE id_aviso=? AND id_empleado_para=?", 'ii', array($id, $yo));
}

function crm_atenciones_abiertas_area($clave_area)
{
	$id_area=acceso_id_por_clave('area', $clave_area);
	if($id_area<=0)
	{
		return array();
	}
	return acceso_consulta(
		"SELECT at.*, c.nombre AS cliente_nombre
		FROM atencion at INNER JOIN cliente c ON c.id_cliente=at.id_cliente
		WHERE at.id_area=? AND at.estado='abierta'
		ORDER BY at.fecha DESC",
		'i',
		array($id_area)
	);
}

function crm_atenciones_de($id_cliente)
{
	$id=intval($id_cliente);
	if($id<=0 || !acceso_tabla_existe('atencion'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT at.*, a.clave AS area_clave, a.nombre AS area_nombre,
			CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS empleado_nombre
		FROM atencion at
		INNER JOIN area a ON a.id_area=at.id_area
		LEFT JOIN empleado e ON e.id_empleado=at.id_empleado
		WHERE at.id_cliente=?
		ORDER BY at.fecha DESC, at.id_atencion DESC",
		'i',
		array($id)
	);
}

function crm_ejes_de_tipo($id_tipo)
{
	$id=intval($id_tipo);
	if($id<=0 || !acceso_tabla_existe('eje'))
	{
		return array();
	}
	return acceso_consulta("SELECT id_eje, nombre FROM eje WHERE id_tipo=? ORDER BY nombre", 'i', array($id));
}

function crm_aplicar_eje_post($id_tarea, $id_tipo)
{
	$id_tarea=intval($id_tarea);
	$id_tipo=intval($id_tipo);
	$nombre=isset($_POST['eje']) ? trim($_POST['eje']) : '';
	if($id_tarea<=0 || $id_tipo<=0 || $nombre=='')
	{
		return;
	}
	$id_eje=crm_eje_cargar($id_tipo, $nombre);
	if($id_eje>0)
	{
		acceso_ejecutar("UPDATE tarea SET id_eje=? WHERE id_tarea=?", 'ii', array($id_eje, $id_tarea));
	}
}

function crm_eje_cargar($id_tipo, $nombre)
{
	$id_tipo=intval($id_tipo);
	$nombre=trim($nombre);
	if($id_tipo<=0 || $nombre=='' || !acceso_tabla_existe('eje'))
	{
		return 0;
	}
	$hay=acceso_consulta_una("SELECT id_eje FROM eje WHERE id_tipo=? AND LOWER(nombre)=LOWER(?) LIMIT 1", 'is', array($id_tipo, $nombre));
	if($hay)
	{
		return intval($hay['id_eje']);
	}
	return acceso_insertar("INSERT INTO eje (id_tipo, nombre) VALUES (?,?)", 'is', array($id_tipo, $nombre));
}

function crm_hay_coordinador_area($clave_area, $excepto_id=0)
{
	$id_area=acceso_id_por_clave('area', $clave_area);
	if($id_area<=0)
	{
		return false;
	}
	$sql="SELECT e.id_empleado FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE e.id_area=? AND r.clave='coordinador' AND e.activo=1";
	$types='i';
	$vals=array($id_area);
	if(intval($excepto_id)>0)
	{
		$sql.=" AND e.id_empleado<>?";
		$types.='i';
		$vals[]=intval($excepto_id);
	}
	$sql.=" LIMIT 1";
	return acceso_consulta_una($sql, $types, $vals) ? true : false;
}

function crm_coord_general_actual()
{
	if(!acceso_tabla_existe('empleado_rol_adicional'))
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT e.id_empleado, e.nombres, e.apellido, e.nombre_usuario, a.clave AS area_clave
		FROM empleado_rol_adicional x
		INNER JOIN rol r ON r.id_rol=x.id_rol
		INNER JOIN empleado e ON e.id_empleado=x.id_empleado
		INNER JOIN area a ON a.id_area=e.id_area
		WHERE r.clave='coordinador_general' AND x.fecha_hasta IS NULL
		ORDER BY x.id DESC
		LIMIT 1"
	);
}

function crm_marcar_coord_general($id_empleado)
{
	$id=intval($id_empleado);
	$id_rol=acceso_id_por_clave('rol', 'coordinador_general');
	if($id<=0)
	{
		return 'Seleccioná un empleado.';
	}
	if($id_rol<=0)
	{
		return 'No está cargado el rol coordinador general en la base.';
	}
	$emp=acceso_consulta_una(
		"SELECT e.id_empleado FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol WHERE e.id_empleado=? AND r.clave<>'superadmin' AND e.activo=1 LIMIT 1",
		'i',
		array($id)
	);
	if(!$emp)
	{
		return 'Ese empleado no puede ser coordinador general.';
	}
	// Solo uno activo: se cierra cualquier marca vigente (fecha_hasta NULL).
	acceso_ejecutar(
		"UPDATE empleado_rol_adicional SET fecha_hasta=CURDATE() WHERE id_rol=? AND fecha_hasta IS NULL",
		'i',
		array($id_rol)
	);
	$ok=acceso_insertar(
		"INSERT INTO empleado_rol_adicional (id_empleado, id_rol, fecha_desde) VALUES (?,?,CURDATE())",
		'ii',
		array($id, $id_rol)
	);
	if($ok<=0)
	{
		return 'No se pudo guardar. Intentá de nuevo.';
	}
	return '';
}

function crm_roles_adicionales_de($id_empleado)
{
	$id=intval($id_empleado);
	if($id<=0 || !acceso_tabla_existe('empleado_rol_adicional'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT x.id, r.clave AS rol, r.nombre AS rol_nombre, a.clave AS area, a.nombre AS area_nombre, x.fecha_desde
		FROM empleado_rol_adicional x
		INNER JOIN rol r ON r.id_rol=x.id_rol
		LEFT JOIN area a ON a.id_area=x.id_area
		WHERE x.id_empleado=? AND x.fecha_hasta IS NULL
		ORDER BY x.id ASC",
		'i',
		array($id)
	);
}

function crm_rol_adicional_agregar($id_empleado, $rol_clave, $area_clave='')
{
	$id=intval($id_empleado);
	$rol_clave=trim($rol_clave);
	$area_clave=trim($area_clave);
	$id_rol=acceso_id_por_clave('rol', $rol_clave);
	if($id<=0 || $id_rol<=0)
	{
		return 'Indicá un empleado y un rol válidos.';
	}
	if($rol_clave==='superadmin')
	{
		return 'Ese rol no se puede asignar como adicional.';
	}
	$id_area=0;
	if($area_clave!='')
	{
		$id_area=acceso_id_por_clave('area', $area_clave);
		if($id_area<=0)
		{
			return 'El área adicional no es válida.';
		}
	}
	if(in_array($rol_clave, array('empleado','coordinador'), true) && $id_area<=0)
	{
		return 'Para empleado o coordinador adicional hay que indicar el área.';
	}
	if($rol_clave==='presidencia')
	{
		return 'Presidencia es un perfil exclusivo: no se asigna como rol adicional ni junto a un área operativa.';
	}
	// Quien ya es Presidencia no puede sumar áreas.
	$emp=acceso_consulta_una(
		"SELECT r.clave AS rol, a.clave AS area FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		LEFT JOIN area a ON a.id_area=e.id_area
		WHERE e.id_empleado=? LIMIT 1",
		'i',
		array($id)
	);
	if($emp && isset($emp['rol']) && $emp['rol']==='presidencia')
	{
		return 'Esa persona es Presidencia: no puede tener áreas ni roles adicionales.';
	}
	if($emp && isset($emp['area']) && $emp['area']==='recepcion' && $rol_clave==='presidencia')
	{
		return 'No se puede combinar Recepción con Presidencia.';
	}
	if($area_clave==='presidencia' || $area_clave==='general')
	{
		return 'Esa área no se usa como adicional. Para Coordinador general elegí el rol sin área, o un área operativa.';
	}
	// Coordinador general adicional: sin área (es transversal sobre su área principal).
	if($rol_clave==='coordinador_general')
	{
		$id_area=0;
		$area_clave='';
	}
	$ya=acceso_consulta_una(
		"SELECT x.id FROM empleado_rol_adicional x
		WHERE x.id_empleado=? AND x.id_rol=? AND ".($id_area>0 ? "x.id_area=?" : "x.id_area IS NULL")." AND x.fecha_hasta IS NULL LIMIT 1",
		$id_area>0 ? 'iii' : 'ii',
		$id_area>0 ? array($id, $id_rol, $id_area) : array($id, $id_rol)
	);
	if($ya)
	{
		return 'Ese rol adicional ya está activo.';
	}
	if($id_area>0)
	{
		$ok=acceso_insertar(
			"INSERT INTO empleado_rol_adicional (id_empleado, id_rol, id_area, fecha_desde) VALUES (?,?,?,CURDATE())",
			'iii',
			array($id, $id_rol, $id_area)
		);
	}
	else
	{
		$ok=acceso_insertar(
			"INSERT INTO empleado_rol_adicional (id_empleado, id_rol, fecha_desde) VALUES (?,?,CURDATE())",
			'ii',
			array($id, $id_rol)
		);
	}
	return $ok>0 ? '' : 'No se pudo agregar el rol adicional.';
}

function crm_rol_adicional_quitar($id_fila, $id_empleado=0)
{
	$id=intval($id_fila);
	if($id<=0)
	{
		return 'Rol adicional inválido.';
	}
	if($id_empleado>0)
	{
		$ok=acceso_ejecutar(
			"UPDATE empleado_rol_adicional SET fecha_hasta=CURDATE() WHERE id=? AND id_empleado=? AND fecha_hasta IS NULL",
			'ii',
			array($id, intval($id_empleado))
		);
	}
	else
	{
		$ok=acceso_ejecutar(
			"UPDATE empleado_rol_adicional SET fecha_hasta=CURDATE() WHERE id=? AND fecha_hasta IS NULL",
			'i',
			array($id)
		);
	}
	return $ok ? '' : 'No se pudo quitar el rol adicional.';
}

function crm_reset_clave_empleado($id_empleado)
{
	$id=intval($id_empleado);
	$row=acceso_consulta_una("SELECT dni FROM empleado WHERE id_empleado=? LIMIT 1", 'i', array($id));
	if(!$row)
	{
		return 'No se encontró el usuario.';
	}
	$clave=crm_clave_desde_dni($row['dni']);
	if($clave=='')
	{
		return 'Ese empleado no tiene un DNI válido para armar la clave.';
	}
	$hash=acceso_hash_clave($clave);
	acceso_ejecutar("UPDATE empleado SET contrasena_hash=?, primer_acceso=1 WHERE id_empleado=?", 'si', array($hash, $id));
	return '';
}

function crm_reset_clave_cliente($id_usuario)
{
	$id=intval($id_usuario);
	$row=acceso_consulta_una("SELECT dni, correo FROM usuario_cliente WHERE id_usuario=? LIMIT 1", 'i', array($id));
	if(!$row)
	{
		return 'No se encontró el cliente.';
	}
	$dni=isset($row['dni']) ? $row['dni'] : '';
	if($dni=='')
	{
		$pers=acceso_consulta_una(
			"SELECT cp.dni FROM clientes_usuario_clientes cuc
			INNER JOIN cliente_persona cp ON cp.id_cliente=cuc.id_cliente
			WHERE cuc.id_usuario=? AND cuc.rol='titular' AND cuc.fecha_hasta IS NULL LIMIT 1",
			'i',
			array($id)
		);
		$dni=$pers && isset($pers['dni']) ? $pers['dni'] : '';
	}
	$clave=crm_clave_desde_dni($dni);
	if($clave=='')
	{
		return 'Ese usuario no tiene DNI para armar la clave.';
	}
	$hash=acceso_hash_clave($clave);
	acceso_ejecutar("UPDATE usuario_cliente SET contrasena_hash=?, primer_acceso=1, dni=? WHERE id_usuario=?", 'ssi', array($hash, crm_solo_numeros($dni), $id));
	return '';
}

function crm_cambiar_clave_super($id_empleado, $nueva)
{
	$id=intval($id_empleado);
	$nueva=trim($nueva);
	if($id<=0 || strlen($nueva)<6)
	{
		return 'La clave nueva tiene que tener al menos 6 caracteres.';
	}
	$row=acceso_consulta_una(
		"SELECT e.id_empleado FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol WHERE e.id_empleado=? AND r.clave='superadmin' LIMIT 1",
		'i',
		array($id)
	);
	if(!$row)
	{
		return 'Solo se puede cambiar la clave del superadmin acá.';
	}
	$hash=acceso_hash_clave($nueva);
	acceso_ejecutar("UPDATE empleado SET contrasena_hash=?, primer_acceso=0 WHERE id_empleado=?", 'si', array($hash, $id));
	return '';
}

function crm_set_empleado_activo($id_empleado, $activo)
{
	$id=intval($id_empleado);
	$activo=$activo ? 1 : 0;
	if($id<=0)
	{
		return 'Falta el usuario.';
	}
	acceso_ejecutar("UPDATE empleado SET activo=? WHERE id_empleado=?", 'ii', array($activo, $id));
	return '';
}

function crm_programas_lista()
{
	crm_asegurar_tablas_programa();
	if(!acceso_tabla_existe('programa'))
	{
		return array();
	}
	return acceso_consulta("SELECT * FROM programa ORDER BY nombre");
}

function crm_programa_items($id_programa)
{
	$id=intval($id_programa);
	if($id<=0 || !acceso_tabla_existe('programa_item'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT i.*, a.nombre AS area_nombre, a.clave AS area_clave, t.nombre AS tipo_nombre, e.nombre AS eje_nombre
		FROM programa_item i
		INNER JOIN area a ON a.id_area=i.id_area
		LEFT JOIN tipo_tarea t ON t.id_tipo=i.id_tipo
		LEFT JOIN eje e ON e.id_eje=i.id_eje
		WHERE i.id_programa=?
		ORDER BY a.nombre, t.nombre, i.titulo, i.id",
		'i',
		array($id)
	);
}

function crm_programa_quitar_item($id_programa, $id_item)
{
	$id_programa=intval($id_programa);
	$id_item=intval($id_item);
	if($id_programa<=0 || $id_item<=0 || !acceso_tabla_existe('programa_item'))
	{
		return 'No se pudo quitar la tarea.';
	}
	$ok=acceso_ejecutar(
		"DELETE FROM programa_item WHERE id=? AND id_programa=? LIMIT 1",
		'ii',
		array($id_item, $id_programa)
	);
	return $ok ? '' : 'No se pudo quitar la tarea del programa.';
}

function crm_agrupar_items_por_area($items)
{
	$salida=array();
	if(!is_array($items))
	{
		return $salida;
	}
	foreach($items as $it)
	{
		$area=isset($it['area_nombre']) && $it['area_nombre']!='' ? $it['area_nombre'] : 'Sin área';
		if(!isset($salida[$area]))
		{
			$salida[$area]=array();
		}
		$salida[$area][]=$it;
	}
	return $salida;
}

function crm_agrupar_modelos_por_objetivo_area($modelos)
{
	$salida=array();
	if(!is_array($modelos))
	{
		return $salida;
	}
	foreach($modelos as $m)
	{
		$obj=isset($m['tipo_nombre']) && $m['tipo_nombre']!='' ? $m['tipo_nombre'] : 'Sin objetivo';
		$area=isset($m['area_nombre']) && $m['area_nombre']!='' ? $m['area_nombre'] : 'Sin área';
		if(!isset($salida[$obj]))
		{
			$salida[$obj]=array();
		}
		if(!isset($salida[$obj][$area]))
		{
			$salida[$obj][$area]=array();
		}
		$salida[$obj][$area][]=$m;
	}
	return $salida;
}

function crm_eventos_de_area($area_clave)
{
	$id_area=acceso_id_por_clave('area', $area_clave);
	if($id_area<=0 || !acceso_tabla_existe('evento'))
	{
		return array();
	}
	$id_emp=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;
	return acceso_consulta(
		"SELECT DISTINCT ev.*, a.clave AS area_clave, c.nombre AS cliente_nombre,
			CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS responsable,
			(SELECT COUNT(*) FROM evento_inscripcion ei WHERE ei.id_evento=ev.id_evento) AS n_inscriptos
		FROM evento ev
		INNER JOIN area a ON a.id_area=ev.id_area
		LEFT JOIN cliente c ON c.id_cliente=ev.id_cliente
		LEFT JOIN empleado e ON e.id_empleado=ev.id_empleado
		LEFT JOIN evento_tarea et ON et.id_evento=ev.id_evento
		LEFT JOIN tarea t ON t.id_tarea=et.id_tarea
		LEFT JOIN tarea_responsable tr ON tr.id_tarea=t.id_tarea
		WHERE ev.activo=1
		  AND (
		      ev.id_area=?
		      OR t.id_area=?
		      OR (? > 0 AND (ev.id_empleado=? OR tr.id_empleado=?))
		  )
		ORDER BY ev.fecha DESC, ev.id_evento DESC",
		'iiiii',
		array($id_area, $id_area, $id_emp, $id_emp, $id_emp)
	);
}

function crm_evento_guardar($datos)
{
	if(function_exists('crm_asegurar_evento_portal'))
	{
		crm_asegurar_evento_portal();
	}
	$titulo=isset($datos['titulo']) ? trim($datos['titulo']) : '';
	$id_area=isset($datos['id_area']) ? intval($datos['id_area']) : 0;
	if($id_area<=0 && isset($datos['area']))
	{
		$id_area=acceso_id_por_clave('area', $datos['area']);
	}
	if($titulo=='' || $id_area<=0 || !acceso_tabla_existe('evento'))
	{
		return 0;
	}
	$desc=isset($datos['descripcion']) ? trim($datos['descripcion']) : '';
	$tipo=isset($datos['tipo']) && trim($datos['tipo'])!='' ? trim($datos['tipo']) : 'reunion';
	$fecha=isset($datos['fecha']) && $datos['fecha']!='' ? $datos['fecha'] : date('Y-m-d');
	$id_cli=isset($datos['id_cliente']) ? intval($datos['id_cliente']) : 0;
	$id_localidad=isset($datos['id_localidad']) ? intval($datos['id_localidad']) : 0;
	$id_emp=isset($datos['id_empleado']) ? intval($datos['id_empleado']) : crm_id_empleado_sesion();
	$id_tar=isset($datos['id_tarea_origen']) ? intval($datos['id_tarea_origen']) : 0;
	$asis=isset($datos['asistencia']) ? trim($datos['asistencia']) : '';
	$res=isset($datos['resultado']) ? trim($datos['resultado']) : '';
	$auto=isset($datos['autoinscripcion']) && (intval($datos['autoinscripcion'])==1 || $datos['autoinscripcion']==='1') ? 1 : 0;
	$publico=isset($datos['publico']) ? trim($datos['publico']) : 'todos';
	if(!in_array($publico, array('todos','emprendedor','empresa','municipalidad','organismo','persona'), true))
	{
		$publico='todos';
	}
	if($publico==='persona')
	{
		$publico='emprendedor';
	}
	return acceso_insertar(
		"INSERT INTO evento (titulo, descripcion, tipo, fecha, id_area, id_cliente, id_localidad, id_empleado, asistencia, resultado, autoinscripcion, publico)
		VALUES (?,?,?,?,?,NULLIF(?,0),NULLIF(?,0),NULLIF(?,0),?,?,?,?)",
		'ssssiiiissis',
		array($titulo, $desc, $tipo, $fecha, $id_area, $id_cli, $id_localidad, $id_emp, $asis, $res, $auto, $publico)
	);
}

function crm_motivo_texto($motivo)
{
	$t=array('consulta'=>'Consulta','documentacion'=>'Documentación','seguimiento'=>'Seguimiento','tramite'=>'Trámite','otro'=>'Otro');
	return isset($t[$motivo]) ? $t[$motivo] : $motivo;
}

function crm_estado_texto($estado)
{
	$estado=trim((string)$estado);
	$map=array(
		'pendiente'=>'Solicitud pendiente',
		'abierta'=>'A la espera del cliente',
		'en_atencion'=>'Siendo atendido',
		'cerrada'=>'Cerrado',
		'cancelada'=>'Cancelado',
		'derivada'=>'Derivado'
	);
	if(isset($map[$estado]))
	{
		return $map[$estado];
	}
	return ($estado!=='') ? $estado : 'Sin estado';
}

function crm_atencion_url($id_atencion, $area_clave='')
{
	if($area_clave=='')
	{
		$area_clave=acceso_area_pantalla();
	}
	return url_crm($area_clave.'/atencion.php?id='.intval($id_atencion));
}

function crm_area_ve_todo($area_clave='')
{
	if($area_clave=='')
	{
		$area_clave=acceso_area_pantalla();
	}
	// Solo superadmin ve el listado global. Cada área ve únicamente sus atenciones.
	return acceso_es_super();
}

function crm_cliente_puede_ver($id_cliente, $area_clave='')
{
	$id=intval($id_cliente);
	if($id<=0)
	{
		return false;
	}
	if($area_clave=='')
	{
		$area_clave=acceso_area_pantalla();
	}
	// Recepción gestiona clientes y altas, y superadmin ve todo.
	if(crm_area_ve_todo($area_clave) || $area_clave=='recepcion')
	{
		return true;
	}
	$id_area=acceso_id_por_clave('area', $area_clave);
	if($id_area<=0)
	{
		return false;
	}
	if(acceso_consulta_una("SELECT id FROM cliente_area WHERE id_cliente=? AND id_area=? LIMIT 1", 'ii', array($id, $id_area)))
	{
		return true;
	}
	if(acceso_consulta_una("SELECT id_atencion FROM atencion WHERE id_cliente=? AND id_area=? LIMIT 1", 'ii', array($id, $id_area)))
	{
		return true;
	}
	if(acceso_tabla_existe('tarea') && acceso_consulta_una("SELECT id_tarea FROM tarea WHERE id_cliente=? AND id_area=? LIMIT 1", 'ii', array($id, $id_area)))
	{
		return true;
	}
	return false;
}

function crm_atenciones_listado($area_clave='', $opts=array())
{
	if($area_clave=='')
	{
		$area_clave=acceso_area_pantalla();
	}
	$q=isset($opts['q']) ? trim($opts['q']) : (isset($_GET['q']) ? trim($_GET['q']) : '');
	$id_loc=isset($opts['id_localidad']) ? intval($opts['id_localidad']) : (isset($_GET['id_localidad']) ? intval($_GET['id_localidad']) : 0);
	$fecha_filtro=isset($opts['fecha']) ? trim($opts['fecha']) : (isset($_GET['fecha']) ? trim($_GET['fecha']) : '');
	$area_filtro=isset($opts['area']) ? trim($opts['area']) : (isset($_GET['area']) ? trim($_GET['area']) : '');
	$lim=isset($opts['limite']) ? intval($opts['limite']) : 200;
	if($lim<20) { $lim=20; }
	if($lim>500) { $lim=500; }

	$sql="SELECT at.*, c.nombre AS cliente_nombre, c.cuil_cuit, cp.dni AS dni_persona, l.nombre AS localidad_nombre,
			a.clave AS area_clave, a.nombre AS area_nombre
		FROM atencion at
		INNER JOIN cliente c ON c.id_cliente=at.id_cliente
		INNER JOIN area a ON a.id_area=at.id_area
		LEFT JOIN cliente_persona cp ON cp.id_cliente=c.id_cliente
		LEFT JOIN localidad l ON l.id_localidad=c.id_localidad";
	$types='';
	$vals=array();
	$wheres=array();
	if($area_clave==='recepcion')
	{
		// Recepción puede consultar todos los turnos que registró derivados a las distintas áreas.
		// Si se especifica un área particular por filtro, se aplica.
		if($area_filtro!='')
		{
			$wheres[]="a.clave=?";
			$types.='s';
			$vals[]=$area_filtro;
		}
	}
	else if(!crm_area_ve_todo($area_clave) && $area_clave!='')
	{
		// Solo atenciones de esta área (también cerradas/derivadas de cuando el cliente pasó por acá).
		$wheres[]="a.clave=?";
		$types.='s';
		$vals[]=$area_clave;
	}
	if($fecha_filtro==='hoy')
	{
		$wheres[]="DATE(COALESCE(at.fecha_turno, at.fecha))=CURDATE()";
		if($area_clave==='recepcion')
		{
			$wheres[]="at.estado NOT IN ('cancelada')";
		}
	}
	else if($fecha_filtro!='' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro))
	{
		$wheres[]="DATE(COALESCE(at.fecha_turno, at.fecha))=?";
		$types.='s';
		$vals[]=$fecha_filtro;
	}
	if($id_loc>0)
	{
		$wheres[]="c.id_localidad=?";
		$types.='i';
		$vals[]=$id_loc;
	}
	if($q!='')
	{
		$like='%'.$q.'%';
		$qnum=preg_replace('/\D+/', '', $q);
		$wheres[]="(c.nombre LIKE ? OR c.correo LIKE ? OR c.cuil_cuit LIKE ? OR IFNULL(cp.dni,'') LIKE ? OR IFNULL(l.nombre,'') LIKE ?"
			.($qnum!='' ? " OR REPLACE(REPLACE(REPLACE(c.cuil_cuit,'-',''),' ',''),'.','') LIKE ? OR REPLACE(IFNULL(cp.dni,''),'.','') LIKE ?" : "")
			.")";
		$types.='sssss';
		$vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like;
		if($qnum!='')
		{
			$types.='ss';
			$vals[]='%'.$qnum.'%';
			$vals[]='%'.$qnum.'%';
		}
	}
	if(count($wheres)>0)
	{
		$sql.=' WHERE '.implode(' AND ', $wheres);
	}
	$sql.=" ORDER BY FIELD(at.estado,'pendiente','en_atencion','abierta','derivada','cerrada','cancelada'), COALESCE(at.fecha_turno, at.fecha) DESC, at.id_atencion DESC LIMIT ".$lim;
	return acceso_consulta($sql, $types, $vals);
}

function crm_tareas_de_atencion($id_atencion)
{
	$id=intval($id_atencion);
	if($id<=0 || !acceso_tabla_existe('tarea'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT t.id_tarea, t.titulo, t.estado, t.fecha_inicio, a.clave AS area_clave
		FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
		WHERE t.id_atencion=?
		ORDER BY t.id_tarea DESC",
		'i',
		array($id)
	);
}

function crm_tareas_de_cliente($id_cliente)
{
	$id=intval($id_cliente);
	if($id<=0 || !acceso_tabla_existe('tarea'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT t.id_tarea, t.titulo, t.estado, t.fecha_inicio, t.fecha_cierre, t.avance, t.id_atencion,
			a.clave AS area_clave, a.nombre AS area_nombre,
			IFNULL(tt.nombre,'') AS tipo_nombre
		FROM tarea t
		INNER JOIN area a ON a.id_area=t.id_area
		LEFT JOIN tipo_tarea tt ON tt.id_tipo=t.id_tipo
		WHERE t.id_cliente=?
		ORDER BY t.id_tarea DESC",
		'i',
		array($id)
	);
}

function crm_puede_gestionar_atencion($at)
{
	if(!$at)
	{
		return false;
	}
	// Solo el área dueña del turno (abierto o cerrado). Coord. general actúa en su área, no en todas.
	if(acceso_es_super())
	{
		return true;
	}
	$area=acceso_area_pantalla();
	return (isset($at['area_clave']) && $at['area_clave']==$area);
}

function crm_atencion_error_si_no_puede($id_atencion)
{
	$at=crm_atencion_traer($id_atencion);
	if(!$at)
	{
		return 'No se encontró la atención.';
	}
	if(!crm_puede_gestionar_atencion($at))
	{
		return 'Este turno pertenece a otra área. Solo se puede consultar.';
	}
	return '';
}

function crm_tarea_registrar_creacion($id_tarea, $titulo='')
{
	$id_tarea=intval($id_tarea);
	$id_emp=crm_id_empleado_sesion();
	if($id_tarea<=0 || $id_emp<=0 || !acceso_tabla_existe('actividad'))
	{
		return;
	}
	acceso_insertar(
		"INSERT INTO actividad (id_tarea, id_empleado, tipo, descripcion, fecha_actividad) VALUES (?,?,?,?,NOW())",
		'iiss',
		array($id_tarea, $id_emp, 'creada', trim((string)$titulo))
	);
}

function crm_desc_tarea_origen($tipo, $nombre)
{
	$tipo=trim((string)$tipo);
	$nombre=trim((string)$nombre);
	if($nombre==='')
	{
		$nombre='(sin nombre)';
	}
	if(function_exists('mb_substr'))
	{
		$nombre=mb_substr($nombre, 0, 120);
	}
	else
	{
		$nombre=substr($nombre, 0, 120);
	}
	return 'Generada desde: '.$tipo.' '.$nombre.'.';
}

function crm_evento_generar_tarea($id_evento, $titulo, $id_area, $id_tipo, $id_responsable=0)
{
	if(function_exists('crm_asegurar_evento_portal'))
	{
		crm_asegurar_evento_portal();
	}
	$id_evento=intval($id_evento);
	$id_area=intval($id_area);
	$id_tipo=intval($id_tipo);
	$id_responsable=intval($id_responsable);
	$titulo=trim($titulo);
	if($id_evento<=0 || $id_area<=0 || $id_tipo<=0 || $titulo=='')
	{
		return 0;
	}
	// Solo el responsable elegido en el form. NO heredar el de la actividad
	// (si la tarea va a otra área, eso asignaba mal y avisaba a alguien que no tocaba).
	if($id_responsable>0)
	{
		if(!acceso_empleado_por_id($id_responsable) || (function_exists('crm_empleado_pertenece_a_area') && !crm_empleado_pertenece_a_area($id_responsable, $id_area)))
		{
			$id_responsable=0;
		}
	}
	$ev=acceso_consulta_una("SELECT id_cliente, titulo FROM evento WHERE id_evento=? LIMIT 1", 'i', array($id_evento));
	$id_cli=$ev && intval($ev['id_cliente'])>0 ? intval($ev['id_cliente']) : 0;
	$hoy=date('Y-m-d');
	$cierre=date('Y-m-d', strtotime('+7 days'));
	$titulo_origen=$ev && isset($ev['titulo']) ? trim($ev['titulo']) : '';
	if($titulo_origen==='')
	{
		$titulo_origen=$titulo;
	}
	$desc=crm_desc_tarea_origen('Actividad', $titulo_origen);
	if($id_cli>0)
	{
		$id_tarea=acceso_insertar(
			"INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance, id_cliente)
			VALUES (?, ?, ?, ?, 'media', 'no_iniciada', ?, ?, 0, ?)",
			'ssiissi',
			array($titulo, $desc, $id_area, $id_tipo, $hoy, $cierre, $id_cli)
		);
	}
	else
	{
		$id_tarea=acceso_insertar(
			"INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance)
			VALUES (?, ?, ?, ?, 'media', 'no_iniciada', ?, ?, 0)",
			'ssiiss',
			array($titulo, $desc, $id_area, $id_tipo, $hoy, $cierre)
		);
	}
	if($id_tarea<=0)
	{
		return 0;
	}
	crm_tarea_registrar_creacion($id_tarea, $titulo);
	if($id_cli>0)
	{
		crm_vincular_cliente_area($id_cli, $id_area);
	}
	if(acceso_tabla_existe('evento_tarea'))
	{
		$ya=acceso_consulta_una(
			"SELECT id FROM evento_tarea WHERE id_evento=? AND id_tarea=? LIMIT 1",
			'ii',
			array($id_evento, $id_tarea)
		);
		if(!$ya)
		{
			acceso_insertar("INSERT INTO evento_tarea (id_evento, id_tarea) VALUES (?,?)", 'ii', array($id_evento, $id_tarea));
		}
	}
	if($id_responsable>0 && acceso_tabla_existe('tarea_responsable'))
	{
		acceso_insertar(
			"INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?,?,1)",
			'ii',
			array($id_tarea, $id_responsable)
		);
	}
	if(function_exists('crm_aviso_enviar'))
	{
		$area_row=acceso_consulta_una("SELECT clave, nombre FROM area WHERE id_area=? LIMIT 1", 'i', array($id_area));
		$clave_area=$area_row ? $area_row['clave'] : '';
		$nom_area=$area_row ? $area_row['nombre'] : '';
		$url=$clave_area!='' ? url_crm($clave_area.'/ver-tarea.php?id='.$id_tarea) : '';
		$yo=intval(crm_id_empleado_sesion());
		$avisados=array();
		$detalle=$titulo.($nom_area!='' ? ' · '.$nom_area : '');
		// 1) Responsable elegido: siempre recibe aviso.
		if($id_responsable>0)
		{
			crm_aviso_enviar($id_responsable, 'Nueva tarea desde una actividad', $detalle, $url, $id_tarea, 'tarea');
			$avisados[$id_responsable]=1;
		}
		// 2) Coordinadores del área destino (principal + extras).
		foreach(crm_empleados_de_area_id($id_area) as $emp)
		{
			$id_para=intval($emp['id_empleado']);
			$rol=isset($emp['rol_clave']) ? $emp['rol_clave'] : '';
			if($id_para<=0 || isset($avisados[$id_para]))
			{
				continue;
			}
			if($rol==='coordinador' || $rol==='coordinador_general')
			{
				crm_aviso_enviar($id_para, 'Nueva tarea desde una actividad', $detalle, $url, $id_tarea, 'tarea');
				$avisados[$id_para]=1;
			}
		}
		// 3) Sin responsable: avisar a todo el personal del área para que no se pierda.
		if($id_responsable<=0)
		{
			foreach(crm_empleados_de_area_id($id_area) as $emp)
			{
				$id_para=intval($emp['id_empleado']);
				if($id_para<=0 || isset($avisados[$id_para]))
				{
					continue;
				}
				crm_aviso_enviar($id_para, 'Nueva tarea sin responsable (actividad)', $detalle, $url, $id_tarea, 'tarea');
				$avisados[$id_para]=1;
			}
		}
		// 4) Respaldo: si el área no tiene ningún destinatario válido, que el aviso le llegue al superadmin
		if(count($avisados)==0)
		{
			$sup=acceso_consulta_una(
				"SELECT e.id_empleado FROM empleado e
				INNER JOIN rol r ON r.id_rol=e.id_rol
				WHERE r.clave='superadmin' AND e.activo=1
				ORDER BY e.id_empleado ASC LIMIT 1"
			);
			if($sup && intval($sup['id_empleado'])>0)
			{
				$id_sup=intval($sup['id_empleado']);
				crm_aviso_enviar($id_sup, 'Nueva tarea (área sin personal asignado)', $detalle, $url, $id_tarea, 'tarea');
				$avisados[$id_sup]=1;
			}
		}
	}
	return $id_tarea;
}

/** Subtareas activas vinculadas a una tarea padre (id_tarea_padre). */
function crm_tarea_subtareas($id_tarea)
{
	$id=intval($id_tarea);
	if($id<=0 || !acceso_tabla_existe('tarea'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT t.id_tarea, t.titulo, t.estado, t.avance, t.fecha_inicio, t.fecha_cierre, t.id_tarea_padre,
			a.clave AS area_clave, a.nombre AS area_nombre,
			(SELECT GROUP_CONCAT(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) SEPARATOR ', ')
				FROM tarea_responsable tr INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
				WHERE tr.id_tarea=t.id_tarea) AS responsables
		FROM tarea t
		INNER JOIN area a ON a.id_area=t.id_area
		WHERE t.id_tarea_padre=? AND t.estado<>'cancelada'
		ORDER BY t.id_tarea ASC",
		'i',
		array($id)
	);
}

/** Tareas generadas desde una actividad (evento_tarea), incluyendo sus subtareas. */
function crm_evento_tareas($id_evento)
{
	$id=intval($id_evento);
	if($id<=0 || !acceso_tabla_existe('evento_tarea') || !acceso_tabla_existe('tarea'))
	{
		return array();
	}
	$filas=acceso_consulta(
		"SELECT t.id_tarea, t.titulo, t.estado, t.avance, t.fecha_inicio, t.fecha_cierre,
			a.clave AS area_clave, a.nombre AS area_nombre,
			(SELECT GROUP_CONCAT(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) SEPARATOR ', ')
				FROM tarea_responsable tr INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
				WHERE tr.id_tarea=t.id_tarea) AS responsables
		FROM evento_tarea et
		INNER JOIN tarea t ON t.id_tarea=et.id_tarea
		INNER JOIN area a ON a.id_area=t.id_area
		WHERE et.id_evento=? AND (t.id_tarea_padre IS NULL OR t.id_tarea_padre=0) AND t.estado<>'cancelada'
		ORDER BY a.nombre ASC, t.id_tarea ASC",
		'i',
		array($id)
	);
	$salida=array();
	foreach($filas as $f)
	{
		$id_t=intval($f['id_tarea']);
		$f['subtareas']=crm_tarea_subtareas($id_t);
		$salida[]=$f;
	}
	return $salida;
}

/** Asigna (o reemplaza) el responsable principal de una tarea. */
function crm_tarea_asignar_responsable($id_tarea, $id_empleado)
{
	$id_tarea=intval($id_tarea);
	$id_empleado=intval($id_empleado);
	if($id_tarea<=0 || $id_empleado<=0 || !acceso_tabla_existe('tarea_responsable'))
	{
		return 'Indicá la tarea y un responsable.';
	}
	$emp=acceso_consulta_una(
		"SELECT e.id_empleado FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE e.id_empleado=? AND e.activo=1 AND r.clave<>'superadmin' LIMIT 1",
		'i',
		array($id_empleado)
	);
	if(!$emp)
	{
		return 'Ese empleado no es válido.';
	}
	$tar_info=acceso_consulta_una("SELECT id_area FROM tarea WHERE id_tarea=? LIMIT 1", 'i', array($id_tarea));
	if(!$tar_info || intval($tar_info['id_area'])<=0)
	{
		return 'No se encontró la tarea.';
	}
	$id_area_tar=intval($tar_info['id_area']);
	if(function_exists('crm_empleado_pertenece_a_area') && !crm_empleado_pertenece_a_area($id_empleado, $id_area_tar))
	{
		return 'El empleado seleccionado no pertenece al área de la tarea.';
	}
	acceso_ejecutar("DELETE FROM tarea_responsable WHERE id_tarea=?", 'i', array($id_tarea));
	$ok=acceso_insertar(
		"INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?,?,1)",
		'ii',
		array($id_tarea, $id_empleado)
	);
	if($ok<=0)
	{
		return 'No se pudo asignar el responsable.';
	}
	if(function_exists('crm_aviso_enviar'))
	{
		$tar=acceso_consulta_una(
			"SELECT t.titulo, a.clave AS area_clave FROM tarea t INNER JOIN area a ON a.id_area=t.id_area WHERE t.id_tarea=? LIMIT 1",
			'i',
			array($id_tarea)
		);
		if($tar && $id_empleado!=crm_id_empleado_sesion())
		{
			$url=url_crm($tar['area_clave'].'/ver-tarea.php?id='.$id_tarea);
			crm_aviso_enviar($id_empleado, 'Te asignaron una tarea', $tar['titulo'], $url, $id_tarea, 'tarea');
		}
	}
	return '';
}

function crm_puede_gestionar_programas()
{
	// Armar / editar catálogo de programas (pantalla Sistemas / General).
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return true;
	}
	return (acceso_area()=='sistemas' || acceso_area_pantalla()=='general');
}

function crm_puede_anotar_programa()
{
	// Inscribir cliente en un programa: solo coordinadores de área (y el general).
	if(acceso_es_super())
	{
		return true;
	}
	if(function_exists('acceso_es_coord_de_area') && acceso_es_coord_de_area())
	{
		return true;
	}
	return false;
}

function crm_areas_programa()
{
	// Todas las áreas activas desde BD (objetivos/programas no se limitan a las de atención).
	return acceso_etiquetas_area();
}

function crm_asegurar_tablas_programa()
{
	global $con;
	if(!$con)
	{
		return;
	}
	if(!acceso_tabla_existe('eje'))
	{
		@mysqli_query($con, "CREATE TABLE eje (
			id_eje INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_tipo INT UNSIGNED NOT NULL,
			nombre VARCHAR(80) NOT NULL
		) ENGINE=InnoDB");
	}
	if(!acceso_tabla_existe('programa'))
	{
		@mysqli_query($con, "CREATE TABLE programa (
			id_programa INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			nombre VARCHAR(120) NOT NULL,
			descripcion TEXT,
			activo TINYINT(1) NOT NULL DEFAULT 1,
			creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB");
	}
	if(!acceso_tabla_existe('programa_item'))
	{
		@mysqli_query($con, "CREATE TABLE programa_item (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_programa INT UNSIGNED NOT NULL,
			id_area SMALLINT UNSIGNED NOT NULL,
			id_tipo INT UNSIGNED NULL,
			id_eje INT UNSIGNED NULL,
			titulo VARCHAR(200) NOT NULL
		) ENGINE=InnoDB");
	}
	if(!acceso_tabla_existe('cliente_programa'))
	{
		@mysqli_query($con, "CREATE TABLE cliente_programa (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_cliente INT UNSIGNED NOT NULL,
			id_programa INT UNSIGNED NOT NULL,
			fecha_desde DATE NOT NULL,
			fecha_hasta DATE NULL
		) ENGINE=InnoDB");
	}
	if(!acceso_tabla_existe('tarea_modelo'))
	{
		@mysqli_query($con, "CREATE TABLE tarea_modelo (
			id_modelo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_area SMALLINT UNSIGNED NOT NULL,
			id_tipo INT UNSIGNED NOT NULL,
			id_eje INT UNSIGNED NULL,
			titulo VARCHAR(200) NOT NULL
		) ENGINE=InnoDB");
	}
}

function crm_tipo_id_de_nombre($area, $nombre)
{
	$id_area=acceso_id_por_clave('area', $area);
	$nombre=trim($nombre);
	if($id_area<=0 || $nombre=='')
	{
		return 0;
	}
	$row=acceso_consulta_una(
		"SELECT id_tipo FROM tipo_tarea WHERE id_area=? AND LOWER(nombre)=LOWER(?) LIMIT 1",
		'is',
		array($id_area, $nombre)
	);
	return $row ? intval($row['id_tipo']) : 0;
}

function crm_objetivos_con_area()
{
	if(!acceso_tabla_existe('tipo_tarea'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT t.id_tipo, t.nombre, a.clave AS area, a.nombre AS area_nombre
		FROM tipo_tarea t INNER JOIN area a ON a.id_area=t.id_area
		ORDER BY a.nombre, t.nombre"
	);
}

function crm_ejes_mapa()
{
	$salida=array();
	if(!acceso_tabla_existe('eje'))
	{
		return $salida;
	}
	foreach(acceso_consulta("SELECT id_eje, id_tipo, nombre FROM eje ORDER BY nombre") as $row)
	{
		$tid=intval($row['id_tipo']);
		if(!isset($salida[$tid]))
		{
			$salida[$tid]=array();
		}
		$salida[$tid][]=array('id'=>intval($row['id_eje']), 'nombre'=>$row['nombre']);
	}
	return $salida;
}

function crm_ejes_por_area_objetivo()
{
	$salida=array();
	if(!acceso_tabla_existe('eje'))
	{
		return $salida;
	}
	$rows=acceso_consulta(
		"SELECT a.clave AS area, t.nombre AS objetivo, e.nombre AS eje
		FROM eje e
		INNER JOIN tipo_tarea t ON t.id_tipo=e.id_tipo
		INNER JOIN area a ON a.id_area=t.id_area
		ORDER BY a.clave, t.nombre, e.nombre"
	);
	foreach($rows as $row)
	{
		$area=$row['area'];
		$obj=$row['objetivo'];
		if(!isset($salida[$area]))
		{
			$salida[$area]=array();
		}
		if(!isset($salida[$area][$obj]))
		{
			$salida[$area][$obj]=array();
		}
		$salida[$area][$obj][]=$row['eje'];
	}
	return $salida;
}

function crm_eje_cargar_desde_form()
{
	$area=isset($_POST['area_crm']) ? trim($_POST['area_crm']) : acceso_area_pantalla();
	$tipo=isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
	$nombre=isset($_POST['eje_nuevo']) ? trim($_POST['eje_nuevo']) : '';
	$id_tipo=crm_tipo_id_de_nombre($area, $tipo);
	if($id_tipo<=0 || $nombre=='')
	{
		return '';
	}
	$id=crm_eje_cargar($id_tipo, $nombre);
	return $id>0 ? $nombre : '';
}

function crm_tareas_modelo_lista()
{
	crm_asegurar_tablas_programa();
	if(!acceso_tabla_existe('tarea_modelo'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT m.*, a.nombre AS area_nombre, a.clave AS area_clave, t.nombre AS tipo_nombre, e.nombre AS eje_nombre
		FROM tarea_modelo m
		INNER JOIN area a ON a.id_area=m.id_area
		INNER JOIN tipo_tarea t ON t.id_tipo=m.id_tipo
		LEFT JOIN eje e ON e.id_eje=m.id_eje
		ORDER BY a.nombre, t.nombre, m.titulo"
	);
}

function crm_tarea_modelo_guardar($area, $id_tipo, $id_eje, $titulo)
{
	crm_asegurar_tablas_programa();
	$id_area=acceso_id_por_clave('area', $area);
	$id_tipo=intval($id_tipo);
	$titulo=trim($titulo);
	if($id_area<=0 || $id_tipo<=0 || $titulo=='')
	{
		return 'Falta el área, el objetivo o el título.';
	}
	$tipo=acceso_consulta_una("SELECT id_tipo, id_area FROM tipo_tarea WHERE id_tipo=? LIMIT 1", 'i', array($id_tipo));
	if(!$tipo || intval($tipo['id_area'])!=$id_area)
	{
		return 'El objetivo no es de esa área.';
	}
	$ok=acceso_insertar(
		"INSERT INTO tarea_modelo (id_area, id_tipo, titulo) VALUES (?,?,?)",
		'iis',
		array($id_area, $id_tipo, $titulo)
	);
	return $ok>0 ? '' : 'No se pudo guardar la tarea prearmada.';
}

function crm_tarea_modelo_actualizar($id_modelo, $titulo)
{
	crm_asegurar_tablas_programa();
	$id_modelo=intval($id_modelo);
	$titulo=trim($titulo);
	if($id_modelo<=0 || $titulo=='')
	{
		return 'Falta el título de la tarea.';
	}
	$ok=acceso_ejecutar("UPDATE tarea_modelo SET titulo=? WHERE id_modelo=?", 'si', array($titulo, $id_modelo));
	return $ok ? '' : 'No se pudo actualizar la tarea.';
}

function crm_tarea_modelo_eliminar($id_modelo)
{
	crm_asegurar_tablas_programa();
	$id_modelo=intval($id_modelo);
	if($id_modelo<=0)
	{
		return 'Tarea inválida.';
	}
	$ok=acceso_ejecutar("DELETE FROM tarea_modelo WHERE id_modelo=?", 'i', array($id_modelo));
	return $ok ? '' : 'No se pudo eliminar la tarea del catálogo.';
}

function crm_programa_crear($nombre, $desc, $ids_modelo)
{
	crm_asegurar_tablas_programa();
	$nombre=trim($nombre);
	$desc=trim($desc);
	if($nombre=='')
	{
		return 'Ponele un nombre al programa.';
	}
	if(!is_array($ids_modelo) || count($ids_modelo)==0)
	{
		return 'Marcá al menos una tarea para el programa.';
	}
	$ya=acceso_consulta_una("SELECT id_programa FROM programa WHERE nombre=? LIMIT 1", 's', array($nombre));
	if($ya)
	{
		return 'Ya hay un programa con ese nombre.';
	}
	$id=acceso_insertar("INSERT INTO programa (nombre, descripcion, activo) VALUES (?,?,1)", 'ss', array($nombre, $desc));
	if($id<=0)
	{
		return 'No se pudo crear el programa.';
	}
	$msg=crm_programa_guardar_items($id, $ids_modelo);
	return $msg=='' ? '' : $msg;
}

function crm_programa_guardar_items($id_programa, $ids_modelo)
{
	$id_programa=intval($id_programa);
	if($id_programa<=0 || !is_array($ids_modelo))
	{
		return 'Falta el programa o las tareas.';
	}
	foreach($ids_modelo as $idm)
	{
		$idm=intval($idm);
		if($idm<=0)
		{
			continue;
		}
		$mod=acceso_consulta_una("SELECT * FROM tarea_modelo WHERE id_modelo=? LIMIT 1", 'i', array($idm));
		if(!$mod)
		{
			continue;
		}
		$id_tipo=intval($mod['id_tipo']);
		$titulo=$mod['titulo'];
		$id_area=intval($mod['id_area']);
		if($id_tipo>0)
		{
			acceso_insertar(
				"INSERT INTO programa_item (id_programa, id_area, id_tipo, titulo) VALUES (?,?,?,?)",
				'iiis',
				array($id_programa, $id_area, $id_tipo, $titulo)
			);
		}
	}
	return '';
}

function crm_empleado_coord_area($id_area)
{
	$id_area=intval($id_area);
	if($id_area<=0)
	{
		return null;
	}
	$row=acceso_consulta_una(
		"SELECT e.id_empleado, CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS nombre
		FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE e.id_area=? AND r.clave='coordinador' AND e.activo=1
		LIMIT 1",
		'i',
		array($id_area)
	);
	if($row)
	{
		return $row;
	}
	return acceso_consulta_una(
		"SELECT e.id_empleado, CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS nombre
		FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE e.id_area=? AND e.activo=1 AND r.clave<>'superadmin'
		ORDER BY e.id_empleado LIMIT 1",
		'i',
		array($id_area)
	);
}

function crm_programa_aplicar_cliente($id_cliente, $id_programa)
{
	crm_asegurar_tablas_programa();
	$id_cliente=intval($id_cliente);
	$id_programa=intval($id_programa);
	if($id_cliente<=0 || $id_programa<=0)
	{
		return 'Falta el cliente o el programa.';
	}
	$prog=acceso_consulta_una("SELECT * FROM programa WHERE id_programa=? LIMIT 1", 'i', array($id_programa));
	if(!$prog)
	{
		return 'No se encontró el programa.';
	}
	$ya=acceso_consulta_una(
		"SELECT id FROM cliente_programa WHERE id_cliente=? AND id_programa=? AND fecha_hasta IS NULL LIMIT 1",
		'ii',
		array($id_cliente, $id_programa)
	);
	if($ya)
	{
		return 'Ese cliente ya está en este programa.';
	}
	$items=crm_programa_items($id_programa);
	if(count($items)==0)
	{
		return 'Ese programa no tiene tareas marcadas.';
	}
	acceso_insertar(
		"INSERT INTO cliente_programa (id_cliente, id_programa, fecha_desde) VALUES (?,?,CURDATE())",
		'ii',
		array($id_cliente, $id_programa)
	);
	$hoy=date('Y-m-d');
	$cierre=date('Y-m-d', strtotime('+30 days'));
	$hechas=0;
	$de=crm_id_empleado_sesion();
	foreach($items as $it)
	{
		$id_tipo=intval($it['id_tipo']);
		$id_area=intval($it['id_area']);
		$titulo=trim($it['titulo']);
		if($id_tipo<=0 || $id_area<=0 || $titulo=='')
		{
			continue;
		}
		$desc=crm_desc_tarea_origen('Programa', isset($prog['nombre']) ? $prog['nombre'] : '');
		$id_tarea=acceso_insertar(
			"INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance, id_cliente)
			VALUES (?,?,?,?,'media','no_iniciada',?,?,0,?)",
			'ssiissi',
			array($titulo, $desc, $id_area, $id_tipo, $hoy, $cierre, $id_cliente)
		);
		if($id_tarea<=0)
		{
			continue;
		}
		crm_tarea_registrar_creacion($id_tarea, $titulo);
		crm_vincular_cliente_area($id_cliente, $id_area);
		$coord=crm_empleado_coord_area($id_area);
		if($coord)
		{
			acceso_insertar(
				"INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (?,?,1)",
				'ii',
				array($id_tarea, intval($coord['id_empleado']))
			);
			if($de>0 && $de!=intval($coord['id_empleado']) && acceso_tabla_existe('aviso'))
			{
				$pend=acceso_consulta_una(
					"SELECT id_aviso FROM aviso WHERE id_tarea=? AND id_empleado_para=? AND confirmado=0 LIMIT 1",
					'ii',
					array($id_tarea, intval($coord['id_empleado']))
				);
				if(!$pend)
				{
					acceso_insertar(
						"INSERT INTO aviso (id_tarea, id_empleado_de, id_empleado_para) VALUES (?,?,?)",
						'iii',
						array($id_tarea, $de, intval($coord['id_empleado']))
					);
				}
			}
			if(function_exists('tareas_avisos_registrar') && isset($coord['nombre']) && trim($coord['nombre'])!='')
			{
				tareas_avisos_registrar(
					array(trim($coord['nombre'])),
					$titulo,
					$id_tarea,
					isset($it['area_clave']) ? $it['area_clave'] : '',
					array()
				);
			}
		}
		$hechas++;
	}
	if($hechas<=0)
	{
		return 'No se pudieron crear las tareas. Cada ítem tiene que tener objetivo.';
	}
	return '';
}

function crm_programas_de_cliente($id_cliente)
{
	$id=intval($id_cliente);
	if($id<=0 || !acceso_tabla_existe('cliente_programa'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT cp.*, p.nombre
		FROM cliente_programa cp INNER JOIN programa p ON p.id_programa=cp.id_programa
		WHERE cp.id_cliente=? AND cp.fecha_hasta IS NULL
		ORDER BY p.nombre",
		'i',
		array($id)
	);
}

function crm_avisos_tarea_bd_mios()
{
	$yo=crm_id_empleado_sesion();
	if($yo<=0 || !acceso_tabla_existe('aviso'))
	{
		return array();
	}
	$rows=acceso_consulta(
		"SELECT av.id_aviso, av.fecha_aviso, t.titulo, t.id_tarea, t.estado, a.clave AS area,
			CONCAT(IFNULL(ed.nombres,''),' ',IFNULL(ed.apellido,'')) AS de
		FROM aviso av
		INNER JOIN tarea t ON t.id_tarea=av.id_tarea
		INNER JOIN area a ON a.id_area=t.id_area
		LEFT JOIN empleado ed ON ed.id_empleado=av.id_empleado_de
		WHERE av.id_empleado_para=? AND av.confirmado=0
		ORDER BY av.fecha_aviso DESC",
		'i',
		array($yo)
	);
	$salida=array();
	foreach($rows as $row)
	{
		$est = isset($row['estado']) ? strtolower(trim($row['estado'])) : '';
		$tid = intval($row['id_tarea']);

		// Si la tarea fue terminada o cancelada, la alerta está definitivamente resuelta
		if($est === 'terminada' || $est === 'cancelada')
		{
			acceso_ejecutar("UPDATE aviso SET confirmado=1 WHERE id_aviso=?", 'i', array(intval($row['id_aviso'])));
			continue;
		}

		// Si la tarea fue iniciada por este empleado en tarea_inicio o avanzó a en_proceso
		$ya_inicio = false;
		if($tid > 0 && acceso_tabla_existe('tarea_inicio'))
		{
			$ti = acceso_consulta_una("SELECT id_empleado FROM tarea_inicio WHERE id_tarea=? AND id_empleado=? LIMIT 1", 'ii', array($tid, $yo));
			if($ti)
			{
				$ya_inicio = true;
			}
		}

		if($ya_inicio || $est === 'en_proceso')
		{
			acceso_ejecutar("UPDATE aviso SET confirmado=1 WHERE id_aviso=?", 'i', array(intval($row['id_aviso'])));
			continue;
		}

		// IMPORTANTE: Si la tarea está 'pausada', NO se auto-resuelve.
		// Sigue requiriendo atención (reanudar / terminar). Permanece en pendientes.

		$salida[]=array(
			'id'=>intval($row['id_aviso']),
			'titulo'=>$row['titulo'],
			'tarea_id'=>$tid,
			'area'=>$row['area'],
			'de'=>trim($row['de']),
			'fecha_aviso'=>$row['fecha_aviso']
		);
	}
	return $salida;
}

function crm_notificaciones_vistas_ruta()
{
	return BASE_PATH.'/includes/notificaciones-vistas.json';
}

function crm_notificaciones_vistas_leer_disco()
{
	$ruta=crm_notificaciones_vistas_ruta();
	if(!file_exists($ruta))
	{
		return array();
	}
	$contenido=@file_get_contents($ruta);
	if($contenido===false || trim($contenido)==='')
	{
		return array();
	}
	$data=@json_decode($contenido, true);
	return is_array($data) ? $data : array();
}

function crm_notificaciones_vistas_guardar_disco($data)
{
	$ruta=crm_notificaciones_vistas_ruta();
	$json=@json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	if($json!==false)
	{
		@file_put_contents($ruta, $json, LOCK_EX);
	}
}

function crm_notificaciones_vistas_cargar($id_emp=0)
{
	if($id_emp<=0)
	{
		$id_emp=crm_id_empleado_sesion();
	}
	$id_emp=intval($id_emp);
	if($id_emp<=0)
	{
		return array();
	}
	$key_emp=(string)$id_emp;
	if(isset($_SESSION['crm_notif_vistas'][$key_emp]) && is_array($_SESSION['crm_notif_vistas'][$key_emp]))
	{
		return $_SESSION['crm_notif_vistas'][$key_emp];
	}
	$disco=crm_notificaciones_vistas_leer_disco();
	$mis_vistas=isset($disco[$key_emp]) && is_array($disco[$key_emp]) ? $disco[$key_emp] : array();
	if(!isset($_SESSION['crm_notif_vistas']) || !is_array($_SESSION['crm_notif_vistas']))
	{
		$_SESSION['crm_notif_vistas']=array();
	}
	$_SESSION['crm_notif_vistas'][$key_emp]=$mis_vistas;
	return $mis_vistas;
}

function crm_notificacion_es_vista($token_o_tipo, $id=0, $id_emp=0, $entidad_id=0)
{
	if($id_emp<=0)
	{
		$id_emp=crm_id_empleado_sesion();
	}
	$id_emp=intval($id_emp);
	if($id_emp<=0)
	{
		return false;
	}
	$vistas=crm_notificaciones_vistas_cargar($id_emp);
	$tok=trim((string)$token_o_tipo);
	if(strpos($tok, '_')!==false && (intval($id)<=0 && (string)$id==='0'))
	{
		$token=$tok;
	}
	else
	{
		$token=$tok.'_'.$id;
	}
	if(isset($vistas[$token]))
	{
		return true;
	}
	if(intval($entidad_id)>0)
	{
		$tipo_prefix=($pos=strpos($token, '_'))!==false ? substr($token, 0, $pos) : $tok;
		if(isset($vistas[$tipo_prefix.'_ent_'.intval($entidad_id)]))
		{
			return true;
		}
	}
	return false;
}

function crm_notificacion_marcar_vista($token, $id_emp=0)
{
	$token=trim((string)$token);
	if($token==='')
	{
		return;
	}
	if($id_emp<=0)
	{
		$id_emp=crm_id_empleado_sesion();
	}
	$id_emp=intval($id_emp);
	if($id_emp<=0)
	{
		return;
	}
	$key_emp=(string)$id_emp;
	$vistas=crm_notificaciones_vistas_cargar($id_emp);
	if(isset($vistas[$token]))
	{
		return;
	}
	$now=time();
	$vistas[$token]=$now;
	if(!isset($_SESSION['crm_notif_vistas']) || !is_array($_SESSION['crm_notif_vistas']))
	{
		$_SESSION['crm_notif_vistas']=array();
	}
	$_SESSION['crm_notif_vistas'][$key_emp]=$vistas;
	$disco=crm_notificaciones_vistas_leer_disco();
	if(!isset($disco[$key_emp]) || !is_array($disco[$key_emp]))
	{
		$disco[$key_emp]=array();
	}
	$disco[$key_emp][$token]=$now;
	crm_notificaciones_vistas_guardar_disco($disco);
}

function crm_notificacion_marcar_vista_por_entidad($tipo_entidad, $id_entidad, $id_emp=0)
{
	$id_entidad=intval($id_entidad);
	if($id_entidad<=0)
	{
		return;
	}
	if($id_emp<=0)
	{
		$id_emp=crm_id_empleado_sesion();
	}
	$id_emp=intval($id_emp);
	if($id_emp<=0)
	{
		return;
	}
	$tipo_entidad=trim(strtolower($tipo_entidad));
	if($tipo_entidad==='turno')
	{
		if(acceso_tabla_existe('aviso_atencion'))
		{
			$rows=acceso_consulta("SELECT id_aviso FROM aviso_atencion WHERE id_atencion=? AND id_empleado_para=? AND confirmado=0", 'ii', array($id_entidad, $id_emp));
			foreach($rows as $r)
			{
				crm_notificacion_marcar_vista('turno_'.intval($r['id_aviso']), $id_emp);
			}
		}
		crm_notificacion_marcar_vista('turno_ent_'.$id_entidad, $id_emp);
	}
	else if($tipo_entidad==='tarea')
	{
		if(acceso_tabla_existe('aviso'))
		{
			$rows=acceso_consulta("SELECT id_aviso FROM aviso WHERE id_tarea=? AND id_empleado_para=? AND confirmado=0", 'ii', array($id_entidad, $id_emp));
			foreach($rows as $r)
			{
				crm_notificacion_marcar_vista('tarea_'.intval($r['id_aviso']), $id_emp);
			}
		}
		if(acceso_tabla_existe('crm_aviso'))
		{
			$rows=acceso_consulta("SELECT id FROM crm_aviso WHERE id_tarea=? AND id_empleado_para=? AND confirmado=0", 'ii', array($id_entidad, $id_emp));
			foreach($rows as $r)
			{
				crm_notificacion_marcar_vista('crm_'.intval($r['id']), $id_emp);
			}
		}
		crm_notificacion_marcar_vista('tarea_ent_'.$id_entidad, $id_emp);
	}
	else if($tipo_entidad==='actividad')
	{
		if(acceso_tabla_existe('crm_aviso'))
		{
			$url_pat='%actividades.php?id='.$id_entidad.'%';
			$rows=acceso_consulta("SELECT id FROM crm_aviso WHERE id_empleado_para=? AND tipo='actividad' AND url LIKE ? AND confirmado=0", 'is', array($id_emp, $url_pat));
			foreach($rows as $r)
			{
				crm_notificacion_marcar_vista('crm_'.intval($r['id']), $id_emp);
			}
		}
		crm_notificacion_marcar_vista('actividad_ent_'.$id_entidad, $id_emp);
	}
}

function crm_notificacion_vistas_limpiar($id_emp=0)
{
	if($id_emp<=0)
	{
		$id_emp=crm_id_empleado_sesion();
	}
	$id_emp=intval($id_emp);
	if($id_emp<=0)
	{
		return;
	}
	$key_emp=(string)$id_emp;
	if(isset($_SESSION['crm_notif_vistas'][$key_emp]))
	{
		unset($_SESSION['crm_notif_vistas'][$key_emp]);
	}
	$disco=crm_notificaciones_vistas_leer_disco();
	if(isset($disco[$key_emp]))
	{
		unset($disco[$key_emp]);
		crm_notificaciones_vistas_guardar_disco($disco);
	}
}

/**
 * Valida si la URL de una notificacion es accesible para el usuario.
 * Si apunta a un modulo/area donde el usuario no tiene permisos:
 * 1) Intenta redirigir a la actividad de origen (si la tarea nacio de una actividad en su area).
 * 2) Si no hay acceso ni alternativa, devuelve '' para evitar rebotes al dashboard.
 */
function crm_notificacion_resolver_url_accesible($url_base, $id_tarea=0)
{
	$url=trim((string)$url_base);
	if($url==='')
	{
		return '';
	}
	if(function_exists('acceso_es_super') && acceso_es_super())
	{
		return $url;
	}
	$area_url='';
	if(function_exists('acceso_etiquetas_area'))
	{
		$areas=array_keys(acceso_etiquetas_area());
		foreach($areas as $ak)
		{
			if(preg_match('#(?:^|/)(?:public/)?'.preg_quote($ak, '#').'/#i', $url))
			{
				$area_url=$ak;
				break;
			}
		}
	}
	if($area_url!=='' && function_exists('acceso_puede') && !acceso_puede($area_url))
	{
		$id_tar=intval($id_tarea);
		if($id_tar<=0 && preg_match('/[?&]id=(\d+)/', $url, $m))
		{
			$id_tar=intval($m[1]);
		}
		if($id_tar>0 && function_exists('acceso_tabla_existe') && acceso_tabla_existe('evento_tarea') && acceso_tabla_existe('evento'))
		{
			$ev=acceso_consulta_una(
				"SELECT e.id_evento, a.clave AS area_clave
				FROM evento_tarea et
				INNER JOIN evento e ON e.id_evento=et.id_evento
				LEFT JOIN area a ON a.id_area=e.id_area
				WHERE et.id_tarea=? LIMIT 1",
				'i',
				array($id_tar)
			);
			if($ev && !empty($ev['area_clave']) && acceso_puede($ev['area_clave']))
			{
				return url_crm($ev['area_clave'].'/actividades.php?id='.intval($ev['id_evento']));
			}
		}
		return '';
	}
	return $url;
}

function crm_notificacion_url_abrir($url_base, $token_vista, $id_tarea=0)
{
	$url=trim((string)$url_base);
	if($url==='')
	{
		return '';
	}
	$url=crm_notificacion_resolver_url_accesible($url, $id_tarea);
	if($url==='')
	{
		return '';
	}
	$sep=(strpos($url, '?')!==false) ? '&' : '?';
	return $url.$sep.'notif_vista='.urlencode($token_vista);
}

function crm_notificaciones_mias()
{
	// No revisar vencidas en POST de alta de parte (camino caliente).
	if(!(isset($_POST['crear_subtarea']) && isset($_POST['id_tarea'])) && function_exists('crm_tarea_revisar_vencidas'))
	{
		crm_tarea_revisar_vencidas();
	}
	$salida=array();
	$yo=crm_id_empleado_sesion();

	if(function_exists('crm_avisos_atencion_mios'))
	{
		foreach(crm_avisos_atencion_mios() as $av)
		{
			$area=isset($av['area_clave']) ? $av['area_clave'] : acceso_area();
			$cuando=isset($av['fecha_turno']) ? crm_turno_fecha_texto($av['fecha_turno']) : '';
			$motivo=isset($av['motivo']) ? crm_motivo_texto($av['motivo']) : '';
			$id_aviso=intval($av['id_aviso']);
			$id_atencion=intval($av['id_atencion']);
			$ruta_area=(defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)).'/public/'.$area.'/atencion.php';
			if(!is_file($ruta_area))
			{
				$area='recepcion';
			}
			$url=url_crm($area.'/atencion.php?id='.$id_atencion);
			$vista=crm_notificacion_es_vista('turno', $id_aviso, $yo, $id_atencion);

			$salida[]=array(
				'tipo'=>'turno',
				'id'=>$id_aviso,
				'id_entidad'=>$id_atencion,
				'titulo'=>'Nueva atención · '.$av['cliente_nombre'],
				'detalle'=>trim(($cuando!='' ? $cuando : 'Sin fecha').($motivo!='' ? ' · '.$motivo : '').(isset($av['area_nombre']) && $av['area_nombre']!='' ? ' · '.$av['area_nombre'] : '')),
				'url'=>$url,
				'url_abrir'=>crm_notificacion_url_abrir($url, 'turno_'.$id_aviso),
				'campo'=>'cerrar_aviso_atencion',
				'etiqueta'=>'Turno',
				'vista'=>$vista,
				'fecha'=>isset($av['fecha_aviso']) && $av['fecha_aviso']!='' ? $av['fecha_aviso'] : (isset($av['fecha_turno']) ? $av['fecha_turno'] : date('Y-m-d H:i:s'))
			);
		}
	}
	$ya_tarea=array();
	foreach(crm_avisos_tarea_bd_mios() as $av)
	{
		$tid=isset($av['tarea_id']) ? intval($av['tarea_id']) : 0;
		$id_aviso=isset($av['id']) ? intval($av['id']) : 0;
		$ya_tarea[$tid]=1;
		$area=isset($av['area']) ? $av['area'] : acceso_area();
		$de=isset($av['de']) ? trim($av['de']) : '';
		$url=url_crm($area.'/ver-tarea.php?id='.$tid);
		$vista=crm_notificacion_es_vista('tarea', $id_aviso, $yo, $tid);

		$salida[]=array(
			'tipo'=>'tarea',
			'id'=>$id_aviso,
			'id_entidad'=>$tid,
			'titulo'=>isset($av['titulo']) ? $av['titulo'] : 'Tarea',
			'detalle'=>($de!='' ? $de.' te dejó una tarea nueva.' : 'Tenés una tarea nueva asignada.'),
			'url'=>$url,
			'url_abrir'=>crm_notificacion_url_abrir($url, 'tarea_'.$id_aviso),
			'campo'=>'cerrar_aviso',
			'etiqueta'=>'Tarea',
			'vista'=>$vista,
			'fecha'=>isset($av['fecha_aviso']) && $av['fecha_aviso']!='' ? $av['fecha_aviso'] : date('Y-m-d H:i:s')
		);
	}
	if(function_exists('tareas_avisos_mios'))
	{
		foreach(tareas_avisos_mios() as $av)
		{
			$tid=isset($av['tarea_id']) ? intval($av['tarea_id']) : 0;
			if($tid>0 && isset($ya_tarea[$tid]))
			{
				continue;
			}
			$url=function_exists('tareas_aviso_url') ? tareas_aviso_url($av) : url_crm(acceso_area().'/ver-tarea.php?id='.$tid);
			$de=isset($av['de']) ? trim($av['de']) : '';
			$id_av=isset($av['id']) ? $av['id'] : '';
			$vista=crm_notificacion_es_vista('tarea', $id_av, $yo, $tid);

			$salida[]=array(
				'tipo'=>'tarea',
				'id'=>$id_av,
				'id_entidad'=>$tid,
				'titulo'=>isset($av['titulo']) ? $av['titulo'] : 'Tarea',
				'detalle'=>($de!='' ? $de.' te dejó una tarea nueva.' : 'Tenés una tarea nueva asignada.'),
				'url'=>$url,
				'url_abrir'=>crm_notificacion_url_abrir($url, 'tarea_'.$id_av),
				'campo'=>'cerrar_aviso',
				'etiqueta'=>'Tarea',
				'vista'=>$vista,
				'fecha'=>date('Y-m-d H:i:s')
			);
		}
	}
	if(function_exists('crm_avisos_extra_mios'))
	{
		foreach(crm_avisos_extra_mios() as $av)
		{
			$id_crm=isset($av['id']) ? intval($av['id']) : 0;
			$id_ent=isset($av['id_entidad']) ? intval($av['id_entidad']) : (isset($av['id_tarea']) ? intval($av['id_tarea']) : 0);
			$url=isset($av['url']) ? crm_notificacion_resolver_url_accesible($av['url'], $id_ent) : '';
			$vista=crm_notificacion_es_vista('crm', $id_crm, $yo, $id_ent);

			$av['url']=$url;
			$av['vista']=$vista;
			$av['url_abrir']=crm_notificacion_url_abrir($url, 'crm_'.$id_crm, $id_ent);
			$av['fecha']=isset($av['fecha']) && $av['fecha']!='' ? $av['fecha'] : date('Y-m-d H:i:s');
			$salida[]=$av;
		}
	}
	// Siempre ordenar: notificaciones más recientes primero
	usort($salida, function($a, $b) {
		$ta=isset($a['fecha']) && $a['fecha']!='' ? strtotime($a['fecha']) : 0;
		$tb=isset($b['fecha']) && $b['fecha']!='' ? strtotime($b['fecha']) : 0;
		if($ta===$tb)
		{
			$ida=isset($a['id']) ? intval($a['id']) : 0;
			$idb=isset($b['id']) ? intval($b['id']) : 0;
			return $idb - $ida;
		}
		return $tb - $ta;
	});
	return $salida;
}

function crm_notificaciones_contar()
{
	$n=0;
	foreach(crm_notificaciones_mias() as $item)
	{
		if(empty($item['vista']))
		{
			$n++;
		}
	}
	return $n;
}

function crm_aviso_tarea_ok($id)
{
	$id_txt=trim((string)$id);
	if($id_txt==='')
	{
		return;
	}
	if(ctype_digit($id_txt) && acceso_tabla_existe('aviso'))
	{
		$yo=crm_id_empleado_sesion();
		if($yo>0)
		{
			acceso_ejecutar("UPDATE aviso SET confirmado=1 WHERE id_aviso=? AND id_empleado_para=?", 'ii', array(intval($id_txt), $yo));
		}
	}
	if(function_exists('tareas_aviso_aceptar'))
	{
		tareas_aviso_aceptar($id_txt);
	}
}

function crm_aviso_tarea_marcar_leido_por_tarea($id_tarea, $id_empleado = 0)
{
	global $con;
	$id = intval($id_tarea);
	if($id <= 0) { return; }
	if($id_empleado <= 0 && function_exists('crm_id_empleado_sesion'))
	{
		$id_empleado = crm_id_empleado_sesion();
	}
	$emp = intval($id_empleado);
	if($con)
	{
		if($emp > 0)
		{
			@mysqli_query($con, "UPDATE aviso SET confirmado=1 WHERE id_tarea=$id AND (id_empleado_para=$emp OR id_empleado_para=0)");
		}
		else
		{
			@mysqli_query($con, "UPDATE aviso SET confirmado=1 WHERE id_tarea=$id");
		}
		$chk_crm = @mysqli_query($con, "SHOW TABLES LIKE 'crm_aviso'");
		if($chk_crm && @mysqli_num_rows($chk_crm) > 0)
		{
			if($emp > 0)
			{
				@mysqli_query($con, "UPDATE crm_aviso SET confirmado=1 WHERE id_tarea=$id AND (id_empleado_para=$emp OR id_empleado_para=0)");
			}
			else
			{
				@mysqli_query($con, "UPDATE crm_aviso SET confirmado=1 WHERE id_tarea=$id");
			}
		}
	}
	else if(function_exists('acceso_ejecutar'))
	{
		if($emp > 0)
		{
			acceso_ejecutar("UPDATE aviso SET confirmado=1 WHERE id_tarea=? AND (id_empleado_para=? OR id_empleado_para=0)", 'ii', array($id, $emp));
		}
		else
		{
			acceso_ejecutar("UPDATE aviso SET confirmado=1 WHERE id_tarea=?", 'i', array($id));
		}
	}
	if(function_exists('tareas_avisos_leer') && function_exists('tareas_avisos_guardar'))
	{
		$todos = tareas_avisos_leer();
		$cambio = false;
		if(is_array($todos))
		{
			foreach($todos as $persona => $lista)
			{
				if(!is_array($lista)) { continue; }
				$nueva = array();
				foreach($lista as $av)
				{
					if(isset($av['tarea_id']) && intval($av['tarea_id']) === $id)
					{
						$cambio = true;
					}
					else
					{
						$nueva[] = $av;
					}
				}
				$todos[$persona] = $nueva;
			}
			if($cambio)
			{
				tareas_avisos_guardar($todos);
			}
		}
	}
}

function crm_notificaciones_marcar_todas()
{
	$yo=crm_id_empleado_sesion();
	if($yo<=0)
	{
		return;
	}
	if(acceso_tabla_existe('aviso_atencion'))
	{
		acceso_ejecutar("UPDATE aviso_atencion SET confirmado=1 WHERE id_empleado_para=? AND confirmado=0", 'i', array($yo));
	}
	if(acceso_tabla_existe('aviso'))
	{
		acceso_ejecutar("UPDATE aviso SET confirmado=1 WHERE id_empleado_para=? AND confirmado=0", 'i', array($yo));
	}
	if(acceso_tabla_existe('crm_aviso'))
	{
		acceso_ejecutar("UPDATE crm_aviso SET confirmado=1 WHERE id_empleado_para=? AND confirmado=0", 'i', array($yo));
	}
	// Avisos viejos en JSON de sesión: vaciar bandeja del usuario actual.
	if(function_exists('tareas_avisos_leer') && function_exists('tareas_avisos_guardar'))
	{
		$yo_nom=isset($_SESSION['tareas_persona']) ? trim($_SESSION['tareas_persona']) : '';
		if($yo_nom=='' && function_exists('acceso_nombre'))
		{
			$yo_nom=trim(acceso_nombre());
		}
		if($yo_nom!='')
		{
			$todos=tareas_avisos_leer();
			if(!is_array($todos))
			{
				$todos=array();
			}
			$todos[$yo_nom]=array();
			tareas_avisos_guardar($todos);
		}
	}
	if(function_exists('crm_notificacion_vistas_limpiar'))
	{
		crm_notificacion_vistas_limpiar($yo);
	}
}

function crm_notificaciones_destino_volver($back)
{
	$back=trim((string)$back);
	$base=defined('BASE_URL') ? BASE_URL : '';
	// Solo rutas internas del CRM (evitar redirect abierto / vacío).
	if($back!='' && strpos($back, "\n")===false && strpos($back, "\r")===false && strpos($back, 'http')!==0 && strpos($back, '//')!==0)
	{
		if($base=='' || strpos($back, $base)===0 || $back[0]==='/')
		{
			return $back;
		}
	}
	if(isset($_SERVER['REQUEST_URI']) && trim($_SERVER['REQUEST_URI'])!='' && strpos($_SERVER['REQUEST_URI'], 'http')!==0)
	{
		return $_SERVER['REQUEST_URI'];
	}
	$script=isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
	if($script!='' && $base!='' && strpos($script, $base)===0)
	{
		return $script;
	}
	return url_crm('admin/notificaciones.php');
}

function crm_notificaciones_procesar()
{
	if(function_exists('crm_es_staff') && !crm_es_staff())
	{
		return;
	}
	if(isset($_GET['notif_vista']) && trim($_GET['notif_vista'])!=='')
	{
		if(function_exists('crm_notificacion_marcar_vista'))
		{
			crm_notificacion_marcar_vista($_GET['notif_vista']);
		}
	}
	$hubo=false;
	if(isset($_POST['cerrar_aviso_atencion']))
	{
		crm_aviso_atencion_ok($_POST['cerrar_aviso_atencion']);
		$hubo=true;
	}
	if(isset($_POST['cerrar_aviso']))
	{
		crm_aviso_tarea_ok($_POST['cerrar_aviso']);
		$hubo=true;
	}
	if(isset($_POST['cerrar_crm_aviso']) && function_exists('crm_aviso_extra_ok'))
	{
		crm_aviso_extra_ok($_POST['cerrar_crm_aviso']);
		$hubo=true;
	}
	if(isset($_POST['notif_marcar_todas']))
	{
		crm_notificaciones_marcar_todas();
		$hubo=true;
	}
	if(!$hubo)
	{
		return;
	}
	$back=crm_notificaciones_destino_volver(isset($_POST['notif_volver']) ? $_POST['notif_volver'] : '');
	while(ob_get_level()>0)
	{
		@ob_end_clean();
	}
	if(!headers_sent())
	{
		header('Location: '.$back);
		exit();
	}
	$safe=htmlspecialchars($back, ENT_QUOTES, 'UTF-8');
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='.$safe.'"><title>Redirigiendo…</title></head><body><p><a href="'.$safe.'">Continuar</a></p></body></html>';
	exit();
}

// Historial: leídas y sin leer de las tres bandejas (turnos, tareas nuevas y avisos del flujo).
function crm_notificaciones_historial($limite=150)
{
	$yo=crm_id_empleado_sesion();
	$salida=array();
	if($yo<=0)
	{
		return $salida;
	}
	if(acceso_tabla_existe('crm_aviso'))
	{
		foreach(acceso_consulta(
			"SELECT id, id_tarea, tipo, titulo, detalle, url, confirmado, fecha FROM crm_aviso
			WHERE id_empleado_para=? ORDER BY fecha DESC LIMIT ".intval($limite),
			'i',
			array($yo)
		) as $r)
		{
			$id_tar=isset($r['id_tarea']) ? intval($r['id_tarea']) : 0;
			$url_res=crm_notificacion_resolver_url_accesible($r['url'], $id_tar);
			$salida[]=array(
				'etiqueta'=>crm_aviso_etiqueta_tipo($r['tipo']),
				'titulo'=>$r['titulo'],
				'detalle'=>(string)$r['detalle'],
				'url'=>$url_res,
				'leida'=>intval($r['confirmado'])==1,
				'fecha'=>$r['fecha']
			);
		}
	}
	if(acceso_tabla_existe('aviso'))
	{
		foreach(acceso_consulta(
			"SELECT av.confirmado, av.fecha_aviso, t.titulo, t.id_tarea, a.clave AS area,
				CONCAT(IFNULL(ed.nombres,''),' ',IFNULL(ed.apellido,'')) AS de
			FROM aviso av
			INNER JOIN tarea t ON t.id_tarea=av.id_tarea
			INNER JOIN area a ON a.id_area=t.id_area
			LEFT JOIN empleado ed ON ed.id_empleado=av.id_empleado_de
			WHERE av.id_empleado_para=? ORDER BY av.fecha_aviso DESC LIMIT ".intval($limite),
			'i',
			array($yo)
		) as $r)
		{
			$de=trim($r['de']);
			$salida[]=array(
				'etiqueta'=>'Tarea',
				'titulo'=>$r['titulo'],
				'detalle'=>$de!='' ? $de.' te asignó esta tarea.' : 'Tarea asignada.',
				'url'=>url_crm($r['area'].'/ver-tarea.php?id='.intval($r['id_tarea'])),
				'leida'=>intval($r['confirmado'])==1,
				'fecha'=>$r['fecha_aviso']
			);
		}
	}
	if(acceso_tabla_existe('aviso_atencion'))
	{
		foreach(acceso_consulta(
			"SELECT av.confirmado, av.fecha_aviso, at.id_atencion, at.estado, c.nombre AS cliente_nombre, a.clave AS area_clave, a.nombre AS area_nombre,
				COALESCE(at.fecha_turno, at.fecha) AS fecha_turno
			FROM aviso_atencion av
			INNER JOIN atencion at ON at.id_atencion=av.id_atencion
			INNER JOIN cliente c ON c.id_cliente=at.id_cliente
			INNER JOIN area a ON a.id_area=at.id_area
			WHERE av.id_empleado_para=? ORDER BY av.fecha_aviso DESC LIMIT ".intval($limite),
			'i',
			array($yo)
		) as $r)
		{
			$salida[]=array(
				'etiqueta'=>'Turno',
				'titulo'=>'Turno · '.$r['cliente_nombre'],
				'detalle'=>crm_turno_fecha_texto($r['fecha_turno']).' · '.$r['area_nombre'].' · '.crm_estado_texto($r['estado']),
				'url'=>url_crm($r['area_clave'].'/atencion.php?id='.intval($r['id_atencion'])),
				'leida'=>intval($r['confirmado'])==1,
				'fecha'=>$r['fecha_aviso']
			);
		}
	}
	usort($salida, function($a, $b) { return strcmp((string)$b['fecha'], (string)$a['fecha']); });
	return array_slice($salida, 0, $limite);
}

function crm_objetivo_guardar_multi($nombre, $areas_claves)
{
	$nombre=trim($nombre);
	if($nombre=='')
	{
		return 'Escribí el nombre del objetivo.';
	}
	if(!is_array($areas_claves) || count($areas_claves)==0)
	{
		return 'Marcá al menos un área.';
	}
	$ok=0;
	foreach($areas_claves as $clave)
	{
		$clave=trim($clave);
		if($clave=='')
		{
			continue;
		}
		if(tareas_objetivo_guardar($clave, $nombre)!='')
		{
			$ok++;
		}
	}
	return $ok>0 ? '' : 'No se pudo guardar el objetivo.';
}

/** Suma áreas a un objetivo ya existente (mismo nombre). */
function crm_objetivo_sumar_areas($nombre, $areas_claves)
{
	return crm_objetivo_guardar_multi($nombre, $areas_claves);
}

function crm_objetivos_agrupados()
{
	$salida=array();
	if(!acceso_tabla_existe('tipo_tarea'))
	{
		return $salida;
	}
	$rows=acceso_consulta(
		"SELECT t.id_tipo, t.nombre, a.clave AS area, a.nombre AS area_nombre
		FROM tipo_tarea t INNER JOIN area a ON a.id_area=t.id_area
		ORDER BY t.nombre, a.nombre"
	);
	foreach($rows as $row)
	{
		$key=strtolower(trim($row['nombre']));
		if(!isset($salida[$key]))
		{
			$salida[$key]=array(
				'nombre'=>$row['nombre'],
				'areas'=>array(),
				'por_area'=>array()
			);
		}
		$salida[$key]['areas'][$row['area']]=$row['area_nombre'];
		$salida[$key]['por_area'][$row['area']]=intval($row['id_tipo']);
	}
	return array_values($salida);
}

function crm_tareas_modelo_por_objetivo()
{
	$salida=array();
	foreach(crm_tareas_modelo_lista() as $m)
	{
		$obj=isset($m['tipo_nombre']) ? $m['tipo_nombre'] : '';
		if($obj=='')
		{
			$obj='Sin objetivo';
		}
		if(!isset($salida[$obj]))
		{
			$salida[$obj]=array();
		}
		$salida[$obj][]=$m;
	}
	return $salida;
}

function crm_sembrar_programas_emprendedores()
{
	// Solo si no hay programas: evita pisar la carga de demo / datos reales.
	crm_asegurar_tablas_programa();
	$hay=acceso_consulta_una("SELECT id_programa FROM programa LIMIT 1");
	if($hay)
	{
		return;
	}
	$id_area=acceso_id_por_clave('area', 'emprendedores');
	if($id_area<=0)
	{
		return;
	}
	$packs=array(
		array('objetivo'=>'Acelerar', 'tarea'=>'Plan de marketing'),
		array('objetivo'=>'Origen Misiones', 'tarea'=>'Ingreso de productos OM'),
		array('objetivo'=>'Código de Barras', 'tarea'=>'Requisitos de carga')
	);
	$ids=array();
	foreach($packs as $pack)
	{
		tareas_objetivo_guardar('emprendedores', $pack['objetivo']);
		$id_tipo=crm_tipo_id_de_nombre('emprendedores', $pack['objetivo']);
		if($id_tipo<=0)
		{
			continue;
		}
		$ya_m=acceso_consulta_una(
			"SELECT id_modelo FROM tarea_modelo WHERE id_area=? AND id_tipo=? AND titulo=? LIMIT 1",
			'iis',
			array($id_area, $id_tipo, $pack['tarea'])
		);
		if($ya_m)
		{
			$ids[]=intval($ya_m['id_modelo']);
			continue;
		}
		$mid=acceso_insertar(
			"INSERT INTO tarea_modelo (id_area, id_tipo, titulo) VALUES (?,?,?)",
			'iis',
			array($id_area, $id_tipo, $pack['tarea'])
		);
		if($mid>0)
		{
			$ids[]=$mid;
		}
	}
	if(count($ids)>0)
	{
		crm_programa_crear('Programa 1', 'Ejemplo: un programa con tres objetivos y una tarea en cada uno.', $ids);
	}
}

/**
 * Valida si un empleado pertenece al área indicada (por rol principal o rol adicional activo).
 */
function crm_empleado_pertenece_a_area($id_empleado, $id_area)
{
	$id_emp=intval($id_empleado);
	$id_ar=intval($id_area);
	if($id_emp<=0 || $id_ar<=0)
	{
		return false;
	}
	$p=acceso_consulta_una(
		"SELECT e.id_empleado FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE e.id_empleado=? AND e.id_area=? AND e.activo=1 AND r.clave<>'superadmin' LIMIT 1",
		'ii',
		array($id_emp, $id_ar)
	);
	if($p && intval($p['id_empleado'])>0)
	{
		return true;
	}
	if(acceso_tabla_existe('empleado_rol_adicional'))
	{
		$x=acceso_consulta_una(
			"SELECT x.id_empleado FROM empleado_rol_adicional x
			INNER JOIN empleado e ON e.id_empleado=x.id_empleado
			INNER JOIN rol r ON r.id_rol=x.id_rol
			WHERE x.id_empleado=? AND x.id_area=? AND x.fecha_hasta IS NULL AND e.activo=1 AND r.clave<>'superadmin' LIMIT 1",
			'ii',
			array($id_emp, $id_ar)
		);
		if($x && intval($x['id_empleado'])>0)
		{
			return true;
		}
	}
	return false;
}

/**
 * Retorna información detallada de la actividad de origen de una tarea (o subtarea).
 * Resuelve vínculos vía evento_tarea, tareas padre y respaldo por descripción.
 */
function crm_tarea_actividad_origen_info($id_tarea)
{
	$id=intval($id_tarea);
	if($id<=0)
	{
		return null;
	}

	// 1) Vínculo directo en evento_tarea
	$row=acceso_consulta_una(
		"SELECT e.id_evento, e.titulo AS evento_titulo, e.id_area AS evento_id_area,
				ea.clave AS evento_area_clave, ea.nombre AS evento_area_nombre,
				t.id_area AS tarea_id_area, ta.clave AS tarea_area_clave, ta.nombre AS tarea_area_nombre,
				t.descripcion AS tarea_descripcion
		 FROM evento_tarea et
		 INNER JOIN evento e ON e.id_evento=et.id_evento
		 INNER JOIN area ea ON ea.id_area=e.id_area
		 INNER JOIN tarea t ON t.id_tarea=et.id_tarea
		 INNER JOIN area ta ON ta.id_area=t.id_area
		 WHERE et.id_tarea=? LIMIT 1",
		'i',
		array($id)
	);

	// 2) Si es subtarea, buscar por la tarea padre
	if(!$row)
	{
		$row=acceso_consulta_una(
			"SELECT e.id_evento, e.titulo AS evento_titulo, e.id_area AS evento_id_area,
					ea.clave AS evento_area_clave, ea.nombre AS evento_area_nombre,
					t.id_area AS tarea_id_area, ta.clave AS tarea_area_clave, ta.nombre AS tarea_area_nombre,
					t.descripcion AS tarea_descripcion
			 FROM tarea sub
			 INNER JOIN evento_tarea et ON et.id_tarea=sub.id_tarea_padre
			 INNER JOIN evento e ON e.id_evento=et.id_evento
			 INNER JOIN area ea ON ea.id_area=e.id_area
			 INNER JOIN tarea t ON t.id_tarea=sub.id_tarea
			 INNER JOIN area ta ON ta.id_area=t.id_area
			 WHERE sub.id_tarea=? LIMIT 1",
			'i',
			array($id)
		);
	}

	// 3) Respaldo si el evento fue eliminado físicamente pero figura en la descripción
	if(!$row)
	{
		$tar_solo=acceso_consulta_una(
			"SELECT t.id_tarea, t.descripcion AS tarea_descripcion, t.id_area AS tarea_id_area,
					ta.clave AS tarea_area_clave, ta.nombre AS tarea_area_nombre
			 FROM tarea t
			 INNER JOIN area ta ON ta.id_area=t.id_area
			 WHERE t.id_tarea=? LIMIT 1",
			'i',
			array($id)
		);
		if($tar_solo && !empty($tar_solo['tarea_descripcion']) && preg_match('/Generada desde:\s*(?:Actividad\s+)?(.+?)(?:\.|$)/iu', $tar_solo['tarea_descripcion'], $m))
		{
			$nom_ev=trim($m[1]);
			$ev_match=acceso_consulta_una(
				"SELECT e.id_evento, e.titulo AS evento_titulo, e.id_area AS evento_id_area,
						ea.clave AS evento_area_clave, ea.nombre AS evento_area_nombre
				 FROM evento e
				 INNER JOIN area ea ON ea.id_area=e.id_area
				 WHERE e.titulo=? LIMIT 1",
				's',
				array($nom_ev)
			);
			$row=array(
				'id_evento'=>$ev_match ? intval($ev_match['id_evento']) : 0,
				'evento_titulo'=>$ev_match ? $ev_match['evento_titulo'] : $nom_ev,
				'evento_id_area'=>$ev_match ? intval($ev_match['evento_id_area']) : 0,
				'evento_area_clave'=>$ev_match ? $ev_match['evento_area_clave'] : '',
				'evento_area_nombre'=>$ev_match ? $ev_match['evento_area_nombre'] : '',
				'tarea_id_area'=>intval($tar_solo['tarea_id_area']),
				'tarea_area_clave'=>$tar_solo['tarea_area_clave'],
				'tarea_area_nombre'=>$tar_solo['tarea_area_nombre'],
				'tarea_descripcion'=>$tar_solo['tarea_descripcion']
			);
		}
	}

	if(!$row)
	{
		return null;
	}

	// Normalizar etiquetas legibles
	if(function_exists('acceso_etiquetas_area'))
	{
		$etq=acceso_etiquetas_area();
		if(!empty($row['evento_area_clave']) && isset($etq[$row['evento_area_clave']]))
		{
			$row['evento_area_nombre']=$etq[$row['evento_area_clave']];
		}
		if(!empty($row['tarea_area_clave']) && isset($etq[$row['tarea_area_clave']]))
		{
			$row['tarea_area_nombre']=$etq[$row['tarea_area_clave']];
		}
	}

	return $row;
}

