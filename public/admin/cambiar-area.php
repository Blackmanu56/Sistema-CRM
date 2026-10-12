<?php
require_once __DIR__.'/../_init.php';

if(!crm_es_staff())
{
	header('Location: '.url_crm('admin/index.php'));
	exit();
}

$area_solicitada = isset($_GET['area']) ? trim($_GET['area']) : (isset($_POST['area']) ? trim($_POST['area']) : '');
if($area_solicitada === '')
{
	header('Location: '.crm_dashboard_destino());
	exit();
}

if(function_exists('acceso_cambiar_area_activa') && acceso_cambiar_area_activa($area_solicitada))
{
	if($area_solicitada === 'general')
	{
		$destino = acceso_es_super() ? url_crm('admin/home.php') : url_crm('general/dashboard.php');
	}
	elseif(is_file(BASE_PATH.'/public/'.$area_solicitada.'/dashboard.php'))
	{
		$destino = url_crm($area_solicitada.'/dashboard.php');
	}
	elseif(is_file(BASE_PATH.'/public/'.$area_solicitada.'/tareas.php'))
	{
		$destino = url_crm($area_solicitada.'/tareas.php');
	}
	else
	{
		$destino = crm_dashboard_destino();
	}
	header('Location: '.$destino);
	exit();
}

// Si no está permitido el cambio
$_SESSION['errmsg'] = 'No tenés permisos para acceder al área solicitada.';
header('Location: '.crm_dashboard_destino());
exit();
