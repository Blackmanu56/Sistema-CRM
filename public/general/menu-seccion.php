<?php
// Accesos de Vista General; las autorizaciones existentes determinan qué se muestra.
require_once BASE_PATH.'/includes/navegacion-contexto.php';
$crm_contexto_general_valido=isset($_GET['contexto_general']) && crm_navegacion_contexto_general_valido($_GET['contexto_general']);
$crm_area_activa_general=function_exists('acceso_area_activa') && acceso_area_activa()==='general';
if(!$crm_area_activa_general && !$crm_contexto_general_valido) { return; }
if(!isset($menu_activo)) { $menu_activo='tareas'; }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo htmlspecialchars(url_crm('general/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo (in_array($menu_activo, array('inicio','dashboard'), true) ? 'btn-primary' : 'btn-white'); ?>">Inicio</a>
	<a href="<?php echo url_crm('general/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('general/actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_es_coord_general() || acceso_es_super() || acceso_es_admin_tareas()) { ?>
	<a href="<?php echo htmlspecialchars(crm_navegacion_general_url('general/informes-presidencia.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='informes_presidencia' ? 'btn-primary' : 'btn-white'); ?>">Informes Presidencia</a>
	<?php } ?>
	<?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
	<a href="<?php echo htmlspecialchars(crm_navegacion_general_url('general/provincias.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='provincias' ? 'btn-primary' : 'btn-white'); ?>">Provincias y localidades</a>
	<?php } ?>
	<?php if(crm_puede_gestionar_programas()) { ?>
	<a href="<?php echo htmlspecialchars(crm_navegacion_general_url('general/programas.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='programas' ? 'btn-primary' : 'btn-white'); ?>">Programas</a>
	<?php } ?>
	<?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
	<a href="<?php echo htmlspecialchars(crm_navegacion_general_url('general/turnos-config.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='turnos_config' ? 'btn-primary' : 'btn-white'); ?>">Agenda de atenciones</a>
	<?php
		$n_sol=function_exists('crm_solicitud_contar_pendientes') ? crm_solicitud_contar_pendientes() : 0;
	?>
	<a href="<?php echo htmlspecialchars(crm_navegacion_general_url('general/solicitudes-alta.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='solicitudes_alta' ? 'btn-primary' : 'btn-white'); ?>">Solicitudes alta<?php if($n_sol>0) { ?> <span class="badge"><?php echo intval($n_sol); ?></span><?php } ?></a>
	<?php } ?>
<?php crm_menu_seccion_sala_reuniones(); ?>
	<?php if(acceso_es_super() || acceso_es_coord_general()) { ?>
	<a href="<?php echo htmlspecialchars(crm_navegacion_general_url('admin/home.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo ($menu_activo=='panel_operativo' ? 'btn-primary' : 'btn-white'); ?>">Panel Operativo</a>
	<?php } ?>
</div>
