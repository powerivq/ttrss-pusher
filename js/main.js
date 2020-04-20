const BROWSER_ID_KEY = 'browserId';

function urlB64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding)
      .replace(/\-/g, '+')
      .replace(/_/g, '/');

  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; i++) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

function uuidv4() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0; const v = c == 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

function updateSubscriptionOnServer(subscription) {
  const params = {
    'op': 'pusher',
    'method': 'update_subscription',
    'browserId': window.localStorage.getItem(BROWSER_ID_KEY),
    'subscription': JSON.stringify(subscription),
  };
  xhrPost('backend.php', params);
}

function subscribeUser(swRegistration) {
  const applicationServerKey = urlB64ToUint8Array(pusherPublicKey);
  swRegistration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: applicationServerKey,
  })
      .then(function(subscription) {
        updateSubscriptionOnServer(subscription);
      })
      .catch(function(err) {
        console.log('Failed to subscribe the user: ', err);
      });
}

function init() {
  if (
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'localStorage' in window
  ) {
    if (!window.localStorage.getItem(BROWSER_ID_KEY)) {
      window.localStorage.setItem(BROWSER_ID_KEY, uuidv4());
    }

    navigator.serviceWorker.register('./plugins.local/pusher/worker.js')
        .then(function(swReg) {
          swReg.pushManager.getSubscription()
              .then(function(subscription) {
                if (subscription) {
                  updateSubscriptionOnServer(subscription);
                } else {
                  subscribeUser(swReg);
                }
              });
        })
        .catch(function(error) {
          console.error('Service Worker Error', error);
        });
  } else {
    console.warn('Push messaging is not supported');
  }

  if (Notification.permission === 'denied') {
    updateSubscriptionOnServer(null);
  }
}

init();
