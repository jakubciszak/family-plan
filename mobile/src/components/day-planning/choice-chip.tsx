import { Pressable } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';

export default function ChoiceChip({ label, accessibilityLabel, checked, disabled = false, onPress }: {
  label: string; accessibilityLabel: string; checked: boolean; disabled?: boolean; onPress: () => void;
}) {
  const theme = useTheme();
  const ink = checked ? theme.colors.onSecondaryContainer : theme.colors.onSurfaceVariant;
  return <Pressable accessibilityRole="checkbox" accessibilityLabel={accessibilityLabel} accessibilityState={{ checked, disabled }}
    aria-checked={checked} aria-disabled={disabled} disabled={disabled} onPress={onPress}
    style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: 6, minHeight: 40, paddingHorizontal: 12, borderRadius: 8, borderWidth: 1,
      borderColor: checked ? theme.colors.secondaryContainer : theme.colors.outline,
      backgroundColor: checked ? theme.colors.secondaryContainer : pressed ? theme.colors.surfaceVariant : 'transparent', opacity: disabled ? 0.6 : 1 })}>
    {checked && <Icon source="check" size={18} color={ink} />}
    <Text variant="labelLarge" style={{ color: ink }}>{label}</Text>
  </Pressable>;
}
