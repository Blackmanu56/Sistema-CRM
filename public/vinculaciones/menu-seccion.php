<?php
// Botones propios de Coordinacion de vinculaciones.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo tareas_link('tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo tareas_link('actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_puede_clientes_de_area()) { ?>
	<a href="<?php echo tareas_link('clientes.php'); ?>" class="btn <?php echo ($menu_activo=='clientes' ? 'btn-primary' : 'btn-white'); ?>">Clientes</a>
	<a href="<?php echo tareas_link('atenciones.php'); ?>" class="btn <?php echo ($menu_activo=='atenciones' ? 'btn-primary' : 'btn-white'); ?>">Atenciones</a>
	<?php } ?>
	<a href="<?php echo tareas_link('capacitaciones.php'); ?>" class="btn <?php echo ($menu_activo=='Capacitaciones' ? 'btn-primary' : 'btn-white'); ?>">Capacitaciones</a>
</div>
