<?php
// Botones propios de Recursos humanos. Van en el contenido, no en el menu izquierdo.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo htmlspecialchars(url_crm('rrhh/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo (in_array($menu_activo, array('inicio','dashboard'), true) ? 'btn-primary' : 'btn-white'); ?>">Inicio</a>
	<a href="<?php echo url_crm('rrhh/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('rrhh/actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_puede_gestionar('rrhh')) { ?>
	<a href="<?php echo url_crm('rrhh/alta-usuario.php'); ?>" class="btn <?php echo ($menu_activo=='alta' ? 'btn-primary' : 'btn-white'); ?>">Alta de colaborador</a>
	<a href="<?php echo url_crm('rrhh/empleados.php'); ?>" class="btn <?php echo ($menu_activo=='empleados' ? 'btn-primary' : 'btn-white'); ?>">Colaboradores</a>
	<?php } ?>
	<?php if(acceso_puede_clientes_de_area('rrhh')) { ?>
	<a href="<?php echo url_crm('rrhh/clientes.php'); ?>" class="btn <?php echo ($menu_activo=='clientes' ? 'btn-primary' : 'btn-white'); ?>">Clientes</a>
	<?php } ?>
	<?php if(area_capacidad('rrhh', 'puede_atenciones')) { ?>
	<a href="<?php echo url_crm('rrhh/atenciones.php'); ?>" class="btn <?php echo ($menu_activo=='atenciones' ? 'btn-primary' : 'btn-white'); ?>">Atenciones</a>
	<?php } ?>
<?php crm_menu_seccion_capacidades_area('rrhh'); crm_menu_seccion_sala_reuniones(); ?>
</div>
