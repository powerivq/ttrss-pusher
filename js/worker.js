function uuidv4() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
    const r = Math.random() * 16 | 0; const v = c == 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}

self.displayQueue = [];
function showMessage(data) {
  const jobId = uuidv4();
  self.displayQueue.push(jobId);
  return Promise.resolve().then(() => new Promise((resolve) => {
    let timerId;
    const checkPosition = () => {
      if (self.displayQueue[0] != jobId) return false;
      if (timerId !== undefined) clearInterval(timerId);
      data.jobId = jobId;
      const title = data.title;
      const options = {
        data: data,
        body: data.excerpt,
        image: data.image,
        silent: true,
        requireInteraction: true,
      };
      self.registration.showNotification(title, options);
      resolve(new Date().getTime());
      return true;
    };

    if (checkPosition()) return;
    timerId = setInterval(checkPosition, 1000);
  })).then((startTime) => new Promise((resolve) => {
    const timerId = setInterval(() => {
      if (new Date().getTime() - startTime > 20000) {
        self.registration.getNotifications().then((notifications) => {
          for (let i = 0; i < notifications.length; i++) {
            if (notifications[i].data && notifications[i].data.jobId == data.jobId) {
              notifications[i].close();
              closeMessage(notifications[i]);
            }
          }
        });
      }
      if (self.displayQueue.length && self.displayQueue[0] == jobId) return;
      clearInterval(timerId);
      resolve();
    }, 500);
  }));
}

function closeMessage(notification) {
  if (!notification.data) {
    return;
  }
  if (notification.data.jobId != self.displayQueue[0]) {
    console.error('Unmatched message and head of queue');
  } else {
    self.displayQueue = self.displayQueue.slice(1);
  }
}

self.addEventListener('push', function(event) {
  const data = JSON.parse(event.data.text());
  console.log('Push received');
  console.log(data);
  event.waitUntil(showMessage(data));
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  closeMessage(event.notification);

  const data = event.notification.data;
  const headers = new Headers({
    'content-type': 'application/x-www-form-urlencoded; charset=UTF-8',
  });

  event.waitUntil(
      Promise.all([
        clients.openWindow(data.link),
        fetch('backend.php', {
          method: 'POST',
          credentials: 'include',
          headers: headers,
          body: 'op=pusher&method=mark_read&guid=' + encodeURIComponent(data.guid),
        }),
      ])
  );
});

self.addEventListener('notificationclose', function(event) {
  closeMessage(event.notification);
});
