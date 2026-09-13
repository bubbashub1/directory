(function(){
    'use strict';

    function ready(fn){
        if(document.readyState==='loading'){
            document.addEventListener('DOMContentLoaded',fn);
        }else{
            fn();
        }
    }

    ready(function(){
        var root=document.querySelector('.bh-booking-widget'),
            modal=document.querySelector('#bh-booking-modal');

        if(!root||!modal||typeof BubbaHubBookingUI==='undefined')return;

        var groupId=root.getAttribute('data-group-id'),
            dateSelect=root.querySelector('[data-booking-date]'),
            sessionsBox=root.querySelector('[data-sessions]'),
            status=root.querySelector('.bh-booking-status'),
            actions=root.querySelector('[data-booking-actions]'),
            selectedText=root.querySelector('[data-selected-session]'),
            actionButtons=root.querySelector('[data-action-buttons]'),
            reservePanel=root.querySelector('[data-reserve-panel]');

        var selected=null,
            allSessions=[],
            initialSessions=[],
            lastFocused=null,
            requestNumber=0;

        try{
            initialSessions=JSON.parse(root.getAttribute('data-initial-sessions')||'[]');
            if(!Array.isArray(initialSessions))initialSessions=[];
        }catch(e){
            initialSessions=[];
        }

        function esc(v){
            var d=document.createElement('div');
            d.textContent=v==null?'':String(v);
            return d.innerHTML;
        }

        function money(v){
            if(v===null||v===undefined||v==='')return 'Price on request';
            var s=String(v);
            return /^[£$€]/.test(s)?s:'£'+s;
        }

        function time(v){
            if(!v)return '';
            var p=String(v).split(':'),
                h=parseInt(p[0],10);
            if(isNaN(h))return v;
            var m=p[1]||'00';
            return (h%12||12)+':'+m+(h>=12?'pm':'am');
        }

        function formatDate(v){
            var d=new Date(v+'T12:00:00');
            return isNaN(d.getTime())?v:d.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
        }

        function post(data){
            var body=new URLSearchParams();
            Object.keys(data).forEach(function(k){
                body.append(k,data[k]);
            });

            return fetch(BubbaHubBookingUI.ajaxUrl,{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                body:body.toString(),
                credentials:'same-origin'
            }).then(function(r){
                return r.json();
            });
        }

        function openModal(){
            lastFocused=document.activeElement;
            modal.hidden=false;
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('bh-booking-modal-open');

            var c=modal.querySelector('.bh-booking-modal-close');
            if(c)c.focus();

            // Always load the currently selected date when the modal opens.
            // Browsers can restore a <select> value after navigation/back-forward
            // caching, so checking only for an empty value can leave the session
            // area stuck on "Choose a date" even though a date is selected.
            if(dateSelect&&dateSelect.value){
                load(dateSelect.value);
            }else if(dateSelect&&dateSelect.options.length>1){
                dateSelect.value=dateSelect.options[1].value;
                load(dateSelect.value);
            }
        }

        function closeModal(){
            modal.hidden=true;
            modal.setAttribute('aria-hidden','true');
            document.body.classList.remove('bh-booking-modal-open');
            if(reservePanel)reservePanel.hidden=true;
            if(lastFocused&&lastFocused.focus)lastFocused.focus();
        }

        document.querySelectorAll('[data-booking-open]').forEach(function(t){
            t.addEventListener('click',function(e){
                e.preventDefault();
                openModal();
            });
        });

        modal.querySelectorAll('[data-booking-close]').forEach(function(e){
            e.addEventListener('click',closeModal);
        });

        document.addEventListener('keydown',function(e){
            if(e.key==='Escape'&&!modal.hidden)closeModal();
        });

        function showSessions(list){
            allSessions=Array.isArray(list)?list:[];
            selected=null;
            actions.hidden=true;
            actionButtons.innerHTML='';

            if(!allSessions.length){
                sessionsBox.innerHTML='<div class="bh-booking-empty">'+esc(BubbaHubBookingUI.empty)+'</div>';
                return;
            }

            sessionsBox.innerHTML=allSessions.map(function(s){
                var availability=s.capacity>0
                    ?(s.remaining+' space'+(s.remaining===1?'':'s')+' left')
                    :'Spaces available';

                return '<button type="button" class="bh-booking-session" data-session-id="'+esc(s.id)+'">'+
                    '<span class="bh-booking-session-main">'+
                        '<span class="bh-booking-session-time">'+esc(time(s.start_time))+(s.end_time?' – '+esc(time(s.end_time)):'')+'</span>'+
                        '<span class="bh-booking-session-price">'+esc(money(s.price))+'</span>'+
                    '</span>'+ 
                    '<span class="bh-booking-session-meta"><span class="bh-booking-pill">'+esc(availability)+'</span></span>'+ 
                '</button>';
            }).join('');

            sessionsBox.querySelectorAll('[data-session-id]').forEach(function(btn){
                btn.addEventListener('click',function(){
                    selectSession(parseInt(btn.getAttribute('data-session-id'),10),btn);
                });
            });
        }

        function localSessions(date){
            return initialSessions.filter(function(s){
                return String(s.date)===String(date);
            });
        }

        function load(date){
            var thisRequest=++requestNumber;

            if(!date){
                sessionsBox.innerHTML='<div class="bh-booking-empty">Choose a date to see available sessions.</div>';
                actions.hidden=true;
                status.textContent='';
                selected=null;
                return;
            }

            var fallback=localSessions(date);

            if(fallback.length){
                status.textContent=fallback.length+' session'+(fallback.length===1?'':'s')+' available';
                showSessions(fallback);
            }else{
                status.textContent=BubbaHubBookingUI.loading;
                sessionsBox.innerHTML='<div class="bh-booking-empty">'+esc(BubbaHubBookingUI.loading)+'</div>';
                actions.hidden=true;
            }

            post({
                action:'bubbahub_booking_sessions',
                nonce:BubbaHubBookingUI.nonce,
                group_id:groupId,
                date:date
            }).then(function(res){
                if(thisRequest!==requestNumber)return;
                if(!res.success)throw new Error((res.data&&res.data.message)||BubbaHubBookingUI.error);

                var list=res.data.sessions||[];

                if(list.length){
                    status.textContent=list.length+' session'+(list.length===1?'':'s')+' available';
                    showSessions(list);
                }else if(!fallback.length){
                    status.textContent='No sessions available';
                    showSessions([]);
                }
            }).catch(function(err){
                if(thisRequest!==requestNumber)return;

                if(fallback.length){
                    status.textContent=fallback.length+' session'+(fallback.length===1?'':'s')+' available';
                    showSessions(fallback);
                }else{
                    status.textContent='';
                    sessionsBox.innerHTML='<div class="bh-booking-empty">'+esc(err.message||BubbaHubBookingUI.error)+'</div>';
                }
            });
        }

        function selectSession(id,btn){
            selected=allSessions.find(function(s){
                return parseInt(s.id,10)===id;
            });
            if(!selected)return;

            sessionsBox.querySelectorAll('.bh-booking-session').forEach(function(b){
                b.classList.remove('is-selected');
            });
            btn.classList.add('is-selected');

            selectedText.textContent=formatDate(selected.date)+' · '+time(selected.start_time)+(selected.end_time?' – '+time(selected.end_time):'')+' · '+money(selected.price);
            actionButtons.innerHTML='';

            var action=selected.booking_action||(
                selected.booking_method==='external'
                    ?'external'
                    :(selected.booking_method==='none'
                        ?'none'
                        :(selected.reserve_enabled?'reserve_spot':'book_now'))
            );

            if(action==='book_now'){
                var book=document.createElement('button');
                book.type='button';
                book.className='bh-booking-primary';
                book.textContent='Book Now';
                book.addEventListener('click',function(){
                    window.dispatchEvent(new CustomEvent('bubbahub:booking-selected',{detail:selected}));
                    showMessage('Booking form selected.');
                });
                actionButtons.appendChild(book);
            }else if(action==='reserve_spot'){
                var reserve=document.createElement('button');
                reserve.type='button';
                reserve.className='bh-booking-primary';
                reserve.textContent='Reserve Spot';
                reserve.addEventListener('click',function(){
                    reservePanel.hidden=false;
                    reservePanel.scrollIntoView({behavior:'smooth',block:'nearest'});
                });
                actionButtons.appendChild(reserve);
            }else if(action==='external'){
                if(selected.external_url){
                    var ext=document.createElement('a');
                    ext.className='bh-booking-primary';
                    ext.href=selected.external_url;
                    ext.target='_blank';
                    ext.rel='noopener noreferrer';
                    ext.textContent='External Booking →';
                    actionButtons.appendChild(ext);
                }else{
                    var missing=document.createElement('span');
                    missing.className='bh-booking-message is-error';
                    missing.textContent='External booking has been selected, but no booking link has been added.';
                    actionButtons.appendChild(missing);
                }
            }

            actions.hidden=action==='none';
        }

        function showMessage(text,error){
            var box=root.querySelector('[data-reserve-message]');
            if(box){
                box.textContent=text;
                box.className='bh-booking-message'+(error?' is-error':'');
            }
        }

        dateSelect.addEventListener('change',function(){
            load(dateSelect.value);
        });

        // Also initialise an already-selected date on page load. This covers
        // restored form state and ensures the widget never opens with a selected
        // date but an empty session area.
        if(dateSelect&&dateSelect.value){
            load(dateSelect.value);
        }

        var reserveClose=root.querySelector('[data-reserve-close]');
        if(reserveClose)reserveClose.addEventListener('click',function(){
            reservePanel.hidden=true;
        });

        var reserveSubmit=root.querySelector('[data-reserve-submit]');
        if(reserveSubmit)reserveSubmit.addEventListener('click',function(){
            if(!selected)return;

            var name=root.querySelector('[data-reserve-name]').value.trim(),
                email=root.querySelector('[data-reserve-email]').value.trim(),
                places=parseInt(root.querySelector('[data-reserve-places]').value,10)||1;

            showMessage('');
            reserveSubmit.disabled=true;
            reserveSubmit.textContent='Reserving…';

            post({
                action:'bubbahub_booking_reserve',
                nonce:BubbaHubBookingUI.nonce,
                session_id:selected.id,
                customer_name:name,
                customer_email:email,
                places:places
            }).then(function(res){
                if(!res.success)throw new Error((res.data&&res.data.message)||'Reservation failed.');

                showMessage(res.data.message+' Reference #'+res.data.booking_id);
                root.querySelector('[data-reserve-name]').value='';
                root.querySelector('[data-reserve-email]').value='';
                load(dateSelect.value);
            }).catch(function(err){
                showMessage(err.message||'Reservation failed.',true);
            }).finally(function(){
                reserveSubmit.disabled=false;
                reserveSubmit.textContent='Reserve Spot';
            });
        });
    });
})();
