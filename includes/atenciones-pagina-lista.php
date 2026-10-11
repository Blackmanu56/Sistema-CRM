<?php
// Listado de atenciones del área (también las ya cerradas/derivadas de cuando el cliente pasó por acá).
$area_cli=acceso_area_pantalla();
if(!acceso_puede_clientes_de_area($area_cli) && !crm_area_ve_todo($area_cli) && !acceso_puede($area_cli))
{
	header('Location: '.acceso_destino_login());
	exit();
}
$menu_activo='atenciones';
$etq=acceso_etiquetas_area();
$nombre_area=isset($etq[$area_cli]) ? $etq[$area_cli] : $area_cli;
$lista=crm_atenciones_listado($area_cli);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Atenciones</title>
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
                    <li><a href="#" class="active"><?php echo texto_mostrar($nombre_area); ?> / Atenciones</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Atenciones<?php if($area_cli=='recepcion' && isset($_GET['fecha']) && $_GET['fecha']=='hoy') { echo ' de hoy'; } ?></h3>
                    <?php if($area_cli=='recepcion') { ?>
                    <a href="nueva-atencion.php" class="btn btn-primary btn-cons pull-right">Dar turno</a>
                    <?php } ?>
                </div>
                <?php if($area_cli=='recepcion') { ?>
                    <?php if(isset($_GET['fecha']) && $_GET['fecha']=='hoy') { ?>
                    <p class="muted">Mostrando los turnos registrados para hoy. <a href="atenciones.php" class="btn btn-white btn-xs" style="margin-left: 8px;"><i class="fa fa-list"></i> Ver todas las atenciones</a></p>
                    <?php } else { ?>
                    <p class="muted">Atenciones y turnos registrados desde recepción. <a href="atenciones.php?fecha=hoy" class="btn btn-info btn-xs" style="margin-left: 8px;"><i class="fa fa-calendar"></i> Ver solo atenciones de hoy</a></p>
                    <?php } ?>
                <?php } else { ?>
                <p>Atenciones de esta área. Si el cliente pasó por acá y después se derivó, igual ves esa atención y qué se hizo aquí.</p>
                <?php } ?>
                <?php echo crm_html_busqueda_clientes(); ?>

                <div class="row">
                    <div class="col-md-12">
                        <div class="grid simple">
                            <div class="grid-title no-border"><h4>Listado</h4></div>
                            <div class="grid-body no-border">
                                <p class="muted"><?php echo count($lista); ?> atención(es) (máx. 200).</p>
                                <table class="table table-hover no-more-tables">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Cliente</th>
                                            <th>Estado</th>
                                            <th>Área</th>
                                            <th>Motivo</th>
                                            <th>Fecha del turno</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    if(count($lista)==0) { ?>
                                        <tr><td colspan="7">No hay atenciones para estos filtros.</td></tr>
                                    <?php } else {
                                        foreach($lista as $row) {
                                            $ft=isset($row['fecha_turno']) && $row['fecha_turno']!='' ? $row['fecha_turno'] : $row['fecha'];
                                    ?>
                                        <tr>
                                            <td><?php echo intval($row['id_atencion']); ?></td>
                                            <td><?php echo texto_mostrar($row['cliente_nombre']); ?></td>
                                            <td><?php echo texto_mostrar(crm_estado_texto($row['estado'])); ?></td>
                                            <td><?php echo texto_mostrar($row['area_nombre']); ?></td>
                                            <td><?php echo texto_mostrar(crm_motivo_texto($row['motivo'])); ?></td>
                                            <td><?php echo texto_mostrar(function_exists('crm_turno_fecha_texto') ? crm_turno_fecha_texto($ft) : $ft); ?></td>
                                            <td><a href="atencion.php?id=<?php echo intval($row['id_atencion']); ?>" class="btn btn-primary btn-xs">Abrir</a></td>
                                        </tr>
                                    <?php } } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
