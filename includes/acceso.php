<?php
// Accesos del personal interno: usuario, clave, área y rol salen de la tabla empleado.
// Este archivo es de TODOS. No agregues acá lógica de un área (tareas de recepción,
// sistemas, comercio, etc.). Cada área responde con funciones en su datos-tareas.php.

if(!function_exists('acceso_etiquetas_area'))
{

function acceso_sql($texto)
{
	global $con;
	if(!$con)
	{
		return '';
	}
	return mysqli_real_escape_string($con, (string)$texto);
}

$GLOBALS['acceso_ultimo_error'] = '';

function acceso_ultimo_error()
{
	global $con;
	if(!empty($GLOBALS['acceso_ultimo_error']))
	{
		return $GLOBALS['acceso_ultimo_error'];
	}
	return ($con && mysqli_error($con)) ? mysqli_error($con) : '';
}

function acceso_stmt_preparar($sql, $types, &$vals)
{
	global $con;
	$GLOBALS['acceso_ultimo_error'] = '';
	if(!$con)
	{
		$GLOBALS['acceso_ultimo_error'] = 'Sin conexión a la base de datos.';
		return false;
	}
	$stmt=@mysqli_prepare($con, $sql);
	if(!$stmt)
	{
		$GLOBALS['acceso_ultimo_error'] = mysqli_error($con);
		return false;
	}
	if($types!='' && count($vals)>0)
	{
		$refs=array($types);
		foreach($vals as $i=>$v)
		{
			$refs[]=&$vals[$i];
		}
		if(!call_user_func_array(array($stmt, 'bind_param'), $refs))
		{
			$GLOBALS['acceso_ultimo_error'] = mysqli_stmt_error($stmt);
			mysqli_stmt_close($stmt);
			return false;
		}
	}
	if(!mysqli_stmt_execute($stmt))
	{
		$GLOBALS['acceso_ultimo_error'] = mysqli_stmt_error($stmt);
		mysqli_stmt_close($stmt);
		return false;
	}
	return $stmt;
}

function acceso_consulta($sql, $types='', $vals=array())
{
	$filas=array();
	$stmt=acceso_stmt_preparar($sql, $types, $vals);
	if(!$stmt)
	{
		return $filas;
	}
	$res=mysqli_stmt_get_result($stmt);
	if($res)
	{
		while($row=mysqli_fetch_assoc($res))
		{
			$filas[]=$row;
		}
	}
	mysqli_stmt_close($stmt);
	return $filas;
}

function acceso_consulta_una($sql, $types='', $vals=array())
{
	$filas=acceso_consulta($sql, $types, $vals);
	return isset($filas[0]) ? $filas[0] : null;
}

function acceso_ejecutar($sql, $types='', $vals=array())
{
	$stmt=acceso_stmt_preparar($sql, $types, $vals);
	if(!$stmt)
	{
		return false;
	}
	mysqli_stmt_close($stmt);
	return true;
}

function acceso_insertar($sql, $types='', $vals=array())
{
	global $con;
	$stmt=acceso_stmt_preparar($sql, $types, $vals);
	if(!$stmt)
	{
		return 0;
	}
	$id=intval(mysqli_insert_id($con));
	mysqli_stmt_close($stmt);
	return $id;
}

function acceso_hash_clave($clave)
{
	// bcrypt ($2y$): 60 caracteres, entra en VARCHAR(255) de usuario_cliente y empleado.
	return password_hash($clave, PASSWORD_BCRYPT);
}

function acceso_verificar_clave($clave, $hash)
{
	if($clave==='' || $hash==='')
	{
		return false;
	}
	// PHP entiende $2y$; algunos hashes vienen como $2b$ (mismo algoritmo).
	if(strpos($hash, '$2b$')===0)
	{
		$hash='$2y$'.substr($hash, 4);
	}
	return password_verify($clave, $hash);
}

function acceso_tabla_existe($tabla)
{
	global $con;
	if(!$con || $tabla==='')
	{
		return false;
	}
	$q=@mysqli_query($con,"SHOW TABLES LIKE '".acceso_sql($tabla)."'");
	return ($q && mysqli_num_rows($q)>0);
}

// Las migraciones automáticas se registran en la misma base (no en archivos de tmp/):
// al copiar el sistema a otra máquina, cada base conserva su propio estado.
function crm_schema_al_dia($clave)
{
	global $con;
	static $marcas=null;
	if(!$con)
	{
		return false;
	}
	if($marcas===null)
	{
		$marcas=array();
		$q=@mysqli_query($con, "SELECT clave, fecha FROM crm_schema_version");
		if($q)
		{
			while($r=mysqli_fetch_assoc($q))
			{
				$marcas[$r['clave']]=strtotime($r['fecha']);
			}
		}
	}
	return isset($marcas[$clave]) && ($marcas[$clave]+86400)>time();
}

function crm_schema_marcar($clave)
{
	global $con;
	if(!$con)
	{
		return;
	}
	@mysqli_query($con, "CREATE TABLE IF NOT EXISTS crm_schema_version (
		clave VARCHAR(60) NOT NULL PRIMARY KEY,
		fecha DATETIME NOT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	@mysqli_query($con, "REPLACE INTO crm_schema_version (clave, fecha) VALUES ('".acceso_sql($clave)."', NOW())");
}

// Asegura flags de área en BD (sin listas hardcodeadas de claves).
function crm_asegurar_area_flags()
{
	static $listo=false;
	if($listo || crm_schema_al_dia('area_flags_v1'))
	{
		$listo=true;
		return;
	}
	global $con;
	if(!$con || !acceso_tabla_existe('area'))
	{
		return;
	}
	$cols=array();
	$q=@mysqli_query($con, "SHOW COLUMNS FROM area");
	if($q)
	{
		while($r=mysqli_fetch_assoc($q))
		{
			$cols[$r['Field']]=true;
		}
	}
	if(!isset($cols['atiende_clientes']))
	{
		@mysqli_query($con, "ALTER TABLE area ADD COLUMN atiende_clientes TINYINT(1) NOT NULL DEFAULT 0 AFTER activo");
	}
	if(!isset($cols['asignable_empleado']))
	{
		@mysqli_query($con, "ALTER TABLE area ADD COLUMN asignable_empleado TINYINT(1) NOT NULL DEFAULT 1 AFTER atiende_clientes");
	}
	// v3: capacidades independientes (clientes / derivaciones / turnos).
	// Si hay que crearlas, se arrancan desde atiende_clientes para no perder
	// el funcionamiento anterior; después cada fila se configura por separado.
	$cols_nuevas=array();
	if(!isset($cols['puede_clientes']))
	{
		@mysqli_query($con, "ALTER TABLE area ADD COLUMN puede_clientes TINYINT(1) NOT NULL DEFAULT 0 AFTER atiende_clientes");
		$cols_nuevas[]='puede_clientes';
	}
	if(!isset($cols['recibe_derivaciones']))
	{
		@mysqli_query($con, "ALTER TABLE area ADD COLUMN recibe_derivaciones TINYINT(1) NOT NULL DEFAULT 0 AFTER puede_clientes");
		$cols_nuevas[]='recibe_derivaciones';
	}
	if(!isset($cols['recibe_turnos']))
	{
		@mysqli_query($con, "ALTER TABLE area ADD COLUMN recibe_turnos TINYINT(1) NOT NULL DEFAULT 0 AFTER recibe_derivaciones");
		$cols_nuevas[]='recibe_turnos';
	}
	// Semilla única: según módulo público real + áreas de dirección (sin carpeta operativa de clientes).
	$base=defined('BASE_PATH') ? BASE_PATH.'/public' : '';
	$filas=acceso_consulta("SELECT id_area, clave FROM area");
	foreach($filas as $a)
	{
		$clave=$a['clave'];
		$tiene_aten=($base!=='' && (is_file($base.'/'.$clave.'/atenciones.php') || is_file($base.'/'.$clave.'/atencion.php')));
		$tiene_mod=($base!=='' && (is_file($base.'/'.$clave.'/tareas.php') || is_file($base.'/'.$clave.'/dashboard.php')));
		// Dirección / roles institucionales: no se asignan como área de alta común.
		$asignable=($clave==='presidencia' || $clave==='general') ? 0 : 1;
		// Recibe clientes/derivaciones si el módulo tiene pantallas de atención.
		$atiende=$tiene_aten ? 1 : 0;
		// Si no hay carpeta todavía, no forzar flags en 0 si ya fueron tocados a mano.
		acceso_ejecutar(
			"UPDATE area SET atiende_clientes=?, asignable_empleado=? WHERE id_area=?",
			'iii',
			array($atiende, $asignable, intval($a['id_area']))
		);
		unset($tiene_mod); // reservado por si se amplía el criterio
	}
	// Backfill de las capacidades recién creadas (solo la primera vez).
	foreach($cols_nuevas as $cn)
	{
		@mysqli_query($con, "UPDATE area SET {$cn}=atiende_clientes WHERE {$cn}=0 AND atiende_clientes=1");
	}
	crm_schema_marcar('area_flags_v1');
	$listo=true;
}

// Areas de la tabla area (solo BD).
function acceso_etiquetas_area()
{
	static $cache=null;
	if($cache!==null)
	{
		return $cache;
	}
	crm_asegurar_area_flags();
	global $con;
	$cache=array();
	if($con)
	{
		$q=@mysqli_query($con,"SELECT clave, nombre FROM area WHERE activo=1 ORDER BY nombre");
		if($q)
		{
			while($row=mysqli_fetch_assoc($q))
			{
				$cache[$row['clave']]=$row['nombre'];
			}
		}
	}
	return $cache;
}

/**
 * Áreas operativas para asignar personal (flag asignable_empleado en BD).
 * Presidencia / Coordinador general: sin área operativa en el alta.
 */
function acceso_etiquetas_area_asignables($rol='')
{
	crm_asegurar_area_flags();
	$rol=trim((string)$rol);
	if($rol==='presidencia' || $rol==='coordinador_general')
	{
		return array();
	}
	$salida=array();
	$filas=acceso_consulta("SELECT clave, nombre FROM area WHERE activo=1 AND asignable_empleado=1 ORDER BY nombre");
	foreach($filas as $a)
	{
		$salida[$a['clave']]=$a['nombre'];
	}
	return $salida;
}

// Nombre corto del area, para mostrar en las tareas (Kami - Marketing).
function acceso_etiqueta_corta_area($area)
{
	$etq=acceso_etiquetas_area();
	if(!isset($etq[$area]))
	{
		return $area;
	}
	$nom=trim($etq[$area]);
	// Abreviatura natural: primera palabra o iniciales si es compuesto largo.
	if(function_exists('mb_strlen') && mb_strlen($nom, 'UTF-8')<=18)
	{
		return $nom;
	}
	$partes=preg_split('/\s+/u', $nom);
	if(count($partes)<=2)
	{
		return $nom;
	}
	$corto=$partes[0];
	if(isset($partes[1]) && function_exists('mb_substr'))
	{
		$corto.=' '.mb_substr($partes[1], 0, 1, 'UTF-8').'.';
	}
	return $corto;
}

// Areas a las que se puede derivar / asignar atención de cliente (flag en BD).
function recepcion_areas_cliente()
{
	crm_asegurar_area_flags();
	$salida=array();
	$filas=acceso_consulta("SELECT clave, nombre FROM area WHERE activo=1 AND recibe_turnos=1 ORDER BY nombre");
	foreach($filas as $a)
	{
		// Recepción agenda/deriva hacia otras áreas; no se lista a sí misma como destino.
		if($a['clave']==='recepcion')
		{
			continue;
		}
		$salida[$a['clave']]=$a['nombre'];
	}
	return $salida;
}

function crm_areas_destino_derivacion($area_origen = '')
{
	crm_asegurar_area_flags();
	if($area_origen === '')
	{
		$area_origen = function_exists('acceso_area_activa') ? acceso_area_activa() : (function_exists('acceso_area') ? acceso_area() : '');
	}
	$salida = array();
	$filas = acceso_consulta("SELECT clave, nombre FROM area WHERE activo=1 AND recibe_derivaciones=1 ORDER BY nombre");
	foreach($filas as $a)
	{
		if($a['clave'] === $area_origen)
		{
			continue;
		}
		$salida[$a['clave']] = $a['nombre'];
	}
	return $salida;
}

function clientes_asegurar_columnas()
{
	// Ya no se toca la tabla vieja "user". Los clientes van en cliente / usuario_cliente.
}

/**
 * Capacidad puntual de un área (columnas v3 de la tabla area).
 * $cap: 'puede_clientes' | 'recibe_derivaciones' | 'recibe_turnos'
 * Devuelve 1 o 0.
 */
function area_capacidad($clave, $cap)
{
	static $permitidas=null;
	if($permitidas===null)
	{
		$permitidas=array('puede_clientes','recibe_derivaciones','recibe_turnos');
	}
	crm_asegurar_area_flags();
	$clave=trim((string)$clave);
	if($clave==='' || !in_array($cap, $permitidas, true))
	{
		return 0;
	}
	$row=acceso_consulta_una(
		"SELECT {$cap} AS v FROM area WHERE clave=? AND activo=1 LIMIT 1",
		's',
		array($clave)
	);
	return ($row && intval($row['v'])===1) ? 1 : 0;
}

// Nombre histórico: ¿el área recibe turnos/atenciones? (ahora respeta la columna propia).
function acceso_area_atiende_clientes($clave='')
{
	if($clave==='')
	{
		$clave=function_exists('acceso_area_pantalla') ? acceso_area_pantalla() : '';
	}
	return area_capacidad($clave, 'recibe_turnos');
}

// Clientes para relacionarlos a una tarea (limitado; preferir ajax/clientes-buscar.php).
function clientes_lista_para_tareas($q='')
{
	$salida=array();
	if(function_exists('crm_clientes_buscar'))
	{
		$bus=crm_clientes_buscar(array(
			'area'=>acceso_area_pantalla(),
			'q'=>$q,
			'pagina'=>1,
			'limite'=>40
		));
		foreach($bus['lista'] as $cli)
		{
			$salida[]=array(
				'id'=>$cli['id'],
				'nombre'=>$cli['name'],
				'email'=>$cli['email'],
				'localidad'=>$cli['localidad'],
				'cuil_cuit'=>isset($cli['cuil_cuit']) ? $cli['cuil_cuit'] : '',
				'dni'=>isset($cli['dni']) ? $cli['dni'] : ''
			);
		}
		return $salida;
	}
	foreach(clientes_listado() as $cli)
	{
		$salida[]=array(
			'id'=>$cli['id'],
			'nombre'=>$cli['name'],
			'email'=>$cli['email'],
			'localidad'=>$cli['localidad']
		);
		if(count($salida)>=40) { break; }
	}
	return $salida;
}

function cliente_origenes_validos()
{
	return array('web','presencial','redes','whatsapp');
}

function cliente_origen_etiquetas()
{
	return array(
		'web'=>'Web',
		'presencial'=>'Presencial',
		'redes'=>'Redes',
		'whatsapp'=>'WhatsApp'
	);
}

function cliente_tipos_validos()
{
	// Emprendedor o empresa (municipalidad/organismo se tratan como empresa/organización).
	return array('emprendedor','empresa','municipalidad','organismo');
}

function cliente_tipo_etiquetas()
{
	return array(
		'emprendedor'=>'Emprendedor',
		'empresa'=>'Empresa',
		'municipalidad'=>'Municipalidad (empresa)',
		'organismo'=>'Organismo (empresa)'
	);
}

function cliente_tipo_etiqueta($tipo)
{
	$t=cliente_tipo_normalizar($tipo);
	$etq=cliente_tipo_etiquetas();
	return isset($etq[$t]) ? $etq[$t] : 'Cliente';
}

function cliente_tipo_normalizar($tipo)
{
	$t=strtolower(trim((string)$tipo));
	if($t==='persona')
	{
		return 'emprendedor';
	}
	if(in_array($t, cliente_tipos_validos(), true))
	{
		return $t;
	}
	return 'emprendedor';
}

function cliente_es_organizacion($tipo)
{
	return in_array(cliente_tipo_normalizar($tipo), array('empresa','municipalidad','organismo'), true);
}

function cliente_genero_form($genero)
{
	if($genero=='femenino')
	{
		return 'f';
	}
	if($genero=='masculino')
	{
		return 'm';
	}
	return 'm';
}

function cliente_genero_bd($form)
{
	return ($form=='f') ? 'femenino' : 'masculino';
}

function cliente_fila_pantalla($row)
{
	$tipo=cliente_tipo_normalizar(isset($row['tipo_cliente']) ? $row['tipo_cliente'] : 'emprendedor');
	return array(
		'id'=>isset($row['id_cliente']) ? intval($row['id_cliente']) : 0,
		'name'=>isset($row['nombre']) ? $row['nombre'] : '',
		'email'=>isset($row['correo']) ? $row['correo'] : '',
		'mobile'=>isset($row['telefono']) ? $row['telefono'] : '',
		'localidad'=>isset($row['localidad_nombre']) ? $row['localidad_nombre'] : '',
		'id_localidad'=>isset($row['id_localidad']) ? intval($row['id_localidad']) : 0,
		'id_provincia'=>isset($row['id_provincia']) ? intval($row['id_provincia']) : 0,
		'cuil_cuit'=>isset($row['cuil_cuit']) ? $row['cuil_cuit'] : '',
		'area_afectada'=>isset($row['area_clave']) ? $row['area_clave'] : '',
		'gender'=>cliente_genero_form(isset($row['genero']) ? $row['genero'] : ''),
		'address'=>isset($row['direccion']) ? $row['direccion'] : '',
		'tipo_cliente'=>$tipo
	);
}

function cliente_sql_base()
{
	return "SELECT c.id_cliente, c.nombre, c.correo, c.telefono, c.cuil_cuit, cp.genero, c.direccion, c.tipo_cliente, c.id_localidad,
		a.clave AS area_clave, l.nombre AS localidad_nombre, l.id_provincia
		FROM cliente c
		LEFT JOIN cliente_persona cp ON cp.id_cliente=c.id_cliente
		LEFT JOIN area a ON a.id_area=c.id_area_afectada
		LEFT JOIN localidad l ON l.id_localidad=c.id_localidad";
}

function clientes_listado($area_clave='')
{
	global $con;
	$salida=array();
	if(!$con || !acceso_tabla_existe('cliente'))
	{
		return $salida;
	}
	$sql=cliente_sql_base();
	$types='';
	$vals=array();
	if($area_clave!='' && $area_clave!='recepcion' && $area_clave!='sistemas' && $area_clave!='general')
	{
		$sql.=" WHERE (EXISTS (
			SELECT 1 FROM cliente_area ca INNER JOIN area a2 ON a2.id_area=ca.id_area
			WHERE ca.id_cliente=c.id_cliente AND a2.clave=?
		) OR EXISTS (
			SELECT 1 FROM atencion at INNER JOIN area a3 ON a3.id_area=at.id_area
			WHERE at.id_cliente=c.id_cliente AND a3.clave=?
		) OR EXISTS (
			SELECT 1 FROM tarea t INNER JOIN area a4 ON a4.id_area=t.id_area
			WHERE t.id_cliente=c.id_cliente AND a4.clave=?
		))";
		$types='sss';
		$vals[]=$area_clave;
		$vals[]=$area_clave;
		$vals[]=$area_clave;
	}
	$sql.=" ORDER BY c.tipo_cliente ASC, c.nombre ASC, c.id_cliente DESC";
	foreach(acceso_consulta($sql, $types, $vals) as $row)
	{
		$salida[]=cliente_fila_pantalla($row);
	}
	return $salida;
}

function cliente_traer($id)
{
	global $con;
	$id=intval($id);
	if($id<=0 || !$con || !acceso_tabla_existe('cliente'))
	{
		return null;
	}
	$row=acceso_consulta_una(cliente_sql_base()." WHERE c.id_cliente=? LIMIT 1", 'i', array($id));
	return $row ? cliente_fila_pantalla($row) : null;
}

function cliente_id_localidad_por_nombre($nombre)
{
	global $con;
	$nombre=trim($nombre);
	if($nombre=='' || !$con)
	{
		return 0;
	}
	$row=acceso_consulta_una("SELECT id_localidad FROM localidad WHERE nombre=? LIMIT 1", 's', array($nombre));
	return $row ? intval($row['id_localidad']) : 0;
}

function cliente_tipo_de($datos, $actual='emprendedor')
{
	$t=isset($datos['tipo_cliente']) ? $datos['tipo_cliente'] : $actual;
	return cliente_tipo_normalizar($t);
}

function usuario_cliente_por_correo($correo)
{
	$correo=trim($correo);
	if($correo=='' || !acceso_tabla_existe('usuario_cliente'))
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT id_usuario, nombre, correo, telefono, primer_acceso, activo FROM usuario_cliente WHERE correo=? LIMIT 1",
		's',
		array($correo)
	);
}

function usuario_cliente_crear($nombre, $correo, $clave, $telefono='', $dni='')
{
	$hash=acceso_hash_clave($clave);
	if($hash===false || $hash==='')
	{
		return 0;
	}
	$dni=crm_solo_numeros($dni);
	return acceso_insertar(
		"INSERT INTO usuario_cliente (nombre, correo, contrasena_hash, primer_acceso, dni, telefono, activo) VALUES (?,?,?,1,?,?,1)",
		'sssss',
		array($nombre, $correo, $hash, $dni, $telefono)
	);
}

function usuario_cliente_vincular($id_usuario, $id_cliente, $rol, $principal=0)
{
	$id_usuario=intval($id_usuario);
	$id_cliente=intval($id_cliente);
	$rol=($rol=='titular') ? 'titular' : 'representante';
	$principal=($principal==1) ? 1 : 0;
	if($id_usuario<=0 || $id_cliente<=0)
	{
		return 0;
	}
	$ya=acceso_consulta_una(
		"SELECT id FROM clientes_usuario_clientes WHERE id_usuario=? AND id_cliente=? LIMIT 1",
		'ii',
		array($id_usuario, $id_cliente)
	);
	if($ya)
	{
		acceso_ejecutar(
			"UPDATE clientes_usuario_clientes SET rol=?, es_principal=?, fecha_hasta=NULL WHERE id=?",
			'sii',
			array($rol, $principal, intval($ya['id']))
		);
		return intval($ya['id']);
	}
	return acceso_insertar(
		"INSERT INTO clientes_usuario_clientes (id_usuario, id_cliente, rol, es_principal) VALUES (?,?,?,?)",
		'iisi',
		array($id_usuario, $id_cliente, $rol, $principal)
	);
}

function cliente_id_usuario_titular($id_cliente)
{
	$row=acceso_consulta_una(
		"SELECT id_usuario FROM clientes_usuario_clientes WHERE id_cliente=? AND rol='titular' AND fecha_hasta IS NULL LIMIT 1",
		'i',
		array(intval($id_cliente))
	);
	return $row ? intval($row['id_usuario']) : 0;
}

function usuario_tiene_ficha_persona($id_usuario)
{
	$row=acceso_consulta_una(
		"SELECT cuc.id FROM clientes_usuario_clientes cuc INNER JOIN cliente c ON c.id_cliente=cuc.id_cliente WHERE cuc.id_usuario=? AND cuc.rol='titular' AND c.tipo_cliente='emprendedor' AND cuc.fecha_hasta IS NULL LIMIT 1",
		'i',
		array(intval($id_usuario))
	);
	return $row ? 1 : 0;
}

function cliente_representantes_de($id_cliente)
{
	$id=intval($id_cliente);
	if($id<=0 || !acceso_tabla_existe('clientes_usuario_clientes'))
	{
		return array();
	}
	return acceso_consulta(
		"SELECT cuc.id, cuc.id_usuario, u.nombre, u.telefono, u.correo, cuc.es_principal,
			(SELECT x.id_cliente FROM clientes_usuario_clientes x INNER JOIN cliente c2 ON c2.id_cliente=x.id_cliente WHERE x.id_usuario=cuc.id_usuario AND x.rol='titular' AND c2.tipo_cliente='emprendedor' AND x.fecha_hasta IS NULL LIMIT 1) AS id_cliente_persona
		FROM clientes_usuario_clientes cuc
		INNER JOIN usuario_cliente u ON u.id_usuario=cuc.id_usuario
		WHERE cuc.id_cliente=? AND cuc.rol='representante' AND cuc.fecha_hasta IS NULL
		ORDER BY cuc.es_principal DESC, cuc.id ASC",
		'i',
		array($id)
	);
}

function cliente_representantes_desde_post()
{
	$nombres=isset($_POST['representante_nombre']) ? $_POST['representante_nombre'] : array();
	if(!is_array($nombres))
	{
		$nombres=array($nombres);
	}
	$tels=isset($_POST['representante_telefono']) && is_array($_POST['representante_telefono']) ? $_POST['representante_telefono'] : array();
	$correos=isset($_POST['representante_correo']) && is_array($_POST['representante_correo']) ? $_POST['representante_correo'] : array();
	$ids=isset($_POST['representante_id']) && is_array($_POST['representante_id']) ? $_POST['representante_id'] : array();
	$claves=isset($_POST['representante_clave']) && is_array($_POST['representante_clave']) ? $_POST['representante_clave'] : array();
	$dnis=isset($_POST['representante_dni']) && is_array($_POST['representante_dni']) ? $_POST['representante_dni'] : array();
	$salida=array();
	foreach($nombres as $i=>$nom)
	{
		$salida[]=array(
			'id'=>isset($ids[$i]) ? intval($ids[$i]) : 0,
			'nombre'=>$nom,
			'telefono'=>isset($tels[$i]) ? $tels[$i] : '',
			'correo'=>isset($correos[$i]) ? $correos[$i] : '',
			'clave'=>isset($claves[$i]) ? $claves[$i] : '',
			'dni'=>isset($dnis[$i]) ? $dnis[$i] : ''
		);
	}
	return $salida;
}

function cliente_representantes_guardar($id_empresa, $lista)
{
	$id_empresa=intval($id_empresa);
	if($id_empresa<=0 || !acceso_tabla_existe('clientes_usuario_clientes') || !acceso_tabla_existe('usuario_cliente'))
	{
		return 'No se pudo guardar el responsable.';
	}
	if(!is_array($lista))
	{
		$lista=array();
	}
	$quedan=array();
	$n=0;
	foreach($lista as $rep)
	{
		$nombre=isset($rep['nombre']) ? trim($rep['nombre']) : '';
		if($nombre=='')
		{
			continue;
		}
		$tel=isset($rep['telefono']) ? trim($rep['telefono']) : '';
		$correo=isset($rep['correo']) ? trim($rep['correo']) : '';
		if($correo=='')
		{
			return 'Falta el correo del responsable.';
		}
		$dni_rep=crm_solo_numeros(isset($rep['dni']) ? $rep['dni'] : '');
		$clave=isset($rep['clave']) ? trim($rep['clave']) : '';
		$principal=($n==0) ? 1 : 0;
		$u=usuario_cliente_por_correo($correo);
		if(!$u)
		{
			if($clave=='')
			{
				$clave=crm_clave_desde_dni($dni_rep);
			}
			if($clave=='')
			{
				return 'Falta el DNI del responsable (se usa como clave, mínimo 6 números).';
			}
			$id_u=usuario_cliente_crear($nombre, $correo, $clave, $tel, $dni_rep);
			if($id_u<=0)
			{
				return 'No se pudo crear el usuario del responsable.';
			}
		}
		else
		{
			// Usuario ya existente (p. ej. cliente persona): conserva su clave y su primer ingreso.
			$id_u=intval($u['id_usuario']);
			if($tel!='')
			{
				acceso_ejecutar("UPDATE usuario_cliente SET telefono=? WHERE id_usuario=?", 'si', array($tel, $id_u));
			}
			if($clave!='' && intval($u['primer_acceso'])==1)
			{
				if(strlen($clave)<6)
				{
					return 'La clave del responsable debe tener al menos 6 caracteres.';
				}
				$hash=acceso_hash_clave($clave);
				if($hash===false || $hash==='')
				{
					return 'No se pudo guardar la clave del responsable.';
				}
				acceso_ejecutar("UPDATE usuario_cliente SET contrasena_hash=? WHERE id_usuario=?", 'si', array($hash, $id_u));
			}
		}
		$id_v=usuario_cliente_vincular($id_u, $id_empresa, 'representante', $principal);
		if($id_v>0)
		{
			$quedan[]=$id_v;
			$n++;
		}
	}
	if($n==0)
	{
		// Responsable opcional: se puede cargar después.
		return '';
	}
	foreach(cliente_representantes_de($id_empresa) as $actual)
	{
		$aid=intval($actual['id']);
		if(!in_array($aid, $quedan))
		{
			acceso_ejecutar("UPDATE clientes_usuario_clientes SET fecha_hasta=CURDATE() WHERE id=? AND id_cliente=?", 'ii', array($aid, $id_empresa));
		}
	}
	return '';
}

// Ingreso: entra un usuario_cliente (nunca la empresa).
// Sesión para el resto del CRM:
//   $_SESSION['login']              correo de quien entró
//   $_SESSION['id_usuario']         id de usuario_cliente
//   $_SESSION['primer_acceso']      1 = debe cambiar la clave y volver a entrar
//   $_SESSION['id']                 id_cliente de la cuenta activa
//   $_SESSION['id_representante']   id del vínculo si actúa por una empresa
//   $_SESSION['cuenta_tipo']        'persona' o 'empresa'
//   $_SESSION['cuenta_id']          id_cliente de la cuenta que está gestionando
//   $_SESSION['cuenta_nombre']      nombre a mostrar
// acceso_cliente_cuenta() devuelve tipo, id y nombre de la cuenta activa.

function acceso_cliente_cuentas_de($id_usuario)
{
	$id_usuario=intval($id_usuario);
	$cuentas=array();
	if($id_usuario<=0 || !acceso_tabla_existe('clientes_usuario_clientes'))
	{
		return $cuentas;
	}
	$filas=acceso_consulta(
		"SELECT cuc.id, cuc.rol, c.id_cliente, c.nombre, c.tipo_cliente
		FROM clientes_usuario_clientes cuc
		INNER JOIN cliente c ON c.id_cliente=cuc.id_cliente
		WHERE cuc.id_usuario=? AND c.activo=1 AND cuc.fecha_hasta IS NULL
		ORDER BY cuc.rol ASC, cuc.es_principal DESC, cuc.id ASC",
		'i',
		array($id_usuario)
	);
	$vistos=array();
	foreach($filas as $row)
	{
		$cid=intval($row['id_cliente']);
		$tipo_real=cliente_tipo_normalizar(isset($row['tipo_cliente']) ? $row['tipo_cliente'] : 'emprendedor');
		$tipo=cliente_es_organizacion($tipo_real) ? 'empresa' : 'persona';
		$marca=$tipo_real.$cid;
		if(isset($vistos[$marca]))
		{
			continue;
		}
		$vistos[$marca]=1;
		$cuentas[]=array(
			'tipo'=>$tipo,
			'tipo_cliente'=>$tipo_real,
			'id'=>$cid,
			'nombre'=>isset($row['nombre']) ? $row['nombre'] : '',
			'id_representante'=>($tipo=='empresa') ? intval($row['id']) : 0
		);
	}
	return $cuentas;
}

function acceso_cliente_aplicar_cuenta($cuenta)
{
	$_SESSION['cuenta_tipo']=isset($cuenta['tipo']) ? $cuenta['tipo'] : '';
	$_SESSION['cuenta_tipo_cliente']=isset($cuenta['tipo_cliente'])
		? cliente_tipo_normalizar($cuenta['tipo_cliente'])
		: (($_SESSION['cuenta_tipo']=='empresa') ? 'empresa' : 'emprendedor');
	$_SESSION['cuenta_id']=isset($cuenta['id']) ? intval($cuenta['id']) : 0;
	$_SESSION['cuenta_nombre']=isset($cuenta['nombre']) ? $cuenta['nombre'] : '';
	$_SESSION['id_representante']=isset($cuenta['id_representante']) ? intval($cuenta['id_representante']) : 0;
	if($_SESSION['cuenta_id']>0)
	{
		$_SESSION['id']=$_SESSION['cuenta_id'];
	}
	if($_SESSION['cuenta_tipo']=='persona' && $_SESSION['cuenta_nombre']!='')
	{
		$_SESSION['name']=$_SESSION['cuenta_nombre'];
	}
}

function acceso_cliente_cuenta()
{
	if(!isset($_SESSION['cuenta_tipo']) || $_SESSION['cuenta_tipo']=='' || !isset($_SESSION['cuenta_id']) || intval($_SESSION['cuenta_id'])<=0)
	{
		return null;
	}
	$tipo_cli=isset($_SESSION['cuenta_tipo_cliente'])
		? cliente_tipo_normalizar($_SESSION['cuenta_tipo_cliente'])
		: (($_SESSION['cuenta_tipo']=='empresa') ? 'empresa' : 'emprendedor');
	return array(
		'tipo'=>$_SESSION['cuenta_tipo'],
		'tipo_cliente'=>$tipo_cli,
		'id'=>intval($_SESSION['cuenta_id']),
		'nombre'=>isset($_SESSION['cuenta_nombre']) ? $_SESSION['cuenta_nombre'] : '',
		'id_representante'=>isset($_SESSION['id_representante']) ? intval($_SESSION['id_representante']) : 0
	);
}

function acceso_cliente_entrar($correo, $clave)
{
	// Entra usuario_cliente. La empresa no inicia sesión.
	// 1) Cliente persona: un usuario titular de esa ficha.
	// 2) Solo responsable: un usuario vinculado a la empresa (rol representante).
	// 3) Los dos: el mismo usuario; después elige perfil.
	// 4) Primer acceso: cambia la clave y vuelve a iniciar sesión.
	$correo=trim($correo);
	if($correo=='' || $clave==='' || !acceso_tabla_existe('usuario_cliente'))
	{
		return 'no';
	}
	$u=acceso_consulta_una(
		"SELECT id_usuario, nombre, correo, contrasena_hash, primer_acceso, activo FROM usuario_cliente WHERE correo=? AND activo=1 LIMIT 1",
		's',
		array($correo)
	);
	if(!$u || !acceso_verificar_clave($clave, isset($u['contrasena_hash']) ? $u['contrasena_hash'] : ''))
	{
		return 'no';
	}
	$id_usuario=intval($u['id_usuario']);
	$_SESSION['login']=$correo;
	$_SESSION['id_usuario']=$id_usuario;
	$_SESSION['name']=$u['nombre'];
	$_SESSION['id']=0;
	$_SESSION['id_representante']=0;
	$_SESSION['cuenta_tipo']='';
	$_SESSION['cuenta_id']=0;
	$_SESSION['cuenta_nombre']='';
	$_SESSION['cuentas_cliente']=array();
	if(isset($u['primer_acceso']) && intval($u['primer_acceso'])==1)
	{
		$_SESSION['primer_acceso']=1;
		return 'primer';
	}
	$_SESSION['primer_acceso']=0;
	$cuentas=acceso_cliente_cuentas_de($id_usuario);
	$_SESSION['cuentas_cliente']=$cuentas;
	if(count($cuentas)==0)
	{
		unset($_SESSION['login'], $_SESSION['id_usuario'], $_SESSION['name'], $_SESSION['id'], $_SESSION['id_representante'], $_SESSION['cuenta_tipo'], $_SESSION['cuenta_id'], $_SESSION['cuenta_nombre'], $_SESSION['cuentas_cliente'], $_SESSION['primer_acceso']);
		return 'no';
	}
	if(count($cuentas)==1)
	{
		acceso_cliente_aplicar_cuenta($cuentas[0]);
		return 'ok';
	}
	return 'elegir';
}

function acceso_cliente_cambiar_clave($actual, $nueva)
{
	$nueva=trim($nueva);
	if($nueva=='' || strlen($nueva)<6)
	{
		return 'La nueva clave debe tener al menos 6 caracteres.';
	}
	$id_usuario=isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;
	if($id_usuario<=0)
	{
		return 'No se encontró la cuenta.';
	}
	$row=acceso_consulta_una("SELECT contrasena_hash FROM usuario_cliente WHERE id_usuario=? LIMIT 1", 'i', array($id_usuario));
	if(!$row)
	{
		return 'No se encontró la cuenta.';
	}
	$forzar=isset($_SESSION['primer_acceso']) && intval($_SESSION['primer_acceso'])==1;
	if(!$forzar && !acceso_verificar_clave($actual, isset($row['contrasena_hash']) ? $row['contrasena_hash'] : ''))
	{
		return 'La contraseña actual no coincide.';
	}
	$hash=acceso_hash_clave($nueva);
	if($hash===false || $hash==='')
	{
		return 'No se pudo guardar la nueva clave.';
	}
	acceso_ejecutar("UPDATE usuario_cliente SET contrasena_hash=?, primer_acceso=0 WHERE id_usuario=?", 'si', array($hash, $id_usuario));
	$_SESSION['primer_acceso']=0;
	return '';
}

function cliente_alta($datos)
{
	global $con;
	if(!$con || !acceso_tabla_existe('cliente') || !acceso_tabla_existe('usuario_cliente'))
	{
		return 'No se pudo guardar el cliente.';
	}
	$nombre=isset($datos['nombre']) ? trim($datos['nombre']) : '';
	$correo=isset($datos['correo']) ? trim($datos['correo']) : '';
	$cuil=isset($datos['cuil_cuit']) ? trim($datos['cuil_cuit']) : '';
	$area=isset($datos['area']) ? $datos['area'] : '';
	$localidad=isset($datos['localidad']) ? trim($datos['localidad']) : '';
	$tipo=cliente_tipo_de($datos, 'emprendedor');
	$es_org=cliente_es_organizacion($tipo);
	$id_area=acceso_id_por_clave('area', $area);
	$id_loc=isset($datos['id_localidad']) ? intval($datos['id_localidad']) : 0;
	if($id_loc<=0)
	{
		$id_loc=cliente_id_localidad_por_nombre($localidad);
	}
	if($nombre=='' || $correo=='')
	{
		return 'Faltan el nombre o el correo.';
	}
	if($cuil=='')
	{
		return 'Falta el CUIL o CUIT.';
	}
	if($id_loc<=0)
	{
		return 'Esa localidad no está cargada.';
	}
	// Área: si no eligen, intentar con el área activa del usuario antes de caer en Recepción.
	if($id_area<=0)
	{
		$area_mia = function_exists('acceso_area_activa') ? acceso_area_activa() : (function_exists('acceso_area') ? acceso_area() : '');
		if($area_mia!='' && $area_mia!='general' && $area_mia!='sistemas' && $area_mia!='rrhh')
		{
			$id_area = acceso_id_por_clave('area', $area_mia);
		}
	}
	if($id_area<=0)
	{
		$id_area=acceso_id_por_clave('area', 'recepcion');
	}
	$dni_cli=crm_solo_numeros(isset($datos['dni']) ? $datos['dni'] : '');
	$clave=isset($datos['clave']) ? trim($datos['clave']) : '';
	if(!$es_org && $clave=='')
	{
		$clave=crm_clave_desde_dni($dni_cli);
	}
	$u=usuario_cliente_por_correo($correo);
	if(!$es_org)
	{
		if($u && usuario_tiene_ficha_persona(intval($u['id_usuario'])))
		{
			return 'Ese correo ya está cargado. Probá con otro.';
		}
		if(!$u && $clave=='')
		{
			return 'Falta el DNI del cliente (se usa como clave, mínimo 6 números).';
		}
	}
	$hay2=acceso_consulta_una("SELECT id_cliente FROM cliente WHERE cuil_cuit=? LIMIT 1", 's', array($cuil));
	if($hay2)
	{
		return 'Ese CUIL/CUIT ya está cargado.';
	}
	$tel=isset($datos['telefono']) ? trim($datos['telefono']) : '';
	$dir=isset($datos['direccion']) ? trim($datos['direccion']) : '';
	$genero=cliente_genero_bd(isset($datos['genero']) ? $datos['genero'] : 'm');
	$origen=isset($datos['origen']) ? $datos['origen'] : '';
	$ok_origen=cliente_origenes_validos();
	if(!in_array($origen, $ok_origen, true))
	{
		$origen='presencial';
	}
	$id_area_sql=($id_area>0) ? $id_area : null;
	$id_emp_alta=isset($datos['id_empleado_alta']) ? intval($datos['id_empleado_alta']) : (function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0);
	$id_emp_sql=($id_emp_alta>0) ? $id_emp_alta : null;

	$tiene_col_emp=function_exists('crm_columna_existe') ? crm_columna_existe('cliente', 'id_empleado_alta') : false;
	if($tiene_col_emp)
	{
		$id=acceso_insertar(
			"INSERT INTO cliente (tipo_cliente, nombre, correo, telefono, id_localidad, direccion, cuil_cuit, id_area_afectada, id_empleado_alta, origen_contacto)
			VALUES (?,?,?,?,?,?,?,?,?,?)",
			'ssssissiis',
			array($tipo, $nombre, $correo, $tel, $id_loc, $dir, $cuil, $id_area_sql, $id_emp_sql, $origen)
		);
	}
	else
	{
		$id=acceso_insertar(
			"INSERT INTO cliente (tipo_cliente, nombre, correo, telefono, id_localidad, direccion, cuil_cuit, id_area_afectada, origen_contacto)
			VALUES (?,?,?,?,?,?,?,?,?)",
			'ssssissis',
			array($tipo, $nombre, $correo, $tel, $id_loc, $dir, $cuil, $id_area_sql, $origen)
		);
	}
	if($id<=0)
	{
		return 'No se pudo dar de alta el cliente. Intentá de nuevo.';
	}
	$GLOBALS['crm_ultimo_cliente_alta_id'] = $id;
	if($es_org)
	{
		acceso_insertar("INSERT INTO cliente_empresa (id_cliente, razon_social) VALUES (?,?)", 'is', array($id, $nombre));
		$reps=isset($datos['representantes']) && is_array($datos['representantes']) ? $datos['representantes'] : array();
		$err_rep=cliente_representantes_guardar($id, $reps);
		if($err_rep!='')
		{
			return $err_rep;
		}
	}
	else
	{
		acceso_insertar("INSERT INTO cliente_persona (id_cliente, genero, dni) VALUES (?,?,?)", 'iss', array($id, $genero, $dni_cli));
		if($u)
		{
			$id_u=intval($u['id_usuario']);
			acceso_ejecutar("UPDATE usuario_cliente SET nombre=?, telefono=?, dni=? WHERE id_usuario=?", 'sssi', array($nombre, $tel, $dni_cli, $id_u));
		}
		else
		{
			$id_u=usuario_cliente_crear($nombre, $correo, $clave, $tel, $dni_cli);
			if($id_u<=0)
			{
				return 'No se pudo crear el usuario de ingreso.';
			}
		}
		usuario_cliente_vincular($id_u, $id, 'titular', 1);
	}
	$destinos=array();
	if($id_area>0)
	{
		$destinos[]=$id_area;
	}
	$areas_extra=isset($datos['areas']) && is_array($datos['areas']) ? $datos['areas'] : array();
	foreach($areas_extra as $clave_area)
	{
		$id_a=acceso_id_por_clave('area', $clave_area);
		if($id_a>0 && !in_array($id_a, $destinos))
		{
			$destinos[]=$id_a;
		}
	}
	foreach($destinos as $id_a)
	{
		// El alta de cliente NO genera atención. La atención se carga aparte (dar turno).
		$abrir_turno=false;
		if(isset($datos['abrir_turno']))
		{
			$abrir_turno=(bool)$datos['abrir_turno'];
		}
		if(!$abrir_turno)
		{
			if(function_exists('crm_vincular_cliente_area'))
			{
				crm_vincular_cliente_area($id, $id_a);
			}
			continue;
		}
		crm_atencion_abrir(array(
			'id_cliente'=>$id,
			'id_area'=>$id_a,
			'motivo'=>isset($datos['motivo']) ? $datos['motivo'] : 'consulta',
			'canal'=>'presencial',
			'detalle'=>isset($datos['detalle_atencion']) ? trim($datos['detalle_atencion']) : '',
			'fecha_turno'=>date('Y-m-d H:i:s')
		));
	}
	return '';
}

function cliente_actualizar($id, $datos)
{
	global $con;
	$id=intval($id);
	$actual=cliente_traer($id);
	if(!$actual || !$con)
	{
		return 'No se encontró el cliente.';
	}
	$nombre=isset($datos['nombre']) ? trim($datos['nombre']) : '';
	$cuil=isset($datos['cuil_cuit']) ? trim($datos['cuil_cuit']) : '';
	$area=isset($datos['area']) ? $datos['area'] : '';
	$localidad=isset($datos['localidad']) ? trim($datos['localidad']) : '';
	$tipo=cliente_tipo_de($datos, isset($actual['tipo_cliente']) ? $actual['tipo_cliente'] : 'emprendedor');
	$es_org=cliente_es_organizacion($tipo);
	$id_area=acceso_id_por_clave('area', $area);
	$id_loc=$actual['id_localidad'];
	if(isset($datos['id_localidad']) && intval($datos['id_localidad'])>0)
	{
		$id_loc=intval($datos['id_localidad']);
	}
	else if($localidad!='')
	{
		$bus=cliente_id_localidad_por_nombre($localidad);
		if($bus<=0)
		{
			return 'Esa localidad no está cargada.';
		}
		$id_loc=$bus;
	}
	if($nombre=='')
	{
		return 'Falta el nombre del cliente.';
	}
	if($cuil=='')
	{
		return 'Falta el CUIL o CUIT.';
	}
	if($id_area<=0 && isset($actual['area_afectada']))
	{
		$id_area=acceso_id_por_clave('area', $actual['area_afectada']);
	}
	if($id_loc<=0)
	{
		return 'Falta la localidad.';
	}
	$tel=isset($datos['telefono']) ? trim($datos['telefono']) : '';
	$dir=isset($datos['direccion']) ? trim($datos['direccion']) : '';
	$genero=cliente_genero_bd(isset($datos['genero']) ? $datos['genero'] : 'm');
	$correo=isset($datos['correo']) ? trim($datos['correo']) : '';
	if($correo=='')
	{
		$correo=isset($actual['email']) ? $actual['email'] : '';
	}
	if($correo!='')
	{
		if(!filter_var($correo, FILTER_VALIDATE_EMAIL))
		{
			return 'El correo electrónico no es válido.';
		}
		$hay_correo=acceso_consulta_una("SELECT id_cliente FROM cliente WHERE correo=? AND id_cliente<>? LIMIT 1", 'si', array($correo, $id));
		if($hay_correo)
		{
			return 'Ese correo electrónico ya está registrado en otro cliente.';
		}
		$id_u_check=cliente_id_usuario_titular($id);
		if($id_u_check>0)
		{
			$hay_u=acceso_consulta_una("SELECT id_usuario FROM usuario_cliente WHERE correo=? AND id_usuario<>? LIMIT 1", 'si', array($correo, $id_u_check));
			if($hay_u)
			{
				return 'Ese correo electrónico ya está en uso por otra cuenta de usuario.';
			}
		}
		else
		{
			$hay_u=acceso_consulta_una("SELECT id_usuario FROM usuario_cliente WHERE correo=? LIMIT 1", 's', array($correo));
			if($hay_u)
			{
				return 'Ese correo electrónico ya está en uso por otra cuenta de usuario.';
			}
		}
	}
	$hay_cuil=acceso_consulta_una("SELECT id_cliente FROM cliente WHERE cuil_cuit=? AND id_cliente<>? LIMIT 1", 'si', array($cuil, $id));
	if($hay_cuil)
	{
		return 'Ese CUIL/CUIT ya está cargado.';
	}
	$ok=acceso_ejecutar(
		"UPDATE cliente SET nombre=?, correo=?, telefono=?, direccion=?, cuil_cuit=?, id_localidad=?, id_area_afectada=?, tipo_cliente=? WHERE id_cliente=?",
		'sssssissi',
		array($nombre, $correo, $tel, $dir, $cuil, $id_loc, $id_area, $tipo, $id)
	);
	if(!$ok)
	{
		return 'No se pudieron guardar los cambios.';
	}
	$era_org=cliente_es_organizacion(isset($actual['tipo_cliente']) ? $actual['tipo_cliente'] : '');
	if($es_org)
	{
		$ce=acceso_consulta_una("SELECT id_cliente FROM cliente_empresa WHERE id_cliente=? LIMIT 1", 'i', array($id));
		if($ce)
		{
			acceso_ejecutar("UPDATE cliente_empresa SET razon_social=? WHERE id_cliente=?", 'si', array($nombre, $id));
		}
		else
		{
			acceso_insertar("INSERT INTO cliente_empresa (id_cliente, razon_social) VALUES (?,?)", 'is', array($id, $nombre));
		}
		acceso_ejecutar("DELETE FROM cliente_persona WHERE id_cliente=?", 'i', array($id));
		if(isset($datos['representantes']))
		{
			$err_rep=cliente_representantes_guardar($id, $datos['representantes']);
			if($err_rep!='')
			{
				return $err_rep;
			}
		}
	}
	else
	{
		$cp=acceso_consulta_una("SELECT id_cliente FROM cliente_persona WHERE id_cliente=? LIMIT 1", 'i', array($id));
		if($cp)
		{
			acceso_ejecutar("UPDATE cliente_persona SET genero=? WHERE id_cliente=?", 'si', array($genero, $id));
		}
		else
		{
			acceso_insertar("INSERT INTO cliente_persona (id_cliente, genero) VALUES (?,?)", 'is', array($id, $genero));
		}
		acceso_ejecutar("DELETE FROM cliente_empresa WHERE id_cliente=?", 'i', array($id));
		$clave=isset($datos['clave']) ? trim($datos['clave']) : '';
		$id_u=cliente_id_usuario_titular($id);
		if($id_u<=0)
		{
			$u=($correo!='') ? usuario_cliente_por_correo($correo) : null;
			if($u)
			{
				$id_u=intval($u['id_usuario']);
			}
			else
			{
				if($clave=='' || strlen($clave)<6)
				{
					return 'Falta la clave de ingreso (mínimo 6 caracteres).';
				}
				$id_u=usuario_cliente_crear($nombre, $correo, $clave, $tel);
				if($id_u<=0)
				{
					return 'No se pudo crear el usuario de ingreso.';
				}
			}
			usuario_cliente_vincular($id_u, $id, 'titular', 1);
		}
		else
		{
			acceso_ejecutar("UPDATE usuario_cliente SET nombre=?, correo=?, telefono=? WHERE id_usuario=?", 'sssi', array($nombre, $correo, $tel, $id_u));
			if($clave!='')
			{
				if(strlen($clave)<6)
				{
					return 'La clave de ingreso debe tener al menos 6 caracteres.';
				}
				$hash=acceso_hash_clave($clave);
				if($hash===false || $hash==='')
				{
					return 'No se pudo guardar la clave.';
				}
				acceso_ejecutar("UPDATE usuario_cliente SET contrasena_hash=?, primer_acceso=1 WHERE id_usuario=?", 'si', array($hash, $id_u));
			}
		}
	}
	return '';
}

function tareas_leer_cliente_post()
{
	$id=isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
	$nombre=isset($_POST['cliente_nombre']) ? trim($_POST['cliente_nombre']) : '';
	if($id<=0)
	{
		return array('id'=>0, 'nombre'=>'');
	}
	foreach(clientes_lista_para_tareas() as $cli)
	{
		if($cli['id']==$id)
		{
			return array('id'=>$cli['id'], 'nombre'=>$cli['nombre']);
		}
	}
	return array('id'=>$id, 'nombre'=>$nombre);
}

function tarea_texto_cliente($tarea)
{
	if(isset($tarea['cliente_nombre']) && trim($tarea['cliente_nombre'])!='')
	{
		return $tarea['cliente_nombre'];
	}
	return '';
}

function acceso_armar_persona($nombre, $area)
{
	$nombre=trim($nombre);
	if($nombre=='')
	{
		return '';
	}
	return $nombre.' - '.acceso_etiqueta_corta_area($area);
}

function acceso_etiquetas_area_con_tareas()
{
	$claves=tareas_claves_sesion();
	$salida=array();
	foreach(acceso_etiquetas_area() as $clave=>$titulo)
	{
		if(isset($claves[$clave]))
		{
			$salida[$clave]=$titulo;
		}
	}
	return $salida;
}

function acceso_roles_catalogo()
{
	static $cache=null;
	if($cache!==null)
	{
		return $cache;
	}
	global $con;
	$cache=array();
	if($con)
	{
		$q=@mysqli_query($con,"SELECT clave, nombre FROM rol ORDER BY id_rol");
		if($q)
		{
			while($row=mysqli_fetch_assoc($q))
			{
				$cache[$row['clave']]=$row['nombre'];
			}
		}
	}
	if(count($cache)==0)
	{
		$cache=array(
			'superadmin'=>'Superadmin',
			'coordinador_general'=>'Coordinador general',
			'coordinador'=>'Coordinador de área',
			'empleado'=>'Colaborador',
			'presidencia'=>'Presidencia'
		);
	}
	return $cache;
}

function acceso_roles_para_alta()
{
	// Alta RRHH: los 4 roles base. Permisos/extras adicionales los administra Sistemas.
	return array(
		'empleado'=>'Colaborador',
		'coordinador'=>'Coordinador de área',
		'presidencia'=>'Presidencia',
		'coordinador_general'=>'Coordinador general'
	);
}

function acceso_roles_extras_permitidos()
{
	// Extras: otra área (empleado/coord) o sumar Coordinador general.
	// Presidencia es exclusiva: nunca como rol adicional.
	$roles=array(
		'empleado'=>'Colaborador',
		'coordinador'=>'Coordinador de área',
		'coordinador_general'=>'Coordinador general'
	);
	return $roles;
}

/** Valida combinación rol/área (Presidencia y CG sin área; empleado/coord con área). */
function acceso_validar_rol_area($rol, $area, $id_empleado=0)
{
	$rol=trim((string)$rol);
	$area=trim((string)$area);
	if($rol==='presidencia' || $rol==='coordinador_general')
	{
		if($area!=='')
		{
			return ($rol==='presidencia')
				? 'Presidencia no tiene área: es un perfil exclusivo.'
				: 'Coordinador general no se carga con área; si además opera un área, Sistemas le suma ese permiso.';
		}
		if($id_empleado>0 && $rol==='presidencia' && function_exists('crm_roles_adicionales_de'))
		{
			$extras=crm_roles_adicionales_de($id_empleado);
			if(count($extras)>0)
			{
				return 'Presidencia no puede tener roles ni áreas adicionales. Quitá los extras primero.';
			}
		}
		return '';
	}
	if($area==='')
	{
		return 'Indicá el área para colaborador o coordinador de área.';
	}
	if($area==='presidencia' || $area==='general')
	{
		return 'Esa área no se usa en el alta de personal operativo.';
	}
	$ok=acceso_etiquetas_area_asignables($rol);
	if(!isset($ok[$area]))
	{
		return 'El área no es válida para ese rol.';
	}
	return '';
}

function acceso_id_por_clave($tabla, $clave)
{
	global $con;
	if(!$con || $clave=='')
	{
		return 0;
	}
	if($tabla!='rol' && $tabla!='area')
	{
		return 0;
	}
	$campo=($tabla=='rol') ? 'id_rol' : 'id_area';
	$q=@mysqli_query($con,"SELECT ".$campo." AS id FROM ".$tabla." WHERE clave='".acceso_sql($clave)."' LIMIT 1");
	if(!$q)
	{
		return 0;
	}
	$row=mysqli_fetch_assoc($q);
	return $row ? intval($row['id']) : 0;
}

if(!function_exists('crm_area_id'))
{
	function crm_area_id($clave)
	{
		return acceso_id_por_clave('area', $clave);
	}
}

function acceso_clave_por_id($tabla, $id)
{
	global $con;
	$id=intval($id);
	if(!$con || $id<=0)
	{
		return '';
	}
	if($tabla!='rol' && $tabla!='area')
	{
		return '';
	}
	$campo=($tabla=='rol') ? 'id_rol' : 'id_area';
	$q=@mysqli_query($con, "SELECT clave FROM ".$tabla." WHERE ".$campo."=".$id." LIMIT 1");
	if(!$q)
	{
		return '';
	}
	$row=mysqli_fetch_assoc($q);
	return $row && isset($row['clave']) ? $row['clave'] : '';
}

function acceso_fila_a_persona($row)
{
	$nombre=trim((isset($row['nombres']) ? $row['nombres'] : '').' '.(isset($row['apellido']) ? $row['apellido'] : ''));
	$area=isset($row['area_clave']) ? $row['area_clave'] : '';
	$rol=isset($row['rol_clave']) ? $row['rol_clave'] : '';
	return array(
		'id'=>isset($row['id_empleado']) ? intval($row['id_empleado']) : 0,
		'usuario'=>isset($row['nombre_usuario']) ? $row['nombre_usuario'] : '',
		'area'=>$area,
		'rol'=>$rol,
		'nombre'=>$nombre,
		'nombres'=>isset($row['nombres']) ? $row['nombres'] : '',
		'apellido'=>isset($row['apellido']) ? $row['apellido'] : '',
		'correo'=>isset($row['correo']) ? $row['correo'] : '',
		'dni'=>isset($row['dni']) ? $row['dni'] : '',
		'cuil'=>isset($row['cuil']) ? $row['cuil'] : '',
		'telefono'=>isset($row['telefono']) ? $row['telefono'] : '',
		'telefono_alt'=>isset($row['telefono_alt']) ? $row['telefono_alt'] : '',
		'direccion'=>isset($row['direccion']) ? $row['direccion'] : '',
		'fecha_nacimiento'=>isset($row['fecha_nacimiento']) ? $row['fecha_nacimiento'] : '',
		'fecha_ingreso'=>isset($row['fecha_ingreso']) ? $row['fecha_ingreso'] : '',
		'puesto'=>isset($row['puesto']) ? $row['puesto'] : '',
		'tipo_vinculo'=>isset($row['tipo_vinculo']) ? $row['tipo_vinculo'] : '',
		'estado_laboral'=>isset($row['estado_laboral']) ? $row['estado_laboral'] : 'activo',
		'modalidad'=>isset($row['modalidad']) ? $row['modalidad'] : '',
		'condicion_fiscal'=>isset($row['condicion_fiscal']) ? $row['condicion_fiscal'] : '',
		'persona'=>acceso_armar_persona($nombre, $area),
		'activo'=>isset($row['activo']) ? intval($row['activo']) : 1
	);
}

function acceso_sql_empleado_base()
{
	return "SELECT e.*, r.clave AS rol_clave, a.clave AS area_clave
		FROM empleado e
		INNER JOIN rol r ON r.id_rol=e.id_rol
		LEFT JOIN area a ON a.id_area=e.id_area";
}

// Personal interno: sale de la tabla empleado.
function acceso_personal_completo($incluir_super=false)
{
	global $con;
	$lista=array();
	if(!$con)
	{
		return $lista;
	}
	$extra=$incluir_super ? '' : " AND r.clave<>'superadmin'";
	$q=@mysqli_query($con, acceso_sql_empleado_base()." WHERE e.activo=1".$extra." ORDER BY a.nombre, e.apellido, e.nombres");
	if($q)
	{
		while($row=mysqli_fetch_assoc($q))
		{
			$lista[]=acceso_fila_a_persona($row);
		}
	}
	if(function_exists('acceso_tabla_existe') && acceso_tabla_existe('empleado_rol_adicional'))
	{
		$qa=@mysqli_query($con, "SELECT e.*, r.clave AS rol_clave, a.clave AS area_clave
			FROM empleado_rol_adicional x
			INNER JOIN empleado e ON e.id_empleado=x.id_empleado
			INNER JOIN rol r ON r.id_rol=x.id_rol
			INNER JOIN area a ON a.id_area=x.id_area
			WHERE x.fecha_hasta IS NULL AND e.activo=1".$extra."
			ORDER BY a.nombre, e.apellido, e.nombres");
		if($qa)
		{
			while($row_ad=mysqli_fetch_assoc($qa))
			{
				$lista[]=acceso_fila_a_persona($row_ad);
			}
		}
	}
	return $lista;
}

function acceso_usuario_existe($usuario)
{
	global $con;
	if($usuario=='' || !$con)
	{
		return false;
	}
	$q=@mysqli_query($con,"SELECT id_empleado FROM empleado WHERE nombre_usuario='".acceso_sql($usuario)."' LIMIT 1");
	return ($q && mysqli_num_rows($q)>0);
}

function acceso_correo_existe($correo, $excepto_id=0)
{
	global $con;
	if($correo=='' || !$con)
	{
		return false;
	}
	$extra=($excepto_id>0) ? " AND id_empleado<>'".intval($excepto_id)."'" : '';
	$q=@mysqli_query($con,"SELECT id_empleado FROM empleado WHERE correo='".acceso_sql($correo)."'".$extra." LIMIT 1");
	return ($q && mysqli_num_rows($q)>0);
}

function acceso_cargar_roles_adicionales($id_empleado)
{
	global $con;
	$salida=array();
	$id=intval($id_empleado);
	if($id<=0 || !$con)
	{
		return $salida;
	}
	$sql="SELECT r.clave AS rol, a.clave AS area
		FROM empleado_rol_adicional x
		INNER JOIN rol r ON r.id_rol=x.id_rol
		LEFT JOIN area a ON a.id_area=x.id_area
		WHERE x.id_empleado='".$id."'
		AND x.fecha_hasta IS NULL";
	$q=@mysqli_query($con, $sql);
	if(!$q)
	{
		return $salida;
	}
	while($row=mysqli_fetch_assoc($q))
	{
		$salida[]=array(
			'rol'=>isset($row['rol']) ? $row['rol'] : '',
			'area'=>isset($row['area']) ? $row['area'] : ''
		);
	}
	return $salida;
}

// Login: usuario de la tabla empleado y clave hasheada.
function acceso_buscar_personal($usuario, $clave)
{
	global $con;
	$usuario=trim($usuario);
	if($usuario=='' || $clave==='' || !$con)
	{
		return null;
	}
	$q=@mysqli_query($con, acceso_sql_empleado_base()." WHERE e.nombre_usuario='".acceso_sql($usuario)."' AND e.activo=1 LIMIT 1");
	if(!$q || mysqli_num_rows($q)==0)
	{
		return null;
	}
	$row=mysqli_fetch_assoc($q);
	if(!acceso_verificar_clave($clave, isset($row['contrasena_hash']) ? $row['contrasena_hash'] : ''))
	{
		return null;
	}
	$persona=acceso_fila_a_persona($row);
	$persona['primer_acceso']=isset($row['primer_acceso']) ? intval($row['primer_acceso']) : 0;
	$persona['roles_adicionales']=acceso_cargar_roles_adicionales($persona['id']);
	return $persona;
}

function acceso_iniciar_sesion($persona)
{
	$rol=isset($persona['rol']) ? $persona['rol'] : '';
	if($rol=='trabajador')
	{
		$rol='empleado';
	}
	$_SESSION['alogin']=isset($persona['usuario']) ? $persona['usuario'] : '';
	$_SESSION['id']=isset($persona['id']) ? intval($persona['id']) : 0;
	$_SESSION['id_empleado']=$_SESSION['id'];
	$area_base=isset($persona['area']) ? $persona['area'] : '';
	$_SESSION['area_base']=$area_base;
	$_SESSION['rol_base']=$rol;
	$_SESSION['area_activa']=$area_base;
	$_SESSION['area_usuario']=$area_base;
	$_SESSION['rol_area']=$rol;
	$_SESSION['nombre_mostrado']=isset($persona['nombre']) ? $persona['nombre'] : '';
	$_SESSION['persona_tareas']=isset($persona['persona']) ? $persona['persona'] : '';
	$_SESSION['tareas_rol']=$rol;
	if($rol=='coordinador_general' || $rol=='superadmin')
	{
		$_SESSION['tareas_rol']='coordinador';
	}
	$_SESSION['tareas_persona']=$_SESSION['persona_tareas'];
	$_SESSION['roles_adicionales']=isset($persona['roles_adicionales']) && is_array($persona['roles_adicionales']) ? $persona['roles_adicionales'] : array();
	$_SESSION['primer_acceso']=isset($persona['primer_acceso']) ? intval($persona['primer_acceso']) : 0;
}

function acceso_es_super()
{
	return (isset($_SESSION['rol_area']) && ($_SESSION['rol_area']=='superadmin' || $_SESSION['rol_area']=='admin'))
		|| (isset($_SESSION['rol_base']) && ($_SESSION['rol_base']=='superadmin' || $_SESSION['rol_base']=='admin'));
}

function acceso_es_coord_general()
{
	$id=0;
	if(isset($_SESSION['id_empleado']))
	{
		$id=intval($_SESSION['id_empleado']);
	}
	else if(isset($_SESSION['id']))
	{
		$id=intval($_SESSION['id']);
	}
	if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']=='coordinador_general')
	{
		return true;
	}
	static $cache=array();
	if($id>0 && isset($cache[$id]))
	{
		return $cache[$id];
	}
	if($id>0 && function_exists('acceso_consulta_una'))
	{
		// Rol principal o rol adicional vigente.
		$principal=acceso_consulta_una(
			"SELECT e.id_empleado FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
			WHERE e.id_empleado=? AND r.clave='coordinador_general' LIMIT 1",
			'i',
			array($id)
		);
		$adicional=null;
		if(!$principal && acceso_tabla_existe('empleado_rol_adicional'))
		{
			$adicional=acceso_consulta_una(
				"SELECT x.id FROM empleado_rol_adicional x
				INNER JOIN rol r ON r.id_rol=x.id_rol
				WHERE x.id_empleado=? AND r.clave='coordinador_general' AND x.fecha_hasta IS NULL
				LIMIT 1",
				'i',
				array($id)
			);
		}
		$cache[$id]=($principal || $adicional) ? true : false;
		return $cache[$id];
	}
	foreach(acceso_roles_adicionales_sesion() as $extra)
	{
		if(isset($extra['rol']) && $extra['rol']=='coordinador_general')
		{
			return true;
		}
	}
	return false;
}

function acceso_es_coord_de_area($area='')
{
	if(acceso_es_coord_general() || acceso_es_super())
	{
		return true;
	}
	if($area==='')
	{
		$area=function_exists('acceso_area_pantalla') ? acceso_area_pantalla() : (function_exists('acceso_area') ? acceso_area() : '');
	}
	$area_pri=isset($_SESSION['area_usuario']) ? $_SESSION['area_usuario'] : '';
	$rol_pri=isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '';

	if($area_pri!=='' && $area_pri===$area && $rol_pri==='coordinador')
	{
		return true;
	}

	$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
	foreach($extras as $ext)
	{
		if(isset($ext['area']) && $ext['area']===$area && isset($ext['rol']) && $ext['rol']==='coordinador')
		{
			return true;
		}
	}

	return false;
}

/**
 * Acceso transversal exclusivo al Mapa de Eventos.
 * No altera los permisos de los demás módulos.
 */
function acceso_puede_ver_mapa_eventos()
{
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return true;
	}

	// Leer claves directamente para no invocar helpers de presentación que
	// pueden intentar asegurar columnas del esquema durante la petición.
	$areas=acceso_consulta("SELECT clave FROM area");
	$claves_area=array();
	foreach($areas as $fila)
	{
		$clave=strtolower(trim(isset($fila['clave']) ? (string)$fila['clave'] : ''));
		if($clave==='' || in_array($clave, array('general', 'admin'), true))
		{
			continue;
		}
		$claves_area[]=$clave;
	}

	$area_principal=isset($_SESSION['area_usuario']) ? strtolower(trim((string)$_SESSION['area_usuario'])) : '';
	$rol_principal=isset($_SESSION['rol_area']) ? (string)$_SESSION['rol_area'] : '';
	if($rol_principal==='coordinador' && in_array($area_principal, $claves_area, true))
	{
		return true;
	}

	foreach(acceso_roles_adicionales_sesion() as $extra)
	{
		$area_extra=isset($extra['area']) ? strtolower(trim((string)$extra['area'])) : '';
		if(isset($extra['rol']) && $extra['rol']==='coordinador' && in_array($area_extra, $claves_area, true))
		{
			return true;
		}
	}
	return false;
}

/**
 * Devuelve la lista de áreas y roles asignados válidos del usuario actual.
 */
function acceso_mis_areas_asignadas()
{
	$salida = array();
	$ya = array();
	$etiquetas = function_exists('acceso_etiquetas_area') ? acceso_etiquetas_area() : array();
	if (empty($etiquetas) && function_exists('acceso_etiquetas_area_con_tareas'))
	{
		$etiquetas = acceso_etiquetas_area_con_tareas();
	}
	if (empty($etiquetas))
	{
		$etiquetas = array(
			'rrhh' => 'Recursos Humanos',
			'emprendedores' => 'Emprendedores',
			'recepcion' => 'Recepción',
			'comercio-exterior' => 'Comercio Exterior',
			'sistemas' => 'Sistemas',
			'administracion' => 'Administración',
			'general' => 'General'
		);
	}

	$fn_url = function($clave) {
		if($clave === 'general')
		{
			return function_exists('url_crm') ? url_crm('admin/home.php') : '../admin/home.php';
		}
		if(function_exists('url_crm'))
		{
			if(is_file(BASE_PATH.'/public/'.$clave.'/dashboard.php'))
			{
				return url_crm($clave.'/dashboard.php');
			}
			return url_crm($clave.'/tareas.php');
		}
		return '../' . $clave . '/dashboard.php';
	};

	$area_activa = function_exists('acceso_area_activa') ? acceso_area_activa() : (isset($_SESSION['area_activa']) ? $_SESSION['area_activa'] : (isset($_SESSION['area_usuario']) ? $_SESSION['area_usuario'] : ''));

	// Superadmin / Coordinador General: acceso global
	if (acceso_es_super() || acceso_es_coord_general())
	{
		$rol_nombre = acceso_es_super() ? 'Superadmin' : 'Coordinación General';
		$salida[] = array(
			'clave' => 'general',
			'nombre' => 'Vista General',
			'rol' => acceso_es_super() ? 'superadmin' : 'coordinador_general',
			'rol_nombre' => $rol_nombre,
			'es_principal' => true,
			'es_activa' => ($area_activa === 'general' || $area_activa === ''),
			'url' => $fn_url('general')
		);
		$ya['general'] = true;

		foreach ($etiquetas as $clave => $nombre)
		{
			if (isset($ya[$clave])) { continue; }
			$ya[$clave] = true;
			$salida[] = array(
				'clave' => $clave,
				'nombre' => $nombre,
				'rol' => acceso_es_super() ? 'superadmin' : 'coordinador_general',
				'rol_nombre' => $rol_nombre,
				'es_principal' => false,
				'es_activa' => ($area_activa === $clave),
				'url' => $fn_url($clave)
			);
		}
		return $salida;
	}

	// 1. Área principal (base de la cuenta del usuario)
	$area_base = isset($_SESSION['area_base']) && $_SESSION['area_base'] !== '' 
		? $_SESSION['area_base'] 
		: (isset($_SESSION['area_usuario']) ? $_SESSION['area_usuario'] : '');
	$rol_base = isset($_SESSION['rol_base']) && $_SESSION['rol_base'] !== ''
		? $_SESSION['rol_base']
		: (isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '');

	if ($area_base !== '' && isset($etiquetas[$area_base]))
	{
		$rol_nom_pri = function_exists('acceso_etiqueta_rol') ? acceso_etiqueta_rol($rol_base) : ($rol_base === 'coordinador' ? 'Coordinación' : ($rol_base === 'presidencia' ? 'Presidencia' : 'Personal'));
		$salida[] = array(
			'clave' => $area_base,
			'nombre' => $etiquetas[$area_base],
			'rol' => $rol_base,
			'rol_nombre' => $rol_nom_pri,
			'es_principal' => true,
			'es_activa' => ($area_activa === $area_base),
			'url' => $fn_url($area_base)
		);
		$ya[$area_base] = true;
	}

	// 2. Roles adicionales activos
	$extras = function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
	foreach ($extras as $ext)
	{
		$area_ext = isset($ext['area']) ? trim((string)$ext['area']) : '';
		$rol_ext = isset($ext['rol']) ? trim((string)$ext['rol']) : '';
		if ($area_ext !== '' && isset($etiquetas[$area_ext]) && !isset($ya[$area_ext]))
		{
			$rol_nom_ext = function_exists('acceso_etiqueta_rol') ? acceso_etiqueta_rol($rol_ext) : ($rol_ext === 'coordinador' ? 'Coordinación' : ($rol_ext === 'presidencia' ? 'Presidencia' : 'Personal'));
			$salida[] = array(
				'clave' => $area_ext,
				'nombre' => $etiquetas[$area_ext],
				'rol' => $rol_ext,
				'rol_nombre' => $rol_nom_ext,
				'es_principal' => false,
				'es_activa' => ($area_activa === $area_ext),
				'url' => $fn_url($area_ext)
			);
			$ya[$area_ext] = true;
		}
	}

	return $salida;
}

function acceso_area_activa()
{
	if(isset($_SESSION['area_activa']) && $_SESSION['area_activa'] !== '')
	{
		return $_SESSION['area_activa'];
	}
	if(isset($_SESSION['area_usuario']) && $_SESSION['area_usuario'] !== '')
	{
		return $_SESSION['area_usuario'];
	}
	return '';
}

function acceso_rol_activo()
{
	if(acceso_es_super())
	{
		return 'superadmin';
	}
	if(acceso_es_coord_general())
	{
		return 'coordinador_general';
	}
	return isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '';
}

function acceso_cambiar_area_activa($nueva_area)
{
	$nueva_area = trim((string)$nueva_area);
	if($nueva_area === '')
	{
		return false;
	}

	if(acceso_es_super() || acceso_es_coord_general())
	{
		$etq = acceso_etiquetas_area();
		if($nueva_area !== 'general' && !isset($etq[$nueva_area]))
		{
			return false;
		}
		$_SESSION['area_activa'] = $nueva_area;
		$_SESSION['area_usuario'] = $nueva_area;
		if(acceso_es_super())
		{
			$_SESSION['rol_area'] = 'superadmin';
			$_SESSION['tareas_rol'] = 'coordinador';
		}
		else
		{
			$_SESSION['rol_area'] = 'coordinador_general';
			$_SESSION['tareas_rol'] = 'coordinador';
		}
		return true;
	}

	$mis = acceso_mis_areas_asignadas();
	$encontrada = null;
	foreach($mis as $a)
	{
		if($a['clave'] === $nueva_area)
		{
			$encontrada = $a;
			break;
		}
	}

	if(!$encontrada)
	{
		return false;
	}

	$_SESSION['area_activa'] = $nueva_area;
	$_SESSION['area_usuario'] = $nueva_area;
	$rol_en_area = isset($encontrada['rol']) ? $encontrada['rol'] : 'empleado';
	$_SESSION['rol_area'] = $rol_en_area;
	$_SESSION['tareas_rol'] = ($rol_en_area === 'coordinador' || $rol_en_area === 'coordinador_general' || $rol_en_area === 'superadmin') ? 'coordinador' : $rol_en_area;
	return true;
}

function acceso_roles_adicionales_sesion()
{
	return (isset($_SESSION['roles_adicionales']) && is_array($_SESSION['roles_adicionales'])) ? $_SESSION['roles_adicionales'] : array();
}

function acceso_es_presidencia()
{
	if(acceso_es_super())
	{
		return false;
	}
	if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']=='presidencia')
	{
		return true;
	}
	foreach(acceso_roles_adicionales_sesion() as $extra)
	{
		if(isset($extra['rol']) && $extra['rol']=='presidencia')
		{
			return true;
		}
	}
	return false;
}

function acceso_es_admin_tareas()
{
	// Compat: el rol legacy admin_tareas equivale a trabajar en el área Administración.
	if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']=='admin_tareas')
	{
		return true;
	}
	if(acceso_area()=='administracion')
	{
		return true;
	}
	foreach(acceso_roles_adicionales_sesion() as $extra)
	{
		if(isset($extra['rol']) && $extra['rol']=='admin_tareas')
		{
			return true;
		}
		if(isset($extra['area']) && $extra['area']=='administracion')
		{
			return true;
		}
	}
	return false;
}

// Ve todas las áreas: solo superadmin o coordinador general.
function acceso_es_general()
{
	return (acceso_es_super() || acceso_es_coord_general());
}

// Informes: Presidencia (solo publicados) y Coordinación general (arma y eleva).
function acceso_puede_ver_informes()
{
	return (acceso_es_presidencia() || acceso_es_coord_general() || acceso_es_super());
}

function acceso_puede_armar_informes()
{
	return (acceso_es_coord_general() || acceso_es_super()) && !acceso_es_presidencia();
}

function acceso_es_empleado()
{
	$r=isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '';
	$t=isset($_SESSION['tareas_rol']) ? $_SESSION['tareas_rol'] : '';
	return ($r=='empleado' || $r=='trabajador' || $t=='empleado' || $t=='trabajador');
}

// Area de la carpeta que esta abierta (aunque el general vea todas).
function acceso_area_pantalla()
{
	$script=str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
	foreach(array_keys(acceso_etiquetas_area()) as $area)
	{
		if(strpos($script, '/'.$area.'/')!==false)
		{
			return $area;
		}
	}
	return acceso_area();
}

function acceso_nombres_equipo($area_crm)
{
	// Solo gente de esa area. Quien esta logueado no se mete en otra lista.
	$nombres=array();
	foreach(acceso_personal_completo() as $persona)
	{
		if($area_crm=='' || $persona['area']==$area_crm)
		{
			$nom=($persona['persona']!='' ? $persona['persona'] : $persona['nombre']);
			if($nom!='' && !in_array($nom, $nombres))
			{
				$nombres[]=$nom;
			}
		}
	}
	return $nombres;
}

/** Resuelve "Nombre Apellido - Área" (o solo nombre) a id_empleado. */
function acceso_id_empleado_por_persona($persona)
{
	$persona=trim((string)$persona);
	if($persona==='')
	{
		return 0;
	}
	foreach(acceso_personal_completo(true) as $p)
	{
		if((isset($p['persona']) && $p['persona']===$persona) || (isset($p['nombre']) && $p['nombre']===$persona))
		{
			return intval($p['id']);
		}
	}
	$nom=trim(preg_replace('/\s+-\s+.+$/', '', $persona));
	if($nom!=='' && $nom!==$persona)
	{
		foreach(acceso_personal_completo(true) as $p)
		{
			if(isset($p['nombre']) && $p['nombre']===$nom)
			{
				return intval($p['id']);
			}
		}
	}
	return 0;
}

function tareas_objetivos_de_bd($area)
{
	$id_area=acceso_id_por_clave('area', $area);
	if($id_area<=0)
	{
		return array();
	}
	$salida=array();
	foreach(acceso_consulta("SELECT nombre FROM tipo_tarea WHERE id_area=? ORDER BY nombre", 'i', array($id_area)) as $row)
	{
		$nom=isset($row['nombre']) ? trim($row['nombre']) : '';
		if($nom!='' && !in_array($nom, $salida))
		{
			$salida[]=$nom;
		}
	}
	return $salida;
}

function tareas_objetivo_guardar($area, $nombre)
{
	$nombre=trim($nombre);
	$id_area=acceso_id_por_clave('area', $area);
	if($nombre=='' || $id_area<=0)
	{
		return '';
	}
	$hay=acceso_consulta_una(
		"SELECT nombre FROM tipo_tarea WHERE id_area=? AND LOWER(nombre)=LOWER(?) LIMIT 1",
		'is',
		array($id_area, $nombre)
	);
	if($hay && isset($hay['nombre']))
	{
		return $hay['nombre'];
	}
	acceso_insertar("INSERT INTO tipo_tarea (id_area, nombre) VALUES (?,?)", 'is', array($id_area, $nombre));
	$ok=acceso_consulta_una(
		"SELECT nombre FROM tipo_tarea WHERE id_area=? AND LOWER(nombre)=LOWER(?) LIMIT 1",
		'is',
		array($id_area, $nombre)
	);
	return ($ok && isset($ok['nombre'])) ? $ok['nombre'] : $nombre;
}

/** Valida fechas de alta/edición. Devuelve texto de error o ''. */
function tareas_validar_fechas_alta($fecha_inicio, $fecha_cierre)
{
	$fi=trim((string)$fecha_inicio);
	$fc=trim((string)$fecha_cierre);
	if($fc==='')
	{
		if(function_exists('tareas_fecha_cierre_obligatoria') && tareas_fecha_cierre_obligatoria())
		{
			return 'Indicá la fecha estimada de finalización.';
		}
		return '';
	}
	if($fi==='')
	{
		$fi=date('Y-m-d');
	}
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fi) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fc))
	{
		return 'Revisá las fechas (formato AAAA-MM-DD).';
	}
	$hoy=date('Y-m-d');
	if($fi<$hoy)
	{
		return 'La fecha de inicio no puede ser anterior a la fecha actual.';
	}
	if($fc<$hoy)
	{
		return 'La fecha estimada de finalización no puede ser anterior a la fecha actual.';
	}
	if($fc<$fi)
	{
		return 'La fecha estimada de finalización no puede ser anterior a la fecha de inicio.';
	}
	return '';
}

function tareas_mensaje_form_set($msg)
{
	$_SESSION['msgtarea_form']=trim((string)$msg);
}

function tareas_mensaje_form_tomar()
{
	if(!isset($_SESSION['msgtarea_form']) || $_SESSION['msgtarea_form']==='')
	{
		return '';
	}
	$m=$_SESSION['msgtarea_form'];
	$_SESSION['msgtarea_form']='';
	return $m;
}

/** Cargar objetivo desde el form de tarea: solo coordinación (mismo catálogo que Programas). */
function tareas_usuario_puede_cargar_objetivo()
{
	if(acceso_es_empleado())
	{
		return false;
	}
	if(function_exists('tareas_puede_cargar_tipo') && !tareas_puede_cargar_tipo())
	{
		return false;
	}
	return true;
}

function tareas_tipos_por_area()
{
	$salida=array();
	foreach(array_keys(acceso_etiquetas_area()) as $clave)
	{
		$salida[$clave]=tareas_objetivos_de_bd($clave);
	}
	return $salida;
}

function tareas_etiqueta_tipo($area='')
{
	return 'Objetivo';
}

function tareas_opciones_tipo($area)
{
	if(function_exists('tareas_tipos_de_area'))
	{
		$ops=tareas_tipos_de_area($area);
		if(is_array($ops) && count($ops)>0)
		{
			return $ops;
		}
	}
	return tareas_objetivos_de_bd($area);
}

function tareas_opciones_prioridad()
{
	return array('Urgente','Alta','Media','Baja');
}

function tarea_lista_responsables($tarea)
{
	$nombres=array();
	if(isset($tarea['responsables']) && is_array($tarea['responsables']))
	{
		foreach($tarea['responsables'] as $nom)
		{
			$nom=trim($nom);
			if($nom!='' && !in_array($nom, $nombres))
			{
				$nombres[]=$nom;
			}
		}
	}
	if(isset($tarea['responsable']) && trim($tarea['responsable'])!='' && !in_array($tarea['responsable'], $nombres))
	{
		array_unshift($nombres, $tarea['responsable']);
	}
	return $nombres;
}

function tarea_texto_responsables($tarea)
{
	return implode(', ', tarea_lista_responsables($tarea));
}

function tarea_tiene_responsable($tarea, $nombre)
{
	return in_array($nombre, tarea_lista_responsables($tarea));
}

// Quienes ya tocaron Iniciar en esta tarea.
function tarea_lista_iniciaron($tarea)
{
	$ini=isset($tarea['iniciaron']) && is_array($tarea['iniciaron']) ? $tarea['iniciaron'] : array();
	$salida=array();
	foreach($ini as $nom)
	{
		$nom=trim($nom);
		if($nom!='' && !in_array($nom, $salida))
		{
			$salida[]=$nom;
		}
	}
	return $salida;
}

function tarea_persona_inicio($tarea, $nombre)
{
	return in_array($nombre, tarea_lista_iniciaron($tarea));
}

function tarea_todos_iniciaron($tarea)
{
	$nombres=tarea_lista_responsables($tarea);
	if(count($nombres)==0)
	{
		return false;
	}
	foreach($nombres as $nom)
	{
		if(!tarea_persona_inicio($tarea, $nom))
		{
			return false;
		}
	}
	return true;
}

function tarea_nombres_sin_iniciar($tarea)
{
	$faltan=array();
	foreach(tarea_lista_responsables($tarea) as $nom)
	{
		if(!tarea_persona_inicio($tarea, $nom))
		{
			$faltan[]=$nom;
		}
	}
	return $faltan;
}

// Lo que se muestra: con que uno inicie, la tarea pasa a En proceso.
function tarea_texto_estado($tarea)
{
	$est=isset($tarea['estado']) ? $tarea['estado'] : '';
	$est_l=strtolower(trim($est));
	if($est_l=='cancelada' || $est=='Cancelada' || $est=='Eliminada' || $est_l=='eliminada')
	{
		return 'Eliminada';
	}
	if($est=='Pendiente')
	{
		$est='No iniciada';
	}
	if($est=='Terminada' || $est=='Pausada')
	{
		return $est;
	}
	if($est=='En proceso')
	{
		return 'En proceso';
	}
	if(count(tarea_lista_iniciaron($tarea))>0)
	{
		return 'En proceso';
	}
	return 'No iniciada';
}

function tarea_coincide_estado($tarea, $estado)
{
	if($estado=='')
	{
		return true;
	}
	if($estado=='Vencida')
	{
		if(!function_exists('tarea_esta_vencida'))
		{
			return false;
		}
		return tarea_esta_vencida($tarea);
	}
	if($estado=='Pendiente')
	{
		$estado='No iniciada';
	}
	if($estado=='cancelada')
	{
		$estado='Eliminada';
	}
	return (tarea_texto_estado($tarea)==$estado);
}

function tarea_puede_agregar_extras($tarea)
{
	if(!$tarea || (function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($tarea)))
	{
		return false;
	}
	$est=isset($tarea['estado']) ? strtolower(trim($tarea['estado'])) : '';
	$txt=function_exists('tarea_texto_estado') ? tarea_texto_estado($tarea) : '';
	if($est==='terminada' || $txt==='Terminada' || $est==='cancelada' || $est==='eliminada')
	{
		return false;
	}
	// Superadmin puede gestionar extras en cualquier tarea visible que siga activa.
	if(acceso_es_super())
	{
		return true;
	}
	if(isset($tarea['area_origen']) && $tarea['area_origen']!='')
	{
		return false;
	}
	if(function_exists('tarea_puede_ver') && !tarea_puede_ver($tarea))
	{
		return false;
	}
	// Sin responsable no se pueden cargar avances / comentarios / links.
	$resps=function_exists('tarea_lista_responsables') ? tarea_lista_responsables($tarea) : array();
	$hay=false;
	foreach($resps as $r)
	{
		if(trim((string)$r)!='')
		{
			$hay=true;
			break;
		}
	}
	if(!$hay)
	{
		return false;
	}
	if(acceso_es_coord_de_area() || acceso_es_coord_general())
	{
		return true;
	}
	return function_exists('tarea_es_responsable') ? tarea_es_responsable($tarea) : false;
}

function tareas_avisos_ruta()
{
	return BASE_PATH.'/includes/tareas-avisos.json';
}

function tareas_avisos_leer()
{
	$f=tareas_avisos_ruta();
	if(!is_file($f))
	{
		return array();
	}
	$datos=json_decode(file_get_contents($f), true);
	return is_array($datos) ? $datos : array();
}

function tareas_avisos_guardar($datos)
{
	@file_put_contents(tareas_avisos_ruta(), json_encode($datos, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
}

function tarea_marcar_compartida($tarea)
{
	if(!isset($tarea['uid']) || $tarea['uid']=='')
	{
		$tarea['uid']=uniqid('t', true);
	}
	$tarea['actualizado']=time();
	return $tarea;
}

function tareas_tablero_publicar($area, $tarea)
{
	return $tarea;
}

function tareas_tablero_publicar_id($id)
{
}

function tareas_tablero_sincronizar()
{
}

// Aviso a cada responsable (menos quien carga la tarea) cuando un coordinador asigna.
function tareas_avisos_registrar($responsables, $titulo, $id, $area, $tarea=array())
{
	if(acceso_es_empleado())
	{
		return;
	}
	$de=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : acceso_nombre();
	$todos=tareas_avisos_leer();
	foreach($responsables as $nom)
	{
		$nom=trim($nom);
		if($nom=='' || $nom==$de)
		{
			continue;
		}
		if(!isset($todos[$nom]) || !is_array($todos[$nom]))
		{
			$todos[$nom]=array();
		}
		$todos[$nom][]=array(
			'id'=>uniqid('av', true),
			'de'=>$de,
			'titulo'=>$titulo,
			'tarea_id'=>$id,
			'area'=>$area,
			'tarea'=>$tarea,
			'cuando'=>date('d/m/Y H:i')
		);
	}
	tareas_avisos_guardar($todos);
}

function tareas_avisos_mios()
{
	$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
	if($yo=='')
	{
		return array();
	}
	$todos=tareas_avisos_leer();
	return (isset($todos[$yo]) && is_array($todos[$yo])) ? $todos[$yo] : array();
}

function tareas_aviso_marcar_leido($id)
{
	$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
	if($yo=='' || $id=='')
	{
		return;
	}
	$todos=tareas_avisos_leer();
	if(!isset($todos[$yo]) || !is_array($todos[$yo]))
	{
		return;
	}
	$queda=array();
	foreach($todos[$yo] as $aviso)
	{
		if(!isset($aviso['id']) || $aviso['id']!=$id)
		{
			$queda[]=$aviso;
		}
	}
	$todos[$yo]=$queda;
	tareas_avisos_guardar($todos);
}

// Al tocar OK: se cierra el aviso. La tarea ya está en el listado.
function tareas_aviso_aceptar($id)
{
	tareas_aviso_marcar_leido($id);
}

function tareas_aviso_url($aviso)
{
	$area=isset($aviso['area']) ? $aviso['area'] : acceso_area_pantalla();
	$id=isset($aviso['tarea_id']) ? intval($aviso['tarea_id']) : 0;
	$etq=acceso_etiquetas_area();
	if(!isset($etq[$area]))
	{
		$area=acceso_area_pantalla();
	}
	if($area==acceso_area_pantalla())
	{
		return tareas_link('ver-tarea.php', array('id'=>$id));
	}
	return url_crm($area.'/ver-tarea.php').'?id='.$id;
}

function tareas_guardar_extras_ver($id)
{
	if(function_exists('tareas_datos_guardar_extras'))
	{
		return tareas_datos_guardar_extras($id);
	}
	return false;
}

function tareas_leer_lista_post($campo)
{
	$salida=array();
	if(!isset($_POST[$campo]) || !is_array($_POST[$campo]))
	{
		return $salida;
	}
	foreach($_POST[$campo] as $valor)
	{
		$valor=trim($valor);
		if($valor!='' && !in_array($valor, $salida))
		{
			$salida[]=$valor;
		}
	}
	return $salida;
}

function tareas_leer_responsables_post($area, $tarea_actual=null)
{
	if(acceso_es_empleado())
	{
		if(is_array($tarea_actual))
		{
			return tarea_lista_responsables($tarea_actual);
		}
		$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
		return ($yo!='' ? array($yo) : array());
	}
	$ok=acceso_nombres_equipo($area);
	if(count($ok)===0 && !is_array($tarea_actual))
	{
		// No descartar un responsable enviado por POST sin avisar: no hay
		// personal válido en esta área para recibir ni gestionar la tarea.
		tareas_mensaje_form_set('No se puede crear la tarea porque el área seleccionada no tiene personal asignado. Asigná personal al área o elegí otra área.');
		return array();
	}
	$elegidos=tareas_leer_lista_post('responsables');
	$salida=array();
	foreach($elegidos as $nom)
	{
		if(in_array($nom, $ok) || (is_array($tarea_actual) && in_array($nom, tarea_lista_responsables($tarea_actual))))
		{
			$salida[]=$nom;
		}
	}
	if(count($salida)==0 && is_array($tarea_actual))
	{
		$resp_prev=tarea_lista_responsables($tarea_actual);
		if(count($resp_prev)>0)
		{
			$salida=$resp_prev;
		}
	}
	if(count($salida)==0 && isset($ok[0]))
	{
		$salida[]=$ok[0];
	}
	return $salida;
}

function tareas_validar_responsables_area($area)
{
	if(acceso_es_empleado())
	{
		return '';
	}
	if(count(acceso_nombres_equipo($area))===0)
	{
		return 'No se puede crear la tarea porque el área seleccionada no tiene personal asignado. Asigná personal al área o elegí otra área.';
	}
	return '';
}

function tareas_armar_comentarios_nuevos()
{
	$salida=array();
	$quien=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : acceso_nombre();
	foreach(tareas_leer_lista_post('comentarios') as $texto)
	{
		$salida[]=array(
			'texto'=>$texto,
			'quien'=>$quien,
			'cuando'=>date('d/m/Y H:i')
		);
	}
	return $salida;
}

function acceso_mostrar_rol($rol)
{
	$r = strtolower(trim((string)$rol));
	if($r === 'coordinador')
	{
		return 'Coordinación';
	}
	if($r === 'coordinador_general')
	{
		return 'Coordinación General';
	}
	if($r === 'trabajador' || $r === 'empleado')
	{
		return 'Personal';
	}
	if($r === 'superadmin' || $r === 'admin')
	{
		return 'Superadmin';
	}
	if($r === 'presidencia')
	{
		return 'Presidencia';
	}
	if($r === 'admin_tareas')
	{
		return 'Administración de tareas';
	}
	return ($rol !== '') ? ucfirst($rol) : 'Personal';
}

function acceso_etiqueta_rol($rol)
{
	return acceso_mostrar_rol($rol);
}

// Areas que tienen pantalla de tareas. Ya no se usa para guardar en sesión.
function tareas_claves_sesion()
{
	$base = array(
		'marketing'=>'tareas_demo',
		'empresas'=>'emps_tareas_demo',
		'emprendedores'=>'emp_tareas_demo',
		'vinculaciones'=>'vin_tareas_demo',
		'comercio'=>'com_tareas_demo',
		'sistemas'=>'sis_tareas_demo',
		'rrhh'=>'rrhh_tareas_demo',
		'recepcion'=>'rec_tareas_demo',
		'administracion'=>'adm_tareas_demo',
		'general'=>'gen_tareas_demo'
	);
	foreach(acceso_etiquetas_area() as $cl=>$nom)
	{
		if($cl!='presidencia' && !isset($base[$cl]))
		{
			$base[$cl] = $cl.'_tareas_demo';
		}
	}
	return $base;
}

function acceso_equipos_por_area()
{
	$salida=array();
	foreach(array_keys(acceso_etiquetas_area_con_tareas()) as $area)
	{
		$salida[$area]=acceso_nombres_equipo($area);
	}
	return $salida;
}

function tareas_areas_alta_permitidas()
{
	$todas=acceso_etiquetas_area_con_tareas();
	if(acceso_es_general())
	{
		return $todas;
	}
	if(acceso_es_coord_rrhh())
	{
		$salida=array();
		if(isset($todas['rrhh'])) { $salida['rrhh']=$todas['rrhh']; }
		if(isset($todas['recepcion'])) { $salida['recepcion']=$todas['recepcion']; }
		return $salida;
	}
	return array();
}

/** Sistemas solo recibe tareas desde Coordinación general, programas o la propia área. */
function tareas_puede_asignar_a_area($area_destino)
{
	$area_destino=trim((string)$area_destino);
	if($area_destino=='' || $area_destino!=='sistemas')
	{
		return true;
	}
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return true;
	}
	$mia=acceso_area();
	$pant=acceso_area_pantalla();
	if($mia==='sistemas' || $pant==='sistemas')
	{
		return true;
	}
	return false;
}

// Area a la que se puede asignar una tarea nueva.
function tareas_area_destino_alta()
{
	$etiquetas=acceso_etiquetas_area();
	$area=acceso_area_pantalla();
	$permitidas=tareas_areas_alta_permitidas();
	if(count($permitidas)>0 && isset($_POST['area_crm']) && isset($permitidas[$_POST['area_crm']]))
	{
		$area=$_POST['area_crm'];
	}
	else
	{
		$mia=acceso_area();
		if($mia!='' && $mia!='todas')
		{
			$area=$mia;
		}
	}
	$claves=tareas_claves_sesion();
	if(!isset($claves[$area]))
	{
		$area=acceso_area_pantalla();
	}
	if(!tareas_puede_asignar_a_area($area))
	{
		// Sistemas no recibe tareas desde otras áreas (salvo Coordinación general / programas).
		$area=acceso_area_pantalla();
		if(!tareas_puede_asignar_a_area($area))
		{
			$area=acceso_area();
		}
	}
	return $area;
}

// Alta de tarea: arranca No iniciada y en 0. Cada responsable tiene que tocarla Iniciar.
function tareas_guardar_nueva_desde_form()
{
	$fi=isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-d');
	$fc=isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : '';
	$err=tareas_validar_fechas_alta($fi, $fc);
	if($err!='')
	{
		tareas_mensaje_form_set($err);
		return false;
	}
	$area_dest=tareas_area_destino_alta();
	if(!tareas_puede_asignar_a_area($area_dest))
	{
		tareas_mensaje_form_set('Sistemas solo recibe tareas desde Coordinación general o desde programas.');
		return false;
	}
	$area=tareas_area_destino_alta();
	$err_responsables=tareas_validar_responsables_area($area);
	if($err_responsables!=='')
	{
		tareas_mensaje_form_set($err_responsables);
		return false;
	}
	if(function_exists('tareas_datos_guardar_nueva'))
	{
		$area=tareas_datos_guardar_nueva($area);
	}
	if($area===false || $area===null)
	{
		if(!isset($_SESSION['msgtarea_form']) || $_SESSION['msgtarea_form']==='')
		{
			tareas_mensaje_form_set('No se pudo guardar la tarea. Revisá los datos e intentá de nuevo.');
		}
		return false;
	}
	// Alta desde un turno: no todas las áreas guardan id_atencion, se vincula acá la tarea recién creada.
	$id_at=isset($_POST['id_atencion']) ? intval($_POST['id_atencion']) : 0;
	$id_cli=isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
	if($id_at>0 && $id_cli>0)
	{
		acceso_ejecutar(
			"UPDATE tarea SET id_atencion=? WHERE id_cliente=? AND id_atencion IS NULL AND titulo=? AND creado_en>=DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id_tarea DESC LIMIT 1",
			'iis',
			array($id_at, $id_cli, isset($_POST['titulo']) ? trim($_POST['titulo']) : '')
		);
	}
	return $area;
}

function tareas_ir_despues_guardar_nueva($area_alta)
{
	if($area_alta===false || $area_alta===null || $area_alta==='')
	{
		return;
	}
	$id_at=isset($_POST['id_atencion']) ? intval($_POST['id_atencion']) : 0;
	if($id_at>0)
	{
		header('Location: '.url_crm((isset($area_alta) && $area_alta!='' ? $area_alta : acceso_area_pantalla()).'/atencion.php?id='.$id_at));
		exit();
	}
	header('Location: '.url_crm((isset($area_alta) && $area_alta!='' ? $area_alta : acceso_area_pantalla()).'/tareas.php'));
	exit();
}

// Guarda lo basico de la edicion (titulo, tipo, responsables, fechas). Links y comentarios van en Ver.
function tareas_actualizar_desde_form($id)
{
	$id=intval($id);
	$antes=function_exists('tarea_por_id') ? tarea_por_id($id) : null;
	$motivo=isset($_POST['motivo_cambio']) ? trim($_POST['motivo_cambio']) : '';
	if(isset($_POST['fecha_cierre']) && trim($_POST['fecha_cierre'])!=='')
	{
		$fc_post=trim($_POST['fecha_cierre']);
		$fc_ant=($antes && !empty($antes['fecha_cierre'])) ? substr(trim($antes['fecha_cierre']), 0, 10) : '';
		if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fc_post))
		{
			$_SESSION['msgtarea']='Revisá la fecha estimada (formato AAAA-MM-DD).';
			return false;
		}
		if($fc_post!==$fc_ant && $fc_post<date('Y-m-d'))
		{
			$_SESSION['msgtarea']='La fecha estimada de finalización no puede ser anterior a la fecha actual.';
			return false;
		}
		$fi_post=isset($_POST['fecha_inicio']) && trim($_POST['fecha_inicio'])!=='' ? trim($_POST['fecha_inicio']) : (($antes && !empty($antes['fecha_inicio'])) ? substr(trim($antes['fecha_inicio']), 0, 10) : date('Y-m-d'));
		if($fi_post!=='' && $fc_post<$fi_post)
		{
			$_SESSION['msgtarea']='La fecha estimada de finalización no puede ser anterior a la fecha de inicio.';
			return false;
		}
	}
	if($antes && function_exists('crm_tarea_armar_detalle_cambios'))
	{
		$prev=$antes;
		$prev['titulo']=isset($_POST['titulo']) ? $_POST['titulo'] : (isset($antes['titulo']) ? $antes['titulo'] : '');
		$prev['descripcion']=isset($_POST['descripcion']) ? $_POST['descripcion'] : (isset($antes['descripcion']) ? $antes['descripcion'] : '');
		$prev['prioridad']=isset($_POST['prioridad']) ? $_POST['prioridad'] : (isset($antes['prioridad']) ? $antes['prioridad'] : '');
		$prev['fecha_inicio']=isset($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : (isset($antes['fecha_inicio']) ? $antes['fecha_inicio'] : '');
		$prev['fecha_cierre']=isset($_POST['fecha_cierre']) ? $_POST['fecha_cierre'] : (isset($antes['fecha_cierre']) ? $antes['fecha_cierre'] : '');
		$antes['responsables_txt']=function_exists('tarea_texto_responsables') ? tarea_texto_responsables($antes) : '';
		$resp_post=isset($_POST['responsables']) && is_array($_POST['responsables']) ? array_filter(array_map('trim', $_POST['responsables'])) : array();
		$prev['responsables_txt']=count($resp_post) ? implode(', ', $resp_post) : $antes['responsables_txt'];
		$cambios=crm_tarea_armar_detalle_cambios($antes, $prev);
		if(count($cambios)>0 && $motivo=='' && !acceso_es_coord_general() && !acceso_es_super())
		{
			$_SESSION['msgtarea']='Indicá el motivo del cambio (por ejemplo, por qué se mueve la fecha estimada).';
			return false;
		}
	}
	$ok=false;
	if(function_exists('tareas_datos_actualizar'))
	{
		$ok=tareas_datos_actualizar($id);
	}
	if($ok && $antes && function_exists('crm_tarea_notificar_edicion'))
	{
		$despues=tarea_por_id($id);
		if($despues)
		{
			$antes['responsables_txt']=function_exists('tarea_texto_responsables') ? tarea_texto_responsables($antes) : '';
			$despues['responsables_txt']=function_exists('tarea_texto_responsables') ? tarea_texto_responsables($despues) : '';
			$cambios=crm_tarea_armar_detalle_cambios($antes, $despues);
			if(count($cambios)>0)
			{
				crm_tarea_notificar_edicion($id, $antes, $despues, $motivo);
			}
		}
	}
	return $ok;
}

// Quien figura como responsable de la tarea (da igual el rol: empleado, coordinador o superadmin).
function tarea_es_responsable($tarea)
{
	$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
	if($yo=='')
	{
		return false;
	}
	return tarea_tiene_responsable($tarea, $yo);
}

function tarea_esta_eliminada($tarea)
{
	if(!is_array($tarea))
	{
		return false;
	}
	$est=isset($tarea['estado']) ? $tarea['estado'] : '';
	$est_l=strtolower(trim($est));
	return ($est_l=='cancelada' || $est=='Cancelada' || $est=='Eliminada' || $est_l=='eliminada');
}

// Obtiene la clave de area de una tarea de forma robusta a partir de sus campos o de la base.
function tarea_area_clave($tarea)
{
	if(!$tarea || !is_array($tarea))
	{
		return '';
	}
	if(isset($tarea['area_clave']) && $tarea['area_clave']!='')
	{
		return $tarea['area_clave'];
	}
	if(isset($tarea['clave_area']) && $tarea['clave_area']!='')
	{
		return $tarea['clave_area'];
	}
	if(isset($tarea['id_area']) && intval($tarea['id_area'])>0 && function_exists('acceso_clave_por_id'))
	{
		$c=acceso_clave_por_id('area', $tarea['id_area']);
		if($c!='')
		{
			return $c;
		}
	}
	$etqs=function_exists('acceso_etiquetas_area') ? acceso_etiquetas_area() : array();
	if(isset($tarea['area']) && $tarea['area']!='')
	{
		$val=trim($tarea['area']);
		if(isset($etqs[$val]))
		{
			return $val;
		}
		foreach($etqs as $c=>$nom)
		{
			if(strcasecmp($val, $nom)===0 || (function_exists('acceso_etiqueta_corta_area') && strcasecmp($val, acceso_etiqueta_corta_area($c))===0))
			{
				return $c;
			}
		}
	}
	if(isset($tarea['id']) && intval($tarea['id'])>0)
	{
		global $con;
		if($con)
		{
			$q_ar=@mysqli_query($con, "SELECT a.clave FROM tarea t INNER JOIN area a ON a.id_area=t.id_area WHERE t.id_tarea=".intval($tarea['id'])." LIMIT 1");
			if($q_ar && ($r_ar=mysqli_fetch_assoc($q_ar)))
			{
				return $r_ar['clave'];
			}
		}
	}
	return '';
}

// Verifica si el usuario actual tiene permiso para acceder/visualizar una tarea segun su area principal, adicional y rol.
function tarea_puede_ver($tarea)
{
	if(!$tarea || (function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($tarea)))
	{
		return false;
	}
	// Superadmin y Coordinador General pueden ver cualquier tarea
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return true;
	}

	$area_tarea=tarea_area_clave($tarea);

	$area_usuario=function_exists('acceso_area') ? acceso_area() : '';
	$rol_usuario=isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '';

	$pertenece_area=false;
	$rol_en_area='';

	if($area_tarea!='' && $area_tarea===$area_usuario)
	{
		$pertenece_area=true;
		$rol_en_area=$rol_usuario;
	}
	else if($area_tarea!='')
	{
		$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
		foreach($extras as $ext)
		{
			if(isset($ext['area']) && $ext['area']===$area_tarea)
			{
				$pertenece_area=true;
				$rol_en_area=isset($ext['rol']) ? $ext['rol'] : '';
				break;
			}
		}
	}

	if(!$pertenece_area && $area_tarea==='recepcion' && function_exists('acceso_es_coord_rrhh') && acceso_es_coord_rrhh())
	{
		$pertenece_area=true;
		$rol_en_area='coordinador';
	}

	if(!$pertenece_area)
	{
		return false;
	}

	if($rol_en_area==='coordinador' || $rol_en_area==='coordinacion')
	{
		return true;
	}

	return function_exists('tarea_es_responsable') ? tarea_es_responsable($tarea) : false;
}

// El responsable, el coordinador del área y el Superadmin pueden cambiar lo básico
// (título, responsables y fechas). Las tareas terminadas se deben reabrir antes.
function tarea_puede_editar($tarea)
{
	if(tarea_esta_eliminada($tarea))
	{
		return false;
	}
	$est=isset($tarea['estado']) ? strtolower(trim($tarea['estado'])) : '';
	$txt=function_exists('tarea_texto_estado') ? tarea_texto_estado($tarea) : '';
	if($est==='terminada' || $txt==='Terminada')
	{
		return false;
	}
	if(acceso_es_super())
	{
		return true;
	}
	if(isset($tarea['area_origen']) && $tarea['area_origen']!='')
	{
		return false;
	}
	if(function_exists('tarea_puede_ver') && !tarea_puede_ver($tarea))
	{
		return false;
	}
	if(tarea_es_responsable($tarea))
	{
		return true;
	}
	return !acceso_es_empleado();
}

// Iniciar / pausar / terminar: solo cada responsable, en su nombre.
function tarea_puede_cambiar_estado($tarea)
{
	if(acceso_es_super() || tarea_esta_eliminada($tarea))
	{
		return false;
	}
	$est=isset($tarea['estado']) ? strtolower(trim($tarea['estado'])) : '';
	$txt=function_exists('tarea_texto_estado') ? tarea_texto_estado($tarea) : '';
	if($est==='terminada' || $txt==='Terminada')
	{
		return false;
	}
	if(isset($tarea['area_origen']) && $tarea['area_origen']!='')
	{
		return false;
	}
	if(function_exists('tarea_puede_ver') && !tarea_puede_ver($tarea))
	{
		return false;
	}
	return tarea_es_responsable($tarea);
}

// Reabrir tarea terminada: solo Superadmin, Coordinador General o Coordinador de su area.
function tarea_puede_reabrir($tarea)
{
	if(!$tarea || (function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($tarea)))
	{
		return false;
	}
	$est=isset($tarea['estado']) ? strtolower(trim($tarea['estado'])) : '';
	$txt=function_exists('tarea_texto_estado') ? tarea_texto_estado($tarea) : '';
	if($est!=='terminada' && $txt!=='Terminada')
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return true;
	}
	$area_tarea=tarea_area_clave($tarea);
	if($area_tarea==='')
	{
		return false;
	}
	$area_usuario=function_exists('acceso_area') ? acceso_area() : '';
	$area_pantalla=function_exists('acceso_area_pantalla') ? acceso_area_pantalla() : '';
	if(acceso_es_coord_de_area())
	{
		if($area_tarea===$area_usuario || $area_tarea===$area_pantalla)
		{
			return true;
		}
	}
	$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
	foreach($extras as $ext)
	{
		if(isset($ext['area']) && $ext['area']===$area_tarea && isset($ext['rol']) && $ext['rol']==='coordinador')
		{
			return true;
		}
	}
	return false;
}

function tareas_aplicar_accion_en_lista($lista, $id, $accion, $extra=array())
{
	foreach($lista as $i=>$item)
	{
		if($item['id']!=$id)
		{
			continue;
		}
		if(!tarea_puede_cambiar_estado($item))
		{
			break;
		}
		$est=isset($item['estado']) ? $item['estado'] : '';
		$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
		if($accion=='iniciar' && $est!='Terminada' && $est!='Pausada')
		{
			$ini=isset($item['iniciaron']) && is_array($item['iniciaron']) ? $item['iniciaron'] : array();
			if($yo!='' && !in_array($yo, $ini))
			{
				$ini[]=$yo;
			}
			$lista[$i]['iniciaron']=$ini;
			$lista[$i]['estado']='En proceso';
			if(!isset($item['fecha_inicio']) || $item['fecha_inicio']=='')
			{
				$lista[$i]['fecha_inicio']=date('Y-m-d');
			}
			$lista[$i]=tarea_marcar_compartida($lista[$i]);
		}
		else if($accion=='pausar' && $est=='En proceso')
		{
			$lista[$i]['estado']='Pausada';
		}
		else if($accion=='reanudar' && $est=='Pausada')
		{
			$lista[$i]['estado']='En proceso';
		}
		else if($accion=='terminar' && ($est=='En proceso' || $est=='Pausada'))
		{
			$lista[$i]['estado']='Terminada';
			$lista[$i]['avance']=100;
			// El padre se recalcula DESPUÉS de guardar en BD (crm_tarea_sync_padre_de).
		}
		break;
	}
	return $lista;
}

function tareas_procesar_accion_si_vino()
{
	if(function_exists('crm_tarea_procesar_post_flujo') && crm_tarea_procesar_post_flujo())
	{
		return true;
	}
	if(isset($_POST['gestionar_extra_tarea']) && isset($_POST['id_tarea']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return true;
		}
		$id_tarea=intval($_POST['id_tarea']);
		$ok=function_exists('crm_tarea_extra_procesar') && crm_tarea_extra_procesar(
			$id_tarea,
			isset($_POST['accion_extra_tarea']) ? $_POST['accion_extra_tarea'] : '',
			isset($_POST['id_comentario']) ? intval($_POST['id_comentario']) : 0,
			isset($_POST['id_enlace']) ? intval($_POST['id_enlace']) : 0,
			isset($_POST['texto_comentario']) ? $_POST['texto_comentario'] : '',
			isset($_POST['url_enlace']) ? $_POST['url_enlace'] : ''
		);
		$accion_extra=isset($_POST['accion_extra_tarea']) ? $_POST['accion_extra_tarea'] : '';
		$_SESSION['msgtarea']=$ok
			? (strpos($accion_extra,'eliminar_')===0 ? 'El comentario o link se quitó correctamente.' : 'El comentario o link se actualizó correctamente.')
			: 'No se pudo modificar ese comentario o link. Revisá los permisos y los datos ingresados.';
		return true;
	}
	if(isset($_POST['cerrar_aviso_atencion']) && function_exists('crm_aviso_atencion_ok'))
	{
		crm_aviso_atencion_ok($_POST['cerrar_aviso_atencion']);
		return true;
	}
	if(isset($_POST['cerrar_aviso']))
	{
		tareas_aviso_aceptar($_POST['cerrar_aviso']);
		return true;
	}
	if(isset($_POST['asignar_responsable_rapido']) && isset($_POST['id_tarea']))
	{
		$id=intval($_POST['id_tarea']);
		$t=function_exists('tarea_por_id') ? tarea_por_id($id) : null;
		if(!$t || (function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($t)))
		{
			$_SESSION['msgtarea']='No se encontró la tarea.';
			return true;
		}
		if(!function_exists('tarea_puede_editar') || !tarea_puede_editar($t))
		{
			$_SESSION['msgtarea']='No podés asignar responsable en esta tarea.';
			return true;
		}
		$msg=function_exists('crm_tarea_asignar_responsable')
			? crm_tarea_asignar_responsable($id, isset($_POST['id_responsable']) ? intval($_POST['id_responsable']) : 0)
			: 'No disponible.';
		$_SESSION['msgtarea']=($msg==='') ? 'Responsable asignado.' : $msg;
		return true;
	}
	if(isset($_POST['agregar_avance']) && isset($_POST['id_tarea']))
	{
		$id=intval($_POST['id_tarea']);
		$t=null;
		if(function_exists('tarea_por_id'))
		{
			$t=tarea_por_id($id);
			if($t && function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($t))
			{
				$_SESSION['msgtarea']='Esa tarea está eliminada. Solo se puede consultar.';
				return true;
			}
		}
		$url=isset($_POST['enlace_nuevo']) ? trim($_POST['enlace_nuevo']) : '';
		$txt=isset($_POST['comentario_nuevo']) ? trim($_POST['comentario_nuevo']) : '';
		if($txt=='')
		{
			$_SESSION['msgtarea']='El comentario es obligatorio.';
			return true;
		}
		if($t && function_exists('tarea_puede_agregar_extras') && !tarea_puede_agregar_extras($t))
		{
			$est=function_exists('tarea_texto_estado') ? tarea_texto_estado($t) : '';
			if($est==='Terminada' || (isset($t['estado']) && strtolower(trim($t['estado']))==='terminada'))
			{
				$_SESSION['msgtarea']='No se pueden agregar comentarios ni enlaces a una tarea finalizada.';
			}
			else
			{
				$_SESSION['msgtarea']='No tenés permiso para cargar comentarios o links en esta tarea (o no tiene responsable asignado).';
			}
			return true;
		}
		$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
		$necesita_iniciar=false;
		if($t && $yo!='' && function_exists('tarea_puede_cambiar_estado') && tarea_puede_cambiar_estado($t))
		{
			$est=function_exists('tarea_texto_estado') ? tarea_texto_estado($t) : '';
			if($est!='Terminada' && $est!='Pausada' && function_exists('tarea_persona_inicio') && !tarea_persona_inicio($t, $yo))
			{
				$necesita_iniciar=true;
			}
		}
		if($necesita_iniciar || ($t && function_exists('tarea_texto_estado') && tarea_texto_estado($t)=='No iniciada'))
		{
			$_SESSION['msgtarea']='Para cargar comentarios y links la tarea debe estar iniciada.';
			return true;
		}
		$ok=function_exists('crm_tarea_agregar_comentario_con_enlace')
			? crm_tarea_agregar_comentario_con_enlace($id, $txt, $url)
			: false;
		if(!$ok)
		{
			$error_esquema=function_exists('crm_tarea_enlace_comentario_error') ? crm_tarea_enlace_comentario_error() : '';
			$_SESSION['msgtarea']=$error_esquema!==''
				? $error_esquema
				: 'No se pudo guardar el comentario y su enlace asociado. Revisá la sesión e intentá nuevamente.';
			return true;
		}
		return true;
	}
	if(function_exists('tareas_datos_procesar_accion'))
	{
		return tareas_datos_procesar_accion();
	}
	return false;
}

// Botones de estado: se muestran en Ver tarea, solo si sos el responsable.
function tarea_botones_avance($tarea)
{
	if(!tarea_puede_cambiar_estado($tarea))
	{
		return;
	}
	$id=intval($tarea['id']);
	$est=tarea_texto_estado($tarea);
	$yo=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
	echo '<form method="post" action="" class="crm-form-acciones">';
	echo '<input type="hidden" name="id_tarea" value="'.$id.'">';
	if($yo!='' && !tarea_persona_inicio($tarea, $yo) && $est!='Terminada' && $est!='Pausada')
	{
		echo '<button type="submit" name="accion_tarea" value="iniciar" class="btn btn-success">Iniciar</button>';
		echo '</form>';
		return;
	}
	if($est=='En proceso')
	{
		echo '<button type="submit" name="accion_tarea" value="pausar" class="btn btn-white">Pausar</button>';
		echo '<button type="submit" name="accion_tarea" value="terminar" class="btn btn-primary">Terminar</button>';
	}
	if($est=='Pausada')
	{
		echo '<button type="submit" name="accion_tarea" value="reanudar" class="btn btn-success">Reanudar</button>';
		echo '<button type="submit" name="accion_tarea" value="terminar" class="btn btn-primary">Terminar</button>';
	}
	echo '</form>';
}

function tareas_compartidas_otras_areas($area_actual)
{
	return array();
}

// Coordinador de area: las tareas de su area.
// Empleado: solo las suyas.
function acceso_tareas_visibles($lista, $area_crm)
{
	if(!acceso_es_empleado())
	{
		return $lista;
	}
	$mias=array();
	$persona=isset($_SESSION['tareas_persona']) ? $_SESSION['tareas_persona'] : '';
	foreach($lista as $tarea)
	{
		if(tarea_es_responsable($tarea))
		{
			$mias[]=$tarea;
		}
	}
	return $mias;
}

function acceso_empleado_por_usuario($usuario)
{
	global $con;
	if($usuario=='' || !$con)
	{
		return null;
	}
	$q=@mysqli_query($con, acceso_sql_empleado_base()." WHERE e.nombre_usuario='".acceso_sql($usuario)."' LIMIT 1");
	if(!$q || mysqli_num_rows($q)==0)
	{
		return null;
	}
	$row=mysqli_fetch_assoc($q);
	$persona=acceso_fila_a_persona($row);
	$persona['primer_acceso']=isset($row['primer_acceso']) ? intval($row['primer_acceso']) : 0;
	$persona['roles_adicionales']=acceso_cargar_roles_adicionales($persona['id']);
	return $persona;
}

function acceso_empleado_por_id($id)
{
	global $con;
	$id=intval($id);
	if($id<=0 || !$con)
	{
		return null;
	}
	$q=@mysqli_query($con, acceso_sql_empleado_base()." WHERE e.id_empleado='".$id."' LIMIT 1");
	if(!$q || mysqli_num_rows($q)==0)
	{
		return null;
	}
	$row=mysqli_fetch_assoc($q);
	$persona=acceso_fila_a_persona($row);
	$persona['primer_acceso']=isset($row['primer_acceso']) ? intval($row['primer_acceso']) : 0;
	$persona['roles_adicionales']=acceso_cargar_roles_adicionales($persona['id']);
	return $persona;
}

// RRHH / superadmin guardan cambios del empleado en la tabla empleado.
function acceso_guardar_empleado($usuario, $datos)
{
	global $con;
	$persona=acceso_empleado_por_usuario($usuario);
	if(!$persona || !$con)
	{
		return false;
	}
	$id=intval($persona['id']);
	$set=array();
	if(isset($datos['nombres']))
	{
		$set[]="nombres='".acceso_sql($datos['nombres'])."'";
	}
	if(isset($datos['apellido']))
	{
		$set[]="apellido='".acceso_sql($datos['apellido'])."'";
	}
	if(isset($datos['nombre']) && !isset($datos['nombres']))
	{
		$partes=preg_split('/\s+/', trim($datos['nombre']), 2);
		$set[]="nombres='".acceso_sql($partes[0])."'";
		$set[]="apellido='".acceso_sql(isset($partes[1]) ? $partes[1] : '')."'";
	}
	if(isset($datos['correo']))
	{
		$set[]="correo='".acceso_sql($datos['correo'])."'";
	}
	$rol_nuevo=isset($datos['rol']) ? $datos['rol'] : $persona['rol'];
	$area_nueva=isset($datos['area']) ? $datos['area'] : $persona['area'];
	if($rol_nuevo==='presidencia' || $rol_nuevo==='coordinador_general')
	{
		$area_nueva='';
		$datos['area']='';
	}
	$err_ra=acceso_validar_rol_area($rol_nuevo, $area_nueva, $id);
	if($err_ra!='')
	{
		return false;
	}
	if(isset($datos['area']) || isset($datos['rol']))
	{
		if($area_nueva==='')
		{
			$set[]="id_area=NULL";
		}
		else
		{
			$id_area=acceso_id_por_clave('area', $area_nueva);
			if($id_area>0)
			{
				$set[]="id_area='".$id_area."'";
			}
		}
	}
	if(isset($datos['rol']))
	{
		if($datos['rol']=='coordinador' && $area_nueva!='' && function_exists('crm_hay_coordinador_area') && crm_hay_coordinador_area($area_nueva, $id))
		{
			return false;
		}
		$id_rol=acceso_id_por_clave('rol', $datos['rol']);
		if($id_rol>0)
		{
			$set[]="id_rol='".$id_rol."'";
		}
	}
	if(isset($datos['activo']))
	{
		$set[]="activo='".(intval($datos['activo']) ? 1 : 0)."'";
	}
	$campos_txt=array(
		'telefono'=>'telefono',
		'telefono_alt'=>'telefono_alt',
		'direccion'=>'direccion',
		'estado_civil'=>'estado_civil',
		'localidad_domicilio'=>'localidad_domicilio',
		'provincia_domicilio'=>'provincia_domicilio',
		'puesto'=>'puesto',
		'tipo_vinculo'=>'tipo_vinculo',
		'estado_laboral'=>'estado_laboral',
		'motivo_baja'=>'motivo_baja',
		'modalidad'=>'modalidad',
		'horario_jornada'=>'horario_jornada',
		'condicion_fiscal'=>'condicion_fiscal',
		'cuit_facturacion'=>'cuit_facturacion'
	);
	if(isset($datos['condicion_fiscal']))
	{
		$cf_ok=acceso_condiciones_fiscales();
		$cf_val=trim((string)$datos['condicion_fiscal']);
		if($cf_val!=='' && !isset($cf_ok[$cf_val]))
		{
			$cf_val='otro';
		}
		$datos['condicion_fiscal']=$cf_val;
	}
	foreach($campos_txt as $k=>$col)
	{
		if(isset($datos[$k]))
		{
			$set[]=$col."='".acceso_sql(trim((string)$datos[$k]))."'";
		}
	}
	foreach(array('fecha_nacimiento','fecha_ingreso','fecha_baja') as $f)
	{
		if(isset($datos[$f]))
		{
			$v=trim((string)$datos[$f]);
			$set[]=$f."=".($v!='' ? "'".acceso_sql($v)."'" : 'NULL');
		}
	}
	if(count($set)==0)
	{
		return false;
	}
	return (bool)@mysqli_query($con,"UPDATE empleado SET ".implode(', ', $set)." WHERE id_empleado='".$id."'");
}

/** Condiciones fiscales habituales (AFIP) para el legajo de personal. */
function acceso_condiciones_fiscales()
{
	return array(
		''=>'—',
		'monotributo'=>'Monotributo',
		'responsable_inscripto'=>'Responsable inscripto',
		'exento'=>'Exento',
		'no_inscripto'=>'No inscripto',
		'otro'=>'Otro'
	);
}

function acceso_condicion_fiscal_etiqueta($clave)
{
	$mapa=acceso_condiciones_fiscales();
	$clave=trim((string)$clave);
	if($clave!=='' && isset($mapa[$clave]))
	{
		return $mapa[$clave];
	}
	return $clave!=='' ? $clave : '—';
}

function acceso_alta_empleado($datos)
{
	global $con;
	if(!$con)
	{
		return 'No hay conexión con la base.';
	}
	$nombres=isset($datos['nombres']) ? trim($datos['nombres']) : '';
	$apellido=isset($datos['apellido']) ? trim($datos['apellido']) : '';
	$cuil=isset($datos['cuil']) ? preg_replace('/\D+/', '', $datos['cuil']) : '';
	$dni=isset($datos['dni']) ? trim($datos['dni']) : '';
	$correo=isset($datos['correo']) ? trim($datos['correo']) : '';
	$telefono=isset($datos['telefono']) ? trim($datos['telefono']) : '';
	$direccion=isset($datos['direccion']) ? trim($datos['direccion']) : '';
	$usuario=isset($datos['usuario']) ? trim($datos['usuario']) : '';
	$dni=crm_solo_numeros($dni);
	$clave=crm_clave_desde_dni($dni);
	$area=isset($datos['area']) ? $datos['area'] : '';
	$rol=isset($datos['rol']) ? $datos['rol'] : '';
	$ingreso=isset($datos['fecha_ingreso']) ? $datos['fecha_ingreso'] : date('Y-m-d');
	$roles_ok=acceso_roles_para_alta();
	if($nombres=='' || $apellido=='' || $usuario=='' || $clave=='' || $correo=='' || $cuil=='' || $dni=='')
	{
		return 'Faltan datos. El DNI se usa como clave (mínimo 6 números).';
	}
	if(strlen($cuil)!=11)
	{
		return 'El CUIL tiene que tener 11 números.';
	}
	if(!isset($roles_ok[$rol]))
	{
		return 'El rol no es válido.';
	}
	if($rol==='presidencia' || $rol==='coordinador_general')
	{
		$area='';
	}
	$err_ra=acceso_validar_rol_area($rol, $area);
	if($err_ra!='')
	{
		return $err_ra;
	}
	if(acceso_usuario_existe($usuario))
	{
		return 'Ese usuario ya está cargado. Probá con otro.';
	}
	if(acceso_correo_existe($correo))
	{
		return 'Ese correo ya está cargado.';
	}
	$id_rol=acceso_id_por_clave('rol', $rol);
	if($id_rol<=0)
	{
		return 'No se encontró el rol en la base.';
	}
	$id_area=0;
	if($area!='')
	{
		$id_area=acceso_id_por_clave('area', $area);
		if($id_area<=0)
		{
			return 'No se encontró el área en la base.';
		}
	}
	if($rol=='coordinador' && $area!='' && function_exists('crm_hay_coordinador_area') && crm_hay_coordinador_area($area))
	{
		return 'Esa área ya tiene un coordinador. Solo puede haber uno.';
	}
	$hash=acceso_hash_clave($clave);
	$puesto=isset($datos['puesto']) ? trim($datos['puesto']) : '';
	$tipo_vinculo=isset($datos['tipo_vinculo']) ? trim($datos['tipo_vinculo']) : '';
	$fecha_nac=isset($datos['fecha_nacimiento']) ? trim($datos['fecha_nacimiento']) : '';
	$tel_alt=isset($datos['telefono_alt']) ? trim($datos['telefono_alt']) : '';
	$estado_lab=isset($datos['estado_laboral']) ? trim($datos['estado_laboral']) : 'activo';
	$modalidad=isset($datos['modalidad']) ? trim($datos['modalidad']) : '';
	$cond_fiscal=isset($datos['condicion_fiscal']) ? trim($datos['condicion_fiscal']) : '';
	$cf_ok=acceso_condiciones_fiscales();
	if($cond_fiscal!=='' && !isset($cf_ok[$cond_fiscal]))
	{
		$cond_fiscal='otro';
	}
	$id_area_sql=($id_area>0) ? "'".$id_area."'" : 'NULL';
	$sql="INSERT INTO empleado (nombres, apellido, cuil, dni, correo, telefono, telefono_alt, direccion, fecha_nacimiento, fecha_ingreso, puesto, tipo_vinculo, estado_laboral, modalidad, condicion_fiscal, nombre_usuario, contrasena_hash, primer_acceso, id_rol, id_area, activo)
		VALUES ('".acceso_sql($nombres)."','".acceso_sql($apellido)."','".acceso_sql($cuil)."','".acceso_sql($dni)."','".acceso_sql($correo)."','".acceso_sql($telefono)."','".acceso_sql($tel_alt)."','".acceso_sql($direccion)."',".($fecha_nac!='' ? "'".acceso_sql($fecha_nac)."'" : 'NULL').",'".acceso_sql($ingreso)."','".acceso_sql($puesto)."','".acceso_sql($tipo_vinculo)."','".acceso_sql($estado_lab)."','".acceso_sql($modalidad)."','".acceso_sql($cond_fiscal)."','".acceso_sql($usuario)."','".acceso_sql($hash)."',1,'".$id_rol."',".$id_area_sql.",1)";
	if(!@mysqli_query($con, $sql))
	{
		return 'No se pudo guardar el colaborador. Revisá que CUIL, DNI, correo y usuario no estén repetidos.';
	}
	return '';
}

function acceso_area()
{
	if(!isset($_SESSION['area_usuario']) || $_SESSION['area_usuario']=='')
	{
		return '';
	}
	return $_SESSION['area_usuario'];
}

function acceso_es_coord_rrhh()
{
	return (acceso_area()=='rrhh' && !acceso_es_empleado());
}

function acceso_puede($seccion)
{
	if(acceso_es_super())
	{
		return true;
	}
	if(acceso_es_presidencia())
	{
		return false;
	}
	if(acceso_es_coord_general())
	{
		$etq=acceso_etiquetas_area();
		return isset($etq[$seccion]);
	}
	foreach(acceso_roles_adicionales_sesion() as $extra)
	{
		if(isset($extra['area']) && $extra['area']==$seccion)
		{
			return true;
		}
	}
	// RRHH coordina tambien las tareas de Recepcion.
	if($seccion=='recepcion' && acceso_es_coord_rrhh())
	{
		return true;
	}
	return (acceso_area()==$seccion);
}

// Alta de clientes, empleados o ajustes de Sistemas: solo esa area o el superadmin.
function acceso_puede_gestionar($seccion)
{
	if(acceso_es_super())
	{
		return true;
	}
	if(acceso_es_presidencia())
	{
		return false;
	}
	if($seccion==='rrhh' && acceso_es_coord_general())
	{
		return true;
	}
	if(acceso_es_empleado())
	{
		return false;
	}
	return (acceso_area()===$seccion && acceso_es_coord_de_area());
}

// El area que tiene al cliente puede verlo y cambiarle datos (telefono, etc.).
function acceso_puede_clientes_de_area($area='')
{
	if($area=='')
	{
		$area=acceso_area_pantalla();
	}
	if(acceso_es_presidencia())
	{
		return false;
	}
	if(acceso_es_super())
	{
		return true;
	}
	if($area=='recepcion')
	{
		return acceso_puede('recepcion');
	}
	crm_asegurar_area_flags();
	$row=acceso_consulta_una(
		"SELECT puede_clientes FROM area WHERE clave=? AND activo=1 LIMIT 1",
		's',
		array($area)
	);
	if(!$row || intval($row['puede_clientes'])!==1)
	{
		return false;
	}
	return (acceso_area()==$area || acceso_es_super());
}

// Comprueba si existe una columna en una tabla MySQL.
function crm_columna_existe($tabla, $columna)
{
	global $con;
	if(!$con) { return false; }
	$t_esc = mysqli_real_escape_string($con, $tabla);
	$c_esc = mysqli_real_escape_string($con, $columna);
	$res = @mysqli_query($con, "SHOW COLUMNS FROM `{$t_esc}` LIKE '{$c_esc}'");
	return ($res && mysqli_num_rows($res) > 0);
}

// Permisos para dar de alta clientes en un área operativa.
// Presidencia, Coordinación general y Admin quedan expresamente excluidas.
function acceso_puede_alta_cliente($area='')
{
	if($area=='')
	{
		$area=function_exists('acceso_area_activa') ? acceso_area_activa() : (function_exists('acceso_area_pantalla') ? acceso_area_pantalla() : acceso_area());
	}
	if($area==='' || $area==='presidencia' || $area==='general' || $area==='admin')
	{
		return false;
	}
	if(acceso_es_presidencia())
	{
		return false;
	}
	if(acceso_es_super())
	{
		return true;
	}
	crm_asegurar_area_flags();
	$row=acceso_consulta_una(
		"SELECT puede_clientes FROM area WHERE clave=? AND activo=1 LIMIT 1",
		's',
		array($area)
	);
	if(!$row || intval($row['puede_clientes'])!==1)
	{
		return false;
	}
	if(acceso_es_coord_general())
	{
		return true;
	}
	if($area==='recepcion')
	{
		return acceso_puede('recepcion');
	}
	$area_act = function_exists('acceso_area_activa') ? acceso_area_activa() : acceso_area();
	return ($area_act === $area && acceso_puede($area));
}

// Búsqueda rápida de clientes existentes por CUIT/CUIL o DNI (para prevención de duplicados).
function cliente_buscar_por_identificador($identificador)
{
	$limpio = preg_replace('/\D+/', '', (string)$identificador);
	if(strlen($limpio) < 6)
	{
		return null;
	}
	$c = acceso_consulta_una(
		"SELECT c.id_cliente, c.nombre, c.correo, c.telefono, c.tipo_cliente, c.cuil_cuit, c.id_localidad, l.nombre AS localidad_nombre, cp.dni
		FROM cliente c
		LEFT JOIN localidad l ON l.id_localidad=c.id_localidad
		LEFT JOIN cliente_persona cp ON cp.id_cliente=c.id_cliente
		WHERE REPLACE(REPLACE(REPLACE(c.cuil_cuit,'-',''),' ',''),'.','') = ?
		   OR cp.dni = ?
		   OR REPLACE(IFNULL(cp.dni,''),'.','') = ?
		LIMIT 1",
		'sss',
		array($limpio, $identificador, $limpio)
	);
	return $c ?: null;
}

// Vincula un cliente existente al área operativa seleccionada.
function cliente_vincular_area_existente($id_cliente, $clave_area)
{
	$id_cliente = intval($id_cliente);
	if($id_cliente <= 0 || $clave_area == '')
	{
		return false;
	}
	$id_area = acceso_id_por_clave('area', $clave_area);
	if($id_area <= 0)
	{
		return false;
	}
	if(function_exists('crm_vincular_cliente_area'))
	{
		crm_vincular_cliente_area($id_cliente, $id_area);
	}
	acceso_ejecutar("UPDATE cliente SET id_area_afectada=? WHERE id_cliente=? AND (id_area_afectada IS NULL OR id_area_afectada=0)", 'ii', array($id_area, $id_cliente));
	return true;
}

function acceso_exigir_gestion($seccion)
{
	if(acceso_puede_gestionar($seccion))
	{
		return;
	}
	header('Location: '.acceso_destino_login());
	exit();
}

function acceso_exigir_super()
{
	if(acceso_es_super())
	{
		return;
	}
	header('Location: '.acceso_destino_login());
	exit();
}

// Dashboard de informes: superadmin, coordinador general y Presidencia.
function acceso_exigir_dashboard()
{
	if(acceso_puede_ver_informes())
	{
		return;
	}
	header('Location: '.acceso_destino_login());
	exit();
}

function acceso_nombre()
{
	if(isset($_SESSION['nombre_mostrado']) && $_SESSION['nombre_mostrado']!='')
	{
		return $_SESSION['nombre_mostrado'];
	}
	if(acceso_es_coord_general())
	{
		return 'Coordinador general';
	}
	return 'Superadmin';
}

function acceso_destino_area()
{
	return acceso_destino_login();
}

// A donde entra cada area al iniciar sesion.
function acceso_destino_login()
{
	if(isset($_SESSION['primer_acceso']) && intval($_SESSION['primer_acceso'])==1)
	{
		return url_crm('change-password.php');
	}
	if(acceso_es_presidencia())
	{
		return url_crm('admin/informes-presidencia.php');
	}
	if(acceso_es_super())
	{
		return url_crm('admin/home.php');
	}
	if(function_exists('crm_dashboard_destino'))
	{
		return crm_dashboard_destino();
	}
	$area=acceso_area();
	if($area!='' && file_exists(BASE_PATH.'/public/'.$area.'/dashboard.php'))
	{
		return url_crm($area.'/dashboard.php');
	}
	if($area!='' && file_exists(BASE_PATH.'/public/'.$area.'/tareas.php'))
	{
		return url_crm($area.'/tareas.php');
	}
	return url_crm('admin/home.php');
}

}
?>
