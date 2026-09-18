export default function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        const script = process.env.NODE_ENV === 'production' ? '/sw.js' : '/sw.js?cache=off';
        navigator.serviceWorker.register(script).catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}
