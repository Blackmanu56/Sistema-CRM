<?php
// Botones de Presidencia: tareas, actividades e informes. Sin secciones operativas
// (provincias, programas, turnos ni solicitudes: esos archivos no existen en presidencia/).
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo url_crm('presidencia/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('presidencia/actividades.php'); ?>" class="btn <?php echo (in_array($menu_activo, array('actividades', 'Actividades'), true) ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<a href="<?php echo url_crm('admin/informes-presidencia.php'); ?>" class="btn <?php echo ($menu_activo=='informes_presidencia' ? 'btn-primary' : 'btn-white'); ?>">Informes</a>
</div>
