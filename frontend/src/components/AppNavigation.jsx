import React from 'react';
import { useTranslation } from 'react-i18next';
import Icon from './md3/Icon';
import useRipple from './md3/useRipple';

const BAR_DESTINATIONS = 4;

function NavItem({ item, active, compact, onSelect }) {
    const spawnRipple = useRipple();

    return (
        <button
            type="button"
            className="app-nav__item md-ripple-host"
            aria-label={item.label}
            aria-current={active ? 'page' : undefined}
            onPointerDown={spawnRipple}
            onClick={() => onSelect(item.id)}
        >
            <span className="app-nav__indicator">
                <Icon name={item.icon} size={24} />
            </span>
            <span className="app-nav__label">
                {compact ? item.shortLabel || item.label : item.label}
            </span>
        </button>
    );
}

function AppNavigation({ items, currentPage, onSelect, windowClass, appTitle }) {
    const { t } = useTranslation();
    const [moreOpen, setMoreOpen] = React.useState(false);

    const layout = windowClass === 'expanded' ? 'drawer' : windowClass === 'medium' ? 'rail' : 'bar';
    const overflows = layout === 'bar' && items.length > BAR_DESTINATIONS + 1;

    const visible = overflows ? items.slice(0, BAR_DESTINATIONS) : items;
    const hidden = overflows ? items.slice(BAR_DESTINATIONS) : [];
    const hiddenIsActive = hidden.some((item) => item.id === currentPage);

    React.useEffect(() => {
        setMoreOpen(false);
    }, [currentPage, layout]);

    const select = (id) => {
        setMoreOpen(false);
        onSelect(id);
    };

    return (
        <>
            {moreOpen && (
                <div className="app-nav__sheet-scrim" onClick={() => setMoreOpen(false)}>
                    <div className="app-nav__sheet" onClick={(e) => e.stopPropagation()}>
                        {hidden.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className="app-nav__sheet-item md-ripple-host"
                                aria-current={currentPage === item.id ? 'page' : undefined}
                                onClick={() => select(item.id)}
                            >
                                <Icon name={item.icon} size={24} />
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            <nav className={`app-nav app-nav--${layout}`} aria-label={appTitle}>
                {layout === 'rail' && (
                    <span className="app-nav__brand" aria-hidden="true">
                        <Icon name="checkCircle" size={24} />
                    </span>
                )}
                {layout === 'drawer' && (
                    <span className="app-nav__brand">
                        <Icon name="checkCircle" size={24} />
                        {appTitle}
                    </span>
                )}

                {visible.map((item) => (
                    <NavItem
                        key={item.id}
                        item={item}
                        active={currentPage === item.id}
                        compact={layout === 'bar'}
                        onSelect={select}
                    />
                ))}

                {overflows && (
                    <button
                        type="button"
                        className="app-nav__item app-nav__item--more md-ripple-host"
                        aria-haspopup="menu"
                        aria-expanded={moreOpen}
                        aria-current={hiddenIsActive ? 'page' : undefined}
                        onClick={() => setMoreOpen((open) => !open)}
                    >
                        <span className="app-nav__indicator">
                            <Icon name="more" size={24} />
                        </span>
                        <span className="app-nav__label">{t('common.more')}</span>
                    </button>
                )}
            </nav>
        </>
    );
}

export default AppNavigation;
