<?php
// Calendario del mes en Tareas. Cada día con tareas filtra la lista por ese día.
// Usa: $cal_anio, $cal_mes, $dias_marcados, $primer_dia, $dias_del_mes, $hoy, $contadores.
$dia_sel=isset($_GET['dia']) ? $_GET['dia'] : '';
?>
                                <table class="table table-condensed crm-calendario">
                                    <thead>
                                        <tr>
                                            <th>Lu</th><th>Ma</th><th>Mi</th><th>Ju</th><th>Vi</th><th>Sá</th><th>Do</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $dia=1;
                                    $celda=1;
                                    echo '<tr>';
                                    while($celda<$primer_dia)
                                    {
                                        echo '<td></td>';
                                        $celda++;
                                    }
                                    while($dia<=$dias_del_mes)
                                    {
                                        if($celda==1)
                                        {
                                            echo '<tr>';
                                        }
                                        $fecha_celda=sprintf('%04d-%02d-%02d', $cal_anio, $cal_mes, $dia);
                                        $clases=array();
                                        if($fecha_celda==$hoy) { $clases[]='crm-cal-hoy'; }
                                        if(isset($dias_marcados[$dia])) { $clases[]='crm-cal-con'; }
                                        if($fecha_celda==$dia_sel) { $clases[]='crm-cal-sel'; }
                                        echo '<td class="'.implode(' ', $clases).'">';
                                        if(isset($dias_marcados[$dia]))
                                        {
                                            $href=($fecha_celda==$dia_sel)
                                                ? tareas_link('tareas.php', array('cal'=>sprintf('%04d-%02d', $cal_anio, $cal_mes)))
                                                : tareas_link('tareas.php', array('dia'=>$fecha_celda, 'cal'=>sprintf('%04d-%02d', $cal_anio, $cal_mes)));
                                            echo '<a href="'.texto_mostrar($href).'" title="Ver las tareas de este día">'.$dia.' <span class="text-info">•</span></a>';
                                        }
                                        else
                                        {
                                            echo $dia;
                                        }
                                        echo '</td>';
                                        if($celda==7)
                                        {
                                            echo '</tr>';
                                            $celda=0;
                                        }
                                        $dia++;
                                        $celda++;
                                    }
                                    if($celda>1)
                                    {
                                        while($celda<=7)
                                        {
                                            echo '<td></td>';
                                            $celda++;
                                        }
                                        echo '</tr>';
                                    }
                                    ?>
                                    </tbody>
                                </table>
                                <p class="crm-cal-links">
                                    <a class="text-danger" href="<?php echo texto_mostrar(tareas_link('tareas.php', array('estado'=>'Vencida'))); ?>">Vencidas (<?php echo intval($contadores['Vencida']); ?>)</a> ·
                                    <a href="<?php echo texto_mostrar(tareas_link('tareas.php', array('vence'=>'hoy'))); ?>">Vencen hoy (<?php echo intval(isset($contadores['Hoy']) ? $contadores['Hoy'] : 0); ?>)</a> ·
                                    <a href="<?php echo texto_mostrar(tareas_link('tareas.php')); ?>">Todas (<?php echo intval(isset($contadores['total']) ? $contadores['total'] : 0); ?>)</a>
                                </p>
