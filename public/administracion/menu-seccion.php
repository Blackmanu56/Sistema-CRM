<?php
// Administración: solo tareas y actividades (sin clientes/atenciones).
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo tareas_link('tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo tareas_link('actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
</div>
