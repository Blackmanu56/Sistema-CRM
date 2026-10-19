<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff();
// Presidencia no ve el panel operativo en vivo; solo informes elevados.
if(acceso_es_presidencia() && !acceso_es_super())
{
	header('Location: '.url_crm('admin/informes-presidencia.php'));
	exit();
}
if(!acceso_es_super() && !acceso_es_coord_general())
{
	header('Location: '.acceso_destino_login());
	exit();
}
acceso_exigir_dashboard();
require_once BASE_PATH.'/includes/informes.php';

$filtro=isset($_GET['area']) ? $_GET['area'] : 'todas';
$areas_ok=acceso_etiquetas_area();
if($filtro!='' && $filtro!='todas' && !isset($areas_ok[$filtro]))
{
	$filtro='todas';
}
$tareas=informes_tareas_por_area($filtro);
$tot=$tareas['total'];
$clientes=informes_clientes_resumen();
$acts=informes_actividades_por_area();
$ver_todas=($filtro=='todas' || $filtro=='');
$titulo_filtro=$ver_todas ? 'Todas las áreas' : $areas_ok[$filtro];
$menu_activo='panel_operativo';
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Panel Operativo</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<meta content="" name="description" />
<meta content="" name="author" />
<link href="../assets/plugins/pace/pace-theme-flash.css" rel="stylesheet" type="text/css" media="screen"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/boostrapv3/css/bootstrap-theme.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/font-awesome/css/font-awesome.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/animate.min.css" rel="stylesheet" type="text/css"/>
<link href="../assets/plugins/jquery-scrollbar/jquery.scrollbar.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/style.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/responsive.css" rel="stylesheet" type="text/css"/>
<link href="../assets/css/custom-icon-set.css" rel="stylesheet" type="text/css"/>
<style>
.crm-operativo-page { padding-bottom: 24px; }
.crm-operativo-page .page-title { margin: 8px 0 16px; }
.crm-operativo-page .page-title h3 { color: #1e293b; font-size: 25px; font-weight: 700; letter-spacing: -.3px; }
.crm-ops-filter { margin: 0 0 18px; padding: 16px 18px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.04); }
.crm-ops-filter .crm-ops-filter-title { margin: 0 0 10px; color: #475569; font-size: 13px; font-weight: 700; }
.crm-ops-filter .form-inline { display: flex; align-items: center; flex-wrap: wrap; gap: 9px; margin: 0; }
.crm-ops-filter label { margin: 0; color: #334155; font-weight: 600; }
.crm-ops-filter select { min-width: 240px; }
.crm-ops-filter-summary { margin: 10px 0 0; color: #64748b; font-size: 12px; }
.crm-ops-filter-summary strong { color: #334155; }
.crm-ops-kpi .tiles { min-height: 112px; margin-bottom: 14px; overflow: hidden; border-radius: 10px; box-shadow: 0 4px 12px rgba(15,23,42,.10); }
.crm-ops-kpi .tiles-body { position: relative; padding: 16px 18px; }
.crm-ops-kpi .tiles-title { padding-right: 34px; font-size: 12px; font-weight: 600; letter-spacing: .2px; opacity: .95; }
.crm-ops-kpi .heading { margin-top: 7px; font-size: 28px; font-weight: 700; line-height: 1.1; }
.crm-ops-kpi-icon { position: absolute; top: 17px; right: 18px; font-size: 18px; opacity: .72; }
.crm-ops-section { margin: 0 0 16px; }
.crm-ops-panel { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.05); }
.crm-ops-panel .grid-title { min-height: 52px; padding: 14px 18px; border-bottom: 1px solid #edf1f5; background: #f8fafc; }
.crm-ops-panel .grid-title h4 { margin: 0; color: #334155; font-size: 15px; font-weight: 700; }
.crm-ops-panel .grid-title h4 i { margin-right: 8px; color: #2563eb; }
.crm-ops-panel .grid-body { padding: 14px 18px; }
.crm-ops-panel .table { margin: 0; }
.crm-ops-panel .table thead th { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .35px; text-transform: uppercase; white-space: nowrap; }
.crm-ops-panel .table tbody td { padding: 10px 12px; border-top-color: #edf1f5; color: #334155; vertical-align: middle; }
.crm-ops-panel .table tbody tr:hover { background: #f8fbff; }
.crm-ops-area-total { color: #1d4ed8 !important; font-weight: 700; }
.crm-ops-empty { margin: 0; padding: 18px; border: 1px dashed #d7e0ea; border-radius: 8px; background: #f8fafc; color: #64748b; text-align: center; }
@media (max-width: 767px) {
	.crm-operativo-page .page-title h3 { font-size: 22px; }
	.crm-ops-filter { padding: 14px; }
	.crm-ops-filter .form-inline { align-items: stretch; flex-direction: column; }
	.crm-ops-filter select { width: 100%; min-width: 0; }
	.crm-ops-panel .grid-body { padding: 12px; }
}
</style>
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
            <div class="content crm-operativo-page">
                <ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Panel Operativo</a></li></ul>
                <?php if(acceso_es_super() || acceso_es_coord_general()) { include BASE_PATH.'/public/general/menu-seccion.php'; } ?>
                <div class="page-title">
                    <h3>Panel Operativo</h3>
                </div>

                <div class="grid simple crm-ops-filter">
                    <div class="grid-body">
                        <p class="crm-ops-filter-title"><i class="fa fa-filter" aria-hidden="true"></i> Filtrar indicadores</p>
                        <form method="get" action="" class="form-inline">
                            <label for="panel-area-filter">Área</label>
                            <select id="panel-area-filter" name="area" class="form-control">
                                <option value="todas">Todas las áreas</option>
                                <?php foreach($areas_ok as $clave=>$titulo) { ?>
                                <option value="<?php echo informes_html($clave); ?>" <?php if($filtro==$clave) echo 'selected'; ?>><?php echo informes_html($titulo); ?></option>
                                <?php } ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Ver</button>
                        </form>
                        <p class="crm-ops-filter-summary"><strong>Mostrando:</strong> <?php echo informes_html($titulo_filtro); ?></p>
                    </div>
                </div>

                <div class="row crm-ops-kpi">
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles green added-margin">
                            <div class="tiles-body">
                                <i class="fa fa-check-circle crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">Tareas realizadas</div>
                                <div class="heading"><?php echo intval($tot['Terminada']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles purple added-margin">
                            <div class="tiles-body">
                                <i class="fa fa-refresh crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">En curso</div>
                                <div class="heading"><?php echo intval($tot['En proceso']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles red added-margin">
                            <div class="tiles-body">
                                <i class="fa fa-hourglass-start crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">No iniciadas</div>
                                <div class="heading"><?php echo intval($tot['No iniciada']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles black added-margin">
                            <div class="tiles-body">
                                <i class="fa fa-pause-circle crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">Pausadas</div>
                                <div class="heading"><?php echo intval($tot['Pausada']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row crm-ops-kpi">
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles blue added-margin">
                            <div class="tiles-body">
                                <i class="fa fa-exclamation-triangle crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">Vencidas</div>
                                <div class="heading"><?php echo intval($tot['Vencida']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles added-margin" style="background:#1f7a8c;">
                            <div class="tiles-body">
                                <i class="fa fa-tasks crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">Total de tareas</div>
                                <div class="heading"><?php echo intval($tot['total']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles added-margin" style="background:#c47c16;">
                            <div class="tiles-body">
                                <i class="fa fa-users crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">Clientes</div>
                                <div class="heading"><?php echo intval($clientes['total']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles added-margin" style="background:#5b8c1a;">
                            <div class="tiles-body">
                                <i class="fa fa-user-plus crm-ops-kpi-icon" aria-hidden="true"></i><div class="tiles-title">Clientes nuevos del mes</div>
                                <div class="heading"><?php echo intval($clientes['mes']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid simple crm-ops-panel crm-ops-section">
                    <div class="grid-title no-border">
                        <h4><i class="fa fa-sitemap" aria-hidden="true"></i>Tareas por área</h4>
                    </div>
                    <div class="grid-body no-border">
                        <div class="table-responsive"><table class="table table-hover no-more-tables">
                            <thead>
                                <tr>
                                    <th>Área</th>
                                    <th>Realizadas</th>
                                    <th>En curso</th>
                                    <th>No iniciadas</th>
                                    <th>Pausadas</th>
                                    <th>Vencidas</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($tareas['areas'] as $fila) { ?>
                                <tr>
                                    <td><?php echo informes_html($fila['titulo']); ?></td>
                                    <td><?php echo intval($fila['Terminada']); ?></td>
                                    <td><?php echo intval($fila['En proceso']); ?></td>
                                    <td><?php echo intval($fila['No iniciada']); ?></td>
                                    <td><?php echo intval($fila['Pausada']); ?></td>
                                    <td><?php echo intval($fila['Vencida']); ?></td>
                                    <td class="crm-ops-area-total"><?php echo intval($fila['total']); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if(count($tareas['areas'])==0) { ?><tr><td colspan="7"><p class="crm-ops-empty">No hay tareas para mostrar en esta selección.</p></td></tr><?php } ?>
                            </tbody>
                        </table></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="grid simple crm-ops-panel crm-ops-section">
                            <div class="grid-title no-border">
                                <h4><i class="fa fa-tags" aria-hidden="true"></i>Clientes por tipo</h4>
                            </div>
                            <div class="grid-body no-border">
                                <?php if($clientes['total']==0) { ?>
                                <p class="crm-ops-empty">Todavía no hay clientes cargados.</p>
                                <?php } else { ?>
                                <div class="table-responsive"><table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($clientes['por_tipo'] as $fila_t) { ?>
                                        <tr>
                                            <td><?php echo informes_html($fila_t['titulo']); ?></td>
                                            <td><?php echo intval($fila_t['total']); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table></div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="grid simple crm-ops-panel crm-ops-section">
                            <div class="grid-title no-border">
                                <h4><i class="fa fa-building-o" aria-hidden="true"></i>Clientes por área</h4>
                            </div>
                            <div class="grid-body no-border">
                                <?php if($clientes['total']==0) { ?>
                                <p class="crm-ops-empty">Todavía no hay clientes cargados.</p>
                                <?php } else { ?>
                                <div class="table-responsive"><table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>Área</th>
                                            <th>Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($clientes['por_area'] as $nom=>$cant) { ?>
                                        <tr>
                                            <td><?php echo informes_html($nom); ?></td>
                                            <td><?php echo intval($cant); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table></div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row crm-ops-section">
                    <div class="col-md-12">
                        <div class="grid simple crm-ops-panel">
                            <div class="grid-title no-border">
                                <h4><i class="fa fa-calendar" aria-hidden="true"></i>Actividades cargadas</h4>
                            </div>
                            <div class="grid-body no-border">
                                <p class="crm-ops-filter-summary">Total de actividades: <strong><?php echo intval($acts['total']); ?></strong></p>
                                <?php if(intval($acts['total'])===0) { ?><p class="crm-ops-empty">Todavía no hay actividades cargadas.</p><?php } else { ?>
                                <div class="table-responsive"><table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>Área</th>
                                            <th>Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach($acts['areas'] as $fila) { ?>
                                        <tr>
                                            <td><?php echo informes_html($fila['titulo']); ?></td>
                                            <td><?php echo intval($fila['total']); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table></div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid simple crm-ops-panel crm-ops-section">
                    <div class="grid-title no-border">
                        <h4><i class="fa fa-user-plus" aria-hidden="true"></i>Clientes nuevos (últimas altas)</h4>
                    </div>
                    <div class="grid-body no-border">
                        <?php if(count($clientes['recientes'])==0) { ?>
                        <p class="crm-ops-empty">Todavía no hay altas para mostrar.</p>
                        <?php } else { ?>
                        <div class="table-responsive"><table class="table table-hover no-more-tables">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Área</th>
                                    <th>Fecha de alta</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($clientes['recientes'] as $cli) { ?>
                                <tr>
                                    <td><?php echo informes_html($cli['nombre']); ?></td>
                                    <td><?php echo informes_html(isset($cli['tipo']) ? $cli['tipo'] : ''); ?></td>
                                    <td><?php echo informes_html($cli['area']); ?></td>
                                    <td><?php echo informes_html($cli['fecha']); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table></div>
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
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
</body>
</html>
