/* Icono para mostrar/ocultar contraseña en todos los type=password */
(function(){
	function wrap(input){
		if(!input || input.getAttribute('data-crm-pass')==='1'){ return; }
		input.setAttribute('data-crm-pass','1');
		var parent=input.parentNode;
		if(parent && parent.className && (''+parent.className).indexOf('crm-pass-wrap')>=0){
			if(!parent.querySelector('.crm-pass-toggle')){
				addBtn(parent, input);
			}
			return;
		}
		// Si está en input-group, sacar el input del addon y envolver solo el input
		var wrap=document.createElement('div');
		wrap.className='crm-pass-wrap';
		parent.insertBefore(wrap, input);
		wrap.appendChild(input);
		addBtn(wrap, input);
	}
	function addBtn(wrap, input){
		var btn=document.createElement('button');
		btn.type='button';
		btn.className='crm-pass-toggle';
		btn.setAttribute('title','Ver contraseña');
		btn.setAttribute('aria-label','Ver contraseña');
		btn.tabIndex=0;
		btn.innerHTML='<i class="fa fa-eye"></i>';
		btn.onclick=function(e){
			e.preventDefault();
			e.stopPropagation();
			var show=input.type==='password';
			input.type=show ? 'text' : 'password';
			btn.innerHTML=show ? '<i class="fa fa-eye-slash"></i>' : '<i class="fa fa-eye"></i>';
			btn.setAttribute('title', show ? 'Ocultar contraseña' : 'Ver contraseña');
		};
		wrap.appendChild(btn);
	}
	function scan(){
		var list=document.querySelectorAll('input[type="password"]');
		for(var i=0;i<list.length;i++){ wrap(list[i]); }
	}
	if(document.readyState==='loading'){
		document.addEventListener('DOMContentLoaded', scan);
	} else {
		scan();
	}
})();
