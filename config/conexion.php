<?php
// Conexión principal a la base de datos MySQL.
// Parámetros por defecto para desarrollo con puerto estándar 3306.
mysqli_report(MYSQLI_REPORT_OFF);

$crm_host = getenv('DB_HOST') ?: '127.0.0.1';
$crm_puerto = (int)(getenv('DB_PORT') ?: 3306);
$crm_usuario = getenv('DB_USER') ?: '';
$crm_clave = getenv('DB_PASS') ?: '';
$crm_base = getenv('DB_NAME') ?: 'crm_prueba';

// Carga de configuración local opcional (excluida en .gitignore)
if(is_file(__DIR__.'/conexion.local.php'))
{
    $local_cfg = require __DIR__.'/conexion.local.php';
    if(is_array($local_cfg))
    {
        if(!empty($local_cfg['host'])) $crm_host = $local_cfg['host'];
        if(isset($local_cfg['puerto'])) $crm_puerto = (int)$local_cfg['puerto'];
        if(isset($local_cfg['usuario'])) $crm_usuario = $local_cfg['usuario'];
        if(isset($local_cfg['clave'])) $crm_clave = $local_cfg['clave'];
        if(!empty($local_cfg['base'])) $crm_base = $local_cfg['base'];
    }
    elseif(is_numeric($local_cfg))
    {
        $crm_puerto = (int)$local_cfg;
    }
}

$con = mysqli_connect($crm_host, $crm_usuario, $crm_clave, $crm_base, $crm_puerto);
if(mysqli_connect_errno()){
    echo "Connection Fail: " . mysqli_connect_error();
}
else
{
    mysqli_set_charset($con, 'utf8mb4');
    @mysqli_query($con, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    @mysqli_query($con, "SET CHARACTER SET utf8mb4");
}
?>
