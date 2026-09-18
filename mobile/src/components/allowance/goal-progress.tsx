import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { ProgressBar, Text } from 'react-native-paper';
import type { Goal } from '@/api/allowance';
import { formatMoney } from '@/money';

export default function GoalProgress({ goal, currency }: { goal: Goal; currency: string }) {
  const { t, i18n } = useTranslation();
  const money = (amount: number) => formatMoney(amount, currency, i18n.language);
  return (
    <View style={{ gap: 8 }} testID={`goal-progress-${goal.id}`}>
      <Text variant="titleSmall">{goal.name}</Text>
      <ProgressBar
        progress={goal.target ? Math.min(1, goal.saved / goal.target) : 0}
        style={{ height: 8, borderRadius: 4 }}
      />
      <Text>
        {money(goal.saved)} / {money(goal.target)}
      </Text>
      <Text variant="bodySmall">
        {goal.reached
          ? t('allowance.goalReached')
          : goal.perWeekNeeded != null && goal.weeksLeft != null
            ? t('allowance.goalPerWeek', {
                amount: money(goal.perWeekNeeded),
                weeks: goal.weeksLeft,
              })
            : goal.weeksAtThisPace != null
              ? t('allowance.goalAtThisPace', { weeks: goal.weeksAtThisPace })
              : t('allowance.goalMissing', { amount: money(goal.missing) })}
      </Text>
    </View>
  );
}
