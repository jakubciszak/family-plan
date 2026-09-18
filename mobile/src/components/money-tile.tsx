import { StyleSheet, View } from 'react-native';
import { Text, useTheme } from 'react-native-paper';

import { formatMoney } from '@/money';

type Props = {
  label: string;
  hint?: string;
  amount: number;
  currency: string;
  language: string;
  tone?: 'primary' | 'secondary' | 'tertiary';
};

export default function MoneyTile({
  label,
  hint,
  amount,
  currency,
  language,
  tone = 'primary',
}: Props) {
  const theme = useTheme();

  const paint = {
    primary: { on: theme.colors.onPrimaryContainer, in: theme.colors.primaryContainer },
    secondary: { on: theme.colors.onSecondaryContainer, in: theme.colors.secondaryContainer },
    tertiary: { on: theme.colors.onTertiaryContainer, in: theme.colors.tertiaryContainer },
  }[tone];

  return (
    <View style={[styles.tile, { backgroundColor: paint.in }]}>
      <Text variant="labelLarge" style={{ color: paint.on }}>
        {label}
      </Text>
      <Text variant="headlineSmall" style={{ color: paint.on }}>
        {formatMoney(amount, currency, language)}
      </Text>
      {hint ? (
        <Text variant="bodySmall" style={{ color: paint.on }}>
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  tile: {
    borderRadius: 16,
    flexGrow: 1,
    flexBasis: '45%',
    gap: 2,
    padding: 16,
  },
});
