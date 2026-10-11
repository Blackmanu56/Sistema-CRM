<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('vinculaciones');

include("datos-tareas.php");
tareas_iniciar_contexto();
// El inicio del area pasa directo a las tareas, que es lo que esta armado.
header("Location: ".tareas_link('tareas.php'));
exit();
?>
