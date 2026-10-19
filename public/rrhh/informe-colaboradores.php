<?php
require_once __DIR__.'/../_init.php';
crm_proteger_staff();

header('Location: '.url_crm('informes.php?reporte=colaboradores'));
exit();
