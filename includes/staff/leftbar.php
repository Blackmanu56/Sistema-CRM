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
 </style>
  <div class="page-sidebar" id="main-menu">
    <div class="page-sidebar-wrapper scrollbar-dynamic" id="main-menu-wrapper">
      <div class="user-info-wrapper">
        <div class="profile-wrapper" style="border:solid #fff 1px;"> <img src="<?php echo asset('img/admin.png'); ?>"  alt="" data-src="<?php echo asset('img/admin.png'); ?>" data-src-retina="<?php echo asset('img/admin.png'); ?>" width="69" height="69"  /> </div>
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
      $crm_panel_op_activo = ($crm_cur_script === 'home.php')
        && (strpos($crm_cur_uri, '/admin/home.php') !== false)
        && function_exists('acceso_es_super') && acceso_es_super()
        && function_exists('acceso_puede_armar_informes') && acceso_puede_armar_informes();
      $crm_es_inicio = in_array($crm_cur_script, array('dashboard.php', 'home.php', 'inicio.php'), true) && (strpos($crm_cur_uri, '/general/dashboard.php') === false) && !$crm_panel_op_activo;
      $dash_href=function_exists('crm_dashboard_destino') ? crm_dashboard_destino() : url_crm('admin/home.php');
      ?>
      <li class="start <?php echo $crm_es_inicio ? 'active' : ''; ?>"> <a href="<?php echo $dash_href; ?>"> <i class="icon-custom-home"></i> <span class="title">Inicio</span>  </a>
		    </li>
      <?php if(acceso_es_presidencia()) { ?>
      <li class="<?php echo ($crm_cur_script === 'informes-presidencia.php') ? 'active' : ''; ?>"> <a href="<?php echo url_crm('admin/informes-presidencia.php'); ?>"> <i class="fa fa-file-text-o"></i> <span class="title">Informes</span>  </a>
		    </li>
      <?php } elseif(function_exists('acceso_puede_armar_informes') && acceso_puede_armar_informes()) { ?>
      <li class="<?php echo ($crm_cur_script === 'informes-presidencia.php') ? 'active' : ''; ?>"> <a href="<?php echo url_crm('admin/informes-presidencia.php'); ?>"> <i class="fa fa-file-text-o"></i> <span class="title">Informes Presidencia</span>  </a>
		    </li>
      <?php if(acceso_es_super()) { ?>
      <li class="<?php echo $crm_panel_op_activo ? 'active' : ''; ?>"> <a href="<?php echo url_crm('admin/home.php'); ?>"> <i class="fa fa-bar-chart-o"></i> <span class="title">Panel operativo</span>  </a>
		    </li>
      <?php } ?>
      <?php } ?>
      <?php if(acceso_es_super()) { ?>
      <li class="<?php echo ($crm_cur_script === 'manage-users.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('admin/manage-users.php'); ?>"><span class="fa fa-users"></span> Clientes</a></li>
      <?php } ?>
      <?php if(acceso_puede('general') && (acceso_es_coord_general() || acceso_es_super())) { ?>
      <li class="<?php echo ($crm_cur_script === 'dashboard.php' && strpos($crm_cur_uri, '/general/dashboard.php') !== false) ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/dashboard.php'); ?>"><span class="fa fa-sitemap"></span>&nbsp;&nbsp;Coordinación general</a></li>
      <?php } ?>
      <?php if(acceso_es_coord_general() || acceso_es_super() || acceso_es_general()) { ?>
      <li class="<?php echo (strpos($crm_cur_uri, 'mapa-interactivo') !== false) ? 'active' : ''; ?>"><a href="<?php echo url_crm('general/mapa-interactivo/index.php'); ?>"><span class="fa fa-map-marker"></span>&nbsp;&nbsp;Mapa de eventos</a></li>
      <?php } ?>

      <?php
      if($left_area_act !== '' && $left_area_act !== 'general' && $left_area_act !== 'admin') {
      ?>
      <li class="crm-sidebar-separator" style="margin: 8px 14px 10px 14px; border-top: 1px solid rgba(255, 255, 255, 0.08); list-style: none;"></li>
      <li class="<?php echo in_array($crm_cur_script, array('tareas.php','nueva-tarea.php','ver-tarea.php','editar-tarea.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/tareas.php'); ?>"><span class="fa fa-check-square-o"></span>&nbsp;&nbsp;Tareas</a></li>
      <li class="<?php echo in_array($crm_cur_script, array('actividades.php','nueva-actividad.php','editar-actividad.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/actividades.php'); ?>"><span class="fa fa-calendar"></span>&nbsp;&nbsp;Actividades</a></li>
      <?php if($left_area_act === 'recepcion') { ?>
      <li class="<?php echo in_array($crm_cur_script, array('atenciones.php','nueva-atencion.php','atencion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('recepcion/atenciones.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Atenciones</a></li>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('recepcion/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } elseif($left_area_act === 'rrhh') { ?>
      <?php if(acceso_puede_gestionar('rrhh')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('empleados.php','editar-empleado.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/empleados.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Colaboradores</a></li>
      <li class="<?php echo ($crm_cur_script === 'alta-usuario.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/alta-usuario.php'); ?>"><span class="fa fa-user"></span>&nbsp;&nbsp;Alta de colaborador</a></li>
      <?php } ?>
      <?php if(acceso_puede_clientes_de_area('rrhh')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } ?>
      <?php if(area_capacidad('rrhh', 'recibe_turnos')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('atenciones.php','nueva-atencion.php','atencion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('rrhh/atenciones.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Atenciones</a></li>
      <?php } ?>
      <?php } elseif($left_area_act === 'sistemas') { ?>
      <li class="<?php echo ($crm_cur_script === 'dominios.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/dominios.php'); ?>"><span class="fa fa-globe"></span>&nbsp;&nbsp;Dominios</a></li>
      <?php if(acceso_puede_gestionar('sistemas')) { ?>
      <li class="<?php echo ($crm_cur_script === 'programas.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/programas.php'); ?>"><span class="fa fa-th-large"></span>&nbsp;&nbsp;Programas</a></li>
      <li class="<?php echo ($crm_cur_script === 'accesos.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/accesos.php'); ?>"><span class="fa fa-key"></span>&nbsp;&nbsp;Accesos</a></li>
      <li class="<?php echo ($crm_cur_script === 'permisos.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/permisos.php'); ?>"><span class="fa fa-shield"></span>&nbsp;&nbsp;Permisos</a></li>
      <li class="<?php echo ($crm_cur_script === 'ajustes.php') ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/ajustes.php'); ?>"><span class="fa fa-sliders"></span>&nbsp;&nbsp;Ajustes</a></li>
      <?php } ?>
      <?php if(acceso_puede_clientes_de_area('sistemas')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('sistemas/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } ?>
      <?php } elseif($left_area_act === 'vinculaciones') { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('vinculaciones/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <li class="<?php echo in_array($crm_cur_script, array('atenciones.php','nueva-atencion.php','atencion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('vinculaciones/atenciones.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Atenciones</a></li>
      <li class="<?php echo in_array($crm_cur_script, array('capacitaciones.php','nueva-capacitacion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('vinculaciones/capacitaciones.php'); ?>"><span class="fa fa-graduation-cap"></span>&nbsp;&nbsp;Capacitaciones</a></li>
      <?php } elseif(in_array($left_area_act, array('comercio','emprendedores','empresas','marketing','analista'), true)) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <li class="<?php echo in_array($crm_cur_script, array('atenciones.php','nueva-atencion.php','atencion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/atenciones.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Atenciones</a></li>
      <?php } elseif($left_area_act === 'administracion') { ?>
      <li class="<?php echo in_array($crm_cur_script, array('atenciones.php','nueva-atencion.php','atencion.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('administracion/atenciones.php'); ?>"><span class="fa fa-clock-o"></span>&nbsp;&nbsp;Atenciones</a></li>
      <?php if(acceso_puede_clientes_de_area('administracion')) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm('administracion/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } ?>
      <?php } elseif(acceso_puede_clientes_de_area($left_area_act)) { ?>
      <li class="<?php echo in_array($crm_cur_script, array('clientes.php','alta-cliente.php','editar-cliente.php'), true) ? 'active' : ''; ?>"><a href="<?php echo url_crm($left_area_act.'/clientes.php'); ?>"><span class="fa fa-users"></span>&nbsp;&nbsp;Clientes</a></li>
      <?php } ?>
      <?php } ?>
    </ul>
