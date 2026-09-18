import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';
import { Button, Card, Dialog, List, Portal, Text, TextInput, useTheme } from 'react-native-paper';

import {
  POINTS_ACCOUNTS,
  removeRule,
  setRule,
  type AllowanceRule,
  type PointsAccount,
} from '@/api/allowance';
import { formatMoney, fromMinorUnits, toMinorUnits } from '@/money';

type Draft = {
  pointsAccount: PointsAccount;
  minimumPoints: string;
  rateAmount: string;
  ratePerPoints: string;
};

type Props = {
  teamId: string;
  rules: AllowanceRule[];
  currency: string;
  busy: boolean;
  onChanged: (action: () => Promise<unknown>) => void;
  onInvalid: (why: string) => void;
};

export default function RateRules({ teamId, rules, currency, busy, onChanged, onInvalid }: Props) {
  const { t, i18n } = useTranslation();
  const theme = useTheme();
  const [draft, setDraft] = useState<Draft | null>(null);

  const money = (minor: number) => formatMoney(minor, currency, i18n.language);
  const ruleFor = (account: PointsAccount) => rules.find((rule) => rule.pointsAccount === account);

  const keep = () => {
    if (!draft) {
      return;
    }

    const rateAmount = toMinorUnits(draft.rateAmount);
    const perPoints = Number(draft.ratePerPoints) || 0;

    if (rateAmount === null || perPoints <= 0) {
      onInvalid(t('allowance.ruleInvalid'));
      return;
    }

    const body = {
      teamId,
      pointsAccount: draft.pointsAccount,
      minimumPoints: Number(draft.minimumPoints) || 0,
      rateAmount,
      ratePerPoints: perPoints,
    };

    setDraft(null);
    onChanged(() => setRule(body));
  };

  return (
    <Card mode="elevated" style={styles.card}>
      <Card.Title title={t('allowance.tabs.rules')} titleVariant="titleMedium" />
      {POINTS_ACCOUNTS.map((account) => {
        const rule = ruleFor(account);

        return (
          <View key={account}>
            <List.Item
              title={t(`allowance.accounts.${account}`)}
              description={
                rule
                  ? `${t('allowance.ruleExample', { points: rule.ratePerPoints, amount: money(rule.rateAmount) })} · ${t('allowance.minimumPoints')}: ${rule.minimumPoints}`
                  : t('allowance.noRulesYet')
              }
              descriptionNumberOfLines={3}
            />
            <View style={styles.actions}>
              <Button
                compact
                disabled={busy}
                onPress={() =>
                  setDraft({
                    pointsAccount: account,
                    minimumPoints: String(rule?.minimumPoints ?? 0),
                    rateAmount: fromMinorUnits(rule?.rateAmount ?? 0),
                    ratePerPoints: String(rule?.ratePerPoints ?? 1),
                  })
                }
              >
                {t(rule ? 'common.edit' : 'common.create')}
              </Button>
              {rule ? (
                <Button
                  compact
                  textColor={theme.colors.error}
                  disabled={busy}
                  onPress={() => onChanged(() => removeRule(teamId, account))}
                >
                  {t('allowance.removeRule')}
                </Button>
              ) : null}
            </View>
          </View>
        );
      })}

      <Portal>
        <Dialog visible={Boolean(draft)} onDismiss={() => setDraft(null)}>
          <Dialog.Title>{draft ? t(`allowance.accounts.${draft.pointsAccount}`) : ''}</Dialog.Title>
          <Dialog.Content style={styles.form}>
            <TextInput
              mode="outlined"
              label={t('allowance.minimumPoints')}
              accessibilityLabel={t('allowance.minimumPoints')}
              value={draft?.minimumPoints ?? ''}
              onChangeText={(value) =>
                setDraft((held) => held && { ...held, minimumPoints: value.replace(/\D/g, '') })
              }
              keyboardType="number-pad"
            />
            <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
              {t('allowance.minimumPointsHint')}
            </Text>
            <TextInput
              mode="outlined"
              label={t('allowance.rateAmount')}
              accessibilityLabel={t('allowance.rateAmount')}
              value={draft?.rateAmount ?? ''}
              onChangeText={(rateAmount) => setDraft((held) => held && { ...held, rateAmount })}
              keyboardType="decimal-pad"
            />
            <TextInput
              mode="outlined"
              label={t('allowance.ratePerPoints')}
              accessibilityLabel={t('allowance.ratePerPoints')}
              value={draft?.ratePerPoints ?? ''}
              onChangeText={(value) =>
                setDraft((held) => held && { ...held, ratePerPoints: value.replace(/\D/g, '') })
              }
              keyboardType="number-pad"
            />
            {draft ? (
              <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
                {t('allowance.ruleExample', {
                  points: draft.ratePerPoints || '0',
                  amount: money(toMinorUnits(draft.rateAmount) ?? 0),
                })}
              </Text>
            ) : null}
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setDraft(null)}>{t('common.cancel')}</Button>
            <Button disabled={busy} onPress={keep}>
              {t('common.save')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>
    </Card>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 16,
  },
  actions: {
    flexDirection: 'row',
    gap: 4,
    justifyContent: 'flex-end',
    paddingBottom: 8,
    paddingRight: 8,
  },
  form: {
    gap: 8,
  },
});
