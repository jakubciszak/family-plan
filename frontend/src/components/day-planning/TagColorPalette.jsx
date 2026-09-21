import React, { useId } from 'react';
import { useTranslation } from 'react-i18next';

const colors = [
    ['green', '#226a4c'], ['blue', '#325f99'], ['purple', '#86549e'], ['orange', '#a45132'], ['gold', '#706219'],
    ['red', '#b3261e'], ['pink', '#a63d72'], ['teal', '#007f82'], ['gray', '#667085'], ['indigo', '#4f46a5'],
];

const checkColor = (color) => {
    const channels = color.slice(1).match(/.{2}/g).map((channel) => Number.parseInt(channel, 16) / 255).map((channel) => channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4);
    return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722 > .179 ? '#000' : '#fff';
};

export default function TagColorPalette({ value, onChange, disabled = false, customColor }) {
    const { t } = useTranslation();
    const name = useId();
    const original = customColor || value;
    const palette = colors.some(([, color]) => color === original.toLowerCase()) ? colors : [...colors, ['current', original]];
    return <fieldset className="day-choice-group day-tag-colors" disabled={disabled}>
        <legend>{t('dayPlanning.color')}</legend>
        <div className="day-color-palette">{palette.map(([label, color]) => <label key={color} className="day-color-option" title={t(`dayPlanning.tagColors.${label}`)}>
            <input type="radio" name={name} aria-label={t(`dayPlanning.tagColors.${label}`)} value={color} checked={color.toLowerCase() === value.toLowerCase()} onChange={() => onChange(color)} />
            <span className="day-color-swatch" style={{ backgroundColor: color, color: checkColor(color) }} aria-hidden="true">{color.toLowerCase() === value.toLowerCase() ? '✓' : ''}</span>
        </label>)}</div>
    </fieldset>;
}
