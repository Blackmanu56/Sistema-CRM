<?php
require_once BASE_PATH.'/includes/rrhh/fotos.php';
$left_profile_id=function_exists('crm_id_empleado_sesion') ? crm_id_empleado_sesion() : 0;
$left_profile_photo_key=$left_profile_id>0 ? crm_rrhh_foto_key_por_empleado($left_profile_id) : '';
$left_profile_photo_url=($left_profile_photo_key!=='' && crm_rrhh_foto_ruta_por_key($left_profile_photo_key)!=='') ? url_crm('foto-empleado.php?id='.$left_profile_id.'&v='.rawurlencode($left_profile_photo_key)) : asset('img/admin.png');
$left_profile_url=url_crm('perfil-empleado.php');
?>
 <!-- BEGIN SIDEBAR -->
 <style>
 .page-sidebar,
 #main-menu-wrapper {
	overflow-x: hidden !important;
 }
 .page-sidebar .scroll-element.scroll-x {
	display: none !important;
 }
 .footer-widget {
	display: none !important;
 }
 .page-sidebar .page-sidebar-wrapper > ul > li > a {
	white-space: normal;
	overflow-wrap: break-word;
	word-wrap: break-word;
 }
 .user-info .username {
	white-space: normal;
	overflow-wrap: break-word;
 }
  .crm-sidebar-area-box {
	padding: 0 10px 14px 10px !important;
  }
  .crm-sidebar-area-dropdown {
	width: 100% !important;
	display: block !important;
	position: relative !important;
  }
  .crm-sidebar-area-btn {
	float: none !important;
	width: 100% !important;
	background: #1e293b !important;
	color: #f1f5f9 !important;
	border: 1px solid rgba(255,255,255,0.14) !important;
	border-radius: 6px !important;
	box-shadow: 0 1px 3px rgba(0,0,0,0.25) !important;
	transition: all .2s ease;
	font-weight: 600 !important;
	padding: 7px 8px !important;
	display: flex !important;
	align-items: center !important;
	justify-content: space-between !important;
	font-size: 11.5px !important;
	text-shadow: none !important;
	line-height: normal !important;
  }
  .crm-sidebar-area-btn:hover,
  .crm-sidebar-area-btn:focus {
	background: #273548 !important;
	color: #ffffff !important;
	border-color: rgba(255,255,255,0.28) !important;
  }
  .crm-sidebar-area-dropdown.open .crm-sidebar-area-btn {
	background: #1e293b !important;
	color: #ffffff !important;
	border-color: rgba(255,255,255,0.20) !important;
	border-bottom: 1px solid rgba(255,255,255,0.08) !important;
	border-bottom-left-radius: 0 !important;
	border-bottom-right-radius: 0 !important;
	box-shadow: none !important;
  }
  .crm-sidebar-area-dropdown.open .crm-sidebar-chevron {
	transform: rotate(180deg);
  }
  .crm-sidebar-chevron {
	transition: transform .2s ease;
  }
  .crm-sidebar-area-menu {
	display: none;
  }
  .crm-sidebar-area-dropdown.open > .crm-sidebar-area-menu {
	display: block !important;
	position: absolute !important;
	top: 100% !important;
	left: 0 !important;
	width: 100% !important;
	min-width: 100% !important;
	z-index: 1050 !important;
	float: none !important;
	margin: 0 !important;
	border: 1px solid rgba(255,255,255,0.20) !important;
	border-top: none !important;
	border-top-left-radius: 0 !important;
	border-top-right-radius: 0 !important;
	border-bottom-left-radius: 6px !important;
	border-bottom-right-radius: 6px !important;
	background: #1e293b !important;
	box-shadow: 0 14px 28px rgba(0,0,0,0.6) !important;
	overflow-y: auto !important;
	max-height: 280px !important;
	padding: 3px 0 5px 0 !important;
  }
  #main-menu .crm-sidebar-area-menu > li > a {
	margin: 0 !important;
	padding: 7px 12px !important;
	border-radius: 0 !important;
	display: flex !important;
	align-items: center !important;
	justify-content: space-between !important;
	color: #cbd5e1 !important;
	font-size: 12px !important;
	line-height: 1.3 !important;
	border-left: 3px solid transparent !important;
	text-decoration: none !important;
	transition: background 0.15s ease, color 0.15s ease !important;
  }
  #main-menu .crm-sidebar-area-menu > li > a:hover {
	background: #334155 !important;
	color: #ffffff !important;
  }
  #main-menu .crm-sidebar-area-menu > li.active > a {
	background: rgba(56, 189, 248, 0.14) !important;
	color: #ffffff !important;
	border-left: 3px solid #38bdf8 !important;
	font-weight: 600 !important;
  }
  .crm-sidebar-rol-badge {
	font-size: 8.5px !important;
	padding: 1px 4px !important;
	line-height: 1.2 !important;
	letter-spacing: 0 !important;
	font-weight: 600 !important;
	border-radius: 3px !important;
	display: inline-block !important;
	min-width: 0 !important;
	flex-shrink: 0 !important;
  }
  #main-menu ul > li.active > a {
	background: rgba(56, 189, 248, 0.12) !important;
	color: #ffffff !important;
	border-left: 3px solid #38bdf8 !important;
	font-weight: 600 !important;
  }
  #main-menu ul > li.active > a i,
  #main-menu ul > li.active > a span.fa {
	color: #38bdf8 !important;
  }
  #main-menu-wrapper:has(.crm-sidebar-area-dropdown.open) > ul > li.active > a,
  .page-sidebar:has(.crm-sidebar-area-dropdown.open) #main-menu-wrapper > ul > li.active > a {
	border-left-color: transparent !important;
  }
  .fa-user-plus:before { content: "\f007"; }
 #main-menu .crm-sidebar-map-divider {
	display: block !important;
	height: 1px !important;
	margin: 8px 14px 10px !important;
	padding: 0 !important;
	border: 0 !important;
	list-style: none;
 }
 #main-menu .crm-sidebar-map-divider > span {
	display: block;
	width: 100%;
	height: 1px;
	background: rgba(255, 255, 255, 0.08);
 }
 #main-menu .crm-sidebar-group-title {
	display: block !important;
	margin: 12px 14px 6px !important;
	padding: 0 !important;
	color: #64748b !important;
	font-size: 10px !important;
	font-weight: 600 !important;
	letter-spacing: .7px !important;
	line-height: 1.4 !important;
	text-transform: uppercase !important;
	list-style: none !important;
 }
 </style>
  <div class="page-sidebar" id="main-menu">
    <div class="page-sidebar-wrapper scrollbar-dynamic" id="main-menu-wrapper">
      <div class="user-info-wrapper">
        <div class="profile-wrapper" style="border:solid #fff 1px; border-radius:50%; overflow:hidden;">
          <a href="<?php echo htmlspecialchars($left_profile_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Abrir mi perfil">
            <img src="<?php echo htmlspecialchars($left_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Fotografía de perfil" data-src="<?php echo htmlspecialchars($left_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" data-src-retina="<?php echo htmlspecialchars($left_profile_photo_url, ENT_QUOTES, 'UTF-8'); ?>" width="69" height="69" style="object-fit:cover; border-radius:50%;" />
          </a>
        </div>
        <div class="user-info">
          <div class="greeting">Bienvenido</div>
          <div class="username"><?php echo htmlspecialchars(acceso_nombre()); ?></div>
        </div>
      </div>
      <?php
      $left_areas = function_exists('acceso_mis_areas_asignadas') ? acceso_mis_areas_asignadas() : array();
      $left_area_act = function_exists('acceso_area_activa') ? acceso_area_activa() : (function_exists('acceso_area') ? acceso_area() : '');
      $left_etq = function_exists('acceso_etiquetas_area') ? acceso_etiquetas_area() : array();
      $left_area_nom = ($left_area_act === 'general') ? 'Vista General' : (isset($left_etq[$left_area_act]) ? $left_etq[$left_area_act] : $left_area_act);
      if($left_area_act === 'rrhh') {
        $left_area_nom = 'Recursos Humanos';
      }
      $left_rol_clave = function_exists('acceso_rol_activo') ? acceso_rol_activo() : (isset($_SESSION['rol_area']) ? $_SESSION['rol_area'] : '');
      $left_rol_nom = function_exists('acceso_etiqueta_rol') ? acceso_etiqueta_rol($left_rol_clave) : (($left_rol_clave === 'superadmin') ? 'Superadmin' : (($left_rol_clave === 'coordinador_general') ? 'Coordinación General' : (($left_rol_clave === 'coordinador') ? 'Coordinación' : 'Personal')));
      foreach($left_areas as $la) {
        if($la['clave'] === $left_area_act && !empty($la['rol_nombre'])) {
          $left_rol_nom = $la['rol_nombre'];
          break;
        }
      }
      ?>
      <?php if(count($left_areas) > 1) { ?>
      <div class="crm-sidebar-area-box" style="padding: 0 10px 14px 10px;">
        <div class="btn-group dropdown crm-sidebar-area-dropdown" style="width:100%; display:block; position:relative;">
          <button type="button" class="btn crm-sidebar-area-btn dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Cambiar área de trabajo">
            <span style="display:flex; align-items:center; gap:6px; min-width:0; flex:1 1 auto; overflow:hidden;">
              <i class="fa fa-folder-open-o" style="color:#38bdf8; font-size:11px; flex-shrink:0;"></i>
              <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11.5px;"><?php echo htmlspecialchars($left_area_nom); ?></span>
            </span>
            <span style="display:flex; align-items:center; gap:4px; flex-shrink:0; margin-left:4px;">
              <span class="badge crm-sidebar-rol-badge" style="background:#0284c7 !important; color:#fff !important;">
                <?php echo htmlspecialchars($left_rol_nom); ?>
              </span>
              <span class="fa fa-chevron-down crm-sidebar-chevron" style="font-size:9px; color:#94a3b8;"></span>
            </span>
          </button>
          <ul class="dropdown-menu crm-sidebar-area-menu" role="menu" style="width:100%; min-width:100%; box-sizing:border-box; background:#1e293b; border:1px solid rgba(255,255,255,0.20); border-top:none; border-radius:0 0 6px 6px; box-shadow:0 14px 28px rgba(0,0,0,0.6); margin:0; padding:3px 0 5px 0; max-height:280px; overflow-y:auto;">
            <?php foreach($left_areas as $la_item) {
              $es_activa = ($la_item['clave'] === $left_area_act);
              $item_nom = ($la_item['clave'] === 'rrhh') ? 'Recursos Humanos' : $la_item['nombre'];
            ?>
            <li class="<?php echo $es_activa ? 'active' : ''; ?>">
              <a href="<?php echo url_crm('admin/cambiar-area.php?area='.urlencode($la_item['clave'])); ?>">
                <span style="display:flex; align-items:center; gap:8px; min-width:0; flex:1 1 auto; overflow:hidden;">
                  <i class="fa <?php echo $es_activa ? 'fa-check' : 'fa-circle-o'; ?>" style="font-size:11px; color:<?php echo $es_activa ? '#38bdf8' : '#64748b'; ?>; flex-shrink:0;"></i>
                  <span style="font-weight:<?php echo $es_activa ? '600' : '400'; ?>; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($item_nom); ?></span>
                </span>
                <?php if($es_activa) { ?>
                <span style="font-size:10px; color:#38bdf8; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-left:6px; flex-shrink:0;">Activa</span>
                <?php } ?>
              </a>
            </li>
            <?php } ?>
          </ul>
        </div>
      </div>
      <?php } elseif(count($left_areas) === 1) { ?>
      <div class="crm-sidebar-area-box" style="padding: 0 10px 14px 10px;">
        <div class="crm-sidebar-area-static" style="width:100%; background:#1e293b; color:#f1f5f9; border:1px solid rgba(255,255,255,0.14); border-radius:6px; font-weight:600; padding:7px 8px; display:flex; align-items:center; justify-content:space-between; font-size:11.5px; box-shadow:0 1px 3px rgba(0,0,0,0.25); box-sizing:border-box;">
          <span style="display:flex; align-items:center; gap:6px; min-width:0; flex:1 1 auto; overflow:hidden;">
            <i class="fa fa-folder-open-o" style="color:#38bdf8; font-size:11px; flex-shrink:0;"></i>
            <span style="color:#f1f5f9; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11.5px;"><?php echo htmlspecialchars($left_area_nom); ?></span>
          </span>
          <span class="badge crm-sidebar-rol-badge" style="background:#0284c7 !important; color:#fff !important; margin-left:4px;">
            <?php echo htmlspecialchars($left_rol_nom); ?>
          </span>
        </div>
      </div>
      <?php } ?>
      <p class="menu-title">NAVEGAR</p>
    <ul>
      <?php
      $crm_cur_script = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
      $crm_cur_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
      $crm_cur_script_path = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
      $crm_route_area = '';
      if(function_exists('acceso_etiquetas_area')) {
        foreach(array_keys(acceso_etiquetas_area()) as $crm_area_key) {
          if(strpos($crm_cur_script_path, '/'.$crm_area_key.'/') !== false) {
            $crm_route_area = $crm_area_key;
            break;
          }
        }
      }
      $crm_operational_route = ($crm_route_area !== '' && !in_array($crm_route_area, array('general', 'admin'), true));
      $crm_panel_operativo_route = ($crm_cur_script === 'home.php')
        && (strpos($crm_cur_uri, '/admin/home.php') !== false)
        && (acceso_es_super() || acceso_es_coord_general());
      require_once BASE_PATH.'/public/general/mapa-interactivo/contexto.php';
      require_once BASE_PATH.'/includes/navegacion-contexto.php';
      $crm_contexto_general_navegacion = isset($_GET['contexto_general']) && crm_navegacion_contexto_general_valido($_GET['contexto_general']);
      $crm_contexto_general_actividad = ($left_area_act === 'general' && $crm_cur_script === 'actividades.php'
        && isset($_GET['contexto']) && $_GET['contexto'] === 'general'
        && isset($_GET['origen_general'])
        && mapa_interactivo_contexto_general_valido($_GET['origen_general']));
      // General navigation must follow the selected area; route names alone cannot override it.
      $crm_vista_general_menu = $crm_contexto_general_navegacion || ($left_area_act === 'general' && !$crm_operational_route);
      $crm_sidebar_muestra_mapa = function_exists('acceso_puede_ver_mapa_eventos') && acceso_puede_ver_mapa_eventos();
      $crm_mapa_href=url_crm('general/mapa-interactivo/index.php');
      $crm_mapa_token_general=$crm_vista_general_menu ? mapa_interactivo_contexto_general_token() : '';
      $crm_nav_params=array();
      if($crm_mapa_token_general!=='') { $crm_nav_params['origen_general']=$crm_mapa_token_general; }
      $crm_nav_token_general=$crm_vista_general_menu ? crm_navegacion_general_token() : '';
      if($crm_nav_token_general!=='') { $crm_nav_params['contexto_general']=$crm_nav_token_general; }
      if($crm_nav_params) { $crm_mapa_href.='?'.http_build_query($crm_nav_params); }
      $crm_calendario_href=url_crm('calendario-institucional.php');
      if($crm_nav_token_general!=='') { $crm_calendario_href.='?contexto_general='.urlencode($crm_nav_token_general); }
      $crm_panel_op_activo = $crm_panel_operativo_route;
      require_once BASE_PATH.'/includes/informes-autorizacion.php';
      $crm_informe_permisos=crm_informes_autorizaciones_usuario();
      $crm_puede_ver_informes=!empty($crm_informe_permisos['crm_informe_tiene_tab_o_destino']);
      $crm_informes_href=url_crm('informes.php');
      if($crm_vista_general_menu && $crm_nav_token_general!=='') { $crm_informes_href.='?contexto_general='.urlencode($crm_nav_token_general); }
      $crm_es_inicio = in_array($crm_cur_script, array('dashboard.php', 'home.php', 'inicio.php'), true) && !$crm_panel_op_activo;
      $dash_href=function_exists('crm_dashboard_destino') ? crm_dashboard_destino() : url_crm('admin/home.php');
      // ¿El bloque de área de abajo imprime su propio "Clientes"? Si lo imprime (y el archivo existe),
      // no repetir el "Clientes" global del super (manage-users.php).
      $crm_area_muestra_clientes = false;
      if($left_area_act !== '' && $left_area_act !== 'general' && $left_area_act !== 'admin') {
        if($left_area_act === 'recepcion' || $left_area_act === 'vinculaciones'
          || in_array($left_area_act, array('comercio','emprendedores','empresas','marketing','analista'), true)) {
          $crm_area_muestra_clientes = true;
        } elseif($left_area_act === 'rrhh') {
          $crm_area_muestra_clientes = acceso_puede_clientes_de_area('rrhh');
        } elseif($left_area_act === 'sistemas') {
          $crm_area_muestra_clientes = acceso_puede_clientes_de_area('sistemas');
        } elseif($left_area_act === 'administracion') {
          $crm_area_muestra_clientes = acceso_puede_clientes_de_area('administracion');
        } else {
          $crm_area_muestra_clientes = acceso_puede_clientes_de_area($left_area_act);
        }
        if($crm_area_muestra_clientes && !is_file(BASE_PATH.'/public/'.$left_area_act.'/clientes.php')) {
          $crm_area_muestra_clientes = false;
        }
      }
      ?>
      <li class="start <?php echo $crm_es_inicio ? 'active' : ''; ?>"> <a href="<?php echo $dash_href; ?>"> <i class="icon-custom-home"></i> <span class="title">Inicio</span>  </a>
		    </li>
      <?php if($crm_sidebar_muestra_mapa) { ?>
      <li class="<?php echo (strpos($crm_cur_uri, 'mapa-interactivo') !== false) ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($crm_mapa_href, ENT_QUOTES, 'UTF-8'); ?>"><span class="fa fa-map-marker"></span>&nbsp;&nbsp;Mapa de eventos</a></li>
      <?php } ?>
      <?php if(function_exists('crm_es_staff') && crm_es_staff()) { ?>
      <li class="<?php echo ($crm_cur_script === 'calendario-institucional.php') ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($crm_calendario_href, ENT_QUOTES, 'UTF-8'); ?>"><span class="fa fa-calendar"></span>&nbsp;&nbsp;Calendario Institucional</a></li>
      <?php } ?>
      <li class="crm-sidebar-map-divider" aria-hidden="true"><span></span></li>
      <li class="crm-sidebar-group-title" aria-hidden="true">GESTIÓN</li>
      <?php
      if(!$crm_vista_general_menu && $left_area_act !== '' && $left_area_act !== 'general' && $left_area_act !== 'admin') {
      ?>
      <?php if(is_file(BASE_PATH.'/public/'.$left_area_act.'/tareas.php')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('tareas.php','nueva-tarea.php','ver-tarea.php','editar-tarea.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/tareas.php'); ?>"><span class="fa fa-check-square-o"></span>&nbsp;&nbsp;Tareas</a></li>
      <?php } ?>
      <?php if(is_file(BASE_PATH.'/public/'.$left_area_act.'/actividades.php')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('actividades.php','nueva-actividad.php','editar-actividad.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/actividades.php'); ?>"><span class="fa fa-calendar"></span>&nbsp;&nbsp;Actividades</a></li>
      <?php } ?>
      <?php if($left_area_act === 'rrhh' && acceso_puede_gestionar('rrhh')) { ?>
      <li class="<?php echo ($crm_cur_script === 'alta-usuario.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/alta-usuario.php'); ?>"><span class="fa fa-user"></span>&nbsp;&nbsp;Alta de colaborador</a></li>
      <li class="<?php echo in_array($crm_cur_script, array('empleados.php','editar-empleado.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/empleados.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Colaboradores</a></li>
      <?php } ?>
      <?php if($left_area_act === 'sistemas' && crm_puede_gestionar_programas()) { ?>
      <li class="<?php echo ($crm_cur_script === 'programas.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/programas.php'); ?>"><span class="fa fa-th-large"></span>&nbsp;&nbsp;Programas</a></li>
      <?php } ?>
      <?php if(acceso_es_super() && !$crm_area_muestra_clientes) { ?>
      <li class="<?php echo ($crm_cur_script === 'manage-users.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('admin/manage-users.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } elseif($left_area_act === 'recepcion' && acceso_puede_clientes_de_area('recepcion')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('recepcion/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } elseif($left_area_act === 'rrhh' && acceso_puede_clientes_de_area('rrhh')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } elseif($left_area_act === 'sistemas' && acceso_puede_clientes_de_area('sistemas')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } elseif(($left_area_act === 'vinculaciones' || in_array($left_area_act, array('comercio','emprendedores','empresas','marketing','analista'), true)) && acceso_puede_clientes_de_area($left_area_act)) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } elseif($left_area_act === 'administracion' && acceso_puede_clientes_de_area('administracion')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('administracion/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } elseif(acceso_puede_clientes_de_area($left_area_act) && is_file(BASE_PATH.'/public/'.$left_area_act.'/clientes.php')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } ?>
      <?php if(area_capacidad($left_area_act, 'puede_atenciones') && ($left_area_act === 'recepcion' || $left_area_act === 'vinculaciones' || in_array($left_area_act, array('comercio','emprendedores','empresas','marketing','analista','administracion'), true) || $left_area_act === 'sistemas') && (acceso_puede_clientes_de_area($left_area_act) || crm_area_ve_todo($left_area_act) || acceso_puede($left_area_act))) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('atenciones.php','nueva-atencion.php','atencion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/atenciones.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Atenciones</a></li>
      <?php } elseif($left_area_act === 'rrhh' && area_capacidad('rrhh', 'puede_atenciones')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('atenciones.php','nueva-atencion.php','atencion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/atenciones.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Atenciones</a></li>
      <?php } ?>
      <?php if($left_area_act === 'sistemas') { ?>
      <li class="<?php echo ($crm_cur_script === 'dominios.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/dominios.php'); ?>"><span class="fa fa-globe"></span>&nbsp;&nbsp;Dominios</a></li>
      <?php } ?>
      <?php if(acceso_puede_salas_reuniones()) { ?><li class="<?php echo ($crm_cur_script === 'salas-reuniones.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('salas-reuniones.php'); ?>"><span class="fa fa-calendar"></span>&nbsp;&nbsp;Sala de reuniones</a></li><?php } ?>
      <?php if($left_area_act === 'sistemas' && acceso_puede_gestionar('sistemas')) { ?>
      <li class="<?php echo ($crm_cur_script === 'accesos.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/accesos.php'); ?>"><span class="fa fa-key"></span>&nbsp;&nbsp;Accesos</a></li>
      <li class="<?php echo ($crm_cur_script === 'permisos.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/permisos.php'); ?>"><span class="fa fa-shield"></span>&nbsp;&nbsp;Permisos</a></li>
      <li class="<?php echo ($crm_cur_script === 'areas.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/areas.php'); ?>"><span class="fa fa-sitemap"></span>&nbsp;&nbsp;Áreas</a></li>
      <?php } ?>
      <?php if($left_area_act === 'sistemas' && acceso_puede_gestionar('sistemas')) { ?>
      <li class="<?php echo ($crm_cur_script === 'ajustes.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/ajustes.php'); ?>"><span class="fa fa-sliders"></span>&nbsp;&nbsp;Ajustes</a></li>
      <?php } ?>
      <?php } ?>
      <?php if($crm_vista_general_menu && acceso_puede('general') && (acceso_es_super() || acceso_es_coord_general())) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('tareas.php','nueva-tarea.php','ver-tarea.php','editar-tarea.php'), true) && strpos($crm_cur_uri, '/general/') !== false ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/tareas.php'); ?>"><span class="fa fa-check-square-o"></span>&nbsp;&nbsp;Tareas</a></li>
      <li class="<?php echo in_array($crm_cur_script, array('actividades.php','nueva-actividad.php','editar-actividad.php'), true) && (($left_area_act === 'general' && strpos($crm_cur_uri, '/general/') !== false) || $crm_contexto_general_actividad) ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/actividades.php'); ?>"><span class="fa fa-calendar"></span>&nbsp;&nbsp;Actividades</a></li>
      <?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('provincias.php','localidades.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/provincias.php'); ?>"><span class="fa fa-globe"></span>&nbsp;&nbsp;Provincias y localidades</a></li>
      <?php } ?>
      <?php if(crm_puede_gestionar_programas()) { ?>
      <li class="<?php echo ($crm_cur_script === 'programas.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/programas.php'); ?>"><span class="fa fa-th-large"></span>&nbsp;&nbsp;Programas</a></li>
      <?php } ?>
      <?php if(acceso_es_coord_general() || acceso_es_super()) { ?>
      <li class="<?php echo ($crm_cur_script === 'turnos-config.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/turnos-config.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Agenda de atenciones</a></li>
      <li class="<?php echo ($crm_cur_script === 'solicitudes-alta.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/solicitudes-alta.php'); ?>"><span class="fa fa-user-plus"></span>&nbsp;&nbsp;Solicitudes de alta</a></li>
      <?php } ?>
      <?php if(acceso_puede_salas_reuniones()) { ?><li class="<?php echo ($crm_cur_script === 'salas-reuniones.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('salas-reuniones.php'); ?>"><span class="fa fa-calendar"></span>&nbsp;&nbsp;Sala de reuniones</a></li><?php } ?>
      <?php if(acceso_es_super() || acceso_es_coord_general()) { ?>
      <li class="<?php echo $crm_panel_op_activo ? 'active' : ''; ?>"><a href="<?php echo url_crm('admin/home.php'); ?>"><span class="fa fa-bar-chart-o"></span>&nbsp;&nbsp;Panel operativo</a></li>
      <?php } ?>
      <?php } elseif(!$crm_vista_general_menu && ($left_area_act === '' || $left_area_act === 'admin')) { ?>
      <?php if(acceso_es_super() && !$crm_area_muestra_clientes) { ?>
      <li class="<?php echo ($crm_cur_script === 'manage-users.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('admin/manage-users.php'); ?>"><span class="fa fa-users"></span> Clientes</a></li>
      <?php } ?>
      <?php if(acceso_puede_salas_reuniones()) { ?><li class="<?php echo ($crm_cur_script === 'salas-reuniones.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('salas-reuniones.php'); ?>"><span class="fa fa-calendar"></span>&nbsp;&nbsp;Sala de reuniones</a></li><?php } ?>
      <?php } ?>
      <?php if($crm_puede_ver_informes) { ?>
      <li class="crm-sidebar-map-divider" aria-hidden="true"><span></span></li>
      <li class="crm-sidebar-group-title" aria-hidden="true">INFORMES</li>
      <li class="<?php echo in_array($crm_cur_script, array('informes.php','informes-presidencia.php'), true) ? 'active' : ''; ?>"><a href="<?php echo htmlspecialchars($crm_informes_href, ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-file-text-o"></i> <span class="title">Informes</span></a></li>
      <?php } ?>
    </ul>
