import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Banner, Card, Chip, List, Text } from 'react-native-paper';

import { readStandings, type Standing } from '@/api/calendar';
import { listMembers, listTeams, type Member, type Team } from '@/api/teams';
import { currentMonday } from '@/dates';
import WeekPicker from './week-picker';

export default function HomeTeamSection({ kind }: { kind: 'standings' | 'members' }) {
  const { t } = useTranslation();
  const [teams, setTeams] = useState<Team[]>([]);
  const [chosen, setChosen] = useState<string | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [standings, setStandings] = useState<Standing[]>([]);
  const [weekStart, setWeekStart] = useState(currentMonday);
  const [error, setError] = useState(false);

  const load = useCallback(async () => {
    try {
      const mine = await listTeams();
      const available = kind === 'members' ? mine.filter((team) => team.role === 'admin') : mine;
      setTeams(available);
      const selected = available.find((team) => team.id === chosen) ?? available[0];
      if (!selected) return;
      if (kind === 'members') {
        setMembers((await listMembers(selected.id)).filter((member) => member.role !== 'admin'));
      } else {
        setStandings((await readStandings(selected.id, weekStart)).standings ?? []);
      }
      setError(false);
    } catch { setError(true); }
  }, [kind, chosen, weekStart]);

  useFocusEffect(useCallback(() => { void load(); }, [load]));

  return (
    <Card testID={`home-${kind}`}>
      <Card.Title title={t(`personalise.places.home.${kind}`)} />
      <Card.Content>
        <Banner visible={error} actions={[{ label: t('common.retry'), onPress: () => void load() }]}>{error ? t('errors.generic') : ''}</Banner>
        {teams.length > 1 ? <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 4 }}>{teams.map((team) => <Chip key={team.id} selected={team.id === (chosen ?? teams[0]?.id)} onPress={() => setChosen(team.id)}>{team.name}</Chip>)}</View> : null}
        {kind === 'standings' ? <>
          <WeekPicker value={weekStart} onChange={setWeekStart} />
          {standings.length ? standings.map((entry, index) => <List.Item key={entry.userId} title={`${index + 1}. ${entry.name}`} description={`${entry.total} ${t('tasks.points')}`} />) : <Text>{t('leaderboard.empty')}</Text>}
        </> : members.length ? members.map((member) => <List.Item key={member.id} title={member.face?.nickname || member.givenName || member.userName} right={() => <List.Icon icon="chevron-right" />} onPress={() => router.push({ pathname: '/member', params: { id: member.userId, name: member.face?.nickname || member.givenName || member.userName } })} />) : <Text>{t('allowance.noMembersYet')}</Text>}
      </Card.Content>
    </Card>
  );
}
