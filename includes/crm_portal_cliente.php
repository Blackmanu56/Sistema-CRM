<?php
// Portal del cliente: turnos, programas, actividades e inscripción.

function crm_asegurar_evento_portal()
{
	global $con;
	static $listo=false;
	if($listo || !$con)
	{
		return;
	}
	$listo=true;

	if(!acceso_tabla_existe('evento'))
	{
		return;
	}
	// Siempre asegurar el vínculo actividad↔tarea (aunque el resto del schema ya esté marcado).
	if(!acceso_tabla_existe('evento_tarea'))
	{
		@mysqli_query($con, "CREATE TABLE evento_tarea (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_evento INT UNSIGNED NOT NULL,
			id_tarea INT UNSIGNED NOT NULL,
			UNIQUE KEY uq_evtar (id_evento, id_tarea),
			INDEX idx_evtar_evento (id_evento),
			INDEX idx_evtar_tarea (id_tarea)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
	if(crm_schema_al_dia('evento_v4'))
	{
		return;
	}
	$q=@mysqli_query($con, "SHOW COLUMNS FROM evento LIKE 'autoinscripcion'");
	if($q && mysqli_num_rows($q)==0)
	{
		@mysqli_query($con, "ALTER TABLE evento ADD COLUMN autoinscripcion TINYINT(1) NOT NULL DEFAULT 0 AFTER resultado");
	}
	$q2=@mysqli_query($con, "SHOW COLUMNS FROM evento LIKE 'publico'");
	if($q2 && mysqli_num_rows($q2)==0)
	{
		@mysqli_query($con, "ALTER TABLE evento ADD COLUMN publico ENUM('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos' AFTER autoinscripcion");
	}
	else if($q2 && mysqli_num_rows($q2)>0)
	{
		$col=mysqli_fetch_assoc($q2);
		$tipo_col=isset($col['Type']) ? $col['Type'] : '';
		if(strpos($tipo_col, "'persona'")!==false)
		{
			@mysqli_query($con, "UPDATE evento SET publico='emprendedor' WHERE publico='persona'");
			@mysqli_query($con, "ALTER TABLE evento MODIFY publico ENUM('todos','emprendedor','empresa','municipalidad','organismo') NOT NULL DEFAULT 'todos'");
		}
	}
	$q3=@mysqli_query($con, "SHOW COLUMNS FROM evento LIKE 'organismo'");
	if($q3 && mysqli_num_rows($q3)==0)
	{
		@mysqli_query($con, "ALTER TABLE evento ADD COLUMN organismo VARCHAR(200) NULL AFTER id_cliente");
	}
	if(!acceso_tabla_existe('evento_inscripcion'))
	{
		@mysqli_query($con, "CREATE TABLE evento_inscripcion (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_evento INT UNSIGNED NOT NULL,
			id_cliente INT UNSIGNED NOT NULL,
			estado ENUM('inscrito','asistio','ausente') NOT NULL DEFAULT 'inscrito',
			fecha_inscripcion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uq_evins (id_evento, id_cliente),
			INDEX idx_evins_cliente (id_cliente, estado)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
	$q4=@mysqli_query($con, "SHOW COLUMNS FROM evento LIKE 'activo'");
	if($q4 && mysqli_num_rows($q4)==0)
	{
		@mysqli_query($con, "ALTER TABLE evento ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1");
	}
	crm_schema_marcar('evento_v4');
}

function crm_portal_id_cliente()
{
	$cta=function_exists('acceso_cliente_cuenta') ? acceso_cliente_cuenta() : null;
	return $cta ? intval($cta['id']) : 0;
}

function crm_portal_tipo_cliente()
{
	$cta=function_exists('acceso_cliente_cuenta') ? acceso_cliente_cuenta() : null;
	if(!$cta)
	{
		return '';
	}
	if(isset($cta['tipo_cliente']) && $cta['tipo_cliente']!='')
	{
		return cliente_tipo_normalizar($cta['tipo_cliente']);
	}
	return ($cta['tipo']=='empresa') ? 'empresa' : 'emprendedor';
}

function crm_evento_publico_ok($publico, $tipo_cliente)
{
	$publico=trim((string)$publico);
	if($publico==='' || $publico==='todos')
	{
		return true;
	}
	if($publico==='persona')
	{
		$publico='emprendedor';
	}
	$tipo=cliente_tipo_normalizar($tipo_cliente);
	return ($publico===$tipo);
}

function crm_cliente_mis_turnos($id_cliente=0)
{
	$id=$id_cliente>0 ? intval($id_cliente) : crm_portal_id_cliente();
	if($id<=0)
	{
		return array();
	}
	return crm_atenciones_de($id);
}

function crm_cliente_programas_disponibles()
{
	crm_asegurar_tablas_programa();
	if(!acceso_tabla_existe('programa'))
	{
		return array();
	}
	return acceso_consulta("SELECT * FROM programa WHERE activo=1 ORDER BY nombre");
}

function crm_cliente_mis_programas($id_cliente=0)
{
	$id=$id_cliente>0 ? intval($id_cliente) : crm_portal_id_cliente();
	return crm_programas_de_cliente($id);
}

function crm_cliente_inscripcion_de($id_evento, $id_cliente)
{
	crm_asegurar_evento_portal();
	$id_evento=intval($id_evento);
	$id_cliente=intval($id_cliente);
	if($id_evento<=0 || $id_cliente<=0 || !acceso_tabla_existe('evento_inscripcion'))
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT * FROM evento_inscripcion WHERE id_evento=? AND id_cliente=? LIMIT 1",
		'ii',
		array($id_evento, $id_cliente)
	);
}

function crm_cliente_actividades_proximas($id_cliente=0)
{
	crm_asegurar_evento_portal();
	$id=$id_cliente>0 ? intval($id_cliente) : crm_portal_id_cliente();
	$tipo=crm_portal_tipo_cliente();
	if($id<=0 || !acceso_tabla_existe('evento'))
	{
		return array();
	}
	$rows=acceso_consulta(
		"SELECT ev.*, a.nombre AS area_nombre, a.clave AS area_clave,
			ei.estado AS mi_estado, ei.id AS id_inscripcion
		FROM evento ev
		INNER JOIN area a ON a.id_area=ev.id_area
		LEFT JOIN evento_inscripcion ei ON ei.id_evento=ev.id_evento AND ei.id_cliente=?
		WHERE ev.fecha >= CURDATE() AND ev.activo=1
			AND (
				ei.id IS NOT NULL
				OR (ev.autoinscripcion=1 AND (ev.publico='todos' OR ev.publico=?))
				OR ev.id_cliente=?
			)
		ORDER BY ev.fecha ASC, ev.id_evento ASC",
		'isi',
		array($id, $tipo, $id)
	);
	$salida=array();
	foreach($rows as $r)
	{
		if(isset($r['mi_estado']) && $r['mi_estado']!='')
		{
			$salida[]=$r;
			continue;
		}
		if(intval($r['id_cliente'])===$id)
		{
			$salida[]=$r;
			continue;
		}
		if(intval($r['autoinscripcion'])==1 && crm_evento_publico_ok(isset($r['publico']) ? $r['publico'] : 'todos', $tipo))
		{
			$salida[]=$r;
		}
	}
	return $salida;
}

function crm_cliente_actividades_asistidas($id_cliente=0)
{
	crm_asegurar_evento_portal();
	$id=$id_cliente>0 ? intval($id_cliente) : crm_portal_id_cliente();
	if($id<=0 || !acceso_tabla_existe('evento_inscripcion'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT ev.*, a.nombre AS area_nombre, ei.estado AS mi_estado, ei.fecha_inscripcion
		FROM evento_inscripcion ei
		INNER JOIN evento ev ON ev.id_evento=ei.id_evento
		INNER JOIN area a ON a.id_area=ev.id_area
		WHERE ei.id_cliente=? AND ei.estado='asistio' AND ev.activo=1
		ORDER BY ev.fecha DESC, ev.id_evento DESC",
		'i',
		array($id)
	);
}

function crm_cliente_recomendaciones($id_cliente=0)
{
	crm_asegurar_evento_portal();
	$id=$id_cliente>0 ? intval($id_cliente) : crm_portal_id_cliente();
	$tipo=crm_portal_tipo_cliente();
	if($id<=0 || !acceso_tabla_existe('evento'))
	{
		return array();
	}
	$rows=acceso_consulta(
		"SELECT ev.*, a.nombre AS area_nombre
		FROM evento ev
		INNER JOIN area a ON a.id_area=ev.id_area
		LEFT JOIN evento_inscripcion ei ON ei.id_evento=ev.id_evento AND ei.id_cliente=?
		WHERE ev.fecha >= CURDATE() AND ev.activo=1
			AND ev.autoinscripcion=1
			AND ei.id IS NULL
			AND (ev.publico='todos' OR ev.publico=?)
		ORDER BY
			CASE WHEN ev.publico=? THEN 0 ELSE 1 END,
			ev.fecha ASC
		LIMIT 8",
		'iss',
		array($id, $tipo, $tipo)
	);
	$salida=array();
	foreach($rows as $r)
	{
		$motivo='Hay una actividad próxima a la que te podés inscribir.';
		$pub=isset($r['publico']) ? $r['publico'] : 'todos';
		if($pub==='persona') { $pub='emprendedor'; }
		if($pub!=='todos' && $pub===$tipo)
		{
			$motivo='Hay una actividad pensada para tu tipo de cliente.';
		}
		$r['motivo_reco']=$motivo;
		$salida[]=$r;
	}
	return $salida;
}

function crm_cliente_inscribir_actividad($id_evento, $id_cliente=0)
{
	crm_asegurar_evento_portal();
	$id_evento=intval($id_evento);
	$id=$id_cliente>0 ? intval($id_cliente) : crm_portal_id_cliente();
	$tipo=crm_portal_tipo_cliente();
	if($id_evento<=0 || $id<=0)
	{
		return 'No se pudo inscribir.';
	}
	$ev=acceso_consulta_una("SELECT * FROM evento WHERE id_evento=? AND activo=1 LIMIT 1", 'i', array($id_evento));
	if(!$ev)
	{
		return 'La actividad no existe.';
	}
	if(intval($ev['autoinscripcion'])!=1)
	{
		return 'Esta actividad no admite autoinscripción.';
	}
	if(!crm_evento_publico_ok(isset($ev['publico']) ? $ev['publico'] : 'todos', $tipo))
	{
		return 'Esta actividad no está dirigida a tu tipo de cuenta.';
	}
	if(isset($ev['fecha']) && $ev['fecha']!='' && strtotime($ev['fecha']) < strtotime(date('Y-m-d')))
	{
		return 'La actividad ya pasó.';
	}
	$ya=crm_cliente_inscripcion_de($id_evento, $id);
	if($ya)
	{
		return 'Ya estás inscripto.';
	}
	$ok=acceso_insertar(
		"INSERT INTO evento_inscripcion (id_evento, id_cliente, estado) VALUES (?,?, 'inscrito')",
		'ii',
		array($id_evento, $id)
	);
	return $ok>0 ? '' : 'No se pudo guardar la inscripción.';
}

function crm_evento_inscriptos($id_evento)
{
	crm_asegurar_evento_portal();
	$id_evento=intval($id_evento);
	if($id_evento<=0 || !acceso_tabla_existe('evento_inscripcion'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT ei.*, c.nombre AS cliente_nombre, c.tipo_cliente
		FROM evento_inscripcion ei
		INNER JOIN cliente c ON c.id_cliente=ei.id_cliente
		WHERE ei.id_evento=?
		ORDER BY ei.fecha_inscripcion",
		'i',
		array($id_evento)
	);
}

function crm_evento_marcar_asistencia($id_inscripcion, $estado)
{
	crm_asegurar_evento_portal();
	$id=intval($id_inscripcion);
	$ok=array('asistio','ausente','inscrito');
	if($id<=0 || !in_array($estado, $ok, true) || !acceso_tabla_existe('evento_inscripcion'))
	{
		return false;
	}
	acceso_ejecutar("UPDATE evento_inscripcion SET estado=? WHERE id=?", 'si', array($estado, $id));
	return true;
}

function crm_evento_por_id($id_evento)
{
	$id=intval($id_evento);
	if($id<=0 || !acceso_tabla_existe('evento'))
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT ev.*, a.nombre AS area_nombre, a.clave AS area_clave,
			c.nombre AS cliente_nombre,
			CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS responsable
		FROM evento ev
		INNER JOIN area a ON a.id_area=ev.id_area
		LEFT JOIN cliente c ON c.id_cliente=ev.id_cliente
		LEFT JOIN empleado e ON e.id_empleado=ev.id_empleado
		WHERE ev.id_evento=? LIMIT 1",
		'i',
		array($id)
	);
}

function crm_portal_fecha($fecha)
{
	$f=trim((string)$fecha);
	if($f=='' || $f=='0000-00-00' || $f=='0000-00-00 00:00:00')
	{
		return '—';
	}
	$ts=strtotime($f);
	if($ts===false)
	{
		return $f;
	}
	if(strlen($f)>10)
	{
		return date('d/m/Y H:i', $ts);
	}
	return date('d/m/Y', $ts);
}

function crm_portal_tipo_actividad($tipo)
{
	$t=array(
		'reunion'=>'Reunión',
		'capacitacion'=>'Capacitación',
		'ronda'=>'Ronda de negocios',
		'otro'=>'Otro'
	);
	return isset($t[$tipo]) ? $t[$tipo] : $tipo;
}

/**
 * El cliente cancela su propio turno (abierta o solicitud pendiente) e indica el motivo.
 */
function crm_cliente_cancelar_turno($id_atencion, $motivo)
{
	$id=intval($id_atencion);
	$motivo=trim((string)$motivo);
	$id_cli=crm_portal_id_cliente();
	if($id<=0 || $id_cli<=0)
	{
		return 'No se pudo cancelar.';
	}
	if($motivo==='')
	{
		return 'Indicá el motivo de la cancelación.';
	}
	$at=acceso_consulta_una(
		"SELECT * FROM atencion WHERE id_atencion=? AND id_cliente=? LIMIT 1",
		'ii',
		array($id, $id_cli)
	);
	if(!$at)
	{
		return 'Ese turno no te pertenece o no existe.';
	}
	if(!in_array($at['estado'], array('abierta','pendiente'), true))
	{
		return 'Solo podés cancelar solicitudes o turnos que todavía están a la espera.';
	}
	$viejo=isset($at['detalle']) ? trim($at['detalle']) : '';
	$paso='Cancelado por el cliente: '.$motivo;
	$detalle=($viejo!='' ? $viejo.' | ' : '').$paso;
	$ok=acceso_ejecutar(
		"UPDATE atencion SET estado='cancelada', detalle=? WHERE id_atencion=? AND id_cliente=? AND estado IN ('abierta','pendiente')",
		'sii',
		array($detalle, $id, $id_cli)
	);
	if(!$ok)
	{
		return 'No se pudo cancelar el turno.';
	}
	// Aviso claro al área (no "nuevo turno")
	if(function_exists('crm_aviso_enviar') && function_exists('crm_empleado_coord_area'))
	{
		$coord=crm_empleado_coord_area(intval($at['id_area']));
		$id_coord=$coord ? intval($coord['id_empleado']) : 0;
		$area_clave='';
		$ar=acceso_consulta_una("SELECT clave FROM area WHERE id_area=? LIMIT 1", 'i', array(intval($at['id_area'])));
		if($ar) { $area_clave=$ar['clave']; }
		$url=$area_clave!='' ? url_crm($area_clave.'/atencion.php?id='.$id) : '';
		$cli=acceso_consulta_una("SELECT nombre FROM cliente WHERE id_cliente=? LIMIT 1", 'i', array($id_cli));
		$nom=$cli ? $cli['nombre'] : 'Un cliente';
		if($id_coord>0)
		{
			crm_aviso_enviar($id_coord, 'Cliente canceló el turno', $nom.' canceló: '.$motivo, $url, 0, 'info');
		}
	}
	return '';
}

/** Solicitud de turno desde el portal (no agenda sola: Recepción acepta). */
function crm_cliente_solicitar_turno($motivo, $detalle='', $fecha_pref='')
{
	$id_cli=crm_portal_id_cliente();
	if($id_cli<=0)
	{
		return 'Tenés que iniciar sesión.';
	}
	$ok_motivo=array('consulta','documentacion','seguimiento','tramite','otro');
	if(!in_array($motivo, $ok_motivo, true))
	{
		$motivo='consulta';
	}
	$detalle=trim((string)$detalle);
	$fecha_turno=null;
	if($fecha_pref!='')
	{
		$ts=strtotime($fecha_pref);
		if($ts!==false)
		{
			$fecha_turno=date('Y-m-d H:i:s', $ts);
		}
	}
	$id_area=acceso_id_por_clave('area', 'recepcion');
	if($id_area<=0)
	{
		return 'No está configurada el área Recepción.';
	}
	$ya=acceso_consulta_una(
		"SELECT id_atencion FROM atencion WHERE id_cliente=? AND id_area=? AND estado='pendiente' LIMIT 1",
		'ii',
		array($id_cli, $id_area)
	);
	if($ya)
	{
		return 'Ya tenés una solicitud pendiente. Esperá la confirmación de Recepción.';
	}
	$id=crm_atencion_abrir(array(
		'id_cliente'=>$id_cli,
		'id_area'=>$id_area,
		'motivo'=>$motivo,
		'canal'=>'web',
		'detalle'=>$detalle!='' ? $detalle : 'Solicitud desde el portal web',
		'estado'=>'pendiente',
		'fecha_turno'=>$fecha_turno
	));
	if($id<=0)
	{
		return 'No se pudo enviar la solicitud.';
	}
	return '';
}

function crm_cliente_perfil()
{
	$id_u=isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
	if($id_u<=0)
	{
		return null;
	}
	$u=acceso_consulta_una(
		"SELECT id_usuario, nombre, correo, dni, telefono, creado_en FROM usuario_cliente WHERE id_usuario=? LIMIT 1",
		'i',
		array($id_u)
	);
	if(!$u)
	{
		return null;
	}
	$u['cuenta']=null;
	$id_cli=crm_portal_id_cliente();
	if($id_cli>0)
	{
		$u['cuenta']=acceso_consulta_una(
			"SELECT c.id_cliente, c.tipo_cliente, c.nombre, c.correo, c.telefono, c.direccion, c.cuil_cuit, c.fecha_alta, l.nombre AS localidad
			FROM cliente c
			LEFT JOIN localidad l ON l.id_localidad=c.id_localidad
			WHERE c.id_cliente=? LIMIT 1",
			'i',
			array($id_cli)
		);
	}
	return $u;
}

function crm_cliente_perfil_guardar_datos($nombre, $dni, $telefono, $direccion)
{
	$perfil=crm_cliente_perfil();
	if(!$perfil)
	{
		return 'No se encontró tu cuenta.';
	}
	$nombre=trim((string)$nombre);
	$dni=trim((string)$dni);
	$telefono=trim((string)$telefono);
	$direccion=trim((string)$direccion);
	if($nombre=='' || mb_strlen($nombre)>150)
	{
		return 'Indicá tu nombre (hasta 150 caracteres).';
	}
	if($dni!='' && !preg_match('/^\d{6,10}$/', $dni))
	{
		return 'El DNI debe tener solo números (6 a 10 dígitos).';
	}
	if(mb_strlen($telefono)>30)
	{
		return 'El teléfono es demasiado largo.';
	}
	acceso_ejecutar(
		"UPDATE usuario_cliente SET nombre=?, dni=?, telefono=? WHERE id_usuario=?",
		'sssi',
		array($nombre, $dni!='' ? $dni : null, $telefono!='' ? $telefono : null, intval($perfil['id_usuario']))
	);
	$cta=$perfil['cuenta'];
	// Solo sincroniza ficha de emprendedor; organizaciones las gestiona el personal.
	$es_emprendedor=false;
	if($cta)
	{
		if(isset($cta['tipo_cliente']) && $cta['tipo_cliente']!='')
		{
			$es_emprendedor=!cliente_es_organizacion($cta['tipo_cliente']);
		}
		else
		{
			$es_emprendedor=(isset($cta['tipo']) && $cta['tipo']=='persona');
		}
	}
	if($es_emprendedor)
	{
		$id_cli_ficha=isset($cta['id_cliente']) ? intval($cta['id_cliente']) : (isset($cta['id']) ? intval($cta['id']) : 0);
		if($id_cli_ficha>0)
		{
			acceso_ejecutar(
				"UPDATE cliente SET nombre=?, telefono=?, direccion=? WHERE id_cliente=?",
				'sssi',
				array($nombre, $telefono!='' ? $telefono : null, $direccion!='' ? mb_substr($direccion, 0, 200) : null, $id_cli_ficha)
			);
			$_SESSION['cuenta_nombre']=$nombre;
		}
	}
	$_SESSION['name']=$nombre;
	return '';
}

function crm_cliente_perfil_cambiar_correo($correo, $clave_actual)
{
	$perfil=crm_cliente_perfil();
	if(!$perfil)
	{
		return 'No se encontró tu cuenta.';
	}
	$correo=trim((string)$correo);
	if(!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo)>150)
	{
		return 'Ingresá un correo válido.';
	}
	if(strcasecmp($correo, $perfil['correo'])==0)
	{
		return 'Ese ya es tu correo de ingreso.';
	}
	$row=acceso_consulta_una("SELECT contrasena_hash FROM usuario_cliente WHERE id_usuario=? LIMIT 1", 'i', array(intval($perfil['id_usuario'])));
	if(!$row || !acceso_verificar_clave($clave_actual, $row['contrasena_hash']))
	{
		return 'La contraseña actual no coincide.';
	}
	$otro=acceso_consulta_una(
		"SELECT id_usuario FROM usuario_cliente WHERE correo=? AND id_usuario<>? LIMIT 1",
		'si',
		array($correo, intval($perfil['id_usuario']))
	);
	if($otro)
	{
		return 'Ese correo ya está en uso por otra cuenta.';
	}
	acceso_ejecutar("UPDATE usuario_cliente SET correo=? WHERE id_usuario=?", 'si', array($correo, intval($perfil['id_usuario'])));
	$_SESSION['login']=$correo;
	return '';
}
