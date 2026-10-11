<?php
require_once __DIR__.'/../_init.php';
error_reporting(0);
crm_proteger_staff('rrhh');
// El inicio del area pasa a las tareas, igual que el resto.
header("Location: tareas.php");
exit();
?>
