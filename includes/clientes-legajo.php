<?php
/** Private storage and persistence helpers for client digital files. */

function crm_cliente_legajo_categorias()
{
	return array(
		'documentacion_fiscal'=>'Documentación fiscal',
		'constancia'=>'Constancia',
		'formulario'=>'Formulario',
		'solicitud'=>'Solicitud',
		'otros'=>'Otros'
	);
}

function crm_cliente_legajo_tabla_disponible()
{
	global $con;
	static $disponible=null;
	if($disponible!==null) { return $disponible; }
	$disponible=false;
	if(!$con) { return false; }
	$stmt=$con->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='cliente_documento_legajo'");
	if(!$stmt || !$stmt->execute()) { if($stmt) { $stmt->close(); } return false; }
	$stmt->bind_result($cantidad);
	if($stmt->fetch()) { $disponible=(intval($cantidad)===1); }
	$stmt->close();
	return $disponible;
}

function crm_cliente_legajo_storage_dir($crear=false)
{
	$document_root=isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
	$override=getenv('CRM_CLIENT_DOCUMENT_DIR');
	if(is_string($override) && trim($override)!=='') { $directorio=trim($override); }
	else
	{
		$base=defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
		$raiz_privada=$document_root ? dirname($document_root) : dirname($base, 2);
		$directorio=$raiz_privada.DIRECTORY_SEPARATOR.'crm_private_data'.DIRECTORY_SEPARATOR.'client_documents';
	}
	if($crear && !is_dir($directorio) && !@mkdir($directorio, 0700, true) && !is_dir($directorio)) { return ''; }
	$real=realpath($directorio);
	if($real===false || !is_dir($real)) { return ''; }
	if($crear) { @chmod($real, 0700); }
	if($document_root)
	{
		$doc=strtolower(rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $document_root), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
		$dir=strtolower(rtrim(str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $real), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
		if(strpos($dir, $doc)===0) { return ''; }
	}
	return $real;
}

function crm_cliente_legajo_ruta_por_key($key)
{
	$key=is_string($key) ? $key : '';
	if(!preg_match('/\A[a-f0-9]{32}\.(pdf|doc|docx)\z/', $key)) { return ''; }
	$directorio=crm_cliente_legajo_storage_dir(false);
	if($directorio==='') { return ''; }
	$ruta=realpath($directorio.DIRECTORY_SEPARATOR.$key);
	$prefijo=strtolower(rtrim($directorio, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
	if($ruta===false || strpos(strtolower($ruta), $prefijo)!==0 || !is_file($ruta)) { return ''; }
	return $ruta;
}

function crm_cliente_legajo_nombre_seguro($nombre)
{
	$nombre=is_string($nombre) ? str_replace('\\', '/', $nombre) : '';
	$nombre=basename($nombre);
	$nombre=preg_replace('/[\x00-\x1F\x7F]/', '', $nombre);
	if(!is_string($nombre)) { return ''; }
	if(function_exists('mb_check_encoding') && !mb_check_encoding($nombre, 'UTF-8'))
	{
		$nombre=function_exists('iconv') ? @iconv('UTF-8', 'UTF-8//IGNORE', $nombre) : '';
	}
	$nombre=trim($nombre);
	if($nombre==='' || strlen($nombre)>1024) { return ''; }
	if(function_exists('mb_substr')) { return mb_substr($nombre, 0, 255, 'UTF-8'); }
	return substr($nombre, 0, 255);
}

function crm_cliente_legajo_validar_archivo($archivo)
{
	if(!is_array($archivo) || !isset($archivo['error'])) { return array(false, '', '', 0, 'Seleccioná un documento para cargar.'); }
	$error=intval($archivo['error']);
	if($error!==UPLOAD_ERR_OK)
	{
		$mensajes=array(
			UPLOAD_ERR_INI_SIZE=>'El archivo supera el tamaño máximo permitido por el servidor.',
			UPLOAD_ERR_FORM_SIZE=>'El archivo supera el tamaño máximo permitido.',
			UPLOAD_ERR_PARTIAL=>'El archivo se recibió de forma incompleta.',
			UPLOAD_ERR_NO_FILE=>'Seleccioná un documento para cargar.',
			UPLOAD_ERR_NO_TMP_DIR=>'No está disponible la carpeta temporal de carga.',
			UPLOAD_ERR_CANT_WRITE=>'No se pudo guardar temporalmente el archivo.',
			UPLOAD_ERR_EXTENSION=>'La carga fue detenida por una extensión del servidor.'
		);
		return array(false, '', '', 0, isset($mensajes[$error]) ? $mensajes[$error] : 'No se pudo recibir el archivo.');
	}
	if(empty($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) { return array(false, '', '', 0, 'No se recibió un archivo de carga válido.'); }
	$tamano=@filesize($archivo['tmp_name']);
	if($tamano===false || $tamano<=0 || $tamano>15*1024*1024) { return array(false, '', '', 0, 'El documento debe pesar como máximo 15 MB.'); }
	$nombre=crm_cliente_legajo_nombre_seguro(isset($archivo['name']) ? $archivo['name'] : '');
	$extension=strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
	if($nombre==='' || !in_array($extension, array('pdf','doc','docx'), true)) { return array(false, '', '', 0, 'Solo se permiten documentos PDF, DOC o DOCX.'); }
	if(!function_exists('finfo_open')) { return array(false, '', '', 0, 'No está disponible la validación segura de archivos en este servidor.'); }
	$finfo=finfo_open(FILEINFO_MIME_TYPE);
	$mime=$finfo ? finfo_file($finfo, $archivo['tmp_name']) : '';
	if($finfo) { finfo_close($finfo); }
	$handle=@fopen($archivo['tmp_name'], 'rb');
	$header=$handle ? fread($handle, 8) : '';
	if($handle) { fclose($handle); }
	$valido=false;
	if($extension==='pdf')
	{
		$valido=$mime==='application/pdf' && substr($header, 0, 5)==='%PDF-';
		$mime='application/pdf';
	}
	elseif($extension==='doc')
	{
		$ole="\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
		$mimes=array('application/msword','application/x-ole-storage','application/CDFV2','application/vnd.ms-office');
		$word_stream=false;
		if($header===$ole && in_array($mime, array('application/x-ole-storage','application/CDFV2','application/vnd.ms-office'), true))
		{
			$contenido=@file_get_contents($archivo['tmp_name']);
			$word_stream=is_string($contenido) && strpos($contenido, "W\0o\0r\0d\0D\0o\0c\0u\0m\0e\0n\0t\0")!==false;
		}
		$valido=$header===$ole && ($mime==='application/msword' || $word_stream);
		$mime='application/msword';
	}
	else
	{
		$mimes=array('application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/x-zip','application/x-zip-compressed');
		$zip_ok=false;
		if(class_exists('ZipArchive') && in_array($mime, $mimes, true))
		{
			$zip=new ZipArchive();
			if($zip->open($archivo['tmp_name'], ZipArchive::CHECKCONS)===true)
			{
				$types_stream=$zip->getStream('[Content_Types].xml');
				$document_stream=$zip->getStream('word/document.xml');
				$types_xml=$types_stream ? fread($types_stream, 65536) : '';
				$document_xml=$document_stream ? fread($document_stream, 65536) : '';
				if($types_stream) { fclose($types_stream); }
				if($document_stream) { fclose($document_stream); }
				$zip_ok=is_string($types_xml) && is_string($document_xml)
					&& preg_match('/<(?:[A-Za-z0-9_]+:)?Types(?:\s|>)/', $types_xml)===1
					&& preg_match('/<(?:[A-Za-z0-9_]+:)?document(?:\s|>)/', $document_xml)===1;
				$zip->close();
			}
		}
		$valido=$zip_ok && substr($header, 0, 2)==="PK";
		$mime='application/vnd.openxmlformats-officedocument.wordprocessingml.document';
	}
	if(!$valido) { return array(false, '', '', 0, 'El contenido no coincide con un documento PDF, DOC o DOCX válido.'); }
	return array(true, $nombre, $mime, intval($tamano), '');
}

function crm_cliente_legajo_listar($id_cliente)
{
	global $con;
	$id_cliente=intval($id_cliente);
	if(!$con || $id_cliente<=0 || !crm_cliente_legajo_tabla_disponible()) { return false; }
	$sql="SELECT d.id_documento,d.categoria,d.nombre_original,d.archivo_key,d.descripcion,d.fecha_carga,d.mime_type,d.tamano_bytes,e.nombres,e.apellido
		FROM cliente_documento_legajo d LEFT JOIN empleado e ON e.id_empleado=d.id_empleado_carga
		WHERE d.id_cliente=? ORDER BY d.fecha_carga DESC,d.id_documento DESC";
	$stmt=$con->prepare($sql);
	if(!$stmt) { return false; }
	$stmt->bind_param('i', $id_cliente);
	if(!$stmt->execute()) { $stmt->close(); return false; }
	$resultado=$stmt->get_result();
	$documentos=$resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : array();
	$stmt->close();
	return $documentos;
}

function crm_cliente_legajo_documento_por_id($id_documento)
{
	global $con;
	$id_documento=intval($id_documento);
	if(!$con || !crm_cliente_legajo_tabla_disponible() || $id_documento<=0) { return null; }
	$stmt=$con->prepare('SELECT id_documento,id_cliente,categoria,nombre_original,archivo_key,mime_type,tamano_bytes FROM cliente_documento_legajo WHERE id_documento=? LIMIT 1');
	if(!$stmt) { return false; }
	$stmt->bind_param('i', $id_documento);
	if(!$stmt->execute()) { $stmt->close(); return false; }
	$resultado=$stmt->get_result();
	$documento=$resultado ? $resultado->fetch_assoc() : null;
	$stmt->close();
	return $documento ? $documento : null;
}

function crm_cliente_legajo_agregar($id_cliente, $categoria, $descripcion, $archivo)
{
	global $con;
	$id_cliente=intval($id_cliente);
	$categorias=crm_cliente_legajo_categorias();
	if($id_cliente<=0 || !isset($categorias[$categoria])) { return array(false, 'Seleccioná una categoría válida.'); }
	if(!crm_cliente_legajo_tabla_disponible()) { return array(false, 'El legajo digital quedará habilitado cuando se aplique la migración pendiente.'); }
	$descripcion=is_string($descripcion) ? trim($descripcion) : '';
	if($descripcion!=='' && function_exists('mb_check_encoding') && !mb_check_encoding($descripcion, 'UTF-8')) { return array(false, 'La descripción contiene caracteres no válidos.'); }
	if($descripcion!=='' && !function_exists('mb_check_encoding') && @preg_match('//u', $descripcion)!==1) { return array(false, 'La descripción contiene caracteres no válidos.'); }
	if(function_exists('mb_strlen') && mb_strlen($descripcion, 'UTF-8')>1000) { return array(false, 'La descripción no puede superar los 1000 caracteres.'); }
	if(!function_exists('mb_strlen') && strlen($descripcion)>4000) { return array(false, 'La descripción es demasiado extensa.'); }
	$validacion=crm_cliente_legajo_validar_archivo($archivo);
	if(!$validacion[0]) { return array(false, $validacion[4]); }
	$id_carga=function_exists('crm_id_empleado_sesion') ? intval(crm_id_empleado_sesion()) : 0;
	if($id_carga<=0) { return array(false, 'No se pudo identificar al empleado que realiza la carga.'); }
	$directorio=crm_cliente_legajo_storage_dir(true);
	if($directorio==='') { return array(false, 'No se pudo acceder al almacenamiento privado del legajo.'); }
	try { $base=bin2hex(random_bytes(16)); }
	catch(Exception $e) { return array(false, 'No se pudo generar un nombre interno seguro.'); }
	$key=$base.'.'.strtolower(pathinfo($validacion[1], PATHINFO_EXTENSION));
	$temporal=$directorio.DIRECTORY_SEPARATOR.'.'.$base.'.tmp';
	$destino=$directorio.DIRECTORY_SEPARATOR.$key;
	if(!move_uploaded_file($archivo['tmp_name'], $temporal)) { return array(false, 'No se pudo guardar el documento en el almacenamiento privado.'); }
	@chmod($temporal, 0600);
	if(!@rename($temporal, $destino)) { @unlink($temporal); return array(false, 'No se pudo finalizar el almacenamiento privado del documento.'); }
	@chmod($destino, 0600);
	$nombre=$validacion[1]; $mime=$validacion[2]; $bytes=$validacion[3]; $descripcion_db=$descripcion!=='' ? $descripcion : null;
	$stmt=$con ? $con->prepare('INSERT INTO cliente_documento_legajo (id_cliente,categoria,nombre_original,archivo_key,mime_type,tamano_bytes,descripcion,id_empleado_carga) VALUES (?,?,?,?,?,?,?,?)') : false;
	if(!$stmt) { @unlink($destino); return array(false, 'No se pudo registrar el documento. Verificá que la migración esté aplicada.'); }
	$stmt->bind_param('issssisi', $id_cliente, $categoria, $nombre, $key, $mime, $bytes, $descripcion_db, $id_carga);
	$ok=$stmt->execute() && $stmt->affected_rows===1;
	$stmt->close();
	if(!$ok) { @unlink($destino); return array(false, 'No se pudo registrar el documento en el legajo.'); }
	return array(true, 'Documento agregado al legajo digital.');
}

function crm_cliente_legajo_eliminar($id_cliente, $id_documento)
{
	global $con;
	$id_cliente=intval($id_cliente); $id_documento=intval($id_documento);
	if(!$con || $id_cliente<=0 || $id_documento<=0 || !crm_cliente_legajo_tabla_disponible()) { return array(false, 'No se pudo verificar el documento solicitado.'); }
	if(!$con->begin_transaction()) { return array(false, 'No se pudo iniciar la eliminación del documento.'); }
	$stmt=$con->prepare('SELECT archivo_key FROM cliente_documento_legajo WHERE id_documento=? AND id_cliente=? LIMIT 1 FOR UPDATE');
	if(!$stmt) { $con->rollback(); return array(false, 'No se pudo verificar el documento solicitado.'); }
	$stmt->bind_param('ii', $id_documento, $id_cliente);
	if(!$stmt->execute()) { $stmt->close(); $con->rollback(); return array(false, 'No se pudo verificar el documento solicitado.'); }
	$stmt->bind_result($key); $encontrado=$stmt->fetch(); $stmt->close();
	if(!$encontrado) { $con->rollback(); return array(false, 'El documento no pertenece a este cliente.'); }
	$stmt=$con->prepare('DELETE FROM cliente_documento_legajo WHERE id_documento=? AND id_cliente=? LIMIT 1');
	if(!$stmt) { $con->rollback(); return array(false, 'No se pudo eliminar el documento.'); }
	$stmt->bind_param('ii', $id_documento, $id_cliente);
	$ok=$stmt->execute() && $stmt->affected_rows===1; $stmt->close();
	if(!$ok || !$con->commit()) { $con->rollback(); return array(false, 'No se pudo confirmar la eliminación. El archivo se conservó.'); }
	$ruta=crm_cliente_legajo_ruta_por_key($key);
	if($ruta!=='' && is_file($ruta) && !@unlink($ruta)) { return array(true, 'El registro se eliminó, pero el archivo privado no pudo limpiarse.'); }
	return array(true, 'Documento eliminado del legajo digital.');
}
