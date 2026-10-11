<?php
// Búsquedas paginadas de clientes y empleados (optimizado para muchos registros).

function crm_busqueda_q()
{
	return isset($_GET['q']) ? trim($_GET['q']) : '';
}

function crm_busqueda_pagina()
{
	$p=isset($_GET['p']) ? intval($_GET['p']) : 1;
	return $p>0 ? $p : 1;
}

function crm_busqueda_limite($def=40)
{
	$n=isset($_GET['limit']) ? intval($_GET['limit']) : $def;
	if($n<10) { $n=10; }
	if($n>100) { $n=100; }
	return $n;
}

function crm_localidades_lista()
{
	if(!acceso_tabla_existe('localidad'))
	{
		return array();
	}
	return acceso_consulta("SELECT id_localidad, nombre FROM localidad ORDER BY nombre LIMIT 500");
}

function crm_clientes_buscar($opts=array())
{
	$area=isset($opts['area']) ? $opts['area'] : '';
	$q=isset($opts['q']) ? trim($opts['q']) : crm_busqueda_q();
	$id_loc=isset($opts['id_localidad']) ? intval($opts['id_localidad']) : (isset($_GET['id_localidad']) ? intval($_GET['id_localidad']) : 0);
	$pag=isset($opts['pagina']) ? intval($opts['pagina']) : crm_busqueda_pagina();
	$lim=isset($opts['limite']) ? intval($opts['limite']) : crm_busqueda_limite(40);
	if($pag<1) { $pag=1; }
	$off=($pag-1)*$lim;

	$sql_from=cliente_sql_base();
	// Ampliar select con DNI
	$sql_from=str_replace(
		'cp.genero, c.direccion',
		'cp.genero, cp.dni AS dni_persona, c.direccion',
		$sql_from
	);
	$wheres=array();
	$types='';
	$vals=array();

	if($area!='' && $area!='recepcion' && $area!='sistemas' && $area!='general' && !crm_area_ve_todo($area))
	{
		$wheres[]="(EXISTS (
			SELECT 1 FROM cliente_area ca INNER JOIN area a2 ON a2.id_area=ca.id_area
			WHERE ca.id_cliente=c.id_cliente AND a2.clave=?
		) OR EXISTS (
			SELECT 1 FROM atencion at INNER JOIN area a3 ON a3.id_area=at.id_area
			WHERE at.id_cliente=c.id_cliente AND a3.clave=?
		) OR EXISTS (
			SELECT 1 FROM tarea t INNER JOIN area a4 ON a4.id_area=t.id_area
			WHERE t.id_cliente=c.id_cliente AND a4.clave=?
		))";
		$types.='sss';
		$vals[]=$area; $vals[]=$area; $vals[]=$area;
	}
	if($id_loc>0)
	{
		$wheres[]="c.id_localidad=?";
		$types.='i';
		$vals[]=$id_loc;
	}
	if($q!='')
	{
		$like='%'.$q.'%';
		$qnum=preg_replace('/\D+/', '', $q);
		$wheres[]="(c.nombre LIKE ? OR c.correo LIKE ? OR c.cuil_cuit LIKE ? OR c.telefono LIKE ?
			OR l.nombre LIKE ?
			OR cp.dni LIKE ?
			OR (ce.razon_social IS NOT NULL AND ce.razon_social LIKE ?)
			".($qnum!='' ? " OR REPLACE(REPLACE(REPLACE(c.cuil_cuit,'-',''),' ',''),'.','') LIKE ? OR REPLACE(IFNULL(cp.dni,''),'.','') LIKE ?" : "")."
		)";
		// Join empresa for razon_social
		$sql_from=str_replace(
			'LEFT JOIN localidad l ON l.id_localidad=c.id_localidad',
			'LEFT JOIN localidad l ON l.id_localidad=c.id_localidad
		LEFT JOIN cliente_empresa ce ON ce.id_cliente=c.id_cliente',
			$sql_from
		);
		$types.='sssssss';
		$vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like;
		if($qnum!='')
		{
			$types.='ss';
			$vals[]='%'.$qnum.'%';
			$vals[]='%'.$qnum.'%';
		}
	}
	else
	{
		$sql_from=str_replace(
			'LEFT JOIN localidad l ON l.id_localidad=c.id_localidad',
			'LEFT JOIN localidad l ON l.id_localidad=c.id_localidad
		LEFT JOIN cliente_empresa ce ON ce.id_cliente=c.id_cliente',
			$sql_from
		);
	}

	$where_sql=count($wheres) ? (' WHERE '.implode(' AND ', $wheres)) : '';
	$total=0;
	$sin_total=!empty($opts['sin_total']);
	if(!$sin_total)
	{
		$count_sql="SELECT COUNT(DISTINCT c.id_cliente) AS n FROM cliente c
			LEFT JOIN cliente_persona cp ON cp.id_cliente=c.id_cliente
			LEFT JOIN cliente_empresa ce ON ce.id_cliente=c.id_cliente
			LEFT JOIN localidad l ON l.id_localidad=c.id_localidad".$where_sql;
		$total_row=acceso_consulta_una($count_sql, $types, $vals);
		$total=$total_row ? intval($total_row['n']) : 0;
	}

	$sql=$sql_from.$where_sql." ORDER BY c.nombre ASC, c.id_cliente DESC LIMIT ".$lim." OFFSET ".$off;
	$rows=acceso_consulta($sql, $types, $vals);
	$lista=array();
	foreach($rows as $row)
	{
		$fila=cliente_fila_pantalla($row);
		$fila['dni']=isset($row['dni_persona']) ? $row['dni_persona'] : '';
		$lista[]=$fila;
	}
	if($sin_total)
	{
		$total=count($lista);
	}
	return array(
		'lista'=>$lista,
		'total'=>$total,
		'pagina'=>$pag,
		'limite'=>$lim,
		'paginas'=>$sin_total ? 1 : max(1, (int)ceil($total/max(1,$lim))),
		'q'=>$q,
		'id_localidad'=>$id_loc
	);
}

/** Autocomplete rápido: sin COUNT, SQL liviano, área solo por cliente_area. */
function crm_clientes_buscar_rapido($opts=array())
{
	$area=isset($opts['area']) ? $opts['area'] : '';
	$q=isset($opts['q']) ? trim($opts['q']) : '';
	$id_loc=isset($opts['id_localidad']) ? intval($opts['id_localidad']) : 0;
	$lim=isset($opts['limite']) ? intval($opts['limite']) : 15;
	$tipos=isset($opts['tipos']) && is_array($opts['tipos']) ? $opts['tipos'] : array();
	$solo_org=!empty($opts['solo_organismos']);
	if($solo_org && count($tipos)==0)
	{
		$tipos=array('empresa','municipalidad','organismo');
	}
	if($lim<5) { $lim=5; }
	if($lim>30) { $lim=30; }
	if($q==='' && $id_loc<=0 && count($tipos)==0)
	{
		return array('lista'=>array(), 'total'=>0, 'q'=>$q);
	}

	$wheres=array();
	$types='';
	$vals=array();

	if($area!='' && $area!='recepcion' && $area!='sistemas' && $area!='general' && !(function_exists('crm_area_ve_todo') && crm_area_ve_todo($area)))
	{
		$wheres[]="EXISTS (
			SELECT 1 FROM cliente_area ca
			INNER JOIN area a2 ON a2.id_area=ca.id_area
			WHERE ca.id_cliente=c.id_cliente AND a2.clave=?
		)";
		$types.='s';
		$vals[]=$area;
	}
	if($id_loc>0)
	{
		$wheres[]="c.id_localidad=?";
		$types.='i';
		$vals[]=$id_loc;
	}
	if(count($tipos)>0)
	{
		$ph=array();
		foreach($tipos as $t)
		{
			$t=function_exists('cliente_tipo_normalizar') ? cliente_tipo_normalizar($t) : trim($t);
			if($t==='') { continue; }
			$ph[]='?';
			$types.='s';
			$vals[]=$t;
		}
		if(count($ph)>0)
		{
			$wheres[]='c.tipo_cliente IN ('.implode(',', $ph).')';
		}
	}
	if($q!='')
	{
		$like='%'.$q.'%';
		$pref=$q.'%';
		$qnum=preg_replace('/\D+/', '', $q);
		$parts=array(
			"c.nombre LIKE ?",
			"c.nombre LIKE ?",
			"c.correo LIKE ?",
			"c.cuil_cuit LIKE ?",
			"IFNULL(cp.dni,'') LIKE ?",
			"IFNULL(l.nombre,'') LIKE ?"
		);
		$types.='ssssss';
		$vals[]=$pref;   // nombre empieza con…
		$vals[]=$like;   // apellido / texto en el medio
		$vals[]=$like;
		$vals[]=$pref;
		$vals[]=$pref;
		$vals[]=$like;
		if($qnum!='' && strlen($qnum)>=3)
		{
			$parts[]="REPLACE(REPLACE(REPLACE(IFNULL(c.cuil_cuit,''),'-',''),' ',''),'.','') LIKE ?";
			$parts[]="REPLACE(IFNULL(cp.dni,''),'.','') LIKE ?";
			$types.='ss';
			$vals[]=$qnum.'%';
			$vals[]=$qnum.'%';
		}
		$wheres[]='('.implode(' OR ', $parts).')';
	}

	if(count($wheres)==0)
	{
		return array('lista'=>array(), 'total'=>0, 'q'=>$q);
	}

	$sql="SELECT c.id_cliente, c.nombre, c.correo, c.telefono, c.cuil_cuit, c.tipo_cliente, c.id_localidad,
			cp.dni AS dni_persona, l.nombre AS localidad_nombre
		FROM cliente c
		LEFT JOIN cliente_persona cp ON cp.id_cliente=c.id_cliente
		LEFT JOIN localidad l ON l.id_localidad=c.id_localidad
		WHERE ".implode(' AND ', $wheres)."
		ORDER BY
			CASE WHEN c.nombre LIKE ? THEN 0 ELSE 1 END,
			c.nombre ASC
		LIMIT ".$lim;
	$types.='s';
	$vals[]=($q!=='' ? $q : '').'%';

	$rows=acceso_consulta($sql, $types, $vals);
	$lista=array();
	foreach($rows as $row)
	{
		$lista[]=array(
			'id'=>intval($row['id_cliente']),
			'name'=>isset($row['nombre']) ? $row['nombre'] : '',
			'email'=>isset($row['correo']) ? $row['correo'] : '',
			'localidad'=>isset($row['localidad_nombre']) ? $row['localidad_nombre'] : '',
			'cuil_cuit'=>isset($row['cuil_cuit']) ? $row['cuil_cuit'] : '',
			'dni'=>isset($row['dni_persona']) ? $row['dni_persona'] : '',
			'tipo_cliente'=>isset($row['tipo_cliente']) ? cliente_tipo_normalizar($row['tipo_cliente']) : 'emprendedor'
		);
	}
	return array('lista'=>$lista, 'total'=>count($lista), 'q'=>$q);
}

function crm_empleados_buscar($opts=array())
{
	$q=isset($opts['q']) ? trim($opts['q']) : crm_busqueda_q();
	$area=isset($opts['area']) ? $opts['area'] : (isset($_GET['area']) ? trim($_GET['area']) : '');
	$rol=isset($opts['rol']) ? $opts['rol'] : (isset($_GET['rol']) ? trim($_GET['rol']) : '');
	$pag=isset($opts['pagina']) ? intval($opts['pagina']) : crm_busqueda_pagina();
	$lim=isset($opts['limite']) ? intval($opts['limite']) : crm_busqueda_limite(40);
	if($pag<1) { $pag=1; }
	$off=($pag-1)*$lim;
	$incluir_super=isset($opts['incluir_super']) ? (bool)$opts['incluir_super'] : false;

	$wheres=array("e.activo=1");
	$types='';
	$vals=array();
	if(!$incluir_super)
	{
		$wheres[]="r.clave<>'superadmin'";
	}
	if($area!='')
	{
		$wheres[]="a.clave=?";
		$types.='s';
		$vals[]=$area;
	}
	if($rol!='')
	{
		$wheres[]="r.clave=?";
		$types.='s';
		$vals[]=$rol;
	}
	$order_extra='';
	if($q!='')
	{
		$like='%'.$q.'%';
		$qnum=preg_replace('/\D+/', '', $q);
		$wheres[]="(e.nombres LIKE ? OR e.apellido LIKE ? OR CONCAT(e.nombres,' ',e.apellido) LIKE ?
			OR e.nombre_usuario LIKE ? OR e.correo LIKE ? OR e.dni LIKE ? OR e.cuil LIKE ?
			".($qnum!='' ? " OR REPLACE(IFNULL(e.dni,''),'.','') LIKE ? OR REPLACE(REPLACE(IFNULL(e.cuil,''),'-',''),'.','') LIKE ?" : "").")";
		$types.='sssssss';
		$vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like; $vals[]=$like;
		if($qnum!='')
		{
			$types.='ss';
			$vals[]='%'.$qnum.'%';
			$vals[]='%'.$qnum.'%';
		}
		// Prioriza prefijo más corto/cercano (ej. "Ema" → "Ema Knott" antes que "emanuel…").
		$order_extra="(CASE
			WHEN e.nombre_usuario LIKE ? THEN 0
			WHEN e.nombres LIKE ? THEN 1
			WHEN CONCAT(e.nombres,' ',e.apellido) LIKE ? THEN 2
			WHEN e.apellido LIKE ? THEN 3
			ELSE 4 END), CHAR_LENGTH(e.nombre_usuario), CHAR_LENGTH(e.nombres), ";
		$pref=$q.'%';
		$types.='ssss';
		$vals[]=$pref; $vals[]=$pref; $vals[]=$pref; $vals[]=$pref;
	}
	$where_sql=' WHERE '.implode(' AND ', $wheres);
	$base=acceso_sql_empleado_base();
	// COUNT no usa los placeholders del ORDER CASE.
	$types_count=$types;
	$vals_count=$vals;
	if($order_extra!=='')
	{
		$types_count=substr($types, 0, -4);
		$vals_count=array_slice($vals, 0, -4);
	}
	$total_row=acceso_consulta_una("SELECT COUNT(*) AS n FROM empleado e INNER JOIN rol r ON r.id_rol=e.id_rol INNER JOIN area a ON a.id_area=e.id_area".$where_sql, $types_count, $vals_count);
	$total=$total_row ? intval($total_row['n']) : 0;
	$rows=acceso_consulta($base.$where_sql." ORDER BY ".$order_extra." a.nombre, e.apellido, e.nombres LIMIT ".$lim." OFFSET ".$off, $types, $vals);
	$lista=array();
	foreach($rows as $row)
	{
		$lista[]=acceso_fila_a_persona($row);
	}
	return array(
		'lista'=>$lista,
		'total'=>$total,
		'pagina'=>$pag,
		'limite'=>$lim,
		'paginas'=>max(1, (int)ceil($total/max(1,$lim))),
		'q'=>$q,
		'area'=>$area,
		'rol'=>$rol
	);
}

function crm_tareas_filtrar_cliente($lista)
{
	$q=isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
	if($q=='' || !is_array($lista))
	{
		return $lista;
	}
	$q_l=function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
	$q_num=preg_replace('/\D+/', '', $q);
	$out=array();
	foreach($lista as $t)
	{
		$nom='';
		if(isset($t['cliente_nombre'])) { $nom=$t['cliente_nombre']; }
		else if(isset($t['cliente'])) { $nom=is_array($t['cliente']) ? (isset($t['cliente']['nombre']) ? $t['cliente']['nombre'] : '') : $t['cliente']; }
		$nom_l=function_exists('mb_strtolower') ? mb_strtolower($nom, 'UTF-8') : strtolower($nom);

		$cuit=isset($t['cliente_cuit']) ? (string)$t['cliente_cuit'] : '';
		$dni=isset($t['cliente_dni']) ? (string)$t['cliente_dni'] : '';
		$cuit_num=preg_replace('/\D+/', '', $cuit);
		$dni_num=preg_replace('/\D+/', '', $dni);

		$match = false;
		if($nom!='' && strpos($nom_l, $q_l)!==false) { $match = true; }
		if(!$match && $cuit!='' && strpos(strtolower($cuit), $q_l)!==false) { $match = true; }
		if(!$match && $dni!='' && strpos(strtolower($dni), $q_l)!==false) { $match = true; }
		if(!$match && $q_num!='' && (($cuit_num!='' && strpos($cuit_num, $q_num)!==false) || ($dni_num!='' && strpos($dni_num, $q_num)!==false))) { $match = true; }

		if($match)
		{
			$out[]=$t;
		}
	}
	return $out;
}

function crm_html_busqueda_clientes($extra_hidden=array())
{
	$q=crm_busqueda_q();
	$id_loc=isset($_GET['id_localidad']) ? intval($_GET['id_localidad']) : 0;
	$locs=crm_localidades_lista();
	ob_start();
	?>
	<form method="get" action="" class="crm-busqueda form-inline" style="margin-bottom:14px;">
		<?php foreach($extra_hidden as $k=>$v) { ?>
		<input type="hidden" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($v); ?>">
		<?php } ?>
		<div class="form-group">
			<label>Buscar</label>
			<input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="Nombre, DNI, CUIL/CUIT, localidad…" style="min-width:240px;">
		</div>
		<div class="form-group">
			<label>Localidad</label>
			<select name="id_localidad" class="form-control" style="min-width:180px;">
				<option value="0">Todas</option>
				<?php foreach($locs as $l) { ?>
				<option value="<?php echo intval($l['id_localidad']); ?>" <?php if($id_loc==intval($l['id_localidad'])) echo 'selected'; ?>><?php echo htmlspecialchars($l['nombre']); ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="form-group crm-busqueda-acciones">
			<label>&nbsp;</label>
			<div>
				<button type="submit" class="btn btn-primary">Buscar</button>
				<a href="?" class="btn btn-white">Limpiar</a>
			</div>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

function crm_html_busqueda_empleados()
{
	$q=crm_busqueda_q();
	$area=isset($_GET['area']) ? trim($_GET['area']) : '';
	$rol=isset($_GET['rol']) ? trim($_GET['rol']) : '';
	$etq=acceso_etiquetas_area();
	$roles=function_exists('acceso_roles_para_alta') ? acceso_roles_para_alta() : array();
	$ajax_url=function_exists('url_crm') ? url_crm('ajax/empleados-buscar.php') : '../ajax/empleados-buscar.php';
	ob_start();
	?>
	<form method="get" action="" class="crm-busqueda form-inline" style="margin-bottom:14px;" id="form_bus_empleados">
		<div class="form-group" style="position:relative;">
			<label>Buscar</label>
			<input type="text" name="q" id="emp_buscar_q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="Nombre, apellido, DNI, usuario, correo…" autocomplete="off" style="min-width:260px;">
			<div id="emp_buscar_sugerencias" class="crm-cli-suggest" style="display:none;"></div>
		</div>
		<div class="form-group">
			<label>Área</label>
			<select name="area" id="emp_buscar_area" class="form-control">
				<option value="">Todas</option>
				<?php foreach($etq as $k=>$t) { ?>
				<option value="<?php echo htmlspecialchars($k); ?>" <?php if($area===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="form-group">
			<label>Rol</label>
			<select name="rol" id="emp_buscar_rol" class="form-control">
				<option value="">Todos</option>
				<?php foreach($roles as $k=>$t) { ?>
				<option value="<?php echo htmlspecialchars($k); ?>" <?php if($rol===$k) echo 'selected'; ?>><?php echo htmlspecialchars($t); ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="form-group crm-busqueda-acciones">
			<label>&nbsp;</label>
			<div>
				<button type="submit" class="btn btn-primary">Buscar</button>
				<a href="?" class="btn btn-white">Limpiar</a>
			</div>
		</div>
	</form>
	<script>
	(function(){
		var input=document.getElementById('emp_buscar_q');
		var box=document.getElementById('emp_buscar_sugerencias');
		var selArea=document.getElementById('emp_buscar_area');
		var form=document.getElementById('form_bus_empleados');
		if(!input || !box) return;
		var timer=null;
		var url=<?php echo json_encode($ajax_url, JSON_UNESCAPED_UNICODE); ?>;
		function ocultar(){ box.style.display='none'; box.innerHTML=''; }
		function buscar(){
			var q=input.value.replace(/^\s+|\s+$/g,'');
			if(q.length<2){ ocultar(); return; }
			var area=selArea ? selArea.value : '';
			var xhr=new XMLHttpRequest();
			xhr.open('GET', url+'?q='+encodeURIComponent(q)+'&area='+encodeURIComponent(area)+'&limit=15', true);
			xhr.onreadystatechange=function(){
				if(xhr.readyState!==4) return;
				try{
					var data=JSON.parse(xhr.responseText||'{}');
					var lista=(data && data.ok && data.lista) ? data.lista : [];
					if(!lista.length){
						box.innerHTML='<div class="crm-cli-suggest-item" style="color:#888;">Sin coincidencias</div>';
						box.style.display='block';
						return;
					}
					box.innerHTML='';
					lista.forEach(function(emp){
						var item=document.createElement('div');
						item.className='crm-cli-suggest-item';
						item.setAttribute('tabindex','0');
						var tit=document.createElement('strong');
						tit.textContent=emp.nombre||emp.usuario||'';
						item.appendChild(tit);
						var extra=[];
						if(emp.usuario) extra.push(emp.usuario);
						if(emp.area) extra.push(emp.area);
						if(emp.dni) extra.push('DNI '+emp.dni);
						if(extra.length){
							var s=document.createElement('span');
							s.style.color='#777';
							s.style.fontSize='11px';
							s.style.marginLeft='6px';
							s.textContent=extra.join(' · ');
							item.appendChild(s);
						}
						item.onclick=function(){
							input.value=emp.nombre||emp.usuario||'';
							ocultar();
							if(form) form.submit();
						};
						box.appendChild(item);
					});
					box.style.display='block';
				}catch(e){ ocultar(); }
			};
			xhr.send();
		}
		input.addEventListener('input', function(){
			if(timer) clearTimeout(timer);
			timer=setTimeout(buscar, 250);
		});
		input.addEventListener('focus', function(){
			if(input.value.replace(/^\s+|\s+$/g,'').length>=2) buscar();
		});
		document.addEventListener('click', function(e){
			if(!input.contains(e.target) && !box.contains(e.target)) ocultar();
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

/** Picker AJAX de empleado (mismo patrón que clientes). Devuelve HTML. */
function crm_html_picker_empleado($opts=array())
{
	$input_id=isset($opts['input_id']) ? $opts['input_id'] : 'buscar_empleado';
	$hidden_id=isset($opts['hidden_id']) ? $opts['hidden_id'] : 'id_empleado';
	$hidden_name=isset($opts['hidden_name']) ? $opts['hidden_name'] : 'id_empleado';
	$valor_id=isset($opts['valor_id']) ? intval($opts['valor_id']) : 0;
	$valor_texto=isset($opts['valor_texto']) ? (string)$opts['valor_texto'] : '';
	$placeholder=isset($opts['placeholder']) ? $opts['placeholder'] : 'Buscar por nombre, DNI, usuario o correo…';
	$area=isset($opts['area']) ? trim((string)$opts['area']) : '';
	$ajax_url=function_exists('url_crm') ? url_crm('ajax/empleados-buscar.php') : '../ajax/empleados-buscar.php';
	$suggest_id=$input_id.'_sugerencias';
	ob_start();
	?>
	<div class="crm-cli-pick">
		<input type="hidden" name="<?php echo htmlspecialchars($hidden_name); ?>" id="<?php echo htmlspecialchars($hidden_id); ?>" value="<?php echo intval($valor_id); ?>">
		<input type="text" id="<?php echo htmlspecialchars($input_id); ?>" class="form-control" value="<?php echo htmlspecialchars($valor_texto); ?>" placeholder="<?php echo htmlspecialchars($placeholder); ?>" autocomplete="off">
		<div id="<?php echo htmlspecialchars($suggest_id); ?>" class="crm-cli-suggest" style="display:none;"></div>
	</div>
	<script>
	(function(){
		var bus=document.getElementById(<?php echo json_encode($input_id); ?>);
		var hid=document.getElementById(<?php echo json_encode($hidden_id); ?>);
		var box=document.getElementById(<?php echo json_encode($suggest_id); ?>);
		if(!bus || !hid || !box) return;
		var timer=null;
		var url=<?php echo json_encode($ajax_url, JSON_UNESCAPED_UNICODE); ?>;
		var areaFija=<?php echo json_encode($area, JSON_UNESCAPED_UNICODE); ?>;
		function ocultar(){ box.style.display='none'; box.innerHTML=''; }
		function buscar(){
			var q=bus.value.replace(/^\s+|\s+$/g,'');
			if(q.length<2){ ocultar(); return; }
			var xhr=new XMLHttpRequest();
			xhr.open('GET', url+'?q='+encodeURIComponent(q)+'&area='+encodeURIComponent(areaFija)+'&limit=15', true);
			xhr.onreadystatechange=function(){
				if(xhr.readyState!==4) return;
				try{
					var data=JSON.parse(xhr.responseText||'{}');
					var lista=(data && data.ok && data.lista) ? data.lista : [];
					box.innerHTML='';
					if(!lista.length){
						box.innerHTML='<div class="crm-cli-suggest-item" style="color:#888;">Sin coincidencias</div>';
						box.style.display='block';
						return;
					}
					lista.forEach(function(emp){
						var item=document.createElement('div');
						item.className='crm-cli-suggest-item';
						var tit=document.createElement('strong');
						tit.textContent=emp.nombre||emp.usuario||'';
						item.appendChild(tit);
						var extra=[];
						if(emp.usuario) extra.push(emp.usuario);
						if(emp.area) extra.push(emp.area);
						if(emp.dni) extra.push('DNI '+emp.dni);
						if(extra.length){
							var s=document.createElement('span');
							s.style.color='#777'; s.style.fontSize='11px'; s.style.marginLeft='6px';
							s.textContent=extra.join(' · ');
							item.appendChild(s);
						}
						item.onclick=function(){
							hid.value=String(emp.id||0);
							bus.value=emp.nombre||emp.usuario||'';
							ocultar();
							if(typeof window.crmEmpleadoElegido==='function'){
								window.crmEmpleadoElegido(emp, <?php echo json_encode($hidden_id); ?>);
							}
						};
						box.appendChild(item);
					});
					box.style.display='block';
				}catch(e){ ocultar(); }
			};
			xhr.send();
		}
		bus.addEventListener('input', function(){
			hid.value='0';
			if(timer) clearTimeout(timer);
			timer=setTimeout(buscar, 250);
		});
		bus.addEventListener('focus', function(){
			if(bus.value.replace(/^\s+|\s+$/g,'').length>=2) buscar();
		});
		document.addEventListener('click', function(e){
			if(!bus.contains(e.target) && !box.contains(e.target)) ocultar();
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

function crm_html_paginacion($res, $base_params=array())
{
	if(!isset($res['paginas']) || intval($res['paginas'])<=1)
	{
		$total=isset($res['total']) ? intval($res['total']) : 0;
		return $total>0 ? '<p class="muted">'.$total.' resultado(s)</p>' : '';
	}
	$pag=intval($res['pagina']);
	$paginas=intval($res['paginas']);
	$total=intval($res['total']);
	$html='<p class="muted">'.$total.' resultado(s) · página '.$pag.' de '.$paginas.'</p><p>';
	for($i=1;$i<=$paginas;$i++)
	{
		if($i>1 && $i<$paginas && abs($i-$pag)>2) { continue; }
		$params=array_merge($base_params, array('p'=>$i));
		if(isset($res['q']) && $res['q']!='') { $params['q']=$res['q']; }
		if(isset($res['id_localidad']) && $res['id_localidad']>0) { $params['id_localidad']=$res['id_localidad']; }
		if(isset($res['area']) && $res['area']!='') { $params['area']=$res['area']; }
		if(isset($res['rol']) && $res['rol']!='') { $params['rol']=$res['rol']; }
		$url='?'.http_build_query($params);
		if($i==$pag)
		{
			$html.='<span class="btn btn-primary btn-xs" style="margin:2px;">'.$i.'</span> ';
		}
		else
		{
			$html.='<a class="btn btn-white btn-xs" style="margin:2px;" href="'.htmlspecialchars($url).'">'.$i.'</a> ';
		}
	}
	$html.='</p>';
	return $html;
}

function crm_tareas_clientes_disponibles()
{
	global $con;
	$salida=array();
	if(!$con)
	{
		return $salida;
	}
	$sql="SELECT DISTINCT c.nombre 
		FROM cliente c 
		INNER JOIN tarea t ON t.id_cliente=c.id_cliente 
		WHERE c.nombre IS NOT NULL AND c.nombre!='' 
		ORDER BY c.nombre";
	$res=@mysqli_query($con, $sql);
	if($res)
	{
		while($row=mysqli_fetch_assoc($res))
		{
			$nom=trim($row['nombre']);
			if($nom!='' && !in_array($nom, $salida))
			{
				$salida[]=$nom;
			}
		}
	}
	return $salida;
}

function crm_html_filtro_cliente_tarea()
{
	$q=isset($_GET['cliente']) ? trim($_GET['cliente']) : '';
	$ajax_url = function_exists('url_crm') ? url_crm('ajax/clientes-buscar.php') : '../ajax/clientes-buscar.php';
	ob_start();
	?>
	<div class="form-group" style="position:relative;">
		<label for="filtro_cliente_input">Cliente</label>
		<div style="position:relative;">
			<input type="text"
				name="cliente"
				id="filtro_cliente_input"
				value="<?php echo htmlspecialchars($q); ?>"
				class="form-control"
				placeholder="Buscar por nombre, DNI o CUIT..."
				title="Buscar cliente por nombre, DNI o CUIT"
				autocomplete="off"
				style="min-width:250px; <?php echo $q!=='' ? 'padding-right:26px;' : ''; ?>">
			<?php if($q !== '') { ?>
			<button type="button"
				id="btn_limpiar_cliente_filtro"
				title="Quitar filtro de cliente"
				style="position:absolute; right:6px; top:50%; transform:translateY(-50%); background:transparent; border:none; color:#888; font-size:16px; line-height:1; cursor:pointer; padding:0 4px;"
				onclick="var inp=document.getElementById('filtro_cliente_input'); if(inp){ inp.value=''; inp.form.submit(); }">&times;</button>
			<?php } ?>
			<div id="filtro_cliente_sugerencias" style="display:none; position:absolute; left:0; top:100%; z-index:1050; background:#fff; border:1px solid #ccc; border-radius:4px; box-shadow:0 6px 12px rgba(0,0,0,0.175); width:100%; min-width:250px; max-height:220px; overflow-y:auto; margin-top:2px;"></div>
		</div>
	</div>
	<script>
	(function() {
		var input = document.getElementById('filtro_cliente_input');
		var sugerencias = document.getElementById('filtro_cliente_sugerencias');
		if(!input || !sugerencias) return;

		var timer = null;
		var url = <?php echo json_encode($ajax_url, JSON_UNESCAPED_UNICODE); ?>;

		function ocultarSugerencias() {
			sugerencias.style.display = 'none';
			sugerencias.innerHTML = '';
		}

		input.addEventListener('input', function() {
			var q = this.value.trim();
			if(timer) clearTimeout(timer);
			if(q.length < 2) {
				ocultarSugerencias();
				return;
			}
			timer = setTimeout(function() {
				var xhr = new XMLHttpRequest();
				xhr.open('GET', url + '?q=' + encodeURIComponent(q) + '&todas=1&limit=15', true);
				xhr.onreadystatechange = function() {
					if(xhr.readyState !== 4) return;
					try {
						var data = JSON.parse(xhr.responseText || '{}');
						var lista = (data && data.ok && Array.isArray(data.lista)) ? data.lista : [];
						if(lista.length === 0) {
							sugerencias.innerHTML = '<div style="padding:8px 10px; color:#888; font-size:12px;">Sin coincidencias</div>';
							sugerencias.style.display = 'block';
							return;
						}
						sugerencias.innerHTML = '';
						lista.forEach(function(cli) {
							var item = document.createElement('div');
							item.style.padding = '8px 10px';
							item.style.cursor = 'pointer';
							item.style.borderBottom = '1px solid #f0f0f0';
							item.style.fontSize = '13px';

							var nombreSpan = document.createElement('strong');
							nombreSpan.textContent = cli.nombre || '';
							item.appendChild(nombreSpan);

							var extra = '';
							if(cli.cuil_cuit) extra += ' · ' + cli.cuil_cuit;
							else if(cli.dni) extra += ' · DNI ' + cli.dni;
							if(cli.localidad) extra += ' (' + cli.localidad + ')';
							if(extra) {
								var small = document.createElement('span');
								small.style.color = '#777';
								small.style.fontSize = '11px';
								small.style.marginLeft = '4px';
								small.textContent = extra;
								item.appendChild(small);
							}

							item.addEventListener('mouseover', function() { item.style.background = '#eef3f7'; });
							item.addEventListener('mouseout', function() { item.style.background = '#fff'; });
							item.addEventListener('click', function(e) {
								e.preventDefault();
								e.stopPropagation();
								input.value = cli.nombre;
								ocultarSugerencias();
								if(input.form) {
									input.form.submit();
								}
							});
							sugerencias.appendChild(item);
						});
						sugerencias.style.display = 'block';
					} catch(err) {
						ocultarSugerencias();
					}
				};
				xhr.send();
			}, 250);
		});

		document.addEventListener('click', function(e) {
			if(!input.contains(e.target) && !sugerencias.contains(e.target)) {
				ocultarSugerencias();
			}
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}

/** Dashboard scope: empleado | area | todo */
function crm_dashboard_alcance()
{
	if(acceso_es_super() || acceso_es_coord_general())
	{
		return 'todo';
	}
	if(acceso_es_empleado())
	{
		return 'empleado';
	}
	return 'area';
}

function crm_dashboard_destino()
{
	if(acceso_es_presidencia())
	{
		return url_crm('admin/informes-presidencia.php');
	}
	if(acceso_es_super())
	{
		return url_crm('admin/home.php');
	}
	if(acceso_es_coord_general())
	{
		return url_crm('general/dashboard.php');
	}
	$area=acceso_area();
	if($area=='')
	{
		return url_crm('admin/home.php');
	}
	return url_crm($area.'/dashboard.php');
}
