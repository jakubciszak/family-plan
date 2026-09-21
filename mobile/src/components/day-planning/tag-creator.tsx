import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Text, TextInput } from 'react-native-paper';

import { saveCalendarTag, type CalendarTag } from '@/api/day-planning';
import ChoicePicker from '@/components/action-plans/choice-picker';
import TagColors from '@/components/day-planning/tag-colors';

type Props = {
  teamId: string | null;
  scopes: CalendarTag['scope'][];
  onCreated: (tag: CalendarTag) => void;
  onCatalogChanged: () => void;
  onCancel: () => void;
};

export default function TagCreator({ teamId, scopes, onCreated, onCatalogChanged, onCancel }: Props) {
  const { t } = useTranslation();
  const [name, setName] = useState('');
  const [color, setColor] = useState('#226a4c');
  const [scope, setScope] = useState(scopes[0]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const pending = useRef(false);
  const active = useRef(true);
  useEffect(() => { active.current = true; return () => { active.current = false; }; }, []);
  const save = async () => {
    if (pending.current) return;
    if (!name.trim() || name.trim().length > 60 || !scopes.includes(scope) || (scope === 'TEAM' && !teamId)) { setError(t('dayPlanning.invalidTag')); return; }
    pending.current = true; setSaving(true); setError('');
    try {
      const tag = await saveCalendarTag({ name: name.trim(), color, scope, teamId: scope === 'TEAM' ? teamId : null });
      onCatalogChanged();
      if (active.current) onCreated(tag);
    } catch { if (active.current) setError(t('dayPlanning.saveError')); }
    finally { pending.current = false; if (active.current) setSaving(false); }
  };
  return <View testID="day-inline-tag" style={{ gap: 12 }}>
    <Text variant="titleMedium">{t('dayPlanning.newTag')}</Text>
    {!!error && <Text accessibilityRole="alert">{error}</Text>}
    <TextInput mode="outlined" label={t('dayPlanning.tagName')} accessibilityLabel={t('dayPlanning.tagName')} value={name} onChangeText={setName} maxLength={60} disabled={saving} />
    {scopes.length > 1 ? <ChoicePicker label={t('dayPlanning.tagScope')} value={scope} disabled={saving} options={scopes.map((value) => ({ value, label: t(`dayPlanning.tagScopes.${value}`) }))} onChange={(value) => setScope(value as CalendarTag['scope'])} /> : <Text>{t(`dayPlanning.tagScopes.${scope}`)}</Text>}
    <TagColors value={color} onChange={setColor} disabled={saving} />
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
      <Button mode="contained-tonal" loading={saving} disabled={saving || !name.trim()} onPress={() => void save()}>{t('dayPlanning.createTag')}</Button>
      <Button disabled={saving} onPress={onCancel}>{t('dayPlanning.cancelTag')}</Button>
    </View>
    <Text variant="bodySmall">{t('dayPlanning.finishTagFirst')}</Text>
  </View>;
}
