import React from 'react';
import { Icon } from './md3';

/** Share of its width a bubble has to travel before letting go dismisses it. */
const SWIPE_SHARE = 0.35;
/** A quick flick dismisses even when it travelled less. */
const FLICK_SPEED = 0.5;
const FLICK_DISTANCE = 32;
/** Movement before a drag counts as a horizontal swipe rather than a tap or a scroll. */
const DECIDE_AFTER = 8;
const LEAVE_MS = 180;

const prefersReducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/**
 * One notification bubble. It closes with the X, Escape, a swipe to either side with a finger or the mouse,
 * or by itself after `autoHideMs`. The timer waits while the pointer rests on it, it has focus or the tab is hidden.
 */
function NotificationBubble({ title, message, meta, icon = 'notifications', onOpen, onDismiss, autoHideMs = 0, closeLabel, children }) {
    const surface = React.useRef(null);
    const drag = React.useRef(null);
    const swiped = React.useRef(false);
    const [offset, setOffset] = React.useState(0);
    const [dragging, setDragging] = React.useState(false);
    const [leaving, setLeaving] = React.useState(null);
    const [held, setHeld] = React.useState(false);

    const leave = React.useCallback((direction) => {
        if (leaving !== null) {
            return;
        }

        if (prefersReducedMotion()) {
            onDismiss();
            return;
        }

        setLeaving(direction);
        window.setTimeout(onDismiss, LEAVE_MS);
    }, [leaving, onDismiss]);

    React.useEffect(() => {
        if (!autoHideMs || held || dragging || leaving !== null) {
            return undefined;
        }

        let remaining = autoHideMs;
        let startedAt = 0;
        let timer = null;
        const start = () => {
            startedAt = Date.now();
            timer = window.setTimeout(() => leave(0), remaining);
        };
        const onVisibility = () => {
            if (document.hidden) {
                window.clearTimeout(timer);
                remaining = Math.max(0, remaining - (Date.now() - startedAt));
            } else {
                start();
            }
        };

        if (!document.hidden) {
            start();
        }
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [autoHideMs, held, dragging, leaving, leave]);

    const onPointerDown = (event) => {
        if ((event.pointerType === 'mouse' && event.button !== 0) || event.target.closest('.notification-bubble__close, .notification-bubble__actions')) {
            return;
        }

        drag.current = { id: event.pointerId, x: event.clientX, y: event.clientY, at: event.timeStamp, swiping: false };
    };

    const onPointerMove = (event) => {
        const current = drag.current;
        if (!current || current.id !== event.pointerId) {
            return;
        }

        const dx = event.clientX - current.x;
        const dy = event.clientY - current.y;

        if (!current.swiping) {
            if (Math.abs(dy) > DECIDE_AFTER && Math.abs(dy) > Math.abs(dx)) {
                drag.current = null;
                return;
            }
            if (Math.abs(dx) < DECIDE_AFTER) {
                return;
            }
            current.swiping = true;
            setDragging(true);
            surface.current?.setPointerCapture?.(event.pointerId);
        }

        setOffset(dx);
    };

    const onPointerEnd = (event) => {
        const current = drag.current;
        drag.current = null;

        if (!current?.swiping) {
            return;
        }

        swiped.current = true;
        setDragging(false);
        const dx = event.clientX - current.x;
        const width = surface.current?.offsetWidth || 1;
        const speed = Math.abs(dx) / Math.max(1, event.timeStamp - current.at);

        if (event.type !== 'pointercancel' && (Math.abs(dx) > width * SWIPE_SHARE || (speed > FLICK_SPEED && Math.abs(dx) > FLICK_DISTANCE))) {
            leave(Math.sign(dx) || 1);
        } else {
            setOffset(0);
        }
    };

    const onOpenClick = () => {
        // A swipe ends with a click on the same element; it is not a request to open.
        if (swiped.current) {
            swiped.current = false;
            return;
        }
        onOpen?.();
    };

    const width = surface.current?.offsetWidth || 400;
    const shift = leaving !== null ? (leaving === 0 ? 0 : leaving * width * 1.2) : offset;
    const opacity = leaving !== null ? 0 : Math.max(0.2, 1 - Math.abs(offset) / width);

    return (
        <div
            ref={surface}
            className={`notification-bubble${dragging ? ' is-dragging' : ''}${leaving !== null ? ' is-leaving' : ''}`}
            style={{ transform: `translateX(${shift}px)`, opacity }}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerEnd}
            onPointerCancel={onPointerEnd}
            onMouseEnter={() => setHeld(true)}
            onMouseLeave={() => setHeld(false)}
            onFocus={() => setHeld(true)}
            onBlur={(event) => !event.currentTarget.contains(event.relatedTarget) && setHeld(false)}
            onKeyDown={(event) => event.key === 'Escape' && leave(0)}
        >
            <span className="notification-bubble__icon" aria-hidden="true"><Icon name={icon} size={20} /></span>
            <button type="button" className="notification-bubble__body" onClick={onOpenClick}>
                {title && <span className="notification-bubble__title">{title}</span>}
                {message && <span className="notification-bubble__message">{message}</span>}
                {meta && <span className="notification-bubble__meta">{meta}</span>}
            </button>
            {children && <div className="notification-bubble__actions">{children}</div>}
            <button type="button" className="notification-bubble__close" onClick={() => leave(0)} aria-label={closeLabel} title={closeLabel}>
                <Icon name="close" size={20} />
            </button>
        </div>
    );
}

export default NotificationBubble;
