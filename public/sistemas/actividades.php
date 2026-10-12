<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('sistemas');
include("datos-tareas.php");
tareas_iniciar_contexto();
$menu_activo='Actividades';
$lista=sis_actividades_pagina();
$nombre_area='Sistemas';
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
<meta charset="utf-8" />
<title>CRM | <?php echo texto_mostrar($nombre_area); ?> - Actividades</title>
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
                    <li><a href="<?php echo tareas_link('tareas.php'); ?>"><?php echo texto_mostrar($nombre_area); ?></a></li>
                    <li><a href="#" class="active">Actividades</a></li>
                </ul>
                <?php include("menu-seccion.php"); ?>
                <div class="page-title">
                    <h3>Actividades</h3>
                </div>
                <p>Actividades registradas sobre las tareas del área (creación, avance, cierre).</p>

                <?php if(count($lista)==0){ ?>
                <div class="grid simple">
                    <div class="grid-body">
                        <p>Todavía no hay actividades registradas.</p>
                    </div>
                </div>
                <?php } ?>

                <?php foreach($lista as $item){ ?>
                <div class="grid simple">
                    <div class="grid-title">
                        <h4><?php echo texto_mostrar(isset($item['titulo']) ? $item['titulo'] : ''); ?></h4>
                    </div>
                    <div class="grid-body">
                        <p><?php echo texto_mostrar(isset($item['descripcion']) ? $item['descripcion'] : ''); ?></p>
                        <p><i class="fa fa-user"></i> <?php echo texto_mostrar(isset($item['responsable']) ? $item['responsable'] : ''); ?></p>
                        <p><strong>Fecha:</strong> <?php echo fecha_mostrar(isset($item['fecha']) ? $item['fecha'] : ''); ?></p>
                    </div>
                </div>
                <?php } ?>
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