import { DatePickerDialog, Host, TimePickerDialog } from '@expo/ui/jetpack-compose';
import { useTranslation } from 'react-i18next';
import { useTheme } from 'react-native-paper';

import type { NativePickerProps } from './native-picker.types';

export default function NativePicker({ value, mode, minimumDate, onValueChange, onDismiss }: NativePickerProps) {
  const { t } = useTranslation();
  const theme = useTheme();
  const props = { initialDate: value.toISOString(), onDateSelected: onValueChange, onDismissRequest: onDismiss,
    color: theme.colors.primary, confirmButtonLabel: t('common.confirm'), dismissButtonLabel: t('common.cancel') };
  return <Host colorScheme={theme.dark ? 'dark' : 'light'} seedColor={theme.colors.primary}>
    {mode === 'date' ? <DatePickerDialog {...props} selectableDates={minimumDate ? { start: minimumDate } : undefined} /> : <TimePickerDialog {...props} is24Hour />}
  </Host>;
}
