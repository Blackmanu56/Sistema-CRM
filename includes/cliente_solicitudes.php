<?php
/**
 * Solicitudes de alta web: formulario corto → evaluación Coordinación general.
 * Los datos de programa (diagnóstico largo) van aparte, más adelante.
 */

function crm_asegurar_cliente_aviso()
{
	static $listo=false;
	if($listo || (function_exists('crm_schema_al_dia') && crm_schema_al_dia('cliente_aviso_v1')))
	{
		$listo=true;
		return;
	}
	global $con;
	if(!$con)
	{
		return;
	}
	@mysqli_query($con, "CREATE TABLE IF NOT EXISTS cliente_aviso (
		id_aviso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		id_usuario INT UNSIGNED NOT NULL,
		titulo VARCHAR(200) NOT NULL,
		detalle TEXT NULL,
		leido TINYINT(1) NOT NULL DEFAULT 0,
		fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		INDEX idx_claviso_user (id_usuario, leido, fecha),
		CONSTRAINT fk_claviso_user FOREIGN KEY (id_usuario) REFERENCES usuario_cliente(id_usuario) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	if(function_exists('crm_schema_marcar'))
	{
		crm_schema_marcar('cliente_aviso_v1');
	}
	$listo=true;
}

function crm_cliente_aviso_crear($id_usuario, $titulo, $detalle='')
{
	crm_asegurar_cliente_aviso();
	$id_usuario=intval($id_usuario);
	$titulo=trim($titulo);
	if($id_usuario<=0 || $titulo==='' || !acceso_tabla_existe('cliente_aviso'))
	{
		return false;
	}
	return acceso_insertar(
		"INSERT INTO cliente_aviso (id_usuario, titulo, detalle) VALUES (?,?,?)",
		'iss',
		array($id_usuario, $titulo, trim($detalle))
	)>0;
}

function crm_cliente_avisos_pendientes($id_usuario=0)
{
	crm_asegurar_cliente_aviso();
	if($id_usuario<=0)
	{
		$id_usuario=isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
	}
	if($id_usuario<=0 || !acceso_tabla_existe('cliente_aviso'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT * FROM cliente_aviso WHERE id_usuario=? AND leido=0 ORDER BY fecha DESC LIMIT 20",
		'i',
		array($id_usuario)
	);
}

function crm_cliente_aviso_marcar_leido($id_aviso, $id_usuario=0)
{
	crm_asegurar_cliente_aviso();
	$id_aviso=intval($id_aviso);
	if($id_usuario<=0)
	{
		$id_usuario=isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
	}
	if($id_aviso<=0 || $id_usuario<=0)
	{
		return;
	}
	acceso_ejecutar(
		"UPDATE cliente_aviso SET leido=1 WHERE id_aviso=? AND id_usuario=?",
		'ii',
		array($id_aviso, $id_usuario)
	);
}

function crm_asegurar_cliente_solicitud()
{
	static $listo=false;
	if($listo || (function_exists('crm_schema_al_dia') && crm_schema_al_dia('cliente_solicitud_v1')))
	{
		$listo=true;
		if(function_exists('crm_asegurar_cliente_aviso'))
		{
			crm_asegurar_cliente_aviso();
		}
		return;
	}
	global $con;
	if(!$con)
	{
		return;
	}
	crm_asegurar_cliente_aviso();
	@mysqli_query($con, "CREATE TABLE IF NOT EXISTS cliente_solicitud (
		id_solicitud INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		nombre VARCHAR(150) NOT NULL,
		correo VARCHAR(150) NOT NULL,
		telefono VARCHAR(30) NULL,
		dni VARCHAR(15) NULL,
		cuil_cuit VARCHAR(13) NOT NULL,
		nombre_negocio VARCHAR(150) NULL,
		id_localidad SMALLINT UNSIGNED NOT NULL,
		tipo_autodeclarado ENUM('emprendedor','empresa') NOT NULL DEFAULT 'emprendedor',
		sector VARCHAR(80) NULL,
		sector_otro VARCHAR(120) NULL,
		anios_funcionamiento VARCHAR(40) NULL,
		personas_involucradas VARCHAR(40) NULL,
		personal_formal TINYINT(1) NOT NULL DEFAULT 0,
		producto_servicio TEXT NULL,
		contrasena_hash VARCHAR(255) NOT NULL,
		estado ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
		sugerencia_tipo ENUM('emprendedor','empresa') NULL,
		tipo_asignado ENUM('emprendedor','empresa') NULL,
		id_cliente INT UNSIGNED NULL,
		id_revisa INT UNSIGNED NULL,
		nota_revision TEXT NULL,
		fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		fecha_resolucion DATETIME NULL,
		INDEX idx_csol_estado (estado, fecha_solicitud),
		INDEX idx_csol_correo (correo),
		CONSTRAINT fk_csol_loc FOREIGN KEY (id_localidad) REFERENCES localidad(id_localidad),
		CONSTRAINT fk_csol_cli FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
		CONSTRAINT fk_csol_rev FOREIGN KEY (id_revisa) REFERENCES empleado(id_empleado)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	if(function_exists('crm_schema_marcar'))
	{
		crm_schema_marcar('cliente_solicitud_v1');
	}
	$listo=true;
}

function crm_solicitud_sectores()
{
	return array(
		'comercio'=>'Comercio',
		'servicio'=>'Servicio',
		'produccion'=>'Producción',
		'tecnologia'=>'Tecnología',
		'agroindustria'=>'Agroindustria',
		'otro'=>'Otro'
	);
}

function crm_solicitud_anios_opciones()
{
	return array(
		'menos_1'=>'Menos de 1 año',
		'1_3'=>'De 1 a 3 años',
		'3_5'=>'De 3 a 5 años',
		'mas_5'=>'Más de 5 años'
	);
}

function crm_solicitud_personas_opciones()
{
	return array(
		'solo'=>'Solo yo',
		'2_5'=>'De 2 a 5 personas',
		'6_10'=>'De 6 a 10 personas',
		'mas_10'=>'Más de 10 personas'
	);
}

function crm_solicitud_etiqueta($mapa, $clave)
{
	$clave=trim((string)$clave);
	return ($clave!=='' && isset($mapa[$clave])) ? $mapa[$clave] : ($clave!=='' ? $clave : '—');
}

function crm_solicitud_sugerir_tipo($datos)
{
	$emp=0;
	$empr=0;
	$auto=isset($datos['tipo_autodeclarado']) ? $datos['tipo_autodeclarado'] : 'emprendedor';
	if($auto==='empresa') { $emp+=3; } else { $empr+=3; }
	if(!empty($datos['personal_formal'])) { $emp+=2; } else { $empr+=1; }
	$pers=isset($datos['personas_involucradas']) ? $datos['personas_involucradas'] : '';
	if($pers==='solo' || $pers==='2_5') { $empr+=2; }
	if($pers==='6_10' || $pers==='mas_10') { $emp+=2; }
	$anios=isset($datos['anios_funcionamiento']) ? $datos['anios_funcionamiento'] : '';
	if($anios==='mas_5') { $emp+=1; }
	if($anios==='menos_1') { $empr+=1; }
	$doc=preg_replace('/\D+/', '', isset($datos['cuil_cuit']) ? $datos['cuil_cuit'] : '');
	if(strlen($doc)>=2)
	{
		$pref=substr($doc, 0, 2);
		if(in_array($pref, array('30','33','34'), true)) { $emp+=2; }
		if(in_array($pref, array('20','23','24','27'), true)) { $empr+=1; }
	}
	return ($emp>$empr) ? 'empresa' : 'emprendedor';
}

function crm_solicitud_guardar_web($datos)
{
	crm_asegurar_cliente_solicitud();
	if(!acceso_tabla_existe('cliente_solicitud'))
	{
		return 'No se pudo guardar la solicitud.';
	}
	$nombre=isset($datos['nombre']) ? trim($datos['nombre']) : '';
	$correo=isset($datos['correo']) ? trim($datos['correo']) : '';
	$tel=isset($datos['telefono']) ? trim($datos['telefono']) : '';
	$dni=crm_solo_numeros(isset($datos['dni']) ? $datos['dni'] : '');
	$cuil=preg_replace('/\D+/', '', isset($datos['cuil_cuit']) ? $datos['cuil_cuit'] : '');
	$negocio=isset($datos['nombre_negocio']) ? trim($datos['nombre_negocio']) : '';
	$id_loc=isset($datos['id_localidad']) ? intval($datos['id_localidad']) : 0;
	$tipo_auto=(isset($datos['tipo_autodeclarado']) && $datos['tipo_autodeclarado']==='empresa') ? 'empresa' : 'emprendedor';
	$sector=isset($datos['sector']) ? trim($datos['sector']) : '';
	$sector_otro=isset($datos['sector_otro']) ? trim($datos['sector_otro']) : '';
	$anios=isset($datos['anios_funcionamiento']) ? trim($datos['anios_funcionamiento']) : '';
	$personas=isset($datos['personas_involucradas']) ? trim($datos['personas_involucradas']) : '';
	$personal=(!empty($datos['personal_formal'])) ? 1 : 0;
	$prod=isset($datos['producto_servicio']) ? trim($datos['producto_servicio']) : '';
	$clave=isset($datos['clave']) ? (string)$datos['clave'] : '';

	if($nombre==='' || $correo==='' || $tel==='' || $negocio==='' || $id_loc<=0 || $cuil==='' || $clave==='')
	{
		return 'Completá los datos obligatorios.';
	}
	if(strlen($clave)<6)
	{
		return 'La contraseña debe tener al menos 6 caracteres.';
	}
	if(!filter_var($correo, FILTER_VALIDATE_EMAIL))
	{
		return 'El correo no es válido.';
	}
	if(strlen($cuil)<11)
	{
		return 'Ingresá un CUIL o CUIT válido (11 dígitos).';
	}
	$sectores=crm_solicitud_sectores();
	if($sector==='' || !isset($sectores[$sector]))
	{
		return 'Elegí el sector.';
	}
	if($sector==='otro' && $sector_otro==='')
	{
		return 'Indicá el sector (Otro).';
	}
	if(!isset(crm_solicitud_anios_opciones()[$anios]))
	{
		return 'Indicá los años de funcionamiento.';
	}
	if(!isset(crm_solicitud_personas_opciones()[$personas]))
	{
		return 'Indicá cuántas personas están involucradas.';
	}
	$pend=acceso_consulta_una(
		"SELECT id_solicitud FROM cliente_solicitud WHERE correo=? AND estado='pendiente' LIMIT 1",
		's',
		array($correo)
	);
	if($pend)
	{
		return 'Ya hay una solicitud pendiente con ese correo.';
	}
	if(usuario_cliente_por_correo($correo))
	{
		return 'Ese correo ya tiene cuenta. Iniciá sesión o usá otro correo.';
	}
	$hay_cuil=acceso_consulta_una("SELECT id_cliente FROM cliente WHERE cuil_cuit=? LIMIT 1", 's', array($cuil));
	if($hay_cuil)
	{
		return 'Ese CUIL/CUIT ya está cargado en el padrón.';
	}
	$hash=acceso_hash_clave($clave);
	if($hash===false || $hash==='')
	{
		return 'No se pudo guardar la contraseña.';
	}
	$pack=array(
		'tipo_autodeclarado'=>$tipo_auto,
		'personal_formal'=>$personal,
		'personas_involucradas'=>$personas,
		'anios_funcionamiento'=>$anios,
		'cuil_cuit'=>$cuil
	);
	$sugerencia=crm_solicitud_sugerir_tipo($pack);
	$id=acceso_insertar(
		"INSERT INTO cliente_solicitud (
			nombre, correo, telefono, dni, cuil_cuit, nombre_negocio, id_localidad,
			tipo_autodeclarado, sector, sector_otro, anios_funcionamiento, personas_involucradas,
			personal_formal, producto_servicio, contrasena_hash, estado, sugerencia_tipo
		) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pendiente',?)",
		'ssssssisssssisss',
		array(
			$nombre, $correo, $tel, $dni, $cuil, $negocio, $id_loc,
			$tipo_auto, $sector, $sector_otro, $anios, $personas,
			$personal, $prod, $hash, $sugerencia
		)
	);
	if($id<=0)
	{
		return 'No se pudo enviar la solicitud. Intentá de nuevo.';
	}
	crm_solicitud_avisar_general($id, $nombre, $negocio);
	return '';
}

function crm_solicitud_avisar_general($id_solicitud, $nombre, $negocio)
{
	$id_solicitud=intval($id_solicitud);
	$url=url_crm('general/solicitudes-alta.php?id='.$id_solicitud);
	$detalle=trim($nombre.($negocio!=='' ? ' · '.$negocio : ''));
	$coords=acceso_consulta(
		"SELECT e.id_empleado FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE e.activo=1 AND r.clave IN ('coordinador_general','superadmin')
		ORDER BY r.clave, e.id_empleado"
	);
	$vistos=array();
	foreach($coords as $c)
	{
		$id_para=intval($c['id_empleado']);
		if($id_para<=0 || isset($vistos[$id_para])) { continue; }
		$vistos[$id_para]=1;
		if(function_exists('crm_aviso_enviar'))
		{
			crm_aviso_enviar($id_para, 'Nueva solicitud de alta web', $detalle, $url, 0, 'info');
		}
	}
}

function crm_solicitud_traer($id)
{
	crm_asegurar_cliente_solicitud();
	$id=intval($id);
	if($id<=0)
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT s.*, l.nombre AS localidad_nombre, p.nombre AS provincia_nombre,
			CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS revisa_nombre
		FROM cliente_solicitud s
		LEFT JOIN localidad l ON l.id_localidad=s.id_localidad
		LEFT JOIN provincia p ON p.id_provincia=l.id_provincia
		LEFT JOIN empleado e ON e.id_empleado=s.id_revisa
		WHERE s.id_solicitud=? LIMIT 1",
		'i',
		array($id)
	);
}

function crm_solicitud_listar($solo='pendiente', $limite=100)
{
	crm_asegurar_cliente_solicitud();
	$limite=max(1, min(300, intval($limite)));
	$where="WHERE 1=1";
	$types='';
	$vals=array();
	if($solo==='pendiente' || $solo==='aceptada' || $solo==='rechazada')
	{
		$where.=" AND s.estado=?";
		$types.='s';
		$vals[]=$solo;
	}
	$sql="SELECT s.*, l.nombre AS localidad_nombre
		FROM cliente_solicitud s
		LEFT JOIN localidad l ON l.id_localidad=s.id_localidad
		$where
		ORDER BY FIELD(s.estado,'pendiente','aceptada','rechazada'), s.fecha_solicitud DESC
		LIMIT $limite";
	if($types!=='')
	{
		return acceso_consulta($sql, $types, $vals);
	}
	return acceso_consulta($sql);
}

function crm_solicitud_contar_pendientes()
{
	crm_asegurar_cliente_solicitud();
	if(!acceso_tabla_existe('cliente_solicitud'))
	{
		return 0;
	}
	$row=acceso_consulta_una("SELECT COUNT(*) AS n FROM cliente_solicitud WHERE estado='pendiente'");
	return $row ? intval($row['n']) : 0;
}

function crm_solicitud_rechazar($id, $nota='')
{
	crm_asegurar_cliente_solicitud();
	$id=intval($id);
	$s=crm_solicitud_traer($id);
	if(!$s || $s['estado']!=='pendiente')
	{
		return 'La solicitud no está pendiente.';
	}
	$yo=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;
	acceso_ejecutar(
		"UPDATE cliente_solicitud SET estado='rechazada', id_revisa=?, nota_revision=?, fecha_resolucion=NOW() WHERE id_solicitud=?",
		'isi',
		array($yo, trim($nota), $id)
	);
	return '';
}

function crm_solicitud_aceptar($id, $tipo_asignado, $nota='')
{
	crm_asegurar_cliente_solicitud();
	$id=intval($id);
	$s=crm_solicitud_traer($id);
	if(!$s || $s['estado']!=='pendiente')
	{
		return 'La solicitud no está pendiente.';
	}
	$tipo=($tipo_asignado==='empresa') ? 'empresa' : 'emprendedor';
	$area=($tipo==='empresa') ? 'empresas' : 'emprendedores';
	$nombre_cli=trim($s['nombre_negocio'])!=='' ? trim($s['nombre_negocio']) : trim($s['nombre']);
	$correo=trim($s['correo']);
	$tel=trim($s['telefono']);
	$cuil=trim($s['cuil_cuit']);
	$id_loc=intval($s['id_localidad']);
	$hash=$s['contrasena_hash'];
	$dni=crm_solo_numeros($s['dni']);

	if(usuario_cliente_por_correo($correo))
	{
		return 'Ese correo ya tiene usuario. No se puede aceptar así.';
	}
	if(acceso_consulta_una("SELECT id_cliente FROM cliente WHERE cuil_cuit=? LIMIT 1", 's', array($cuil)))
	{
		return 'Ese CUIL/CUIT ya está en el padrón.';
	}

	if($tipo==='empresa')
	{
		$id_cli=acceso_insertar(
			"INSERT INTO cliente (tipo_cliente, nombre, correo, telefono, id_localidad, direccion, cuil_cuit, origen_contacto)
			VALUES ('empresa',?,?,?,?,?,?,?)",
			'sssisss',
			array($nombre_cli, $correo, $tel, $id_loc, '', $cuil, 'web')
		);
		if($id_cli<=0)
		{
			return 'No se pudo crear el cliente empresa.';
		}
		acceso_insertar("INSERT INTO cliente_empresa (id_cliente, razon_social) VALUES (?,?)", 'is', array($id_cli, $nombre_cli));
		$id_u=acceso_insertar(
			"INSERT INTO usuario_cliente (nombre, correo, contrasena_hash, primer_acceso, dni, telefono, activo) VALUES (?,?,?,0,?,?,1)",
			'sssss',
			array(trim($s['nombre']), $correo, $hash, $dni, $tel)
		);
		if($id_u<=0)
		{
			return 'No se pudo crear el usuario del responsable.';
		}
		usuario_cliente_vincular($id_u, $id_cli, 'titular', 1);
	}
	else
	{
		$id_cli=acceso_insertar(
			"INSERT INTO cliente (tipo_cliente, nombre, correo, telefono, id_localidad, direccion, cuil_cuit, origen_contacto)
			VALUES ('emprendedor',?,?,?,?,?,?,?)",
			'sssisss',
			array($nombre_cli, $correo, $tel, $id_loc, '', $cuil, 'web')
		);
		if($id_cli<=0)
		{
			return 'No se pudo crear el cliente emprendedor.';
		}
		acceso_insertar("INSERT INTO cliente_persona (id_cliente, genero, dni) VALUES (?,NULL,?)", 'is', array($id_cli, $dni));
		$id_u=acceso_insertar(
			"INSERT INTO usuario_cliente (nombre, correo, contrasena_hash, primer_acceso, dni, telefono, activo) VALUES (?,?,?,0,?,?,1)",
			'sssss',
			array(trim($s['nombre']), $correo, $hash, $dni, $tel)
		);
		if($id_u<=0)
		{
			return 'No se pudo crear el usuario de ingreso.';
		}
		usuario_cliente_vincular($id_u, $id_cli, 'titular', 1);
	}

	$id_area=acceso_id_por_clave('area', $area);
	if($id_area>0)
	{
		acceso_ejecutar("UPDATE cliente SET id_area_afectada=? WHERE id_cliente=?", 'ii', array($id_area, $id_cli));
		if(function_exists('crm_vincular_cliente_area'))
		{
			crm_vincular_cliente_area($id_cli, $id_area);
		}
	}

	$yo=function_exists('crm_id_empleado_sesion') ? intval(crm_id_empleado_sesion()) : 0;
	if($yo>0)
	{
		if(function_exists('acceso_columna_existe') && acceso_columna_existe('cliente', 'id_empleado_alta'))
		{
			acceso_ejecutar("UPDATE cliente SET id_empleado_alta=? WHERE id_cliente=?", 'ii', array($yo, $id_cli));
		}
		acceso_ejecutar(
			"UPDATE cliente_solicitud SET estado='aceptada', tipo_asignado=?, id_cliente=?, id_revisa=?, nota_revision=?, fecha_resolucion=NOW() WHERE id_solicitud=?",
			'siisi',
			array($tipo, $id_cli, $yo, trim($nota), $id)
		);
	}
	else
	{
		acceso_ejecutar(
			"UPDATE cliente_solicitud SET estado='aceptada', tipo_asignado=?, id_cliente=?, nota_revision=?, fecha_resolucion=NOW() WHERE id_solicitud=?",
			'sisi',
			array($tipo, $id_cli, trim($nota), $id)
		);
	}
	// Aviso en portal (primer ingreso) + intento de mail.
	if(isset($id_u) && intval($id_u)>0)
	{
		$area_txt=($tipo==='empresa') ? 'Empresas' : 'Emprendedores';
		crm_cliente_aviso_crear(
			intval($id_u),
			'Tu solicitud fue aceptada',
			'ADEMI aceptó tu alta. Ya podés usar el portal. Estás vinculado al área de '.$area_txt.'.'
		);
		$asunto='ADEMI — Solicitud aceptada';
		$cuerpo="Hola,\n\nTu solicitud de alta en ADEMI fue aceptada.\nYa podés iniciar sesión con tu correo y la contraseña que elegiste.\nEstás vinculado al área de ".$area_txt.".\n\nADEMI\n";
		@mail($correo, $asunto, $cuerpo, "From: noreply@ademi.local\r\nContent-Type: text/plain; charset=UTF-8");
	}
	return '';
}

/** Pasa un cliente entre emprendedor y empresa (Coordinación general). */
function cliente_cambiar_tipo($id_cliente, $tipo_nuevo, $nota='')
{
	$id_cliente=intval($id_cliente);
	$tipo=($tipo_nuevo==='empresa') ? 'empresa' : 'emprendedor';
	$cli=cliente_traer($id_cliente);
	if(!$cli)
	{
		return 'No se encontró el cliente.';
	}
	$actual=cliente_tipo_normalizar(isset($cli['tipo_cliente']) ? $cli['tipo_cliente'] : '');
	if($actual===$tipo)
	{
		return 'El cliente ya es de ese tipo.';
	}
	if(!in_array($actual, array('emprendedor','empresa'), true))
	{
		return 'Solo se puede pasar entre emprendedor y empresa.';
	}
	$es_org_nuevo=($tipo==='empresa');
	acceso_ejecutar("UPDATE cliente SET tipo_cliente=? WHERE id_cliente=?", 'si', array($tipo, $id_cliente));
	if($es_org_nuevo)
	{
		acceso_ejecutar("DELETE FROM cliente_persona WHERE id_cliente=?", 'i', array($id_cliente));
		$ya=acceso_consulta_una("SELECT id_cliente FROM cliente_empresa WHERE id_cliente=? LIMIT 1", 'i', array($id_cliente));
		if(!$ya)
		{
			acceso_insertar(
				"INSERT INTO cliente_empresa (id_cliente, razon_social) VALUES (?,?)",
				'is',
				array($id_cliente, $cli['nombre'])
			);
		}
	}
	else
	{
		acceso_ejecutar("DELETE FROM cliente_empresa WHERE id_cliente=?", 'i', array($id_cliente));
		$ya=acceso_consulta_una("SELECT id_cliente FROM cliente_persona WHERE id_cliente=? LIMIT 1", 'i', array($id_cliente));
		if(!$ya)
		{
			acceso_insertar("INSERT INTO cliente_persona (id_cliente, genero, dni) VALUES (?,NULL,NULL)", 'i', array($id_cliente));
		}
	}
	$area_nueva=($tipo==='empresa') ? 'empresas' : 'emprendedores';
	$area_vieja=($tipo==='empresa') ? 'emprendedores' : 'empresas';
	$id_nueva=acceso_id_por_clave('area', $area_nueva);
	$id_vieja=acceso_id_por_clave('area', $area_vieja);
	if($id_vieja>0)
	{
		acceso_ejecutar("DELETE FROM cliente_area WHERE id_cliente=? AND id_area=?", 'ii', array($id_cliente, $id_vieja));
	}
	if($id_nueva>0)
	{
		acceso_ejecutar("UPDATE cliente SET id_area_afectada=? WHERE id_cliente=?", 'ii', array($id_nueva, $id_cliente));
		if(function_exists('crm_vincular_cliente_area'))
		{
			crm_vincular_cliente_area($id_cliente, $id_nueva);
		}
	}
	unset($nota);
	return '';
}
