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

function crm_aviso_etiqueta_tipo($tipo)
{
	$map=array(
		'subtarea'=>'Subtarea',
		'cambio'=>'Cambio',
		'vencimiento'=>'Vencida',
		'baja'=>'Eliminar',
		'actividad'=>'Actividad',
		'tarea'=>'Tarea',
		'info'=>'Aviso'
	);
	$t=trim($tipo);
	return isset($map[$t]) ? $map[$t] : 'Aviso';
}

function crm_avisos_extra_mios()
{
	$yo=crm_id_empleado_sesion();
	if($yo<=0 || !acceso_tabla_existe('crm_aviso'))
	{
		return array();
	}
	$rows=acceso_consulta(
		"SELECT id, id_tarea, tipo, titulo, detalle, url, fecha FROM crm_aviso
		WHERE id_empleado_para=? AND confirmado=0
		ORDER BY fecha DESC LIMIT 40",
		'i',
		array($yo)
	);
	$salida=array();
	foreach($rows as $r)
	{
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
		$tipo_av=isset($r['tipo']) ? trim($r['tipo']) : 'info';
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
		$salida[]=array(
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
	if(!$fila || $fila['estado']=='cancelada' || $fila['estado']=='terminada')
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
	if(!$padre || $padre['estado']=='cancelada' || $padre['estado']=='terminada')
	{
		return 'Esa tarea no admite subtareas nuevas porque se encuentra finalizada o eliminada.';
	}
	if(function_exists('tarea_puede_ver') && !tarea_puede_ver($padre))
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
		foreach($nuevos as $nom)
		{
			if($yo_nom!='' && $nom===$yo_nom)
			{
				continue;
			}
			$id_para=crm_tarea_id_empleado_por_nombre($nom);
			if($id_para<=0)
			{
				continue;
			}
			if(function_exists('repo_avisos_crear') && $de>0)
			{
				repo_avisos_crear($id, $de, $id_para);
			}
			crm_aviso_enviar(
				$id_para,
				'Te asignaron una tarea',
				'Te sumaron como responsable de “'.$titulo.'”.',
				$url,
				$id,
				'cambio'
			);
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

function crm_tarea_solicitar_baja($id_tarea, $motivo)
{
	crm_tareas_flujo_asegurar();
	$id=intval($id_tarea);
	$motivo=trim($motivo);
	$yo=crm_id_empleado_sesion();
	$fila=crm_tarea_fila($id);
	if(!$fila || $fila['estado']=='cancelada')
	{
		return 'Esa tarea no se puede eliminar.';
	}
	if($motivo==='')
	{
		return 'Indicá el motivo.';
	}
	if($yo<=0)
	{
		return 'Sesión inválida.';
	}
	if(crm_tarea_solicitud_activa($id))
	{
		return 'Ya hay un pedido de eliminación en curso.';
	}
	$est=acceso_es_empleado() ? 'pendiente_coord' : 'pendiente_general';
	acceso_insertar(
		"INSERT INTO tarea_solicitud_baja (id_tarea, id_solicita, motivo, estado) VALUES (?,?,?,?)",
		'iiss',
		array($id, $yo, $motivo, $est)
	);
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
	crm_tareas_flujo_asegurar();
	$id_s=intval($id_solicitud);
	$yo=crm_id_empleado_sesion();
	$nota=trim($nota);
	$sol=acceso_consulta_una("SELECT * FROM tarea_solicitud_baja WHERE id=? LIMIT 1", 'i', array($id_s));
	if(!$sol)
	{
		return 'Pedido no encontrado.';
	}
	$fila=crm_tarea_fila(intval($sol['id_tarea']));
	if(!$fila)
	{
		return 'La tarea ya no existe.';
	}
	$url=url_crm($fila['area_clave'].'/ver-tarea.php?id='.intval($fila['id_tarea']));

	$es_final=(acceso_es_coord_general() || acceso_es_super());
	if($sol['estado']=='pendiente_coord' && $es_final && $aceptar)
	{
		// El coordinador general no necesita pasar por el paso del área.
		$sol['estado']='pendiente_general';
	}
	if($sol['estado']=='pendiente_coord')
	{
		if(!acceso_es_coord_de_area() && !$es_final)
		{
			return 'Solo el coordinador de área puede resolver esto.';
		}
		if(!$aceptar)
		{
			acceso_ejecutar(
				"UPDATE tarea_solicitud_baja SET estado='rechazada', id_revisa_coord=?, nota_coord=?, fecha_resolucion=NOW() WHERE id=?",
				'isi',
				array($yo, $nota!='' ? $nota : 'Rechazado', $id_s)
			);
			crm_aviso_enviar(intval($sol['id_solicita']), 'No se eliminó la tarea', '“'.$fila['titulo'].'” sigue activa. '.($nota!='' ? $nota : ''), $url, intval($fila['id_tarea']), 'baja');
			return '';
		}
		acceso_ejecutar(
			"UPDATE tarea_solicitud_baja SET estado='pendiente_general', id_revisa_coord=?, nota_coord=? WHERE id=?",
			'isi',
			array($yo, $nota!='' ? $nota : 'OK del área', $id_s)
		);
		$gen=crm_id_coord_general_empleado();
		$det='El área aceptó eliminar “'.$fila['titulo'].'”. Falta tu OK.';
		if($gen>0)
		{
			crm_aviso_enviar($gen, 'Falta tu OK para eliminar', $det, $url, intval($fila['id_tarea']), 'baja');
		}
		return '';
	}

	if($sol['estado']=='pendiente_general')
	{
		if(!$es_final)
		{
			return 'Solo el coordinador general puede dar el OK final.';
		}
		if(!$aceptar)
		{
			acceso_ejecutar(
				"UPDATE tarea_solicitud_baja SET estado='rechazada', id_revisa_general=?, nota_general=?, fecha_resolucion=NOW() WHERE id=?",
				'isi',
				array($yo, $nota!='' ? $nota : 'Rechazado', $id_s)
			);
			crm_aviso_enviar(intval($sol['id_solicita']), 'No se eliminó la tarea', '“'.$fila['titulo'].'” sigue activa.', $url, intval($fila['id_tarea']), 'baja');
			return '';
		}
		acceso_ejecutar(
			"UPDATE tarea_solicitud_baja SET estado='aceptada', id_revisa_general=?, nota_general=?, fecha_resolucion=NOW() WHERE id=?",
			'isi',
			array($yo, $nota!='' ? $nota : 'Eliminada', $id_s)
		);
		acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea=?", 'i', array(intval($fila['id_tarea'])));
		acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea_padre=?", 'i', array(intval($fila['id_tarea'])));
		$msg='“'.$fila['titulo'].'” quedó eliminada.';
		crm_aviso_enviar(intval($sol['id_solicita']), 'Listo: tarea eliminada', $msg, $url, intval($fila['id_tarea']), 'baja');
		return '';
	}
	return 'Ese pedido ya fue resuelto.';
}

function crm_html_orden_tarea()
{
	$orden = isset($_GET['orden']) ? trim($_GET['orden']) : '';
	ob_start();
	?>
	<div class="form-group">
		<label>Ordenar</label>
		<select name="orden" class="form-control" onchange="this.form.submit()">
			<option value="recientes" <?php if($orden==='recientes' || $orden==='') echo 'selected'; ?>>Más recientes</option>
			<option value="antiguas" <?php if($orden==='antiguas') echo 'selected'; ?>>Más antiguas</option>
		</select>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Lista de tareas de cualquier área: saca las eliminadas, aplica los filtros
 * del calendario (dia=AAAA-MM-DD, vence=hoy) y deja primero las abiertas.
 */
function crm_tareas_preparar_lista($lista)
{
	if(!is_array($lista))
	{
		return array();
	}
	$hoy=date('Y-m-d');
	$dia=isset($_GET['dia']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dia']) ? $_GET['dia'] : '';
	$vence=isset($_GET['vence']) ? $_GET['vence'] : '';
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
	if(isset($_POST['cerrar_crm_aviso']))
	{
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
		$msg=crm_tarea_solicitar_baja($_POST['id_tarea'], isset($_POST['motivo_baja']) ? $_POST['motivo_baja'] : '');
		$_SESSION['msgtarea']=($msg==='') ? 'Pedido enviado. Te avisamos cuando haya novedades.' : $msg;
		return true;
	}
	if(isset($_POST['eliminar_tarea_directo']) && isset($_POST['id_tarea']))
	{
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
