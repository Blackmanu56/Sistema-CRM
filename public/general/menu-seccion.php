<?php
// Accesos de Vista General; las autorizaciones existentes determinan qué se muestra.
require_once __DIR__.'/mapa-interactivo/contexto.php';
if(!isset($menu_activo)) { $menu_activo='tareas'; }
$mapa_general_href=url_crm('general/mapa-interactivo/index.php');
$mapa_general_token=mapa_interactivo_contexto_general_token();
if($mapa_general_token!=='') { $mapa_general_href.='?origen_general='.urlencode($mapa_general_token); }
?>
 <div class="crm-menu-seccion">
	<a href="<?php echo url_crm('general/tareas.php'); ?>" class="btn <?php echo ($menu_activo=='tareas' ? 'btn-primary' : 'btn-white'); ?>">Tareas</a>
	<a href="<?php echo url_crm('general/actividades.php'); ?>" class="btn <?php echo ($menu_activo=='Actividades' ? 'btn-primary' : 'btn-white'); ?>">Actividades</a>
	<?php if(acceso_es_coord_general() || acceso_es_super() || acceso_es_admin_tareas()) { ?>
	<a href="<?php echo url_crm('general/informes-presidencia.php'); ?>" class="btn <?php echo ($menu_activo=='informes_presidencia' ? 'btn-primary' : 'btn-white'); ?>">Informes Presidencia</a>
	<?php } ?>
	<?php if(function_exists('acceso_puede_ver_mapa_eventos') && acceso_puede_ver_mapa_eventos()) { ?>
	<a href="<?php echo htmlspecialchars($mapa_general_href, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-white">Mapa de eventos</a>
	<?php } ?>
	<?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
	<a href="<?php echo url_crm('general/provincias.php'); ?>" class="btn <?php echo ($menu_activo=='provincias' ? 'btn-primary' : 'btn-white'); ?>">Provincias y localidades</a>
	<?php } ?>
	<?php if(crm_puede_gestionar_programas()) { ?>
	<a href="<?php echo url_crm('general/programas.php'); ?>" class="btn <?php echo ($menu_activo=='programas' ? 'btn-primary' : 'btn-white'); ?>">Programas</a>
	<?php } ?>
	<?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
	<a href="<?php echo url_crm('general/turnos-config.php'); ?>" class="btn <?php echo ($menu_activo=='turnos_config' ? 'btn-primary' : 'btn-white'); ?>">Agenda de atenciones</a>
	<?php
		$n_sol=function_exists('crm_solicitud_contar_pendientes') ? crm_solicitud_contar_pendientes() : 0;
	?>
	<a href="<?php echo url_crm('general/solicitudes-alta.php'); ?>" class="btn <?php echo ($menu_activo=='solicitudes_alta' ? 'btn-primary' : 'btn-white'); ?>">Solicitudes alta<?php if($n_sol>0) { ?> <span class="badge"><?php echo intval($n_sol); ?></span><?php } ?></a>
	<?php } ?>
<?php crm_menu_seccion_sala_reuniones(); ?>
	<?php if(acceso_es_super() || acceso_es_coord_general()) { ?>
	<a href="<?php echo url_crm('admin/home.php'); ?>" class="btn <?php echo ($menu_activo=='panel_operativo' ? 'btn-primary' : 'btn-white'); ?>">Panel Operativo</a>
	<?php } ?>
</div>
