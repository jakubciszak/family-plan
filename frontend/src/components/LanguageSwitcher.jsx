import React from 'react';
import { useTranslation } from 'react-i18next';

const LanguageSwitcher = () => {
    const { i18n } = useTranslation();

    const changeLanguage = (lng) => {
        i18n.changeLanguage(lng);
    };

    return (
        <div className="language-switcher">
            <button
                onClick={() => changeLanguage('en')}
                className={i18n.language === 'en' ? 'active' : ''}
                aria-label="Switch to English"
            >
                EN
            </button>
            <button
                onClick={() => changeLanguage('pl')}
                className={i18n.language === 'pl' ? 'active' : ''}
                aria-label="Switch to Polish"
            >
                PL
            </button>
        </div>
    );
};

export default LanguageSwitcher;
