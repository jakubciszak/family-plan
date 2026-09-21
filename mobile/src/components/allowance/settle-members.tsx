import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Card, Chip, Dialog, Portal, Text, TextInput } from 'react-native-paper';
import {
  closeWeek,
  offerPayout,
  reopenWeek,
  type PayoutSummary,
  type Week,
} from '@/api/allowance';
import type { Member } from '@/api/teams';
import MoneyTile from '@/components/money-tile';
import { TaskButton as Button } from '@/components/tasks/task-ui';
import { formatMoney, toMinorUnits } from '@/money';
import WeekSummary from './week-summary';

type Props = {
  members: Member[];
  weeks: Record<string, Week>;
  wallets: Record<string, PayoutSummary>;
  currency: string;
  busy: boolean;
  onChanged: (action: () => Promise<unknown>) => void;
};

export default function SettleMembers({
  members,
  weeks,
  wallets,
  currency,
  busy,
  onChanged,
}: Props) {
  const { t, i18n } = useTranslation();
  const [selected, setSelected] = useState<string | null>(null);
  const [paying, setPaying] = useState<string | null>(null);
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const member = members.find((one) => one.userId === selected) ?? members[0];
  const week = member && weeks[member.userId];
  const wallet = member && wallets[member.userId];
  const free = wallet
    ? wallet.pending - wallet.awaitingConfirmation.reduce((sum, payout) => sum + payout.amount, 0)
    : 0;
  const money = (minor: number) => formatMoney(minor, wallet?.currency ?? currency, i18n.language);

  return (
    <View style={{ gap: 24 }}>
      <Text variant="titleMedium">{t('allowance.whoToSettle')}</Text>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
        {members.map((one) => (
          <Chip
            key={one.userId}
            selected={member?.userId === one.userId}
            onPress={() => setSelected(one.userId)}
          >
            {one.face?.nickname || one.userName}
          </Chip>
        ))}
        {!members.length ? <Text>{t('allowance.noMembersYet')}</Text> : null}
      </View>
      {week ? (
        <Card style={{ borderRadius: 16 }}>
          <Card.Title title={t('allowance.memberWeeks')} titleVariant="titleMedium" />
          <Card.Content>
            <WeekSummary week={week} />
          </Card.Content>
          <Card.Actions>
            {week.closure ? (
              <Button
                disabled={busy}
                onPress={() => onChanged(() => reopenWeek(member.userId, week.weekStart))}
              >
                {t('allowance.reopenWeek')}
              </Button>
            ) : (
              <Button
                mode="contained-tonal"
                disabled={busy || !week.isOver}
                onPress={() => onChanged(() => closeWeek(member.userId, week.weekStart))}
              >
                {t('allowance.closeWeek')}
              </Button>
            )}
          </Card.Actions>
          {!week.isOver && !week.closure ? (
            <Card.Content>
              <Text>{t('allowance.weekNotOverYet')}</Text>
            </Card.Content>
          ) : null}
        </Card>
      ) : null}
      {wallet ? (
        <Card style={{ borderRadius: 16 }}>
          <Card.Title title={t('allowance.memberPayouts')} titleVariant="titleMedium" />
          <Card.Content style={{ gap: 12 }}>
            {(['pending', 'paid'] as const).map((kind) => (
              <MoneyTile
                key={kind}
                label={t(`allowance.${kind}`)}
                hint={t(`allowance.${kind}Hint`)}
                amount={wallet[kind]}
                currency={wallet.currency}
                language={i18n.language}
              />
            ))}
            {wallet.awaitingConfirmation.map((payout) => (
              <Text key={payout.id}>
                {t('allowance.payoutOffered')}: {money(payout.amount)}
                {payout.note ? ` · ${payout.note}` : ''}
              </Text>
            ))}
            <Text>{t('allowance.freeToPayOut', { amount: money(free) })}</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              <Button
                mode="outlined"
                disabled={busy || free <= 0}
                onPress={() => {
                  setPaying(member.userId);
                  setAmount('');
                  setNote('');
                }}
              >
                {t('allowance.payPart')}
              </Button>
              <Button
                mode="contained"
                disabled={busy || free <= 0}
                onPress={() => onChanged(() => offerPayout(member.userId, free))}
              >
                {t('allowance.payEverything')}
              </Button>
            </View>
          </Card.Content>
        </Card>
      ) : null}
      <Portal>
        <Dialog visible={Boolean(paying)} onDismiss={() => setPaying(null)}>
          <Dialog.Title>{t('allowance.payPart')}</Dialog.Title>
          <Dialog.Content style={{ gap: 12 }}>
            <Text>{t('allowance.freeToPayOut', { amount: money(free) })}</Text>
            <TextInput
              mode="outlined"
              label={t('allowance.amount')}
              accessibilityLabel={t('allowance.amount')}
              value={amount}
              onChangeText={setAmount}
              keyboardType="decimal-pad"
            />
            <TextInput
              mode="outlined"
              label={t('allowance.payoutNote')}
              accessibilityLabel={t('allowance.payoutNote')}
              value={note}
              onChangeText={setNote}
            />
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setPaying(null)}>{t('common.cancel')}</Button>
            <Button
              disabled={
                busy || (toMinorUnits(amount) ?? 0) <= 0 || (toMinorUnits(amount) ?? 0) > free
              }
              onPress={() => {
                const minor = toMinorUnits(amount);
                const who = paying;
                setPaying(null);
                if (who && minor !== null) onChanged(() => offerPayout(who, minor, note.trim()));
              }}
            >
              {t('common.save')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>
    </View>
  );
}
