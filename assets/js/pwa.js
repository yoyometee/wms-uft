/**
 * PWA (Progressive Web App) Manager for Austam Good WMS
 * Handles installation, updates, offline functionality, and notifications
 */

class PWAManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.swRegistration = null;
        this.updateAvailable = false;
        
        this.init();
    }
    
    async init() {
        await this.registerServiceWorker();
        this.setupInstallPrompt();
        this.setupUpdateHandling();
        this.setupOfflineHandling();
        this.setupNotifications();
        this.setupBackgroundSync();
        this.createPWAUI();
        
        console.log('[PWA] PWA Manager initialized');
    }
    
    // Register Service Worker
    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                this.swRegistration = await navigator.serviceWorker.register('/wms-uft/sw.js');
                console.log('[PWA] Service Worker registered successfully');
                
                // Listen for updates
                this.swRegistration.addEventListener('updatefound', () => {
                    const newWorker = this.swRegistration.installing;
                    
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.updateAvailable = true;
                            this.showUpdateNotification();
                        }
                    });
                });
                
                // Listen for messages from service worker
                navigator.serviceWorker.addEventListener('message', (event) => {
                    this.handleServiceWorkerMessage(event);
                });
                
            } catch (error) {
                console.error('[PWA] Service Worker registration failed:', error);
            }
        }
    }
    
    // Setup install prompt
    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (event) => {
            console.log('[PWA] Install prompt available');
            event.preventDefault();
            this.deferredPrompt = event;
            this.showInstallButton();
        });
        
        window.addEventListener('appinstalled', () => {
            console.log('[PWA] App installed successfully');
            this.isInstalled = true;
            this.hideInstallButton();
            this.showInstalledNotification();
        });
        
        // Check if already installed
        if (window.matchMedia('(display-mode: standalone)').matches || 
            window.navigator.standalone) {
            this.isInstalled = true;
        }
    }
    
    // Setup update handling
    setupUpdateHandling() {
        // Check for updates every 10 minutes
        setInterval(() => {
            if (this.swRegistration) {
                this.swRegistration.update();
            }
        }, 10 * 60 * 1000);
    }
    
    // Setup offline handling
    setupOfflineHandling() {
        window.addEventListener('online', () => {
            this.showConnectionStatus('online');
            this.syncPendingData();
        });
        
        window.addEventListener('offline', () => {
            this.showConnectionStatus('offline');
        });
        
        // Initial status
        if (!navigator.onLine) {
            this.showConnectionStatus('offline');
        }
    }
    
    // Setup notifications
    async setupNotifications() {
        if ('Notification' in window && 'serviceWorker' in navigator) {
            const permission = await Notification.requestPermission();
            console.log('[PWA] Notification permission:', permission);
        }
    }
    
    // Setup background sync
    setupBackgroundSync() {
        // Register periodic sync for data updates
        if ('serviceWorker' in navigator && 'periodicSync' in window.ServiceWorkerRegistration.prototype) {
            navigator.serviceWorker.ready.then(registration => {
                return registration.periodicSync.register('data-sync', {
                    minInterval: 24 * 60 * 60 * 1000, // 24 hours
                });
            }).catch(error => {
                console.log('[PWA] Periodic sync not supported:', error);
            });
        }
    }
    
    // Create PWA UI elements
    createPWAUI() {
        this.createInstallButton();
        this.createUpdateButton();
        this.createStatusIndicator();
        this.createOfflineIndicator();
    }
    
    // Create install button
    createInstallButton() {
        const installBtn = document.createElement('button');
        installBtn.id = 'pwa-install-btn';
        installBtn.className = 'btn btn-primary btn-sm position-fixed';
        installBtn.style.cssText = `
            bottom: 80px;
            right: 20px;
            z-index: 1050;
            display: none;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        `;
        installBtn.innerHTML = '<i class="fas fa-download"></i> ติดตั้งแอป';
        installBtn.onclick = () => this.installApp();
        
        document.body.appendChild(installBtn);
    }
    
    // Create update button
    createUpdateButton() {
        const updateBtn = document.createElement('button');
        updateBtn.id = 'pwa-update-btn';
        updateBtn.className = 'btn btn-warning btn-sm position-fixed';
        updateBtn.style.cssText = `
            bottom: 130px;
            right: 20px;
            z-index: 1050;
            display: none;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        `;
        updateBtn.innerHTML = '<i class="fas fa-sync"></i> อัปเดต';
        updateBtn.onclick = () => this.updateApp();
        
        document.body.appendChild(updateBtn);
    }
    
    // Create connection status indicator
    createStatusIndicator() {
        const statusDiv = document.createElement('div');
        statusDiv.id = 'pwa-status-indicator';
        statusDiv.className = 'position-fixed';
        statusDiv.style.cssText = `
            top: 70px;
            right: 20px;
            z-index: 1040;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: none;
            transition: all 0.3s ease;
        `;
        
        document.body.appendChild(statusDiv);
    }
    
    // Create offline indicator
    createOfflineIndicator() {
        const offlineDiv = document.createElement('div');
        offlineDiv.id = 'pwa-offline-indicator';
        offlineDiv.className = 'alert alert-warning position-fixed';
        offlineDiv.style.cssText = `
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1060;
            display: none;
            min-width: 300px;
            text-align: center;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        `;
        offlineDiv.innerHTML = `
            <i class="fas fa-wifi"></i>
            <strong>โหมดออฟไลน์</strong><br>
            <small>ข้อมูลจะถูกซิงค์เมื่อเชื่อมต่อกลับ</small>
        `;
        
        document.body.appendChild(offlineDiv);
    }
    
    // Show install button
    showInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn && !this.isInstalled) {
            installBtn.style.display = 'block';
        }
    }
    
    // Hide install button
    hideInstallButton() {
        const installBtn = document.getElementById('pwa-install-btn');
        if (installBtn) {
            installBtn.style.display = 'none';
        }
    }
    
    // Install app
    async installApp() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            const { outcome } = await this.deferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                console.log('[PWA] User accepted install prompt');
            } else {
                console.log('[PWA] User dismissed install prompt');
            }
            
            this.deferredPrompt = null;
            this.hideInstallButton();
        }
    }
    
    // Show update notification
    showUpdateNotification() {
        const updateBtn = document.getElementById('pwa-update-btn');
        if (updateBtn) {
            updateBtn.style.display = 'block';
        }
        
        // Show toast notification
        this.showToast('มีอัปเดตใหม่พร้อมใช้งาน', 'info', {
            action: 'อัปเดต',
            actionCallback: () => this.updateApp()
        });
    }
    
    // Update app
    updateApp() {
        if (this.swRegistration && this.swRegistration.waiting) {
            this.swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
        }
        
        window.location.reload();
    }
    
    // Show connection status
    showConnectionStatus(status) {
        const statusIndicator = document.getElementById('pwa-status-indicator');
        const offlineIndicator = document.getElementById('pwa-offline-indicator');
        
        if (status === 'online') {
            statusIndicator.innerHTML = '<i class="fas fa-wifi"></i> ออนไลน์';
            statusIndicator.style.cssText += 'background: #d1f2eb; color: #0c5460; display: block;';
            offlineIndicator.style.display = 'none';
            
            setTimeout(() => {
                statusIndicator.style.display = 'none';
            }, 3000);
        } else {
            offlineIndicator.style.display = 'block';
            statusIndicator.style.display = 'none';
        }
    }
    
    // Show installed notification
    showInstalledNotification() {
        this.showToast('แอปติดตั้งสำเร็จ!', 'success');
    }
    
    // Show toast notification
    showToast(message, type = 'info', options = {}) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} position-fixed`;
        toast.style.cssText = `
            top: 20px;
            right: 20px;
            z-index: 1070;
            min-width: 300px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease;
        `;
        
        toast.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    ${message}
                </div>
                ${options.action ? `<button class="btn btn-sm btn-outline-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} ms-2">${options.action}</button>` : ''}
                <button type="button" class="btn-close ms-2" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        
        if (options.action && options.actionCallback) {
            toast.querySelector('button:not(.btn-close)').onclick = options.actionCallback;
        }
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
    
    // Handle service worker messages
    handleServiceWorkerMessage(event) {
        const { data } = event;
        
        switch (data.type) {
            case 'UPDATE_AVAILABLE':
                this.showUpdateNotification();
                break;
            case 'CACHE_UPDATED':
                this.showToast('ข้อมูลได้รับการอัปเดตแล้ว', 'success');
                break;
            case 'SYNC_COMPLETE':
                this.showToast('ข้อมูลซิงค์เสร็จสิ้น', 'success');
                break;
        }
    }
    
    // Sync pending data
    async syncPendingData() {
        if ('serviceWorker' in navigator && this.swRegistration) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('background-sync');
                console.log('[PWA] Background sync registered');
            } catch (error) {
                console.error('[PWA] Background sync failed:', error);
            }
        }
    }
    
    // Store data for offline use
    async storeOfflineData(key, data) {
        try {
            const db = await this.openIndexedDB();
            const transaction = db.transaction(['offline_data'], 'readwrite');
            const store = transaction.objectStore('offline_data');
            
            await store.put({
                key: key,
                data: data,
                timestamp: Date.now(),
                type: 'cached_data'
            });
            
            console.log('[PWA] Data stored for offline use:', key);
        } catch (error) {
            console.error('[PWA] Failed to store offline data:', error);
        }
    }
    
    // Get offline data
    async getOfflineData(key) {
        try {
            const db = await this.openIndexedDB();
            const transaction = db.transaction(['offline_data'], 'readonly');
            const store = transaction.objectStore('offline_data');
            
            const result = await store.get(key);
            return result ? result.data : null;
        } catch (error) {
            console.error('[PWA] Failed to get offline data:', error);
            return null;
        }
    }
    
    // Open IndexedDB
    openIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('AustamWMS', 1);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
            
            request.onupgradeneeded = event => {
                const db = event.target.result;
                
                if (!db.objectStoreNames.contains('offline_data')) {
                    const store = db.createObjectStore('offline_data', { keyPath: 'key' });
                    store.createIndex('type', 'type');
                    store.createIndex('timestamp', 'timestamp');
                }
                
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const store = db.createObjectStore('sync_queue', { 
                        keyPath: 'id', 
                        autoIncrement: true 
                    });
                    store.createIndex('timestamp', 'timestamp');
                }
            };
        });
    }
    
    // Check if app is installed
    isAppInstalled() {
        return this.isInstalled || 
               window.matchMedia('(display-mode: standalone)').matches ||
               window.navigator.standalone;
    }
    
    // Get app info
    getAppInfo() {
        return {
            isInstalled: this.isAppInstalled(),
            isOnline: navigator.onLine,
            hasServiceWorker: 'serviceWorker' in navigator,
            hasNotifications: 'Notification' in window,
            updateAvailable: this.updateAvailable
        };
    }
    
    // Enable push notifications
    async enablePushNotifications() {
        try {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                throw new Error('Push messaging is not supported');
            }
            
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.getVapidPublicKey())
            });
            
            // Send subscription to server
            await fetch('/wms-uft/api/push-subscribe.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subscription)
            });
            
            console.log('[PWA] Push notifications enabled');
            this.showToast('เปิดใช้งานการแจ้งเตือนแล้ว', 'success');
            
        } catch (error) {
            console.error('[PWA] Failed to enable push notifications:', error);
            this.showToast('ไม่สามารถเปิดใช้งานการแจ้งเตือนได้', 'error');
        }
    }
    
    // VAPID public key (replace with your actual key)
    getVapidPublicKey() {
        return 'BEl62iUYgUivxIkv69yViEuiBIa40HI9stpjgM2JYODNd_4LWGNXOl8hKg8B8LUyU1QgXK6L4YZz4j9H9w3Cp8';
    }
    
    // Convert VAPID key
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
    
    // Add CSS animations
    addPWAStyles() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .pwa-button {
                transition: all 0.3s ease;
            }
            
            .pwa-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.3) !important;
            }
            
            #pwa-install-btn,
            #pwa-update-btn {
                backdrop-filter: blur(10px);
                border: none;
            }
            
            #pwa-offline-indicator {
                backdrop-filter: blur(10px);
            }
        `;
        document.head.appendChild(style);
    }
}

// Initialize PWA Manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
    window.pwaManager.addPWAStyles();
});

// Export for use in other scripts
window.PWAManager = PWAManager;