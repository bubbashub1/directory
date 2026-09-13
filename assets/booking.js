(function(){'use strict';
function ready(fn){if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);}else{fn();}}
ready(function(){
 var root=document.querySelector('.bh-booking-widget');
 if(!root||typeof BubbaHubBookingUI==='undefined'){return;}
 var groupId=root.getAttribute('data-group-id');
 var dateSelect=root.querySelector('[data-booking-date]');
 var sessionsBox=root.querySelector('[data-sessions]');
 var status=root.querySelector('.bh-booking-status');
 var actions=root.querySelector('[data-booking-actions]');
 var selectedText=root.querySelector('[data-selected-session]');
 var actionButtons=root.querySelector('[data-action-buttons]');
 var reservePanel=root.querySelector('[data-reserve-panel]');
 var selected=null;
 var allSessions=[];

 function esc(value){var d=document.createElement('div');d.textContent=value==null?'':String(value);return d.innerHTML;}
 function money(value){if(value===null||value===undefined||value===''){return 'Price on request';}var s=String(value);return /^[£$€]/.test(s)?s:'£'+s;}
 function time(value){if(!value)return '';var p=String(value).split(':');var h=parseInt(p[0],10);if(isNaN(h))return value;var m=p[1]||'00';var suffix=h>=12?'pm':'am';h=h%12||12;return h+':'+m+' '+suffix;}
 function formatDate(value){var d=new Date(value+'T12:00:00');return isNaN(d.getTime())?value:d.toLocaleDateString(undefined,{weekday:'short',day:'numeric',month:'short',year:'numeric'});}
 function post(data){var body=new URLSearchParams();Object.keys(data).forEach(function(k){body.append(k,data[k]);});return fetch(BubbaHubBookingUI.ajaxUrl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(function(r){return r.json();});}
 function showSessions(list){
   allSessions=list||[];selected=null;actions.hidden=true;actionButtons.innerHTML='';
   if(!allSessions.length){sessionsBox.innerHTML='<div class="bh-booking-empty">'+esc(BubbaHubBookingUI.empty)+'</div>';return;}
   sessionsBox.innerHTML=allSessions.map(function(s){
     var availability=s.capacity>0?(s.remaining+' space'+(s.remaining===1?'':'s')+' left'):'Spaces available';
     return '<button type="button" class="bh-booking-session" data-session-id="'+esc(s.id)+'"><span class="bh-booking-session-main"><span class="bh-booking-session-time">'+esc(time(s.start_time))+(s.end_time?' – '+esc(time(s.end_time)):'')+'</span><span class="bh-booking-session-price">'+esc(money(s.price))+'</span></span><span class="bh-booking-session-meta"><span class="bh-booking-pill">'+esc(availability)+'</span></span></button>';
   }).join('');
   sessionsBox.querySelectorAll('[data-session-id]').forEach(function(btn){btn.addEventListener('click',function(){selectSession(parseInt(btn.getAttribute('data-session-id'),10),btn);});});
 }
 function load(date){
   if(!date){sessionsBox.innerHTML='<div class="bh-booking-empty">Choose a date to see available sessions.</div>';actions.hidden=true;status.textContent='';selected=null;return;}
   status.textContent=BubbaHubBookingUI.loading;sessionsBox.innerHTML='<div class="bh-booking-empty">'+esc(BubbaHubBookingUI.loading)+'</div>';actions.hidden=true;
   post({action:'bubbahub_booking_sessions',nonce:BubbaHubBookingUI.nonce,group_id:groupId,date:date}).then(function(res){
     if(!res.success){throw new Error((res.data&&res.data.message)||BubbaHubBookingUI.error);}
     status.textContent=res.data.sessions.length?(res.data.sessions.length+' session'+(res.data.sessions.length===1?'':'s')+' available'):'No sessions available';showSessions(res.data.sessions);
   }).catch(function(){status.textContent='';sessionsBox.innerHTML='<div class="bh-booking-empty">'+esc(BubbaHubBookingUI.error)+'</div>';});
 }
 function selectSession(id,btn){
   selected=allSessions.find(function(s){return parseInt(s.id,10)===id;});if(!selected)return;
   sessionsBox.querySelectorAll('.bh-booking-session').forEach(function(b){b.classList.remove('is-selected');});btn.classList.add('is-selected');
   selectedText.textContent=formatDate(selected.date)+' · '+time(selected.start_time)+(selected.end_time?' – '+time(selected.end_time):'')+' · '+money(selected.price);
   actionButtons.innerHTML='';
   if(selected.booking_method==='external'&&selected.external_url){
     var ext=document.createElement('a');ext.className='bh-booking-external';ext.href=selected.external_url;ext.target='_blank';ext.rel='noopener noreferrer';ext.textContent='Book Now →';actionButtons.appendChild(ext);
   }else{
     var book=document.createElement('a');book.className='bh-booking-primary';book.href='#bh-booking-form';book.textContent='Book Now';book.setAttribute('data-book-now','');
     book.addEventListener('click',function(e){e.preventDefault();window.dispatchEvent(new CustomEvent('bubbahub:booking-selected',{detail:selected}));showMessage('Book Now will connect to the Ninja Forms booking form in the next integration stage.');});actionButtons.appendChild(book);
   }
   if(selected.reserve_enabled){var reserve=document.createElement('button');reserve.type='button';reserve.className='bh-booking-secondary';reserve.textContent='Reserve Spot';reserve.addEventListener('click',function(){reservePanel.hidden=false;reservePanel.scrollIntoView({behavior:'smooth',block:'nearest'});});actionButtons.appendChild(reserve);}
   actions.hidden=false;
 }
 function showMessage(text,error){var box=root.querySelector('[data-reserve-message]');box.textContent=text;box.className='bh-booking-message'+(error?' is-error':'');}
 dateSelect.addEventListener('change',function(){load(dateSelect.value);});
 var reserveClose=root.querySelector('[data-reserve-close]');if(reserveClose){reserveClose.addEventListener('click',function(){reservePanel.hidden=true;});}
 var reserveSubmit=root.querySelector('[data-reserve-submit]');if(reserveSubmit){reserveSubmit.addEventListener('click',function(){
   if(!selected)return;var name=root.querySelector('[data-reserve-name]').value.trim();var email=root.querySelector('[data-reserve-email]').value.trim();var places=parseInt(root.querySelector('[data-reserve-places]').value,10)||1;
   showMessage('');reserveSubmit.disabled=true;reserveSubmit.textContent='Reserving…';
   post({action:'bubbahub_booking_reserve',nonce:BubbaHubBookingUI.nonce,session_id:selected.id,customer_name:name,customer_email:email,places:places}).then(function(res){
     if(!res.success){throw new Error((res.data&&res.data.message)||'Reservation failed.');}
     showMessage(res.data.message+' Reference #'+res.data.booking_id);root.querySelector('[data-reserve-name]').value='';root.querySelector('[data-reserve-email]').value='';
     load(dateSelect.value);
   }).catch(function(err){showMessage(err.message||'Reservation failed.',true);}).finally(function(){reserveSubmit.disabled=false;reserveSubmit.textContent='Reserve Spot';});
 });}
});
})();