<?php
// Botones propios de Marketing. Van en el contenido, no en el menu izquierdo.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo htmlspecialchars(url_crm('marketing/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo (in_array($menu_activo, array('inicio','dashboard'), true) ? 'btn-primary' : 'btn-white'); ?>">Inicio</a>
	<a href="<?php echo htmlspecialchars(url_crm('marketing/tareas.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo htmlspecialchars(url_crm('marketing/actividades.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_puede_clientes_de_area()) { ?>
	<a href="<?php echo htmlspecialchars(url_crm('marketing/clientes.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='clientes' ? 'btn-primary' : 'btn-white'); ?>">Clientes</a>
	<?php } ?>
	<?php if(acceso_puede_clientes_de_area('marketing') || crm_area_ve_todo('marketing') || acceso_puede('marketing')) { ?>
	<?php if(area_capacidad('marketing', 'puede_atenciones')) { ?>
	<a href="<?php echo htmlspecialchars(url_crm('marketing/atenciones.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='atenciones' ? 'btn-primary' : 'btn-white'); ?>">Atenciones</a>
	<?php } ?>
	<?php } ?>
<?php crm_menu_seccion_capacidades_area('marketing'); crm_menu_seccion_sala_reuniones(); ?>
</div>
