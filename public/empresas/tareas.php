<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('empresas');

include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='tareas';
if(tareas_procesar_accion_si_vino())
{
	header("Location: ".tareas_link('tareas.php'));
	exit();
}


$lista=crm_tareas_preparar_lista(crm_tareas_filtrar_cliente(tareas_filtradas()));
$tareas_calendario=tareas_visibles();
$contadores=crm_tareas_contar($tareas_calendario);

// Mes visible del calendario; un día seleccionado determina el mes mostrado.
$dia_cal=isset($_GET['dia']) && function_exists('crm_tarea_fecha_filtro_valida') && crm_tarea_fecha_filtro_valida($_GET['dia']) ? $_GET['dia'] : '';
$cal=isset($_GET['cal']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',(string)$_GET['cal']) && checkdate(1,1,intval(substr($_GET['cal'],0,4))) ? $_GET['cal'] : date('Y-m');
if($dia_cal!=='') { $cal=substr($dia_cal,0,7); }
$cal_partes=explode('-', $cal);
$cal_anio=intval($cal_partes[0]);
$cal_mes=intval($cal_partes[1]);
$cal_prev=date('Y-m', strtotime($cal.'-01 -1 month'));
$cal_next=date('Y-m', strtotime($cal.'-01 +1 month'));
$dias_marcados=array();
$primer_dia=intval(date('N', strtotime($cal.'-01')));
$dias_del_mes=intval(date('t', strtotime($cal.'-01')));
$hoy=date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Empresas - Tareas</title>
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
<body class="crm-tareas-page">
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
                    <li>
                        <p>ESTÁS AQUÍ</p>
                    </li>
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>">Coordinación de empresas</a></li>
                    <li><a href="#" class="active">Tareas</a></li>
                </ul>

                <?php include("menu-seccion.php"); ?>
                <?php include BASE_PATH.'/includes/tareas-avisos-caja.php'; ?>

                <div class="page-title crm-compact-heading">
                    <h3><?php echo acceso_es_empleado() ? 'Mis tareas' : 'Tareas'; ?></h3>
                    <a href="<?php echo tareas_link('nueva-tarea.php'); ?>" class="btn btn-primary btn-cons pull-right">Nueva tarea</a>
                </div>
                <p>Gestión y seguimiento de las tareas del área Coordinación de empresas.</p>

                <!-- Contadores: pendiente, en proceso, terminada y vencida. -->
                <div class="row">
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <a href="<?php echo tareas_link_filtro(array('estado'=>'No iniciada')); ?>">
                        <div class="tiles red added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">No iniciadas</div>
                                <div class="heading"><?php echo intval($contadores['Pendiente']); ?></div>
                            </div>
                        </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <a href="<?php echo tareas_link_filtro(array('estado'=>'En proceso')); ?>">
                        <div class="tiles purple added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">En proceso</div>
                                <div class="heading"><?php echo intval($contadores['En proceso']); ?></div>
                            </div>
                        </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <a href="<?php echo tareas_link_filtro(array('estado'=>'Terminada')); ?>">
                        <div class="tiles green added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">Terminadas</div>
                                <div class="heading"><?php echo intval($contadores['Terminada']); ?></div>
                            </div>
                        </div>
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 spacing-bottom">
                        <a href="<?php echo tareas_link_filtro(array('estado'=>'Vencida')); ?>">
                        <div class="tiles black added-margin">
                            <div class="tiles-body">
                                <div class="tiles-title">Vencidas</div>
                                <div class="heading"><?php echo intval($contadores['Vencida']); ?></div>
                            </div>
                        </div>
                        </a>
                    </div>
                </div>

                <!-- Filtros de la lista. Al cambiar uno se recarga la pagina. -->
                <div class="grid simple crm-tareas-filter-panel">
                    <div class="grid-body">
                        <form method="get" action="tareas.php" class="form-inline crm-tareas-filtros"><?php echo crm_tareas_filtros_campos_html($cal,$dia_cal); ?></form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <?php echo crm_tareas_filtro_activo_html(); ?>

                        <?php if(count($lista)==0){ ?>
                        <div class="grid simple">
                            <div class="grid-body">
                                <p>No hay tareas para mostrar con estos filtros.</p>
                            </div>
                        </div>
                        <?php } ?>

                        <?php foreach($lista as $tarea){ ?>
                        <div class="grid simple">
                            <div class="grid-title">
                                <h4><?php echo texto_mostrar($tarea['titulo']); ?></h4>
                                <div class="tools crm-task-status">
                                    <span class="label <?php echo label_estado(tarea_texto_estado($tarea)); ?>"><?php echo texto_mostrar(tarea_texto_estado($tarea)); ?></span>
                                    <?php if(tarea_esta_vencida($tarea)){ ?>
                                    <span class="label label-important">Vencida</span>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="grid-body">
                                <?php include BASE_PATH.'/includes/tareas-lista-cuerpo.php'; ?>
                            </div>
                        </div>
                        <?php } ?>

                    </div>

                    <div class="col-md-4">
                        <!-- Calendario de tareas con filtros sincronizados. -->
                        <div class="grid simple">
                            <div class="grid-title crm-tareas-cal-grid-title">
                                <h4><i class="fa fa-calendar text-info"></i>Calendario de tareas</h4>
                                <?php echo crm_html_tareas_calendario_controles($cal_anio,$cal_mes); ?>
                            </div>
                            <div class="grid-body">
                                <?php include BASE_PATH.'/includes/tareas-calendario.php'; ?>
                            </div>
                        </div>

                        <?php include BASE_PATH.'/includes/tareas-historial.php'; ?>
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