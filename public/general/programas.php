<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('general', true);
if(!crm_puede_gestionar_programas('general'))
{
	header('Location: '.acceso_destino_login());
	exit();
}
include("datos-tareas.php");
tareas_iniciar_contexto();
$programas_puede_gestionar=crm_puede_gestionar_programas('general');
$menu_activo='programas';
include BASE_PATH.'/includes/programas-pagina.php';
