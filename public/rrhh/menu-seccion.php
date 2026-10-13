<?php
// Botones propios de Recursos humanos. Van en el contenido, no en el menu izquierdo.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo url_crm('rrhh/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('rrhh/actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_puede_gestionar('rrhh')) { ?>
	<a href="<?php echo url_crm('rrhh/alta-usuario.php'); ?>" class="btn <?php echo ($menu_activo=='alta' ? 'btn-primary' : 'btn-white'); ?>">Alta de colaborador</a>
	<a href="<?php echo url_crm('rrhh/empleados.php'); ?>" class="btn <?php echo ($menu_activo=='empleados' ? 'btn-primary' : 'btn-white'); ?>">Colaboradores</a>
	<?php } ?>
</div>
