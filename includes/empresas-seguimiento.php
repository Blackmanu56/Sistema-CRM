<?php
// Seguimientos manuales a 3 y 6 meses vinculados a atenciones de empresas.

function empresa_seguimiento_fecha_prevista($fecha_referencia, $meses)
{
	$meses=intval($meses);
	if(!in_array($meses, array(3,6), true)) { return false; }
	return empresa_seguimiento_fecha_calcular($fecha_referencia,$meses);
}

function empresa_seguimiento_fecha_manual($fecha_referencia, $meses)
{
	$meses=intval($meses);
	if(!in_array($meses, array(1,3,6), true)) { return false; }
	return empresa_seguimiento_fecha_calcular($fecha_referencia,$meses);
}

function empresa_seguimiento_fecha_calcular($fecha_referencia, $meses)
{
	if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$fecha_referencia, $partes))
	{
		return false;
	}
	$anio=intval($partes[1]);
	$mes=intval($partes[2]);
	$dia=intval($partes[3]);
	if(!checkdate($mes, $dia, $anio))
	{
		return false;
	}
	$indice_mes=($anio * 12) + ($mes - 1) + $meses;
	$anio_destino=(int)floor($indice_mes / 12);
	$mes_destino=($indice_mes % 12) + 1;
	$dia_destino=min($dia, cal_days_in_month(CAL_GREGORIAN, $mes_destino, $anio_destino));
	return sprintf('%04d-%02d-%02d', $anio_destino, $mes_destino, $dia_destino);
}

function empresa_seguimiento_id_empleado_sesion()
{
	$id=isset($_SESSION['id_empleado']) ? intval($_SESSION['id_empleado']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
	return $id>0 ? $id : 0;
}

function empresa_seguimiento_token_manual()
{
	if(!isset($_SESSION['empresa_seguimiento_tokens_manual']) || !is_array($_SESSION['empresa_seguimiento_tokens_manual'])) { $_SESSION['empresa_seguimiento_tokens_manual']=array(); }
	$ahora=time();
	foreach($_SESSION['empresa_seguimiento_tokens_manual'] as $token=>$creado) { if(intval($creado)<$ahora-1800) { unset($_SESSION['empresa_seguimiento_tokens_manual'][$token]); } }
	if(count($_SESSION['empresa_seguimiento_tokens_manual'])>=10) { array_shift($_SESSION['empresa_seguimiento_tokens_manual']); }
	if(function_exists('random_bytes'))
	{
		try { $token=bin2hex(random_bytes(24)); }
		catch(Exception $e) { $token=sha1(uniqid((string)mt_rand(),true)); }
	}
	else { $token=sha1(uniqid((string)mt_rand(),true)); }
	$_SESSION['empresa_seguimiento_tokens_manual'][$token]=$ahora;
	return $token;
}

function empresa_seguimiento_token_manual_valido($token)
{
	return is_string($token) && isset($_SESSION['empresa_seguimiento_tokens_manual'][$token])
		&& intval($_SESSION['empresa_seguimiento_tokens_manual'][$token])>=time()-1800;
}

function empresa_seguimiento_token_manual_consumir($token)
{
	if(isset($_SESSION['empresa_seguimiento_tokens_manual'][$token])) { unset($_SESSION['empresa_seguimiento_tokens_manual'][$token]); }
}

function empresa_seguimiento_empresas_visibles()
{
	$filas=acceso_consulta("SELECT id_cliente,nombre FROM cliente WHERE tipo_cliente='empresa' ORDER BY nombre");
	$salida=array();
	foreach(is_array($filas)?$filas:array() as $fila)
	{
		$id=intval($fila['id_cliente']);
		if($id>0 && empresa_seguimiento_puede_ver($id,'empresas')) { $salida[]=$fila; }
	}
	return $salida;
}

function empresa_seguimiento_estado_visible($estado, $fecha_prevista, $fecha_realizacion, $hoy=null)
{
	if($hoy===null)
	{
		$hoy=date('Y-m-d');
	}
	if($estado==='realizado' || (is_string($fecha_realizacion) && $fecha_realizacion!==''))
	{
		return 'realizado';
	}
	return ((string)$fecha_prevista < (string)$hoy) ? 'vencido' : 'pendiente';
}

function empresa_seguimiento_resultado_valido($resultado)
{
	return in_array($resultado, array('positivo','neutro','negativo'), true);
}

function empresa_seguimiento_estado_tablero($estado, $fecha_prevista, $fecha_realizacion, $hoy=null)
{
	$visible=empresa_seguimiento_estado_visible($estado, $fecha_prevista, $fecha_realizacion, $hoy);
	if($visible!=='pendiente') { return $visible; }
	$hoy=$hoy===null ? date('Y-m-d') : $hoy;
	$limite=date('Y-m-d', strtotime($hoy.' +30 days'));
	return (string)$fecha_prevista<=$limite ? 'proximo' : 'pendiente';
}

function empresa_seguimiento_empleados_autorizados()
{
	if(!function_exists('acceso_personal_completo')) { return array(); }
	$salida=array();
	foreach(acceso_personal_completo() as $persona)
	{
		$id=isset($persona['id']) ? intval($persona['id']) : 0;
		if($id>0 && isset($persona['area']) && $persona['area']==='empresas'
			&& (!isset($persona['activo']) || intval($persona['activo'])===1))
		{
			$salida[$id]=array('id'=>$id,'nombre'=>trim((string)$persona['nombre']));
		}
	}
	 uasort($salida, function($a,$b){ return strcasecmp($a['nombre'],$b['nombre']); });
	return array_values($salida);
}

function empresa_seguimiento_empleado_autorizado($id_empleado)
{
	$id_empleado=intval($id_empleado);
	foreach(empresa_seguimiento_empleados_autorizados() as $empleado)
	{
		if($empleado['id']===$id_empleado) { return true; }
	}
	return false;
}

function empresa_seguimiento_responsable_sugerido($id_atencion, $empleados=null)
{
	if($empleados===null) { $empleados=empresa_seguimiento_empleados_autorizados(); }
	$fila=acceso_consulta_una('SELECT id_empleado FROM atencion WHERE id_atencion=? LIMIT 1','i',array(intval($id_atencion)));
	$id=$fila && isset($fila['id_empleado']) ? intval($fila['id_empleado']) : 0;
	foreach($empleados as $empleado)
	{
		if($id>0 && intval($empleado['id'])===$id) { return $id; }
	}
	return 0;
}

function empresa_seguimiento_puede_ver($id_cliente, $area='')
{
	$id_cliente=intval($id_cliente);
	if($area==='' && function_exists('acceso_area_pantalla'))
	{
		$area=acceso_area_pantalla();
	}
	if($id_cliente<=0 || $area!=='empresas' || !function_exists('crm_es_staff') || !crm_es_staff()
		|| !function_exists('acceso_puede_clientes_de_area') || !acceso_puede_clientes_de_area('empresas')
		|| !function_exists('crm_cliente_puede_ver') || !crm_cliente_puede_ver($id_cliente, 'empresas'))
	{
		return false;
	}
	$cliente=cliente_traer($id_cliente);
	return $cliente && function_exists('cliente_tipo_normalizar')
		&& cliente_tipo_normalizar(isset($cliente['tipo_cliente']) ? $cliente['tipo_cliente'] : '')==='empresa';
}

function empresa_seguimiento_puede_editar($id_cliente, $area='')
{
	if(!empresa_seguimiento_puede_ver($id_cliente, $area))
	{
		return false;
	}
	return !function_exists('crm_cliente_area_solo_lectura') || !crm_cliente_area_solo_lectura('empresas');
}

function empresa_seguimiento_puede_ver_area($area='')
{
	if($area==='' && function_exists('acceso_area_pantalla')) { $area=acceso_area_pantalla(); }
	return $area==='empresas' && function_exists('crm_es_staff') && crm_es_staff()
		&& function_exists('acceso_puede') && acceso_puede('empresas')
		&& function_exists('acceso_puede_clientes_de_area') && acceso_puede_clientes_de_area('empresas');
}

function empresa_seguimiento_atencion_referencia($id_cliente, $id_atencion)
{
	return acceso_consulta_una(
		"SELECT at.id_atencion, at.id_empleado, DATE(COALESCE(at.fecha_turno,at.fecha)) AS fecha_referencia
		 FROM atencion at
		 INNER JOIN cliente c ON c.id_cliente=at.id_cliente
		 WHERE at.id_atencion=? AND at.id_cliente=? AND c.tipo_cliente='empresa' AND at.estado<>'cancelada'
		 LIMIT 1",
		'ii',
		array(intval($id_atencion), intval($id_cliente))
	);
}

function empresa_seguimiento_procesar_post($id_cliente, $area, $solo_lectura, $url_retorno=null)
{
	if(!isset($_POST['empresa_seguimiento_accion']))
	{
		return array('error'=>'', 'valores'=>array(), 'accion'=>'');
	}
	if(!empresa_seguimiento_puede_editar($id_cliente, $area) || $solo_lectura)
	{
		http_response_code(403);
		header('Content-Type: text/plain; charset=UTF-8');
		exit('No estás autorizado para modificar seguimientos de esta empresa.');
	}
	$valores=$_POST;
	$accion=isset($_POST['empresa_seguimiento_accion']) && is_string($_POST['empresa_seguimiento_accion']) ? trim($_POST['empresa_seguimiento_accion']) : '';
	$token=isset($_POST['csrf_tarea_extra']) && is_string($_POST['csrf_tarea_extra']) ? $_POST['csrf_tarea_extra'] : '';
	if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido($token))
	{
		return array('error'=>'La sesión expiró o el formulario no es válido. Actualizá la página e intentá nuevamente.', 'valores'=>$valores, 'accion'=>$accion);
	}
	if(!function_exists('acceso_tabla_existe') || !acceso_tabla_existe('empresa_seguimiento'))
	{
		return array('error'=>'La base de pruebas todavía no tiene habilitado el seguimiento empresarial.', 'valores'=>$valores, 'accion'=>$accion);
	}
	if(!function_exists('crm_columna_existe') || !crm_columna_existe('empresa_seguimiento','resultado_clasificacion'))
	{
		return array('error'=>'La base todavía no tiene habilitada la clasificación de resultados. Aplicá la migración del seguimiento empresarial.', 'valores'=>$valores, 'accion'=>$accion);
	}
	$id_cliente=intval($id_cliente);
	if($accion==='crear_manual')
	{
		$token_manual=isset($_POST['empresa_seguimiento_token_manual']) && is_string($_POST['empresa_seguimiento_token_manual']) ? $_POST['empresa_seguimiento_token_manual'] : '';
		if(!empresa_seguimiento_token_manual_valido($token_manual)) { return array('error'=>'Este formulario ya fue enviado o venció. Actualizá la página antes de volver a intentar.', 'valores'=>$valores, 'accion'=>$accion); }
		$id_atencion_post=isset($_POST['id_atencion_referencia']) && is_string($_POST['id_atencion_referencia']) ? trim($_POST['id_atencion_referencia']) : '';
		$id_atencion=null;
		$atencion=null;
		if($id_atencion_post!=='')
		{
			if(!ctype_digit($id_atencion_post) || intval($id_atencion_post)<=0) { return array('error'=>'Seleccioná una atención válida de esta empresa.', 'valores'=>$valores, 'accion'=>$accion); }
			$id_atencion=intval($id_atencion_post);
			$atencion=empresa_seguimiento_atencion_referencia($id_cliente,$id_atencion);
			if(!$atencion || empty($atencion['fecha_referencia'])) { return array('error'=>'La atención seleccionada no pertenece a esta empresa o no está disponible.', 'valores'=>$valores, 'accion'=>$accion); }
		}
		$modo_fecha=isset($_POST['modo_fecha']) && is_string($_POST['modo_fecha']) ? $_POST['modo_fecha'] : 'fecha';
		$fecha_prevista='';
		if($modo_fecha==='fecha')
		{
			$fecha_prevista=isset($_POST['fecha_prevista']) && is_string($_POST['fecha_prevista']) ? trim($_POST['fecha_prevista']) : '';
		}
		elseif($modo_fecha==='plazo')
		{
			$plazo_post=isset($_POST['plazo_meses']) && is_string($_POST['plazo_meses']) ? $_POST['plazo_meses'] : '';
			if(!in_array($plazo_post,array('1','3','6'),true)) { return array('error'=>'Seleccioná un plazo válido de 1, 3 o 6 meses.', 'valores'=>$valores, 'accion'=>$accion); }
			$plazo=intval($plazo_post);
			$fecha_prevista=empresa_seguimiento_fecha_manual(date('Y-m-d'),$plazo);
		}
		else { return array('error'=>'Seleccioná una fecha concreta o un plazo válido.', 'valores'=>$valores, 'accion'=>$accion); }
		if(!is_string($fecha_prevista) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$fecha_prevista,$fecha_partes)
			|| !checkdate(intval($fecha_partes[2]),intval($fecha_partes[3]),intval($fecha_partes[1])))
		{
			return array('error'=>'Ingresá una fecha prevista válida.', 'valores'=>$valores, 'accion'=>$accion);
		}
		$motivo=isset($_POST['motivo']) && is_string($_POST['motivo']) ? trim($_POST['motivo']) : '';
		$longitud_motivo=function_exists('mb_strlen') ? mb_strlen($motivo,'UTF-8') : strlen($motivo);
		if($motivo==='' || $longitud_motivo>255) { return array('error'=>'El motivo es obligatorio y no puede superar los 255 caracteres.', 'valores'=>$valores, 'accion'=>$accion); }
		$id_empleado_responsable=isset($_POST['id_empleado_responsable']) ? intval($_POST['id_empleado_responsable']) : 0;
		if($id_empleado_responsable<=0 || !empresa_seguimiento_empleado_autorizado($id_empleado_responsable)) { return array('error'=>'Seleccioná un responsable activo autorizado del área Empresas.', 'valores'=>$valores, 'accion'=>$accion); }
		$id_creador=empresa_seguimiento_id_empleado_sesion();
		if($id_creador<=0) { return array('error'=>'No se pudo identificar al empleado autenticado para registrar el seguimiento.', 'valores'=>$valores, 'accion'=>$accion); }
		$observaciones=isset($_POST['observaciones']) && is_string($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
		global $con;
		if(!$con || !($con instanceof mysqli)) { return array('error'=>'No se pudo conectar con la base de datos.', 'valores'=>$valores, 'accion'=>$accion); }
		if(!acceso_consulta_una('SELECT id_empleado FROM empleado WHERE id_empleado=? AND activo=1 LIMIT 1','i',array($id_creador))) { return array('error'=>'La sesión no corresponde a un empleado activo autorizado.', 'valores'=>$valores, 'accion'=>$accion); }
		if($atencion)
		{
			$stmt=mysqli_prepare($con,"INSERT INTO empresa_seguimiento (id_cliente,id_atencion_referencia,tipo_seguimiento,fecha_referencia,fecha_prevista,fecha_realizacion,estado,resultado_clasificacion,situacion_resultado,observaciones,id_empleado_responsable,motivo,id_empleado_creador) VALUES (?,?, 'manual', ?, ?, NULL, 'pendiente', NULL, NULL, ?, ?, ?, ?)");
			if($stmt) { mysqli_stmt_bind_param($stmt,'iisssisi',$id_cliente,$id_atencion,$atencion['fecha_referencia'],$fecha_prevista,$observaciones,$id_empleado_responsable,$motivo,$id_creador); }
		}
		else
		{
			$stmt=mysqli_prepare($con,"INSERT INTO empresa_seguimiento (id_cliente,id_atencion_referencia,tipo_seguimiento,fecha_referencia,fecha_prevista,fecha_realizacion,estado,resultado_clasificacion,situacion_resultado,observaciones,id_empleado_responsable,motivo,id_empleado_creador) VALUES (?,NULL,'manual',NULL,?,NULL,'pendiente',NULL,NULL,?,?,?,?)");
			if($stmt) { mysqli_stmt_bind_param($stmt,'issisi',$id_cliente,$fecha_prevista,$observaciones,$id_empleado_responsable,$motivo,$id_creador); }
		}
		if(!$stmt || !mysqli_stmt_execute($stmt))
		{
			if($stmt) { mysqli_stmt_close($stmt); }
			return array('error'=>'No se pudo programar el seguimiento. Revisá los datos e intentá nuevamente.', 'valores'=>$valores, 'accion'=>$accion);
		}
		mysqli_stmt_close($stmt);
		empresa_seguimiento_token_manual_consumir($token_manual);
		$_SESSION['msgcliente']='Seguimiento manual programado correctamente.';
		$destino=is_string($url_retorno) && $url_retorno!=='' ? $url_retorno : url_crm('empresas/editar-cliente.php?id='.$id_cliente.'#seguimiento-empresarial');
		header('Location: '.$destino);
		exit();
	}
	$id_atencion=isset($_POST['id_atencion_referencia']) ? intval($_POST['id_atencion_referencia']) : 0;
	$tipo=isset($_POST['tipo_seguimiento']) && is_string($_POST['tipo_seguimiento']) ? trim($_POST['tipo_seguimiento']) : '';
	$id_seguimiento=isset($_POST['id_seguimiento']) ? intval($_POST['id_seguimiento']) : 0;
	$seguimiento_actual=$accion==='actualizar' && $id_seguimiento>0 ? acceso_consulta_una("SELECT * FROM empresa_seguimiento WHERE id_seguimiento=? AND id_cliente=? LIMIT 1",'ii',array($id_seguimiento,$id_cliente)) : null;
	$es_manual=$seguimiento_actual && $seguimiento_actual['tipo_seguimiento']==='manual';
	if($es_manual) { $tipo='manual'; $id_atencion=$seguimiento_actual['id_atencion_referencia']!==null ? intval($seguimiento_actual['id_atencion_referencia']) : 0; }
	if(!in_array($accion, array('crear','actualizar'), true)
		|| ($accion==='crear' && !in_array($tipo,array('3_meses','6_meses'),true))
		|| ($accion==='actualizar' && !$seguimiento_actual)
		|| ($accion==='actualizar' && !$es_manual && !in_array($tipo,array('3_meses','6_meses'),true)))
	{
		return array('error'=>'Seleccioná un tipo de seguimiento válido.', 'valores'=>$valores, 'accion'=>$accion);
	}
	$atencion=null;
	if(!$es_manual)
	{
		$atencion=empresa_seguimiento_atencion_referencia($id_cliente, $id_atencion);
		if(!$atencion || empty($atencion['fecha_referencia']))
		{
			return array('error'=>'La atención de referencia no pertenece a esta empresa o no está disponible.', 'valores'=>$valores, 'accion'=>$accion);
		}
	}
	$fecha_referencia=$es_manual ? ($seguimiento_actual['fecha_referencia']!==null ? (string)$seguimiento_actual['fecha_referencia'] : null) : $atencion['fecha_referencia'];
	if($es_manual)
	{
		$fecha_prevista=isset($_POST['fecha_prevista']) && is_string($_POST['fecha_prevista']) ? trim($_POST['fecha_prevista']) : (string)$seguimiento_actual['fecha_prevista'];
		$motivo=isset($_POST['motivo']) && is_string($_POST['motivo']) ? trim($_POST['motivo']) : (string)$seguimiento_actual['motivo'];
		$longitud_motivo=function_exists('mb_strlen') ? mb_strlen($motivo,'UTF-8') : strlen($motivo);
		if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$fecha_prevista,$fecha_partes)
			|| !checkdate(intval($fecha_partes[2]),intval($fecha_partes[3]),intval($fecha_partes[1]))) { return array('error'=>'Ingresá una fecha prevista válida.', 'valores'=>$valores, 'accion'=>$accion); }
		if($motivo==='' || $longitud_motivo>255) { return array('error'=>'El motivo es obligatorio y no puede superar los 255 caracteres.', 'valores'=>$valores, 'accion'=>$accion); }
	}
	else
	{
		$meses=$tipo==='3_meses' ? 3 : 6;
		$fecha_prevista=empresa_seguimiento_fecha_prevista($fecha_referencia, $meses);
		$motivo=$seguimiento_actual && isset($seguimiento_actual['motivo']) ? (string)$seguimiento_actual['motivo'] : '';
	}
	if($fecha_prevista===false)
	{
		return array('error'=>'No se pudo calcular la fecha prevista del seguimiento.', 'valores'=>$valores, 'accion'=>$accion);
	}
	$estado_solicitado=isset($_POST['estado']) && is_string($_POST['estado']) ? trim($_POST['estado']) : 'pendiente';
	if(!in_array($estado_solicitado, array('pendiente','realizado'), true))
	{
		return array('error'=>'Seleccioná un estado válido.', 'valores'=>$valores, 'accion'=>$accion);
	}
	$fecha_realizacion=isset($_POST['fecha_realizacion']) && is_string($_POST['fecha_realizacion']) ? trim($_POST['fecha_realizacion']) : '';
	if($estado_solicitado==='realizado')
	{
		if(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha_realizacion, $fecha_partes)
			|| !checkdate(intval($fecha_partes[2]), intval($fecha_partes[3]), intval($fecha_partes[1])))
		{
			return array('error'=>'Ingresá una fecha válida para marcar el seguimiento como realizado.', 'valores'=>$valores, 'accion'=>$accion);
		}
		if((!empty($fecha_referencia) && $fecha_realizacion < $fecha_referencia) || $fecha_realizacion > date('Y-m-d'))
		{
			return array('error'=>'La fecha de realización debe estar entre la atención de referencia y hoy.', 'valores'=>$valores, 'accion'=>$accion);
		}
	}
	else
	{
		$fecha_realizacion=null;
	}
	$situacion=isset($_POST['situacion_resultado']) && is_string($_POST['situacion_resultado']) ? trim($_POST['situacion_resultado']) : '';
	$observaciones=isset($_POST['observaciones']) && is_string($_POST['observaciones']) ? trim($_POST['observaciones']) : '';
	$resultado=isset($_POST['resultado_clasificacion']) && is_string($_POST['resultado_clasificacion']) ? trim($_POST['resultado_clasificacion']) : '';
	if($resultado!=='' && !empresa_seguimiento_resultado_valido($resultado))
	{
		return array('error'=>'Seleccioná una clasificación de resultado válida.', 'valores'=>$valores, 'accion'=>$accion);
	}
	if($estado_solicitado==='realizado' && !empresa_seguimiento_resultado_valido($resultado))
	{
		return array('error'=>'Seleccioná si el resultado fue positivo, neutro o negativo.', 'valores'=>$valores, 'accion'=>$accion);
	}
	if($resultado==='') { $resultado=null; }
	$id_empleado_responsable=isset($_POST['id_empleado_responsable']) ? intval($_POST['id_empleado_responsable']) : 0;
	$responsable_actual=$accion==='actualizar' && $id_seguimiento>0
		? acceso_consulta_una('SELECT id_empleado_responsable FROM empresa_seguimiento WHERE id_seguimiento=? AND id_cliente=? LIMIT 1','ii',array($id_seguimiento,$id_cliente))
		: null;
	$modo_edicion_manual=isset($_POST['seguimiento_modo']) && is_string($_POST['seguimiento_modo']) ? trim($_POST['seguimiento_modo']) : '';
	$resultado_solo_manual=$es_manual && $accion==='actualizar' && $modo_edicion_manual==='resultado';
	if($resultado_solo_manual)
	{
		$id_empleado_responsable=$responsable_actual && intval($responsable_actual['id_empleado_responsable'])>0 ? intval($responsable_actual['id_empleado_responsable']) : 0;
	}
	if(!isset($_POST['id_empleado_responsable']) && $accion==='actualizar')
	{
		$id_empleado_responsable=$responsable_actual ? intval($responsable_actual['id_empleado_responsable']) : 0;
	}
	if(!isset($_POST['id_empleado_responsable']) && $accion==='crear')
	{
		$id_empleado_responsable=empresa_seguimiento_responsable_sugerido($id_atencion);
	}
	$responsable_preexistente=($accion==='actualizar' && $responsable_actual && intval($responsable_actual['id_empleado_responsable'])===$id_empleado_responsable);
	if($es_manual && !$resultado_solo_manual && $id_empleado_responsable<=0)
	{
		return array('error'=>'Seleccioná un responsable activo autorizado del área Empresas.', 'valores'=>$valores, 'accion'=>$accion);
	}
	if($id_empleado_responsable>0 && !$responsable_preexistente && !empresa_seguimiento_empleado_autorizado($id_empleado_responsable))
	{
		return array('error'=>'El responsable seleccionado no pertenece al personal activo autorizado de Empresas.', 'valores'=>$valores, 'accion'=>$accion);
	}
	$id_empleado_responsable=$id_empleado_responsable>0 ? $id_empleado_responsable : null;
	global $con;
	if(!$con || !($con instanceof mysqli))
	{
		return array('error'=>'No se pudo conectar con la base de datos.', 'valores'=>$valores, 'accion'=>$accion);
	}
	if($accion==='actualizar')
	{
		if($id_seguimiento<=0 || !acceso_consulta_una("SELECT id_seguimiento FROM empresa_seguimiento WHERE id_seguimiento=? AND id_cliente=? LIMIT 1", 'ii', array($id_seguimiento,$id_cliente)))
		{
			return array('error'=>'El seguimiento solicitado no pertenece a esta empresa.', 'valores'=>$valores, 'accion'=>$accion);
		}
		$duplicado=!$es_manual ? acceso_consulta_una("SELECT id_seguimiento FROM empresa_seguimiento WHERE id_atencion_referencia=? AND tipo_seguimiento=? AND id_seguimiento<>? LIMIT 1", 'isi', array($id_atencion,$tipo,$id_seguimiento)) : false;
		if($duplicado)
		{
			return array('error'=>'Ya existe ese hito para la atención de referencia seleccionada.', 'valores'=>$valores, 'accion'=>$accion);
		}
		if($es_manual)
		{
			$stmt=mysqli_prepare($con, "UPDATE empresa_seguimiento SET fecha_prevista=?, fecha_realizacion=?, estado=?, resultado_clasificacion=?, situacion_resultado=?, observaciones=?, id_empleado_responsable=?, motivo=? WHERE id_seguimiento=? AND id_cliente=? AND tipo_seguimiento='manual'");
			if($stmt) { mysqli_stmt_bind_param($stmt, 'ssssssisii', $fecha_prevista, $fecha_realizacion, $estado_solicitado, $resultado, $situacion, $observaciones, $id_empleado_responsable, $motivo, $id_seguimiento, $id_cliente); }
		}
		else
		{
			$stmt=mysqli_prepare($con, "UPDATE empresa_seguimiento SET id_atencion_referencia=?, tipo_seguimiento=?, fecha_referencia=?, fecha_prevista=?, fecha_realizacion=?, estado=?, resultado_clasificacion=?, situacion_resultado=?, observaciones=?, id_empleado_responsable=? WHERE id_seguimiento=? AND id_cliente=?");
			if($stmt) { mysqli_stmt_bind_param($stmt, 'issssssssiii', $id_atencion, $tipo, $fecha_referencia, $fecha_prevista, $fecha_realizacion, $estado_solicitado, $resultado, $situacion, $observaciones, $id_empleado_responsable, $id_seguimiento, $id_cliente); }
		}
	}
	else
	{
		if(acceso_consulta_una("SELECT id_seguimiento FROM empresa_seguimiento WHERE id_atencion_referencia=? AND tipo_seguimiento=? LIMIT 1", 'is', array($id_atencion,$tipo)))
		{
			return array('error'=>'Ya existe ese hito para la atención de referencia seleccionada.', 'valores'=>$valores, 'accion'=>$accion);
		}
		$stmt=mysqli_prepare($con, "INSERT INTO empresa_seguimiento (id_cliente, id_atencion_referencia, tipo_seguimiento, fecha_referencia, fecha_prevista, fecha_realizacion, estado, resultado_clasificacion, situacion_resultado, observaciones, id_empleado_responsable) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
		if($stmt)
		{
			mysqli_stmt_bind_param($stmt, 'iissssssssi', $id_cliente, $id_atencion, $tipo, $fecha_referencia, $fecha_prevista, $fecha_realizacion, $estado_solicitado, $resultado, $situacion, $observaciones, $id_empleado_responsable);
		}
	}
	if(!$stmt || !mysqli_stmt_execute($stmt))
	{
		$errno=$stmt ? mysqli_stmt_errno($stmt) : mysqli_errno($con);
		if($stmt) { mysqli_stmt_close($stmt); }
		return array('error'=>$errno===1062 ? 'Ya existe ese hito para la atención de referencia seleccionada.' : 'No se pudo guardar el seguimiento. Revisá los datos e intentá nuevamente.', 'valores'=>$valores, 'accion'=>$accion);
	}
	mysqli_stmt_close($stmt);
	$_SESSION['msgcliente']=$es_manual ? 'Seguimiento manual actualizado correctamente.' : 'Seguimiento empresarial guardado correctamente.';
	$destino=is_string($url_retorno) && $url_retorno!=='' ? $url_retorno : url_crm('empresas/editar-cliente.php?id='.$id_cliente.'#seguimiento-empresarial');
	header('Location: '.$destino);
	exit();
}

function empresa_seguimiento_lista_atenciones($id_cliente)
{
	return acceso_consulta(
		"SELECT at.id_atencion, at.id_cliente, at.id_empleado, DATE(COALESCE(at.fecha_turno,at.fecha)) AS fecha_referencia, at.motivo, at.estado, ar.nombre AS area_nombre
		 FROM atencion at LEFT JOIN area ar ON ar.id_area=at.id_area
		 WHERE at.id_cliente=? AND at.estado<>'cancelada'
		 ORDER BY at.fecha DESC, at.id_atencion DESC",
		'i',
		array(intval($id_cliente))
	);
}

function empresa_seguimiento_lista($id_cliente)
{
	return acceso_consulta(
		"SELECT s.*, CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS responsable_nombre,
			CASE WHEN s.estado='realizado' THEN 'realizado'
				 WHEN s.fecha_prevista<CURDATE() THEN 'vencido' ELSE 'pendiente' END AS estado_visible
		 FROM empresa_seguimiento s
		 LEFT JOIN empleado e ON e.id_empleado=s.id_empleado_responsable
		 INNER JOIN cliente c ON c.id_cliente=s.id_cliente AND c.tipo_cliente='empresa'
		 WHERE s.id_cliente=? ORDER BY s.fecha_prevista DESC, s.id_seguimiento DESC",
		'i',
		array(intval($id_cliente))
	);
}

function empresa_seguimiento_tablero_datos()
{
	if(!empresa_seguimiento_puede_ver_area('empresas') || !function_exists('acceso_tabla_existe') || !acceso_tabla_existe('empresa_seguimiento')) { return array(); }
	$filas=acceso_consulta(
		"SELECT s.*, c.nombre AS empresa_nombre, a.motivo AS atencion_motivo, a.estado AS atencion_estado,
			DATE(COALESCE(a.fecha_turno,a.fecha)) AS atencion_fecha, ar.nombre AS atencion_area,
			CONCAT(IFNULL(e.nombres,''),' ',IFNULL(e.apellido,'')) AS responsable_nombre
		 FROM empresa_seguimiento s
		 INNER JOIN cliente c ON c.id_cliente=s.id_cliente AND c.tipo_cliente='empresa'
		 LEFT JOIN atencion a ON a.id_atencion=s.id_atencion_referencia AND a.id_cliente=s.id_cliente
		 LEFT JOIN area ar ON ar.id_area=a.id_area
		 LEFT JOIN empleado e ON e.id_empleado=s.id_empleado_responsable
		 ORDER BY s.fecha_prevista ASC, s.id_seguimiento ASC"
	);
	if(!is_array($filas)) { return array(); }
	$autorizadas=array();
	$salida=array();
	foreach($filas as $fila)
	{
		$id_cliente=intval($fila['id_cliente']);
		if(!isset($autorizadas[$id_cliente])) { $autorizadas[$id_cliente]=empresa_seguimiento_puede_ver($id_cliente,'empresas'); }
		if($autorizadas[$id_cliente]) { $fila['estado_tablero']=empresa_seguimiento_estado_tablero($fila['estado'],$fila['fecha_prevista'],$fila['fecha_realizacion']); $fila['resultado_etiqueta']=empresa_seguimiento_resultado_etiqueta(isset($fila['resultado_clasificacion']) ? $fila['resultado_clasificacion'] : ''); $salida[]=$fila; }
	}
	return isset($salida) ? $salida : array();
}

function empresa_seguimiento_resultado_etiqueta($resultado)
{
	$etiquetas=array('positivo'=>'Positivo','neutro'=>'Neutro','negativo'=>'Negativo');
	return isset($etiquetas[$resultado]) ? $etiquetas[$resultado] : '';
}

function empresa_seguimiento_tipo_etiqueta($tipo)
{
	$etiquetas=array('3_meses'=>'3 meses','6_meses'=>'6 meses','manual'=>'Manual');
	return isset($etiquetas[$tipo]) ? $etiquetas[$tipo] : '';
}

function empresa_seguimiento_tablero_filtrar($seguimientos, $filtros, $hoy=null)
{
	$hoy=$hoy===null ? date('Y-m-d') : $hoy;
	$busqueda=isset($filtros['q']) ? trim((string)$filtros['q']) : '';
	$estado=isset($filtros['estado']) ? (string)$filtros['estado'] : '';
	$hito=isset($filtros['hito']) ? (string)$filtros['hito'] : '';
	$desde=isset($filtros['desde']) ? (string)$filtros['desde'] : '';
	$hasta=isset($filtros['hasta']) ? (string)$filtros['hasta'] : '';
	$salida=array();
	foreach($seguimientos as $fila)
	{
		$estado_fila=isset($fila['estado_tablero']) ? $fila['estado_tablero'] : empresa_seguimiento_estado_tablero($fila['estado'],$fila['fecha_prevista'],$fila['fecha_realizacion'],$hoy);
		$texto=(isset($fila['empresa_nombre']) ? $fila['empresa_nombre'] : '').' '.(isset($fila['atencion_motivo']) ? $fila['atencion_motivo'] : '');
		if($busqueda!=='' && (function_exists('mb_stripos') ? mb_stripos($texto,$busqueda,0,'UTF-8') : stripos($texto,$busqueda))===false) { continue; }
		if($hito!=='' && $hito!==$fila['tipo_seguimiento']) { continue; }
		if($estado!=='' && $estado!==$estado_fila) { continue; }
		if($desde!=='' && $fila['fecha_prevista']<$desde) { continue; }
		if($hasta!=='' && $fila['fecha_prevista']>$hasta) { continue; }
		$fila['estado_tablero']=$estado_fila;
		$salida[]=$fila;
	}
	return $salida;
}

function empresa_seguimiento_h($valor)
{
	return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function empresa_seguimiento_render_formulario($id_cliente, $atenciones, $csrf, $modo='crear', $fila=null, $valores=array())
{
	$id_seguimiento=$fila ? intval($fila['id_seguimiento']) : 0;
	$tipo=isset($valores['tipo_seguimiento']) ? $valores['tipo_seguimiento'] : ($fila ? $fila['tipo_seguimiento'] : '3_meses');
	$id_atencion=isset($valores['id_atencion_referencia']) ? intval($valores['id_atencion_referencia']) : ($fila ? intval($fila['id_atencion_referencia']) : 0);
	$estado=isset($valores['estado']) ? $valores['estado'] : ($fila ? $fila['estado'] : 'pendiente');
	$fecha_realizacion=isset($valores['fecha_realizacion']) ? $valores['fecha_realizacion'] : ($fila && !empty($fila['fecha_realizacion']) ? $fila['fecha_realizacion'] : '');
	$resultado=isset($valores['resultado_clasificacion']) ? $valores['resultado_clasificacion'] : ($fila && !empty($fila['resultado_clasificacion']) ? $fila['resultado_clasificacion'] : '');
	$id_responsable=isset($valores['id_empleado_responsable']) ? intval($valores['id_empleado_responsable']) : ($fila ? intval($fila['id_empleado_responsable']) : 0);
	$empleados=empresa_seguimiento_empleados_autorizados();
	$empleados_ids=array(); foreach($empleados as $empleado) { $empleados_ids[intval($empleado['id'])]=true; }
	$responsable_preexistente_no_activo=$fila && $id_responsable>0 && !isset($empleados_ids[$id_responsable]);
	if($modo==='crear' && !isset($valores['id_empleado_responsable'])) { $id_responsable=empresa_seguimiento_responsable_sugerido($id_atencion,$empleados); }
	$situacion=isset($valores['situacion_resultado']) ? $valores['situacion_resultado'] : ($fila ? $fila['situacion_resultado'] : '');
	$observaciones=isset($valores['observaciones']) ? $valores['observaciones'] : ($fila ? $fila['observaciones'] : '');
	$motivo=isset($valores['motivo']) ? $valores['motivo'] : ($fila && isset($fila['motivo']) ? $fila['motivo'] : '');
	$form_id=$modo==='crear' ? 'empresa-seguimiento-nuevo' : 'empresa-seguimiento-editar-'.$id_seguimiento;
	?>
	<form id="<?php echo empresa_seguimiento_h($form_id); ?>" method="post" action="editar-cliente.php?id=<?php echo intval($id_cliente); ?>#seguimiento-empresarial" class="empresa-seguimiento-form">
		<input type="hidden" name="csrf_tarea_extra" value="<?php echo empresa_seguimiento_h($csrf); ?>">
		<input type="hidden" name="empresa_seguimiento_accion" value="<?php echo $modo==='crear' ? 'crear' : 'actualizar'; ?>">
		<?php if($modo!=='crear') { ?><input type="hidden" name="id_seguimiento" value="<?php echo $id_seguimiento; ?>"><?php } ?>
		<div class="empresa-seguimiento-form-grid">
			<?php if($fila && $fila['tipo_seguimiento']==='manual') { ?>
			<input type="hidden" name="tipo_seguimiento" value="manual"><input type="hidden" name="id_atencion_referencia" value="<?php echo intval($fila['id_atencion_referencia']); ?>">
			<div class="form-group"><label>Atención de referencia</label><input class="form-control" value="<?php echo !empty($fila['fecha_referencia']) ? date('d/m/Y',strtotime($fila['fecha_referencia'])) : 'Sin atención de referencia'; ?>" readonly></div>
			<div class="form-group"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-fecha-prevista">Fecha prevista</label><input class="form-control" type="date" id="<?php echo empresa_seguimiento_h($form_id); ?>-fecha-prevista" name="fecha_prevista" value="<?php echo empresa_seguimiento_h(isset($valores['fecha_prevista']) ? $valores['fecha_prevista'] : $fila['fecha_prevista']); ?>" required></div>
			<div class="form-group empresa-seguimiento-form-wide"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-motivo">Motivo</label><input class="form-control" maxlength="255" id="<?php echo empresa_seguimiento_h($form_id); ?>-motivo" name="motivo" value="<?php echo empresa_seguimiento_h($motivo); ?>" required></div>
			<?php } else { ?>
			<div class="form-group"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-atencion">Atención de referencia</label>
				<select class="form-control" id="<?php echo empresa_seguimiento_h($form_id); ?>-atencion" name="id_atencion_referencia" required>
					<option value="">Seleccionar atención</option>
					<?php foreach($atenciones as $at) { $id_at=intval($at['id_atencion']); $f=isset($at['fecha_referencia']) ? $at['fecha_referencia'] : ''; $etiqueta=date('d/m/Y', strtotime($f)).' · '.(isset($at['area_nombre']) ? $at['area_nombre'] : 'Área'). ' · '.(isset($at['estado']) ? $at['estado'] : ''); $id_resp_sugerido=isset($at['id_empleado']) && isset($empleados_ids[intval($at['id_empleado'])]) ? intval($at['id_empleado']) : 0; ?>
					<option value="<?php echo $id_at; ?>" data-responsable="<?php echo $id_resp_sugerido; ?>" <?php echo $id_at===$id_atencion ? 'selected' : ''; ?>><?php echo empresa_seguimiento_h($etiqueta); ?></option>
					<?php } ?>
				</select><small class="help-block">La fecha de referencia se toma de la atención seleccionada.</small>
			</div>
			<div class="form-group"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-tipo">Hito</label><select class="form-control" id="<?php echo empresa_seguimiento_h($form_id); ?>-tipo" name="tipo_seguimiento" required><option value="3_meses" <?php echo $tipo==='3_meses' ? 'selected' : ''; ?>>3 meses</option><option value="6_meses" <?php echo $tipo==='6_meses' ? 'selected' : ''; ?>>6 meses</option></select></div>
			<?php } ?>
			<div class="form-group"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-estado">Estado</label><select class="form-control" id="<?php echo empresa_seguimiento_h($form_id); ?>-estado" name="estado"><option value="pendiente" <?php echo $estado==='pendiente' ? 'selected' : ''; ?>>Pendiente</option><option value="realizado" <?php echo $estado==='realizado' ? 'selected' : ''; ?>>Realizado</option></select></div>
			<div class="form-group"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-responsable">Responsable</label><select class="form-control" id="<?php echo empresa_seguimiento_h($form_id); ?>-responsable" name="id_empleado_responsable" <?php echo $fila && $fila['tipo_seguimiento']==='manual' ? 'required' : ''; ?>><option value=""><?php echo $fila && $fila['tipo_seguimiento']==='manual' ? 'Seleccionar responsable' : 'Sin asignar'; ?></option><?php if($responsable_preexistente_no_activo) { ?><option value="<?php echo $id_responsable; ?>" selected><?php echo empresa_seguimiento_h(trim((string)$fila['responsable_nombre'])!=='' ? trim($fila['responsable_nombre']).' (asignación existente)' : 'Responsable existente'); ?></option><?php } ?><?php foreach($empleados as $empleado) { ?><option value="<?php echo intval($empleado['id']); ?>" <?php echo intval($empleado['id'])===$id_responsable ? 'selected' : ''; ?>><?php echo empresa_seguimiento_h($empleado['nombre']); ?></option><?php } ?></select><small class="help-block">Solo personal activo autorizado del área Empresas para nuevas asignaciones.</small></div>
			<div class="form-group"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-fecha">Fecha de realización</label><input class="form-control" type="date" id="<?php echo empresa_seguimiento_h($form_id); ?>-fecha" name="fecha_realizacion" value="<?php echo empresa_seguimiento_h($fecha_realizacion); ?>"><small class="help-block">Obligatoria cuando el estado es Realizado.</small></div>
			<div class="form-group"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-resultado">Clasificación del resultado</label><select class="form-control" id="<?php echo empresa_seguimiento_h($form_id); ?>-resultado" name="resultado_clasificacion"><option value="">Seleccionar</option><option value="positivo" <?php echo $resultado==='positivo' ? 'selected' : ''; ?>>Positivo</option><option value="neutro" <?php echo $resultado==='neutro' ? 'selected' : ''; ?>>Neutro</option><option value="negativo" <?php echo $resultado==='negativo' ? 'selected' : ''; ?>>Negativo</option></select><small class="help-block">Obligatoria al marcar el seguimiento como realizado.</small></div>
			<div class="form-group empresa-seguimiento-form-wide"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-situacion">Situación o resultados</label><textarea class="form-control" id="<?php echo empresa_seguimiento_h($form_id); ?>-situacion" name="situacion_resultado" rows="2"><?php echo empresa_seguimiento_h($situacion); ?></textarea></div>
			<div class="form-group empresa-seguimiento-form-wide"><label for="<?php echo empresa_seguimiento_h($form_id); ?>-observaciones">Observaciones</label><textarea class="form-control" id="<?php echo empresa_seguimiento_h($form_id); ?>-observaciones" name="observaciones" rows="2"><?php echo empresa_seguimiento_h($observaciones); ?></textarea></div>
		</div>
		<button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> <?php echo $modo==='crear' ? 'Guardar seguimiento' : 'Guardar cambios'; ?></button>
		<?php if($modo!=='crear') { ?><button type="button" class="btn btn-white btn-sm" data-dismiss="modal">Cancelar</button><?php } ?>
	</form>
	<?php
}

function empresa_seguimiento_render_formulario_manual($empresas, $id_cliente, $atenciones, $csrf, $token_manual, $accion_url, $valores=array(), $empresa_fija=null)
{
	$id_cliente=intval($id_cliente);
	$id_atencion=isset($valores['id_atencion_referencia']) ? intval($valores['id_atencion_referencia']) : 0;
	$id_responsable=isset($valores['id_empleado_responsable']) ? intval($valores['id_empleado_responsable']) : 0;
	$modo_fecha=isset($valores['modo_fecha']) ? (string)$valores['modo_fecha'] : 'fecha';
	$fecha_prevista=isset($valores['fecha_prevista']) ? (string)$valores['fecha_prevista'] : '';
	$plazo=isset($valores['plazo_meses']) ? intval($valores['plazo_meses']) : 3;
	$motivo=isset($valores['motivo']) ? (string)$valores['motivo'] : '';
	$observaciones=isset($valores['observaciones']) ? (string)$valores['observaciones'] : '';
	$empleados=empresa_seguimiento_empleados_autorizados();
	$empleados_habilitados=array(); foreach($empleados as $empleado) { $empleados_habilitados[intval($empleado['id'])]=true; }
	$token_formulario=isset($valores['empresa_seguimiento_token_manual']) && empresa_seguimiento_token_manual_valido($valores['empresa_seguimiento_token_manual']) ? $valores['empresa_seguimiento_token_manual'] : $token_manual;
	?>
	<div class="modal fade empresa-seguimiento-modal" id="modal-programar-seguimiento-manual" tabindex="-1" role="dialog" aria-labelledby="modal-programar-seguimiento-manual-titulo" aria-hidden="true" <?php echo !empty($valores['_error']) ? 'data-open-on-load="1"' : ''; ?>><div class="modal-dialog" role="document"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="modal-programar-seguimiento-manual-titulo">Programar nuevo seguimiento</h4></div>
		<form method="post" action="<?php echo empresa_seguimiento_h($accion_url); ?>" class="empresa-seguimiento-form">
			<input type="hidden" name="csrf_tarea_extra" value="<?php echo empresa_seguimiento_h($csrf); ?>"><input type="hidden" name="empresa_seguimiento_token_manual" value="<?php echo empresa_seguimiento_h($token_formulario); ?>"><input type="hidden" name="empresa_seguimiento_accion" value="crear_manual">
			<?php if($empresa_fija) { ?><input type="hidden" name="id_cliente" value="<?php echo $id_cliente; ?>"><?php } ?>
			<div class="modal-body">
				<?php if(!empty($valores['_error'])) { ?><div class="alert alert-danger" role="alert"><?php echo empresa_seguimiento_h($valores['_error']); ?></div><?php } ?>
				<?php if($empresa_fija) { ?><div class="form-group"><label>Empresa</label><input class="form-control" value="<?php echo empresa_seguimiento_h($empresa_fija['nombre']); ?>" readonly></div><?php } else { ?>
				<div class="form-group"><label for="manual-seguimiento-empresa">Empresa</label><select class="form-control" id="manual-seguimiento-empresa" name="id_cliente" required><option value="">Seleccionar empresa</option><?php foreach($empresas as $empresa) { ?><option value="<?php echo intval($empresa['id_cliente']); ?>" <?php echo intval(isset($valores['id_cliente'])?$valores['id_cliente']:0)===intval($empresa['id_cliente'])?'selected':''; ?>><?php echo empresa_seguimiento_h($empresa['nombre']); ?></option><?php } ?></select></div><?php } ?>
				<div class="form-group"><label for="manual-seguimiento-atencion">Atención de referencia</label><select class="form-control" id="manual-seguimiento-atencion" name="id_atencion_referencia"><option value="" <?php echo $id_atencion<=0?'selected':''; ?>>Sin atención de referencia</option><?php foreach($atenciones as $atencion) { $id_at=intval($atencion['id_atencion']); $owner=intval(isset($atencion['id_cliente'])?$atencion['id_cliente']:$id_cliente); $id_sugerido=isset($atencion['id_empleado']) && isset($empleados_habilitados[intval($atencion['id_empleado'])]) ? intval($atencion['id_empleado']) : 0; $etiqueta=date('d/m/Y',strtotime($atencion['fecha_referencia'])).' · '.(isset($atencion['area_nombre'])?$atencion['area_nombre']:'Área'); ?><option data-empresa="<?php echo $owner; ?>" data-responsable="<?php echo $id_sugerido; ?>" value="<?php echo $id_at; ?>" <?php echo $id_at===$id_atencion?'selected':''; ?>><?php echo empresa_seguimiento_h($etiqueta); ?></option><?php } ?></select></div>
				<div class="form-group"><label for="manual-seguimiento-modo-fecha">Programar por</label><select class="form-control" id="manual-seguimiento-modo-fecha" name="modo_fecha"><option value="fecha" <?php echo $modo_fecha==='fecha'?'selected':''; ?>>Fecha concreta</option><option value="plazo" <?php echo $modo_fecha==='plazo'?'selected':''; ?>>Plazo</option></select></div>
				<div class="form-group" data-manual-fecha-concreta><label for="manual-seguimiento-fecha">Fecha prevista</label><input class="form-control" type="date" id="manual-seguimiento-fecha" name="fecha_prevista" value="<?php echo empresa_seguimiento_h($fecha_prevista); ?>"></div>
				<div class="form-group" data-manual-plazo><label for="manual-seguimiento-plazo">Plazo</label><select class="form-control" id="manual-seguimiento-plazo" name="plazo_meses"><option value="1" <?php echo $plazo===1?'selected':''; ?>>1 mes</option><option value="3" <?php echo $plazo===3?'selected':''; ?>>3 meses</option><option value="6" <?php echo $plazo===6?'selected':''; ?>>6 meses</option></select><small class="help-block">El sistema calcula la fecha desde hoy.</small></div>
				<div class="form-group"><label for="manual-seguimiento-responsable">Responsable</label><select class="form-control" id="manual-seguimiento-responsable" name="id_empleado_responsable" required data-seleccion-manual="<?php echo isset($valores['id_empleado_responsable'])?'1':'0'; ?>"><option value="">Seleccionar responsable</option><?php foreach($empleados as $empleado) { ?><option value="<?php echo intval($empleado['id']); ?>" <?php echo intval($empleado['id'])===$id_responsable?'selected':''; ?>><?php echo empresa_seguimiento_h($empleado['nombre']); ?></option><?php } ?></select></div>
				<div class="form-group"><label for="manual-seguimiento-motivo">Motivo del seguimiento</label><input class="form-control" id="manual-seguimiento-motivo" name="motivo" maxlength="255" value="<?php echo empresa_seguimiento_h($motivo); ?>" required></div>
				<div class="form-group"><label for="manual-seguimiento-observaciones">Observaciones (opcional)</label><textarea class="form-control" id="manual-seguimiento-observaciones" name="observaciones" rows="3"><?php echo empresa_seguimiento_h($observaciones); ?></textarea></div>
			</div><div class="modal-footer"><button type="button" class="btn btn-white" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fa fa-calendar-plus-o"></i> Programar seguimiento</button></div>
		</form>
	</div></div></div>
	<script>(function(){var empresa=document.getElementById('manual-seguimiento-empresa'),atencion=document.getElementById('manual-seguimiento-atencion'),modo=document.getElementById('manual-seguimiento-modo-fecha'),fecha=document.getElementById('manual-seguimiento-fecha'),plazo=document.getElementById('manual-seguimiento-plazo'),responsable=document.getElementById('manual-seguimiento-responsable');if(!atencion){return;}var filtrarAtenciones=function(){var cliente=empresa?empresa.value:'<?php echo $id_cliente; ?>';var seleccionada=atencion.value;for(var i=0;i<atencion.options.length;i++){var opcion=atencion.options[i];if(!opcion.value){continue;}var visible=!cliente||opcion.getAttribute('data-empresa')===cliente;opcion.hidden=!visible;opcion.disabled=!visible;if(!visible&&opcion.value===seleccionada){atencion.value='';}}};var actualizarFecha=function(){if(!modo||!fecha||!plazo){return;}var porFecha=modo.value==='fecha';var fechaBox=fecha.parentNode;var plazoBox=plazo.parentNode;if(fechaBox){fechaBox.style.display=porFecha?'':'none';}if(plazoBox){plazoBox.style.display=porFecha?'none':'';}fecha.required=porFecha;plazo.required=!porFecha;};var actualizarResponsable=function(){if(!responsable||responsable.getAttribute('data-seleccion-manual')==='1'){return;}var opcion=atencion.options[atencion.selectedIndex];responsable.value=opcion&&opcion.getAttribute('data-responsable')?opcion.getAttribute('data-responsable'):'';};if(empresa){empresa.addEventListener('change',filtrarAtenciones);filtrarAtenciones();}if(modo){modo.addEventListener('change',actualizarFecha);actualizarFecha();}atencion.addEventListener('change',actualizarResponsable);actualizarResponsable();if(responsable){responsable.addEventListener('change',function(){responsable.setAttribute('data-seleccion-manual','1');});}})();</script>
	<?php
}

function empresa_seguimiento_render($id_cliente, $cliente, $area, $puede_gestionar, $post_resultado=array(), $solo_lectura=false)
{
	if(!empresa_seguimiento_puede_ver($id_cliente, $area))
	{
		return;
	}
	$id_cliente=intval($id_cliente);
	$puede_gestionar=(bool)$puede_gestionar && !$solo_lectura && empresa_seguimiento_puede_editar($id_cliente, $area);
	if(!function_exists('acceso_tabla_existe') || !acceso_tabla_existe('empresa_seguimiento'))
	{
		?>
		<section class="grid simple empresa-seguimiento-wrap" id="seguimiento-empresarial">
			<div class="grid-title"><h4><i class="fa fa-line-chart text-primary"></i> Seguimiento empresarial</h4></div>
			<div class="grid-body"><div class="alert alert-info">La estructura del seguimiento empresarial todavía no está habilitada en esta base de pruebas.</div></div>
		</section>
		<?php
		return;
	}
	?>
	<style>
	.empresa-seguimiento-wrap{margin:18px 0;scroll-margin-top:90px}.empresa-seguimiento-hint{color:#64748b;margin:0 0 12px}.empresa-seguimiento-form-box{padding:14px;margin:0 0 14px;border:1px solid #dbe4ef;border-radius:6px;background:#f8fafc}.empresa-seguimiento-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 14px}.empresa-seguimiento-form-grid .form-group{min-width:0}.empresa-seguimiento-form-wide{grid-column:1/-1}.empresa-seguimiento-table{width:100%}.empresa-seguimiento-table td,.empresa-seguimiento-table th{vertical-align:top!important}.empresa-seguimiento-badge{display:inline-block;padding:4px 8px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap}.empresa-seguimiento-pendiente{background:#dbeafe;color:#1d4ed8}.empresa-seguimiento-realizado{background:#dcfce7;color:#166534}.empresa-seguimiento-vencido{background:#fee2e2;color:#b91c1c}.empresa-seguimiento-edit-button{white-space:nowrap}.empresa-seguimiento-manual-cta{margin-top:12px}.empresa-seguimiento-modal .modal-body{max-height:calc(100vh - 220px);overflow-y:auto}.empresa-seguimiento-modal .empresa-seguimiento-form{margin:0}.empresa-seguimiento-modal .empresa-seguimiento-form .btn{margin-right:6px}
	@media(max-width:767px){.empresa-seguimiento-form-grid{grid-template-columns:1fr}.empresa-seguimiento-form-wide{grid-column:auto}.empresa-seguimiento-table-wrap{overflow-x:auto}.empresa-seguimiento-modal .modal-dialog{margin:10px}.empresa-seguimiento-modal .modal-body{max-height:calc(100vh - 150px);padding:15px}}
	</style>
	<section class="grid simple empresa-seguimiento-wrap" id="seguimiento-empresarial">
		<div class="grid-title"><h4><i class="fa fa-line-chart text-primary"></i> Seguimiento empresarial</h4></div>
		<div class="grid-body">
			<p class="empresa-seguimiento-hint">Registrá la evolución de la empresa a los 3 y 6 meses de una atención de referencia.</p>
			<?php
			$post_edicion_id=isset($post_resultado['accion'],$post_resultado['valores']['id_seguimiento']) && $post_resultado['accion']==='actualizar' ? intval($post_resultado['valores']['id_seguimiento']) : 0;
			$post_edicion_en_lista=false;
			?>
			<?php if($puede_gestionar) {
				$atenciones=empresa_seguimiento_lista_atenciones($id_cliente);
				$csrf=function_exists('crm_tarea_extra_csrf_token') ? crm_tarea_extra_csrf_token() : '';
				$manual_error=isset($post_resultado['accion']) && $post_resultado['accion']==='crear_manual' && !empty($post_resultado['error']);
				$manual_values=$manual_error && isset($post_resultado['valores']) && is_array($post_resultado['valores']) ? $post_resultado['valores'] : array();
				if($manual_error) { $manual_values['_error']=$post_resultado['error']; }
				$manual_token=isset($manual_values['empresa_seguimiento_token_manual']) && empresa_seguimiento_token_manual_valido($manual_values['empresa_seguimiento_token_manual']) ? $manual_values['empresa_seguimiento_token_manual'] : empresa_seguimiento_token_manual();
			?>
			<div class="empresa-seguimiento-form-box">
				<h5 style="margin-top:0;">Registrar seguimiento</h5>
				<?php if(count($atenciones)===0) { ?><p class="muted">Esta empresa todavía no tiene atenciones disponibles como referencia.</p><?php } else { empresa_seguimiento_render_formulario($id_cliente,$atenciones,$csrf,'crear',null,isset($post_resultado['accion']) && $post_resultado['accion']==='crear' ? $post_resultado['valores'] : array()); } ?>
				<div class="empresa-seguimiento-manual-cta"><button type="button" class="btn btn-white" data-toggle="modal" data-target="#modal-programar-seguimiento-manual"><i class="fa fa-calendar-plus-o"></i> Programar nuevo seguimiento</button></div>
			</div>
			<?php
				$empresa_manual=isset($cliente['nombre']) ? $cliente : array('nombre'=>'Empresa');
				$empresa_manual['id_cliente']=$id_cliente;
				empresa_seguimiento_render_formulario_manual(array($empresa_manual),$id_cliente,$atenciones,$csrf,$manual_token,'editar-cliente.php?id='.$id_cliente.'#seguimiento-empresarial',$manual_values,$empresa_manual);
			?>
			<?php } ?>
			<?php $seguimientos=empresa_seguimiento_lista($id_cliente); $cantidad=is_array($seguimientos) ? count($seguimientos) : 0; ?>
			<?php if(isset($post_resultado['error']) && $post_resultado['error']!=='' && (!isset($post_resultado['accion']) || $post_resultado['accion']!=='crear_manual') && ($post_edicion_id<=0 || !is_array($seguimientos))) { ?><div class="alert alert-danger" role="alert"><?php echo empresa_seguimiento_h($post_resultado['error']); ?></div><?php } ?>
			<?php if($cantidad===0) { ?><p class="muted">Todavía no hay seguimientos empresariales registrados.</p><?php } else { ?>
			<div class="empresa-seguimiento-table-wrap"><table class="table table-hover empresa-seguimiento-table"><thead><tr><th>Hito</th><th>Atención de referencia</th><th>Fecha prevista</th><th>Realización</th><th>Responsable</th><th>Estado</th><th>Resultado</th><th>Situación / observaciones</th><?php if($puede_gestionar) { ?><th>Acciones</th><?php } ?></tr></thead><tbody>
			<?php foreach($seguimientos as $seg) { $estado=empresa_seguimiento_estado_visible($seg['estado'],$seg['fecha_prevista'],$seg['fecha_realizacion']); $etiqueta=$estado==='realizado' ? 'Realizado' : ($estado==='vencido' ? 'Vencido' : 'Pendiente'); $badge='empresa-seguimiento-'.$estado; $id_modal='empresa-seguimiento-editar-modal-'.intval($seg['id_seguimiento']); $error_de_esta_edicion=$post_edicion_id===intval($seg['id_seguimiento']) && isset($post_resultado['error']) && $post_resultado['error']!==''; if($error_de_esta_edicion) { $post_edicion_en_lista=true; } ?>
			<tr><td><?php echo empresa_seguimiento_h(empresa_seguimiento_tipo_etiqueta($seg['tipo_seguimiento'])); ?><?php if($seg['tipo_seguimiento']==='manual' && trim((string)$seg['motivo'])!=='') { ?><div class="muted"><?php echo empresa_seguimiento_h($seg['motivo']); ?></div><?php } ?></td><td><?php echo !empty($seg['fecha_referencia']) ? date('d/m/Y',strtotime($seg['fecha_referencia'])) : 'Sin atención de referencia'; ?></td><td><?php echo date('d/m/Y',strtotime($seg['fecha_prevista'])); ?></td><td><?php echo !empty($seg['fecha_realizacion']) ? date('d/m/Y',strtotime($seg['fecha_realizacion'])) : '—'; ?></td><td><?php echo trim((string)$seg['responsable_nombre'])!=='' ? empresa_seguimiento_h(trim($seg['responsable_nombre'])) : 'No asignado'; ?></td><td><span class="empresa-seguimiento-badge <?php echo $badge; ?>"><?php echo $etiqueta; ?></span></td><td><?php echo empresa_seguimiento_h(empresa_seguimiento_resultado_etiqueta(isset($seg['resultado_clasificacion']) ? $seg['resultado_clasificacion'] : '')) ?: '—'; ?></td><td><?php echo trim((string)$seg['situacion_resultado'])!=='' ? nl2br(empresa_seguimiento_h($seg['situacion_resultado'])) : ''; ?><?php if(trim((string)$seg['observaciones'])!=='') { ?><div class="muted"><strong>Observaciones:</strong> <?php echo nl2br(empresa_seguimiento_h($seg['observaciones'])); ?></div><?php } ?></td>
			<?php if($puede_gestionar) { ?><td><button type="button" class="btn btn-white btn-sm empresa-seguimiento-edit-button" data-toggle="modal" data-target="#<?php echo empresa_seguimiento_h($id_modal); ?>">Editar</button></td><?php } ?></tr>
			<?php } ?>
			</tbody></table></div>
			<?php } ?>
			<?php if($puede_gestionar && $cantidad>0) { foreach($seguimientos as $seg) { $id_seg=intval($seg['id_seguimiento']); $id_modal='empresa-seguimiento-editar-modal-'.$id_seg; $error_de_esta_edicion=$post_edicion_id===$id_seg && isset($post_resultado['error']) && $post_resultado['error']!==''; ?>
			<div class="modal fade empresa-seguimiento-modal" id="<?php echo empresa_seguimiento_h($id_modal); ?>" tabindex="-1" role="dialog" aria-labelledby="<?php echo empresa_seguimiento_h($id_modal); ?>-titulo" aria-hidden="true" <?php echo $error_de_esta_edicion ? 'data-open-on-load="1"' : ''; ?>><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
				<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="<?php echo empresa_seguimiento_h($id_modal); ?>-titulo">Editar seguimiento</h4></div>
				<div class="modal-body"><?php if($error_de_esta_edicion) { ?><div class="alert alert-danger" role="alert"><?php echo empresa_seguimiento_h($post_resultado['error']); ?></div><?php } ?><?php empresa_seguimiento_render_formulario($id_cliente,$atenciones,$csrf,'editar',$seg,$error_de_esta_edicion ? $post_resultado['valores'] : array()); ?></div>
			</div></div></div>
			<?php } } ?>
			<?php if($post_edicion_id>0 && !$post_edicion_en_lista && isset($post_resultado['error']) && $post_resultado['error']!=='') { ?><div class="alert alert-danger" role="alert"><?php echo empresa_seguimiento_h($post_resultado['error']); ?></div><?php } ?>
		</div>
	</section>
	<?php
	$id_seguimiento_nav=isset($_GET['seguimiento_id']) && is_string($_GET['seguimiento_id']) && ctype_digit($_GET['seguimiento_id']) ? intval($_GET['seguimiento_id']) : 0;
	?>
	<script>(function(){var atencion=document.getElementById('empresa-seguimiento-nuevo-atencion');var responsable=document.getElementById('empresa-seguimiento-nuevo-responsable');if(!atencion||!responsable){return;}atencion.addEventListener('change',function(){if(responsable.getAttribute('data-seleccion-manual')==='1'){return;}var opcion=atencion.options[atencion.selectedIndex];responsable.value=opcion&&opcion.getAttribute('data-responsable')?opcion.getAttribute('data-responsable'):'';});responsable.addEventListener('change',function(){responsable.setAttribute('data-seleccion-manual','1');});})();</script>
	<?php
	if($id_seguimiento_nav>0 && acceso_consulta_una("SELECT id_seguimiento FROM empresa_seguimiento WHERE id_seguimiento=? AND id_cliente=? LIMIT 1", 'ii', array($id_seguimiento_nav, $id_cliente))) { ?>
	<script>(function(){var ir=function(){var seccion=document.getElementById('seguimiento-empresarial');if(seccion){seccion.scrollIntoView({block:'start',behavior:'smooth'});}};if(document.readyState==='complete'){ir();}else{window.addEventListener('load',ir);}})();</script>
	<?php } ?>
	<?php if(isset($manual_error) && $manual_error) { ?>
	<script>(function(){var abrir=function(){if(window.jQuery&&jQuery.fn&&jQuery.fn.modal){jQuery('#modal-programar-seguimiento-manual').modal('show');}};if(document.readyState==='complete'){abrir();}else{window.addEventListener('load',abrir);}})();</script>
	<?php } ?>
	<?php if($post_edicion_en_lista) { $id_modal_error='empresa-seguimiento-editar-modal-'.$post_edicion_id; ?>
	<script>(function(){var abrir=function(){if(window.jQuery && jQuery.fn && jQuery.fn.modal){jQuery('#<?php echo empresa_seguimiento_h($id_modal_error); ?>').modal('show');}};if(document.readyState==='complete'){abrir();}else{window.addEventListener('load',abrir);}})();</script>
	<?php } ?>
	<?php
}
