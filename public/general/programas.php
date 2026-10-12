<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('general');
if(!crm_puede_gestionar_programas())
{
	header('Location: '.acceso_destino_login());
	exit();
}
include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='programas';
include BASE_PATH.'/includes/programas-pagina.php';
