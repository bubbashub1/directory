(function(){
    'use strict';

    function ready(fn){
        if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    ready(function(){
        var root = document.querySelector('.bh-booking-widget');
        var modal = document.querySelector('#bh-booking-modal');
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