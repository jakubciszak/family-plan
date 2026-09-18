import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Card, IconButton, Text, TextInput, useTheme } from 'react-native-paper';

import type { ActionPlan, PlanDraft, PlanStep } from '@/api/action-plans';
import type { Team } from '@/api/teams';
import { activitiesOf } from '@/action-plans/run';
import ChoicePicker from './choice-picker';
import SoundPicker, { useReminderSound } from './sound-picker';

const move = <T,>(items: T[], index: number, delta: number): T[] => {
  const result = [...items];
  [result[index], result[index + delta]] = [result[index + delta], result[index]];
  return result;
};

export default function PlanEditor({ plan, teams, saving, error, onSave, onCancel }: {
  plan: ActionPlan | null; teams: Team[]; saving: boolean; error: string;
  onSave: (draft: PlanDraft) => void; onCancel: () => void;
}) {
  const { t } = useTranslation();
  const theme = useTheme();
  const audio = useReminderSound();
  const [draft, setDraft] = useState<PlanDraft>(() => plan ?? {
    name: '', teamId: null, estimatedMinutes: null, reminderMinutes: 10, reminderSound: 'soft', steps: [{ name: '', stages: [] }],
  });
  const [duration, setDuration] = useState(String(plan?.estimatedMinutes ?? ''));
  const [reminder, setReminder] = useState(String(plan?.reminderMinutes ?? 10));
  const [invalid, setInvalid] = useState('');
  const change = (fields: Partial<PlanDraft>) => setDraft((current) => ({ ...current, ...fields }));
  const stepChange = (index: number, fields: Partial<PlanStep>) => change({ steps: draft.steps.map((step, i) => i === index ? { ...step, ...fields } : step) });
  const reorder = <T,>(items: T[], index: number, update: (items: T[]) => void, name: string) => <>
    <IconButton icon="arrow-up" disabled={saving || index === 0} accessibilityLabel={t('actionPlans.moveUp', { name })} onPress={() => update(move(items, index, -1))} />
    <IconButton icon="arrow-down" disabled={saving || index === items.length - 1} accessibilityLabel={t('actionPlans.moveDown', { name })} onPress={() => update(move(items, index, 1))} />
  </>;
  const save = () => {
    const next = { ...draft, name: draft.name.trim(), estimatedMinutes: duration.trim() === '' ? null : Number(duration), reminderMinutes: Number(reminder),
      steps: draft.steps.map((step) => ({ name: step.name.trim(), stages: step.stages.map((stage) => stage.trim()) })) };
    let key = '';
    if (!next.name || next.name.length > 160 || next.steps.some((step) => !step.name || step.name.length > 240 || step.stages.some((stage) => !stage || stage.length > 240))) key = 'invalidName';
    else if (next.estimatedMinutes !== null && (!Number.isInteger(next.estimatedMinutes) || next.estimatedMinutes < 1 || next.estimatedMinutes > 1440)) key = 'invalidDuration';
    else if (!Number.isInteger(next.reminderMinutes) || next.reminderMinutes < 1 || next.reminderMinutes > 120) key = 'invalidReminder';
    else if (!next.steps.length || next.steps.length > 100 || next.steps.some((step) => step.stages.length > 100) || activitiesOf(next).length > 500) key = 'invalidSteps';
    setInvalid(key ? t(`actionPlans.${key}`) : '');
    if (!key) { audio.stop(); onSave(next); }
  };
  return <View style={{ gap: 16 }} testID="plan-editor">
    <Text variant="headlineSmall" accessibilityRole="header">{t(plan ? 'actionPlans.edit' : 'actionPlans.create')}</Text>
    <TextInput mode="outlined" label={t('actionPlans.name')} accessibilityLabel={t('actionPlans.name')} value={draft.name} maxLength={160} disabled={saving} onChangeText={(name) => change({ name })} />
    <ChoicePicker label={t('actionPlans.visibility')} value={draft.teamId ?? ''} disabled={saving || plan?.canChangeScope === false}
      options={[{ value: '', label: t('actionPlans.private') }, ...teams.map((team) => ({ value: team.id, label: team.name })),
        ...(draft.teamId && !teams.some((team) => team.id === draft.teamId) ? [{ value: draft.teamId, label: t('teams.title') }] : [])]}
      onChange={(teamId) => change({ teamId: teamId || null })} />
    <Text>{t('actionPlans.visibilityHint')}</Text>
    {plan?.canChangeScope === false && <Text>{t('actionPlans.onlyAuthorChangesScope')}</Text>}
    <TextInput mode="outlined" label={t('actionPlans.duration')} accessibilityLabel={t('actionPlans.duration')} value={duration} keyboardType="number-pad" disabled={saving} onChangeText={setDuration} />
    {!Number(duration) && <TextInput mode="outlined" label={t('actionPlans.reminderMinutes')} accessibilityLabel={t('actionPlans.reminderMinutes')} value={reminder} keyboardType="number-pad" disabled={saving} onChangeText={setReminder} />}
    <Text>{t(Number(duration) ? 'actionPlans.estimateHint' : 'actionPlans.noEstimateHint')}</Text>
    <SoundPicker value={draft.reminderSound} onChange={(reminderSound) => change({ reminderSound })} disabled={saving} audio={audio} />
    <Text variant="titleLarge" accessibilityRole="header">{t('actionPlans.steps')}</Text>
    <Text>{t('actionPlans.stagesHint')}</Text>
    {draft.steps.map((step, index) => <Card key={index} mode="outlined" testID={`plan-step-${index}`}>
      <Card.Content style={{ gap: 8 }}>
        <TextInput mode="outlined" label={t('actionPlans.stepNumber', { number: index + 1 })} accessibilityLabel={t('actionPlans.stepNumber', { number: index + 1 })} value={step.name} maxLength={240} disabled={saving} onChangeText={(name) => stepChange(index, { name })} />
        <View style={{ flexDirection: 'row', justifyContent: 'flex-end' }}>
          {reorder(draft.steps, index, (steps) => change({ steps }), step.name || String(index + 1))}
          <IconButton icon="trash-can-outline" disabled={saving || draft.steps.length === 1} accessibilityLabel={t('actionPlans.removeStep', { number: index + 1 })} onPress={() => change({ steps: draft.steps.filter((_, i) => i !== index) })} />
        </View>
        {step.stages.map((stage, stageIndex) => <View key={stageIndex} style={{ paddingLeft: 16 }}>
          <TextInput mode="outlined" label={t('actionPlans.stageNumber', { number: `${index + 1}.${stageIndex + 1}` })} accessibilityLabel={t('actionPlans.stageNumber', { number: `${index + 1}.${stageIndex + 1}` })} value={stage} maxLength={240} disabled={saving} onChangeText={(name) => stepChange(index, { stages: step.stages.map((value, i) => i === stageIndex ? name : value) })} />
          <View style={{ flexDirection: 'row', justifyContent: 'flex-end' }}>
            {reorder(step.stages, stageIndex, (stages) => stepChange(index, { stages }), stage || `${index + 1}.${stageIndex + 1}`)}
            <IconButton icon="trash-can-outline" disabled={saving} accessibilityLabel={t('actionPlans.removeStage', { number: `${index + 1}.${stageIndex + 1}` })} onPress={() => stepChange(index, { stages: step.stages.filter((_, i) => i !== stageIndex) })} />
          </View>
        </View>)}
        <Button accessibilityLabel={t('actionPlans.addStage')} disabled={saving || step.stages.length >= 100} icon="plus" onPress={() => stepChange(index, { stages: [...step.stages, ''] })}>{t('actionPlans.addStage')}</Button>
      </Card.Content>
    </Card>)}
    <Button mode="contained-tonal" icon="plus" accessibilityLabel={t('actionPlans.addStep')} disabled={saving || draft.steps.length >= 100} onPress={() => change({ steps: [...draft.steps, { name: '', stages: [] }] })}>{t('actionPlans.addStep')}</Button>
    {!!(invalid || error) && <Text accessibilityRole="alert" style={{ color: theme.colors.error }}>{invalid || error}</Text>}
    <Button mode="contained" disabled={saving} loading={saving} onPress={save}>{t('common.save')}</Button>
    <Button disabled={saving} onPress={() => { audio.stop(); onCancel(); }}>{t('common.cancel')}</Button>
  </View>;
}
