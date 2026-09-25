const CACHE = 'family-plan-shell-v2';
const CACHE_ENABLED = new URL(self.location.href).searchParams.get('cache') !== 'off';
const SHELL = ['/', '/manifest.json', '/favicon.svg', '/icon-192.png', '/icon-512.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    (CACHE_ENABLED ? caches.open(CACHE).then((cache) => cache.addAll(SHELL)) : Promise.resolve())
      .catch(() => undefined)
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith('family-plan-shell-') && (!CACHE_ENABLED || key !== CACHE)).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('push', (event) => {
  let payload = {};

  try {
    payload = event.data ? event.data.json() : {};
  } catch {
    payload = { body: event.data ? event.data.text() : '' };
  }

  const title = payload.title || 'Family Plan';
  const body = payload.body || '';

  if (!body) {
    return;
  }

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
      // Somebody is looking at the app right now: it shows the news as a bubble, a system notification would repeat it.
      const watching = windows.find((client) => client.visibilityState === 'visible' && client.focused);

      if (watching) {
        watching.postMessage({ type: 'notifications:arrived' });
        return undefined;
      }

      return self.registration.showNotification(title, {
        body,
        icon: '/icon-192.png',
        badge: '/icon-192.png',
        tag: payload.tag || undefined,
        // A newer word on the same topic replaces the old one and is announced again.
        renotify: Boolean(payload.tag),
        timestamp: Date.now(),
        data: { url: payload.url || '/', notificationId: payload.notificationId || null },
      });
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const { url = '/', notificationId = null } = event.notification.data || {};
  const target = new URL(url, self.location.origin);

  if (notificationId) {
    target.searchParams.set('notification', notificationId);
  }

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
      const open = windows.find((client) => new URL(client.url).origin === self.location.origin && 'focus' in client);

      if (open) {
        // The app marks it as read and moves to where it leads, without reloading.
        open.postMessage({ type: 'notifications:open', id: notificationId, url });
        return open.focus();
      }

      return self.clients.openWindow ? self.clients.openWindow(target.href) : undefined;
    })
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (!CACHE_ENABLED || request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin || url.pathname.startsWith('/api/')) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // An error page (e.g. a 502 while a new version starts) must not become the offline shell.
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE).then((cache) => cache.put('/', copy)).catch(() => undefined);
          }
          return response;
        })
        .catch(() => caches.match('/').then((cached) => cached || Response.error()))
    );
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) {
        return cached;
      }

      return fetch(request).then((response) => {
        if (response.ok && response.type === 'basic') {
          const copy = response.clone();
          caches.open(CACHE).then((cache) => cache.put(request, copy)).catch(() => undefined);
        }
        return response;
      });
    })
  );
});
