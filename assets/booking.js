(function(){
    'use strict';

    function ready(fn){
        if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function(){
        var root = document.querySelector('.bh-booking-widget');
        var modal = document.querySelector('#bh-booking-modal');
        if(!root || !modal || typeof BubbaHubBookingUI === 'undefined') return;

        var groupId = root.getAttribute('data-group-id');
        var dateSelect = root.querySelector('[data-booking-date]');
        var sessionsBox = root.querySelector('[data-sessions]');
        var status = root.querySelector('.bh-booking-status');
        var actions = root.querySelector('[data-booking-actions]');
        var selectedText = root.querySelector('[data-selected-session]');
        var actionButtons = root.querySelector('[data-action-buttons]');
        var reservePanel = root.querySelector('[data-reserve-panel]');

        if(!dateSelect || !sessionsBox || !status || !actions || !actionButtons) return;

        var selected = null;
        var allSessions = [];
        var initialSessions = [];
        var lastFocused = null;
        var requestNumber = 0;

        try {
            initialSessions = JSON.parse(root.getAttribute('data-initial-sessions') || '[]');
            if(!Array.isArray(initialSessions)) initialSessions = [];
        } catch(e) {
            initialSessions = [];
        }

        function esc(v){
            var d = document.createElement('div');
            d.textContent = v == null ? '' : String(v);
            return d.innerHTML;
        }

        function normaliseDate(v){
            v = String(v == null ? '' : v).trim();
            var m;
            if((m = v.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/)))
                return m[1] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[3]).slice(-2);
            if((m = v.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/)))
                return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
            return v;
        }

        function money(v){
            if(v === null || v === undefined || v === '') return 'Price on request';
            var s = String(v);
            return /^[£$€]/.test(s) ? s : '£' + s;
        }

        function time(v){
            if(!v) return '';
            var p = String(v).split(':');
            var h = parseInt(p[0],10);
            if(isNaN(h)) return v;
            var m = p[1] || '00';
            return (h % 12 || 12) + ':' + m + (h >= 12 ? 'pm' : 'am');
        }

        function formatDate(v){
            var d = new Date(normaliseDate(v) + 'T12:00:00');
            return isNaN(d.getTime()) ? v : d.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
        }

        function post(data){
            var body = new URLSearchParams();
            Object.keys(data).forEach(function(k){ body.append(k, data[k]); });
            return fetch(BubbaHubBookingUI.ajaxUrl, {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                body:body.toString(),
                credentials:'same-origin'
            }).then(function(response){
                return response.text().then(function(text){
                    var json;
                    try { json = JSON.parse(text); }
                    catch(e) { throw new Error('The booking availability response was not valid.'); }
                    return json;
                });
            });
        }

        function localSessions(date){
            var wanted = normaliseDate(date);
            return initialSessions.filter(function(s){
                return normaliseDate(s.date) === wanted;
            });
        }

        function showSessions(list){
            allSessions = Array.isArray(list) ? list : [];
            selected = null;
            actions.hidden = true;
            actionButtons.innerHTML = '';

            if(!allSessions.length){
                sessionsBox.innerHTML = '<div class="bh-booking-empty">' + esc(BubbaHubBookingUI.empty) + '</div>';
                return;
            }

            sessionsBox.innerHTML = allSessions.map(function(s){
                var remaining = Number(s.remaining);
                var capacity = Number(s.capacity);
                var availability = capacity > 0
                    ? (remaining + ' space' + (remaining === 1 ? '' : 's') + ' left')
                    : 'Spaces available';

                return '<button type="button" class="bh-booking-session" data-session-id="' + esc(s.id) + '">' +
                    '<span class="bh-booking-session-main">' +
                        '<span class="bh-booking-session-time">' + esc(time(s.start_time)) + (s.end_time ? ' – ' + esc(time(s.end_time)) : '') + '</span>' +
                        '<span class="bh-booking-session-price">' + esc(money(s.price)) + '</span>' +
                    '</span>' +
                    '<span class="bh-booking-session-meta"><span class="bh-booking-pill">' + esc(availability) + '</span></span>' +
                '</button>';
            }).join('');

            sessionsBox.querySelectorAll('[data-session-id]').forEach(function(btn){
                btn.addEventListener('click', function(){
                    selectSession(parseInt(btn.getAttribute('data-session-id'),10), btn);
                });
            });
        }

        function load(date){
            var thisRequest = ++requestNumber;
            var wanted = normaliseDate(date);

            if(!wanted){
                sessionsBox.innerHTML = '<div class="bh-booking-empty">Choose a date to see available sessions.</div>';
                actions.hidden = true;
                status.textContent = '';
                selected = null;
                return;
            }

            /*
             * Render the server-provided session data first. This is deliberately
             * independent of AJAX, so a valid session can never disappear merely
             * because admin-ajax.php is slow, cached or blocked by another plugin.
             */
            var fallback = localSessions(wanted);
            if(fallback.length){
                status.textContent = fallback.length + ' session' + (fallback.length === 1 ? '' : 's') + ' available';
                showSessions(fallback);
            } else {
                status.textContent = BubbaHubBookingUI.loading;
                sessionsBox.innerHTML = '<div class="bh-booking-empty">' + esc(BubbaHubBookingUI.loading) + '</div>';
                actions.hidden = true;
            }

            post({
                action:'bubbahub_booking_sessions',
                nonce:BubbaHubBookingUI.nonce,
                group_id:groupId,
                date:wanted
            }).then(function(res){
                if(thisRequest !== requestNumber) return;
                if(!res || !res.success) throw new Error((res && res.data && res.data.message) || BubbaHubBookingUI.error);

                var list = res.data && Array.isArray(res.data.sessions) ? res.data.sessions : [];

                /* Never replace known-good local sessions with an empty AJAX result. */
                if(list.length){
                    status.textContent = list.length + ' session' + (list.length === 1 ? '' : 's') + ' available';
                    showSessions(list);
                } else if(!fallback.length){
                    status.textContent = 'No sessions available';
                    showSessions([]);
                }
            }).catch(function(err){
                if(thisRequest !== requestNumber) return;
                if(fallback.length){
                    status.textContent = fallback.length + ' session' + (fallback.length === 1 ? '' : 's') + ' available';
                    showSessions(fallback);
                } else {
                    status.textContent = '';
                    sessionsBox.innerHTML = '<div class="bh-booking-empty">' + esc(err.message || BubbaHubBookingUI.error) + '</div>';
                }
            });
        }

        function selectSession(id, btn){
            selected = allSessions.find(function(s){ return parseInt(s.id,10) === id; });
            if(!selected) return;

            sessionsBox.querySelectorAll('.bh-booking-session').forEach(function(b){ b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');

            selectedText.textContent = formatDate(selected.date) + ' · ' + time(selected.start_time) + (selected.end_time ? ' – ' + time(selected.end_time) : '') + ' · ' + money(selected.price);
            actionButtons.innerHTML = '';

            var action = selected.booking_action || (
                selected.booking_method === 'external' ? 'external' :
                (selected.booking_method === 'none' ? 'none' :
                (selected.reserve_enabled ? 'reserve_spot' : 'book_now'))
            );

            if(action === 'book_now'){
                var book = document.createElement('button');
                book.type = 'button';
                book.className = 'bh-booking-primary';
                book.textContent = 'Book Now';
                book.addEventListener('click', function(){
                    window.dispatchEvent(new CustomEvent('bubbahub:booking-selected',{detail:selected}));
                    showMessage('Booking form selected.');
                });
                actionButtons.appendChild(book);
            } else if(action === 'reserve_spot'){
                var reserve = document.createElement('button');
                reserve.type = 'button';
                reserve.className = 'bh-booking-primary';
                reserve.textContent = 'Reserve Spot';
                reserve.addEventListener('click', function(){
                    if(reservePanel){
                        reservePanel.hidden = false;
                        reservePanel.scrollIntoView({behavior:'smooth',block:'nearest'});
                    }
                });
                actionButtons.appendChild(reserve);
            } else if(action === 'external'){
                if(selected.external_url){
                    var ext = document.createElement('a');
                    ext.className = 'bh-booking-primary';
                    ext.href = selected.external_url;
                    ext.target = '_blank';
                    ext.rel = 'noopener noreferrer';
                    ext.textContent = 'External Booking →';
                    actionButtons.appendChild(ext);
                } else {
                    var missing = document.createElement('span');
                    missing.className = 'bh-booking-message is-error';
                    missing.textContent = 'External booking has been selected, but no booking link has been added.';
                    actionButtons.appendChild(missing);
                }
            }

            actions.hidden = action === 'none';
        }

        function showMessage(text,error){
            var box = root.querySelector('[data-reserve-message]');
            if(box){
                box.textContent = text;
                box.className = 'bh-booking-message' + (error ? ' is-error' : '');
            }
        }

        function openModal(){
            lastFocused = document.activeElement;
            modal.hidden = false;
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('bh-booking-modal-open');

            var close = modal.querySelector('.bh-booking-modal-close');
            if(close) close.focus();

            /* Preserve the user's selected date; otherwise select the first date. */
            if(dateSelect.value) load(dateSelect.value);
            else if(dateSelect.options.length > 1){
                dateSelect.value = dateSelect.options[1].value;
                load(dateSelect.value);
            }
        }

        function closeModal(){
            modal.hidden = true;
            modal.setAttribute('aria-hidden','true');
            document.body.classList.remove('bh-booking-modal-open');
            if(reservePanel) reservePanel.hidden = true;
            if(lastFocused && lastFocused.focus) lastFocused.focus();
        }

        document.querySelectorAll('[data-booking-open]').forEach(function(trigger){
            trigger.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
        });

        modal.querySelectorAll('[data-booking-close]').forEach(function(el){ el.addEventListener('click', closeModal); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !modal.hidden) closeModal(); });

        dateSelect.addEventListener('change', function(){ load(dateSelect.value); });

        var reserveClose = root.querySelector('[data-reserve-close]');
        if(reserveClose) reserveClose.addEventListener('click', function(){ if(reservePanel) reservePanel.hidden = true; });

        var reserveSubmit = root.querySelector('[data-reserve-submit]');
        if(reserveSubmit) reserveSubmit.addEventListener('click', function(){
            if(!selected) return;

            var name = root.querySelector('[data-reserve-name]').value.trim();
            var email = root.querySelector('[data-reserve-email]').value.trim();
            var places = parseInt(root.querySelector('[data-reserve-places]').value,10) || 1;

            showMessage('');
            reserveSubmit.disabled = true;
            reserveSubmit.textContent = 'Reserving…';

            post({
                action:'bubbahub_booking_reserve',
                nonce:BubbaHubBookingUI.nonce,
                session_id:selected.id,
                customer_name:name,
                customer_email:email,
                places:places
            }).then(function(res){
                if(!res.success) throw new Error((res.data && res.data.message) || 'Reservation failed.');
                showMessage(res.data.message + ' Reference #' + res.data.booking_id);
                root.querySelector('[data-reserve-name]').value = '';
                root.querySelector('[data-reserve-email]').value = '';
                load(dateSelect.value);
            }).catch(function(err){
                showMessage(err.message || 'Reservation failed.',true);
            }).finally(function(){
                reserveSubmit.disabled = false;
                reserveSubmit.textContent = 'Reserve Spot';
            });
        });

        /* If the browser restores a date, populate sessions immediately. */
        if(dateSelect.value) load(dateSelect.value);
    });
})();
