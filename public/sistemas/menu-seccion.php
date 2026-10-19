<?php
// Botones propios de Sistemas (tareas, actividades y ajustes).
// Van en el contenido, no en el menu izquierdo general.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo htmlspecialchars(url_crm('sistemas/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo (in_array($menu_activo, array('inicio','dashboard'), true) ? 'btn-primary' : 'btn-white'); ?>">Inicio</a>
	<a href="<?php echo url_crm('sistemas/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('sistemas/actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(crm_puede_gestionar_programas()) { ?>
	<a href="<?php echo url_crm('sistemas/programas.php'); ?>" class="btn <?php echo ($menu_activo=='programas' ? 'btn-primary' : 'btn-white'); ?>">Programas</a>
	<?php } ?>
	<?php if(acceso_puede_clientes_de_area('sistemas')) { ?>
	<a href="<?php echo url_crm('sistemas/clientes.php'); ?>" class="btn <?php echo ($menu_activo=='clientes' ? 'btn-primary' : 'btn-white'); ?>">Clientes</a>
	<?php } ?>
	<?php if(area_capacidad('sistemas', 'puede_atenciones') && (acceso_puede_clientes_de_area('sistemas') || crm_area_ve_todo('sistemas') || acceso_puede('sistemas'))) { ?>
	<a href="<?php echo url_crm('sistemas/atenciones.php'); ?>" class="btn <?php echo ($menu_activo=='atenciones' ? 'btn-primary' : 'btn-white'); ?>">Atenciones</a>
	<?php } ?>
	<a href="<?php echo url_crm('sistemas/dominios.php'); ?>" class="btn <?php echo ($menu_activo=='dominios' ? 'btn-primary' : 'btn-white'); ?>">Dominios</a>
	<?php crm_menu_seccion_sala_reuniones(); ?>
	<?php if(acceso_puede_gestionar('sistemas')) { ?>
	<a href="<?php echo url_crm('sistemas/accesos.php'); ?>" class="btn <?php echo ($menu_activo=='accesos' ? 'btn-primary' : 'btn-white'); ?>">Accesos</a>
	<a href="<?php echo url_crm('sistemas/permisos.php'); ?>" class="btn <?php echo ($menu_activo=='permisos' ? 'btn-primary' : 'btn-white'); ?>">Permisos</a>
	<a href="<?php echo url_crm('sistemas/areas.php'); ?>" class="btn <?php echo ($menu_activo=='areas' ? 'btn-primary' : 'btn-white'); ?>">Áreas</a>
	<a href="<?php echo url_crm('sistemas/ajustes.php'); ?>" class="btn <?php echo ($menu_activo=='ajustes' ? 'btn-primary' : 'btn-white'); ?>">Ajustes</a>
	<?php } ?>
</div>
