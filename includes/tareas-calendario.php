<?php
// Calendar dates are derived only from the already area-scoped task list.
$dia_sel=isset($_GET['dia']) && crm_tarea_fecha_filtro_valida($_GET['dia']) ? $_GET['dia'] : '';
$eventos_mes=isset($tareas_calendario) ? crm_tareas_calendario_eventos($tareas_calendario,$cal_anio,$cal_mes) : array();
?>
<style>
.cal-compact-grid-mini{width:100%;border-collapse:collapse;table-layout:fixed}
.cal-compact-grid-mini th{text-align:center;font-size:12px;color:#64748b;padding:8px 0;font-weight:700;text-transform:uppercase}
.cal-compact-grid-mini td{text-align:center;padding:4px 0;height:40px;vertical-align:top;border-radius:6px;position:relative;transition:background-color .15s ease}
.cal-compact-grid-mini td:hover{background-color:#f1f5f9}
.cal-compact-grid-mini td a{display:flex;flex-direction:column;align-items:center;width:100%;color:inherit;text-decoration:none}
.cal-mini-num{font-size:13px;color:#334155;font-weight:600;display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%}
.cal-mini-hoy .cal-mini-num{background-color:#0088cc;color:#fff;font-weight:700;box-shadow:0 2px 4px rgba(0,136,204,.25)}
.cal-mini-sel{background-color:#d1fae5!important;border:1px solid #10b981!important}
.cal-mini-dots{display:flex;justify-content:center;gap:3px;margin-top:2px;height:7px}
.dot{width:6px;height:6px;border-radius:50%;display:inline-block}
.dot-blue{background-color:#0088cc}.dot-orange{background-color:#f8a900}
.cal-legend{display:flex;justify-content:space-around;margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9;font-size:12px;color:#64748b;font-weight:500}
.crm-tareas-cal-leyenda{gap:10px;flex-wrap:wrap}
.crm-tareas-cal-grid-title{display:block;padding:14px 16px!important;border-bottom:1px solid #e5e9ec;background:#fafbfc}
.crm-tareas-cal-grid-title h4{margin:0 0 10px;font-size:14px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px}
.crm-tareas-cal-grid-title + .grid-body{padding:10px 15px!important}
.crm-tareas-cal-controls{display:flex;align-items:center;gap:6px;width:100%;margin:0;white-space:nowrap;box-sizing:border-box}
.crm-tareas-cal-selectores{display:flex;align-items:center;gap:6px;flex:1;min-width:0;margin:0}
.crm-tareas-cal-controls .btn{height:32px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dce1e5;border-radius:4px;padding:0 9px;flex:0 0 auto}
.crm-tareas-cal-controls>a:first-child,.crm-tareas-cal-controls>a:last-child{width:34px;padding:0}
.crm-tareas-cal-controls>a:nth-last-child(2){min-width:75px;justify-content:center;font-size:12px;font-weight:600}
.crm-tareas-cal-selectores select{height:32px;max-width:none;padding:4px 8px;font-size:12px;font-weight:600;border:1px solid #dce1e5;border-radius:4px;color:#1e293b;background:#fff;cursor:pointer}
.crm-tareas-cal-selectores select:first-of-type{flex:1;min-width:0}
.crm-tareas-cal-selectores select:last-of-type{width:72px;flex:0 0 72px}
.crm-cal-links{margin:8px 0 0;font-size:12px;text-align:center}
.crm-task-status{display:flex;align-items:center;gap:5px;flex-wrap:wrap}
.crm-task-status .label{display:inline-block;line-height:1.3}
.crm-tarea-fechas{display:flex;flex-wrap:wrap;gap:6px 18px;margin:8px 0}
.crm-tarea-fechas span{white-space:nowrap}
.crm-tarea-card-meta{display:grid;grid-template-columns:minmax(0,1fr) minmax(220px,1.35fr) auto;align-items:center;gap:14px;margin:10px 0 2px}
.crm-tarea-card-persona{display:flex;flex-direction:column;gap:5px;min-width:0}
.crm-tarea-card-responsable,.crm-tarea-card-area{display:flex;align-items:flex-start;gap:7px;min-width:0;line-height:1.35}
.crm-tarea-card-responsable{color:#334155;font-weight:600}
.crm-tarea-card-area{padding-left:19px;color:#475569;font-size:13px}
.crm-tarea-card-responsable>i,.crm-tarea-card-area>i{flex:0 0 12px;margin-top:2px;color:#64748b;text-align:center}
.crm-tarea-card-responsable>span{min-width:0;overflow-wrap:normal;word-break:normal}
.crm-tarea-card-area>span{min-width:0;overflow-wrap:anywhere}
.crm-tarea-card-fechas{display:grid;grid-template-columns:minmax(0,1fr) 1px minmax(0,1fr);align-items:center;gap:12px;min-width:0;padding:9px 12px;border:1px solid #e8eef7;border-radius:6px;background:#f6f9fe}
.crm-tarea-card-fecha{display:flex;align-items:center;gap:8px;min-width:0}
.crm-tarea-card-fecha>i{flex:0 0 14px;color:#4d7fca;text-align:center}
.crm-tarea-card-fecha>span{display:flex;flex-direction:column;min-width:0;line-height:1.3}
.crm-tarea-card-fecha strong{color:#64748b;font-size:10px;font-weight:600}
.crm-tarea-card-fecha>span>span{color:#1e293b;font-size:13px;font-weight:600;white-space:nowrap}
.crm-tarea-card-fecha-separador{display:block;width:1px;height:28px;background:#dbe4ef}
.crm-tarea-card-accion{display:flex;align-items:center;justify-content:flex-end}
.crm-tarea-card-accion .btn{min-width:92px;margin:0}
@media(max-width:991px){.crm-tarea-card-meta{grid-template-columns:minmax(0,1fr) minmax(0,1.3fr);gap:10px}.crm-tarea-card-accion{grid-column:1/-1}}
@media(max-width:560px){.crm-tarea-card-meta{grid-template-columns:minmax(0,1fr);gap:9px}.crm-tarea-card-fechas{gap:8px;padding:8px}.crm-tarea-card-accion{grid-column:auto;justify-content:flex-end}}
.crm-task-activity-item{position:relative;margin:0!important;padding:8px 0 8px 22px;border-bottom:1px solid #f1f5f9;font-size:12px;line-height:1.35}
.crm-task-activity-item:last-child{border-bottom:0}
.crm-task-activity-item>i{position:absolute;left:0;top:10px}
.crm-task-activity-item strong{display:inline-block;max-width:calc(100% - 86px);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:bottom;color:#334155}
.crm-task-activity-date{float:right;color:#94a3b8;font-size:11px;white-space:nowrap}
.crm-task-activity-meta{display:block;margin-top:3px;color:#64748b;font-size:11px}
@media(max-width:767px){.crm-tareas-cal-grid-title{padding:12px!important}.crm-tareas-cal-controls,.crm-tareas-cal-selectores{gap:4px}.crm-tareas-cal-controls>a:nth-last-child(2){min-width:70px}.crm-tareas-cal-selectores select:last-of-type{width:64px;flex-basis:64px}}
</style>
<table class="cal-compact-grid-mini">
    <thead><tr><th>Lu</th><th>Ma</th><th>Mi</th><th>Ju</th><th>Vi</th><th>Sá</th><th>Do</th></tr></thead>
    <tbody>
    <?php
    $dia=1; $celda=1; echo '<tr>';
    while($celda<$primer_dia) { echo '<td class="cal-mini-empty"></td>'; $celda++; }
    while($dia<=$dias_del_mes)
    {
        if($celda==1) { echo '<tr>'; }
        $fecha_celda=sprintf('%04d-%02d-%02d',$cal_anio,$cal_mes,$dia);
        $tipos=isset($eventos_mes[$dia]) ? $eventos_mes[$dia] : array();
        $clases=array('cal-mini-day');
        if($fecha_celda===$hoy) { $clases[]='cal-mini-hoy'; }
        if(count($tipos)) { $clases[]='cal-mini-has-events'; }
        if($fecha_celda===$dia_sel) { $clases[]='cal-mini-sel'; }
        $href=crm_tareas_link_filtro(array('dia'=>$fecha_celda,'cal'=>sprintf('%04d-%02d',$cal_anio,$cal_mes)));
        echo '<td class="'.implode(' ',$clases).'"><a href="'.htmlspecialchars($href,ENT_QUOTES,'UTF-8').'" title="'.(isset($tipos['inicio'])?'Inicio de tarea ':'').(isset($tipos['vencimiento'])?'Vencimiento de tarea':'Filtrar tareas de este día').'"><span class="cal-mini-num">'.$dia.'</span><div class="cal-mini-dots">'.(isset($tipos['inicio'])?'<span class="dot dot-blue"></span>':'').(isset($tipos['vencimiento'])?'<span class="dot dot-orange"></span>':'').'</div></a></td>';
        if($celda==7) { echo '</tr>'; $celda=0; }
        $dia++; $celda++;
    }
    if($celda>1) { while($celda<=7) { echo '<td></td>'; $celda++; } echo '</tr>'; }
    ?>
    </tbody>
</table>
<div class="cal-legend crm-tareas-cal-leyenda">
    <span><span class="dot dot-blue"></span> Inicio</span>
    <span><span class="dot dot-orange"></span> Vencimiento</span>
</div>
<p class="crm-cal-links">
    <a class="text-danger" href="<?php echo texto_mostrar(tareas_link('tareas.php',array('estado'=>'Vencida'))); ?>">Vencidas (<?php echo intval($contadores['Vencida']); ?>)</a> ·
    <a href="<?php echo texto_mostrar(tareas_link('tareas.php',array('vence'=>'hoy'))); ?>">Vencen hoy (<?php echo intval(isset($contadores['Hoy'])?$contadores['Hoy']:0); ?>)</a> ·
    <a href="<?php echo texto_mostrar(tareas_link('tareas.php')); ?>">Todas (<?php echo intval(isset($contadores['total'])?$contadores['total']:0); ?>)</a>
</p>
