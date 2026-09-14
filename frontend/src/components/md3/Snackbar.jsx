import React from 'react';

function Snackbar({ message, actionLabel, onAction, onDismiss, duration = 4000 }) {
    React.useEffect(() => {
        if (!message || !duration) {
            return undefined;
        }

        const timer = window.setTimeout(() => onDismiss?.(), duration);
        return () => window.clearTimeout(timer);
    }, [message, duration, onDismiss]);

    if (!message) {
        return null;
    }

    return (
        <div className="md-snackbar" role="status" aria-live="polite">
            <span className="md-snackbar__label md-body-medium">{message}</span>
            {actionLabel && (
                <button type="button" className="md-snackbar__action" onClick={onAction || onDismiss}>
                    {actionLabel}
                </button>
            )}
        </div>
    );
}

export default Snackbar;
