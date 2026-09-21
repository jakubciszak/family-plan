import { useLayoutEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal, Platform, Pressable, View } from 'react-native';
import { Button, Icon, Text, useTheme } from 'react-native-paper';

import type { PlanningDateTimeFieldProps } from './date-time-field.types';
import NativePicker from './native-picker';
import { dayString } from '@/dates';
import { formatPickerValue, isPickerValue, pickerDate, pickerMinimumDate, pickerValue } from '@/day-planning/picker-values';

export default function PlanningDateTimeField({ label, value, mode, onChange, disabled = false, minimumDate }: PlanningDateTimeFieldProps) {
  const { t, i18n } = useTranslation();
  const theme = useTheme();
  const [open, setOpen] = useState(false);
  if (disabled && open) setOpen(false);
  const current = useRef({ value, mode, minimumDate, disabled, onChange });
  useLayoutEffect(() => { current.current = { value, mode, minimumDate, disabled, onChange }; }, [value, mode, minimumDate, disabled, onChange]);
  const display = formatPickerValue(value, mode, i18n.language) || t(mode === 'date' ? 'dayPlanning.chooseDate' : 'dayPlanning.chooseTime');
  const minimum = mode === 'date' && minimumDate && isPickerValue(minimumDate, 'date') ? minimumDate : undefined;
  const initial = isPickerValue(value, mode) ? value : minimum ?? (mode === 'date' ? dayString(new Date()) : '09:00');
  return <View style={{ gap: 6 }}>
    <Text variant="labelLarge" style={{ color: disabled ? theme.colors.onSurfaceDisabled : theme.colors.onSurfaceVariant }}>{label}</Text>
    <Pressable accessibilityRole="button" accessibilityLabel={label} accessibilityValue={{ text: display }} accessibilityState={{ disabled, expanded: open }} disabled={disabled}
      onPress={() => setOpen(true)} style={({ pressed }) => ({ minHeight: 56, paddingHorizontal: 14, paddingVertical: 12, borderWidth: 1, borderRadius: 8, borderColor: theme.colors.outline, backgroundColor: pressed ? theme.colors.surfaceVariant : theme.colors.surface, flexDirection: 'row', alignItems: 'center', gap: 12, opacity: disabled ? 0.55 : 1 })}>
      <Text style={{ flex: 1 }}>{display}</Text><Icon source={mode === 'date' ? 'calendar-month-outline' : 'clock-outline'} size={24} color={theme.colors.onSurfaceVariant} />
    </Pressable>
    {open && !disabled && <PickerSelection key={`${mode}-${value}-${minimum}`} label={label} value={minimum && initial < minimum ? minimum : initial} mode={mode} minimumDate={minimum}
      onCancel={() => setOpen(false)} onChange={(next) => { setOpen(false); if (!current.current.disabled && current.current.value === value && current.current.mode === mode && current.current.minimumDate === minimumDate) current.current.onChange(next); }} />}
  </View>;
}

function PickerSelection({ label, value, mode, onChange, onCancel, minimumDate }: PlanningDateTimeFieldProps & { onCancel: () => void }) {
  const { t } = useTranslation();
  const theme = useTheme();
  const platform = Platform.OS === 'android' ? 'android' : 'ios';
  const [selection, setSelection] = useState(() => pickerDate(value, mode, platform));
  const active = useRef(true);
  useLayoutEffect(() => { active.current = true; return () => { active.current = false; }; }, []);
  const commit = (date: Date) => { if (!active.current) return; active.current = false; onChange(pickerValue(date, mode, platform)); };
  const cancel = () => { active.current = false; onCancel(); };
  const picker = <NativePicker value={selection} mode={mode}
    minimumDate={mode === 'date' && minimumDate ? pickerMinimumDate(minimumDate, platform) : undefined}
    onValueChange={(next) => { if (active.current) { if (platform === 'android') commit(next); else setSelection(next); } }} onDismiss={cancel} />;
  if (platform === 'android') return picker;
  return <Modal visible transparent animationType="fade" onRequestClose={cancel}>
    <View style={{ flex: 1, backgroundColor: theme.colors.backdrop, justifyContent: 'center', padding: 16 }}>
      <View accessibilityViewIsModal style={{ backgroundColor: theme.colors.surface, borderRadius: 20, paddingVertical: 20, overflow: 'hidden' }}>
        <Text variant="titleLarge" accessibilityRole="header" style={{ paddingHorizontal: 20 }}>{label}</Text>
        {picker}
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'flex-end', paddingHorizontal: 12, gap: 8 }}>
          <Button onPress={cancel}>{t('common.cancel')}</Button><Button onPress={() => commit(selection)}>{t('common.confirm')}</Button>
        </View>
      </View>
    </View>
  </Modal>;
}
