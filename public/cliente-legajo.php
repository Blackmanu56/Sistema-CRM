<?php
require_once __DIR__.'/_init.php';
crm_proteger_staff();
require_once BASE_PATH.'/includes/crm_extra.php';
require_once BASE_PATH.'/includes/clientes-legajo.php';

$area=acceso_area_pantalla();
$puede_consultar=function($id_cliente) use ($area) {
	$id_cliente=intval($id_cliente);
	return $id_cliente>0
		&& function_exists('acceso_puede_clientes_de_area')
		&& acceso_puede_clientes_de_area($area)
		&& function_exists('crm_cliente_puede_ver')
		&& crm_cliente_puede_ver($id_cliente, $area);
};
$puede_gestionar=!crm_cliente_area_solo_lectura($area);
$redirect_cliente=function($id_cliente,$volver='ficha') use ($area) {
	$pagina=$area.'/editar-cliente.php?id='.intval($id_cliente);
	if($volver!=='editar') { $pagina.='&vista=ficha'; }
	$pagina.='#clienteLegajoDigital';
	header('Location: '.url_crm($pagina));
	exit();
};

$id_documento=isset($_GET['id_documento']) && is_scalar($_GET['id_documento']) ? intval($_GET['id_documento']) : 0;
$modo=isset($_GET['modo']) && is_string($_GET['modo']) ? $_GET['modo'] : '';
if($id_documento>0 && in_array($modo, array('ver','imprimir','descargar'), true))
{
	$documento=crm_cliente_legajo_documento_por_id($id_documento);
	if(!$documento || !$puede_consultar(intval($documento['id_cliente']))) { http_response_code(404); exit(); }
	$extension=strtolower(pathinfo($documento['archivo_key'], PATHINFO_EXTENSION));
	$mimes=array('pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	if(!isset($mimes[$extension]) || !isset($documento['mime_type']) || $documento['mime_type']!==$mimes[$extension]) { http_response_code(404); exit(); }
	$ruta=crm_cliente_legajo_ruta_por_key($documento['archivo_key']);
	if($ruta==='' || !is_readable($ruta)) { http_response_code(404); exit(); }
	$nombre=crm_cliente_legajo_nombre_seguro($documento['nombre_original']);
	if($nombre==='') { $nombre='documento'; }
	$ascii=preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);
	if($ascii==='' || $ascii==='.') { $ascii='documento'; }
	while(ob_get_level()>0) { ob_end_clean(); }
	header('Content-Type: '.$mimes[$extension]);
	header('Content-Length: '.filesize($ruta));
	header('Content-Disposition: '.($modo==='descargar' ? 'attachment' : 'inline').'; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($nombre));
	header('X-Content-Type-Options: nosniff');
	header('Cache-Control: private, no-store, max-age=0');
	header('Pragma: no-cache');
	header('X-Frame-Options: SAMEORIGIN');
	readfile($ruta);
	exit();
}

if(strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '')!=='POST') { http_response_code(405); exit(); }
$id_cliente=isset($_POST['id_cliente']) && is_scalar($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
if(!$puede_consultar($id_cliente)) { http_response_code(404); exit(); }
if(!$puede_gestionar) { http_response_code(403); exit('No tenés autorización para administrar documentos de clientes.'); }
$volver=isset($_POST['volver']) && is_string($_POST['volver']) && $_POST['volver']==='editar' ? 'editar' : 'ficha';
$accion_post=isset($_POST['accion']) && is_string($_POST['accion']) ? $_POST['accion'] : '';
$guardar_contexto_formulario=function() use ($accion_post,$volver) {
	if($accion_post!=='agregar') { return; }
	$categorias=crm_cliente_legajo_categorias();
	$categoria=isset($_POST['categoria']) && is_string($_POST['categoria']) && isset($categorias[$_POST['categoria']]) ? $_POST['categoria'] : '';
	$descripcion=isset($_POST['descripcion']) && is_string($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
	if(function_exists('mb_substr')) { $descripcion=mb_substr($descripcion, 0, 1000, 'UTF-8'); }
	else { $descripcion=substr($descripcion, 0, 4000); }
	$_SESSION['crm_cliente_legajo_formulario']=array('categoria'=>$categoria,'descripcion'=>$descripcion,'reabrir'=>1,'volver'=>$volver);
};
if(!function_exists('crm_tarea_extra_csrf_valido') || !crm_tarea_extra_csrf_valido(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : ''))
{
	http_response_code(303);
	$guardar_contexto_formulario();
	$_SESSION['crm_cliente_legajo_mensaje']=array('error','La sesión del formulario venció. Actualizá la ficha e intentá nuevamente.');
	$redirect_cliente($id_cliente,$volver);
}

$accion=$accion_post;
if($accion==='agregar')
{
	$resultado=crm_cliente_legajo_agregar($id_cliente,
		isset($_POST['categoria']) && is_string($_POST['categoria']) ? $_POST['categoria'] : '',
		isset($_POST['descripcion']) && is_string($_POST['descripcion']) ? $_POST['descripcion'] : '',
		isset($_FILES['documento']) && is_array($_FILES['documento']) ? $_FILES['documento'] : array());
}
elseif($accion==='eliminar')
{
	$id_doc=isset($_POST['id_documento']) && is_scalar($_POST['id_documento']) ? intval($_POST['id_documento']) : 0;
	$resultado=crm_cliente_legajo_eliminar($id_cliente, $id_doc);
}
else { $resultado=array(false, 'La acción solicitada no es válida.'); }

$_SESSION['crm_cliente_legajo_mensaje']=array($resultado[0] ? 'success' : 'error', $resultado[1]);
if(!$resultado[0]) { $guardar_contexto_formulario(); }
else { unset($_SESSION['crm_cliente_legajo_formulario']); }
$redirect_cliente($id_cliente,$volver);
