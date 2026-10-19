<?php
/** Private employee profile-photo storage and persistence helpers. */

function crm_rrhh_foto_columna_disponible()
{
	static $disponible=null;
	if($disponible===null)
	{
		$disponible=function_exists('crm_columna_existe') && crm_columna_existe('empleado', 'foto_perfil_key');
	}
	return $disponible;
}

function crm_rrhh_foto_autorizado($id_empleado)
{
	$id_empleado=intval($id_empleado);
	if($id_empleado<=0 || !function_exists('crm_id_empleado_sesion')) { return false; }
	if(crm_id_empleado_sesion()===$id_empleado) { return true; }
	if(!function_exists('acceso_puede_gestionar') || !acceso_puede_gestionar('rrhh') || !function_exists('acceso_empleado_por_id')) { return false; }
	$empleado=acceso_empleado_por_id($id_empleado);
	return $empleado && isset($empleado['rol']) && $empleado['rol']!=='superadmin' && !empty($empleado['activo']);
}

function crm_rrhh_foto_key_por_empleado($id_empleado)
{
	global $con;
	$id_empleado=intval($id_empleado);
	if($id_empleado<=0 || !$con || !crm_rrhh_foto_columna_disponible()) { return ''; }
	$stmt=$con->prepare('SELECT foto_perfil_key FROM empleado WHERE id_empleado=? LIMIT 1');
	if(!$stmt) { return ''; }
	$stmt->bind_param('i', $id_empleado);
	if(!$stmt->execute()) { $stmt->close(); return ''; }
	$stmt->bind_result($key);
	$existe=$stmt->fetch();
	$stmt->close();
	return $existe && is_string($key) ? $key : '';
}

function crm_rrhh_foto_storage_dir($crear=false)
{
	$document_root=isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
	$override=getenv('CRM_EMPLOYEE_PHOTO_DIR');
	if(is_string($override) && trim($override)!=='')
	{
		$directorio=trim($override);
	}
	else
	{
		$base=defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
		$raiz_privada=$document_root ? dirname($document_root) : dirname($base, 2);
		$directorio=$raiz_privada.DIRECTORY_SEPARATOR.'crm_private_data'.DIRECTORY_SEPARATOR.'employee_photos';
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

function crm_rrhh_foto_extension_por_mime($mime)
{
	$mapa=array('image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp');
	return isset($mapa[$mime]) ? $mapa[$mime] : '';
}

function crm_rrhh_foto_ruta_por_key($key)
{
	$key=is_string($key) ? $key : '';
	if(!preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $key)) { return ''; }
	$directorio=crm_rrhh_foto_storage_dir(false);
	if($directorio==='') { return ''; }
	$ruta=realpath($directorio.DIRECTORY_SEPARATOR.$key);
	$prefijo=strtolower(rtrim($directorio, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
	if($ruta===false || strpos(strtolower($ruta), $prefijo)!==0 || !is_file($ruta)) { return ''; }
	return $ruta;
}

function crm_rrhh_foto_actualizar_key($id_empleado, $key)
{
	global $con;
	$id_empleado=intval($id_empleado);
	if(!$con || $id_empleado<=0 || !crm_rrhh_foto_columna_disponible()) { return false; }
	$stmt=$con->prepare('UPDATE empleado SET foto_perfil_key=? WHERE id_empleado=? LIMIT 1');
	if(!$stmt) { return false; }
	$stmt->bind_param('si', $key, $id_empleado);
	$ok=$stmt->execute() && $stmt->affected_rows===1;
	$stmt->close();
	return $ok;
}

function crm_rrhh_foto_subir($id_empleado, $archivo)
{
	$id_empleado=intval($id_empleado);
	if(!crm_rrhh_foto_autorizado($id_empleado)) { return array(false, 'No tenés autorización para modificar esta fotografía.'); }
	if(!crm_rrhh_foto_columna_disponible()) { return array(false, 'La gestión de fotografías está pendiente de aplicar la migración de base de datos.'); }
	if(!is_array($archivo) || !isset($archivo['error']) || intval($archivo['error'])!==UPLOAD_ERR_OK || empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name']))
	{
		return array(false, 'No se recibió una imagen válida.');
	}
	$tamano=@filesize($archivo['tmp_name']);
	if($tamano===false || $tamano<=0 || $tamano>5*1024*1024) { return array(false, 'La fotografía debe pesar como máximo 5 MB.'); }
	$finfo=function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
	$mime=$finfo ? finfo_file($finfo, $archivo['tmp_name']) : '';
	if($finfo) { finfo_close($finfo); }
	$imagen=@getimagesize($archivo['tmp_name']);
	$mime_imagen=is_array($imagen) && isset($imagen['mime']) ? $imagen['mime'] : '';
	$extension=crm_rrhh_foto_extension_por_mime($mime);
	if($extension==='' || $mime!==$mime_imagen || !is_array($imagen) || intval($imagen[0])<=0 || intval($imagen[1])<=0 || intval($imagen[0])>6000 || intval($imagen[1])>6000 || intval($imagen[0])*intval($imagen[1])>24000000)
	{
		return array(false, 'El archivo no es una imagen JPG, PNG o WebP válida.');
	}
	$directorio=crm_rrhh_foto_storage_dir(true);
	if($directorio==='') { return array(false, 'No se pudo acceder al almacenamiento privado de fotografías.'); }
	try { $base=bin2hex(random_bytes(16)); }
	catch(Exception $e) { return array(false, 'No se pudo generar un nombre seguro para la fotografía.'); }
	$key_nuevo=$base.'.'.$extension;
	$temporal=$directorio.DIRECTORY_SEPARATOR.'.'.$base.'.tmp';
	$destino=$directorio.DIRECTORY_SEPARATOR.$key_nuevo;
	if(!move_uploaded_file($archivo['tmp_name'], $temporal)) { return array(false, 'No se pudo guardar la fotografía.'); }
	@chmod($temporal, 0600);
	if(!@rename($temporal, $destino)) { @unlink($temporal); return array(false, 'No se pudo finalizar el guardado de la fotografía.'); }
	@chmod($destino, 0600);
	$key_anterior=crm_rrhh_foto_key_por_empleado($id_empleado);
	if(!crm_rrhh_foto_actualizar_key($id_empleado, $key_nuevo))
	{
		@unlink($destino);
		return array(false, 'No se pudo actualizar la ficha. La fotografía anterior se conservó.');
	}
	if($key_anterior!=='' && $key_anterior!==$key_nuevo)
	{
		$ruta_anterior=crm_rrhh_foto_ruta_por_key($key_anterior);
		if($ruta_anterior!=='') { @unlink($ruta_anterior); }
	}
	return array(true, 'Fotografía actualizada.');
}

function crm_rrhh_foto_eliminar($id_empleado)
{
	$id_empleado=intval($id_empleado);
	if(!crm_rrhh_foto_autorizado($id_empleado)) { return array(false, 'No tenés autorización para eliminar esta fotografía.'); }
	if(!crm_rrhh_foto_columna_disponible()) { return array(false, 'La gestión de fotografías está pendiente de aplicar la migración de base de datos.'); }
	$key=crm_rrhh_foto_key_por_empleado($id_empleado);
	if($key==='') { return array(true, 'El empleado ya utiliza el avatar predeterminado.'); }
	if(!crm_rrhh_foto_actualizar_key($id_empleado, null)) { return array(false, 'No se pudo actualizar la ficha. La fotografía se conservó.'); }
	$ruta=crm_rrhh_foto_ruta_por_key($key);
	if($ruta!=='') { @unlink($ruta); }
	return array(true, 'Fotografía eliminada.');
}
