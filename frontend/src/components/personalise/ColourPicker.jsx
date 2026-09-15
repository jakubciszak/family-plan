import React from 'react';
import { useTranslation } from 'react-i18next';

const COLOURS = [
    '#2e7d5b', '#1565c0', '#8e24aa', '#d81b60', '#ef6c00', '#00838f',
    '#5d4037', '#455a64', '#c62828', '#6a1b9a', '#2e7d32', '#f9a825',
];

function ColourPicker({ theme, onPick }) {
    const { t } = useTranslation();

    return (
        <div className="colour-picker" data-testid="colour-picker">
            <div className="colour-picker__swatches" role="group" aria-label={t('personalise.colour')}>
                {COLOURS.map((colour) => (
                    <button
                        key={colour}
                        type="button"
                        className={`colour-picker__swatch${theme === colour ? ' is-chosen' : ''}`}
                        style={{ '--swatch': colour }}
                        aria-pressed={theme === colour}
                        aria-label={colour}
                        onClick={() => onPick(colour)}
                    />
                ))}
            </div>

            <label className="colour-picker__own">
                <span>{t('personalise.ownColour')}</span>
                <input
                    type="color"
                    value={theme || '#2e7d5b'}
                    onChange={(event) => onPick(event.target.value.toLowerCase())}
                />
            </label>
        </div>
    );
}

export default ColourPicker;
