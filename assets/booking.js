(function(){
    'use strict';

    function ready(fn){
        if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    var bookNowHandled = false;

    function showBookingConfirmation(type){
        if(document.querySelector('#bh-booking-confirmation')) return;

        var isBookNow = type === 'book_now';
        var title = isBookNow ? 'Thank you for your booking.' : 'Thank you for your reservation.';
        var message = isBookNow
            ? 'Your booking has been received and confirmed.'
            : 'The organiser will be in touch to confirm booking';

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
                '<h2 id="bh-booking-confirmation-title">' + title + '</h2>' +
                '<p>' + message + '</p>' +
                '<button type="button" class="bh-booking-primary bh-booking-confirmation-done">Done</button>' +
            '</div>';

        document.body.appendChild(modal);
        document.body.classList.add('bh-booking-confirmation-open');

        function close(){
            modal.remove();
            document.body.classList.remove('bh-booking-confirmation-open');
        }
        function goToMyHub(){ window.location.href = new URL('/myhub/', window.location.origin).toString(); }

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

    function showGetPaidModal(url){
        if(document.querySelector('#bh-booking-confirmation')) return;
        var modal = document.createElement('div');
        modal.id = 'bh-booking-confirmation';
        modal.className = 'bh-booking-confirmation-modal';
        modal.setAttribute('role','dialog');
        modal.setAttribute('aria-modal','true');
        modal.setAttribute('aria-labelledby','bh-booking-confirmation-title');
        modal.innerHTML = '<div class="bh-booking-confirmation-backdrop"></div>' +
            '<div class="bh-booking-confirmation-dialog">' +
                '<button type="button" class="bh-booking-confirmation-close" aria-label="Close payment message">×</button>' +
                '<div class="bh-booking-confirmation-icon" aria-hidden="true">£</div>' +
                '<h2 id="bh-booking-confirmation-title">Booking received</h2>' +
                '<p>Your place is being held while you complete payment securely.</p>' +
                '<button type="button" class="bh-booking-primary bh-booking-payment-continue">Continue to payment</button>' +
            '</div>';
        document.body.appendChild(modal);
        document.body.classList.add('bh-booking-confirmation-open');

        function close(){
            modal.remove();
            document.body.classList.remove('bh-booking-confirmation-open');
        }
        function pay(){ window.location.href = url; }
        modal.querySelector('.bh-booking-confirmation-close').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-backdrop').addEventListener('click', close);
        modal.querySelector('.bh-booking-payment-continue').addEventListener('click', pay);
        setTimeout(function(){
            var button = modal.querySelector('.bh-booking-payment-continue');
            if(button) button.focus();
        }, 0);
    }

    function showPaymentError(){
        if(document.querySelector('#bh-booking-confirmation')) return;
        var modal = document.createElement('div');
        modal.id = 'bh-booking-confirmation';
        modal.className = 'bh-booking-confirmation-modal';
        modal.setAttribute('role','dialog');
        modal.setAttribute('aria-modal','true');
        modal.innerHTML = '<div class="bh-booking-confirmation-backdrop"></div>' +
            '<div class="bh-booking-confirmation-dialog">' +
                '<button type="button" class="bh-booking-confirmation-close" aria-label="Close">×</button>' +
                '<div class="bh-booking-confirmation-icon" aria-hidden="true">!</div>' +
                '<h2>Booking received</h2>' +
                '<p>We received your booking, but the payment page could not be opened. Please try again from MyHub or contact the organiser.</p>' +
                '<button type="button" class="bh-booking-primary bh-booking-confirmation-done">Done</button>' +
            '</div>';
        document.body.appendChild(modal);
        document.body.classList.add('bh-booking-confirmation-open');
        function close(){ modal.remove(); document.body.classList.remove('bh-booking-confirmation-open'); }
        modal.querySelector('.bh-booking-confirmation-close').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-backdrop').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-done').addEventListener('click', function(){ window.location.href = new URL('/myhub/', window.location.origin).toString(); });
    }

    function getHiddenValue(form, fieldKey){
        if(!form) return '';
        var input = form.querySelector('input[name*="' + fieldKey + '"]');
        return input ? input.value : '';
    }

    function extractSubmissionId(response){
        var data = response && response.data ? response.data : {};
        return parseInt(data.id || data.sub_id || data.submission_id || data.submissionId || '', 10) || 0;
    }

    function fetchGetPaidCheckout(response, attempt){
        attempt = attempt || 0;
        var form = document.querySelector('.nf-form-cont');
        var submissionId = extractSubmissionId(response);
        var sessionId = getHiddenValue(form, 'session_id');
        var email = getHiddenValue(form, 'email');
        var endpoint = new URL('/wp-json/bubbahub/v1/getpaid/checkout', window.location.origin);
        if(submissionId) endpoint.searchParams.set('submission_id', submissionId);
        if(sessionId) endpoint.searchParams.set('session_id', sessionId);
        if(email) endpoint.searchParams.set('email', email);

        fetch(endpoint.toString(), {credentials:'same-origin', headers:{'Accept':'application/json'}})
            .then(function(res){ return res.json().then(function(data){ return {ok:res.ok, data:data}; }); })
            .then(function(result){
                if(result.ok && result.data && result.data.url){
                    showGetPaidModal(result.data.url);
                    return;
                }
                if(attempt < 5){
                    window.setTimeout(function(){ fetchGetPaidCheckout(response, attempt + 1); }, 700);
                    return;
                }
                showPaymentError();
            })
            .catch(function(){
                if(attempt < 5){
                    window.setTimeout(function(){ fetchGetPaidCheckout(response, attempt + 1); }, 700);
                    return;
                }
                showPaymentError();
            });
    }

    function handleBookNowSuccess(response){
        if(bookNowHandled) return;
        bookNowHandled = true;
        fetchGetPaidCheckout(response || {}, 0);
    }

    function watchNinjaSuccess(){
        var form = document.querySelector('.nf-form-cont');
        if(!form) return;

        function validResponse(response){
            if(!response) return true;
            if(response.errors && Object.keys(response.errors).length) return false;
            if(response.data && response.data.errors && Object.keys(response.data.errors).length) return false;
            return true;
        }

        function check(){
            var response = form.querySelector('.nf-response-msg');
            if(response && response.textContent.trim()) handleBookNowSuccess(window.__bhLastNinjaResponse || {});
        }
        check();

        if(window.MutationObserver){
            var observer = new MutationObserver(check);
            observer.observe(form, {childList:true, subtree:true, characterData:true});
        }

        if(window.jQuery){
            window.jQuery(document).on('nfFormSubmitResponse', function(event, response){
                if(!validResponse(response)) return;
                window.__bhLastNinjaResponse = response || {};
                handleBookNowSuccess(response || {});
            });
        }

        if(typeof window.nfRadio !== 'undefined'){
            try {
                window.nfRadio.channel('forms').on('submit:response', function(response){
                    if(!validResponse(response)) return;
                    window.__bhLastNinjaResponse = response || {};
                    handleBookNowSuccess(response || {});
                });
            } catch(e) {}
        }
    }

    ready(function(){
        var root = document.querySelector('.bh-booking-widget');
        var modal = document.querySelector('#bh-booking-modal');
        var successMessage = document.querySelector('.bh-booking-message.is-success');
        if(successMessage) showBookingConfirmation('reserve_spot');
        watchNinjaSuccess();
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
            trigger.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
        });
        modal.querySelectorAll('[data-booking-close]').forEach(function(el){ el.addEventListener('click', closeModal); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !modal.hidden) closeModal(); });
        dateSelect.addEventListener('change', updateContinue);
        continueLink.addEventListener('click', function(e){ if(!dateSelect.value){ e.preventDefault(); dateSelect.focus(); } });
        updateContinue();
    });

    ready(function(){
        var form = document.querySelector('.nf-form-cont');
        if(!form) return;
        function syncHiddenFields(){
            if(!window.jQuery) return;
            window.jQuery(form).find('input[type="hidden"]').each(function(){ window.jQuery(this).trigger('change'); });
        }
        setTimeout(syncHiddenFields, 100);
        setTimeout(syncHiddenFields, 500);
        document.addEventListener('click', function(event){
            if(event.target.closest('[data-ticket-plus]') || event.target.closest('[data-ticket-minus]')){
                window.setTimeout(syncHiddenFields, 100);
                window.setTimeout(syncHiddenFields, 300);
            }
        });
        document.addEventListener('input', function(event){
            if(event.target.matches('[data-ticket-quantity]')) window.setTimeout(syncHiddenFields, 50);
        });
    });
})();