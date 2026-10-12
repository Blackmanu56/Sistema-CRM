<div class="header navbar navbar-inverse ">
  <div class="navbar-inner">
    <div class="header-seperation">
      <ul class="nav pull-left notifcation-center" id="main-menu-toggle-wrapper" style="display:none">
        <li class="dropdown"> <a id="main-menu-toggle" href="#main-menu"  class="" >
          <div class="iconset top-menu-toggle-white"></div>
          </a> </li>
      </ul>
      <a href="<?php echo function_exists('crm_dashboard_destino') ? crm_dashboard_destino() : url_crm('admin/home.php'); ?>" class="crm-brand-link">ADEMI</a>
      <ul class="nav pull-right notifcation-center">
      </ul>
    </div>
    <div class="header-quick-nav" >
      <div class="pull-left">
        <ul class="nav quick-section">
        </ul>
      </div>
      <div class="pull-right">
        <ul class="nav quick-section ">
          <?php
          $crm_pag_hdr=basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
          $notif_lista=($crm_pag_hdr==='change-password.php') ? array() : (function_exists('crm_notificaciones_mias') ? crm_notificaciones_mias() : array());
          $notif_total=count($notif_lista);
          $notif_n=0;
          foreach($notif_lista as $n_item)
          {
            if(empty($n_item['vista']))
            {
              $notif_n++;
            }
          }
          $notif_volver=isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
          ?>
          <style type="text/css">
            #crm-campana-menu {
              width: 440px;
              max-height: 480px;
              overflow: auto;
              padding: 0;
              border: 0;
              border-radius: 8px;
              box-shadow: 0 12px 28px rgba(40,50,60,.18);
              margin-top: 8px;
            }
            #crm-campana-menu .crm-n-head {
              padding: 12px 14px 10px;
              background: linear-gradient(135deg, #2f4050 0%, #3d5266 100%);
              color: #fff;
            }
            #crm-campana-menu .crm-n-head strong { font-size: 14px; letter-spacing: .2px; }
            #crm-campana-menu .crm-n-head span { opacity: .85; font-size: 12px; margin-left: 8px; }
            #crm-campana-menu .crm-n-head-acc {
              display: flex;
              align-items: center;
              gap: 6px;
              margin-top: 10px;
              flex-wrap: nowrap;
            }
            #crm-campana-menu .crm-n-head-acc form { margin: 0; flex: 1; display: flex; }
            #crm-campana-menu .crm-n-head-acc button,
            #crm-campana-menu .crm-n-head-acc a {
              flex: 1;
              display: inline-flex;
              align-items: center;
              justify-content: center;
              gap: 5px;
              background: rgba(255,255,255,.14);
              border: 1px solid rgba(255,255,255,.3);
              color: #fff;
              border-radius: 5px;
              padding: 5px 6px;
              font-size: 11px;
              font-weight: 600;
              text-decoration: none;
              cursor: pointer;
              white-space: nowrap;
              box-sizing: border-box;
              text-align: center;
              transition: all .15s ease;
            }
            #crm-campana-menu .crm-n-head-acc button:hover,
            #crm-campana-menu .crm-n-head-acc a:hover {
              background: rgba(255,255,255,.26);
              color: #fff;
              border-color: rgba(255,255,255,.5);
            }
            #crm-campana-menu .crm-n-empty {
              padding: 28px 20px;
              text-align: center;
              color: #7a8694;
              background: #f7f9fb;
            }
            #crm-campana-menu .crm-n-empty i { font-size: 28px; color: #b8c2cc; display: block; margin-bottom: 10px; }
            #crm-campana-menu .crm-n-item {
              padding: 13px 16px 12px;
              border-bottom: 1px solid #eef1f4;
              background: #fff;
              transition: background .15s ease;
            }
            #crm-campana-menu .crm-n-item:hover { background: #f5f8fb; }
            #crm-campana-menu .crm-n-item:last-child { border-bottom: 0; }
            #crm-campana-menu .crm-n-tag {
              display: inline-block;
              font-size: 10px;
              font-weight: 600;
              letter-spacing: .4px;
              text-transform: uppercase;
              padding: 2px 8px;
              border-radius: 10px;
              margin-bottom: 7px;
              line-height: 1.6;
            }
            #crm-campana-menu .crm-n-tag-turno { background: #e8f4ff; color: #2a6fad; }
            #crm-campana-menu .crm-n-tag-tarea { background: #e9f7ef; color: #2d7a4f; }
            #crm-campana-menu .crm-n-tag-vencida { background: #fdecea; color: #b33a3a; }
            #crm-campana-menu .crm-n-tag-cambio { background: #fff6e5; color: #9a6b12; }
            #crm-campana-menu .crm-n-tag-eliminar { background: #f3e8ff; color: #6b3fa0; }
            #crm-campana-menu .crm-n-tag-parte { background: #eef7f5; color: #1f6f64; }
            #crm-campana-menu .crm-n-tag-aviso { background: #eef0f3; color: #5a6570; }
            #crm-campana-menu .crm-n-title {
              margin: 0 0 4px 0;
              font-size: 13px;
              font-weight: 600;
              color: #2c3640;
              line-height: 1.35;
            }
            #crm-campana-menu .crm-n-det {
              margin: 0 0 10px 0;
              font-size: 12px;
              color: #6b7785;
              line-height: 1.45;
            }
            #crm-campana-menu .crm-n-actions {
              display: flex;
              flex-wrap: wrap;
              align-items: stretch;
              justify-content: center;
              gap: 8px;
              margin-top: 4px;
            }
            #crm-campana-menu .crm-n-actions form { margin: 0; display: inline-flex; }
            #crm-campana-menu .crm-n-actions a.crm-n-ver,
            #crm-campana-menu .crm-n-actions button.crm-n-ok {
              display: inline-flex;
              align-items: center;
              justify-content: center;
              gap: 6px;
              padding: 7px 14px;
              border-radius: 6px;
              font-size: 12px;
              font-weight: 600;
              line-height: 1.2;
              cursor: pointer;
              text-decoration: none;
              box-sizing: border-box;
              min-height: 32px;
              transition: background .15s ease, color .15s ease, box-shadow .15s ease, border-color .15s ease;
            }
            #crm-campana-menu .crm-n-actions a.crm-n-ver {
              background: #0f766e;
              color: #fff !important;
              border: 1px solid #0f766e;
              box-shadow: none;
            }
            #crm-campana-menu .crm-n-actions a.crm-n-ver:hover {
              background: #0d9488;
              border-color: #0d9488;
              color: #fff !important;
            }
            #crm-campana-menu .crm-n-actions button.crm-n-ok {
              background: #fff;
              color: #475569;
              border: 1px solid #cbd5e1;
              box-shadow: none;
            }
            #crm-campana-menu .crm-n-actions button.crm-n-ok:hover {
              background: #f1f5f9;
              color: #1e293b;
              border-color: #94a3b8;
            }
            #crm-campana-menu .crm-n-btn-orden {
              display: inline-flex;
              align-items: center;
              gap: 4px;
              background: transparent;
              color: rgba(255,255,255,0.9);
              border: 1px solid rgba(255,255,255,0.3);
              border-radius: 4px;
              padding: 2px 7px;
              font-size: 11px;
              font-weight: 500;
              cursor: pointer;
              line-height: 1.3;
              transition: all .15s ease;
              text-decoration: none;
            }
            #crm-campana-menu .crm-n-btn-orden:hover {
              background: rgba(255,255,255,0.15);
              border-color: rgba(255,255,255,0.6);
              color: #fff;
            }
            #crm-campana.crm-campana-on i { color: #d45b5b !important; }
            #crm-campana-menu .crm-n-item:last-child { padding-bottom: 14px; }
          </style>
          <li class="quicklinks" style="margin-right:8px;">
            <a data-toggle="dropdown" class="dropdown-toggle pull-right<?php echo $notif_n>0 ? ' crm-campana-on' : ''; ?>" href="#" id="crm-campana" title="Notificaciones" style="padding:8px 10px;">
              <i class="fa fa-bell" style="font-size:17px; color:#505458;"></i>
              <?php if($notif_n>0) { ?>
              <span class="badge badge-important" style="position:relative; top:-10px; left:-6px; min-width:16px; border-radius:10px;"><?php echo intval($notif_n); ?></span>
              <?php } ?>
            </a>
            <ul class="dropdown-menu pull-right" role="menu" aria-labelledby="crm-campana" id="crm-campana-menu">
              <li class="crm-n-head">
                <strong><i class="fa fa-inbox" style="margin-right:6px; opacity:.9;"></i>Tu bandeja</strong>
                <?php if($notif_n>0) { ?>
                <span><?php echo intval($notif_n); ?> nueva<?php echo $notif_n>1 ? 's' : ''; ?></span>
                <?php } else if($notif_total>0) { ?>
                <span><?php echo intval($notif_total); ?> pendiente<?php echo $notif_total>1 ? 's' : ''; ?></span>
                <?php } else { ?>
                <span>al día</span>
                <?php } ?>
                <div class="crm-n-head-acc">
                  <button type="button" class="crm-n-btn-orden" id="crm-n-orden-btn" title="Alternar orden: recientes o antiguas"><i class="fa fa-sort-amount-desc" id="crm-n-orden-icon"></i> <span id="crm-n-orden-txt">Recientes</span></button>
                  <?php if($notif_total>0) { ?>
                  <form method="post" action="">
                    <input type="hidden" name="notif_volver" value="<?php echo htmlspecialchars($notif_volver); ?>">
                    <button type="submit" name="notif_marcar_todas" value="1" title="Marcar todas como leídas"><i class="fa fa-check-square-o"></i> Marcar leídas</button>
                  </form>
                  <?php } ?>
                  <a href="<?php echo url_crm('admin/notificaciones.php'); ?>" title="Ver historial de notificaciones"><i class="fa fa-history"></i> Historial</a>
                </div>
              </li>
              <?php if($notif_total==0) { ?>
              <li class="crm-n-empty">
                <i class="fa fa-coffee"></i>
                Todo tranquilo por ahora.<br>Cuando llegue una atención o una tarea, aparece acá.
              </li>
              <?php } foreach($notif_lista as $n) {
              	$etiq=isset($n['etiqueta']) ? $n['etiqueta'] : '';
              	if($etiq=='' && isset($n['tipo']))
              	{
              		if($n['tipo']=='turno') { $etiq='Atención'; }
              		else if($n['tipo']=='tarea') { $etiq='Tarea'; }
              		else { $etiq='Aviso'; }
              	}
              	$tag_class='crm-n-tag-aviso';
              	$el=mb_strtolower($etiq, 'UTF-8');
              	if($el=='turno') { $tag_class='crm-n-tag-turno'; }
              	else if($el=='tarea') { $tag_class='crm-n-tag-tarea'; }
              	else if($el=='vencida') { $tag_class='crm-n-tag-vencida'; }
              	else if($el=='cambio') { $tag_class='crm-n-tag-cambio'; }
              	else if($el=='eliminar') { $tag_class='crm-n-tag-eliminar'; }
              	else if($el=='parte' || $el=='subtarea') { $tag_class='crm-n-tag-parte'; }
              	else if($el=='actividad') { $tag_class='crm-n-tag-turno'; }
              	$det=isset($n['detalle']) ? $n['detalle'] : '';
              	$det=preg_replace("/\r\n|\n|\r/", ' · ', $det);
              	if(function_exists('mb_strlen') && mb_strlen($det, 'UTF-8')>140)
              	{
              		$det=mb_substr($det, 0, 137, 'UTF-8').'…';
              	}
              	else if(strlen($det)>140)
              	{
              		$det=substr($det, 0, 137).'…';
              	}
              ?>
              <li class="crm-n-item" data-ts="<?php echo intval(isset($n['fecha']) && $n['fecha']!='' ? strtotime($n['fecha']) : 0); ?>" data-id="<?php echo intval(isset($n['id']) ? $n['id'] : 0); ?>" style="<?php echo !empty($n['vista']) ? 'background:#f8fafc;' : ''; ?>">
                <?php if($etiq!='') { ?>
                <span class="crm-n-tag <?php echo $tag_class; ?>"><?php echo htmlspecialchars($etiq); ?></span>
                <?php } ?>
                <?php if(!empty($n['vista'])) { ?>
                <span class="crm-n-tag" style="background:#e2e8f0; color:#475569; font-weight:normal;"><i class="fa fa-eye" style="margin-right:3px;"></i>Vista</span>
                <?php } ?>
                <p class="crm-n-title"><?php echo htmlspecialchars($n['titulo']); ?></p>
                <?php if(trim($det)!='') { ?>
                <p class="crm-n-det"><?php echo htmlspecialchars($det); ?></p>
                <?php } ?>
                <div class="crm-n-actions">
                  <?php 
                    $u_abrir = isset($n['url_abrir']) && $n['url_abrir']!='' ? $n['url_abrir'] : (isset($n['url']) ? $n['url'] : '');
                    if($u_abrir!='') { 
                  ?>
                  <a class="crm-n-ver" href="<?php echo htmlspecialchars($u_abrir); ?>"><i class="fa fa-external-link"></i> Abrir</a>
                  <?php } ?>
                  <form method="post" action="">
                    <input type="hidden" name="notif_volver" value="<?php echo htmlspecialchars($notif_volver); ?>">
                    <input type="hidden" name="<?php echo htmlspecialchars($n['campo']); ?>" value="<?php echo htmlspecialchars((string)$n['id']); ?>">
                    <button type="submit" class="crm-n-ok"><i class="fa fa-check"></i> Marcar leída</button>
                  </form>
                </div>
              </li>
              <?php } ?>
            </ul>
          </li>
          <li class="quicklinks"> <span class="h-seperate"></span></li>
          <li class="quicklinks"> <a data-toggle="dropdown" class="dropdown-toggle  pull-right " href="#" id="user-options">
            <div class="iconset top-settings-dark "></div>
            </a>
            <ul class="dropdown-menu  pull-right" role="menu" aria-labelledby="user-options">
              <li><a href="<?php echo url_crm('change-password.php'); ?>"><i class="fa fa-lock"></i>&nbsp;&nbsp;Cambiar contraseña</a></li>
              <li class="divider"></li>
              <li><a href="<?php echo url_crm('admin/logout.php'); ?>"><i class="fa fa-power-off"></i>&nbsp;&nbsp;Cerrar sesión</a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>
<script type="text/javascript">
(function(){
  function initCampanaOrden() {
    var btn = document.getElementById('crm-n-orden-btn');
    if(!btn || btn._ordenBound) return;
    btn._ordenBound = true;
    var ordenDesc = true;
    btn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      var menu = document.getElementById('crm-campana-menu');
      if(!menu) return;
      var items = Array.prototype.slice.call(menu.querySelectorAll('li.crm-n-item'));
      if(items.length <= 1) return;
      ordenDesc = !ordenDesc;
      var icon = document.getElementById('crm-n-orden-icon');
      var txt = document.getElementById('crm-n-orden-txt');
      if(ordenDesc) {
        if(icon) icon.className = 'fa fa-sort-amount-desc';
        if(txt) txt.textContent = 'Recientes';
      } else {
        if(icon) icon.className = 'fa fa-sort-amount-asc';
        if(txt) txt.textContent = 'Antiguas';
      }
      items.sort(function(a, b){
        var tsa = parseInt(a.getAttribute('data-ts') || '0', 10);
        var tsb = parseInt(b.getAttribute('data-ts') || '0', 10);
        if(tsa === tsb) {
          var ida = parseInt(a.getAttribute('data-id') || '0', 10);
          var idb = parseInt(b.getAttribute('data-id') || '0', 10);
          return ordenDesc ? (idb - ida) : (ida - idb);
        }
        return ordenDesc ? (tsb - tsa) : (tsa - tsb);
      });
      items.forEach(function(item){
        menu.appendChild(item);
      });
    });
  }
  if(document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCampanaOrden);
  } else {
    initCampanaOrden();
  }
})();
</script>
<!-- END HEADER -->
