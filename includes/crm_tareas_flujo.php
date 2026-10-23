<?php
// Flujo extra de tareas: subtareas, cambios avisados, vencimientos y baja con aprobación.

/**
 * Schema: solo CREATE IF NOT EXISTS, 1 vez por día.
 * NUNCA hacer ALTER ADD CONSTRAINT en el request caliente (traba MySQL).
 */
function crm_tareas_flujo_asegurar()
{
	global $con;
	static $listo=false;
	if($listo || !$con)
	{
		return;
	}
	$listo=true;

	if(crm_schema_al_dia('flujo_tareas'))
	{
		return;
	}

	if(!acceso_tabla_existe('crm_aviso'))
	{
		@mysqli_query($con, "CREATE TABLE IF NOT EXISTS crm_aviso (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_empleado_para INT UNSIGNED NOT NULL,
			id_empleado_de INT UNSIGNED NULL,
			id_tarea INT UNSIGNED NULL,
			tipo VARCHAR(40) NOT NULL DEFAULT 'info',
			titulo VARCHAR(200) NOT NULL,
			detalle TEXT NULL,
			url VARCHAR(300) NULL,
			confirmado TINYINT(1) NOT NULL DEFAULT 0,
			fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX idx_crmaviso_bandeja (id_empleado_para, confirmado, fecha)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
	if(!acceso_tabla_existe('tarea_solicitud_baja'))
	{
		@mysqli_query($con, "CREATE TABLE IF NOT EXISTS tarea_solicitud_baja (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			id_tarea INT UNSIGNED NOT NULL,
			id_solicita INT UNSIGNED NOT NULL,
			motivo TEXT NOT NULL,
			estado ENUM('pendiente_coord','pendiente_general','aceptada','rechazada') NOT NULL DEFAULT 'pendiente_coord',
			id_revisa_coord INT UNSIGNED NULL,
			nota_coord TEXT NULL,
			id_revisa_general INT UNSIGNED NULL,
			nota_general TEXT NULL,
			fecha_solicitud DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			fecha_resolucion DATETIME NULL,
			INDEX idx_tsb_tarea (id_tarea),
			INDEX idx_tsb_estado (estado)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
	if(!acceso_tabla_existe('tarea_aviso_vencimiento'))
	{
		@mysqli_query($con, "CREATE TABLE IF NOT EXISTS tarea_aviso_vencimiento (
			id_tarea INT UNSIGNED NOT NULL,
			fecha_aviso DATE NOT NULL,
			PRIMARY KEY (id_tarea, fecha_aviso)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
	}
	crm_schema_marcar('flujo_tareas');
}

function crm_aviso_enviar($id_para, $titulo, $detalle, $url='', $id_tarea=0, $tipo='info')
{
	global $con;
	$id_para=intval($id_para);
	if($id_para<=0 || trim($titulo)=='' || !$con || !acceso_tabla_existe('crm_aviso'))
	{
		return false;
	}
	$de=intval(crm_id_empleado_sesion());
	$id_tarea=intval($id_tarea);
	$url=trim($url);
	$titulo=mysqli_real_escape_string($con, trim($titulo));
	$detalle=mysqli_real_escape_string($con, trim($detalle));
	$tipo=mysqli_real_escape_string($con, trim($tipo)=='' ? 'info' : trim($tipo));
	$url_sql=($url==='') ? 'NULL' : "'".mysqli_real_escape_string($con, $url)."'";
	$de_sql=($de>0) ? strval($de) : 'NULL';
	$tar_sql=($id_tarea>0) ? strval($id_tarea) : 'NULL';
	$sql="INSERT INTO crm_aviso (id_empleado_para, id_empleado_de, id_tarea, tipo, titulo, detalle, url)
		VALUES (".$id_para.",".$de_sql.",".$tar_sql.",'".$tipo."','".$titulo."','".$detalle."',".$url_sql.")";
	return @mysqli_query($con, $sql) ? true : false;
}

/** Create due business-follow-up notices through the existing CRM inbox. */
function crm_empresa_seguimiento_notificaciones_generar()
{
	if(!acceso_tabla_existe('crm_aviso') || !acceso_tabla_existe('empresa_seguimiento'))
	{
		return;
	}
	$area=acceso_consulta_una("SELECT id_area FROM area WHERE clave='empresas' AND activo=1 AND puede_clientes=1 LIMIT 1");
	if(!$area || intval($area['id_area'])<=0)
	{
		return;
	}
	$id_area_empresas=intval($area['id_area']);
	$condicion_rol_adicional=acceso_tabla_existe('empleado_rol_adicional')
		? " OR EXISTS (
			SELECT 1 FROM empleado_rol_adicional x
			INNER JOIN rol rx ON rx.id_rol=x.id_rol
			INNER JOIN area ax ON ax.id_area=x.id_area
			WHERE x.id_empleado=e.id_empleado AND x.id_area=? AND x.fecha_hasta IS NULL
			AND rx.clave='coordinador' AND ax.clave='empresas' AND ax.activo=1
		)"
		: '';
	$parametros_destinatarios=array($id_area_empresas);
	$tipos_destinatarios='i';
	if($condicion_rol_adicional!=='')
	{
		$parametros_destinatarios[]=$id_area_empresas;
		$tipos_destinatarios.='i';
	}
	$destinatarios=acceso_consulta(
		"SELECT DISTINCT e.id_empleado
		 FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
		 WHERE e.activo=1 AND ((e.id_area=? AND r.clave='coordinador')".$condicion_rol_adicional.")",
		$tipos_destinatarios,
		$parametros_destinatarios
	);
	if(!$destinatarios)
	{
		return;
	}
	$seguimientos=acceso_consulta(
		"SELECT s.id_seguimiento, s.id_cliente, s.tipo_seguimiento, s.fecha_prevista, s.motivo, c.nombre AS empresa_nombre
		 FROM empresa_seguimiento s
		 INNER JOIN cliente c ON c.id_cliente=s.id_cliente AND c.tipo_cliente='empresa'
		 WHERE s.estado='pendiente' AND s.fecha_realizacion IS NULL AND s.fecha_prevista<=?
		 ORDER BY s.fecha_prevista ASC, s.id_seguimiento ASC",
		's',
		array(date('Y-m-d'))
	);
	foreach($destinatarios as $destinatario)
	{
		$id_para=intval($destinatario['id_empleado']);
		if($id_para<=0) { continue; }
		foreach($seguimientos as $seguimiento)
		{
			$id_seguimiento=intval($seguimiento['id_seguimiento']);
			$id_cliente=intval($seguimiento['id_cliente']);
			$fecha_prevista=(string)$seguimiento['fecha_prevista'];
			$url=url_crm('empresas/seguimientos.php?seguimiento_id='.$id_seguimiento.'&fecha_prevista='.rawurlencode($fecha_prevista));
			$lock_name='crm_emp_seg_'.$id_para.'_'.$id_seguimiento;
			$lock=acceso_consulta_una('SELECT GET_LOCK(?,2) AS adquirido', 's', array($lock_name));
			if(!$lock || intval($lock['adquirido'])!==1) { continue; }
			$es_manual=$seguimiento['tipo_seguimiento']==='manual';
			$hito=$es_manual ? 'Manual' : ($seguimiento['tipo_seguimiento']==='3_meses' ? '3 meses' : '6 meses');
			$nombre_empresa=trim((string)$seguimiento['empresa_nombre']);
			$motivo=trim((string)$seguimiento['motivo']);
			$fecha_formateada=date('d/m/Y', strtotime($fecha_prevista));
			if($fecha_prevista===date('Y-m-d'))
			{
				$titulo=$es_manual ? 'Hoy corresponde realizar el seguimiento manual de '.$nombre_empresa : 'Hoy corresponde realizar el seguimiento de '.$hito.' de '.$nombre_empresa;
				$detalle='Fecha prevista: '.$fecha_formateada.($es_manual && $motivo!=='' ? ' · Motivo: '.$motivo : '');
			}
			else
			{
				$titulo='Seguimiento empresarial vencido';
				$detalle='Pendiente desde '.$fecha_formateada.' · '.$nombre_empresa.' · '.($es_manual ? 'Seguimiento manual' : 'Hito de '.$hito).($es_manual && $motivo!=='' ? ' · Motivo: '.$motivo : '');
			}
			$posibles_existentes=acceso_consulta(
				"SELECT id, url FROM crm_aviso
				 WHERE id_empleado_para=? AND tipo='empresa_seguimiento'
				 AND url LIKE CONCAT('%seguimiento_id=',?,'%')",
				'ii',
				array($id_para, $id_seguimiento)
			);
			$existente=null;
			foreach($posibles_existentes as $aviso_existente)
			{
				$partes_url_existente=parse_url(isset($aviso_existente['url']) ? (string)$aviso_existente['url'] : '');
				$parametros_url_existente=array();
				if(isset($partes_url_existente['query'])) { parse_str($partes_url_existente['query'], $parametros_url_existente); }
				if(isset($parametros_url_existente['seguimiento_id'], $parametros_url_existente['fecha_prevista'])
					&& intval($parametros_url_existente['seguimiento_id'])===$id_seguimiento
					&& (string)$parametros_url_existente['fecha_prevista']===$fecha_prevista)
				{
					$existente=$aviso_existente;
					break;
				}
			}
			if($existente)
			{
				// Actualiza destino y texto sin cambiar leído/no leído ni crear otro aviso.
				acceso_ejecutar(
					"UPDATE crm_aviso SET titulo=?, detalle=?, url=? WHERE id=? AND id_empleado_para=?",
					'sssii',
					array($titulo, $detalle, $url, intval($existente['id']), $id_para)
				);
			}
			else
			{
				acceso_insertar(
					"INSERT INTO crm_aviso (id_empleado_para, id_empleado_de, id_tarea, tipo, titulo, detalle, url) VALUES (?,NULL,NULL,?,?,?,?)",
					'issss',
					array($id_para, 'empresa_seguimiento', $titulo, $detalle, $url)
				);
			}
			acceso_consulta_una('SELECT RELEASE_LOCK(?) AS liberado', 's', array($lock_name));
		}
	}
}

function crm_empresa_seguimiento_notificacion_autorizada($id_empleado, $id_seguimiento, $fecha_prevista_notificada)
{
	$id_empleado=intval($id_empleado);
	$id_seguimiento=intval($id_seguimiento);
	$fecha_prevista_notificada=trim((string)$fecha_prevista_notificada);
	if($id_empleado<=0 || $id_seguimiento<=0
		|| !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_prevista_notificada)
		|| !isset($_SESSION['rol_area']) || $_SESSION['rol_area']!=='coordinador'
		|| !function_exists('crm_es_staff') || !crm_es_staff()
		|| !function_exists('acceso_puede_clientes_de_area') || !acceso_puede_clientes_de_area('empresas'))
	{
		return false;
	}
	$condicion_rol_adicional=acceso_tabla_existe('empleado_rol_adicional')
		? " OR EXISTS (
			SELECT 1 FROM empleado_rol_adicional x
			INNER JOIN rol rx ON rx.id_rol=x.id_rol
			INNER JOIN area ax ON ax.id_area=x.id_area
			WHERE x.id_empleado=e.id_empleado AND x.fecha_hasta IS NULL
			AND rx.clave='coordinador' AND ax.clave='empresas' AND ax.activo=1
		)"
		: '';
	if(!acceso_consulta_una(
		"SELECT e.id_empleado FROM empleado e
		 LEFT JOIN rol r ON r.id_rol=e.id_rol
		 LEFT JOIN area a ON a.id_area=e.id_area
		 WHERE e.id_empleado=? AND e.activo=1
		 AND ((r.clave='coordinador' AND a.clave='empresas' AND a.activo=1)".$condicion_rol_adicional.")
		 LIMIT 1",
		'i',
		array($id_empleado)
	))
	{
		return false;
	}
	$fila=acceso_consulta_una(
		"SELECT s.id_cliente, s.estado, s.fecha_prevista, s.fecha_realizacion
		 FROM empresa_seguimiento s
		 INNER JOIN cliente c ON c.id_cliente=s.id_cliente AND c.tipo_cliente='empresa'
		 WHERE s.id_seguimiento=? LIMIT 1",
		'i',
		array($id_seguimiento)
	);
	if(!$fila || $fila['estado']!=='pendiente' || !empty($fila['fecha_realizacion'])
		|| $fila['fecha_prevista']!==$fecha_prevista_notificada || $fila['fecha_prevista']>date('Y-m-d'))
	{
		return false;
	}
	return function_exists('crm_cliente_puede_ver') && crm_cliente_puede_ver(intval($fila['id_cliente']), 'empresas');
}

function crm_aviso_etiqueta_tipo($tipo)
{
	$map=array(
		'subtarea'=>'Subtarea',
		'cambio'=>'Cambio',
		'vencimiento'=>'Vencida',
		'baja'=>'Eliminar',
		'actividad'=>'Actividad',
		'empresa_seguimiento'=>'Seguimiento',
		'dominio'=>'Dominio',
		'tarea'=>'Tarea',
		'info'=>'Aviso'
	);
	$t=trim($tipo);
	return isset($map[$t]) ? $map[$t] : 'Aviso';
}

function crm_avisos_extra_mios()
{
	$yo=crm_id_empleado_sesion();
	$area_activa=function_exists('crm_notificacion_area_activa') ? crm_notificacion_area_activa() : '';
	if($yo<=0 || $area_activa==='' || !acceso_tabla_existe('crm_aviso'))
	{
		return array();
	}
	crm_empresa_seguimiento_notificaciones_generar();
	$rows=acceso_consulta(
		"SELECT id, id_tarea, tipo, titulo, detalle, url, fecha FROM crm_aviso
		WHERE id_empleado_para=? AND confirmado=0
		ORDER BY fecha DESC",
		'i',
		array($yo)
	);
	$salida=array();
	$dominios_notificacion_vistos=array();
	foreach($rows as $r)
	{
		$url_original=isset($r['url']) ? (string)$r['url'] : '';
		$tipo_av=isset($r['tipo']) ? trim($r['tipo']) : 'info';
		if(function_exists('crm_notificacion_corresponde_area')
			&& !crm_notificacion_corresponde_area($tipo_av, intval($r['id_tarea']), $url_original))
		{
			continue;
		}
		$url=isset($r['url']) && $r['url']!='' ? $r['url'] : '';
		if($url!='')
		{
			$pos_pub=strpos($url, '/public/');
			if($pos_pub!==false)
			{
				$rel=substr($url, $pos_pub + 8);
				$url=url_crm($rel);
			}
			else if(!preg_match('/^https?:\/\//i', $url) && (strpos($url, '/')===false || substr($url, 0, 1)!=='/'))
			{
				$url=url_crm($url);
			}
		}
		if($url!='' && function_exists('crm_notificacion_resolver_url_accesible'))
		{
			$url=crm_notificacion_resolver_url_accesible($url, intval($r['id_tarea']));
		}
		$id_entidad=intval($r['id_tarea']);
		$accion_etiqueta='';
		if($tipo_av==='empresa_seguimiento')
		{
			$id_seguimiento=0;
			$fecha_prevista_notificada='';
			$partes_seguimiento=parse_url(isset($r['url']) ? (string)$r['url'] : '');
			if(isset($partes_seguimiento['query']))
			{
				parse_str($partes_seguimiento['query'], $parametros_seguimiento);
				$id_seguimiento=isset($parametros_seguimiento['seguimiento_id']) ? intval($parametros_seguimiento['seguimiento_id']) : 0;
				$fecha_prevista_notificada=isset($parametros_seguimiento['fecha_prevista']) ? (string)$parametros_seguimiento['fecha_prevista'] : '';
			}
			if(!crm_empresa_seguimiento_notificacion_autorizada($yo, $id_seguimiento, $fecha_prevista_notificada))
			{
				acceso_ejecutar('UPDATE crm_aviso SET confirmado=1 WHERE id=? AND id_empleado_para=?', 'ii', array(intval($r['id']), $yo));
				continue;
			}
			$id_entidad=$id_seguimiento;
			$accion_etiqueta='Ver seguimiento';
		}
		$es_aviso_dominio=($tipo_av==='dominio');
		if(!$es_aviso_dominio && isset($r['titulo']) && preg_match('/^Dominio (?:vencido|próximo a vencer)\s*[·-]/u', $r['titulo']))
		{
			$es_aviso_dominio=true;
		}
		if($tipo_av==='vencimiento' && $url!=='')
		{
			$partes_url=parse_url($url);
			$es_aviso_dominio=isset($partes_url['path']) && strpos($partes_url['path'], 'dominios.php')!==false;
		}
		if($es_aviso_dominio)
		{
			$nombre_dominio='';
			$partes_url=parse_url($url);
			if(isset($partes_url['query']))
			{
				parse_str($partes_url['query'], $parametros_url);
				$nombre_dominio=isset($parametros_url['q']) ? trim((string)$parametros_url['q']) : '';
			}
			if($nombre_dominio==='' && isset($r['titulo'])
				&& preg_match('/^Dominio (?:vencido|próximo a vencer)\s*[·-]\s*(.+)$/u', $r['titulo'], $coincidencia_dominio))
			{
				$nombre_dominio=trim($coincidencia_dominio[1]);
			}
			$dominio_actual=($nombre_dominio!=='' && acceso_tabla_existe('dominio'))
				? acceso_consulta_una("SELECT id_dominio, nombre, fecha_vencimiento, activo FROM dominio WHERE nombre=? LIMIT 1", 's', array($nombre_dominio))
				: null;
			if($dominio_actual && !empty($dominio_actual['fecha_vencimiento']))
			{
				$id_dominio_notificacion=intval($dominio_actual['id_dominio']);
				if(isset($dominios_notificacion_vistos[$id_dominio_notificacion])) { continue; }
				$dominios_notificacion_vistos[$id_dominio_notificacion]=true;
				$fecha_dom=DateTime::createFromFormat('!Y-m-d', substr($dominio_actual['fecha_vencimiento'], 0, 10));
				if(!$fecha_dom) { continue; }
				if($fecha_dom)
				{
					$hoy_dom=new DateTime('today');
					$dias_dom=(int)$hoy_dom->diff($fecha_dom)->format('%r%a');
					$unidad_dias_dom=(abs($dias_dom)===1 ? 'día' : 'días');
					if(intval($dominio_actual['activo'])===1 && $dias_dom>30) { continue; }
					if(intval($dominio_actual['activo'])===1 && $dias_dom>=0)
					{
						$r['titulo']='Dominio próximo a vencer';
						$r['detalle']=$dominio_actual['nombre'].' · Vence '.$fecha_dom->format('d/m/Y').' · Faltan '.$dias_dom.' '.$unidad_dias_dom.'.';
					}
					else if(intval($dominio_actual['activo'])===1)
					{
						$r['titulo']='Dominio vencido';
						$r['detalle']=$dominio_actual['nombre'].' · Venció '.$fecha_dom->format('d/m/Y').' · Atraso de '.abs($dias_dom).' '.$unidad_dias_dom.'.';
					}
					else
					{
						continue;
					}
				}
			}
			if(!$dominio_actual || empty($dominio_actual['fecha_vencimiento']))
			{
				continue;
			}
		}
		if($tipo_av==='actividad')
		{
			$ev_id=0;
			if(preg_match('/[?&]id=(\d+)/', $url, $m))
			{
				$ev_id=intval($m[1]);
			}
			if($ev_id>0)
			{
				$id_entidad=$ev_id;
				if(function_exists('acceso_tabla_existe') && acceso_tabla_existe('evento'))
				{
					$ev_chk=acceso_consulta_una("SELECT activo FROM evento WHERE id_evento=? LIMIT 1", 'i', array($ev_id));
					// Solo se auto-resuelve si la actividad fue eliminada (activo = 0).
					// NO se descarta por fecha pasada: puede quedar atrasada y requerir atencion.
					if($ev_chk && intval(isset($ev_chk['activo']) ? $ev_chk['activo'] : 1)===0)
					{
						acceso_ejecutar("UPDATE crm_aviso SET confirmado=1 WHERE id=?", 'i', array(intval($r['id'])));
						continue;
					}
				}
			}
		}
		if(intval($r['id_tarea'])>0 && function_exists('acceso_tabla_existe') && acceso_tabla_existe('tarea'))
		{
			$t_chk=acceso_consulta_una("SELECT estado FROM tarea WHERE id_tarea=?", 'i', array(intval($r['id_tarea'])));
			if($t_chk && isset($t_chk['estado']))
			{
				$est_t=strtolower(trim($t_chk['estado']));

				// Si la tarea fue terminada o cancelada, se auto-resuelve el aviso
				// (excepto si el aviso es justamente de baja/eliminación, donde debe informarse al usuario)
				if($tipo_av!=='baja' && ($est_t==='terminada' || $est_t==='cancelada'))
				{
					acceso_ejecutar("UPDATE crm_aviso SET confirmado=1 WHERE id=?", 'i', array(intval($r['id'])));
					continue;
				}

				// Si es aviso de nueva tarea ('tarea') o subtarea ('subtarea'):
				// Se resuelve cuando el empleado la inicia (tarea_inicio) o pasa a en_proceso.
				// NO se resuelve si está 'pausada'.
				if($tipo_av==='tarea' || $tipo_av==='subtarea')
				{
					$ya_inicio=false;
					if(function_exists('acceso_tabla_existe') && acceso_tabla_existe('tarea_inicio'))
					{
						$ti=acceso_consulta_una("SELECT id_empleado FROM tarea_inicio WHERE id_tarea=? AND id_empleado=? LIMIT 1", 'ii', array(intval($r['id_tarea']), $yo));
						if($ti)
						{
							$ya_inicio=true;
						}
					}
					if($ya_inicio || $est_t==='en_proceso')
					{
						acceso_ejecutar("UPDATE crm_aviso SET confirmado=1 WHERE id=?", 'i', array(intval($r['id'])));
						continue;
					}
				}
				// Para otros avisos ('vencimiento', 'baja'): NO se resuelven si la tarea está pausada o en curso
			}
		}
		if($url=='' && intval($r['id_tarea'])>0)
		{
			$area=acceso_area_pantalla();
			$url=url_crm($area.'/ver-tarea.php?id='.intval($r['id_tarea']));
		}
		$aviso=array(
			'tipo'=>'crm',
			'id'=>intval($r['id']),
			'id_entidad'=>$id_entidad,
			'id_tarea'=>intval($r['id_tarea']),
			'titulo'=>$r['titulo'],
			'detalle'=>isset($r['detalle']) ? $r['detalle'] : '',
			'url'=>$url!='' ? $url : url_crm(acceso_area_pantalla().'/tareas.php'),
			'campo'=>'cerrar_crm_aviso',
			'etiqueta'=>crm_aviso_etiqueta_tipo(isset($r['tipo']) ? $r['tipo'] : ''),
			'fecha'=>isset($r['fecha']) ? $r['fecha'] : ''
		);
		if($accion_etiqueta!=='') { $aviso['accion_etiqueta']=$accion_etiqueta; }
		$salida[]=$aviso;
	}
	return $salida;
}

function crm_aviso_extra_ok($id)
{
	$id=intval($id);
	$yo=crm_id_empleado_sesion();
	if($id<=0 || $yo<=0 || !acceso_tabla_existe('crm_aviso'))
	{
		return;
	}
	$fila=acceso_consulta_una("SELECT id_tarea, tipo, url FROM crm_aviso WHERE id=? AND id_empleado_para=? LIMIT 1", 'ii', array($id, $yo));
	if(!$fila || !function_exists('crm_notificacion_corresponde_area')
		|| !crm_notificacion_corresponde_area($fila['tipo'], intval($fila['id_tarea']), $fila['url']))
	{
		return;
	}
	acceso_ejecutar("UPDATE crm_aviso SET confirmado=1 WHERE id=? AND id_empleado_para=?", 'ii', array($id, $yo));
}

// crm_empleado_coord_area vive en crm_extra.php (no redeclarar).

function crm_id_coord_general_empleado()
{
	$row=acceso_consulta_una(
		"SELECT e.id_empleado FROM empleado e
		INNER JOIN empleado_rol_adicional x ON x.id_empleado=e.id_empleado AND x.fecha_hasta IS NULL
		INNER JOIN rol r ON r.id_rol=x.id_rol AND r.clave='coordinador_general'
		WHERE e.activo=1 LIMIT 1"
	);
	if($row)
	{
		return intval($row['id_empleado']);
	}
	$row=acceso_consulta_una(
		"SELECT e.id_empleado FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
		WHERE r.clave='coordinador_general' AND e.activo=1 LIMIT 1"
	);
	return $row ? intval($row['id_empleado']) : 0;
}

function crm_tarea_fila($id_tarea)
{
	return acceso_consulta_una(
		"SELECT t.*, a.clave AS area_clave, a.nombre AS area_nombre
		FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
		WHERE t.id_tarea=? LIMIT 1",
		'i',
		array(intval($id_tarea))
	);
}

/**
 * Permite validar solicitudes de baja con la relación de responsables cargada
 * desde tarea_responsable, sin ampliar la visibilidad por área.
 */
function crm_tarea_baja_fila_autorizada($id_tarea)
{
	$id=intval($id_tarea);
	if($id<=0)
	{
		return null;
	}
	if(!function_exists('repo_tarea_por_id'))
	{
		$repositorio=__DIR__.'/tareas_repositorio.php';
		if(!is_file($repositorio))
		{
			return null;
		}
		require_once $repositorio;
	}
	if(!function_exists('repo_tarea_por_id'))
	{
		return null;
	}
	$tarea=repo_tarea_por_id($id);
	if(!$tarea || !function_exists('tarea_puede_ver') || !tarea_puede_ver($tarea))
	{
		return null;
	}
	return $tarea;
}

/** Los empleados deben figurar como asignados en la relación vigente de la tarea. */
function crm_tarea_baja_empleado_asignado($id_tarea)
{
	if(!acceso_es_empleado())
	{
		return true;
	}
	$id_tarea=intval($id_tarea);
	$id_empleado=crm_id_empleado_sesion();
	if($id_tarea<=0 || $id_empleado<=0 || !acceso_tabla_existe('tarea_responsable'))
	{
		return false;
	}
	$fila=acceso_consulta_una(
		"SELECT id_tarea FROM tarea_responsable WHERE id_tarea=? AND id_empleado=? LIMIT 1",
		'ii',
		array($id_tarea,$id_empleado)
	);
	return $fila!==null;
}

function crm_tarea_hijas($id_padre)
{
	$id=intval($id_padre);
	if($id<=0)
	{
		return array();
	}
	return acceso_consulta(
		"SELECT t.id_tarea, t.titulo, t.estado, t.avance, t.fecha_cierre,
			a.clave AS area_clave,
			(SELECT CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))
			 FROM tarea_responsable tr INNER JOIN empleado e ON e.id_empleado=tr.id_empleado
			 WHERE tr.id_tarea=t.id_tarea ORDER BY tr.es_principal DESC, tr.id ASC LIMIT 1) AS responsable
		FROM tarea t
		INNER JOIN area a ON a.id_area=t.id_area
		WHERE t.id_tarea_padre=? AND t.estado<>'cancelada'
		ORDER BY t.id_tarea ASC",
		'i',
		array($id)
	);
}

function crm_tarea_puede_desglosar($tarea)
{
	if(!$tarea || acceso_es_empleado())
	{
		return false;
	}
	$id=isset($tarea['id']) ? intval($tarea['id']) : 0;
	if($id<=0)
	{
		return false;
	}
	$fila=crm_tarea_fila($id);
	if(!$fila || $fila['estado']=='cancelada' || (function_exists('tarea_estado_finalizada') && tarea_estado_finalizada($fila)))
	{
		return false;
	}
	if(isset($fila['id_tarea_padre']) && intval($fila['id_tarea_padre'])>0)
	{
		return false;
	}
	if(function_exists('tarea_puede_ver') && !tarea_puede_ver($fila))
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general() || acceso_es_general())
	{
		return true;
	}
	return (isset($fila['area_clave']) && $fila['area_clave']==acceso_area_pantalla());
}

/**
 * Alta de parte (subtarea): un INSERT escapado, sin prepared/null, sin ALTER.
 */
function crm_tarea_crear_subtarea($id_padre, $titulo, $id_empleado_resp, $fecha_cierre='')
{
	global $con;
	if(!$con)
	{
		return 'Sin conexión a la base.';
	}
	@mysqli_query($con, 'SET SESSION innodb_lock_wait_timeout=5');
	@mysqli_query($con, 'SET SESSION lock_wait_timeout=5');

	$id_padre=intval($id_padre);
	$titulo=trim($titulo);
	$id_emp=intval($id_empleado_resp);
	$padre=crm_tarea_fila($id_padre);
	if(!$padre || $padre['estado']=='cancelada' || (function_exists('tarea_estado_finalizada') && tarea_estado_finalizada($padre)))
	{
		return 'Esa tarea no admite subtareas nuevas porque se encuentra finalizada o eliminada.';
	}
	if(!function_exists('crm_tarea_puede_desglosar') || !crm_tarea_puede_desglosar(array('id'=>$id_padre)))
	{
		return 'No tenés permisos para agregar subtareas en esta tarea.';
	}
	if(isset($padre['id_tarea_padre']) && intval($padre['id_tarea_padre'])>0)
	{
		return 'Esa tarea ya es una subtarea. No se puede desglosar otra vez.';
	}
	if($titulo==='')
	{
		return 'Poné un título para la subtarea.';
	}
	if($id_emp<=0)
	{
		return 'Elegí quién la hace.';
	}

	$id_area=intval($padre['id_area']);
	$id_tipo=intval($padre['id_tipo']);
	if($id_area<=0 || $id_tipo<=0)
	{
		return 'La tarea padre no tiene área u objetivo válidos.';
	}

	$fc=trim($fecha_cierre);
	if($fc!=='')
	{
		if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fc))
		{
			return 'Revisá la fecha estimada (formato AAAA-MM-DD).';
		}
		$hoy=date('Y-m-d');
		if($fc<$hoy)
		{
			return 'La fecha estimada de la subtarea no puede ser anterior a la fecha actual.';
		}
		if(!empty($padre['creado_en']))
		{
			$f_crea=substr($padre['creado_en'], 0, 10);
			if($fc<$f_crea)
			{
				return 'La fecha de la subtarea no puede ser anterior a la fecha de creación de la tarea.';
			}
		}
		if(!empty($padre['fecha_inicio']))
		{
			$fi_padre=substr($padre['fecha_inicio'], 0, 10);
			if($fc<$fi_padre)
			{
				return 'La fecha estimada de la subtarea no puede ser anterior a la fecha de inicio de la tarea.';
			}
		}
	}
	else
	{
		$fc=!empty($padre['fecha_cierre']) ? substr($padre['fecha_cierre'], 0, 10) : date('Y-m-d');
		if($fc<date('Y-m-d'))
		{
			$fc=date('Y-m-d');
		}
	}
	$fi=!empty($padre['fecha_inicio']) ? substr($padre['fecha_inicio'], 0, 10) : date('Y-m-d');
	if(strtotime($fc)<strtotime($fi))
	{
		$fi=$fc;
	}

	$prio=strtolower(trim(isset($padre['prioridad']) ? $padre['prioridad'] : 'media'));
	if(!in_array($prio, array('urgente','alta','media','baja'), true))
	{
		$prio='media';
	}

	$id_cli_sql=(isset($padre['id_cliente']) && intval($padre['id_cliente'])>0) ? intval($padre['id_cliente']) : 'NULL';
	$id_at_sql=(isset($padre['id_atencion']) && intval($padre['id_atencion'])>0) ? intval($padre['id_atencion']) : 'NULL';
	$titulo_e=mysqli_real_escape_string($con, $titulo);
	$desc_e=mysqli_real_escape_string($con, 'Subtarea de: '.$padre['titulo']);
	$prio_e=mysqli_real_escape_string($con, $prio);
	$fi_e=mysqli_real_escape_string($con, $fi);
	$fc_e=mysqli_real_escape_string($con, $fc);

	$q="INSERT INTO tarea (titulo, descripcion, id_area, id_tipo, prioridad, estado, fecha_inicio, fecha_cierre, avance, id_cliente, id_atencion, id_tarea_padre)
		VALUES ('{$titulo_e}','{$desc_e}',{$id_area},{$id_tipo},'{$prio_e}','no_iniciada','{$fi_e}','{$fc_e}',0,{$id_cli_sql},{$id_at_sql},{$id_padre})";
	if(!@mysqli_query($con, $q))
	{
		return 'No se pudo crear la subtarea. ('.mysqli_error($con).')';
	}
	$id=intval(mysqli_insert_id($con));
	if($id<=0)
	{
		return 'No se pudo crear la subtarea.';
	}

	@mysqli_query($con, "INSERT INTO tarea_responsable (id_tarea, id_empleado, es_principal) VALUES (".$id.",".$id_emp.",1)");

	// Promedio de subtareas (1 terminada + 1 nueva = 50%; todas ok = 100%).
	crm_tarea_recalcular_padre($id_padre);

	$area_clave=isset($padre['area_clave']) ? $padre['area_clave'] : acceso_area_pantalla();
	$url=url_crm($area_clave.'/ver-tarea.php?id='.$id);
	@crm_aviso_enviar($id_emp, 'Te sumaron una subtarea', 'Te toca: "'.$titulo.'" (de "'.$padre['titulo'].'").', $url, $id, 'subtarea');

	return '';
}

/**
 * Padre = promedio de avance de sus partes.
 * Todas terminadas → padre 100% terminada.
 * Ej: 1/2 terminada → 50% en proceso (reabre si estaba terminada).
 */
function crm_tarea_recalcular_padre($id_padre)
{
	global $con;
	$id=intval($id_padre);
	if($id<=0 || !$con)
	{
		return;
	}
	$hijas=crm_tarea_hijas($id);
	if(count($hijas)==0)
	{
		return;
	}
	$sum=0;
	$todas_ok=true;
	foreach($hijas as $h)
	{
		$av=intval($h['avance']);
		if($h['estado']==='terminada')
		{
			$av=100;
		}
		$sum+=$av;
		if($h['estado']!=='terminada')
		{
			$todas_ok=false;
		}
	}
	$pct=intval(round($sum/count($hijas)));
	if($todas_ok)
	{
		@mysqli_query($con, "UPDATE tarea SET avance=100, estado='terminada', fecha_cierre_real=CURDATE() WHERE id_tarea=".$id." AND estado<>'cancelada'");
	}
	else
	{
		$est=($pct>0) ? 'en_proceso' : 'no_iniciada';
		@mysqli_query($con, "UPDATE tarea SET avance=".intval($pct).", estado='".$est."', fecha_cierre_real=NULL WHERE id_tarea=".$id." AND estado<>'cancelada'");
	}
}

/** Después de guardar estado/avance de una tarea: actualizar el padre si es parte. */
function crm_tarea_sync_padre_de($id_tarea)
{
	$id=intval($id_tarea);
	if($id<=0)
	{
		return;
	}
	$f=crm_tarea_fila($id);
	if($f && isset($f['id_tarea_padre']) && intval($f['id_tarea_padre'])>0)
	{
		crm_tarea_recalcular_padre(intval($f['id_tarea_padre']));
	}
}

function crm_tarea_equipo_ids($area_clave='')
{
	if($area_clave=='')
	{
		$area_clave=acceso_area_pantalla();
	}
	// Si estamos en /general/, usar el área de la tarea vía pantalla o sesión operativa
	if($area_clave==='general' || $area_clave==='')
	{
		$area_clave=acceso_area();
	}
	$id_area=acceso_id_por_clave('area', $area_clave);
	if($id_area<=0)
	{
		return array();
	}
	$salida=array();
	$fuente=function_exists('crm_empleados_de_area_id')
		? crm_empleados_de_area_id($id_area)
		: acceso_consulta(
			"SELECT e.id_empleado, CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS nombre
			FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol
			WHERE e.id_area=? AND e.activo=1 AND r.clave<>'superadmin'
			ORDER BY e.nombres, e.apellido",
			'i',
			array($id_area)
		);
	foreach($fuente as $e)
	{
		$salida[]=array(
			'id_empleado'=>intval($e['id_empleado']),
			'nombre'=>isset($e['nombre'])
				? $e['nombre']
				: trim((isset($e['nombres']) ? $e['nombres'] : '').' '.(isset($e['apellido']) ? $e['apellido'] : ''))
		);
	}
	return $salida;
}

function crm_tarea_armar_detalle_cambios($antes, $despues)
{
	$lineas=array();
	$map=array(
		'titulo'=>'Título',
		'descripcion'=>'Descripción',
		'prioridad'=>'Prioridad',
		'fecha_inicio'=>'Fecha de inicio',
		'fecha_cierre'=>'Fecha estimada',
		'estado'=>'Estado'
	);
	foreach($map as $k=>$etiq)
	{
		$a=isset($antes[$k]) ? trim((string)$antes[$k]) : '';
		$b=isset($despues[$k]) ? trim((string)$despues[$k]) : '';
		if($a!==$b)
		{
			$lineas[]=$etiq.': "'.$a.'" → "'.$b.'"';
		}
	}
	$ra=isset($antes['responsables_txt']) ? trim((string)$antes['responsables_txt']) : '';
	$rb=isset($despues['responsables_txt']) ? trim((string)$despues['responsables_txt']) : '';
	if($ra=='' && function_exists('tarea_texto_responsables'))
	{
		$ra=tarea_texto_responsables($antes);
	}
	if($rb=='' && function_exists('tarea_texto_responsables'))
	{
		$rb=tarea_texto_responsables($despues);
	}
	if($ra!==$rb)
	{
		$lineas[]='Responsables: "'.$ra.'" → "'.$rb.'"';
	}
	return $lineas;
}

/** Nombres de responsables normalizados para comparar. */
function crm_tarea_nombres_responsables($tarea)
{
	$lista=array();
	if(isset($tarea['responsables']) && is_array($tarea['responsables']))
	{
		foreach($tarea['responsables'] as $nom)
		{
			$nom=trim((string)$nom);
			if($nom!='')
			{
				$lista[]=$nom;
			}
		}
	}
	else if(isset($tarea['responsables_txt']) && trim($tarea['responsables_txt'])!='')
	{
		foreach(explode(',', $tarea['responsables_txt']) as $nom)
		{
			$nom=trim($nom);
			if($nom!='')
			{
				$lista[]=$nom;
			}
		}
	}
	else if(function_exists('tarea_texto_responsables'))
	{
		$txt=trim(tarea_texto_responsables($tarea));
		if($txt!='')
		{
			foreach(explode(',', $txt) as $nom)
			{
				$nom=trim($nom);
				if($nom!='')
				{
					$lista[]=$nom;
				}
			}
		}
	}
	return array_values(array_unique($lista));
}

function crm_tarea_id_empleado_por_nombre($nombre)
{
	$nombre=trim((string)$nombre);
	if($nombre==='')
	{
		return 0;
	}
	if(function_exists('repo_nombre_a_id'))
	{
		$id=intval(repo_nombre_a_id($nombre));
		if($id>0)
		{
			return $id;
		}
	}
	if(function_exists('acceso_id_empleado_por_persona'))
	{
		return intval(acceso_id_empleado_por_persona($nombre));
	}
	return 0;
}

/**
 * Avisa por reasignación / cambios al editar una tarea.
 * Los responsables nuevos reciben aviso de asignación (campana + tabla aviso).
 */
function crm_tarea_notificar_edicion($id_tarea, $antes, $despues, $motivo='')
{
	$id=intval($id_tarea);
	if($id<=0 || !is_array($antes) || !is_array($despues))
	{
		return;
	}
	$antes_n=crm_tarea_nombres_responsables($antes);
	$despues_n=crm_tarea_nombres_responsables($despues);
	$nuevos=array();
	foreach($despues_n as $nom)
	{
		if(!in_array($nom, $antes_n, true))
		{
			$nuevos[]=$nom;
		}
	}
	$area='';
	if(!empty($despues['area_clave']))
	{
		$area=$despues['area_clave'];
	}
	else if(!empty($antes['area_clave']))
	{
		$area=$antes['area_clave'];
	}
	else
	{
		$area=acceso_area_pantalla();
	}
	$titulo=isset($despues['titulo']) ? trim($despues['titulo']) : (isset($antes['titulo']) ? $antes['titulo'] : 'Tarea');
	$url=url_crm($area.'/ver-tarea.php?id='.$id);
	$de=crm_id_empleado_sesion();
	$yo_nom=isset($_SESSION['tareas_persona']) ? trim($_SESSION['tareas_persona']) : '';

	// 1) Responsables nuevos: notificación de asignación
	if(count($nuevos)>0)
	{
		if(function_exists('tareas_avisos_registrar'))
		{
			tareas_avisos_registrar($nuevos, $titulo, $id, $area, $despues);
		}
	}

	// 2) Otros cambios (fecha, título, etc.): avisar a responsables que siguen
	$cambios=crm_tarea_armar_detalle_cambios($antes, $despues);
	$solo_resp=(count($cambios)==1 && strpos($cambios[0], 'Responsables:')===0);
	if(count($cambios)==0 || $solo_resp)
	{
		return;
	}
	$detalle=implode(' · ', $cambios);
	if(trim($motivo)!='')
	{
		$detalle.=' Motivo: '.$motivo;
	}
	foreach($despues_n as $nom)
	{
		if($yo_nom!='' && $nom===$yo_nom)
		{
			continue;
		}
		// Los recién asignados ya recibieron aviso de asignación
		if(in_array($nom, $nuevos, true))
		{
			continue;
		}
		$id_para=crm_tarea_id_empleado_por_nombre($nom);
		if($id_para<=0)
		{
			continue;
		}
		crm_aviso_enviar(
			$id_para,
			'Actualizaron tu tarea',
			'“'.$titulo.'”: '.$detalle,
			$url,
			$id,
			'cambio'
		);
	}
}

function crm_tarea_revisar_vencidas()
{
	static $hecho=false;
	if($hecho || !acceso_tabla_existe('tarea') || !acceso_tabla_existe('tarea_aviso_vencimiento'))
	{
		return;
	}
	$hecho=true;
	$hoy=date('Y-m-d');
	$vencidas=acceso_consulta(
		"SELECT t.id_tarea, t.titulo, t.id_area, a.clave AS area_clave
		FROM tarea t INNER JOIN area a ON a.id_area=t.id_area
		WHERE t.fecha_cierre < ? AND t.estado NOT IN ('terminada','cancelada')
		AND NOT EXISTS (
			SELECT 1 FROM tarea_aviso_vencimiento v WHERE v.id_tarea=t.id_tarea AND v.fecha_aviso=?
		)
		LIMIT 10",
		'ss',
		array($hoy, $hoy)
	);
	if(count($vencidas)==0)
	{
		return;
	}
	$gen=crm_id_coord_general_empleado();
	foreach($vencidas as $t)
	{
		$id=intval($t['id_tarea']);
		$url=url_crm($t['area_clave'].'/ver-tarea.php?id='.$id);
		$detalle='"'.$t['titulo'].'" ya pasó la fecha estimada y sigue abierta.';
		$coord=crm_empleado_coord_area(intval($t['id_area']));
		$id_coord=$coord ? intval($coord['id_empleado']) : 0;
		if($id_coord>0)
		{
			crm_aviso_enviar($id_coord, 'Atención: tarea vencida', $detalle, $url, $id, 'vencimiento');
		}
		if($gen>0 && $gen!=$id_coord)
		{
			crm_aviso_enviar($gen, 'Tarea vencida', $detalle.' ('.$t['area_clave'].')', $url, $id, 'vencimiento');
		}
		@acceso_insertar("INSERT IGNORE INTO tarea_aviso_vencimiento (id_tarea, fecha_aviso) VALUES (?,?)", 'is', array($id, $hoy));
	}
}

function crm_tarea_solicitud_activa($id_tarea)
{
	if(!acceso_tabla_existe('tarea_solicitud_baja'))
	{
		return null;
	}
	return acceso_consulta_una(
		"SELECT * FROM tarea_solicitud_baja WHERE id_tarea=? AND estado IN ('pendiente_coord','pendiente_general') ORDER BY id DESC LIMIT 1",
		'i',
		array(intval($id_tarea))
	);
}

/**
 * Devuelve el pedido de cambio de fechas pendiente de una tarea.
 * La tabla se agrega mediante una migración explícita; nunca se crea en el request.
 */
function crm_tarea_cambio_fecha_pendiente($id_tarea)
{
	if(!acceso_tabla_existe('tarea_solicitud_cambio_fecha')) { return null; }
	return acceso_consulta_una(
		"SELECT s.*, CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS nombre_solicitante
		FROM tarea_solicitud_cambio_fecha s LEFT JOIN empleado e ON e.id_empleado=s.id_solicita
		WHERE s.id_tarea=? AND s.estado='pendiente' ORDER BY s.id DESC LIMIT 1",
		'i', array(intval($id_tarea))
	);
}

/** Devuelve el último pedido de cambio de fechas del usuario actual para la tarea. */
function crm_tarea_cambio_fecha_mio($id_tarea)
{
	$yo=crm_id_empleado_sesion();
	if($yo<=0 || !acceso_tabla_existe('tarea_solicitud_cambio_fecha')) { return null; }
	return acceso_consulta_una(
		"SELECT s.*, CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS nombre_resuelve
		FROM tarea_solicitud_cambio_fecha s LEFT JOIN empleado e ON e.id_empleado=s.id_resuelve
		WHERE s.id_tarea=? AND s.id_solicita=? ORDER BY s.id DESC LIMIT 1",
		'ii', array(intval($id_tarea), $yo)
	);
}

function crm_tarea_fecha_iso_valida($fecha)
{
	$fecha=trim((string)$fecha);
	if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $fecha)) { return false; }
	$partes=explode('-', $fecha);
	return checkdate(intval($partes[1]), intval($partes[2]), intval($partes[0]));
}

/** Valida el rango sin rechazar una fecha pasada que permanece sin cambios. */
function crm_tarea_cambio_fecha_validar_propuesta($inicio, $cierre, $inicio_original, $cierre_original)
{
	$inicio=trim((string)$inicio);
	$cierre=trim((string)$cierre);
	$inicio_original=trim((string)$inicio_original);
	$cierre_original=trim((string)$cierre_original);
	if(!crm_tarea_fecha_iso_valida($inicio) || !crm_tarea_fecha_iso_valida($cierre))
	{
		return 'Ingresá fechas válidas para inicio y vencimiento.';
	}
	if($cierre<$inicio) { return 'La fecha de vencimiento no puede ser anterior a la fecha de inicio.'; }
	$hoy=date('Y-m-d');
	if($inicio!==$inicio_original && $inicio<$hoy)
	{
		return 'La nueva fecha de inicio no puede ser anterior a la fecha actual.';
	}
	if($cierre!==$cierre_original && $cierre<$hoy)
	{
		return 'La nueva fecha de vencimiento no puede ser anterior a la fecha actual.';
	}
	return '';
}

/** Solo un empleado asignado a la tarea puede pedir el cambio de fechas. */
function crm_tarea_puede_solicitar_cambio_fecha($tarea)
{
	if(!is_array($tarea) || !acceso_es_empleado() || !function_exists('tarea_puede_ver') || !tarea_puede_ver($tarea)
		|| !function_exists('tarea_es_responsable') || !tarea_es_responsable($tarea)
		|| (function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($tarea)))
	{
		return false;
	}
	return acceso_tabla_existe('tarea_solicitud_cambio_fecha');
}

function crm_tarea_puede_resolver_cambio_fecha($tarea, $solicitud)
{
	if(!is_array($tarea) || !is_array($solicitud) || $solicitud['estado']!=='pendiente'
		|| intval($solicitud['id_solicita'])===crm_id_empleado_sesion()
		|| !function_exists('tarea_puede_ver') || !tarea_puede_ver($tarea))
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general()) { return true; }
	$area=isset($tarea['area_clave']) ? trim((string)$tarea['area_clave']) : '';
	return $area!=='' && function_exists('acceso_es_coord_de_area') && acceso_es_coord_de_area($area);
}

function crm_tarea_solicitar_cambio_fecha($id_tarea, $fecha_inicio, $fecha_cierre, $motivo)
{
	global $con;
	$id=intval($id_tarea);
	$yo=crm_id_empleado_sesion();
	$fecha_inicio=trim((string)$fecha_inicio);
	$fecha_cierre=trim((string)$fecha_cierre);
	$motivo=trim((string)$motivo);
	if($id<=0 || $yo<=0) { return 'No se pudo identificar la tarea o la sesión.'; }
	if(!acceso_tabla_existe('tarea_solicitud_cambio_fecha')) { return 'El flujo todavía no está habilitado: falta aplicar la migración de solicitudes de cambio de fecha.'; }
	if(!$con || !@mysqli_begin_transaction($con)) { return 'No se pudo iniciar la solicitud.'; }
	$bloqueo=acceso_consulta_una("SELECT id_tarea FROM tarea WHERE id_tarea=? FOR UPDATE", 'i', array($id));
	$fila=$bloqueo ? crm_tarea_fila($id) : null;
	if(!$fila || !crm_tarea_puede_solicitar_cambio_fecha($fila))
	{
		@mysqli_rollback($con);
		return 'Solo podés solicitar cambios para una tarea asignada a vos y autorizada para tu consulta.';
	}
	$inicio_actual=substr((string)$fila['fecha_inicio'], 0, 10);
	$cierre_actual=substr((string)$fila['fecha_cierre'], 0, 10);
	if($fecha_inicio===$inicio_actual && $fecha_cierre===$cierre_actual)
	{
		@mysqli_rollback($con);
		return 'Modificá al menos una de las fechas para enviar la solicitud.';
	}
	if($motivo==='')
	{
		@mysqli_rollback($con);
		return 'El motivo es obligatorio.';
	}
	if(strlen($motivo)>5000)
	{
		@mysqli_rollback($con);
		return 'El motivo no puede superar los 5000 caracteres.';
	}
	$validacion=crm_tarea_cambio_fecha_validar_propuesta($fecha_inicio,$fecha_cierre,$inicio_actual,$cierre_actual);
	if($validacion!=='')
	{
		@mysqli_rollback($con);
		return $validacion;
	}
	if(crm_tarea_cambio_fecha_pendiente($id))
	{
		@mysqli_rollback($con);
		return 'Ya existe una solicitud de cambio de fecha pendiente para esta tarea.';
	}
	$sol_activa=crm_tarea_solicitud_activa($id);
	if($sol_activa)
	{
		@mysqli_rollback($con);
		return 'La tarea tiene otro pedido pendiente. Esperá a que se resuelva antes de solicitar un cambio de fecha.';
	}
	$id_solicitud=acceso_insertar(
		"INSERT INTO tarea_solicitud_cambio_fecha (id_tarea,id_solicita,fecha_inicio_original,fecha_cierre_original,fecha_inicio_propuesta,fecha_cierre_propuesta,motivo,estado) VALUES (?,?,?,?,?,?,?,'pendiente')",
		'iisssss', array($id,$yo,$inicio_actual,$cierre_actual,$fecha_inicio,$fecha_cierre,$motivo)
	);
	if($id_solicitud<=0 || !@mysqli_commit($con))
	{
		@mysqli_rollback($con);
		return 'No se pudo registrar la solicitud.';
	}
	$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.$id);
	$coord=crm_empleado_coord_area(intval($fila['id_area']));
	if($coord && intval($coord['id_empleado'])!==$yo)
	{
		crm_aviso_enviar(intval($coord['id_empleado']), 'Solicitud de cambio de fecha', 'Solicitan cambiar las fechas de “'.$fila['titulo'].'”. Motivo: '.$motivo, $url, $id, 'cambio');
	}
	else
	{
		$general=crm_id_coord_general_empleado();
		if($general>0 && $general!==$yo)
		{
			crm_aviso_enviar($general, 'Solicitud de cambio de fecha', 'Solicitan cambiar las fechas de “'.$fila['titulo'].'”. Motivo: '.$motivo, $url, $id, 'cambio');
		}
	}
	return '';
}

function crm_tarea_cambio_fecha_decidir($id_solicitud, $id_tarea_contexto, $aprobar, $motivo_rechazo='')
{
	global $con;
	$id_s=intval($id_solicitud);
	$id_contexto=intval($id_tarea_contexto);
	$yo=crm_id_empleado_sesion();
	$motivo_rechazo=trim((string)$motivo_rechazo);
	$sol_inicial=($id_s>0 && acceso_tabla_existe('tarea_solicitud_cambio_fecha'))
		? acceso_consulta_una("SELECT id_tarea FROM tarea_solicitud_cambio_fecha WHERE id=? LIMIT 1", 'i', array($id_s)) : null;
	if(!$sol_inicial || $yo<=0 || intval($sol_inicial['id_tarea'])!==$id_contexto)
	{
		return 'Solicitud no encontrada para esta tarea.';
	}
	if(!$con || !@mysqli_begin_transaction($con)) { return 'No se pudo iniciar la revisión.'; }
	$bloqueo=acceso_consulta_una("SELECT id_tarea FROM tarea WHERE id_tarea=? FOR UPDATE", 'i', array($id_contexto));
	$sol=$bloqueo ? acceso_consulta_una("SELECT * FROM tarea_solicitud_cambio_fecha WHERE id=? FOR UPDATE", 'i', array($id_s)) : null;
	$fila=$sol ? crm_tarea_fila($id_contexto) : null;
	if(!$sol || !$fila || $sol['estado']!=='pendiente')
	{
		@mysqli_rollback($con);
		return 'La solicitud ya fue resuelta o la tarea no está disponible.';
	}
	if(!crm_tarea_puede_resolver_cambio_fecha($fila,$sol))
	{
		@mysqli_rollback($con);
		return intval($sol['id_solicita'])===$yo ? 'No podés aprobar ni rechazar tu propia solicitud.' : 'No tenés autorización para resolver solicitudes de esta área.';
	}
	if(!$aprobar && $motivo_rechazo==='')
	{
		@mysqli_rollback($con);
		return 'Indicá el motivo del rechazo.';
	}
	if(strlen($motivo_rechazo)>5000)
	{
		@mysqli_rollback($con);
		return 'El motivo de rechazo no puede superar los 5000 caracteres.';
	}
	$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.$id_contexto);
	if($aprobar)
	{
		$actual_inicio=substr((string)$fila['fecha_inicio'],0,10);
		$actual_cierre=substr((string)$fila['fecha_cierre'],0,10);
		if($actual_inicio!==substr((string)$sol['fecha_inicio_original'],0,10) || $actual_cierre!==substr((string)$sol['fecha_cierre_original'],0,10))
		{
			@mysqli_rollback($con);
			return 'Las fechas de la tarea cambiaron desde que se envió el pedido. Revisá el estado actual antes de resolverlo.';
		}
		$validacion=crm_tarea_cambio_fecha_validar_propuesta(
			$sol['fecha_inicio_propuesta'],$sol['fecha_cierre_propuesta'],
			$sol['fecha_inicio_original'],$sol['fecha_cierre_original']
		);
		if($validacion!=='')
		{
			@mysqli_rollback($con);
			return $validacion!=='' ? $validacion : 'Las fechas propuestas no son válidas.';
		}
		$ok=acceso_ejecutar(
			"UPDATE tarea SET fecha_inicio=?, fecha_cierre=? WHERE id_tarea=? AND fecha_inicio=? AND fecha_cierre=?",
			'ssiss', array($sol['fecha_inicio_propuesta'],$sol['fecha_cierre_propuesta'],$id_contexto,$sol['fecha_inicio_original'],$sol['fecha_cierre_original'])
		);
		$ok=$ok && acceso_ejecutar("UPDATE tarea_solicitud_cambio_fecha SET estado='aprobada', id_resuelve=?, motivo_resolucion=NULL, fecha_resolucion=NOW() WHERE id=? AND estado='pendiente'", 'ii', array($yo,$id_s));
		if(!$ok || !@mysqli_commit($con))
		{
			@mysqli_rollback($con);
			return 'No se pudo aprobar la solicitud ni actualizar las fechas.';
		}
		crm_aviso_enviar(intval($sol['id_solicita']), 'Cambio de fecha aprobado', 'Se actualizaron las fechas de “'.$fila['titulo'].'”.', $url, $id_contexto, 'cambio');
		return '';
	}
	$ok=acceso_ejecutar("UPDATE tarea_solicitud_cambio_fecha SET estado='rechazada', id_resuelve=?, motivo_resolucion=?, fecha_resolucion=NOW() WHERE id=? AND estado='pendiente'", 'isi', array($yo,$motivo_rechazo,$id_s));
	if(!$ok || !@mysqli_commit($con))
	{
		@mysqli_rollback($con);
		return 'No se pudo rechazar la solicitud.';
	}
	crm_aviso_enviar(intval($sol['id_solicita']), 'Cambio de fecha rechazado', '“'.$fila['titulo'].'” conserva sus fechas originales. Motivo: '.$motivo_rechazo, $url, $id_contexto, 'cambio');
	return '';
}

function crm_tarea_solicitar_baja($id_tarea, $motivo)
{
	global $con;
	crm_tareas_flujo_asegurar();
	$id=intval($id_tarea);
	$motivo=trim($motivo);
	$yo=crm_id_empleado_sesion();
	$fila=crm_tarea_fila($id);
	if(!$fila || $fila['estado']=='cancelada')
	{
		return 'Esa tarea no se puede eliminar.';
	}
	if(function_exists('tarea_padre_finalizada') && tarea_padre_finalizada($fila))
	{
		return 'No se puede eliminar una subtarea mientras su tarea principal esté finalizada. Primero debe reabrirla un coordinador autorizado.';
	}
	$permiso_inicial=crm_tarea_baja_fila_autorizada($id);
	if(!$permiso_inicial || !crm_tarea_baja_empleado_asignado($id))
	{
		return acceso_es_empleado()
			? 'Solo podés solicitar la eliminación de una tarea asignada a vos y visible en el área activa.'
			: 'No tenés permisos para solicitar la eliminación de esta tarea.';
	}
	if($motivo==='')
	{
		return 'Indicá el motivo.';
	}
	if($yo<=0)
	{
		return 'Sesión inválida.';
	}
	if(!$con || !@mysqli_begin_transaction($con))
	{
		return 'No se pudo iniciar el pedido de eliminación.';
	}
	$bloqueo=acceso_consulta_una("SELECT id_tarea FROM tarea WHERE id_tarea=? FOR UPDATE", 'i', array($id));
	if(!$bloqueo)
	{
		@mysqli_rollback($con);
		return 'Esa tarea ya no está disponible para solicitar su eliminación.';
	}
	$fila=crm_tarea_fila($id);
	$permiso_bloqueado=crm_tarea_baja_fila_autorizada($id);
	$padre_finalizado=($fila && function_exists('tarea_padre_finalizada') && tarea_padre_finalizada($fila));
	if(!$fila || $fila['estado']==='cancelada' || $padre_finalizado || !$permiso_bloqueado || !crm_tarea_baja_empleado_asignado($id))
	{
		@mysqli_rollback($con);
		return $padre_finalizado
			? 'No se puede solicitar la eliminación de una subtarea mientras su tarea principal esté finalizada.'
			: (acceso_es_empleado()
				? 'Solo podés solicitar la eliminación de una tarea asignada a vos y visible en el área activa.'
				: 'No tenés permisos para solicitar la eliminación de esta tarea.');
	}
	if(crm_tarea_solicitud_activa($id))
	{
		@mysqli_rollback($con);
		return 'Ya hay un pedido de eliminación en curso.';
	}
	$est=acceso_es_empleado() ? 'pendiente_coord' : 'pendiente_general';
	$id_solicitud=acceso_insertar(
		"INSERT INTO tarea_solicitud_baja (id_tarea, id_solicita, motivo, estado) VALUES (?,?,?,?)",
		'iiss',
		array($id, $yo, $motivo, $est)
	);
	if($id_solicitud<=0 || !@mysqli_commit($con))
	{
		@mysqli_rollback($con);
		return 'No se pudo registrar el pedido de eliminación.';
	}
	$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.$id);
	if($est=='pendiente_coord')
	{
		$coord=crm_empleado_coord_area(intval($fila['id_area']));
		if($coord)
		{
			crm_aviso_enviar(intval($coord['id_empleado']), 'Pedido de eliminación', 'Pidieron eliminar “'.$fila['titulo'].'”. Motivo: '.$motivo, $url, $id, 'baja');
		}
	}
	else
	{
		$gen=crm_id_coord_general_empleado();
		if($gen>0)
		{
			crm_aviso_enviar($gen, 'Pedido de eliminación', 'Pidieron eliminar “'.$fila['titulo'].'”. Motivo: '.$motivo, $url, $id, 'baja');
		}
	}
	return '';
}

function crm_tarea_baja_decidir($id_solicitud, $aceptar, $nota='')
{
	global $con;
	crm_tareas_flujo_asegurar();
	$id_s=intval($id_solicitud);
	$yo=crm_id_empleado_sesion();
	$nota=trim($nota);
	$sol_inicial=acceso_consulta_una("SELECT id_tarea FROM tarea_solicitud_baja WHERE id=? LIMIT 1", 'i', array($id_s));
	if($id_s<=0 || !$sol_inicial || $yo<=0)
	{
		return 'Pedido no encontrado.';
	}
	if(!$con || !@mysqli_begin_transaction($con))
	{
		return 'No se pudo iniciar la revisión del pedido.';
	}
	$bloqueo=acceso_consulta_una("SELECT id_tarea FROM tarea WHERE id_tarea=? FOR UPDATE", 'i', array(intval($sol_inicial['id_tarea'])));
	if(!$bloqueo)
	{
		@mysqli_rollback($con);
		return 'La tarea ya no existe.';
	}
	$sol=acceso_consulta_una("SELECT * FROM tarea_solicitud_baja WHERE id=? FOR UPDATE", 'i', array($id_s));
	if(!$sol || intval($sol['id_tarea'])!==intval($sol_inicial['id_tarea']))
	{
		@mysqli_rollback($con);
		return 'Pedido no encontrado.';
	}
	$fila=crm_tarea_fila(intval($sol['id_tarea']));
	if(!$fila || $fila['estado']==='cancelada')
	{
		@mysqli_rollback($con);
		return 'La tarea ya no está disponible para resolver este pedido.';
	}
	if($aceptar && function_exists('tarea_padre_finalizada') && tarea_padre_finalizada($fila))
	{
		@mysqli_rollback($con);
		return 'No se puede eliminar una subtarea mientras su tarea principal esté finalizada. Primero debe reabrirla un coordinador autorizado.';
	}
	if(intval($sol['id_solicita'])===$yo)
	{
		@mysqli_rollback($con);
		return 'No podés aprobar ni rechazar tu propio pedido.';
	}
	$es_final=(acceso_es_coord_general() || acceso_es_super());
	$estado=$sol['estado'];
	if(!in_array($estado,array('pendiente_coord','pendiente_general'),true))
	{
		@mysqli_rollback($con);
		return 'Ese pedido ya fue resuelto.';
	}
	if($estado==='pendiente_coord')
	{
		$area_tarea=isset($fila['area_clave']) ? $fila['area_clave'] : '';
		if(!$es_final && (!function_exists('acceso_es_coord_de_area') || !acceso_es_coord_de_area($area_tarea)))
		{
			@mysqli_rollback($con);
			return 'Solo el coordinador autorizado del área puede resolver esto.';
		}
		if(!$aceptar)
		{
			if($es_final)
			{
				$ok=acceso_ejecutar(
					"UPDATE tarea_solicitud_baja SET estado='rechazada', id_revisa_general=?, nota_general=?, fecha_resolucion=NOW() WHERE id=? AND estado='pendiente_coord'",
					'isi',
					array($yo, $nota!=='' ? $nota : 'Rechazado', $id_s)
				);
			}
			else
			{
				$ok=acceso_ejecutar(
					"UPDATE tarea_solicitud_baja SET estado='rechazada', id_revisa_coord=?, nota_coord=?, fecha_resolucion=NOW() WHERE id=? AND estado='pendiente_coord'",
					'isi',
					array($yo, $nota!=='' ? $nota : 'Rechazado', $id_s)
				);
			}
			if(!$ok || !@mysqli_commit($con))
			{
				@mysqli_rollback($con);
				return 'No se pudo guardar la decisión.';
			}
			$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.intval($fila['id_tarea']));
			crm_aviso_enviar(intval($sol['id_solicita']), 'No se eliminó la tarea', '“'.$fila['titulo'].'” sigue activa. '.($nota!=='' ? $nota : ''), $url, intval($fila['id_tarea']), 'baja');
			return '';
		}
		if($es_final)
		{
			$estado='pendiente_general';
		}
		else
		{
			$ok=acceso_ejecutar(
				"UPDATE tarea_solicitud_baja SET estado='aceptada', id_revisa_coord=?, nota_coord=?, fecha_resolucion=NOW() WHERE id=? AND estado='pendiente_coord'",
				'isi',
				array($yo, $nota!=='' ? $nota : 'Aprobado por coordinación de área', $id_s)
			);
			$ok=$ok && acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea=?", 'i', array(intval($fila['id_tarea'])));
			$ok=$ok && acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea_padre=?", 'i', array(intval($fila['id_tarea'])));
			if(!$ok || !@mysqli_commit($con))
			{
				@mysqli_rollback($con);
				return 'No se pudo aprobar el pedido de eliminación.';
			}
			$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.intval($fila['id_tarea']));
			crm_aviso_enviar(intval($sol['id_solicita']), 'Listo: tarea eliminada', '“'.$fila['titulo'].'” quedó eliminada.', $url, intval($fila['id_tarea']), 'baja');
			return '';
		}
	}
	if($estado!=='pendiente_general' || !$es_final)
	{
		@mysqli_rollback($con);
		return 'Solo Coordinación General o Superadmin puede dar el OK final.';
	}
	$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.intval($fila['id_tarea']));
	if(!$aceptar)
	{
		$ok=acceso_ejecutar(
			"UPDATE tarea_solicitud_baja SET estado='rechazada', id_revisa_general=?, nota_general=?, fecha_resolucion=NOW() WHERE id=? AND estado IN ('pendiente_general','pendiente_coord')",
			'isi',
			array($yo, $nota!=='' ? $nota : 'Rechazado', $id_s)
		);
		if(!$ok || !@mysqli_commit($con))
		{
			@mysqli_rollback($con);
			return 'No se pudo guardar la decisión.';
		}
		crm_aviso_enviar(intval($sol['id_solicita']), 'No se eliminó la tarea', '“'.$fila['titulo'].'” sigue activa. '.($nota!=='' ? $nota : ''), $url, intval($fila['id_tarea']), 'baja');
		return '';
	}
	$ok=acceso_ejecutar(
		"UPDATE tarea_solicitud_baja SET estado='aceptada', id_revisa_general=?, nota_general=?, fecha_resolucion=NOW() WHERE id=? AND estado IN ('pendiente_general','pendiente_coord')",
		'isi',
		array($yo, $nota!=='' ? $nota : 'Eliminada', $id_s)
	);
	$ok=$ok && acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea=?", 'i', array(intval($fila['id_tarea'])));
	$ok=$ok && acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea_padre=?", 'i', array(intval($fila['id_tarea'])));
	if(!$ok || !@mysqli_commit($con))
	{
		@mysqli_rollback($con);
		return 'No se pudo cancelar la tarea.';
	}
	crm_aviso_enviar(intval($sol['id_solicita']), 'Listo: tarea eliminada', '“'.$fila['titulo'].'” quedó eliminada.', $url, intval($fila['id_tarea']), 'baja');
	return '';
}

function crm_html_orden_tarea()
{
	$orden = isset($_GET['orden']) ? trim($_GET['orden']) : '';
	ob_start();
	?>
	<div class="form-group crm-tareas-orden">
		<label>Ordenar por</label>
		<select name="orden" class="form-control" onchange="this.form.submit()">
			<option value="recientes" <?php if($orden==='recientes' || $orden==='') echo 'selected'; ?>>Más recientes</option>
			<option value="antiguas" <?php if($orden==='antiguas') echo 'selected'; ?>>Más antiguas</option>
			<option value="vence" <?php if($orden==='vence') echo 'selected'; ?>>Próximas a vencer</option>
		</select>
	</div>
	<?php
	return ob_get_clean();
}

function crm_html_filtros_avanzados_tarea()
{
	$buscar=isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
	ob_start();
	?>
	<div class="form-group crm-tareas-buscar-group">
		<label for="filtro_buscar_tarea">Tarea</label>
		<input type="search" id="filtro_buscar_tarea" name="buscar" class="form-control" value="<?php echo htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nombre o descripción" autocomplete="off" oninput="clearTimeout(window.crmTareasBuscarTimer);var f=this.form;window.crmTareasBuscarTimer=setTimeout(function(){f.submit();},450);">
	</div>
	<?php
	return ob_get_clean();
}

function crm_tareas_filtros_campos_html($cal, $dia_cal)
{
	$estado=isset($_GET['estado']) ? (string)$_GET['estado'] : '';
	$mes=isset($_GET['mes']) ? (string)$_GET['mes'] : '';
	$prioridad=isset($_GET['prioridad']) ? (string)$_GET['prioridad'] : '';
	$responsable=isset($_GET['responsable']) ? (string)$_GET['responsable'] : '';
	$objetivo=isset($_GET['tipo']) ? (string)$_GET['tipo'] : '';
	$area=isset($_GET['area']) ? (string)$_GET['area'] : '';
	$areas=tareas_valores_unicos('area');
	$mostrar_area=(count($areas)>1);
	ob_start();
	?>
	<style>
	.crm-tareas-filtros{display:grid;grid-template-columns:minmax(0,1fr);width:100%;min-width:0;margin:0}
	.crm-tareas-filtros-row{display:grid;align-items:end;gap:6px;min-width:0;width:100%}
	.crm-tareas-filtros-row-principal{grid-template-columns:minmax(145px,1.45fr) minmax(180px,1.7fr) minmax(80px,.8fr) minmax(88px,.9fr) minmax(90px,.9fr) minmax(115px,1.15fr) minmax(110px,1.1fr) minmax(125px,1.25fr) auto}
	.crm-tareas-filtros-row-principal.crm-tareas-filtros-con-area{grid-template-columns:minmax(130px,1.35fr) minmax(165px,1.55fr) minmax(72px,.72fr) minmax(78px,.78fr) minmax(82px,.82fr) minmax(100px,1fr) minmax(100px,1fr) minmax(100px,1fr) minmax(112px,1.12fr) auto}
	.crm-tareas-filtros .form-group{display:block;margin:0;min-width:0;max-width:none}
	.crm-tareas-filtros label{display:block;white-space:nowrap;margin:0 0 4px;font-size:11px;line-height:1.2;color:#64748b}
	.crm-tareas-filtros .form-control{height:32px;width:100%;max-width:none;min-width:0;padding:5px 7px;font-size:12px;box-sizing:border-box}
	.crm-tareas-filtros #filtro_cliente_input{width:100%!important}
	.crm-tareas-filtros-row > a.btn{align-self:end;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;white-space:nowrap;height:36px;line-height:20px;padding:5px 8px;margin:0!important}
	@media(min-width:768px) and (max-width:1199px){.crm-tareas-filtros-row-principal,.crm-tareas-filtros-row-principal.crm-tareas-filtros-con-area{grid-template-columns:minmax(140px,1.5fr) minmax(160px,1.6fr) repeat(3,minmax(85px,.9fr))}.crm-tareas-filtros-row-principal .crm-tareas-orden{grid-column:span 2}}
	@media(max-width:767px){.crm-tareas-filtros-row-principal,.crm-tareas-filtros-row-principal.crm-tareas-filtros-con-area{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.crm-tareas-filtros-row-principal .crm-tareas-buscar-group,.crm-tareas-filtros-row-principal .crm-tareas-cliente-group{grid-column:span 2}.crm-tareas-filtros-row > a.btn{justify-self:start}}
	@media(max-width:480px){.crm-tareas-filtros-row-principal,.crm-tareas-filtros-row-principal.crm-tareas-filtros-con-area{grid-template-columns:minmax(0,1fr)}}
	</style>
	<input type="hidden" name="cal" value="<?php echo htmlspecialchars((string)$cal,ENT_QUOTES,'UTF-8'); ?>">
	<input type="hidden" name="dia" value="<?php echo htmlspecialchars((string)$dia_cal,ENT_QUOTES,'UTF-8'); ?>">
	<div class="crm-tareas-filtros-row crm-tareas-filtros-row-principal<?php echo $mostrar_area?' crm-tareas-filtros-con-area':''; ?>">
		<?php echo crm_html_filtros_avanzados_tarea(); ?>
		<?php if(function_exists('crm_html_filtro_cliente_tarea')) { echo crm_html_filtro_cliente_tarea(); } ?>
		<div class="form-group"><label>Estado</label><select name="estado" class="form-control" onchange="this.form.submit()">
			<option value="">Todos</option>
			<?php foreach(array('No iniciada','En proceso','Pausada','Terminada','Vencida','Eliminada') as $op) { $sel=($estado===$op || ($op==='No iniciada' && $estado==='Pendiente') || ($op==='Eliminada' && $estado==='cancelada'))?' selected':''; ?>
			<option value="<?php echo htmlspecialchars($op,ENT_QUOTES,'UTF-8'); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($op,ENT_QUOTES,'UTF-8'); ?></option><?php } ?>
		</select></div>
		<div class="form-group"><label>Prioridad</label><select name="prioridad" class="form-control" onchange="this.form.submit()"><option value="">Todas</option><?php foreach(tareas_valores_unicos('prioridad') as $op) { ?><option value="<?php echo texto_mostrar($op); ?>"<?php if($prioridad===$op) echo ' selected'; ?>><?php echo texto_mostrar($op); ?></option><?php } ?></select></div>
		<div class="form-group"><label>Mes</label><select name="mes" class="form-control" onchange="this.form.submit()"><option value="">Todos</option><?php foreach(tareas_meses_disponibles() as $m) { $partes=explode('-',$m); $etiqueta=mes_nombre($partes[1]).' '.$partes[0]; ?><option value="<?php echo texto_mostrar($m); ?>"<?php if($mes===$m) echo ' selected'; ?>><?php echo texto_mostrar($etiqueta); ?></option><?php } ?></select></div>
		<?php if(!acceso_es_empleado()) { ?><div class="form-group"><label>Responsable</label><select name="responsable" class="form-control" onchange="this.form.submit()"><option value="">Todos</option><?php foreach(tareas_valores_unicos('responsable') as $op) { ?><option value="<?php echo texto_mostrar($op); ?>"<?php if($responsable===$op) echo ' selected'; ?>><?php echo texto_mostrar($op); ?></option><?php } ?></select></div><?php } ?>
		<div class="form-group"><label><?php echo texto_mostrar(tareas_etiqueta_tipo()); ?></label><select name="tipo" class="form-control" onchange="this.form.submit()"><option value="">Todos</option><?php foreach(tareas_valores_unicos('tipo') as $op) { ?><option value="<?php echo texto_mostrar($op); ?>"<?php if($objetivo===$op) echo ' selected'; ?>><?php echo texto_mostrar($op); ?></option><?php } ?></select></div>
		<?php if($mostrar_area) { $etq_area=acceso_etiquetas_area(); ?><div class="form-group"><label>Área</label><select name="area" class="form-control" onchange="this.form.submit()"><option value="">Todas</option><?php foreach($areas as $op) { ?><option value="<?php echo texto_mostrar($op); ?>"<?php if($area===$op) echo ' selected'; ?>><?php echo texto_mostrar(acceso_area_pantalla()==='general' && isset($etq_area[$op]) ? $etq_area[$op] : $op); ?></option><?php } ?></select></div><?php } ?>
		<?php echo crm_html_orden_tarea(); ?>
		<a href="<?php echo tareas_link('tareas.php'); ?>" class="btn btn-white"><i class="fa fa-times"></i> Limpiar</a>
	</div>
	<?php
	return ob_get_clean();
}

function crm_tareas_calendario_eventos($tareas, $anio, $mes)
{
	$eventos=array();
	$prefijo=sprintf('%04d-%02d-',$anio,$mes);
	foreach((array)$tareas as $tarea)
	{
		if(function_exists('tarea_esta_eliminada') && tarea_esta_eliminada($tarea)) { continue; }
		foreach(array('inicio'=>'fecha_inicio','vencimiento'=>'fecha_cierre') as $tipo=>$campo)
		{
			$fecha=substr(isset($tarea[$campo]) ? (string)$tarea[$campo] : '',0,10);
			if(!crm_tarea_fecha_filtro_valida($fecha) || strpos($fecha,$prefijo)!==0) { continue; }
			$dia=intval(substr($fecha,8,2));
			if(!isset($eventos[$dia])) { $eventos[$dia]=array(); }
			$eventos[$dia][$tipo]=true;
		}
	}
	return $eventos;
}

function crm_tareas_link_filtro($cambios)
{
	$actual=array();
	foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente','orden','cal','dia','buscar','fecha_inicio','fecha_vencimiento','vence') as $clave)
	{
		$actual[$clave]=isset($_GET[$clave]) ? $_GET[$clave] : '';
	}
	return tareas_link('tareas.php',array_merge($actual,(array)$cambios));
}

function crm_html_tareas_calendario_controles($anio, $mes)
{
	$anio=intval($anio); $mes=intval($mes);
	$cal=sprintf('%04d-%02d',$anio,$mes);
	$meses=array(1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre');
	$anio_hoy=intval(date('Y'));
	$anios=array_values(array_unique(array_merge(range(max(1,$anio_hoy-5),$anio_hoy+5),range(max(1,$anio-5),$anio+5))));
	sort($anios);
	$prev=date('Y-m',strtotime($cal.'-01 -1 month'));
	$next=date('Y-m',strtotime($cal.'-01 +1 month'));
	$prev_url=crm_tareas_link_filtro(array('cal'=>$prev,'dia'=>''));
	$next_url=crm_tareas_link_filtro(array('cal'=>$next,'dia'=>''));
	$clear_url=crm_tareas_link_filtro(array('cal'=>date('Y-m'),'dia'=>''));
	ob_start();
	?>
	<div class="crm-calendar-controls crm-tareas-cal-controls">
		<a class="btn btn-white btn-xs crm-calendar-arrow" href="<?php echo htmlspecialchars($prev_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mes anterior"><i class="fa fa-chevron-left"></i></a>
		<form method="get" action="<?php echo htmlspecialchars(tareas_link('tareas.php'), ENT_QUOTES, 'UTF-8'); ?>" class="crm-calendar-selectors crm-tareas-cal-selectores">
			<input type="hidden" name="cal" value="<?php echo htmlspecialchars($cal, ENT_QUOTES, 'UTF-8'); ?>">
			<?php foreach(array('mes','area','prioridad','tipo','responsable','estado','cliente','orden','buscar','fecha_inicio','fecha_vencimiento','vence') as $clave) { if(isset($_GET[$clave]) && $_GET[$clave]!=='') { ?><input type="hidden" name="<?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($_GET[$clave], ENT_QUOTES, 'UTF-8'); ?>"><?php } } ?>
			<select name="cal_mes" aria-label="Mes del calendario" onchange="var f=this.form;f.elements['cal'].value=f.elements['cal_anio'].value+'-'+('0'+f.elements['cal_mes'].value).slice(-2);f.submit()"><?php foreach($meses as $numero=>$nombre) { ?><option value="<?php echo $numero; ?>" <?php if($numero===$mes) echo 'selected'; ?>><?php echo $nombre; ?></option><?php } ?></select>
			<select name="cal_anio" aria-label="Año del calendario" onchange="var f=this.form;f.elements['cal'].value=f.elements['cal_anio'].value+'-'+('0'+f.elements['cal_mes'].value).slice(-2);f.submit()"><?php foreach($anios as $y) { ?><option value="<?php echo $y; ?>" <?php if($y===$anio) echo 'selected'; ?>><?php echo $y; ?></option><?php } ?></select>
		</form>
		<a class="btn btn-white btn-xs crm-calendar-clear" href="<?php echo htmlspecialchars($clear_url, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-refresh"></i> Limpiar</a>
		<a class="btn btn-white btn-xs crm-calendar-arrow" href="<?php echo htmlspecialchars($next_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mes siguiente"><i class="fa fa-chevron-right"></i></a>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Lista de tareas de cualquier área: saca las eliminadas y aplica filtros de calendario y lista.
 */
function crm_tarea_fecha_filtro_valida($fecha)
{
	if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)$fecha)) { return false; }
	$partes=explode('-',$fecha);
	return checkdate(intval($partes[1]),intval($partes[2]),intval($partes[0]));
}

function crm_tareas_preparar_lista($lista)
{
	if(!is_array($lista))
	{
		return array();
	}
	$hoy=date('Y-m-d');
	$dia=isset($_GET['dia']) && crm_tarea_fecha_filtro_valida($_GET['dia']) ? $_GET['dia'] : '';
	$vence=isset($_GET['vence']) ? $_GET['vence'] : '';
	$buscar=isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
	$fecha_inicio=isset($_GET['fecha_inicio']) && crm_tarea_fecha_filtro_valida($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
	$fecha_vencimiento=isset($_GET['fecha_vencimiento']) && crm_tarea_fecha_filtro_valida($_GET['fecha_vencimiento']) ? $_GET['fecha_vencimiento'] : '';
	$estado_filtro=isset($_GET['estado']) ? trim($_GET['estado']) : '';
	$ver_eliminadas=($estado_filtro==='Eliminada' || $estado_filtro==='cancelada');
	$out=array();
	foreach($lista as $t)
	{
		$es_eliminada=tarea_esta_eliminada($t);
		if($es_eliminada && !$ver_eliminadas)
		{
			continue;
		}
		if(!$es_eliminada && $ver_eliminadas)
		{
			continue;
		}
		$ini=substr(isset($t['fecha_inicio']) ? (string)$t['fecha_inicio'] : '', 0, 10);
		$fin=substr(isset($t['fecha_cierre']) ? (string)$t['fecha_cierre'] : '', 0, 10);
		$terminada=(tarea_texto_estado($t)=='Terminada');
		if($buscar!=='')
		{
			$titulo=isset($t['titulo']) ? (string)$t['titulo'] : '';
			$descripcion=isset($t['descripcion']) ? (string)$t['descripcion'] : '';
			$en_titulo=function_exists('mb_stripos') ? mb_stripos($titulo,$buscar,0,'UTF-8')!==false : stripos($titulo,$buscar)!==false;
			$en_descripcion=function_exists('mb_stripos') ? mb_stripos($descripcion,$buscar,0,'UTF-8')!==false : stripos($descripcion,$buscar)!==false;
			if(!$en_titulo && !$en_descripcion) { continue; }
		}
		if($fecha_inicio!=='' && $ini!==$fecha_inicio) { continue; }
		if($fecha_vencimiento!=='' && $fin!==$fecha_vencimiento) { continue; }
		if($dia!='' && $ini!==$dia && $fin!==$dia)
		{
			continue;
		}
		if($vence=='hoy' && ($terminada || $fin!==$hoy))
		{
			continue;
		}
		$out[]=$t;
	}
	$orden = isset($_GET['orden']) ? trim($_GET['orden']) : '';
	usort($out, function($a, $b) use ($orden) {
		if($orden==='vence')
		{
			$ca=substr(isset($a['fecha_cierre']) ? (string)$a['fecha_cierre'] : '',0,10);
			$cb=substr(isset($b['fecha_cierre']) ? (string)$b['fecha_cierre'] : '',0,10);
			if($ca==='' && $cb!=='') { return 1; }
			if($cb==='' && $ca!=='') { return -1; }
			if($ca!=='' && $cb!=='' && $ca!==$cb) { return strcmp($ca,$cb); }
			return intval(isset($a['id']) ? $a['id'] : 0)-intval(isset($b['id']) ? $b['id'] : 0);
		}
		$ca = isset($a['creado_en']) && $a['creado_en'] != '' ? (string)$a['creado_en'] : (isset($a['fecha_inicio']) ? (string)$a['fecha_inicio'] : '');
		$cb = isset($b['creado_en']) && $b['creado_en'] != '' ? (string)$b['creado_en'] : (isset($b['fecha_inicio']) ? (string)$b['fecha_inicio'] : '');
		$ida = intval(isset($a['id']) ? $a['id'] : 0);
		$idb = intval(isset($b['id']) ? $b['id'] : 0);

		if($orden === 'antiguas')
		{
			if($ca !== '' && $cb !== '' && $ca !== $cb)
			{
				return strcmp($ca, $cb);
			}
			return $ida - $idb;
		}

		// 'recientes' (por defecto): más nuevas primero
		if($ca !== '' && $cb !== '' && $ca !== $cb)
		{
			return strcmp($cb, $ca);
		}
		return $idb - $ida;
	});
	return $out;
}

function crm_tareas_contar($lista)
{
	$cont=array('Pendiente'=>0,'No iniciada'=>0,'En proceso'=>0,'Pausada'=>0,'Terminada'=>0,'Vencida'=>0,'Hoy'=>0,'Eliminada'=>0,'total'=>0);
	$hoy=date('Y-m-d');
	foreach((array)$lista as $t)
	{
		if(tarea_esta_eliminada($t))
		{
			$cont['Eliminada']++;
			continue;
		}
		$cont['total']++;
		$est=tarea_texto_estado($t);
		if($est=='No iniciada' || $est=='Pendiente')
		{
			$cont['Pendiente']++;
			$cont['No iniciada']++;
		}
		else if(isset($cont[$est]))
		{
			$cont[$est]++;
		}
		$fin=substr(isset($t['fecha_cierre']) ? (string)$t['fecha_cierre'] : '', 0, 10);
		if($est!='Terminada' && $fin!='')
		{
			if($fin<$hoy) { $cont['Vencida']++; }
			if($fin==$hoy) { $cont['Hoy']++; }
		}
	}
	return $cont;
}

function crm_tareas_filtro_activo_html()
{
	$txt='';
	if(isset($_GET['dia']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia']))
	{
		$txt='Tareas del '.date('d/m/Y', strtotime($_GET['dia']));
	}
	else if(isset($_GET['vence']) && $_GET['vence']=='hoy')
	{
		$txt='Tareas que vencen hoy';
	}
	else if(isset($_GET['estado']) && $_GET['estado']=='Vencida')
	{
		$txt='Tareas vencidas';
	}
	else if(isset($_GET['estado']) && ($_GET['estado']=='Eliminada' || $_GET['estado']=='cancelada'))
	{
		$txt='Tareas eliminadas (solo consulta)';
	}
	if($txt=='')
	{
		return '';
	}
	return '<div class="alert alert-info crm-filtro-activo">Mostrando: <strong>'.htmlspecialchars($txt).'</strong> · <a href="'.htmlspecialchars(tareas_link('tareas.php')).'">Ver todas</a></div>';
}

function crm_tarea_puede_eliminar_directo()
{
	return acceso_es_coord_general() || acceso_es_super();
}

// Coordinador general / superadmin: elimina sin circuito de aprobación (queda registrado igual).
function crm_tarea_eliminar_directo($id_tarea, $motivo)
{
	crm_tareas_flujo_asegurar();
	$id=intval($id_tarea);
	$motivo=trim($motivo);
	$yo=crm_id_empleado_sesion();
	if(!crm_tarea_puede_eliminar_directo())
	{
		return 'No tenés permiso para eliminar tareas.';
	}
	$fila=crm_tarea_fila($id);
	if(!$fila || $fila['estado']=='cancelada')
	{
		return 'Esa tarea no se puede eliminar.';
	}
	if($motivo==='')
	{
		return 'Indicá el motivo.';
	}
	$pend=crm_tarea_solicitud_activa($id);
	if($pend)
	{
		acceso_ejecutar(
			"UPDATE tarea_solicitud_baja SET estado='aceptada', id_revisa_general=?, nota_general=?, fecha_resolucion=NOW() WHERE id=?",
			'isi',
			array($yo, $motivo, intval($pend['id']))
		);
	}
	else if($yo>0)
	{
		acceso_insertar(
			"INSERT INTO tarea_solicitud_baja (id_tarea, id_solicita, motivo, estado, id_revisa_general, nota_general, fecha_resolucion) VALUES (?,?,?,'aceptada',?,?,NOW())",
			'iisis',
			array($id, $yo, $motivo, $yo, 'Eliminación directa')
		);
	}
	acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea=? OR id_tarea_padre=?", 'ii', array($id, $id));
	$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.$id);
	$resp=acceso_consulta("SELECT DISTINCT id_empleado FROM tarea_responsable WHERE id_tarea=?", 'i', array($id));
	foreach($resp as $r)
	{
		if(intval($r['id_empleado'])!=$yo)
		{
			crm_aviso_enviar(intval($r['id_empleado']), 'Tarea eliminada', '“'.$fila['titulo'].'” fue eliminada. Motivo: '.$motivo, $url, $id, 'baja');
		}
	}
	return '';
}

// Pedidos de eliminación que esperan una decisión de quien está logueado.
function crm_tarea_bajas_pendientes_mias()
{
	if(!acceso_tabla_existe('tarea_solicitud_baja'))
	{
		return array();
	}
	if(crm_tarea_puede_eliminar_directo())
	{
		return acceso_consulta(
			"SELECT s.id, s.estado, s.motivo, s.fecha_solicitud, t.id_tarea, t.titulo, a.clave AS area_clave, a.nombre AS area_nombre
			FROM tarea_solicitud_baja s
			INNER JOIN tarea t ON t.id_tarea=s.id_tarea
			INNER JOIN area a ON a.id_area=t.id_area
			WHERE s.estado IN ('pendiente_coord','pendiente_general')
			ORDER BY s.estado='pendiente_general' DESC, s.fecha_solicitud ASC LIMIT 30"
		);
	}
	if(acceso_es_coord_de_area())
	{
		return acceso_consulta(
			"SELECT s.id, s.estado, s.motivo, s.fecha_solicitud, t.id_tarea, t.titulo, a.clave AS area_clave, a.nombre AS area_nombre
			FROM tarea_solicitud_baja s
			INNER JOIN tarea t ON t.id_tarea=s.id_tarea
			INNER JOIN area a ON a.id_area=t.id_area
			WHERE s.estado='pendiente_coord' AND a.clave=?
			ORDER BY s.fecha_solicitud ASC LIMIT 30",
			's',
			array(acceso_area())
		);
	}
	return array();
}

/**
 * Procesar POST del flujo. Crear parte = camino corto (sin ALTER ni vencidas).
 */
function crm_tarea_procesar_post_flujo()
{
	if(isset($_POST['solicitar_cambio_fecha']) && isset($_POST['id_tarea']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return true;
		}
		$msg=crm_tarea_solicitar_cambio_fecha(
			$_POST['id_tarea'],
			isset($_POST['fecha_inicio_propuesta']) ? $_POST['fecha_inicio_propuesta'] : '',
			isset($_POST['fecha_cierre_propuesta']) ? $_POST['fecha_cierre_propuesta'] : '',
			isset($_POST['motivo_cambio_fecha']) ? $_POST['motivo_cambio_fecha'] : ''
		);
		$_SESSION['msgtarea']=$msg==='' ? 'La solicitud de cambio de fecha quedó pendiente de aprobación.' : $msg;
		return true;
	}
	if(isset($_POST['decidir_cambio_fecha']) && isset($_POST['id_solicitud_cambio_fecha']) && isset($_POST['id_tarea']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return true;
		}
		$decision=isset($_POST['decision_cambio_fecha']) ? strval($_POST['decision_cambio_fecha']) : '';
		if(!in_array($decision,array('aprobar','rechazar'),true))
		{
			$_SESSION['msgtarea']='La decisión indicada no es válida.';
			return true;
		}
		$aprobar=($decision==='aprobar');
		$msg=crm_tarea_cambio_fecha_decidir(
			$_POST['id_solicitud_cambio_fecha'],
			$_POST['id_tarea'],
			$aprobar,
			isset($_POST['motivo_rechazo_cambio_fecha']) ? $_POST['motivo_rechazo_cambio_fecha'] : ''
		);
		$_SESSION['msgtarea']=$msg==='' ? ($aprobar ? 'La solicitud fue aprobada y se actualizaron las fechas.' : 'La solicitud fue rechazada; las fechas originales se conservaron.') : $msg;
		return true;
	}
	if(isset($_POST['cerrar_crm_aviso']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return true;
		}
		crm_aviso_extra_ok($_POST['cerrar_crm_aviso']);
		return true;
	}
	if(isset($_POST['crear_subtarea']) && isset($_POST['id_tarea']))
	{
		$msg=crm_tarea_crear_subtarea(
			$_POST['id_tarea'],
			isset($_POST['sub_titulo']) ? $_POST['sub_titulo'] : '',
			isset($_POST['sub_responsable']) ? $_POST['sub_responsable'] : 0,
			isset($_POST['sub_fecha']) ? $_POST['sub_fecha'] : ''
		);
		$_SESSION['msgtarea']=($msg==='') ? 'Listo, se agregó la subtarea.' : $msg;
		return true;
	}
	if(isset($_POST['pedir_baja_tarea']) && isset($_POST['id_tarea']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return true;
		}
		$msg=crm_tarea_solicitar_baja($_POST['id_tarea'], isset($_POST['motivo_baja']) ? $_POST['motivo_baja'] : '');
		$_SESSION['msgtarea']=($msg==='') ? 'Pedido enviado. Te avisamos cuando haya novedades.' : $msg;
		return true;
	}
	if(isset($_POST['eliminar_tarea_directo']) && isset($_POST['id_tarea']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return true;
		}
		$msg=crm_tarea_eliminar_directo($_POST['id_tarea'], isset($_POST['motivo_baja']) ? $_POST['motivo_baja'] : '');
		$_SESSION['msgtarea']=($msg==='') ? 'Tarea eliminada.' : $msg;
		return true;
	}
	if(isset($_POST['reabrir_tarea']) && isset($_POST['id_tarea']))
	{
		$msg=crm_tarea_reabrir($_POST['id_tarea']);
		$_SESSION['msgtarea']=($msg==='') ? 'Tarea reabierta correctamente.' : $msg;
		return true;
	}
	if(isset($_POST['baja_decidir']) && isset($_POST['id_solicitud_baja']))
	{
		if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : ''))
		{
			$_SESSION['msgtarea']='La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.';
			return true;
		}
		$ok=(isset($_POST['baja_aceptar']) && strval($_POST['baja_aceptar'])==='1');
		$msg=crm_tarea_baja_decidir($_POST['id_solicitud_baja'], $ok, isset($_POST['nota_baja']) ? $_POST['nota_baja'] : '');
		$_SESSION['msgtarea']=($msg==='') ? ($ok ? 'Pedido aceptado.' : 'Pedido rechazado.') : $msg;
		return true;
	}
	// Vencidas solo en requests idle (no en cada acción)
	if($_SERVER['REQUEST_METHOD']!=='POST')
	{
		crm_tarea_revisar_vencidas();
	}
	return false;
}

/**
 * Reabrir tarea finalizada.
 * Cambia estado a 'en_proceso', borra fecha_cierre_real y registra actividad.
 */
function crm_tarea_reabrir($id_tarea)
{
	$id=intval($id_tarea);
	if($id<=0)
	{
		return 'Identificador de tarea inválido.';
	}
	$tarea=function_exists('repo_tarea_por_id') ? repo_tarea_por_id($id) : (function_exists('crm_tarea_fila') ? crm_tarea_fila($id) : null);
	if(!$tarea)
	{
		return 'No se encontró la tarea especificada.';
	}
	if(!function_exists('tarea_puede_reabrir') || !tarea_puede_reabrir($tarea))
	{
		return 'No tenés permisos para reabrir esta tarea.';
	}
	$id_empleado=function_exists('repo_empleado_logueado') ? repo_empleado_logueado() : (function_exists('tareas_empleado_actual_id') ? tareas_empleado_actual_id() : 0);
	if(function_exists('repo_tarea_cambiar_estado'))
	{
		$ok=repo_tarea_cambiar_estado($id, 'en_proceso', $id_empleado);
		return $ok ? '' : 'No se pudo reabrir la tarea.';
	}
	return 'Función de cambio de estado no disponible.';
}
