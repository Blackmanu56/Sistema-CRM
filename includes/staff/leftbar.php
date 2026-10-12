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
      <p class="menu-title">NAVEGAR</p>
    <ul>
      <?php
      $dash_href=function_exists('crm_dashboard_destino') ? crm_dashboard_destino() : url_crm('admin/home.php');
      ?>
      <li class="start"> <a href="<?php echo $dash_href; ?>"> <i class="icon-custom-home"></i> <span class="title">Inicio</span>  </a>
		    </li>
      <?php if(acceso_es_presidencia()) { ?>
      <li> <a href="<?php echo url_crm('admin/informes-presidencia.php'); ?>"> <i class="fa fa-file-text-o"></i> <span class="title">Informes</span>  </a>
		    </li>
      <?php } elseif(function_exists('acceso_puede_armar_informes') && acceso_puede_armar_informes()) { ?>
      <li> <a href="<?php echo url_crm('admin/informes-presidencia.php'); ?>"> <i class="fa fa-file-text-o"></i> <span class="title">Informes Presidencia</span>  </a>
		    </li>
      <?php if(acceso_es_super()) { ?>
      <li> <a href="<?php echo url_crm('admin/home.php'); ?>"> <i class="fa fa-bar-chart-o"></i> <span class="title">Panel operativo</span>  </a>
		    </li>
      <?php } ?>
      <?php } ?>
      <?php if(acceso_es_super()) { ?>
                            <li><a href="<?php echo url_crm('admin/manage-users.php'); ?>"><span class="fa fa-users"></span> Clientes</a></li>
      <?php } ?>
                            <?php if(acceso_puede('general') && (acceso_es_coord_general() || acceso_es_super())) { ?>
                            <li><a href="<?php echo url_crm('general/dashboard.php'); ?>"><span class="fa fa-sitemap"></span>&nbsp;&nbsp;Coordinación general</a></li>
                            <?php } ?>
                            <?php
                            $iconos_area=array(
                                'rrhh'=>'fa-briefcase',
                                'administracion'=>'fa-archive',
                                'marketing'=>'fa-check-square-o',
                                'empresas'=>'fa-building',
                                'emprendedores'=>'fa-users',
                                'vinculaciones'=>'fa-share-alt',
                                'comercio'=>'fa-globe',
                                'recepcion'=>'fa-phone',
                                'sistemas'=>'fa-cogs',
                            );
                            foreach(acceso_etiquetas_area() as $c_area => $nom_area) {
                                if($c_area === 'general' || $c_area === 'presidencia') {
                                    continue;
                                }
                                if(acceso_puede($c_area)) {
                                    $ico = isset($iconos_area[$c_area]) ? $iconos_area[$c_area] : 'fa-folder-open';
                                    $href_area = is_file(BASE_PATH.'/public/'.$c_area.'/dashboard.php')
                                    	? url_crm($c_area.'/dashboard.php')
                                    	: url_crm($c_area.'/tareas.php');
                            ?>
                            <li><a href="<?php echo $href_area; ?>"><span class="fa <?php echo $ico; ?>"></span>&nbsp;&nbsp;<?php echo htmlspecialchars($nom_area, ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <?php
                                }
                            }
                            ?>
    </ul>
