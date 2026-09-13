(function(){
    'use strict';

    function ready(fn){
        if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    function showBookingConfirmation(){
        if(document.querySelector('#bh-booking-confirmation')) return;

        var modal = document.createElement('div');
        modal.id = 'bh-booking-confirmation';
        modal.className = 'bh-booking-confirmation-modal';
        modal.setAttribute('role','dialog');
        modal.setAttribute('aria-modal','true');
        modal.setAttribute('aria-labelledby','bh-booking-confirmation-title');
        modal.innerHTML = '<div class="bh-booking-confirmation-backdrop"></div>' +
            '<div class="bh-booking-confirmation-dialog">' +
                '<button type="button" class="bh-booking-confirmation-close" aria-label="Close confirmation">×</button>' +
                '<div class="bh-booking-confirmation-icon" aria-hidden="true">✓</div>' +
                '<h2 id="bh-booking-confirmation-title">Thank you for your booking.</h2>' +
                '<p>The organiser will be in touch to confirm booking</p>' +
                '<button type="button" class="bh-booking-primary bh-booking-confirmation-done">Done</button>' +
            '</div>';

        document.body.appendChild(modal);
        document.body.classList.add('bh-booking-confirmation-open');

        function close(){
            modal.remove();
            document.body.classList.remove('bh-booking-confirmation-open');
        }

        function goToMyHub(){
            window.location.href = new URL('/myhub/', window.location.origin).toString();
        }

        modal.querySelector('.bh-booking-confirmation-close').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-backdrop').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-done').addEventListener('click', goToMyHub);
        document.addEventListener('keydown', function escapeHandler(e){
            if(e.key === 'Escape'){
                close();
                document.removeEventListener('keydown', escapeHandler);
            }
        });

        setTimeout(function(){
            var done = modal.querySelector('.bh-booking-confirmation-done');
            if(done) done.focus();
        }, 0);
    }

    ready(function(){
        var root = document.querySelector('.bh-booking-widget');
        var modal = document.querySelector('#bh-booking-modal');

        var successMessage = document.querySelector('.bh-booking-message.is-success');
        if(successMessage) showBookingConfirmation();

        if(!root || !modal) return;

        var dateSelect = root.querySelector('[data-booking-date]') || root.querySelector('#bh-booking-date');
        var continueLink = root.querySelector('[data-booking-continue]');
        var lastFocused = null;

        if(!dateSelect || !continueLink) return;

        var bookUrl = root.getAttribute('data-book-url') || '/book/';
        var groupId = root.getAttribute('data-group-id') || '';

        function updateContinue(){
            var date = dateSelect.value;
            if(!date || !groupId){
                continueLink.href = '#';
                continueLink.setAttribute('aria-disabled','true');
                continueLink.classList.add('is-disabled');
                return;
            }

            var url = new URL(bookUrl, window.location.origin);
            url.searchParams.set('group_id', groupId);
            url.searchParams.set('date', date);
            continueLink.href = url.toString();
            continueLink.removeAttribute('aria-disabled');
            continueLink.classList.remove('is-disabled');
        }

        function openModal(){
            lastFocused = document.activeElement;
            modal.hidden = false;
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('bh-booking-modal-open');
            updateContinue();
            var close = modal.querySelector('.bh-booking-modal-close');
            if(close) close.focus();
        }

        function closeModal(){
            modal.hidden = true;
            modal.setAttribute('aria-hidden','true');
            document.body.classList.remove('bh-booking-modal-open');
            if(lastFocused && lastFocused.focus) lastFocused.focus();
        }

        document.querySelectorAll('[data-booking-open]').forEach(function(trigger){
            trigger.addEventListener('click', function(e){
                e.preventDefault();
                openModal();
            });
        });

        modal.querySelectorAll('[data-booking-close]').forEach(function(el){
            el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape' && !modal.hidden) closeModal();
        });

        dateSelect.addEventListener('change', updateContinue);
        continueLink.addEventListener('click', function(e){
            if(!dateSelect.value){
                e.preventDefault();
                dateSelect.focus();
            }
        });

        updateContinue();
    });

    /*
     * Ninja Forms stores field values in a Backbone model. Updating only the
     * DOM can leave the visible hidden field changed while the submitted value
     * remains empty. Use Ninja Forms' jQuery change pattern when available.
     */
    ready(function(){
        var form = document.querySelector('.nf-form-cont');
        if(!form) return;

        var contextScript = document.querySelector('script[data-bh-ninja-context]');
        if(!contextScript) return;

        var map;
        var context;
        try {
            map = JSON.parse(contextScript.getAttribute('data-field-map') || '{}');
            context = JSON.parse(contextScript.getAttribute('data-context') || '{}');
        } catch(e) {
            return;
        }

        function inputFor(key){
            if(!map[key]) return null;
            return document.getElementById('nf-field-' + map[key]) || form.querySelector('[name="nf-field-' + map[key] + '"]');
        }

        function setField(key, value){
            var input = inputFor(key);
            if(!input) return;
            var stringValue = value == null ? '' : String(value);

            if(window.jQuery){
                window.jQuery(input).val(stringValue).trigger('change');
            } else {
                input.value = stringValue;
                input.dispatchEvent(new Event('input', {bubbles:true}));
                input.dispatchEvent(new Event('change', {bubbles:true}));
            }
        }

        function sync(){
            var rows = Array.prototype.slice.call(document.querySelectorAll('[data-ticket-row]'));
            var items = [];
            var places = 0;
            var total = 0;

            rows.forEach(function(row){
                var input = row.querySelector('[data-ticket-quantity]');
                if(!input) return;
                var qty = Math.max(0, parseInt(input.value || '0', 10) || 0);
                var slug = row.getAttribute('data-ticket-slug') || '';
                var price = parseFloat(row.getAttribute('data-ticket-price') || '0') || 0;
                if(qty > 0 && slug) items.push({slug:slug, quantity:qty});
                places += qty;
                total += qty * price;
            });

            setField('group_id', context.group_id);
            setField('session_id', context.session_id);
            setField('booking_date', context.booking_date);
            setField('ticket_breakdown', JSON.stringify(items));
            setField('total_places', places);
            setField('total_price', total.toFixed(2));
            setField('booking_method', 'book_now');
            setField('booking_status', 'confirmed');
        }

        sync();
        document.addEventListener('click', function(event){
            if(event.target.closest('[data-ticket-plus]') || event.target.closest('[data-ticket-minus]')){
                window.setTimeout(sync, 50);
            }
        });
        document.addEventListener('input', function(event){
            if(event.target.matches('[data-ticket-quantity]')) sync();
        });
    });
})();