(function(){
    'use strict';
    function ready(fn){ if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn); else fn(); }
    var bookNowHandled = false;
    var bookingContext = { sessionId: '', email: '', groupId: '', bookingDate: '' };

    function goToMyHub(){ window.location.href = new URL('/myhub/', window.location.origin).toString(); }

    function closeConfirmationModal(){
        var modal = document.querySelector('#bh-booking-confirmation');
        if(modal) modal.remove();
        document.body.classList.remove('bh-booking-confirmation-open');
    }

    function showBookingConfirmation(type){
        if(document.querySelector('#bh-booking-confirmation')) return;
        var isBookNow = type === 'book_now';
        var title = isBookNow ? 'Thank you for your booking.' : 'Thank you for your reservation.';
        var message = isBookNow ? 'Your booking has been received and confirmed.' : 'The organiser will be in touch to confirm booking';
        var modal = document.createElement('div');
        modal.id = 'bh-booking-confirmation';
        modal.className = 'bh-booking-confirmation-modal';
        modal.setAttribute('role','dialog'); modal.setAttribute('aria-modal','true'); modal.setAttribute('aria-labelledby','bh-booking-confirmation-title');
        modal.innerHTML = '<div class="bh-booking-confirmation-backdrop"></div><div class="bh-booking-confirmation-dialog"><button type="button" class="bh-booking-confirmation-close" aria-label="Close confirmation">×</button><div class="bh-booking-confirmation-icon" aria-hidden="true">✓</div><h2 id="bh-booking-confirmation-title">' + title + '</h2><p>' + message + '</p><button type="button" class="bh-booking-primary bh-booking-confirmation-done">Done</button></div>';
        document.body.appendChild(modal); document.body.classList.add('bh-booking-confirmation-open');
        function close(){ closeConfirmationModal(); }
        modal.querySelector('.bh-booking-confirmation-close').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-backdrop').addEventListener('click', close);
        modal.querySelector('.bh-booking-confirmation-done').addEventListener('click', goToMyHub);
        setTimeout(function(){ var button = modal.querySelector('.bh-booking-confirmation-done'); if(button) button.focus(); }, 0);
    }

    function showPaymentModal(url, walletUrl){
        var modal = document.querySelector('#bh-booking-confirmation');
        if(!modal){
            modal = document.createElement('div');
            modal.id = 'bh-booking-confirmation';
            modal.className = 'bh-booking-confirmation-modal';
            modal.setAttribute('role','dialog');
            modal.setAttribute('aria-modal','true');
            modal.setAttribute('aria-labelledby','bh-booking-confirmation-title');
            modal.innerHTML = '<div class="bh-booking-confirmation-backdrop"></div><div class="bh-booking-confirmation-dialog"><button type="button" class="bh-booking-confirmation-close" aria-label="Close payment message">×</button><div class="bh-booking-confirmation-icon" aria-hidden="true">£</div><h2 id="bh-booking-confirmation-title">Booking received</h2><p class="bh-booking-payment-message">Your booking has been received. Choose how you would like to pay.</p><div class="bh-booking-payment-actions"><button type="button" class="bh-booking-primary bh-booking-payment-wallet">Pay with Wallet</button><button type="button" class="bh-booking-primary bh-booking-payment-continue">Pay by card</button></div></div>';
            document.body.appendChild(modal);
            document.body.classList.add('bh-booking-confirmation-open');
            modal.querySelector('.bh-booking-confirmation-close').addEventListener('click', closeConfirmationModal);
            modal.querySelector('.bh-booking-confirmation-backdrop').addEventListener('click', closeConfirmationModal);
        }
        var button = modal.querySelector('.bh-booking-payment-continue');
        var walletButton = modal.querySelector('.bh-booking-payment-wallet');
        var message = modal.querySelector('.bh-booking-payment-message');
        if(url){
            if(message) message.textContent = walletUrl ? 'Your booking has been received. Choose Wallet or card to complete payment.' : 'Your booking has been received. Continue to secure payment.';
            if(walletButton){ walletButton.style.display = walletUrl ? '' : 'none'; walletButton.disabled = !walletUrl; walletButton.onclick = function(){ window.location.href = walletUrl; }; }
            if(button){
                button.disabled = false;
                button.textContent = 'Continue to payment';
                button.onclick = function(){ window.location.href = url; };
            }
            setTimeout(function(){ if(button) button.focus(); }, 0);
        } else {
            if(message) message.textContent = 'Your booking has been received. Preparing secure payment…';
            if(button){ button.disabled = true; button.textContent = 'Preparing payment…'; }
            setTimeout(function(){ if(button) button.focus(); }, 0);
        }
    }

    function showPaymentError(detail){
        var modal = document.querySelector('#bh-booking-confirmation');
        if(!modal){ showPaymentModal(null, null); modal = document.querySelector('#bh-booking-confirmation'); }
        if(!modal) return;
        var message = modal.querySelector('.bh-booking-payment-message');
        var button = modal.querySelector('.bh-booking-payment-continue');
        var walletButton = modal.querySelector('.bh-booking-payment-wallet');
        var title = modal.querySelector('#bh-booking-confirmation-title');
        if(title) title.textContent = 'Booking received';
        var safeDetail = detail ? String(detail).replace(/\s+/g, ' ').trim() : '';
        if(safeDetail.length > 180) safeDetail = safeDetail.substring(0, 177) + '…';
        if(message){
            message.textContent = safeDetail
                ? 'We received your booking, but the secure Stripe payment page could not be created. ' + safeDetail
                : 'We received your booking, but the secure payment page could not be opened. Please try again from MyHub or contact the organiser.';
        }
        if(button){ button.disabled = false; button.textContent = 'Go to MyHub'; button.onclick = goToMyHub; }
        if(walletButton){ walletButton.style.display = 'none'; }
        setTimeout(function(){ if(button) button.focus(); }, 0);
    }

    function getHiddenValue(form, fieldKey){
        if(!form) return '';
        var input = form.querySelector('input[name*="' + fieldKey + '"]');
        return input ? input.value : '';
    }

    function rememberBookingContext(form){
        if(!form) return;
        var sessionId = getHiddenValue(form, 'session_id');
        var email = getHiddenValue(form, 'email');
        if(sessionId) bookingContext.sessionId = String(sessionId).trim();
        if(email) bookingContext.email = String(email).trim();
        if(!bookingContext.groupId){
            var groupId = getHiddenValue(form, 'group_id');
            if(groupId) bookingContext.groupId = String(groupId).trim();
        }
        if(!bookingContext.bookingDate){
            var bookingDate = getHiddenValue(form, 'booking_date');
            if(bookingDate) bookingContext.bookingDate = String(bookingDate).trim();
        }
    }

    function initialiseBookingContext(form){
        try {
            var params = new URLSearchParams(window.location.search);
            bookingContext.sessionId = params.get('session_id') || bookingContext.sessionId;
            bookingContext.groupId = params.get('group_id') || bookingContext.groupId;
            bookingContext.bookingDate = params.get('date') || bookingContext.bookingDate;
        } catch(e) {}
        rememberBookingContext(form);
        if(form){
            form.addEventListener('input', function(event){
                var key = event.target && event.target.name ? event.target.name : '';
                if(key.indexOf('email') !== -1 && event.target.value) bookingContext.email = String(event.target.value).trim();
                if(key.indexOf('session_id') !== -1 && event.target.value) bookingContext.sessionId = String(event.target.value).trim();
            });
            form.addEventListener('change', function(event){
                var key = event.target && event.target.name ? event.target.name : '';
                if(key.indexOf('email') !== -1 && event.target.value) bookingContext.email = String(event.target.value).trim();
                if(key.indexOf('session_id') !== -1 && event.target.value) bookingContext.sessionId = String(event.target.value).trim();
            });
        }
        document.addEventListener('input', function(event){
            if(!event.target || !event.target.name) return;
            var key = event.target.name;
            if(key.indexOf('email') !== -1 && event.target.value) bookingContext.email = String(event.target.value).trim();
        });
    }

    function extractSubmissionId(response){
        var data = response && response.data ? response.data : {};
        return parseInt(data.id || data.sub_id || data.submission_id || data.submissionId || '', 10) || 0;
    }

    function getCheckoutError(result){
        if(!result || !result.data) return '';
        var data = result.data;
        if(typeof data.message === 'string' && data.message) return data.message;
        if(typeof data.error === 'string' && data.error) return data.error;
        if(typeof data.code === 'string' && data.code) return data.code.replace(/_/g, ' ');
        return '';
    }

    function fetchPaymentCheckout(response, attempt){
        attempt = attempt || 0;
        var form = document.querySelector('.nf-form-cont');
        rememberBookingContext(form);
        var submissionId = extractSubmissionId(response);
        var sessionId = bookingContext.sessionId;
        var email = bookingContext.email;
        var endpoint = new URL('/wp-json/bubbahub/v1/getpaid/checkout', window.location.origin);
        if(submissionId) endpoint.searchParams.set('submission_id', submissionId);
        if(sessionId) endpoint.searchParams.set('session_id', sessionId);
        if(email) endpoint.searchParams.set('email', email);
        if(bookingContext.groupId) endpoint.searchParams.set('group_id', bookingContext.groupId);
        if(bookingContext.bookingDate) endpoint.searchParams.set('booking_date', bookingContext.bookingDate);
        fetch(endpoint.toString(), {credentials:'same-origin', headers:{'Accept':'application/json'}})
            .then(function(res){ return res.json().then(function(data){ return {ok:res.ok, status:res.status, data:data}; }); })
            .then(function(result){
                if(result.ok && result.data && result.data.url){ showPaymentModal(result.data.url, result.data.wallet_url || ''); return; }
                if(result.status === 409 || (result.data && result.data.code === 'payment_not_required')){
                    closeConfirmationModal();
                    showBookingConfirmation('book_now');
                    return;
                }
                if(attempt < 5){ window.setTimeout(function(){ fetchPaymentCheckout(response, attempt + 1); }, 700); return; }
                showPaymentError(getCheckoutError(result));
            })
            .catch(function(){
                if(attempt < 5){ window.setTimeout(function(){ fetchPaymentCheckout(response, attempt + 1); }, 700); return; }
                showPaymentError('The website could not reach the secure payment service.');
            });
    }

    function handleBookNowSuccess(response){
        if(bookNowHandled) return;
        bookNowHandled = true;
        var form = document.querySelector('.nf-form-cont');
        initialiseBookingContext(form);
        showPaymentModal(null);
        fetchPaymentCheckout(response || {}, 0);
    }

    function watchNinjaSuccess(){
        var form = document.querySelector('.nf-form-cont'); if(!form) return;
        initialiseBookingContext(form);
        function validResponse(response){
            if(!response) return true;
            if(response.errors && Object.keys(response.errors).length) return false;
            if(response.data && response.data.errors && Object.keys(response.data.errors).length) return false;
            return true;
        }
        function check(){ var response = form.querySelector('.nf-response-msg'); if(response && response.textContent.trim()) handleBookNowSuccess(window.__bhLastNinjaResponse || {}); }
        check();
        if(window.MutationObserver){ var observer = new MutationObserver(check); observer.observe(form, {childList:true, subtree:true, characterData:true}); }
        if(window.jQuery){ window.jQuery(document).on('nfFormSubmitResponse', function(event, response){ if(!validResponse(response)) return; window.__bhLastNinjaResponse = response || {}; handleBookNowSuccess(response || {}); }); }
        if(typeof window.nfRadio !== 'undefined'){
            try { window.nfRadio.channel('forms').on('submit:response', function(response){ if(!validResponse(response)) return; window.__bhLastNinjaResponse = response || {}; handleBookNowSuccess(response || {}); }); } catch(e) {}
        }
    }

    ready(function(){
        var root = document.querySelector('.bh-booking-widget'); var modal = document.querySelector('#bh-booking-modal');
        var successMessage = document.querySelector('.bh-booking-message.is-success'); if(successMessage) showBookingConfirmation('reserve_spot');
        watchNinjaSuccess();
        if(!root || !modal) return;
        var dateSelect = root.querySelector('[data-booking-date]') || root.querySelector('#bh-booking-date'); var continueLink = root.querySelector('[data-booking-continue]'); var lastFocused = null;
        if(!dateSelect || !continueLink) return;
        var bookUrl = root.getAttribute('data-book-url') || '/book/'; var groupId = root.getAttribute('data-group-id') || '';
        function updateContinue(){
            var date = dateSelect.value;
            if(!date || !groupId){ continueLink.href = '#'; continueLink.setAttribute('aria-disabled','true'); continueLink.classList.add('is-disabled'); return; }
            var url = new URL(bookUrl, window.location.origin); url.searchParams.set('group_id', groupId); url.searchParams.set('date', date); continueLink.href = url.toString(); continueLink.removeAttribute('aria-disabled'); continueLink.classList.remove('is-disabled');
        }
        function openModal(){ lastFocused = document.activeElement; modal.hidden = false; modal.setAttribute('aria-hidden','false'); document.body.classList.add('bh-booking-modal-open'); updateContinue(); var close = modal.querySelector('.bh-booking-modal-close'); if(close) close.focus(); }
        function closeModal(){ modal.hidden = true; modal.setAttribute('aria-hidden','true'); document.body.classList.remove('bh-booking-modal-open'); if(lastFocused && lastFocused.focus) lastFocused.focus(); }
        document.querySelectorAll('[data-booking-open]').forEach(function(trigger){ trigger.addEventListener('click', function(e){ e.preventDefault(); openModal(); }); });
        modal.querySelectorAll('[data-booking-close]').forEach(function(el){ el.addEventListener('click', closeModal); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !modal.hidden) closeModal(); });
        dateSelect.addEventListener('change', updateContinue);
        continueLink.addEventListener('click', function(e){ if(!dateSelect.value){ e.preventDefault(); dateSelect.focus(); } });
        updateContinue();
    });

    ready(function(){
        var form = document.querySelector('.nf-form-cont'); if(!form) return;
        initialiseBookingContext(form);
        function syncHiddenFields(){ if(!window.jQuery) return; window.jQuery(form).find('input[type="hidden"]').each(function(){ window.jQuery(this).trigger('change'); }); }
        setTimeout(function(){ rememberBookingContext(form); syncHiddenFields(); }, 100);
        setTimeout(function(){ rememberBookingContext(form); syncHiddenFields(); }, 500);
        document.addEventListener('click', function(event){ if(event.target.closest('[data-ticket-plus]') || event.target.closest('[data-ticket-minus]')){ window.setTimeout(syncHiddenFields, 100); window.setTimeout(syncHiddenFields, 300); } });
        document.addEventListener('input', function(event){ if(event.target.matches('[data-ticket-quantity]')) window.setTimeout(syncHiddenFields, 50); });
    });
})();