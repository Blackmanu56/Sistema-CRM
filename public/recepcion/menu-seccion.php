<?php
// Botones propios de Recepción.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
<div class="crm-menu-seccion">
	<a href="<?php echo url_crm('recepcion/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('recepcion/actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_puede_clientes_de_area('recepcion')) { ?>
	<a href="<?php echo url_crm('recepcion/clientes.php'); ?>" class="btn <?php echo (($menu_activo=='clientes' || $menu_activo=='alta') ? 'btn-primary' : 'btn-white'); ?>">Clientes</a>
	<?php } ?>
	<?php if(acceso_puede_clientes_de_area('recepcion') || crm_area_ve_todo('recepcion') || acceso_puede('recepcion')) { ?>
	<a href="<?php echo url_crm('recepcion/atenciones.php'); ?>" class="btn <?php echo ($menu_activo=='atenciones' ? 'btn-primary' : 'btn-white'); ?>">Atenciones</a>
	<?php } ?>
<?php crm_menu_seccion_sala_reuniones(); ?>
</div>
