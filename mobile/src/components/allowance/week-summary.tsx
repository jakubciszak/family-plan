import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Text, useTheme } from 'react-native-paper';
import type { Week } from '@/api/allowance';
import { formatMoney } from '@/money';

export default function WeekSummary({ week }: { week: Week }) {
  const { t, i18n } = useTranslation();
  const theme = useTheme();
  const lines = week.closure?.lines ?? week.expected.lines;
  const money = (amount: number) => formatMoney(amount, week.currency, i18n.language);
  return (
    <View style={{ gap: 16 }} testID="allowance-week-summary">
      <Text variant="headlineSmall">{money(week.closure?.total ?? week.expected.total)}</Text>
      <View style={{ flexDirection: 'row', gap: 4 }}>
        {week.days.map((day, index) => (
          <View
            key={day.date}
            style={{
              flex: 1,
              minWidth: 0,
              alignItems: 'center',
              gap: 4,
              paddingVertical: 8,
              borderRadius: 12,
              backgroundColor: theme.colors.secondaryContainer,
              borderWidth: day.isToday ? 2 : 0,
              borderColor: theme.colors.primary,
            }}
          >
            <Text style={{ fontSize: 11 }}>
              {t(`week.days.${['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][index]}`)}
            </Text>
            <Text variant="titleMedium">{day.points}</Text>
            {day.bonus > 0 ? (
              <Text style={{ fontSize: 11 }}>{t('week.bonus', { points: day.bonus })}</Text>
            ) : null}
          </View>
        ))}
      </View>
      {lines.map((line) => (
        <View key={line.pointsAccount} style={{ gap: 4 }}>
          <Text>
            {t(`allowance.accounts.${line.pointsAccount}`)} ·{' '}
            {t('allowance.pointsOfMinimum', { points: line.points, minimum: line.minimumPoints })} ·{' '}
            {money(line.amount)}
          </Text>
          {!week.closure && line.missingPoints > 0 ? (
            <Text variant="bodySmall">
              {t('allowance.missingToEarn', { points: line.missingPoints })}
            </Text>
          ) : null}
        </View>
      ))}
      {!lines.length ? <Text>{t('allowance.noRulesYet')}</Text> : null}
      <Text variant="bodySmall">
        {week.closure
          ? t('allowance.weekClosedOn', {
              when: new Date(week.closure.closedAt).toLocaleDateString(i18n.language),
            })
          : t('allowance.weekStillOpen')}
      </Text>
    </View>
  );
}
