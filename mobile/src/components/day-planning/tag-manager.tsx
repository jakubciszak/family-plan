import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Chip, Text, TextInput } from 'react-native-paper';
import { archiveCalendarTag, saveCalendarTag, type CalendarTag } from '@/api/day-planning';
import type { Team } from '@/api/teams';
import ChoicePicker from '@/components/action-plans/choice-picker';

export default function TagManager({ tags, team, onChanged, onClose }: {
  tags: CalendarTag[]; team?: Team; onChanged: () => Promise<void>; onClose: () => void;
}) {
  const { t } = useTranslation();
  const [editing, setEditing] = useState<CalendarTag | null>(null);
  const [name, setName] = useState('');
  const [color, setColor] = useState('#226a4c');
  const [scope, setScope] = useState('PERSONAL');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [archiving, setArchiving] = useState<string | null>(null);
  const reset = () => { setEditing(null); setName(''); setColor('#226a4c'); setScope('PERSONAL'); };
  const save = async () => {
    if (!name.trim() || !/^#[0-9a-fA-F]{6}$/.test(color)) { setError(t('dayPlanning.invalidTag')); return; }
    setSaving(true); setError('');
    try {
      await saveCalendarTag({ name: name.trim(), color, scope: scope as CalendarTag['scope'], teamId: scope === 'TEAM' ? team?.id ?? null : null }, editing?.id);
      reset(); await onChanged();
    } catch { setError(t('dayPlanning.saveError')); }
    finally { setSaving(false); }
  };
  const archive = async (id: string) => {
    setSaving(true); setError('');
    try { await archiveCalendarTag(id); setArchiving(null); if (editing?.id === id) reset(); await onChanged(); }
    catch { setError(t('dayPlanning.saveError')); }
    finally { setSaving(false); }
  };
  return <View style={{ gap: 12 }} testID="day-tag-manager">
    <Text variant="headlineSmall">{t('dayPlanning.manageTags')}</Text>
    {!!error && <Text accessibilityRole="alert">{error}</Text>}
    {tags.map((tag) => <View key={tag.id} style={{ gap: 4 }}>
      <Text variant="titleMedium">{tag.name} · {t(`dayPlanning.tagScopes.${tag.scope}`)}</Text>
      {tag.canEdit && <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
        <Button disabled={saving} accessibilityLabel={t('dayPlanning.editTag', { name: tag.name })}
          onPress={() => { setEditing(tag); setName(tag.name); setColor(tag.color); setScope(tag.scope); }}>{t('common.edit')}</Button>
        <Button disabled={saving} accessibilityLabel={t('dayPlanning.archiveTag', { name: tag.name })} onPress={() => setArchiving(tag.id)}>{t('dayPlanning.archive')}</Button>
      </View>}
      {archiving === tag.id && <><Text>{t('dayPlanning.archiveHint')}</Text><Button disabled={saving} onPress={() => void archive(tag.id)}>{t('dayPlanning.confirmArchive')}</Button><Button onPress={() => setArchiving(null)}>{t('common.cancel')}</Button></>}
    </View>)}
    <Text variant="titleMedium">{t(editing ? 'dayPlanning.editTagTitle' : 'dayPlanning.newTag')}</Text>
    <TextInput mode="outlined" label={t('dayPlanning.tagName')} accessibilityLabel={t('dayPlanning.tagName')} value={name} onChangeText={setName} disabled={saving} />
    <ChoicePicker label={t('dayPlanning.tagScope')} value={scope} disabled={saving || !!editing}
      options={[{ value: 'PERSONAL', label: t('dayPlanning.tagScopes.PERSONAL') }, ...(team?.role === 'admin' ? [{ value: 'TEAM', label: t('dayPlanning.tagScopes.TEAM') }] : [])]} onChange={setScope} />
    <TextInput mode="outlined" label={t('dayPlanning.tagColor')} accessibilityLabel={t('dayPlanning.tagColor')} value={color} onChangeText={setColor} autoCapitalize="none" disabled={saving} />
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
      {['#226a4c', '#325f99', '#86549e', '#a45132', '#706219'].map((value) => <Chip key={value} selected={color === value} onPress={() => setColor(value)}>{value}</Chip>)}
    </View>
    <Button mode="contained" loading={saving} disabled={saving} onPress={() => void save()}>{t('common.save')}</Button>
    {editing && <Button disabled={saving} onPress={reset}>{t('common.cancel')}</Button>}
    <Button disabled={saving} onPress={onClose}>{t('dayPlanning.backToCalendar')}</Button>
  </View>;
}
