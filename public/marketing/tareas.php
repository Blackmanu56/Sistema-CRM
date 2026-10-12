<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('marketing');

include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='tareas';
if(tareas_procesar_accion_si_vino())
{
	header("Location: ".tareas_link('tareas.php'));
	exit();
}


$lista=crm_tareas_preparar_lista(crm_tareas_filtrar_cliente(tareas_filtradas()));
$contadores=crm_tareas_contar(tareas_visibles());

// Mes del calendario: el que eligieron o el mes actual.
$cal=isset($_GET['cal']) && $_GET['cal']!='' ? $_GET['cal'] : date('Y-m');
$cal_partes=explode('-', $cal);
$cal_anio=isset($cal_partes[0]) ? intval($cal_partes[0]) : 2026;
$cal_mes=isset($cal_partes[1]) ? intval($cal_partes[1]) : 2;
if($cal_mes<1) { $cal_mes=1; }
if($cal_mes>12) { $cal_mes=12; }
$cal_prev=date('Y-m', strtotime($cal_anio.'-'.$cal_mes.'-01 -1 month'));
$cal_next=date('Y-m', strtotime($cal_anio.'-'.$cal_mes.'-01 +1 month'));
$dias_marcados=calendario_dias_con_tareas($cal_anio, $cal_mes, tareas_visibles());
$primer_dia=intval(date('N', strtotime($cal_anio.'-'.$cal_mes.'-01')));
$dias_del_mes=intval(date('t', strtotime($cal_anio.'-'.$cal_mes.'-01')));
$hoy=date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | Marketing - Tareas</title>
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
                <ul class="breadcrumb">
                    <li>
                        <p>ESTÁS AQUÍ</p>
                    </li>
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>">Marketing</a></li>
                    <li><a href="#" class="active">Tareas</a></li>
                </ul>

                <?php include("menu-seccion.php"); ?>
                <?php include BASE_PATH.'/includes/tareas-avisos-caja.php'; ?>

                <div class="page-title">
                    <h3><?php echo acceso_es_empleado() ? 'Mis tareas' : 'Tareas'; ?></h3>
                    <a href="<?php echo tareas_link('nueva-tarea.php'); ?>" class="btn btn-primary btn-cons pull-right">Nueva tarea</a>
                </div>
                <p>Gestión y seguimiento de las tareas del área Marketing.</p>

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
                <div class="grid simple">
                    <div class="grid-title">
                        <h4>Filtros</h4>
                    </div>
                    <div class="grid-body">
                        <form method="get" action="tareas.php" class="form-inline">
                            <input type="hidden" name="cal" value="<?php echo texto_mostrar($cal); ?>">
                            <div class="form-group">
                                <label>Mes</label>
                                <select name="mes" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <?php foreach(tareas_meses_disponibles() as $m){
                                        $partes=explode('-',$m);
                                        $etiqueta=mes_nombre($partes[1]).' '.$partes[0];
                                    ?>
                                    <option value="<?php echo texto_mostrar($m); ?>" <?php if(isset($_GET['mes']) && $_GET['mes']==$m) echo 'selected'; ?>><?php echo texto_mostrar($etiqueta); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php if(tareas_etiqueta_tipo()!='Área') { ?>
                            <div class="form-group">
                                <label>Área</label>
                                <select name="area" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todas</option>
                                    <?php foreach(tareas_valores_unicos('area') as $op){ ?>
                                    <option value="<?php echo texto_mostrar($op); ?>" <?php if(isset($_GET['area']) && $_GET['area']==$op) echo 'selected'; ?>><?php echo texto_mostrar($op); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php } ?>
                            <div class="form-group">
                                <label>Prioridad</label>
                                <select name="prioridad" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todas</option>
                                    <?php foreach(tareas_valores_unicos('prioridad') as $op){ ?>
                                    <option value="<?php echo texto_mostrar($op); ?>" <?php if(isset($_GET['prioridad']) && $_GET['prioridad']==$op) echo 'selected'; ?>><?php echo texto_mostrar($op); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?php echo texto_mostrar(tareas_etiqueta_tipo()); ?></label>
                                <select name="tipo" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <?php foreach(tareas_valores_unicos('tipo') as $op){ ?>
                                    <option value="<?php echo texto_mostrar($op); ?>" <?php if(isset($_GET['tipo']) && $_GET['tipo']==$op) echo 'selected'; ?>><?php echo texto_mostrar($op); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php if(!acceso_es_empleado()) { ?>
                            <div class="form-group">
                                <label>Responsable</label>
                                <select name="responsable" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <?php foreach(tareas_valores_unicos('responsable') as $op){ ?>
                                    <option value="<?php echo texto_mostrar($op); ?>" <?php if(isset($_GET['responsable']) && $_GET['responsable']==$op) echo 'selected'; ?>><?php echo texto_mostrar($op); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php } ?>
                            <div class="form-group">
                                <label>Estado</label>
                                <select name="estado" class="form-control" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <option value="No iniciada" <?php if(isset($_GET['estado']) && ($_GET['estado']=='No iniciada' || $_GET['estado']=='Pendiente')) echo 'selected'; ?>>No iniciada</option>
                                    <option value="En proceso" <?php if(isset($_GET['estado']) && $_GET['estado']=='En proceso') echo 'selected'; ?>>En proceso</option>
                                    <option value="Pausada" <?php if(isset($_GET['estado']) && $_GET['estado']=='Pausada') echo 'selected'; ?>>Pausada</option>
                                    <option value="Terminada" <?php if(isset($_GET['estado']) && $_GET['estado']=='Terminada') echo 'selected'; ?>>Terminada</option>
                                    <option value="Vencida" <?php if(isset($_GET['estado']) && $_GET['estado']=='Vencida') echo 'selected'; ?>>Vencida</option>
                                    <option value="Eliminada" <?php if(isset($_GET['estado']) && ($_GET['estado']=='Eliminada' || $_GET['estado']=='cancelada')) echo 'selected'; ?>>Eliminada</option>
                                </select>
                            </div>
                            <?php if(function_exists('crm_html_orden_tarea')) { echo crm_html_orden_tarea(); } ?>
                            <?php if(function_exists('crm_html_filtro_cliente_tarea')) { echo crm_html_filtro_cliente_tarea(); } ?>
                            <a href="<?php echo tareas_link('tareas.php'); ?>" class="btn btn-white btn-xs" style="margin-top:22px;">Limpiar</a>
                        </form>
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
                                <div class="tools">
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
                        <!-- Calendario simple del mes, sin librerias nuevas. -->
                        <div class="grid simple">
                            <div class="grid-title">
                                <h4><?php echo mes_nombre($cal_mes).' '.$cal_anio; ?></h4>
                                <div class="tools">
                                    <a href="<?php echo tareas_link_filtro(array('cal'=>$cal_prev)); ?>">&lt;</a>
                                    <a href="<?php echo tareas_link_filtro(array('cal'=>$cal_next)); ?>">&gt;</a>
                                </div>
                            </div>
                            <div class="grid-body">
                                <?php include BASE_PATH.'/includes/tareas-calendario.php'; ?>
                            </div>
                        </div>

                        <div class="grid simple">
                            <div class="grid-title">
                                <h4>Actividad reciente</h4>
                            </div>
                            <div class="grid-body">
                                <?php foreach(actividades_lista() as $act){ ?>
                                <p>
                                    <i class="fa <?php echo icono_actividad($act['tipo']); ?>"></i>
                                    <strong><?php echo texto_mostrar($act['titulo']); ?></strong>
                                    <span class="pull-right muted"><?php echo texto_mostrar($act['cuando']); ?></span><br>
                                    <?php echo texto_mostrar($act['detalle']); ?><br>
                                    <span class="muted"><?php echo texto_mostrar($act['extra']); ?></span>
                                </p>
                                <hr>
                                <?php } ?>
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
<script src="../assets/js/chat.js" type="text/javascript"></script>
<script src="../assets/js/demo.js" type="text/javascript"></script>
</body>
</html>
