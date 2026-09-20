(function () {
  'use strict';
  if (!window.BubbaHubPush || !BubbaHubPush.enabled || !BubbaHubPush.firebase) return;

  var state = { token: null, messaging: null, registration: null };

  function request(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': BubbaHubPush.nonce },
      body: JSON.stringify(body || {})
    }).then(function (r) {
      return r.json().then(function (data) { if (!r.ok) throw new Error(data.message || 'Request failed'); return data; });
    });
  }

  function registerPush() {
    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
      return Promise.reject(new Error('Push notifications are not supported on this device or browser.'));
    }
    return Notification.requestPermission().then(function (permission) {
      if (permission !== 'granted') throw new Error('Notification permission was not granted.');
      return navigator.serviceWorker.register(BubbaHubPush.swUrl, { scope: BubbaHubPush.swUrl.replace(/\/wp-json\/bubbahub\/v1\/push-sw\.js$/, '/wp-json/bubbahub/v1/') });
    }).then(function (registration) {
      state.registration = registration;
      firebase.initializeApp(BubbaHubPush.firebase);
      state.messaging = firebase.messaging();
      return state.messaging.getToken({ vapidKey: BubbaHubPush.vapidKey, serviceWorkerRegistration: registration });
    }).then(function (token) {
      if (!token) throw new Error('Firebase did not return a push registration.');
      state.token = token;
      return request(BubbaHubPush.registerUrl, { token: token });
    });
  }

  function disablePush() {
    if (!state.token) return Promise.resolve();
    return request(BubbaHubPush.unregisterUrl, { token: state.token }).then(function () {
      state.token = null;
    });
  }

  function initControls() {
    document.querySelectorAll('[data-bubbahub-push-enable]').forEach(function (button) {
      button.addEventListener('click', function () {
        button.disabled = true;
        registerPush().then(function () {
          button.textContent = '✓ Push notifications enabled';
          button.classList.add('is-enabled');
          document.dispatchEvent(new CustomEvent('bubbahub:push-enabled'));
        }).catch(function (error) {
          button.disabled = false;
          button.textContent = 'Enable push notifications';
          window.alert(error.message || 'Unable to enable push notifications.');
        });
      });
    });
    document.querySelectorAll('[data-bubbahub-push-disable]').forEach(function (button) {
      button.addEventListener('click', function () {
        button.disabled = true;
        disablePush().then(function () {
          button.disabled = false;
          button.textContent = 'Push notifications disabled';
          document.dispatchEvent(new CustomEvent('bubbahub:push-disabled'));
        }).catch(function () {
          button.disabled = false;
        });
      });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initControls);
  else initControls();
  window.BubbaHubPushApi = { enable: registerPush, disable: disablePush };
})();