import React from 'react';
import { useTranslation } from 'react-i18next';
import Icon from './md3/Icon';

const LANGUAGES = ['pl', 'en'];

const LanguageSwitcher = () => {
    const { t, i18n } = useTranslation();

    return (
        <div className="language-switcher md-segmented" role="group" aria-label={t('theme.language')}>
            {LANGUAGES.map((lng) => {
                const active = i18n.language?.startsWith(lng);

                return (
                    <button
                        key={lng}
                        type="button"
                        className={`md-segmented__item md-ripple-host${active ? ' active' : ''}`}
                        aria-pressed={active}
                        aria-label={lng === 'en' ? 'Switch to English' : 'Switch to Polish'}
                        onClick={() => i18n.changeLanguage(lng)}
                    >
                        {active && <Icon name="check" size={18} />}
                        {lng.toUpperCase()}
                    </button>
                );
            })}
        </div>
    );
};

export default LanguageSwitcher;
