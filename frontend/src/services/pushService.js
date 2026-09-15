import apiClient from './apiClient';

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

function arrayBufferToBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';

    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function deviceLabel() {
    const { userAgent } = window.navigator;

    if (/iPhone|iPad|iPod/i.test(userAgent)) return 'iOS';
    if (/Android/i.test(userAgent)) return 'Android';
    if (/Macintosh/i.test(userAgent)) return 'Mac';
    if (/Windows/i.test(userAgent)) return 'Windows';

    return null;
}

const pushService = {
    isSupported() {
        return 'serviceWorker' in navigator
            && 'PushManager' in window
            && 'Notification' in window;
    },

    needsHomeScreenInstall() {
        const isApple = /iPhone|iPad|iPod/i.test(window.navigator.userAgent);
        const standalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        return isApple && !standalone;
    },

    permission() {
        return this.isSupported() ? Notification.permission : 'unsupported';
    },

    async currentSubscription() {
        if (!this.isSupported()) {
            return null;
        }

        const registration = await navigator.serviceWorker.ready;

        return registration.pushManager.getSubscription();
    },

    async isEnabled() {
        return (await this.currentSubscription()) !== null;
    },

    async enable() {
        if (!this.isSupported()) {
            throw new Error('unsupported');
        }

        const { publicKey } = await apiClient.get('/api/push/key');

        if (!publicKey) {
            throw new Error('not_configured');
        }

        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            throw new Error('denied');
        }

        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });

        await apiClient.post('/api/push/subscriptions', {
            endpoint: subscription.endpoint,
            publicKey: arrayBufferToBase64Url(subscription.getKey('p256dh')),
            authToken: arrayBufferToBase64Url(subscription.getKey('auth')),
            deviceLabel: deviceLabel(),
        });

        return subscription;
    },

    async disable() {
        const subscription = await this.currentSubscription();

        if (!subscription) {
            return;
        }

        await apiClient.delete(`/api/push/subscriptions?endpoint=${encodeURIComponent(subscription.endpoint)}`);
        await subscription.unsubscribe();
    },

    async sendTest() {
        return apiClient.post('/api/push/test', {});
    },
};

export default pushService;
