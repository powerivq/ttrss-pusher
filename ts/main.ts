import {urlB64ToUint8Array, uuidv4} from './utils';

declare global {
  interface Window {
    xhrPost: (url: string, params: object) => void;
    pusherPublicKey: string;
  }
}

const BROWSER_ID_KEY = 'browserId';

function updateSubscriptionOnServer(subscription: PushSubscription): void {
  const params = {
    'op': 'pusher',
    'method': 'update_subscription',
    'browserId': window.localStorage.getItem(BROWSER_ID_KEY),
    'subscription': JSON.stringify(subscription),
  };
  window.xhrPost('backend.php', params);
}

function subscribeUser(swRegistration: ServiceWorkerRegistration): void {
  const applicationServerKey = urlB64ToUint8Array(window.pusherPublicKey);
  swRegistration.pushManager
    .subscribe({
      userVisibleOnly: true,
      applicationServerKey: applicationServerKey,
    })
    .then(function (subscription) {
      updateSubscriptionOnServer(subscription);
    })
    .catch(function (err) {
      console.log('Failed to subscribe the user: ', err);
    });
}

function init(): void {
  if (
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'localStorage' in window
  ) {
    if (!window.localStorage.getItem(BROWSER_ID_KEY)) {
      window.localStorage.setItem(BROWSER_ID_KEY, uuidv4());
    }

    navigator.serviceWorker
      .register('./plugins.local/pusher/worker.js')
      .then(function (swReg) {
        swReg.pushManager.getSubscription().then(function (subscription) {
          if (subscription) {
            updateSubscriptionOnServer(subscription);
          } else {
            subscribeUser(swReg);
          }
        });
      })
      .catch(function (error) {
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
