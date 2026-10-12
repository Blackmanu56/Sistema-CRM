<?php
// Botones de Coordinacion general: tareas propias. Las demas areas se ven en el menu izquierdo.
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo tareas_link('tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo tareas_link('actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_es_coord_general() || acceso_es_super() || acceso_es_admin_tareas()) { ?>
	<a href="<?php echo tareas_link('informes-presidencia.php'); ?>" class="btn <?php echo ($menu_activo=='informes_presidencia' ? 'btn-primary' : 'btn-white'); ?>">Informes Presidencia</a>
	<?php } ?>
	<?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
	<a href="<?php echo tareas_link('provincias.php'); ?>" class="btn <?php echo ($menu_activo=='provincias' ? 'btn-primary' : 'btn-white'); ?>">Provincias y localidades</a>
	<?php } ?>
	<?php if(crm_puede_gestionar_programas()) { ?>
	<a href="<?php echo tareas_link('programas.php'); ?>" class="btn <?php echo ($menu_activo=='programas' ? 'btn-primary' : 'btn-white'); ?>">Programas</a>
	<?php } ?>
	<?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
	<a href="<?php echo tareas_link('turnos-config.php'); ?>" class="btn <?php echo ($menu_activo=='turnos_config' ? 'btn-primary' : 'btn-white'); ?>">Agenda de atenciones</a>
	<?php
		$n_sol=function_exists('crm_solicitud_contar_pendientes') ? crm_solicitud_contar_pendientes() : 0;
	?>
	<a href="<?php echo tareas_link('solicitudes-alta.php'); ?>" class="btn <?php echo ($menu_activo=='solicitudes_alta' ? 'btn-primary' : 'btn-white'); ?>">Solicitudes alta<?php if($n_sol>0) { ?> <span class="badge"><?php echo intval($n_sol); ?></span><?php } ?></a>
	<?php } ?>
</div>
