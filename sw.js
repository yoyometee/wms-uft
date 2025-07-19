// Service Worker for Austam Good WMS
// Version 1.0.0

const CACHE_NAME = 'austam-wms-v1.0.0';
const OFFLINE_URL = '/wms-uft/offline.html';

// Files to cache for offline functionality
const CACHE_URLS = [
    '/wms-uft/',
    '/wms-uft/index.php',
    '/wms-uft/offline.html',
    
    // Core CSS
    '/wms-uft/assets/css/custom.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    
    // Core JavaScript
    '/wms-uft/assets/js/main.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
    'https://code.jquery.com/jquery-3.7.0.min.js',
    
    // Key modules
    '/wms-uft/modules/receive/index.php',
    '/wms-uft/modules/picking/index.php',
    '/wms-uft/modules/movement/index.php',
    '/wms-uft/modules/inventory/index.php',
    '/wms-uft/modules/reports/index.php',
    '/wms-uft/modules/dashboard/executive.php',
    
    // API endpoints for offline sync
    '/wms-uft/api/refresh-session.php',
    
    // Icons
    '/wms-uft/assets/images/icons/icon-192x192.png',
    '/wms-uft/assets/images/icons/icon-512x512.png'
];

// Install event - cache resources
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker v' + CACHE_NAME);
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[SW] Caching app shell');
                return cache.addAll(CACHE_URLS.map(url => new Request(url, {
                    credentials: 'same-origin'
                })));
            })
            .catch(error => {
                console.error('[SW] Failed to cache:', error);
            })
    );
    
    // Force the waiting service worker to become the active service worker
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating Service Worker v' + CACHE_NAME);
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            // Take control of all pages immediately
            return self.clients.claim();
        })
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external domains (except CDNs we cache)
    if (url.origin !== location.origin && 
        !url.hostname.includes('cdn.jsdelivr.net') && 
        !url.hostname.includes('cdnjs.cloudflare.com') &&
        !url.hostname.includes('code.jquery.com')) {
        return;
    }
    
    // Handle different request types
    if (request.destination === 'document') {
        // HTML pages - Network first, cache fallback
        event.respondWith(handleDocumentRequest(request));
    } else if (request.destination === 'script' || 
               request.destination === 'style' ||
               request.destination === 'font') {
        // Static assets - Cache first, network fallback
        event.respondWith(handleAssetRequest(request));
    } else if (url.pathname.includes('/api/')) {
        // API requests - Network first with offline storage
        event.respondWith(handleApiRequest(request));
    } else {
        // Other requests - Cache first, network fallback
        event.respondWith(handleAssetRequest(request));
    }
});

// Handle document requests (HTML pages)
async function handleDocumentRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        
        throw new Error('Network response not ok');
    } catch (error) {
        console.log('[SW] Network failed for document, trying cache:', request.url);
        
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page
        return caches.match(OFFLINE_URL);
    }
}

// Handle static asset requests
async function handleAssetRequest(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Try network
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }
        
        throw new Error('Network response not ok');
    } catch (error) {
        console.log('[SW] Failed to fetch asset:', request.url);
        
        // Return a basic error response for missing assets
        if (request.destination === 'image') {
            return new Response('', { status: 404 });
        }
        
        return new Response('Offline', { 
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Handle API requests with background sync
async function handleApiRequest(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Store successful responses for offline access
            if (request.method === 'GET') {
                const cache = await caches.open(CACHE_NAME + '-api');
                cache.put(request, networkResponse.clone());
            }
            
            return networkResponse;
        }
        
        throw new Error('API request failed');
    } catch (error) {
        console.log('[SW] API request failed, checking for cached data:', request.url);
        
        // For GET requests, try to return cached data
        if (request.method === 'GET') {
            const cache = await caches.open(CACHE_NAME + '-api');
            const cachedResponse = await cache.match(request);
            
            if (cachedResponse) {
                // Add offline indicator to response
                const response = cachedResponse.clone();
                response.headers.set('X-Served-By', 'sw-cache');
                return response;
            }
        }
        
        // For POST requests or when no cache available, store for sync
        if (request.method === 'POST') {
            await storeRequestForSync(request);
        }
        
        return new Response(JSON.stringify({
            success: false,
            error: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ ข้อมูลจะถูกซิงค์เมื่อเชื่อมต่อกลับ',
            offline: true
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Store failed requests for background sync
async function storeRequestForSync(request) {
    try {
        const requestData = {
            url: request.url,
            method: request.method,
            headers: Object.fromEntries(request.headers.entries()),
            body: await request.text(),
            timestamp: Date.now()
        };
        
        // Store in IndexedDB for background sync
        const db = await openDB();
        const transaction = db.transaction(['sync_queue'], 'readwrite');
        const store = transaction.objectStore('sync_queue');
        await store.add(requestData);
        
        console.log('[SW] Stored request for sync:', request.url);
        
        // Register background sync
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register('background-sync');
        }
    } catch (error) {
        console.error('[SW] Failed to store request for sync:', error);
    }
}

// Background sync event
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        console.log('[SW] Background sync triggered');
        event.waitUntil(processSyncQueue());
    }
});

// Process sync queue
async function processSyncQueue() {
    try {
        const db = await openDB();
        const transaction = db.transaction(['sync_queue'], 'readonly');
        const store = transaction.objectStore('sync_queue');
        const requests = await store.getAll();
        
        for (const requestData of requests) {
            try {
                const response = await fetch(requestData.url, {
                    method: requestData.method,
                    headers: requestData.headers,
                    body: requestData.body
                });
                
                if (response.ok) {
                    // Remove from sync queue
                    const deleteTransaction = db.transaction(['sync_queue'], 'readwrite');
                    const deleteStore = deleteTransaction.objectStore('sync_queue');
                    await deleteStore.delete(requestData.id);
                    
                    console.log('[SW] Successfully synced request:', requestData.url);
                }
            } catch (error) {
                console.error('[SW] Failed to sync request:', requestData.url, error);
            }
        }
    } catch (error) {
        console.error('[SW] Error processing sync queue:', error);
    }
}

// Open IndexedDB for offline storage
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('AustamWMS', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = event => {
            const db = event.target.result;
            
            // Create sync queue store
            if (!db.objectStoreNames.contains('sync_queue')) {
                const store = db.createObjectStore('sync_queue', { 
                    keyPath: 'id', 
                    autoIncrement: true 
                });
                store.createIndex('timestamp', 'timestamp');
            }
            
            // Create offline data store
            if (!db.objectStoreNames.contains('offline_data')) {
                const store = db.createObjectStore('offline_data', { 
                    keyPath: 'key' 
                });
                store.createIndex('type', 'type');
                store.createIndex('timestamp', 'timestamp');
            }
        };
    });
}

// Push notification event
self.addEventListener('push', event => {
    console.log('[SW] Push received');
    
    const options = {
        body: event.data ? event.data.text() : 'มีการอัปเดตข้อมูลใหม่',
        icon: '/wms-uft/assets/images/icons/icon-192x192.png',
        badge: '/wms-uft/assets/images/icons/icon-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            url: '/wms-uft/',
            timestamp: Date.now()
        },
        actions: [
            {
                action: 'open',
                title: 'เปิดระบบ',
                icon: '/wms-uft/assets/images/icons/icon-72x72.png'
            },
            {
                action: 'close',
                title: 'ปิด'
            }
        ],
        requireInteraction: true,
        tag: 'austam-wms-notification'
    };
    
    event.waitUntil(
        self.registration.showNotification('Austam WMS', options)
    );
});

// Notification click event
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification clicked');
    
    event.notification.close();
    
    if (event.action === 'open' || !event.action) {
        event.waitUntil(
            clients.openWindow(event.notification.data.url || '/wms-uft/')
        );
    }
});

// Message event for communication with main thread
self.addEventListener('message', event => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    } else if (event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.delete(CACHE_NAME).then(() => {
                event.ports[0].postMessage({ success: true });
            })
        );
    }
});

// Periodic background sync for data updates
self.addEventListener('periodicsync', event => {
    if (event.tag === 'data-sync') {
        console.log('[SW] Periodic sync triggered');
        event.waitUntil(syncCriticalData());
    }
});

// Sync critical data in background
async function syncCriticalData() {
    try {
        // Sync session status
        const sessionResponse = await fetch('/wms-uft/api/refresh-session.php');
        if (sessionResponse.ok) {
            const sessionData = await sessionResponse.json();
            console.log('[SW] Session synced:', sessionData);
        }
        
        // Add more critical data sync as needed
        
    } catch (error) {
        console.error('[SW] Failed to sync critical data:', error);
    }
}

console.log('[SW] Service Worker script loaded v' + CACHE_NAME);