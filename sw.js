self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map((key) => caches.delete(key)));
    await self.registration.unregister();
    const clientsList = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    clientsList.forEach((client) => client.navigate(client.url));
  })());
});
