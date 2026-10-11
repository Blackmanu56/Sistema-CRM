<?php
// Pantalla heredada del template viejo (tabla "ticket"). Ya no se usa.
require_once __DIR__.'/../_init.php';
crm_proteger_staff();
header('Location: '.url_crm('admin/home.php'));
exit();
