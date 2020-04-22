import {uuidv4} from './utils';

declare const self: ServiceWorkerGlobalScope;

/* eslint-disable @typescript-eslint/no-explicit-any */
interface ServiceWorkerGlobalScope {
  displayQueue: Array<string>;
  registration: any;
  addEventListener: any;
  clients: any;
  setInterval: any;
}
/* eslint-enable */

interface Data {
  jobId: string;
  title: string;
  excerpt: string;
  image: string;
}

function supportsImageProp(): boolean {
  return !/Mac OS X/.test(navigator.userAgent);
}

function closeMessage(notification: Notification): void {
  if (!notification.data) {
    return;
  }
  if (notification.data.jobId != self.displayQueue[0]) {
    console.error('Unmatched message and head of queue');
  } else {
    self.displayQueue = self.displayQueue.slice(1);
  }
}

self.displayQueue = [];
function showMessage(data: Data): Promise<void> {
  const jobId = uuidv4();
  self.displayQueue.push(jobId);
  return Promise.resolve()
    .then(
      () =>
        new Promise<number>((resolve) => {
          let timerId: number = undefined;
          const checkPosition = (): boolean => {
            if (self.displayQueue[0] != jobId) return false;
            if (timerId !== undefined) clearInterval(timerId);
            data.jobId = jobId;
            const title = data.title;
            const options = {
              data: data,
              body: data.excerpt,
              icon: supportsImageProp() ? undefined : data.image,
              image: data.image,
              silent: true,
              requireInteraction: true,
            };
            self.registration.showNotification(title, options);
            resolve(new Date().getTime());
            return true;
          };

          if (checkPosition()) return;
          timerId = self.setInterval(checkPosition, 1000);
        })
    )
    .then(
      (startTime) =>
        new Promise((resolve) => {
          const timerId = setInterval(() => {
            if (new Date().getTime() - startTime > 20000) {
              self.registration.getNotifications().then((notifications) => {
                for (let i = 0; i < notifications.length; i++) {
                  if (
                    notifications[i].data &&
                    notifications[i].data.jobId == data.jobId
                  ) {
                    notifications[i].close();
                    closeMessage(notifications[i]);
                  }
                }
              });
            }
            if (self.displayQueue.length && self.displayQueue[0] == jobId)
              return;
            clearInterval(timerId);
            resolve();
          }, 500);
        })
    );
}

self.addEventListener('push', function (event) {
  const data = JSON.parse(event.data.text());
  console.log('Push received');
  console.log(data);
  event.waitUntil(showMessage(data));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  closeMessage(event.notification);

  const data = event.notification.data;
  const headers = new Headers({
    'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
  });

  event.waitUntil(
    Promise.all([
      self.clients.openWindow(data.link),
      fetch('../../backend.php', {
        method: 'POST',
        credentials: 'include',
        headers: headers,
        body:
          'op=pusher&method=mark_read&guid=' + encodeURIComponent(data.guid),
      }),
    ])
  );
});

self.addEventListener('notificationclose', function (event) {
  closeMessage(event.notification);
});
