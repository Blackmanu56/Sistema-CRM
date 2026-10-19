<?php
/** Private employee-document storage and persistence helpers. */

function crm_rrhh_legajo_categorias()
{
	return array(
		'monotributo'=>'Monotributo',
		'ausencias_faltas'=>'Ausencias y faltas',
		'certificados_medicos'=>'Certificados médicos',
		'contratos_laborales'=>'Contratos y documentación laboral',
		'cursos_capacitaciones'=>'Cursos y capacitaciones',
		'otros'=>'Otros documentos'
	);
}

function crm_rrhh_legajo_tabla_disponible()
{
	global $con;
	static $disponible=null;
	if($disponible!==null) { return $disponible; }
	$disponible=false;
	if(!$con) { return false; }
	$stmt=$con->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='empleado_documento_legajo'");
	if(!$stmt || !$stmt->execute())
	{
		if($stmt) { $stmt->close(); }
		return false;
	}
	$stmt->bind_result($cantidad);
	if($stmt->fetch()) { $disponible=(intval($cantidad)===1); }
	$stmt->close();
	return $disponible;
}

function crm_rrhh_legajo_puede_gestionar($id_empleado)
{
	$id_empleado=intval($id_empleado);
	if($id_empleado<=0 || !function_exists('acceso_puede_gestionar') || !acceso_puede_gestionar('rrhh') || !function_exists('acceso_empleado_por_id')) { return false; }
	$empleado=acceso_empleado_por_id($id_empleado);
	return $empleado && isset($empleado['rol']) && $empleado['rol']!=='superadmin' && !empty($empleado['activo']);
}

function crm_rrhh_legajo_storage_dir($crear=false)
{
	$document_root=isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
	$override=getenv('CRM_EMPLOYEE_DOCUMENT_DIR');
	if(is_string($override) && trim($override)!=='')
	{
		$directorio=trim($override);
	}
	else
	{
		$base=defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
		$raiz_privada=$document_root ? dirname($document_root) : dirname($base, 2);
		$directorio=$raiz_privada.DIRECTORY_SEPARATOR.'crm_private_data'.DIRECTORY_SEPARATOR.'employee_documents';
	}
	if($crear && !is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) { return ''; }
	$real=realpath($directorio);
	if($real===false || !is_dir($real)) { return ''; }
	if($document_root)
	{
		$doc=strtolower(rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $document_root), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
		$dir=strtolower(rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $real), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
		if(strpos($dir, $doc)===0) { return ''; }
	}
	return $real;
}

function crm_rrhh_legajo_ruta_por_key($key)
{
	$key=is_string($key) ? $key : '';
	if(!preg_match('/\A[a-f0-9]{32}\.pdf\z/', $key)) { return ''; }
	$directorio=crm_rrhh_legajo_storage_dir(false);
	if($directorio==='') { return ''; }
	$ruta=realpath($directorio.DIRECTORY_SEPARATOR.$key);
	$prefijo=strtolower(rtrim($directorio, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
	if($ruta===false || strpos(strtolower($ruta), $prefijo)!==0 || !is_file($ruta)) { return ''; }
	return $ruta;
}

function crm_rrhh_legajo_validar_pdf($archivo)
{
	if(!is_array($archivo) || !isset($archivo['error']) || intval($archivo['error'])!==UPLOAD_ERR_OK || empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name']))
	{
		return array(false, '', 'No se recibió un PDF válido.');
	}
	$tamano=@filesize($archivo['tmp_name']);
	if($tamano===false || $tamano<=0 || $tamano>10*1024*1024) { return array(false, '', 'El documento debe pesar como máximo 10 MB.'); }
	$nombre=isset($archivo['name']) && is_string($archivo['name']) ? str_replace('\\', '/', $archivo['name']) : '';
	$nombre=basename($nombre);
	$nombre=preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre);
	$nombre=trim($nombre);
	if($nombre==='' || strtolower(pathinfo($nombre, PATHINFO_EXTENSION))!=='pdf') { return array(false, '', 'Solo se permiten archivos con extensión PDF.'); }
	if(function_exists('mb_substr')) { $nombre=mb_substr($nombre, 0, 255, 'UTF-8'); }
	else { $nombre=substr($nombre, 0, 255); }
	if(!function_exists('finfo_open')) { return array(false, '', 'No está disponible la validación segura de archivos en este servidor.'); }
	$finfo=finfo_open(FILEINFO_MIME_TYPE);
	$mime=$finfo ? finfo_file($finfo, $archivo['tmp_name']) : '';
	if($finfo) { finfo_close($finfo); }
	$handle=@fopen($archivo['tmp_name'], 'rb');
	$encabezado=$handle ? fread($handle, 5) : '';
	if($handle) { fclose($handle); }
	if($mime!=='application/pdf' || $encabezado!=='%PDF-') { return array(false, '', 'El contenido del archivo no corresponde a un PDF válido.'); }
	return array(true, $nombre, '');
}

function crm_rrhh_legajo_listar($id_empleado)
{
	global $con;
	$id_empleado=intval($id_empleado);
	if(!$con || !crm_rrhh_legajo_tabla_disponible() || !crm_rrhh_legajo_puede_gestionar($id_empleado)) { return false; }
	$stmt=$con->prepare('SELECT id_documento, categoria, nombre_original, descripcion, fecha_carga, id_usuario_carga FROM empleado_documento_legajo WHERE id_empleado=? ORDER BY fecha_carga DESC, id_documento DESC');
	if(!$stmt) { return false; }
	$stmt->bind_param('i', $id_empleado);
	if(!$stmt->execute()) { $stmt->close(); return false; }
	$resultado=$stmt->get_result();
	$documentos=$resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : array();
	$stmt->close();
	return $documentos;
}

function crm_rrhh_legajo_documento_por_id($id_documento)
{
	global $con;
	$id_documento=intval($id_documento);
	if(!$con || !crm_rrhh_legajo_tabla_disponible() || $id_documento<=0) { return null; }
	$stmt=$con->prepare('SELECT id_documento, id_empleado, categoria, nombre_original, archivo_key, descripcion, fecha_carga, id_usuario_carga FROM empleado_documento_legajo WHERE id_documento=? LIMIT 1');
	if(!$stmt) { return false; }
	$stmt->bind_param('i', $id_documento);
	if(!$stmt->execute()) { $stmt->close(); return false; }
	$resultado=$stmt->get_result();
	$documento=$resultado ? $resultado->fetch_assoc() : null;
	$stmt->close();
	return $documento ? $documento : null;
}

function crm_rrhh_legajo_agregar($id_empleado, $categoria, $descripcion, $archivo)
{
	global $con;
	$id_empleado=intval($id_empleado);
	$categorias=crm_rrhh_legajo_categorias();
	if(!crm_rrhh_legajo_puede_gestionar($id_empleado)) { return array(false, 'No tenés autorización para gestionar este legajo.'); }
	if(!crm_rrhh_legajo_tabla_disponible()) { return array(false, 'El legajo digital quedará habilitado cuando se aplique la migración pendiente.'); }
	if(!isset($categorias[$categoria])) { return array(false, 'Seleccioná una categoría válida.'); }
	$descripcion=is_string($descripcion) ? trim($descripcion) : '';
	if(function_exists('mb_strlen') && mb_strlen($descripcion, 'UTF-8')>1000) { return array(false, 'La descripción no puede superar los 1000 caracteres.'); }
	if(!function_exists('mb_strlen') && strlen($descripcion)>4000) { return array(false, 'La descripción es demasiado extensa.'); }
	$validacion=crm_rrhh_legajo_validar_pdf($archivo);
	if(!$validacion[0]) { return array(false, $validacion[2]); }
	$usuario_carga=function_exists('crm_id_empleado_sesion') ? intval(crm_id_empleado_sesion()) : 0;
	if($usuario_carga<=0) { return array(false, 'No se pudo identificar al usuario responsable de la carga.'); }
	$directorio=crm_rrhh_legajo_storage_dir(true);
	if($directorio==='') { return array(false, 'No se pudo acceder al almacenamiento privado del legajo.'); }
	try { $base=bin2hex(random_bytes(16)); }
	catch(Exception $e) { return array(false, 'No se pudo generar un nombre interno seguro.'); }
	$key=$base.'.pdf';
	$temporal=$directorio.DIRECTORY_SEPARATOR.'.'.$base.'.tmp';
	$destino=$directorio.DIRECTORY_SEPARATOR.$key;
	if(!move_uploaded_file($archivo['tmp_name'], $temporal)) { return array(false, 'No se pudo guardar el documento.'); }
	@chmod($temporal, 0600);
	if(!@rename($temporal, $destino)) { @unlink($temporal); return array(false, 'No se pudo finalizar el guardado privado del documento.'); }
	@chmod($destino, 0600);
	$nombre_original=$validacion[1];
	$descripcion_db=$descripcion!=='' ? $descripcion : null;
	$stmt=$con ? $con->prepare('INSERT INTO empleado_documento_legajo (id_empleado, categoria, nombre_original, archivo_key, descripcion, id_usuario_carga) VALUES (?, ?, ?, ?, ?, ?)') : false;
	if(!$stmt)
	{
		@unlink($destino);
		return array(false, 'No se pudo registrar el documento en el legajo. El archivo temporal fue retirado.');
	}
	$stmt->bind_param('issssi', $id_empleado, $categoria, $nombre_original, $key, $descripcion_db, $usuario_carga);
	$ok=$stmt->execute() && $stmt->affected_rows===1;
	$stmt->close();
	if(!$ok)
	{
		@unlink($destino);
		return array(false, 'No se pudo registrar el documento en el legajo.');
	}
	return array(true, 'Documento agregado al legajo.');
}

function crm_rrhh_legajo_eliminar($id_empleado, $id_documento)
{
	global $con;
	$id_empleado=intval($id_empleado);
	$id_documento=intval($id_documento);
	if(!$con || !crm_rrhh_legajo_puede_gestionar($id_empleado)) { return array(false, 'No tenés autorización para gestionar este legajo.'); }
	if(!crm_rrhh_legajo_tabla_disponible()) { return array(false, 'El legajo digital quedará habilitado cuando se aplique la migración pendiente.'); }
	if($id_documento<=0) { return array(false, 'El documento solicitado no es válido.'); }
	if(!$con->begin_transaction()) { return array(false, 'No se pudo iniciar la eliminación del documento.'); }
	$stmt=$con->prepare('SELECT archivo_key FROM empleado_documento_legajo WHERE id_documento=? AND id_empleado=? LIMIT 1 FOR UPDATE');
	if(!$stmt)
	{
		$con->rollback();
		return array(false, 'No se pudo verificar el documento solicitado.');
	}
	$stmt->bind_param('ii', $id_documento, $id_empleado);
	if(!$stmt->execute())
	{
		$stmt->close();
		$con->rollback();
		return array(false, 'No se pudo verificar el documento solicitado.');
	}
	$stmt->bind_result($key);
	$encontrado=$stmt->fetch();
	$stmt->close();
	if(!$encontrado)
	{
		$con->rollback();
		return array(false, 'El documento no existe en este legajo.');
	}
	$stmt=$con->prepare('DELETE FROM empleado_documento_legajo WHERE id_documento=? AND id_empleado=? LIMIT 1');
	if(!$stmt)
	{
		$con->rollback();
		return array(false, 'No se pudo eliminar el documento.');
	}
	$stmt->bind_param('ii', $id_documento, $id_empleado);
	$ok=$stmt->execute() && $stmt->affected_rows===1;
	$stmt->close();
	if(!$ok || !$con->commit())
	{
		$con->rollback();
		return array(false, 'No se pudo confirmar la eliminación. El archivo se conservó.');
	}
	$ruta=crm_rrhh_legajo_ruta_por_key($key);
	if($ruta!=='' && is_file($ruta) && !@unlink($ruta))
	{
		return array(true, 'El registro se eliminó, pero el archivo privado no pudo limpiarse. Revisá el almacenamiento del legajo.');
	}
	return array(true, 'Documento eliminado del legajo.');
}
