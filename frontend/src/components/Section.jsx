import React from 'react';
import Icon from './md3/Icon';

const remember = (id, open) => {
    try {
        localStorage.setItem(`section:${id}`, open ? 'open' : 'closed');
    } catch {
        // storage unavailable
    }
};

const recall = (id, fallback) => {
    try {
        const held = localStorage.getItem(`section:${id}`);

        return held === null ? fallback : held === 'open';
    } catch {
        return fallback;
    }
};

function Section({ id, icon, title, summary, actions, defaultOpen = true, children }) {
    const [open, setOpen] = React.useState(() => recall(id, defaultOpen));

    const toggle = () => setOpen((current) => {
        remember(id, !current);

        return !current;
    });

    return (
        <section className={`panel${open ? ' is-open' : ''}`} data-testid={`panel-${id}`}>
            <div className="panel__head">
                <button
                    type="button"
                    className="panel__toggle"
                    aria-expanded={open}
                    aria-controls={`panel-body-${id}`}
                    onClick={toggle}
                >
                    <span className="panel__icon"><Icon name={icon} size={20} /></span>
                    <span className="panel__title">{title}</span>
                    {summary && <span className="panel__summary">{summary}</span>}
                    <span className="panel__chevron"><Icon name="expand" size={20} /></span>
                </button>

                {actions && <div className="panel__actions">{actions}</div>}
            </div>

            <div className="panel__body" id={`panel-body-${id}`} hidden={!open}>
                {children}
            </div>
        </section>
    );
}

export default Section;
