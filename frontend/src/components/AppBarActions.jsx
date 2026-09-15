import React from 'react';
import { useTranslation } from 'react-i18next';
import Icon from './md3/Icon';
import IconButton from './md3/IconButton';
import Button from './md3/Button';
import Divider from './md3/Divider';
import LanguageSwitcher from './LanguageSwitcher';
import Avatar from './Avatar';
import usePersonalisation from '../hooks/usePersonalisation';
import { THEME_MODES } from '../hooks/useThemeMode';

const THEME_ICONS = { light: 'lightMode', dark: 'darkMode', system: 'systemMode' };

function AppBarActions({ user, points, totalPoints, themeMode, onThemeModeChange, onLogout, onOpenAccount, onOpenPersonalise }) {
    const { t } = useTranslation();
    const { own } = usePersonalisation();
    const [menuOpen, setMenuOpen] = React.useState(false);
    const [showTotal, setShowTotal] = React.useState(false);
    const containerRef = React.useRef(null);

    React.useEffect(() => {
        if (!menuOpen) {
            return undefined;
        }

        const onPointerDown = (event) => {
            if (!containerRef.current?.contains(event.target)) {
                setMenuOpen(false);
            }
        };
        const onKeyDown = (event) => event.key === 'Escape' && setMenuOpen(false);

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [menuOpen]);

    const nextThemeMode = () =>
        THEME_MODES[(THEME_MODES.indexOf(themeMode) + 1) % THEME_MODES.length];

    return (
        <div className="user-info" ref={containerRef}>
            <button
                type="button"
                className="user-face"
                title={t('personalise.title')}
                onClick={onOpenPersonalise}
            >
                <Avatar face={own} name={own?.nickname || user?.name} size={32} />
            </button>

            <span className="user-welcome">{t('app.welcome', { name: own?.nickname || user?.name })}</span>

            <button
                type="button"
                className="user-points"
                aria-pressed={showTotal}
                title={showTotal ? t('user.pointsTotalHint') : t('user.pointsWeekHint')}
                onClick={() => setShowTotal((current) => !current)}
            >
                <Icon name="stars" size={18} />
                {t('user.points', { points: showTotal ? totalPoints ?? 0 : points ?? 0 })}
                <span className="user-points__scope">
                    {showTotal ? t('user.pointsTotal') : t('user.pointsWeek')}
                </span>
            </button>

            <Button
                variant="text"
                icon="logout"
                className="user-info__logout"
                onClick={onLogout}
            >
                {t('auth.logout')}
            </Button>

            <div className="account-menu">
                <IconButton
                    icon="more"
                    label={t('common.more')}
                    aria-haspopup="menu"
                    aria-expanded={menuOpen}
                    onClick={() => setMenuOpen((open) => !open)}
                />

                {menuOpen && (
                    <div className="account-menu__surface" role="menu">
                        <div className="account-menu__header">
                            <span className="account-menu__name">{own?.nickname || user?.name}</span>
                            <span className="account-menu__email">{user?.email}</span>
                        </div>

                        <Divider />

                        <button
                            type="button"
                            role="menuitem"
                            className="account-menu__item md-ripple-host"
                            onClick={() => {
                                onOpenAccount();
                                setMenuOpen(false);
                            }}
                        >
                            <Icon name="account" size={20} />
                            {t('nav.account')}
                        </button>

                        <button
                            type="button"
                            role="menuitem"
                            className="account-menu__item md-ripple-host"
                            onClick={() => {
                                onOpenPersonalise();
                                setMenuOpen(false);
                            }}
                        >
                            <Icon name="stars" size={20} />
                            {t('personalise.title')}
                        </button>

                        <button
                            type="button"
                            role="menuitem"
                            className="account-menu__item md-ripple-host"
                            onClick={() => onThemeModeChange(nextThemeMode())}
                        >
                            <Icon name={THEME_ICONS[themeMode]} size={20} />
                            {t(`theme.${themeMode}`)}
                        </button>

                        <Divider />

                        <div className="account-menu__group">
                            <span className="account-menu__group-label">
                                <Icon name="language" size={18} /> {t('theme.language')}
                            </span>
                            <LanguageSwitcher />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

export default AppBarActions;
