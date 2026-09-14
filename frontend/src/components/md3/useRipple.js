import React from 'react';

const RIPPLE_LIFETIME = 700;

export default function useRipple() {
    const ref = React.useRef(null);

    return React.useCallback((event) => {
        const host = event.currentTarget;
        if (!host || host.disabled) {
            return;
        }

        const rect = host.getBoundingClientRect();
        const x = (event.clientX ?? rect.left + rect.width / 2) - rect.left;
        const y = (event.clientY ?? rect.top + rect.height / 2) - rect.top;
        const radius = Math.hypot(
            Math.max(x, rect.width - x),
            Math.max(y, rect.height - y)
        );

        const ripple = document.createElement('span');
        ripple.className = 'md-ripple';
        ripple.style.left = `${x - radius}px`;
        ripple.style.top = `${y - radius}px`;
        ripple.style.width = `${radius * 2}px`;
        ripple.style.height = `${radius * 2}px`;

        host.appendChild(ripple);
        ref.current = ripple;

        const release = () => {
            ripple.classList.add('is-releasing');
            window.setTimeout(() => ripple.remove(), RIPPLE_LIFETIME);
            host.removeEventListener('pointerup', release);
            host.removeEventListener('pointerleave', release);
            host.removeEventListener('pointercancel', release);
        };

        host.addEventListener('pointerup', release);
        host.addEventListener('pointerleave', release);
        host.addEventListener('pointercancel', release);
    }, []);
}
