<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('empresas');
include("datos-tareas.php");
tareas_iniciar_contexto();
include BASE_PATH.'/includes/atenciones-pagina-lista.php';
