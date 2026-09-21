import { Pressable, View } from 'react-native';
import { Icon, Text, useTheme } from 'react-native-paper';

export default function PlanningCheckbox({ label, accessibilityLabel, status, disabled = false, onPress }: {
  label: string; accessibilityLabel: string; status: 'checked' | 'unchecked'; disabled?: boolean; onPress: () => void;
}) {
  const theme = useTheme();
  const checked = status === 'checked';
  return <Pressable accessibilityRole="checkbox" accessibilityLabel={accessibilityLabel} accessibilityState={{ checked, disabled }}
    aria-checked={checked} aria-disabled={disabled} disabled={disabled} onPress={onPress}
    style={({ pressed }) => ({ paddingVertical: 12, paddingHorizontal: 8, minHeight: 48, opacity: disabled ? 0.6 : 1, backgroundColor: pressed ? theme.colors.surfaceVariant : 'transparent' })}>
    <View style={{ flexDirection: 'row', gap: 12, alignItems: 'center' }}>
      <Icon source={checked ? 'checkbox-marked' : 'checkbox-blank-outline'} size={24} color={theme.colors.primary} />
      <Text style={{ flex: 1 }}>{label}</Text>
    </View>
  </Pressable>;
}
