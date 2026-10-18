<?php
/**
 * Shared creation service used by the full client form and its quick-create modal.
 * Returns an existing-client match instead of creating a duplicate.
 */
function clientes_procesar_alta_formulario($area_act)
{
	$cuil_post = isset($_POST['cuil_cuit']) ? trim($_POST['cuil_cuit']) : '';
	$dni_post = isset($_POST['dni']) ? trim($_POST['dni']) : '';
	$cli_dup = cliente_buscar_por_identificador($cuil_post);
	if(!$cli_dup && $dni_post !== '')
	{
		$cli_dup = cliente_buscar_por_identificador($dni_post);
	}
	if($cli_dup)
	{
		return array('duplicate' => $cli_dup, 'error' => '', 'id' => 0);
	}

	$err = cliente_alta(array(
		'nombre' => isset($_POST['name']) ? trim($_POST['name']) : '',
		'correo' => isset($_POST['email']) ? trim($_POST['email']) : '',
		'telefono' => isset($_POST['mobile']) ? trim($_POST['mobile']) : '',
		'genero' => isset($_POST['gender']) ? $_POST['gender'] : 'm',
		'direccion' => isset($_POST['address']) ? trim($_POST['address']) : '',
		'id_localidad' => isset($_POST['id_localidad']) ? intval($_POST['id_localidad']) : 0,
		'cuil_cuit' => $cuil_post,
		'area' => $area_act,
		'areas' => array($area_act),
		'tipo_cliente' => isset($_POST['tipo_cliente']) ? $_POST['tipo_cliente'] : 'emprendedor',
		'dni' => $dni_post,
		'origen' => isset($_POST['origen']) ? $_POST['origen'] : 'presencial',
		'id_empleado_alta' => function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0,
		'abrir_turno' => false,
		'representantes' => function_exists('cliente_representantes_desde_post') ? cliente_representantes_desde_post() : array()
	));
	if($err !== '')
	{
		return array('duplicate' => null, 'error' => $err, 'id' => 0);
	}
	return array(
		'duplicate' => null,
		'error' => '',
		'id' => isset($GLOBALS['crm_ultimo_cliente_alta_id']) ? intval($GLOBALS['crm_ultimo_cliente_alta_id']) : 0
	);
}
