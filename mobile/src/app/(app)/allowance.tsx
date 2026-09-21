import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  Chip,
  SegmentedButtons,
  Text,
  useTheme,
} from 'react-native-paper';

import {
  readGoals,
  readLedger,
  readRules,
  readWallet,
  readPayoutSummary,
  type PayoutSummary,
  readWeek,
  type AllowanceRule,
  type Goals,
  type Ledger,
  type Wallet,
  type Week,
} from '@/api/allowance';
import { listMembers, listTeams, type Member, type Team } from '@/api/teams';
import WeekPicker from '@/components/week-picker';
import { currentMonday } from '@/dates';
import MyMoney from '@/components/allowance/my-money';
import RateRules from '@/components/allowance/rate-rules';
import SettleMembers from '@/components/allowance/settle-members';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type Tab = 'settle' | 'rules' | 'mine';

export default function AllowanceScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();

  const [weekStart, setWeekStart] = useState(currentMonday);
  const [tab, setTab] = useState<Tab>('settle');
  const [teams, setTeams] = useState<Team[]>([]);
  const [teamId, setTeamId] = useState<string | null>(null);

  const [wallet, setWallet] = useState<Wallet | null>(null);
  const [goals, setGoals] = useState<Goals | null>(null);
  const [ledger, setLedger] = useState<Ledger | null>(null);
  const [week, setWeek] = useState<Week | null>(null);

  const [rules, setRules] = useState<AllowanceRule[]>([]);
  const [currency, setCurrency] = useState('PLN');
  const [members, setMembers] = useState<Member[]>([]);
  const [wallets, setWallets] = useState<Record<string, PayoutSummary>>({});
  const [weeks, setWeeks] = useState<Record<string, Week>>({});

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (wantedTeam?: string | null) => {
      try {
        const candidates = (await listTeams()).filter((one) => one.role === 'admin');
        const crews: Record<string, Member[]> = Object.fromEntries(
          await Promise.all(
            candidates.map(async (one) => [
              one.id,
              (await listMembers(one.id)).filter((member) => member.role !== 'admin'),
            ]),
          ),
        );
        const administered = candidates.filter((one) => crews[one.id].length > 0);
        setTeams(administered);

        if (!administered.length || tab === 'mine') {
          const [mine, wanted, book, thisWeek] = await Promise.all([
            readWallet(),
            readGoals(),
            readLedger(),
            readWeek(undefined, weekStart),
          ]);
          setWallet(mine);
          setGoals(wanted);
          setLedger(book);
          setWeek(thisWeek);
          setError(null);
          return;
        }

        const chosen =
          administered.find((one) => one.id === (wantedTeam ?? teamId))?.id ?? administered[0].id;
        setTeamId(chosen);

        const theirRules = await readRules(chosen);
        const children = crews[chosen];

        setRules(theirRules.rules);
        setCurrency(theirRules.currency);
        setMembers(children);
        const balances = await Promise.all(
          children.map(async (one) => ({
            userId: one.userId,
            week: await readWeek(one.userId, weekStart),
            wallet: await readPayoutSummary(one.userId),
          })),
        );
        setWeeks(Object.fromEntries(balances.map((one) => [one.userId, one.week])));
        setWallets(Object.fromEntries(balances.map((one) => [one.userId, one.wallet])));
        setError(null);
      } catch {
        setError(t('errors.generic'));
      }
    },
    [t, teamId, weekStart, tab],
  );

  useFocusEffect(
    useCallback(() => {
      void load().finally(() => setLoading(false));
    }, [load]),
  );

  const refresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const changed = (action: () => Promise<unknown>) => {
    setBusy(true);

    void action()
      .then(() => load())
      .catch(() => setError(t('errors.generic')))
      .finally(() => setBusy(false));
  };

  if (loading) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  const administers = teams.length > 0;

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
      <Banner
        visible={Boolean(error)}
        actions={[{ label: t('common.close'), onPress: () => setError(null) }]}
      >
        {error ?? ''}
      </Banner>

      <ScrollView
        contentContainerStyle={styles.page}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void refresh()} />}
      >
        {tab !== 'rules' ? (
          <WeekPicker value={weekStart} onChange={setWeekStart} disabled={busy} />
        ) : null}
        {administers ? (
          <>
            <SegmentedButtons
              value={tab}
              onValueChange={(next) => setTab(next as Tab)}
              buttons={[
                { value: 'mine', label: t('allowance.myWallet') },
                { value: 'settle', label: t('allowance.tabs.settle') },
                { value: 'rules', label: t('allowance.tabs.rules') },
              ]}
            />

            {teams.length > 1 && tab !== 'mine' ? (
              <View style={styles.chips}>
                {teams.map((one) => (
                  <Chip
                    key={one.id}
                    selected={teamId === one.id}
                    showSelectedCheck
                    onPress={() => void load(one.id)}
                  >
                    {one.name}
                  </Chip>
                ))}
              </View>
            ) : null}

            {tab === 'mine' ? (
              wallet && goals && week ? (
                <MyMoney
                  wallet={wallet}
                  goals={goals}
                  ledger={ledger}
                  week={week}
                  busy={busy}
                  onChanged={changed}
                />
              ) : null
            ) : tab === 'settle' ? (
              <SettleMembers
                key={teamId}
                members={members}
                wallets={wallets}
                weeks={weeks}
                currency={currency}
                busy={busy}
                onChanged={changed}
              />
            ) : (
              <RateRules
                teamId={teamId ?? ''}
                rules={rules}
                currency={currency}
                busy={busy}
                onChanged={changed}
                onInvalid={setError}
              />
            )}
          </>
        ) : wallet && goals && week ? (
          <MyMoney
            wallet={wallet}
            goals={goals}
            ledger={ledger}
            week={week}
            busy={busy}
            onChanged={changed}
          />
        ) : (
          <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('errors.generic')}
          </Text>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },
  centre: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
  },
  page: {
    gap: 16,
    padding: 16,
    paddingBottom: 32,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
});
