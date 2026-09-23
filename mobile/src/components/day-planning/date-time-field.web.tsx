import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Icon, useTheme } from 'react-native-paper';

import type { PlanningDateTimeFieldProps } from './date-time-field.types';
import { formatPickerValue, isPickerValue } from '@/day-planning/picker-values';

export default function PlanningDateTimeField({ label, value, mode, onChange, disabled = false, minimumDate, title }: PlanningDateTimeFieldProps) {
  const theme = useTheme();
  const { t, i18n } = useTranslation();
  const input = useRef<HTMLInputElement>(null);
  const [focused, setFocused] = useState(false);
  const show = () => { if (!disabled) { input.current?.focus(); try { input.current?.showPicker(); } catch {} } };
  const display = formatPickerValue(value, mode, i18n.language) || t(mode === 'date' ? 'dayPlanning.chooseDate' : 'dayPlanning.chooseTime');
  const control = <input ref={input} type={mode} aria-label={label} value={value} disabled={disabled} min={mode === 'date' ? minimumDate : undefined} step={mode === 'time' ? 60 : undefined} lang={i18n.language}
    onClick={show} onFocus={() => setFocused(true)} onBlur={() => setFocused(false)} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); show(); } }}
    onChange={(event) => { const next = event.currentTarget.value; if (!disabled && isPickerValue(next, mode)) onChange(next); }}
    style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', opacity: 0, border: 0, padding: 0, colorScheme: theme.dark ? 'dark' : 'light', fontSize: 16, cursor: disabled ? 'default' : 'pointer' }} />;
  if (title) return <label style={{ position: 'relative', display: 'flex', alignSelf: 'flex-start', alignItems: 'center', gap: 4, minHeight: 44, paddingRight: 4, borderRadius: 8, outline: focused ? `2px solid ${theme.colors.primary}` : undefined, color: theme.colors.onSurface, fontFamily: theme.fonts.titleMedium.fontFamily, fontSize: theme.fonts.titleMedium.fontSize, fontWeight: theme.fonts.titleMedium.fontWeight, opacity: disabled ? 0.55 : 1 }}>
    <span aria-hidden="true">{title}</span>
    <span aria-hidden="true" style={{ display: 'flex' }}><Icon source="chevron-down" size={22} color={theme.colors.onSurfaceVariant} /></span>
    {control}
  </label>;
  return <label style={{ display: 'flex', flexDirection: 'column', gap: 6, color: theme.colors.onSurfaceVariant, fontFamily: theme.fonts.bodyMedium.fontFamily, fontSize: 14, fontWeight: 500 }}>
    {label}
    <span style={{ position: 'relative', display: 'flex', alignItems: 'center', gap: 12, minHeight: 56, boxSizing: 'border-box', border: `1px solid ${focused ? theme.colors.primary : theme.colors.outline}`, outline: focused ? `1px solid ${theme.colors.primary}` : undefined, borderRadius: 8, padding: '12px 14px', backgroundColor: theme.colors.surface, opacity: disabled ? 0.55 : 1 }}>
      <span aria-hidden="true" style={{ flex: 1, color: theme.colors.onSurface, fontSize: 16, fontWeight: 400 }}>{display}</span>
      <span aria-hidden="true"><Icon source={mode === 'date' ? 'calendar-month-outline' : 'clock-outline'} size={24} color={theme.colors.onSurfaceVariant} /></span>
      {control}
    </span>
  </label>;
}
