<div class="header navbar navbar-inverse ">
  <div class="navbar-inner">
    <div class="header-seperation">
      <ul class="nav pull-left notifcation-center" id="main-menu-toggle-wrapper" style="display:none">
        <li class="dropdown"> <a id="main-menu-toggle" href="#main-menu"  class="" >
          <div class="iconset top-menu-toggle-white"></div>
          </a> </li>
      </ul>
      <a href="<?php echo url_crm('dashboard.php'); ?>" class="crm-brand-link">ADEMI</a>
    </div>
    <div class="header-quick-nav" >
      <div class="pull-left">
      </div>
      <div class="pull-right">
        <ul class="nav quick-section ">
          <li class="quicklinks"> <a data-toggle="dropdown" class="dropdown-toggle  pull-right " href="#" id="user-options">
            <div class="iconset top-settings-dark "></div>
            </a>
            <ul class="dropdown-menu  pull-right" role="menu" aria-labelledby="user-options">
              <li><a href="<?php echo url_crm('profile.php'); ?>"> Mi cuenta</a> </li>
              <?php if(isset($_SESSION['cuentas_cliente']) && is_array($_SESSION['cuentas_cliente']) && count($_SESSION['cuentas_cliente'])>1) { ?>
              <li><a href="<?php echo url_crm('elegir-cuenta.php'); ?>"> Cambiar cuenta</a> </li>
              <?php } ?>
              <li class="divider"></li>
              <li><a href="<?php echo url_crm('logout.php'); ?>"><i class="fa fa-power-off"></i>&nbsp;&nbsp;Cerrar sesión</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
<!-- END HEADER -->
