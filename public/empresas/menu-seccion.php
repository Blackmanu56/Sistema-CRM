<?php
// Botones propios de Coordinacion de empresas. Van en el contenido, no en el menu izquierdo.
require_once BASE_PATH.'/includes/empresas-seguimiento.php';
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
<div class="crm-menu-seccion">
	<a href="<?php echo htmlspecialchars(url_crm('empresas/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo==='inicio' || basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')==='dashboard.php' ? 'btn-primary' : 'btn-white'); ?>" <?php echo ($menu_activo==='inicio' || basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '')==='dashboard.php') ? 'aria-current="page"' : ''; ?>><i class="fa fa-home" aria-hidden="true"></i>&nbsp;Inicio</a>
	<a href="<?php echo htmlspecialchars(url_crm('empresas/tareas.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo htmlspecialchars(url_crm('empresas/actividades.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_puede_clientes_de_area()) { ?>
	<a href="<?php echo htmlspecialchars(url_crm('empresas/clientes.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='clientes' ? 'btn-primary' : 'btn-white'); ?>">Clientes</a>
	<?php } ?>
	<?php if(acceso_puede_clientes_de_area('empresas') || crm_area_ve_todo('empresas') || acceso_puede('empresas')) { ?>
	<?php if(area_capacidad('empresas', 'puede_atenciones')) { ?>
	<a href="<?php echo htmlspecialchars(url_crm('empresas/atenciones.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='atenciones' ? 'btn-primary' : 'btn-white'); ?>">Atenciones</a>
	<?php } ?>
	<?php } ?>
	<?php if(empresa_seguimiento_puede_ver_area('empresas')) { ?>
	<a href="<?php echo htmlspecialchars(url_crm('empresas/seguimientos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo==='seguimientos' ? 'btn-primary' : 'btn-white'); ?>">Seguimientos</a>
	<?php } ?>
<?php crm_menu_seccion_capacidades_area('empresas'); crm_menu_seccion_sala_reuniones(); ?>
</div>
