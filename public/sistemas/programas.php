<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff(null, true);
$programas_area_activa=function_exists('acceso_area_activa') ? acceso_area_activa() : '';
if(!function_exists('crm_puede_consultar_programas') || !crm_puede_consultar_programas($programas_area_activa))
{
	http_response_code(403);
	exit('El módulo de Programas está deshabilitado para esta área o no tenés acceso.');
}
$programas_puede_gestionar=($programas_area_activa==='sistemas' && crm_puede_gestionar_programas('sistemas'))
	|| ($programas_area_activa==='general' && crm_puede_gestionar_programas('general'));
$acciones_catalogo_programas=array('alta_objetivo','sumar_areas_objetivo','alta_tarea','editar_tarea_modelo','eliminar_tarea_modelo','alta_programa','agregar_items','quitar_item');
foreach($acciones_catalogo_programas as $accion_catalogo_programas)
{
	if(isset($_POST[$accion_catalogo_programas]) && !$programas_puede_gestionar)
	{
		http_response_code(403);
		exit('No tenés permisos para administrar el catálogo de Programas.');
	}
}
include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='programas';
include BASE_PATH.'/includes/programas-pagina.php';
