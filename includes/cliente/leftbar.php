 <!-- BEGIN SIDEBAR -->
  <div class="page-sidebar" id="main-menu">
    <div class="page-sidebar-wrapper scrollbar-dynamic" id="main-menu-wrapper">
      <div class="user-info-wrapper">
        <div class="profile-wrapper"> <img src="<?php echo asset('img/user.png'); ?>"  alt="" data-src="<?php echo asset('img/user.png'); ?>" data-src-retina="<?php echo asset('img/user.png'); ?>" width="69" height="69" /> </div>
        <div class="user-info">
          <div class="greeting">Bienvenido</div>
          <div class="username"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : '';?></div>
          <?php
          $cta=function_exists('acceso_cliente_cuenta') ? acceso_cliente_cuenta() : null;
          $varias_ctas=isset($_SESSION['cuentas_cliente']) && is_array($_SESSION['cuentas_cliente']) && count($_SESSION['cuentas_cliente'])>1;
          if($cta && $cta['tipo']=='empresa') { ?>
          <div class="crm-cuenta-activa"><i class="fa fa-building"></i> <?php echo htmlspecialchars($cta['nombre']); ?></div>
          <?php } else if($cta && $varias_ctas) { ?>
          <div class="crm-cuenta-activa"><i class="fa fa-user"></i> Cuenta personal</div>
          <?php } ?>
        </div>
      </div>
      <p class="menu-title">NAVEGAR</p>
    <ul>
      <li class="start"> <a href="<?php echo url_crm('dashboard.php'); ?>"> <i class="icon-custom-home"></i> <span class="title">Inicio</span> <span class="selected"></span>  </a>
		    </li>
          <li><a href="<?php echo url_crm('mis-turnos.php'); ?>"><span class="fa fa-calendar"></span> Mis atenciones</a></li>
          <li><a href="<?php echo url_crm('programas.php'); ?>"><span class="fa fa-bookmark"></span> Programas</a></li>
          <li><a href="<?php echo url_crm('actividades.php'); ?>"><span class="fa fa-users"></span> Actividades</a></li>
          <li><a href="<?php echo url_crm('profile.php'); ?>"><span class="fa fa-user"></span> Mi perfil</a></li>
          <?php if($varias_ctas) { ?>
          <li><a href="<?php echo url_crm('elegir-cuenta.php'); ?>"><span class="fa fa-exchange"></span> Cambiar cuenta</a></li>
          <?php } ?>
    </ul>
