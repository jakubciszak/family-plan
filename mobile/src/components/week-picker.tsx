import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, IconButton, Text } from 'react-native-paper';

import { currentMonday, shiftDay } from '@/dates';

export default function WeekPicker({
  value,
  onChange,
  disabled = false,
}: {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
}) {
  const { t } = useTranslation();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
      <IconButton
        icon="chevron-left"
        accessibilityLabel={t('week.previous')}
        disabled={disabled}
        onPress={() => onChange(shiftDay(value, -7))}
      />
      <View style={{ alignItems: 'center' }}>
        <Text>
          {value} – {shiftDay(value, 6)}
        </Text>
        {value < currentMonday() ? (
          <Button compact disabled={disabled} onPress={() => onChange(currentMonday())}>
            {t('week.thisWeek')}
          </Button>
        ) : null}
      </View>
      <IconButton
        icon="chevron-right"
        accessibilityLabel={t('week.next')}
        disabled={disabled || value >= currentMonday()}
        onPress={() => onChange(shiftDay(value, 7))}
      />
    </View>
  );
}
