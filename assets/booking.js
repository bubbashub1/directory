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

        modal.querySelector('.bh-booking-confirmation-close').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-backdrop').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-done').addEventListener('click', close);
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

        // The confirmation modal is used after a successful Reserve Spot submission.
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
})();