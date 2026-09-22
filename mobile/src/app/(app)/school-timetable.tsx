import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ScrollView, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  Button,
  Card,
  Chip,
  Dialog,
  Divider,
  HelperText,
  Portal,
  Text,
  TextInput,
  useTheme,
} from 'react-native-paper';

import {
  forgetSchoolAccount,
  importTimetable,
  linkStudents,
  mondayOf,
  readSchoolAccount,
  refreshStudents,
  saveSchoolAccount,
  weekAfter,
  type ImportSummary,
  type SchoolAccount,
} from '@/api/school-timetable';
import { listCalendarTags, type CalendarTag } from '@/api/day-planning';
import { listMembers, listTeams, type Member, type Team } from '@/api/teams';
import { useAuth } from '@/auth/auth-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type SignIn = { schoolId: string; login: string; password: string };

const BLANK: SignIn = { schoolId: '', login: '', password: '' };

export default function SchoolTimetableScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();
  const { manages } = useAuth();

  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [teams, setTeams] = useState<Team[]>([]);
  const [teamId, setTeamId] = useState<string | null>(null);
  const [members, setMembers] = useState<Member[]>([]);
  const [tags, setTags] = useState<CalendarTag[]>([]);
  const [account, setAccount] = useState<SchoolAccount | null>(null);
  const [signIn, setSignIn] = useState<SignIn>(BLANK);
  const [week, setWeek] = useState<string>(mondayOf(new Date()));
  const [summary, setSummary] = useState<ImportSummary | null>(null);
  const [forgetting, setForgetting] = useState(false);

  const load = useCallback(async (chosen?: string) => {
    setLoading(true);
    setError(null);

    try {
      const mine = (await listTeams()).filter((team) => team.role === 'admin');
      setTeams(mine);

      const team = chosen ?? mine[0]?.id ?? null;
      setTeamId(team);

      if (!team) {
        setAccount(null);
        return;
      }

      const [held, people, labels] = await Promise.all([
        readSchoolAccount(team),
        listMembers(team),
        listCalendarTags(team),
      ]);
      setMembers(people);
      setTags(labels);
      setAccount(held);
      setSignIn(held ? { schoolId: held.schoolId, login: held.login, password: '' } : BLANK);
    } catch {
      setError(t('schoolTimetable.loadError'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load])
  );

  const work = async (task: () => Promise<void>, failure: string) => {
    setBusy(true);
    setError(null);

    try {
      await task();
    } catch {
      setError(t(failure));
    } finally {
      setBusy(false);
    }
  };

  const keepSignIn = () =>
    work(async () => {
      if (!teamId) return;

      const kept = await saveSchoolAccount({
        teamId,
        schoolId: signIn.schoolId.trim(),
        login: signIn.login.trim(),
        ...(signIn.password ? { password: signIn.password } : {}),
      });

      setAccount(kept);
      setSignIn({ schoolId: kept.schoolId, login: kept.login, password: '' });
    }, 'schoolTimetable.saveError');

  const findStudents = () =>
    work(async () => {
      if (!teamId) return;

      setAccount(await refreshStudents(teamId, week));
    }, 'schoolTimetable.signInError');

  const pointAt = (studentId: string, userId: string | null) =>
    work(async () => {
      if (!teamId) return;

      setAccount(await linkStudents(teamId, { [studentId]: userId }));
    }, 'schoolTimetable.saveError');

  const chooseTag = (tagId: string | null) =>
    work(async () => {
      if (!teamId || !account) return;

      setAccount(
        await saveSchoolAccount({
          teamId,
          schoolId: account.schoolId,
          login: account.login,
          tagId,
        }),
      );
    }, 'schoolTimetable.saveError');

  const runImport = () =>
    work(async () => {
      if (!teamId) return;

      const done = await importTimetable(teamId, week);
      setSummary(done);
      setAccount(done.account);
    }, 'schoolTimetable.importError');

  const forget = () =>
    work(async () => {
      if (!teamId) return;

      await forgetSchoolAccount(teamId);
      setForgetting(false);
      setAccount(null);
      setSignIn(BLANK);
      setSummary(null);
    }, 'schoolTimetable.saveError');

  if (!manages) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <Text>{t('schoolTimetable.adminsOnly')}</Text>
      </View>
    );
  }

  if (loading) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  if (!teams.length) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <Text>{t('schoolTimetable.noTeam')}</Text>
      </View>
    );
  }

  const thisWeek = mondayOf(new Date());

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
      <Banner visible={Boolean(error)} actions={[{ label: t('common.close'), onPress: () => setError(null) }]}>
        {error ?? ''}
      </Banner>

      <ScrollView contentContainerStyle={styles.page}>
        {teams.length > 1 ? (
          <View style={styles.chips}>
            {teams.map((team) => (
              <Chip key={team.id} selected={team.id === teamId} showSelectedCheck={false} onPress={() => void load(team.id)}>
                {team.name}
              </Chip>
            ))}
          </View>
        ) : null}

        <Card mode="elevated" style={styles.card}>
          <Card.Title title={t('schoolTimetable.signInTitle')} titleVariant="titleMedium" subtitle={t('schoolTimetable.signInHint')} subtitleNumberOfLines={3} />
          <Card.Content style={styles.section}>
            <TextInput
              testID="school-timetable-school"
              label={t('schoolTimetable.school')}
              value={signIn.schoolId}
              autoCapitalize="none"
              onChangeText={(schoolId) => setSignIn((current) => ({ ...current, schoolId }))}
            />
            <HelperText type="info">{t('schoolTimetable.schoolHint')}</HelperText>

            <TextInput
              testID="school-timetable-login"
              label={t('schoolTimetable.login')}
              value={signIn.login}
              autoCapitalize="none"
              onChangeText={(login) => setSignIn((current) => ({ ...current, login }))}
            />

            <TextInput
              testID="school-timetable-password"
              label={account ? t('schoolTimetable.passwordKept') : t('schoolTimetable.password')}
              value={signIn.password}
              secureTextEntry
              autoCapitalize="none"
              onChangeText={(password) => setSignIn((current) => ({ ...current, password }))}
            />
            <HelperText type="info">{t('schoolTimetable.passwordHint')}</HelperText>

            <Button testID="school-timetable-save" mode="contained" disabled={busy} onPress={() => void keepSignIn()}>
              {t('common.save')}
            </Button>
            {account ? (
              <Button testID="school-timetable-forget" textColor={theme.colors.error} disabled={busy} onPress={() => setForgetting(true)}>
                {t('schoolTimetable.forget')}
              </Button>
            ) : null}
          </Card.Content>
        </Card>

        {account ? (
          <Card mode="elevated" style={styles.card}>
            <Card.Title title={t('schoolTimetable.studentsTitle')} titleVariant="titleMedium" subtitle={t('schoolTimetable.studentsHint')} subtitleNumberOfLines={3} />
            <Card.Content style={styles.section}>
              <Button testID="school-timetable-refresh" icon="account-search-outline" disabled={busy} onPress={() => void findStudents()}>
                {t('schoolTimetable.findStudents')}
              </Button>

              {account.students.length === 0 ? <Text>{t('schoolTimetable.noStudents')}</Text> : null}

              {account.students.map((student) => (
                <View key={student.studentId} style={styles.section}>
                  <Divider />
                  <Text variant="titleSmall">{student.studentName}</Text>
                  <View style={styles.chips}>
                    {members.map((member) => (
                      <Chip
                        key={member.userId}
                        selected={member.userId === student.userId}
                        showSelectedCheck={false}
                        disabled={busy}
                        onPress={() => void pointAt(student.studentId, member.userId === student.userId ? null : member.userId)}
                      >
                        {member.givenName || member.userName}
                      </Chip>
                    ))}
                  </View>
                </View>
              ))}
            </Card.Content>
          </Card>
        ) : null}

        {account ? (
          <Card mode="elevated" style={styles.card}>
            <Card.Title title={t('schoolTimetable.importTitle')} titleVariant="titleMedium" subtitle={t('schoolTimetable.importHint')} subtitleNumberOfLines={3} />
            <Card.Content style={styles.section}>
              <Text variant="titleSmall">{t('schoolTimetable.tagTitle')}</Text>
              <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
                {t('schoolTimetable.tagHint')}
              </Text>
              <View style={styles.chips}>
                <Chip selected={!account.tagId} showSelectedCheck={false} disabled={busy} onPress={() => void chooseTag(null)}>
                  {t('schoolTimetable.noTag')}
                </Chip>
                {tags.map((tag) => (
                  <Chip
                    key={tag.id}
                    selected={tag.id === account.tagId}
                    showSelectedCheck={false}
                    disabled={busy}
                    onPress={() => void chooseTag(tag.id === account.tagId ? null : tag.id)}
                  >
                    {tag.name}
                  </Chip>
                ))}
              </View>

              <Divider />

              <View style={styles.chips}>
                <Chip selected={week === thisWeek} showSelectedCheck={false} onPress={() => setWeek(thisWeek)}>
                  {t('schoolTimetable.thisWeek')}
                </Chip>
                <Chip selected={week === weekAfter(thisWeek)} showSelectedCheck={false} onPress={() => setWeek(weekAfter(thisWeek))}>
                  {t('schoolTimetable.nextWeek')}
                </Chip>
              </View>

              <Button testID="school-timetable-import" mode="contained" icon="calendar-import" disabled={busy} onPress={() => void runImport()}>
                {t('schoolTimetable.import')}
              </Button>

              {busy ? <ActivityIndicator /> : null}

              {summary ? (
                <>
                  <Text testID="school-timetable-summary">
                    {t('schoolTimetable.summary', {
                      from: summary.weekStart,
                      to: summary.weekEnd,
                      added: summary.added,
                      updated: summary.updated,
                      removed: summary.removed,
                      unchanged: summary.unchanged,
                    })}
                  </Text>
                  <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
                    {t('schoolTimetable.summaryDetails', {
                      substitutions: summary.substitutions,
                      unlinked: summary.skipped.unlinked,
                      cancelled: summary.skipped.cancelled,
                    })}
                  </Text>
                </>
              ) : null}

              {account.importedAt ? (
                <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
                  {t('schoolTimetable.lastImport', { at: new Date(account.importedAt).toLocaleString() })}
                </Text>
              ) : null}
            </Card.Content>
          </Card>
        ) : null}
      </ScrollView>

      <Portal>
        <Dialog visible={forgetting} onDismiss={() => setForgetting(false)}>
          <Dialog.Title>{t('schoolTimetable.forget')}</Dialog.Title>
          <Dialog.Content>
            <Text>{t('schoolTimetable.forgetHint')}</Text>
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setForgetting(false)}>{t('common.cancel')}</Button>
            <Button testID="school-timetable-forget-confirm" textColor={theme.colors.error} onPress={() => void forget()}>
              {t('schoolTimetable.forget')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1 },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  page: { padding: 16, gap: 16 },
  card: { borderRadius: 16 },
  section: { gap: 12 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
});
