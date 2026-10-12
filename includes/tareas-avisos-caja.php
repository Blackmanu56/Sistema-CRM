<?php
// Recordatorio breve: el detalle está en la campanita.
$notif_n=function_exists('crm_notificaciones_contar') ? crm_notificaciones_contar() : 0;
if($notif_n>0) {
?>
                <div class="alert alert-info">
                    <p>Tenés <?php echo intval($notif_n); ?> notificación<?php echo $notif_n==1 ? '' : 'es'; ?> pendiente<?php echo $notif_n==1 ? '' : 's'; ?>. Revisá la campanita en la barra superior.</p>
                </div>
<?php } ?>
