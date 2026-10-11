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
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Informes</title>
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
                <ul class="breadcrumb"><li><p>ESTÁS AQUÍ</p></li><li><a href="#" class="active">Informes</a></li></ul>
                <div class="page-title">
                    <h3>Informes</h3>
                </div>

                <div class="grid simple">
                    <div class="grid-body">
                        <form method="get" action="" class="form-inline">
                            <label>Ver tareas por área</label>
                            <select name="area" class="form-control" style="min-width:240px; display:inline-block;">
                                <option value="todas">Todas las áreas</option>
                                <?php foreach($areas_ok as $clave=>$titulo) { ?>
                                <option value="<?php echo informes_html($clave); ?>" <?php if($filtro==$clave) echo 'selected'; ?>><?php echo informes_html($titulo); ?></option>
                                <?php } ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Ver</button>
                        </form>
                        <p style="margin-top:10px;"><strong>Mostrando:</strong> <?php echo informes_html($titulo_filtro); ?></p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles green added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">Tareas realizadas</div>
                                <div class="heading"><?php echo intval($tot['Terminada']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles purple added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">En curso</div>
                                <div class="heading"><?php echo intval($tot['En proceso']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles red added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">No iniciadas</div>
                                <div class="heading"><?php echo intval($tot['No iniciada']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles black added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">Pausadas</div>
                                <div class="heading"><?php echo intval($tot['Pausada']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles blue added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">Vencidas</div>
                                <div class="heading"><?php echo intval($tot['Vencida']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles added-margin" style="background:#1f7a8c;">
                            <div class="tiles-body">
                                <div class="tiles-title">Total de tareas</div>
                                <div class="heading"><?php echo intval($tot['total']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles added-margin" style="background:#c47c16;">
                            <div class="tiles-body">
                                <div class="tiles-title">Clientes</div>
                                <div class="heading"><?php echo intval($clientes['total']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <div class="tiles added-margin" style="background:#5b8c1a;">
                            <div class="tiles-body">
                                <div class="tiles-title">Clientes nuevos del mes</div>
                                <div class="heading"><?php echo intval($clientes['mes']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border">
                        <h4>Tareas por área</h4>
                    </div>
                    <div class="grid-body no-border">
                        <table class="table table-hover no-more-tables">
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
                                    <td><?php echo intval($fila['total']); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="grid simple">
                            <div class="grid-title no-border">
                                <h4>Clientes por tipo</h4>
                            </div>
                            <div class="grid-body no-border">
                                <?php if($clientes['total']==0) { ?>
                                <p>Todavía no hay clientes cargados.</p>
                                <?php } else { ?>
                                <table class="table table-hover no-more-tables">
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
                                </table>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="grid simple">
                            <div class="grid-title no-border">
                                <h4>Clientes por área</h4>
                            </div>
                            <div class="grid-body no-border">
                                <?php if($clientes['total']==0) { ?>
                                <p>Todavía no hay clientes cargados.</p>
                                <?php } else { ?>
                                <table class="table table-hover no-more-tables">
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
                                </table>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                            <div class="grid-title no-border">
                                <h4>Actividades cargadas</h4>
                            </div>
                            <div class="grid-body no-border">
                                <p>Total: <strong><?php echo intval($acts['total']); ?></strong></p>
                                <table class="table table-hover no-more-tables">
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
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid simple">
                    <div class="grid-title no-border">
                        <h4>Clientes nuevos (últimas altas)</h4>
                    </div>
                    <div class="grid-body no-border">
                        <?php if(count($clientes['recientes'])==0) { ?>
                        <p>Todavía no hay altas para mostrar.</p>
                        <?php } else { ?>
                        <table class="table table-hover no-more-tables">
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
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
</body>
</html>
