<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('recepcion');
include("datos-tareas.php");
tareas_iniciar_contexto();
include BASE_PATH.'/includes/actividades-pagina-nueva.php';
