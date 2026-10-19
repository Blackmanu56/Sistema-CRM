<?php
// Botones propios de Recepción.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
<div class="crm-menu-seccion">
	<a href="<?php echo htmlspecialchars(url_crm('recepcion/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo (in_array($menu_activo, array('inicio','dashboard'), true) ? 'btn-primary' : 'btn-white'); ?>">Inicio</a>
	<a href="<?php echo url_crm('recepcion/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('recepcion/actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_puede_clientes_de_area('recepcion')) { ?>
	<a href="<?php echo url_crm('recepcion/clientes.php'); ?>" class="btn <?php echo (($menu_activo=='clientes' || $menu_activo=='alta') ? 'btn-primary' : 'btn-white'); ?>">Clientes</a>
	<?php } ?>
	<?php if(acceso_puede_clientes_de_area('recepcion') || crm_area_ve_todo('recepcion') || acceso_puede('recepcion')) { ?>
	<?php if(area_capacidad('recepcion', 'puede_atenciones')) { ?>
	<a href="<?php echo url_crm('recepcion/atenciones.php'); ?>" class="btn <?php echo ($menu_activo=='atenciones' ? 'btn-primary' : 'btn-white'); ?>">Atenciones</a>
	<?php } ?>
	<?php } ?>
<?php crm_menu_seccion_sala_reuniones(); ?>
</div>
