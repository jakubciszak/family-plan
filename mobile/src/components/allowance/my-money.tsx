import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';
import {
  Button,
  Card,
  Dialog,
  Divider,
  HelperText,
  List,
  Portal,
  Text,
  TextInput,
  useTheme,
} from 'react-native-paper';

import {
  addExpense,
  addIncome,
  confirmPayout,
  giveUpGoal,
  OUTGOING,
  planGoal,
  putAside,
  spendGoal,
  takeBack,
  type Goals,
  type Ledger,
  type Wallet,
  type Week,
} from '@/api/allowance';
import { dayString, isDay } from '@/dates';
import GoalProgress from './goal-progress';
import WeekSummary from './week-summary';
import MoneyTile from '@/components/money-tile';
import { formatMoney, toMinorUnits } from '@/money';

type NewBooking = { kind: 'income' | 'expense' };

type Props = {
  wallet: Wallet;
  goals: Goals;
  ledger: Ledger | null;
  week: Week;
  busy: boolean;
  onChanged: (action: () => Promise<unknown>) => void;
};

export default function MyMoney({ wallet, goals, ledger, week, busy, onChanged }: Props) {
  const { t, i18n } = useTranslation();
  const theme = useTheme();

  const [booking, setBooking] = useState<NewBooking | null>(null);
  const [amount, setAmount] = useState('');
  const [bookedOn, setBookedOn] = useState(() => dayString(new Date()));
  const [wantedBy, setWantedBy] = useState('');
  const [description, setDescription] = useState('');
  const [planning, setPlanning] = useState(false);
  const [goalName, setGoalName] = useState('');
  const [goalTarget, setGoalTarget] = useState('');
  const [moving, setMoving] = useState<{ id: string; way: 'aside' | 'back' } | null>(null);
  const [moveAmount, setMoveAmount] = useState('');

  const currency = wallet.currency;
  const money = (minor: number) => formatMoney(minor, currency, i18n.language);

  const bookIt = () => {
    const minor = toMinorUnits(amount);

    if (
      !booking ||
      minor === null ||
      minor <= 0 ||
      !description.trim() ||
      !isDay(bookedOn) ||
      bookedOn > dayString(new Date())
    ) {
      return;
    }

    const kind = booking.kind;
    const said = description.trim();
    setBooking(null);
    setAmount('');
    setDescription('');

    onChanged(() =>
      kind === 'income' ? addIncome(minor, said, bookedOn) : addExpense(minor, said, bookedOn),
    );
  };

  return (
    <>
      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('allowance.myWallet')} titleVariant="titleMedium" />
        <Card.Content style={styles.tiles}>
          <MoneyTile
            label={t('allowance.pending')}
            hint={t('allowance.pendingHint')}
            amount={wallet.pending}
            currency={currency}
            language={i18n.language}
            tone="secondary"
          />
          <MoneyTile
            label={t('allowance.available')}
            hint={t('allowance.availableHint')}
            amount={wallet.available}
            currency={currency}
            language={i18n.language}
          />
          <MoneyTile
            label={t('allowance.putAside')}
            hint={t('allowance.putAsideHint')}
            amount={wallet.putAside}
            currency={currency}
            language={i18n.language}
            tone="tertiary"
          />
        </Card.Content>

        {wallet.awaitingConfirmation.map((payout) => (
          <View key={payout.id}>
            <List.Item
              title={t('allowance.payoutOffered')}
              description={`${money(payout.amount)}${payout.note ? ` · ${payout.note}` : ''}`}
            />
            <Card.Actions>
              <Button
                mode="contained"
                disabled={busy}
                onPress={() => onChanged(() => confirmPayout(payout.id))}
              >
                {t('allowance.confirmPayout')}
              </Button>
            </Card.Actions>
          </View>
        ))}
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('allowance.myWeeks')} titleVariant="titleMedium" />
        <Card.Content style={styles.section}>
          <WeekSummary week={week} />
        </Card.Content>
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('allowance.myGoals')} titleVariant="titleMedium" />
        <Card.Content style={styles.section}>
          <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
            {t('allowance.weeklyPace', { amount: money(goals.weeklyPace) })}
          </Text>

          {goals.goals.length ? (
            goals.goals.map((goal) => (
              <View key={goal.id} style={styles.goal}>
                <GoalProgress goal={goal} currency={currency} />
                <View style={styles.chips}>
                  <Button
                    compact
                    disabled={busy}
                    onPress={() => {
                      setMoving({ id: goal.id, way: 'aside' });
                      setMoveAmount('');
                    }}
                  >
                    {t('allowance.putAsideAction')}
                  </Button>
                  <Button
                    compact
                    disabled={busy || goal.saved === 0}
                    onPress={() => {
                      setMoving({ id: goal.id, way: 'back' });
                      setMoveAmount('');
                    }}
                  >
                    {t('allowance.takeBackAction')}
                  </Button>
                  <Button
                    compact
                    disabled={busy || !goal.reached}
                    onPress={() => onChanged(() => spendGoal(goal.id, goal.saved, goal.name))}
                  >
                    {t('allowance.spendGoal')}
                  </Button>
                  <Button
                    compact
                    textColor={theme.colors.error}
                    disabled={busy}
                    onPress={() => onChanged(() => giveUpGoal(goal.id))}
                  >
                    {t('allowance.giveUpGoal')}
                  </Button>
                </View>
              </View>
            ))
          ) : (
            <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
              {t('allowance.noGoalsYet')}
            </Text>
          )}
        </Card.Content>
        <Card.Actions>
          <Button mode="contained-tonal" icon="flag-outline" onPress={() => setPlanning(true)}>
            {t('allowance.planGoal')}
          </Button>
        </Card.Actions>
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('allowance.myMoney')} titleVariant="titleMedium" />
        <Card.Content style={styles.chips}>
          <Button
            mode="contained-tonal"
            icon="plus"
            onPress={() => {
              setBooking({ kind: 'income' });
              setBookedOn(dayString(new Date()));
              setAmount('');
              setDescription('');
            }}
          >
            {t('allowance.addIncome')}
          </Button>
          <Button
            mode="contained-tonal"
            icon="minus"
            onPress={() => {
              setBooking({ kind: 'expense' });
              setBookedOn(dayString(new Date()));
              setAmount('');
              setDescription('');
            }}
          >
            {t('allowance.addExpense')}
          </Button>
        </Card.Content>

        <Divider />

        {ledger?.bookings.length ? (
          ledger.bookings.map((entry) => {
            const out = OUTGOING.includes(entry.type);
            const context = (Array.isArray(entry.context) ? {} : entry.context) ?? {};

            return (
              <List.Item
                key={entry.id}
                title={
                  entry.description ||
                  t(`allowance.bookingTitles.${entry.type}`, {
                    goal: context.goal ?? '',
                    week: context.week ? context.week.slice(0, 10) : '',
                  })
                }
                description={
                  entry.description
                    ? `${entry.bookedAt.slice(0, 10)} · ${t(`allowance.bookings.${entry.type}`)}`
                    : entry.bookedAt.slice(0, 10)
                }
                right={() => (
                  <Text
                    variant="titleSmall"
                    style={[
                      styles.entryAmount,
                      { color: out ? theme.colors.error : theme.colors.primary },
                    ]}
                  >
                    {out ? '−' : '+'}
                    {money(entry.amount)}
                  </Text>
                )}
              />
            );
          })
        ) : (
          <List.Item title={t('allowance.ledgerEmpty')} titleStyle={styles.faded} />
        )}
      </Card>

      <Portal>
        <Dialog
          testID="booking-dialog"
          visible={Boolean(booking)}
          onDismiss={() => setBooking(null)}
        >
          <Dialog.Title>
            {t(booking?.kind === 'income' ? 'allowance.addIncome' : 'allowance.addExpense')}
          </Dialog.Title>
          <Dialog.Content style={styles.section}>
            <TextInput
              mode="outlined"
              label={t('allowance.amount')}
              accessibilityLabel={t('allowance.amount')}
              value={amount}
              onChangeText={setAmount}
              keyboardType="decimal-pad"
            />
            <HelperText type="error" visible={Boolean(amount) && toMinorUnits(amount) === null}>
              {t('allowance.amountInvalid')}
            </HelperText>
            <TextInput
              mode="outlined"
              label={t(
                booking?.kind === 'income'
                  ? 'allowance.incomeDescription'
                  : 'allowance.expenseDescription',
              )}
              accessibilityLabel={t(
                booking?.kind === 'income'
                  ? 'allowance.incomeDescription'
                  : 'allowance.expenseDescription',
              )}
              value={description}
              onChangeText={setDescription}
            />
            <TextInput
              mode="outlined"
              label={t('allowance.on')}
              accessibilityLabel={t('allowance.on')}
              placeholder="YYYY-MM-DD"
              value={bookedOn}
              onChangeText={setBookedOn}
            />
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setBooking(null)}>{t('common.cancel')}</Button>
            <Button
              disabled={
                busy ||
                (toMinorUnits(amount) ?? 0) <= 0 ||
                !description.trim() ||
                !isDay(bookedOn) ||
                bookedOn > dayString(new Date())
              }
              onPress={bookIt}
            >
              {t('common.save')}
            </Button>
          </Dialog.Actions>
        </Dialog>

        <Dialog testID="goal-dialog" visible={planning} onDismiss={() => setPlanning(false)}>
          <Dialog.Title>{t('allowance.planGoal')}</Dialog.Title>
          <Dialog.Content style={styles.section}>
            <TextInput
              mode="outlined"
              label={t('allowance.goalName')}
              accessibilityLabel={t('allowance.goalName')}
              value={goalName}
              onChangeText={setGoalName}
            />
            <TextInput
              mode="outlined"
              label={t('allowance.goalTarget')}
              accessibilityLabel={t('allowance.goalTarget')}
              value={goalTarget}
              onChangeText={setGoalTarget}
              keyboardType="decimal-pad"
            />
            <TextInput
              mode="outlined"
              label={t('allowance.goalWantedBy')}
              accessibilityLabel={t('allowance.goalWantedBy')}
              placeholder="YYYY-MM-DD"
              value={wantedBy}
              onChangeText={setWantedBy}
            />
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setPlanning(false)}>{t('common.cancel')}</Button>
            <Button
              disabled={
                busy ||
                !goalName.trim() ||
                (toMinorUnits(goalTarget) ?? 0) <= 0 ||
                Boolean(wantedBy && !isDay(wantedBy))
              }
              onPress={() => {
                const target = toMinorUnits(goalTarget);
                const name = goalName.trim();
                setPlanning(false);
                setGoalName('');
                setGoalTarget('');
                setWantedBy('');

                if (target !== null) {
                  onChanged(() => planGoal(name, target, wantedBy || undefined));
                }
              }}
            >
              {t('common.save')}
            </Button>
          </Dialog.Actions>
        </Dialog>

        <Dialog
          testID="goal-move-dialog"
          visible={Boolean(moving)}
          onDismiss={() => setMoving(null)}
        >
          <Dialog.Title>
            {t(moving?.way === 'aside' ? 'allowance.putAsideAction' : 'allowance.takeBackAction')}
          </Dialog.Title>
          <Dialog.Content>
            <TextInput
              mode="outlined"
              label={t('allowance.amount')}
              accessibilityLabel={t('allowance.amount')}
              value={moveAmount}
              onChangeText={setMoveAmount}
              keyboardType="decimal-pad"
            />
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setMoving(null)}>{t('common.cancel')}</Button>
            <Button
              disabled={busy || (toMinorUnits(moveAmount) ?? 0) <= 0}
              onPress={() => {
                const minor = toMinorUnits(moveAmount);
                const pair = moving;
                setMoving(null);

                if (pair && minor !== null) {
                  onChanged(() =>
                    pair.way === 'aside' ? putAside(pair.id, minor) : takeBack(pair.id, minor),
                  );
                }
              }}
            >
              {t('common.save')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>
    </>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 16,
  },
  section: {
    gap: 8,
  },
  tiles: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  chips: {
    alignItems: 'center',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  goal: {
    gap: 6,
    paddingVertical: 8,
  },
  bar: {
    borderRadius: 4,
    height: 8,
  },
  entryAmount: {
    alignSelf: 'center',
  },
  faded: {
    opacity: 0.7,
  },
});
