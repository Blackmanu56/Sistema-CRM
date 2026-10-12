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
			'area_clave'=>$area,
			'n_inscriptos'=>isset($ev['n_inscriptos']) ? intval($ev['n_inscriptos']) : 0
		);
	}
	return $lista;
}

function actividades_area_destino()
{
	$area=acceso_area_pantalla();
	$ok=array_keys(acceso_etiquetas_area_con_tareas());
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

function actividad_puede_crear()
{
	return !acceso_es_super();
}

function actividades_guardar_nueva_desde_form()
{
	if(function_exists('crm_asegurar_evento_portal'))
	{
		crm_asegurar_evento_portal();
	}
	if(!actividad_puede_crear())
	{
		return array('area'=>acceso_area_pantalla(), 'id'=>0, 'msg'=>'No podés crear actividades.');
	}
	$area=actividades_area_destino();
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

// Editar / asistencia: responsable, coordinador del área, coordinación general o superadmin.
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
	if(intval(isset($ev['id_empleado']) ? $ev['id_empleado'] : 0)===intval(crm_id_empleado_sesion()))
	{
		return true;
	}
	$area_ev=isset($ev['area_clave']) ? $ev['area_clave'] : '';
	if($area_ev!=='' && acceso_es_coord_de_area() && ($area_ev===acceso_area() || $area_ev===acceso_area_pantalla()))
	{
		return true;
	}
	return false;
}

// Eliminar: solo coordinación (área/general) o superadmin. No el responsable solo.
function actividad_puede_eliminar($ev)
{
	if(!$ev)
	{
		return false;
	}
	if(acceso_es_super() || acceso_es_coord_general() || acceso_es_general())
	{
		return true;
	}
	$area_ev=isset($ev['area_clave']) ? $ev['area_clave'] : '';
	if($area_ev!=='' && acceso_es_coord_de_area() && ($area_ev===acceso_area() || $area_ev===acceso_area_pantalla()))
	{
		return true;
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
			id_empleado=NULLIF(?,0), id_cliente=NULLIF(?,0), organismo=?,
			autoinscripcion=?, publico=?
		WHERE id_evento=?",
		'ssssiisisi',
		array(
			mb_substr($titulo, 0, 200),
			isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '',
			$tipo,
			$fecha,
			$id_emp>0 ? $id_emp : 0,
			$id_org>0 ? $id_org : 0,
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
			$res['detalle'][]='No se creó “'.$titulo_t.'”: el área '.$area_t.' no tiene empleados asignados.';
			continue;
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
		$partes[]=$n_sin_aviso.' tarea(s) en área(s) sin empleados'.(count($items_aviso)>0 ? ': '.implode(', ', $items_aviso) : '').' (se notificó al responsable de la actividad)';
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
