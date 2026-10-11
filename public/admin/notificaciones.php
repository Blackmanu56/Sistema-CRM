<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff();
$menu_activo='notificaciones';
$solo=isset($_GET['ver']) && $_GET['ver']=='sin_leer' ? 'sin_leer' : 'todas';
$orden=isset($_GET['orden']) && $_GET['orden']=='asc' ? 'asc' : 'desc';
$lista=crm_notificaciones_historial(200);
$n_sin_leer=0;
foreach($lista as $n)
{
	if(!$n['leida']) { $n_sin_leer++; }
}
if($solo=='sin_leer')
{
	$lista=array_values(array_filter($lista, function($n) { return !$n['leida']; }));
}
if($orden=='asc')
{
	usort($lista, function($a, $b) {
		$ta=isset($a['fecha']) && $a['fecha']!='' ? strtotime($a['fecha']) : 0;
		$tb=isset($b['fecha']) && $b['fecha']!='' ? strtotime($b['fecha']) : 0;
		return $ta - $tb;
	});
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>CRM | Notificaciones</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
</head>
<body class="">
<?php crm_header_staff();?>
<div class="page-container row">
      <?php crm_menu_staff();?>
      <div class="clearfix"></div>
    </div>
  </div>
    <div class="page-content">
            <div class="clearfix"></div>
            <div class="content">
                <ul class="breadcrumb">
                    <li><p>ESTÁS AQUÍ</p></li>
                    <li><a href="#" class="active">Notificaciones</a></li>
                </ul>
                <div class="page-title">
                    <h3>Historial de notificaciones</h3>
                </div>
                <div class="grid simple">
                    <div class="grid-body no-border" style="padding-top:16px;">
                        <div class="crm-notif-barra">
                            <div class="btn-group">
                                <a href="notificaciones.php<?php echo $orden!='desc' ? '?orden='.urlencode($orden) : ''; ?>" class="btn btn-white btn-sm<?php echo $solo=='todas' ? ' active' : ''; ?>">Todas</a>
                                <a href="notificaciones.php?ver=sin_leer<?php echo $orden!='desc' ? '&orden='.urlencode($orden) : ''; ?>" class="btn btn-white btn-sm<?php echo $solo=='sin_leer' ? ' active' : ''; ?>">Sin leer (<?php echo intval($n_sin_leer); ?>)</a>
                            </div>
                            <a href="notificaciones.php?<?php echo $solo!='todas' ? 'ver='.urlencode($solo).'&' : ''; ?>orden=<?php echo $orden=='asc' ? 'desc' : 'asc'; ?>" class="btn btn-white btn-sm" title="Alternar orden: recientes o antiguas" style="margin-left:6px;"><i class="fa fa-sort-amount-<?php echo $orden=='asc' ? 'asc' : 'desc'; ?>"></i> <?php echo $orden=='asc' ? 'Antiguas primero' : 'Recientes primero'; ?></a>
                            <?php if($n_sin_leer>0) { ?>
                            <form method="post" action="" style="display:inline-block; margin:0 0 0 6px;">
                                <input type="hidden" name="notif_volver" value="<?php echo htmlspecialchars(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''); ?>">
                                <button type="submit" name="notif_marcar_todas" value="1" class="btn btn-primary btn-sm"><i class="fa fa-check-square-o"></i> Marcar todas como leídas</button>
                            </form>
                            <?php } ?>
                        </div>
                        <?php if(count($lista)==0) { ?>
                        <p class="muted" style="margin-top:16px;">No hay notificaciones para mostrar.</p>
                        <?php } else { ?>
                        <table class="table table-hover crm-notif-tabla">
                            <thead><tr><th style="width:130px;">Fecha</th><th style="width:90px;">Tipo</th><th>Detalle</th><th style="width:90px;">Estado</th><th style="width:70px;"></th></tr></thead>
                            <tbody>
                            <?php foreach($lista as $n) { ?>
                            <tr class="<?php echo $n['leida'] ? 'crm-notif-leida' : 'crm-notif-nueva'; ?>">
                                <td><?php echo htmlspecialchars($n['fecha']!='' ? date('d/m/Y H:i', strtotime($n['fecha'])) : ''); ?></td>
                                <td><span class="label"><?php echo htmlspecialchars($n['etiqueta']); ?></span></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($n['titulo']); ?></strong>
                                    <?php if(trim($n['detalle'])!='') { ?><br><span class="muted"><?php echo htmlspecialchars(preg_replace("/\r\n|\n|\r/", ' · ', $n['detalle'])); ?></span><?php } ?>
                                </td>
                                <td><?php echo $n['leida'] ? '<span class="muted">Leída</span>' : '<span class="label label-important">Sin leer</span>'; ?></td>
                                <td><?php if($n['url']!='') { ?><a class="btn btn-white btn-xs" href="<?php echo htmlspecialchars($n['url']); ?>">Abrir</a><?php } ?></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
 </div>
<script src="../assets/plugins/jquery-1.8.3.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-ui/jquery-ui-1.10.1.custom.min.js" type="text/javascript"></script>
<script src="../assets/plugins/boostrapv3/js/bootstrap.min.js" type="text/javascript"></script>
<script src="../assets/plugins/breakpoints.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-unveil/jquery.unveil.min.js" type="text/javascript"></script>
<script src="../assets/plugins/pace/pace.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-scrollbar/jquery.scrollbar.min.js" type="text/javascript"></script>
<script src="../assets/plugins/jquery-block-ui/jqueryblockui.js" type="text/javascript"></script>
<script src="../assets/js/core.js?v=20260926" type="text/javascript"></script>
</body>
</html>
