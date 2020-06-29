declare const self: ServiceWorkerGlobalScope;

/* eslint-disable @typescript-eslint/no-explicit-any */
interface ServiceWorkerGlobalScope {
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

function showMessage(data: Data): void {
  const options = {
    data: data,
    body: data.excerpt,
    icon: supportsImageProp() ? undefined : data.image,
    image: data.image,
    silent: true,
  };
  self.registration.showNotification(data.title, options);
}

self.addEventListener('push', function (event) {
  const data = JSON.parse(event.data.text());
  console.log('Push received');
  console.log(data);
  event.waitUntil(showMessage(data));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

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

export {};
