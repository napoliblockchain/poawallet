// import IndexedDB
importScripts('src/js/idb.js');
importScripts('src/js/idb-utility.js');

// cambiare la versione a ogni modifica di file statici senza ?v= (es. web worker): svuota le cache vecchie
var CACHE_STATIC_NAME = 'poawallet-static-021';
var CACHE_DYNAMIC_NAME = 'poawallet-dynamic-021';

var OFFLINE_URL = 'offline.html';

var STATIC_FILES = [
	'/',
	OFFLINE_URL,
	'manifest.json',
	'favicon.ico',
	'brand/icon.svg',
	'brand/icon-192.png',
	'css/pincode.css',

	'js/global.script.js',
	'js/clipboard-copy.js',
	'js/wallet-sync.js',
	'js/web-workers/eth-tx.js',


	// manage notifications
	'js/notifications-MainWorker.js',
	'js/web-workers/notificationsWorker.js',

	'js/pincode/pincode-global.js',
	'js/pincode/pincode-settings.js',
	'js/pincode/pincode-utility.js',

	'src/js/promise.js',
	'src/js/fetch.js',
	'src/js/idb.js',
	'src/js/idb-utility.js',
	'src/js/service.js',
];



// Funzione Fix per apache
function cleanResponse(response) {
	const clonedResponse = response.clone();

	// Not all browsers support the Response.body stream, so fall back to reading
	// the entire body into memory as a blob.
	const bodyPromise = 'body' in clonedResponse ?
	  Promise.resolve(clonedResponse.body) :
	  clonedResponse.blob();

	return bodyPromise.then((body) => {
	  // new Response() is happy when passed either a stream or a Blob.
	  return new Response(body, {
			headers: clonedResponse.headers,
			status: clonedResponse.status,
			statusText: clonedResponse.statusText,
	  });
	});
}

function trimCache(cacheName, maxItems) {
	console.log('[Service Worker] trimming caches...');

	caches.open(cacheName)
		.then(function(cache) {
			return cache.keys()
				.then(function(keys) {
					if (keys.lenght > maxItems) {
						cache.delete(keys[0])
							.then(trimCache(cacheName, maxItems));
					}
				});
		});
}

self.addEventListener('message', function (event) {
	if (event.data.action === 'skipWaiting') {
    	self.skipWaiting();
		trimCache(CACHE_STATIC_NAME,0);
		trimCache(CACHE_DYNAMIC_NAME,0);
	}
});


self.addEventListener('install', function (event) {
	//console.log('[Service Worker] Installing Service worker...', event);
	console.log('[Service Worker] Installing Service worker...');
	// la nuova versione sostituisce subito la precedente (che può avere in cache file vecchi): activate svuota le cache
	self.skipWaiting();
	event.waitUntil(
		//versioning della cache. Per aggiornare le versioni del software
		caches.open(CACHE_STATIC_NAME)
			.then(function(cache){
				console.log('[Service Worker] Precaching app shell...');
				// dalla rete, mai dalla cache HTTP del browser (che può avere ancora i worker vecchi)
				return cache.addAll(STATIC_FILES.map(function (url) { return new Request(url, {cache: 'reload'}); }));
			})
	)
});
self.addEventListener('activate', function (event) {
	console.log('[Service Worker] Activating Service worker...');
	event.waitUntil(
		caches.keys()
			.then(function(keyList) {
				return Promise.all(keyList.map(function(key){
					if (key !== CACHE_STATIC_NAME && key !== CACHE_DYNAMIC_NAME){
						console.log('[Service Worker] deleting cache', key);
						return caches.delete(key);
					}
				}));
			})

	);
	return self.clients.claim();
});

// Pagine e richieste dinamiche sempre dalla rete (URL puliti: niente più criterio sull'estensione .php).
// In cache solo i file statici dello stesso sito: prima la cache, poi la rete; offline.html per le pagine senza rete.
var STATIC_EXTENSIONS = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'mp3', 'json', 'webmanifest'];

self.addEventListener('fetch', function (event) {
	var request = event.request;
	var url = new URL(request.url);
	if (request.method !== 'GET' || url.origin !== self.location.origin) return;

	var accept = request.headers.get('accept') || '';
	if (request.mode === 'navigate' || accept.includes('text/html')) {
		event.respondWith(fetch(request).catch(function () { return caches.match(OFFLINE_URL); }));
		return;
	}
	var extension = url.pathname.includes('.') ? url.pathname.split('.').pop().toLowerCase() : '';
	if (STATIC_EXTENSIONS.indexOf(extension) === -1) return;

	event.respondWith(
		caches.match(request).then(function (cached) {
			if (cached) return cached.redirected ? cleanResponse(cached) : cached;
			return fetch(request).then(function (response) {
				if (response.ok && response.type === 'basic') {
					var copy = response.clone();
					caches.open(CACHE_DYNAMIC_NAME).then(function (cache) { cache.put(request, copy); });
				}
				return response;
			});
		})
	);
});


//listener per la sincronizzazione in background
self.addEventListener('sync', function(event) {
	console.log('[Service Worker] Background syncing: '+event.tag, event);

	// SINCRONIZZAZIONE INVIO ERC20
	if (event.tag === 'sync-send-erc20') {
		console.log('[Service worker] Evento sincronizzazione invio token trovato!');
 		event.waitUntil(
 			readAllData(event.tag)
 			.then(function(data) {
 				for (var dt of data) {
					console.log('[Service worker] fetching sync-send-erc20',dt);
					var postData = new FormData();
	  					postData.append('id', dt.id);
						//postData.append('chainBlock', dt.chainBlock);

	 				fetch(dt.url, {
	 					method: 'POST',
	 					body: postData,
	 				})
	 				.then(function(response) {
	 					return response.json();
	 				})
	 				.then(function(json) {
						console.log('[Service worker] Risposta di send/validateTransaction',json);
						writeData('np-send-erc20', json);

				 	})
 					.catch(function(err){
 						console.log('[Service worker] Error while checking send-erc20 data', err);
 					})
 				}
 				//per sicurezza cancello tutto da indexedDB
 				clearAllData(event.tag);
 			 })
 		 );
 	}

});


//listener per le notifiche push
self.addEventListener('push', function(event) {
  console.log('[Service Worker] Push Received.');
  //console.log(`[Service Worker] Push had this data: "${event.data.text()}"`);

	const info = JSON.parse(`${event.data.text()}`);
	console.log(`[Service Worker] Push had this data: `+info);

	const sendNotification = body => {
    return self.registration.showNotification(info.title, {
			body: info.body,
			icon: info.icon,
			badge: info.badge,
			vibrate: info.vibrate,
			image: info.image,
			sound: info.sound,
			data: info.data,
			actions: info.actions,
			tag: info.tag,
			renotify: true,
			data: {
				 openUrl: info.openUrl,
			},
    });
	};

  if (event.data) {
    const message = event.data.text();
    event.waitUntil(sendNotification(message));
	}
});

self.addEventListener('notificationclick', function(event) {
	console.log('[SW event on notification click] EVENT', event);

	if (typeof event.notification.data !== 'undefined') {
		var action = event.notification.data;
	}else{
		var action = JSON.parse(event.actions);
	}

	// console.log('[SW event on notification click] ACTION', action);
	// console.log('[SW event on notification click] URL', action.openUrl);

	//
	event.notification.close();
	if (event.action !== 'close') {
    	console.log('handle go to url with yes button');
    	event.waitUntil(clients.openWindow(action.openUrl));
  	}


}
, false);
