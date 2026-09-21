import { useTranslation } from 'react-i18next';
import { Pressable, View } from 'react-native';
import { Text, useTheme } from 'react-native-paper';

export const TAG_COLORS = [
  { name: 'green', value: '#226a4c' }, { name: 'blue', value: '#325f99' },
  { name: 'purple', value: '#86549e' }, { name: 'orange', value: '#a45132' },
  { name: 'gold', value: '#706219' }, { name: 'red', value: '#b3261e' },
  { name: 'pink', value: '#a63d72' }, { name: 'teal', value: '#007f82' },
  { name: 'gray', value: '#667085' }, { name: 'indigo', value: '#4f46a5' },
];

const contrast = (color: string) => {
  const channels = color.slice(1).match(/.{2}/g)?.map((channel) => {
    const value = parseInt(channel, 16) / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });
  return channels && channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722 > 0.179 ? '#171717' : '#ffffff';
};

export function TagColorDot({ color, size = 18, selected = false }: { color: string; size?: number; selected?: boolean }) {
  const theme = useTheme();
  return <View accessible={false} aria-hidden testID="tag-color-dot" style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: color, borderWidth: 1, borderColor: theme.colors.outline, alignItems: 'center', justifyContent: 'center' }}>{selected && <Text style={{ color: contrast(color), fontSize: 12, lineHeight: 14, fontWeight: '700' }}>✓</Text>}</View>;
}

export default function TagColors({ value, onChange, disabled = false, customColor }: { value: string; onChange: (color: string) => void; disabled?: boolean; customColor?: string }) {
  const { t } = useTranslation();
  const theme = useTheme();
  const current = customColor ?? value;
  const colors = TAG_COLORS.some((color) => color.value === current.toLowerCase()) ? TAG_COLORS : [...TAG_COLORS, { name: 'current', value: current }];
  return <View style={{ gap: 8 }}>
    <Text variant="titleSmall">{t('dayPlanning.tagColor')}</Text>
    <View accessibilityRole="radiogroup" accessibilityLabel={t('dayPlanning.tagColor')} style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
      {colors.map((color) => {
        const selected = color.value.toLowerCase() === value.toLowerCase();
        return <Pressable key={color.value} accessibilityRole="radio" accessibilityLabel={t(`dayPlanning.colors.${color.name}`)} accessibilityState={{ checked: selected, disabled }} aria-checked={selected} aria-disabled={disabled} disabled={disabled} onPress={() => onChange(color.value)} style={{ width: 76, minHeight: 72, alignItems: 'center', gap: 4, padding: 4, opacity: disabled ? 0.5 : 1 }}>
          <View style={{ width: 40, height: 40, padding: 3, borderRadius: 20, borderWidth: 2, borderColor: selected ? theme.colors.primary : 'transparent' }}>
            <View style={{ flex: 1, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: color.value, borderWidth: 1, borderColor: theme.colors.outline }}>
              {selected && <Text accessible={false} style={{ color: contrast(color.value), fontWeight: '700', fontSize: 18 }}>✓</Text>}
            </View>
          </View>
          <Text style={{ fontSize: 11, textAlign: 'center' }}>{t(`dayPlanning.colors.${color.name}`)}</Text>
        </Pressable>;
      })}
    </View>
  </View>;
}
