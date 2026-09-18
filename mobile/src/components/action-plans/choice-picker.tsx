import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, List, Surface, Text, TextInput } from 'react-native-paper';

export default function ChoicePicker({ label, value, options, onChange, disabled = false }: {
  label: string; value: string; options: { value: string; label: string }[];
  onChange: (value: string) => void; disabled?: boolean;
}) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const filtered = options.filter((option) => option.label.toLocaleLowerCase().includes(search.toLocaleLowerCase()));
  return <View style={{ gap: 6 }}>
    <Text variant="labelLarge">{label}</Text>
    <Button mode="outlined" icon={open ? 'chevron-up' : 'chevron-down'} disabled={disabled} accessibilityLabel={label}
      onPress={() => { setOpen(!open); setSearch(''); }} contentStyle={{ flexDirection: 'row-reverse' }}>
      {options.find((option) => option.value === value)?.label ?? value}
    </Button>
    {open && !disabled && <Surface elevation={1} style={{ borderRadius: 12, padding: 8 }}>
      <TextInput mode="outlined" accessibilityLabel={t('actionPlans.searchScope')} placeholder={t('actionPlans.searchScope')} value={search} onChangeText={setSearch} />
      {filtered.map((option) => <List.Item key={option.value} title={option.label} titleNumberOfLines={3}
        accessibilityRole="button" accessibilityLabel={option.label}
        left={(props) => <List.Icon {...props} icon={option.value === value ? 'radiobox-marked' : 'radiobox-blank'} />}
        onPress={() => { onChange(option.value); setOpen(false); }} />)}
      {!filtered.length && <Text>{t('actionPlans.noMatchingScopes')}</Text>}
    </Surface>}
  </View>;
}
