<?php
// Botones propios de Coordinacion de empresas. Van en el contenido, no en el menu izquierdo.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
<div class="crm-menu-seccion">
	<a href="<?php echo htmlspecialchars(url_crm('empresas/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo (in_array($menu_activo, array('inicio','dashboard'), true) ? 'btn-primary' : 'btn-white'); ?>">Inicio</a>
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
<?php crm_menu_seccion_sala_reuniones(); ?>
</div>
