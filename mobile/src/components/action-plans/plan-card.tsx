import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Card, Text } from 'react-native-paper';

import type { PlanSnapshot } from '@/api/action-plans';
import { activitiesOf } from '@/action-plans/run';

export default function PlanCard({ plan, scope, taskName, busy, onStart, onEdit, onDelete }: {
  plan: PlanSnapshot; scope?: string; taskName?: string; busy?: boolean;
  onStart: () => void; onEdit?: () => void; onDelete?: () => void;
}) {
  const { t } = useTranslation();
  const [expanded, setExpanded] = useState(false);
  return <Card mode="outlined" testID={`plan-${plan.name}`} style={{ borderRadius: 20 }}>
    <Card.Content style={{ gap: 12 }}>
      <Text variant="titleLarge" accessibilityRole="header">{plan.name}</Text>
      <Text>{taskName ? t('actionPlans.forTask', { name: taskName }) : scope}</Text>
      <Text>{t('actionPlans.activityCount', { count: activitiesOf(plan).length })} · {plan.estimatedMinutes ? t('actionPlans.minutes', { count: plan.estimatedMinutes }) : t('actionPlans.noTimeLimit')}</Text>
      <Button accessibilityLabel={t('actionPlans.preview')} icon={expanded ? 'chevron-up' : 'chevron-down'} accessibilityState={{ expanded }} onPress={() => setExpanded(!expanded)}>{t('actionPlans.preview')}</Button>
      {expanded && <View style={{ gap: 12 }}>{plan.steps.map((step, index) => <View key={index} style={{ gap: 6 }}>
        <Text>{index + 1}. {step.name}</Text>
        {step.stages.map((stage, i) => <Text key={i} style={{ paddingLeft: 20 }}>{index + 1}.{i + 1}. {stage}</Text>)}
      </View>)}</View>}
      {taskName && <Text>{t('actionPlans.completesTaskHint')}</Text>}
      <Button mode="contained" disabled={busy} onPress={onStart}>{t('actionPlans.start')}</Button>
      {(onEdit || onDelete) && <View style={{ flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center' }}>
        {onEdit && <Button disabled={busy} onPress={onEdit}>{t('actionPlans.edit')}</Button>}
        {onDelete && <Button disabled={busy} onPress={onDelete}>{t('common.delete')}</Button>}
      </View>}
    </Card.Content>
  </Card>;
}
