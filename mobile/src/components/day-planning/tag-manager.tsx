import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Text, TextInput } from 'react-native-paper';
import { archiveCalendarTag, saveCalendarTag, type CalendarTag } from '@/api/day-planning';
import type { Team } from '@/api/teams';
import ChoicePicker from '@/components/action-plans/choice-picker';
import TagColors, { TagColorDot } from '@/components/day-planning/tag-colors';

export default function TagManager({ tags, teams, onChanged, onClose }: {
  tags: CalendarTag[]; teams: Team[]; onChanged: () => Promise<void>; onClose: () => void;
}) {
  const { t } = useTranslation();
  const [editing, setEditing] = useState<CalendarTag | null>(null);
  const [name, setName] = useState('');
  const [color, setColor] = useState('#226a4c');
  const [scope, setScope] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [archiving, setArchiving] = useState<string | null>(null);
  const pending = useRef(false);
  const reset = () => { setEditing(null); setName(''); setColor('#226a4c'); setScope(''); };
  const teamName = (id: string | null) => teams.find((team) => team.id === id)?.name;
  const scopeLabel = (tag: Pick<CalendarTag, 'scope' | 'teamId'>) => tag.scope === 'TEAM' && teams.length > 1 && teamName(tag.teamId) ? `${t('dayPlanning.tagScopes.TEAM')}: ${teamName(tag.teamId)}` : t(`dayPlanning.tagScopes.${tag.scope}`);
  const scopes = [...teams.filter((team) => team.role === 'admin').map((team) => team.id), ...(editing?.scope === 'TEAM' && editing.teamId && !teams.some((team) => team.id === editing.teamId && team.role === 'admin') ? [editing.teamId] : [])];
  const save = async () => {
    if (pending.current) return;
    if (!name.trim() || name.trim().length > 60 || !/^#[0-9a-fA-F]{6}$/.test(color)) { setError(t('dayPlanning.invalidTag')); return; }
    pending.current = true; setSaving(true); setError('');
    try {
      await saveCalendarTag({ name: name.trim(), color, scope: scope ? 'TEAM' : 'PERSONAL', teamId: scope || null }, editing?.id);
      reset(); await onChanged();
    } catch { setError(t('dayPlanning.saveError')); }
    finally { pending.current = false; setSaving(false); }
  };
  const archive = async (id: string) => {
    if (pending.current) return;
    pending.current = true; setSaving(true); setError('');
    try { await archiveCalendarTag(id); setArchiving(null); if (editing?.id === id) reset(); await onChanged(); }
    catch { setError(t('dayPlanning.saveError')); }
    finally { pending.current = false; setSaving(false); }
  };
  return <View style={{ gap: 12 }} testID="day-tag-manager">
    <Text variant="headlineSmall">{t('dayPlanning.manageTags')}</Text>
    {!!error && <Text accessibilityRole="alert">{error}</Text>}
    {tags.map((tag) => <View key={tag.id} style={{ gap: 4 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}><TagColorDot color={tag.color} /><Text variant="titleMedium" style={{ flex: 1 }}>{tag.name} · {scopeLabel(tag)}</Text></View>
      {tag.canEdit && <View style={{ flexDirection: 'row', flexWrap: 'wrap' }}>
        <Button disabled={saving} accessibilityLabel={t('dayPlanning.editTag', { name: tag.name })}
          onPress={() => { setEditing(tag); setName(tag.name); setColor(tag.color); setScope(tag.scope === 'TEAM' ? tag.teamId ?? '' : ''); }}>{t('common.edit')}</Button>
        <Button disabled={saving} accessibilityLabel={t('dayPlanning.archiveTag', { name: tag.name })} onPress={() => setArchiving(tag.id)}>{t('dayPlanning.archive')}</Button>
      </View>}
      {archiving === tag.id && <><Text>{t('dayPlanning.archiveHint')}</Text><Button disabled={saving} onPress={() => void archive(tag.id)}>{t('dayPlanning.confirmArchive')}</Button><Button onPress={() => setArchiving(null)}>{t('common.cancel')}</Button></>}
    </View>)}
    <Text variant="titleMedium">{t(editing ? 'dayPlanning.editTagTitle' : 'dayPlanning.newTag')}</Text>
    <TextInput mode="outlined" label={t('dayPlanning.tagName')} accessibilityLabel={t('dayPlanning.tagName')} value={name} onChangeText={setName} maxLength={60} disabled={saving} />
    {!!scopes.length && <ChoicePicker label={t('dayPlanning.tagScope')} value={scope} disabled={saving || !!editing}
      options={[{ value: '', label: t('dayPlanning.tagScopes.PERSONAL') }, ...scopes.map((id) => ({ value: id, label: scopeLabel({ scope: 'TEAM', teamId: id }) }))]} onChange={setScope} />}
    <TagColors value={color} customColor={editing?.color} onChange={setColor} disabled={saving} />
    <Button mode="contained" loading={saving} disabled={saving} onPress={() => void save()}>{t('common.save')}</Button>
    {editing && <Button disabled={saving} onPress={reset}>{t('common.cancel')}</Button>}
    <Button disabled={saving} onPress={onClose}>{t('dayPlanning.backToCalendar')}</Button>
  </View>;
}
