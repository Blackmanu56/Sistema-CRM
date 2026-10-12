<?php
// Unica conexion a la base. Antes estaba copiada en varios lados.
mysqli_report(MYSQLI_REPORT_OFF);
// El puerto del equipo no se comparte en Git; por defecto se usa el de XAMPP.
$crm_puerto=3306;
if(is_file(__DIR__.'/conexion.local.php'))
{
    $crm_puerto=(int) require __DIR__.'/conexion.local.php';
}
$con=mysqli_connect("127.0.0.1", "root", "", "crm_prueba", $crm_puerto);
if(mysqli_connect_errno()){
echo "Connection Fail".mysqli_connect_error();
}
else
{
	mysqli_set_charset($con, 'utf8mb4');
	@mysqli_query($con, "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
	@mysqli_query($con, "SET CHARACTER SET utf8mb4");
}
?>
