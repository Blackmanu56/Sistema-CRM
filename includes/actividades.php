<?php
// Actividades = eventos (capacitacion, reunion, etc.). No son tareas.
// Pueden nacer de una tarea o generar tareas.

function actividades_de_area($area='')
{
	if($area=='')
	{
		$area=acceso_area_pantalla();
	}
	$lista=array();
	foreach(crm_eventos_de_area($area) as $ev)
	{
		if(actividad_puede_ver($ev))
		{
			$lista[]=array(
				'id'=>intval($ev['id_evento']),
				'titulo'=>$ev['titulo'],
				'descripcion'=>isset($ev['descripcion']) ? $ev['descripcion'] : '',
				'fecha'=>isset($ev['fecha']) ? $ev['fecha'] : '',
				'responsable'=>isset($ev['responsable']) ? trim($ev['responsable']) : '',
				'cliente'=>isset($ev['cliente_nombre']) ? $ev['cliente_nombre'] : '',
				'organismo'=>isset($ev['organismo']) && trim((string)$ev['organismo'])!=='' ? trim((string)$ev['organismo']) : (isset($ev['cliente_nombre']) ? trim((string)$ev['cliente_nombre']) : ''),
				'tipo'=>isset($ev['tipo']) ? $ev['tipo'] : '',
				'autoinscripcion'=>isset($ev['autoinscripcion']) ? intval($ev['autoinscripcion']) : 0,
				'publico'=>isset($ev['publico']) ? $ev['publico'] : 'todos',
				'id_empleado'=>isset($ev['id_empleado']) ? intval($ev['id_empleado']) : 0,
				'area_clave'=>isset($ev['area_clave']) ? $ev['area_clave'] : $area,
				'n_inscriptos'=>isset($ev['n_inscriptos']) ? intval($ev['n_inscriptos']) : 0
			);
		}
	}
	return $lista;
}

function actividades_area_destino()
{
	$area=acceso_area_pantalla();
	// Las actividades son eventos del área, aunque el área no tenga personal
	// asignable para tareas. Usar el catálogo completo evita que Presidencia
	// termine guardando sus actividades en Coordinación general.
	$ok=array_keys(acceso_etiquetas_area());
	if($area=='general' && acceso_es_general() && isset($_POST['area_actividad']) && in_array($_POST['area_actividad'], $ok))
	{
		return $_POST['area_actividad'];
	}
	if(!in_array($area, $ok))
	{
		$area='general';
	}
	return $area;
}

function actividad_puede_crear($area_clave='')
{
	if(acceso_es_super() || acceso_es_coord_general() || acceso_es_general())
	{
		return true;
	}
	if($area_clave==='')
	{
		$area_clave=function_exists('acceso_area_pantalla') ? acceso_area_pantalla() : '';
	}
	$area_usuario=function_exists('acceso_area') ? acceso_area() : '';
	$rol_usuario=isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '';

	if($rol_usuario==='coordinador' && ($area_clave==='' || $area_clave===$area_usuario))
	{
		return true;
	}

	$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
	foreach($extras as $ext)
	{
		if(isset($ext['rol']) && $ext['rol']==='coordinador')
		{
			if($area_clave==='' || (isset($ext['area']) && $ext['area']===$area_clave))
			{
				return true;
			}
		}
	}

	return false;
}

function actividades_guardar_nueva_desde_form()
{
	if(function_exists('crm_asegurar_evento_portal'))
	{
		crm_asegurar_evento_portal();
	}
	$area=actividades_area_destino();
	if(!actividad_puede_crear($area))
	{
		return array('area'=>$area, 'id'=>0, 'msg'=>'No podés crear actividades.');
	}
	$id_emp=isset($_POST['responsable_id']) ? intval($_POST['responsable_id']) : 0;
	if($id_emp>0 && !acceso_empleado_por_id($id_emp))
	{
		$id_emp=0;
	}
	if($id_emp<=0)
	{
		$id_emp=crm_id_empleado_sesion();
	}
	$auto=(isset($_POST['autoinscripcion']) && $_POST['autoinscripcion']=='1') ? 1 : 0;
	$org=actividades_organismo_desde_post();
	if($org['error']!=='')
	{
		return array('area'=>$area, 'id'=>0, 'msg'=>$org['error']);
	}
	$id_org=intval($org['id']);
	$organismo=$org['nombre'];
	$id_localidad=isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0;
	$localidad_valida=acceso_consulta_una(
		"SELECT l.id_localidad
		FROM localidad l INNER JOIN provincia p ON p.id_provincia=l.id_provincia
		WHERE l.id_localidad=? AND p.nombre='Misiones' LIMIT 1",
		'i',
		array($id_localidad)
	);
	if(!$localidad_valida)
	{
		return array('area'=>$area, 'id'=>0, 'msg'=>'Seleccioná una localidad válida de Misiones para la actividad.');
	}
	$f_nueva = isset($_POST['fecha']) ? trim($_POST['fecha']) : date('Y-m-d');
	if($f_nueva !== '' && $f_nueva < date('Y-m-d'))
	{
		return array('area'=>$area, 'id'=>0, 'msg'=>'La fecha de la actividad no puede ser anterior a la fecha actual.');
	}
	$id_evento=crm_evento_guardar(array(
		'titulo'=>isset($_POST['titulo']) ? $_POST['titulo'] : '',
		'descripcion'=>isset($_POST['descripcion']) ? $_POST['descripcion'] : '',
		'tipo'=>isset($_POST['tipo_actividad']) ? $_POST['tipo_actividad'] : 'reunion',
		'fecha'=>$f_nueva,
		'area'=>$area,
		'id_cliente'=>$id_org,
		'id_localidad'=>$id_localidad,
		'id_empleado'=>$id_emp,
		'id_tarea_origen'=>isset($_POST['id_tarea_origen']) ? intval($_POST['id_tarea_origen']) : 0,
		'asistencia'=>'',
		'autoinscripcion'=>$auto,
		'publico'=>isset($_POST['publico']) ? $_POST['publico'] : 'todos'
	));
	if($id_evento<=0)
	{
		$err=function_exists('acceso_ultimo_error') ? acceso_ultimo_error() : (isset($con) ? mysqli_error($con) : '');
		$msg_error='No se pudo guardar la actividad'.($err!=='' ? ': '.$err : '.');
		return array('area'=>$area, 'id'=>0, 'msg'=>$msg_error);
	}
	acceso_ejecutar(
		"UPDATE evento SET organismo=? WHERE id_evento=?",
		'si',
		array($organismo!=='' ? $organismo : null, $id_evento)
	);
	if($id_emp>0 && $id_emp!=crm_id_empleado_sesion() && function_exists('crm_aviso_enviar'))
	{
		$titulo_ev=isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
		$fecha_ev=isset($_POST['fecha']) && $_POST['fecha']!='' ? date('d/m/Y', strtotime($_POST['fecha'])) : '';
		$detalle=trim($titulo_ev.($fecha_ev!='' ? ' · '.$fecha_ev : '').($organismo!='' ? ' · '.$organismo : ''));
		crm_aviso_enviar($id_emp, 'Te asignaron una actividad', $detalle, url_crm($area.'/actividades.php?id='.$id_evento), 0, 'actividad');
	}
	$gen=actividades_generar_tareas_desde_post($id_evento, 'nueva');
	$msg='Actividad guardada.';
	$extra=actividades_msg_tareas_generadas($gen);
	if($extra!='')
	{
		$msg.=' '.$extra;
	}
	return array('area'=>$area, 'id'=>$id_evento, 'msg'=>$msg);
}

// Editar / gestionar asistencia: solo Coordinador de área (su área / rol adicional), Coordinación General o Superadmin.
// Empleados comunes no pueden gestionar (ni aunque figuren como responsable).
function actividad_puede_gestionar($ev)
{
	if(!$ev)
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general() || acceso_es_general())
	{
		return true;
	}
	$area_ev=isset($ev['area_clave']) ? $ev['area_clave'] : (isset($ev['area']) ? $ev['area'] : '');
	if($area_ev==='')
	{
		return false;
	}
	$area_usuario=function_exists('acceso_area') ? acceso_area() : '';
	$rol_usuario=isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '';

	if($rol_usuario==='coordinador' && $area_ev===$area_usuario)
	{
		return true;
	}

	$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
	foreach($extras as $ext)
	{
		if(isset($ext['area']) && $ext['area']===$area_ev && isset($ext['rol']) && $ext['rol']==='coordinador')
		{
			return true;
		}
	}
	return false;
}

// Eliminar: solo Coordinador de área (su área / rol adicional), Coordinación General o Superadmin.
function actividad_puede_eliminar($ev)
{
	return actividad_puede_gestionar($ev);
}

// Consultar / visualizar en solo lectura:
// Coordinador de área, Coordinador General, Superadmin, responsable de la actividad,
// o responsable / coordinador de tareas generadas a partir de la actividad (consulta transversal).
function actividad_puede_ver($ev)
{
	if(!$ev)
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general() || acceso_es_general())
	{
		return true;
	}
	$id_ev=intval(isset($ev['id_evento']) ? $ev['id_evento'] : (isset($ev['id']) ? $ev['id'] : 0));
	$area_ev=isset($ev['area_clave']) ? $ev['area_clave'] : (isset($ev['area']) ? $ev['area'] : '');
	$id_emp_sesion=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;

	if($id_emp_sesion>0 && intval(isset($ev['id_empleado']) ? $ev['id_empleado'] : 0)===$id_emp_sesion)
	{
		return true;
	}

	$area_usuario=function_exists('acceso_area') ? acceso_area() : '';
	if($area_ev!=='')
	{
		if($area_usuario!=='' && $area_ev===$area_usuario && isset($_SESSION['rol_area']) && $_SESSION['rol_area']==='coordinador')
		{
			return true;
		}
		$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
		foreach($extras as $ext)
		{
			if(isset($ext['area']) && $ext['area']===$area_ev && isset($ext['rol']) && $ext['rol']==='coordinador')
			{
				return true;
			}
		}
	}

	// Consulta transversal: vinculación mediante tareas generadas
	if($id_ev>0 && function_exists('acceso_consulta_una'))
	{
		if($id_emp_sesion>0)
		{
			$es_resp=acceso_consulta_una(
				"SELECT et.id_evento FROM evento_tarea et
				 INNER JOIN tarea t ON t.id_tarea=et.id_tarea
				 INNER JOIN tarea_responsable tr ON tr.id_tarea=t.id_tarea
				 WHERE et.id_evento=? AND tr.id_empleado=? AND t.estado<>'cancelada' LIMIT 1",
				'ii',
				array($id_ev, $id_emp_sesion)
			);
			if($es_resp)
			{
				return true;
			}
		}

		$areas_coord=array();
		if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']==='coordinador')
		{
			if($area_usuario!='') { $areas_coord[]=$area_usuario; }
		}
		$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
		foreach($extras as $ext)
		{
			if(isset($ext['rol']) && $ext['rol']==='coordinador' && isset($ext['area']))
			{
				$areas_coord[]=$ext['area'];
			}
		}
		$areas_coord=array_unique(array_filter($areas_coord));
		if(count($areas_coord)>0)
		{
			$in_clause="'".implode("','", array_map('acceso_sql', $areas_coord))."'";
			$es_area_part=acceso_consulta_una(
				"SELECT et.id_evento FROM evento_tarea et
				 INNER JOIN tarea t ON t.id_tarea=et.id_tarea
				 INNER JOIN area a ON a.id_area=t.id_area
				 WHERE et.id_evento=? AND a.clave IN (".$in_clause.") AND t.estado<>'cancelada' LIMIT 1",
				'i',
				array($id_ev)
			);
			if($es_area_part)
			{
				return true;
			}
		}
	}

	return false;
}

// Permiso de seguimiento completo de tareas interáreas:
// Superadmin, Coordinador General, Coordinador del área organizadora o Coordinador de alguna área participante.
function actividad_puede_ver_seguimiento($ev)
{
	if(!$ev)
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general() || acceso_es_general())
	{
		return true;
	}
	$id_ev=intval(isset($ev['id_evento']) ? $ev['id_evento'] : (isset($ev['id']) ? $ev['id'] : 0));
	$area_ev=isset($ev['area_clave']) ? $ev['area_clave'] : (isset($ev['area']) ? $ev['area'] : '');
	$area_usuario=function_exists('acceso_area') ? acceso_area() : '';

	if($area_ev!=='')
	{
		if($area_usuario!=='' && $area_ev===$area_usuario && isset($_SESSION['rol_area']) && $_SESSION['rol_area']==='coordinador')
		{
			return true;
		}
		$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
		foreach($extras as $ext)
		{
			if(isset($ext['area']) && $ext['area']===$area_ev && isset($ext['rol']) && $ext['rol']==='coordinador')
			{
				return true;
			}
		}
	}

	if($id_ev>0 && function_exists('acceso_consulta_una'))
	{
		$areas_coord=array();
		if(isset($_SESSION['rol_area']) && $_SESSION['rol_area']==='coordinador')
		{
			if($area_usuario!='') { $areas_coord[]=$area_usuario; }
		}
		$extras=function_exists('acceso_roles_adicionales_sesion') ? acceso_roles_adicionales_sesion() : array();
		foreach($extras as $ext)
		{
			if(isset($ext['rol']) && $ext['rol']==='coordinador' && isset($ext['area']))
			{
				$areas_coord[]=$ext['area'];
			}
		}
		$areas_coord=array_unique(array_filter($areas_coord));
		if(count($areas_coord)>0)
		{
			$in_clause="'".implode("','", array_map('acceso_sql', $areas_coord))."'";
			$es_area_part=acceso_consulta_una(
				"SELECT et.id_evento FROM evento_tarea et
				 INNER JOIN tarea t ON t.id_tarea=et.id_tarea
				 INNER JOIN area a ON a.id_area=t.id_area
				 WHERE et.id_evento=? AND a.clave IN (".$in_clause.") AND t.estado<>'cancelada' LIMIT 1",
				'i',
				array($id_ev)
			);
			if($es_area_part)
			{
				return true;
			}
		}
	}

	return false;
}

/** Organismo = cliente del padrón (tipos institucionales). Sin texto libre. */
function actividades_organismo_desde_post()
{
	$limpiar=(isset($_POST['organismo_limpiar']) && strval($_POST['organismo_limpiar'])==='1');
	$id_org=isset($_POST['organismo_id']) ? intval($_POST['organismo_id']) : 0;
	if($limpiar || $id_org<=0)
	{
		return array('id'=>0, 'nombre'=>'', 'error'=>'');
	}
	$cli=acceso_consulta_una(
		"SELECT id_cliente, nombre, tipo_cliente FROM cliente
		 WHERE id_cliente=? AND activo=1
		   AND tipo_cliente IN ('organismo','municipalidad','empresa')
		 LIMIT 1",
		'i',
		array($id_org)
	);
	if(!$cli)
	{
		return array('id'=>0, 'nombre'=>'', 'error'=>'El organismo debe ser un cliente del padrón (tipo organismo, municipalidad o empresa).');
	}
	return array('id'=>intval($cli['id_cliente']), 'nombre'=>trim($cli['nombre']), 'error'=>'');
}

function actividades_actualizar_desde_form($id_evento)
{
	if(function_exists('crm_asegurar_evento_portal'))
	{
		crm_asegurar_evento_portal();
	}
	$ev=crm_evento_por_id($id_evento);
	if(!actividad_puede_gestionar($ev))
	{
		return 'No podés editar esta actividad.';
	}
	$titulo=isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
	if($titulo==='')
	{
		return 'El título es obligatorio.';
	}
	$id_emp=isset($_POST['responsable_id']) ? intval($_POST['responsable_id']) : intval($ev['id_empleado']);
	if($id_emp>0 && !acceso_empleado_por_id($id_emp))
	{
		$id_emp=intval($ev['id_empleado']);
	}
	$tipo=isset($_POST['tipo_actividad']) ? trim($_POST['tipo_actividad']) : $ev['tipo'];
	$fecha=isset($_POST['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha']) ? $_POST['fecha'] : $ev['fecha'];
	$auto=(isset($_POST['autoinscripcion']) && $_POST['autoinscripcion']=='1') ? 1 : 0;
	$publico=isset($_POST['publico']) ? trim($_POST['publico']) : 'todos';
	if($publico==='persona') { $publico='emprendedor'; }
	if(!in_array($publico, array('todos','emprendedor','empresa','municipalidad','organismo'), true))
	{
		$publico='todos';
	}
	$org=actividades_organismo_desde_post();
	if($org['error']!=='')
	{
		return $org['error'];
	}
	$envio_org=(isset($_POST['organismo_id']) || isset($_POST['organismo_limpiar']));
	$id_localidad=isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0;
	$localidad_valida=acceso_consulta_una(
		"SELECT l.id_localidad
		FROM localidad l INNER JOIN provincia p ON p.id_provincia=l.id_provincia
		WHERE l.id_localidad=? AND p.nombre='Misiones' LIMIT 1",
		'i',
		array($id_localidad)
	);
	if(!$localidad_valida)
	{
		return 'Seleccioná una localidad válida de Misiones para la actividad.';
	}
	if(!$envio_org)
	{
		$id_org=intval(isset($ev['id_cliente']) ? $ev['id_cliente'] : 0);
		$organismo=isset($ev['organismo']) ? trim((string)$ev['organismo']) : '';
	}
	else
	{
		$id_org=intval($org['id']);
		$organismo=$org['nombre'];
	}
	$ok=acceso_ejecutar(
		"UPDATE evento SET titulo=?, descripcion=?, tipo=?, fecha=?,
			id_empleado=NULLIF(?,0), id_cliente=NULLIF(?,0), id_localidad=?, organismo=?,
			autoinscripcion=?, publico=?
		WHERE id_evento=?",
		'ssssiiisisi',
		array(
			mb_substr($titulo, 0, 200),
			isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
			$tipo,
			$fecha,
			$id_emp>0 ? $id_emp : 0,
			$id_org>0 ? $id_org : 0,
			$id_localidad,
			$organismo!=='' ? $organismo : null,
			$auto,
			$publico,
			intval($ev['id_evento'])
		)
	);
	if(!$ok)
	{
		$err=function_exists('acceso_ultimo_error') ? acceso_ultimo_error() : (isset($con) ? mysqli_error($con) : '');
		return 'No se pudieron guardar los cambios'.($err!=='' ? ': '.$err : '.');
	}
	$url=url_crm($ev['area_clave'].'/actividades.php?id='.intval($ev['id_evento']));
	if($id_emp>0 && $id_emp!=intval($ev['id_empleado']) && $id_emp!=crm_id_empleado_sesion())
	{
		crm_aviso_enviar($id_emp, 'Te asignaron una actividad', $titulo.' · '.date('d/m/Y', strtotime($fecha)), $url, 0, 'actividad');
	}
	else if($id_emp>0 && $id_emp!=crm_id_empleado_sesion() && ($fecha!=$ev['fecha'] || $titulo!=$ev['titulo']))
	{
		crm_aviso_enviar($id_emp, 'Cambió una actividad a tu cargo', $titulo.' · '.date('d/m/Y', strtotime($fecha)), $url, 0, 'actividad');
	}
	return '';
}

function actividades_cancelar_tareas_evento($id_evento, $titulo_evento='')
{
	$id_ev=intval($id_evento);
	if($id_ev<=0 || !function_exists('acceso_tabla_existe') || !acceso_tabla_existe('evento_tarea') || !acceso_tabla_existe('tarea'))
	{
		return;
	}
	$yo=intval(function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0);
	$titulo_ev=trim($titulo_evento);
	if($titulo_ev==='')
	{
		$ev=function_exists('crm_evento_por_id') ? crm_evento_por_id($id_ev) : null;
		$titulo_ev=$ev && isset($ev['titulo']) ? $ev['titulo'] : 'Actividad #'.$id_ev;
	}
	$motivo_baja='Eliminada automáticamente al eliminar la actividad: '.$titulo_ev;

	$filas_tar=acceso_consulta("SELECT id_tarea FROM evento_tarea WHERE id_evento=?", 'i', array($id_ev));
	foreach($filas_tar as $ft)
	{
		$id_tar=intval($ft['id_tarea']);
		if($id_tar<=0)
		{
			continue;
		}

		// Cancelar tarea principal y todas sus subtareas (id_tarea_padre)
		acceso_ejecutar("UPDATE tarea SET estado='cancelada', avance=0 WHERE id_tarea=? OR id_tarea_padre=?", 'ii', array($id_tar, $id_tar));

		// Auto-resolver avisos pendientes de la tarea principal
		acceso_ejecutar("UPDATE crm_aviso SET confirmado=1 WHERE id_tarea=? AND confirmado=0 AND tipo<>'baja'", 'i', array($id_tar));

		// Auto-resolver avisos de las subtareas
		$hijas=acceso_consulta("SELECT id_tarea FROM tarea WHERE id_tarea_padre=?", 'i', array($id_tar));
		foreach($hijas as $h)
		{
			$id_h=intval($h['id_tarea']);
			if($id_h>0)
			{
				acceso_ejecutar("UPDATE crm_aviso SET confirmado=1 WHERE id_tarea=? AND confirmado=0 AND tipo<>'baja'", 'i', array($id_h));
			}
		}

		// Registrar baja en tarea_solicitud_baja si la tabla existe
		if(acceso_tabla_existe('tarea_solicitud_baja'))
		{
			$pend=acceso_consulta_una("SELECT id FROM tarea_solicitud_baja WHERE id_tarea=? AND estado IN ('pendiente_coord','pendiente_general') LIMIT 1", 'i', array($id_tar));
			if($pend)
			{
				acceso_ejecutar(
					"UPDATE tarea_solicitud_baja SET estado='aceptada', id_revisa_general=?, nota_general=?, fecha_resolucion=NOW() WHERE id=?",
					'isi',
					array($yo, $motivo_baja, intval($pend['id']))
				);
			}
			else if($yo>0)
			{
				acceso_insertar(
					"INSERT INTO tarea_solicitud_baja (id_tarea, id_solicita, motivo, estado, id_revisa_general, nota_general, fecha_resolucion) VALUES (?,?,?,'aceptada',?,?,NOW())",
					'iisis',
					array($id_tar, $yo, $motivo_baja, $yo, 'Eliminación por actividad')
				);
			}
		}

		// Notificar a los responsables sobre la baja de la tarea
		if(function_exists('crm_aviso_enviar') && function_exists('crm_tarea_fila'))
		{
			$fila_tar=crm_tarea_fila($id_tar);
			if($fila_tar)
			{
				$area_clave=isset($fila_tar['area_clave']) ? $fila_tar['area_clave'] : acceso_area_pantalla();
				$url_tar=url_crm($area_clave.'/ver-tarea.php?id='.$id_tar);
				$resps=acceso_consulta("SELECT DISTINCT id_empleado FROM tarea_responsable WHERE id_tarea=?", 'i', array($id_tar));
				foreach($resps as $r)
				{
					$id_dest=intval($r['id_empleado']);
					if($id_dest>0 && $id_dest!=$yo)
					{
						crm_aviso_enviar($id_dest, 'Tarea eliminada', '“'.$fila_tar['titulo'].'” fue eliminada porque se eliminó la actividad.', $url_tar, $id_tar, 'baja');
					}
				}
			}
		}
	}
}

function actividades_eliminar($id_evento)
{
	$ev=crm_evento_por_id($id_evento);
	if(!actividad_puede_eliminar($ev))
	{
		return 'Solo un coordinador (del área o general) o Sistemas/superadmin puede eliminar actividades.';
	}
	$confirm=isset($_POST['confirm_eliminar']) ? trim((string)$_POST['confirm_eliminar']) : '';
	if(mb_strtoupper($confirm, 'UTF-8')!=='ELIMINAR')
	{
		return 'Para eliminar, escribí la palabra ELIMINAR en el casillero de confirmación.';
	}
	if(!isset($_POST['acepto_baja_tareas']) || strval($_POST['acepto_baja_tareas'])!=='1')
	{
		return 'Debés aceptar que se cancelarán las tareas vinculadas a la actividad.';
	}
	$id_ev=intval($ev['id_evento']);
	acceso_ejecutar("UPDATE evento SET activo=0 WHERE id_evento=?", 'i', array($id_ev));
	actividades_cancelar_tareas_evento($id_ev, isset($ev['titulo']) ? $ev['titulo'] : '');
	return '';
}

// Asistencia sin depender de la autoinscripción: el staff agrega al cliente como presente.
function actividades_agregar_asistente($id_evento, $id_cliente)
{
	$ev=crm_evento_por_id($id_evento);
	$id_cliente=intval($id_cliente);
	if(!actividad_puede_gestionar($ev))
	{
		return 'No podés tomar asistencia en esta actividad.';
	}
	if($id_cliente<=0 || !acceso_consulta_una("SELECT id_cliente FROM cliente WHERE id_cliente=? LIMIT 1", 'i', array($id_cliente)))
	{
		return 'Elegí un cliente de la lista.';
	}
	acceso_ejecutar(
		"INSERT INTO evento_inscripcion (id_evento, id_cliente, estado) VALUES (?,?,'asistio')
		ON DUPLICATE KEY UPDATE estado='asistio'",
		'ii',
		array(intval($ev['id_evento']), $id_cliente)
	);
	return '';
}

function actividades_marcar_asistencia($id_inscripcion, $estado)
{
	$ins=acceso_consulta_una("SELECT id_evento FROM evento_inscripcion WHERE id=? LIMIT 1", 'i', array(intval($id_inscripcion)));
	$ev=$ins ? crm_evento_por_id($ins['id_evento']) : null;
	if(!actividad_puede_gestionar($ev))
	{
		return 'No podés tomar asistencia en esta actividad.';
	}
	return crm_evento_marcar_asistencia(intval($id_inscripcion), $estado) ? '' : 'No se pudo guardar.';
}

// Filtros del listado: q (texto), tipo, desde/hasta, cuando (proximas|pasadas).
function actividades_filtrar($lista)
{
	$q=isset($_GET['q']) ? trim($_GET['q']) : '';
	$tipo=isset($_GET['tipo']) ? trim($_GET['tipo']) : '';
	$cuando=isset($_GET['cuando']) ? $_GET['cuando'] : '';
	$desde=isset($_GET['desde']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde']) ? $_GET['desde'] : '';
	$hasta=isset($_GET['hasta']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta']) ? $_GET['hasta'] : '';
	$hoy=date('Y-m-d');
	$q_l=mb_strtolower($q, 'UTF-8');
	$out=array();
	foreach($lista as $it)
	{
		$f=substr((string)$it['fecha'], 0, 10);
		if($q!=='')
		{
			$texto=mb_strtolower($it['titulo'].' '.$it['descripcion'].' '.$it['organismo'].' '.$it['responsable'], 'UTF-8');
			if(strpos($texto, $q_l)===false) { continue; }
		}
		if($tipo!=='' && $it['tipo']!==$tipo) { continue; }
		if($cuando==='proximas' && $f<$hoy) { continue; }
		if($cuando==='pasadas' && $f>=$hoy) { continue; }
		if($desde!=='' && $f<$desde) { continue; }
		if($hasta!=='' && $f>$hasta) { continue; }
		$out[]=$it;
	}
	return $out;
}

function actividades_tipo_tarea_de_area($id_area)
{
	$id_area=intval($id_area);
	if($id_area<=0)
	{
		return 0;
	}
	$row=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? ORDER BY id_tipo ASC LIMIT 1", 'i', array($id_area));
	if($row && intval($row['id_tipo'])>0)
	{
		return intval($row['id_tipo']);
	}
	// Si el área todavía no tiene objetivo/tipo, se crea uno genérico para no perder la tarea.
	$nuevo=acceso_insertar("INSERT INTO tipo_tarea (id_area, nombre) VALUES (?,?)", 'is', array($id_area, 'General'));
	if($nuevo>0)
	{
		return $nuevo;
	}
	$row=acceso_consulta_una("SELECT id_tipo FROM tipo_tarea WHERE id_area=? LIMIT 1", 'i', array($id_area));
	return $row ? intval($row['id_tipo']) : 0;
}

// Empleados activos agrupados por área (principal + extras): [area => [[id, nombre], ...]].
function actividades_responsables_por_area()
{
	$salida=array();
	$ya=array();
	foreach(acceso_personal_completo() as $p)
	{
		$area=isset($p['area']) ? $p['area'] : '';
		$nom=trim(isset($p['nombre']) && $p['nombre']!='' ? $p['nombre'] : (isset($p['persona']) ? $p['persona'] : ''));
		$id=intval($p['id']);
		if($area=='' || $nom=='' || $id<=0)
		{
			continue;
		}
		$key=$area.'|'.$id;
		if(isset($ya[$key])) { continue; }
		$ya[$key]=1;
		$salida[$area][]=array('id'=>$id, 'nombre'=>$nom);
	}
	// Sumar quienes tienen permiso adicional en otra área.
	if(acceso_tabla_existe('empleado_rol_adicional'))
	{
		$rows=acceso_consulta(
			"SELECT e.id_empleado, a.clave AS area,
				TRIM(CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,''))) AS nombre
			FROM empleado_rol_adicional x
			INNER JOIN empleado e ON e.id_empleado=x.id_empleado
			INNER JOIN area a ON a.id_area=x.id_area
			WHERE x.fecha_hasta IS NULL AND e.activo=1 AND x.id_area IS NOT NULL
			ORDER BY a.clave, e.apellido, e.nombres"
		);
		foreach($rows as $r)
		{
			$area=isset($r['area']) ? $r['area'] : '';
			$nom=trim(isset($r['nombre']) ? $r['nombre'] : '');
			$id=intval($r['id_empleado']);
			if($area=='' || $nom=='' || $id<=0) { continue; }
			$key=$area.'|'.$id;
			if(isset($ya[$key])) { continue; }
			$ya[$key]=1;
			$salida[$area][]=array('id'=>$id, 'nombre'=>$nom);
		}
	}
	return $salida;
}

// Catálogo de organismos = clientes del padrón (tipos institucionales).
function actividades_organismos_catalogo()
{
	$lista=array();
	$filas=acceso_consulta(
		"SELECT id_cliente, nombre, tipo_cliente FROM cliente
		 WHERE activo=1 AND tipo_cliente IN ('empresa','municipalidad','organismo')
		 ORDER BY nombre ASC"
	);
	foreach($filas as $r)
	{
		$nom=trim($r['nombre']);
		if($nom==='')
		{
			continue;
		}
		$lista[]=array(
			'id'=>intval($r['id_cliente']),
			'nombre'=>$nom,
			'tipo'=>$r['tipo_cliente']
		);
	}
	return $lista;
}

/** Genera las tareas pedidas en el form de actividad. Devuelve resumen. */
function actividades_generar_tareas_desde_post($id_evento, $modo='edicion')
{
	$id_evento=intval($id_evento);
	$res=array('ok'=>0, 'fallo'=>0, 'sin_resp'=>0, 'sin_titulo'=>0, 'sin_aviso'=>0, 'avisos'=>0, 'por_area'=>array(), 'areas_sin_empleados'=>array(), 'detalle'=>array());
	if($id_evento<=0 || !isset($_POST['tarea_titulo']) || !is_array($_POST['tarea_titulo']))
	{
		return $res;
	}
	$areas_ok=array_keys(acceso_etiquetas_area_con_tareas());
	$n_fila_t=0;
	foreach($_POST['tarea_titulo'] as $i=>$titulo_t)
	{
		$n_fila_t++;
		$titulo_t=trim((string)$titulo_t);
		$area_t=isset($_POST['tarea_area'][$i]) ? trim($_POST['tarea_area'][$i]) : '';
		$id_resp_t=isset($_POST['tarea_responsable'][$i]) ? intval($_POST['tarea_responsable'][$i]) : 0;
		if($titulo_t==='')
		{
			$res['sin_titulo']++;
			$res['detalle'][]='Fila '.$n_fila_t.' sin título (área '.($area_t!=='' ? $area_t : '-').'): no se creó.';
			continue;
		}
		if(!in_array($area_t, $areas_ok, true))
		{
			$res['fallo']++;
			$res['detalle'][]='Área inválida para “'.$titulo_t.'”.';
			continue;
		}
		$id_area=acceso_id_por_clave('area', $area_t);
		$id_tipo=actividades_tipo_tarea_de_area($id_area);
		if($id_area<=0 || $id_tipo<=0)
		{
			$res['fallo']++;
			$res['detalle'][]='No se pudo crear “'.$titulo_t.'” en '.$area_t.'.';
			continue;
		}
		// Validar que el área tenga personal asignado para no crear tareas sin destinatario
		$n_personal_area=(function_exists('crm_empleados_de_area_id') ? count(crm_empleados_de_area_id($id_area)) : 0);
		if($n_personal_area<=0)
		{
			$res['fallo']++;
			$res['detalle'][]='No se creó “'.$titulo_t.'”: el área '.$area_t.' no tiene colaboradores asignados.';
			continue;
		}
		if($id_resp_t>0 && function_exists('crm_empleado_pertenece_a_area') && !crm_empleado_pertenece_a_area($id_resp_t, $id_area))
		{
			$id_resp_t=0;
		}
		$id_tar=crm_evento_generar_tarea($id_evento, mb_substr($titulo_t, 0, 200), $id_area, $id_tipo, $id_resp_t);
		if($id_tar>0)
		{
			$res['ok']++;
			if($id_resp_t<=0)
			{
				$res['sin_resp']++;
			}
			if(!isset($res['por_area'][$area_t]))
			{
				$res['por_area'][$area_t]=0;
			}
			$res['por_area'][$area_t]++;
		}
		else
		{
			$res['fallo']++;
			$res['detalle'][]='Falló “'.$titulo_t.'” ('.$area_t.').';
		}
	}
	return $res;
}

function actividades_msg_tareas_generadas($res)
{
	$n_sin_titulo=is_array($res) && isset($res['sin_titulo']) ? intval($res['sin_titulo']) : 0;
	$n_ok=is_array($res) && isset($res['ok']) ? intval($res['ok']) : 0;
	$n_fallo=is_array($res) && isset($res['fallo']) ? intval($res['fallo']) : 0;
	$n_sin_resp=is_array($res) && isset($res['sin_resp']) ? intval($res['sin_resp']) : 0;
	$n_sin_aviso=is_array($res) && isset($res['sin_aviso']) ? intval($res['sin_aviso']) : 0;
	if(!is_array($res) || ($n_ok<=0 && $n_fallo<=0 && $n_sin_titulo<=0 && $n_sin_aviso<=0))
	{
		return '';
	}
	$partes=array();
	if($n_ok>0)
	{
		$areas_msg='';
		if(isset($res['por_area']) && is_array($res['por_area']) && count($res['por_area'])>0)
		{
			$lista_areas=array();
			foreach($res['por_area'] as $clave_area_msg=>$n_area_msg)
			{
				$lista_areas[]=$clave_area_msg.' ('.intval($n_area_msg).')';
			}
			$areas_msg=': '.implode(', ', $lista_areas);
		}
		$partes[]='Se crearon '.$n_ok.' tarea(s)'.$areas_msg;
	}
	$n_sin_resp_personal=$n_sin_resp-$n_sin_aviso;
	if($n_sin_resp_personal>0)
	{
		$partes[]=$n_sin_resp_personal.' sin responsable (avisado el personal del área)';
	}
	if($n_sin_aviso>0)
	{
		$items_aviso=array();
		if(isset($res['areas_sin_empleados']) && is_array($res['areas_sin_empleados']))
		{
			foreach($res['areas_sin_empleados'] as $ase)
			{
				if(is_array($ase))
				{
					$items_aviso[]='“'.$ase['titulo'].'” ('.$ase['area'].')';
				}
				else
				{
					$items_aviso[]=(string)$ase;
				}
			}
		}
		$partes[]=$n_sin_aviso.' tarea(s) en área(s) sin colaboradores'.(count($items_aviso)>0 ? ': '.implode(', ', $items_aviso) : '').' (se notificó al responsable de la actividad)';
	}
	if($n_fallo>0)
	{
		$partes[]=$n_fallo.' no se pudieron crear';
	}
	if($n_sin_titulo>0)
	{
		$partes[]=$n_sin_titulo.' fila(s) sin título no se crearon';
	}
	$msg=implode(' · ', $partes).'.';
	if(!empty($res['detalle']))
	{
		$msg.=' '.implode(' ', array_slice($res['detalle'], 0, 3));
	}
	return $msg;
}

/**
 * Calcula el avance real de una actividad institucional a partir de sus tareas vinculadas (evento_tarea).
 * Devuelve:
 * - total_tareas: cantidad de tareas principales activas vinculadas
 * - avance_general: porcentaje general (0 a 100) o null si no tiene tareas vinculadas
 * - areas: lista asociativa [clave_area => ['clave', 'nombre', 'total_tareas', 'avance', 'suma_avance']]
 * - tareas_calculadas: lista asociativa [id_tarea => ['avance_calculado', ...]]
 */
function actividad_calcular_avances($id_evento, $tareas_precargadas=null)
{
	$id=intval($id_evento);
	if(is_array($tareas_precargadas))
	{
		$tareas=$tareas_precargadas;
	}
	else
	{
		if($id<=0)
		{
			return array(
				'total_tareas'=>0,
				'avance_general'=>null,
				'areas'=>array(),
				'tareas_calculadas'=>array()
			);
		}
		$tareas=function_exists('crm_evento_tareas') ? crm_evento_tareas($id) : array();
	}

	if(empty($tareas))
	{
		return array(
			'total_tareas'=>0,
			'avance_general'=>null,
			'areas'=>array(),
			'tareas_calculadas'=>array()
		);
	}

	$suma_total=0;
	$total_tareas=0;
	$areas=array();
	$tareas_calculadas=array();

	foreach($tareas as $t)
	{
		if(isset($t['estado']) && $t['estado']==='cancelada')
		{
			continue;
		}

		$id_t=intval($t['id_tarea']);
		$subt=isset($t['subtareas']) && is_array($t['subtareas']) ? $t['subtareas'] : array();
		$subt_activas=array();
		foreach($subt as $s)
		{
			if(!isset($s['estado']) || $s['estado']!=='cancelada')
			{
				$subt_activas[]=$s;
			}
		}

		$sub_calc=array();
		if(count($subt_activas)>0)
		{
			$suma_sub=0;
			$todas_terminadas=true;
			foreach($subt_activas as $s)
			{
				$id_sub=intval($s['id_tarea']);
				if(isset($s['estado']) && $s['estado']==='terminada')
				{
					$av_s=100;
				}
				else
				{
					$todas_terminadas=false;
					$av_s=max(0, min(100, intval(isset($s['avance']) ? $s['avance'] : 0)));
				}
				$suma_sub+=$av_s;
				$s_copy=$s;
				$s_copy['avance_calculado']=$av_s;
				$sub_calc[$id_sub]=$s_copy;
			}
			$av_tar=$todas_terminadas ? 100 : intval(round($suma_sub / count($subt_activas)));
		}
		else
		{
			if(isset($t['estado']) && $t['estado']==='terminada')
			{
				$av_tar=100;
			}
			else
			{
				$av_tar=max(0, min(100, intval(isset($t['avance']) ? $t['avance'] : 0)));
			}
		}

		$t_copy=$t;
		$t_copy['avance_calculado']=$av_tar;
		$t_copy['subtareas_calculadas']=$sub_calc;
		$tareas_calculadas[$id_t]=$t_copy;

		$total_tareas++;
		$suma_total+=$av_tar;

		$clave_area=isset($t['area_clave']) && $t['area_clave']!=='' ? $t['area_clave'] : 'sin_area';
		$nombre_area=isset($t['area_nombre']) && $t['area_nombre']!=='' ? $t['area_nombre'] : 'Sin área';

		if(!isset($areas[$clave_area]))
		{
			$areas[$clave_area]=array(
				'clave'=>$clave_area,
				'nombre'=>$nombre_area,
				'total_tareas'=>0,
				'suma_avance'=>0,
				'avance'=>0,
				'tareas'=>array()
			);
		}

		$areas[$clave_area]['total_tareas']++;
		$areas[$clave_area]['suma_avance']+=$av_tar;
		$areas[$clave_area]['tareas'][]=$t_copy;
	}

	if($total_tareas===0)
	{
		return array(
			'total_tareas'=>0,
			'avance_general'=>null,
			'areas'=>array(),
			'tareas_calculadas'=>array()
		);
	}

	$avance_general=intval(round($suma_total / $total_tareas));

	foreach($areas as $k=>$a)
	{
		$areas[$k]['avance']=$a['total_tareas']>0 ? intval(round($a['suma_avance'] / $a['total_tareas'])) : 0;
	}

	return array(
		'total_tareas'=>$total_tareas,
		'avance_general'=>$avance_general,
		'areas'=>$areas,
		'tareas_calculadas'=>$tareas_calculadas
	);
}

