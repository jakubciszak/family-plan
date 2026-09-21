import { DateTimePicker } from '@expo/ui/community/datetime-picker';
import { useTranslation } from 'react-i18next';
import { useTheme } from 'react-native-paper';

import type { NativePickerProps } from './native-picker.types';

export default function NativePicker({ value, mode, minimumDate, onValueChange, onDismiss }: NativePickerProps) {
  const { i18n } = useTranslation();
  const theme = useTheme();
  return <DateTimePicker value={value} mode={mode} minimumDate={minimumDate} locale={i18n.language.startsWith('pl') ? 'pl_PL' : 'en_GB'} timeZoneName="UTC"
    themeVariant={theme.dark ? 'dark' : 'light'} accentColor={theme.colors.primary} display="spinner" onValueChange={(_, next) => onValueChange(next)} onDismiss={onDismiss} />;
}
