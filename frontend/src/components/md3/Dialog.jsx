import React from 'react';

function Dialog({ open, onClose, headline, children, actions }) {
    const surfaceRef = React.useRef(null);

    React.useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                onClose?.();
            }
        };

        document.addEventListener('keydown', onKeyDown);
        surfaceRef.current?.querySelector('input, select, textarea, button')?.focus();

        return () => document.removeEventListener('keydown', onKeyDown);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="md-scrim" onMouseDown={(event) => event.target === event.currentTarget && onClose?.()}>
            <div className="md-dialog" role="dialog" aria-modal="true" aria-label={headline} ref={surfaceRef}>
                {headline && <h2 className="md-dialog__headline md-headline-small">{headline}</h2>}
                <div className="md-dialog__content">{children}</div>
                {actions && <div className="md-dialog__actions">{actions}</div>}
            </div>
        </div>
    );
}

export default Dialog;
