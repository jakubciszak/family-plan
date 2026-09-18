import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ScrollView, StyleSheet } from 'react-native';
import { Banner, Card, List, TextInput, Text } from 'react-native-paper';

import { readCalendar } from '@/api/calendar';
import apiClient, { ApiError } from '@/api/client';
import { useAuth } from '@/auth/auth-context';
import Avatar from '@/components/avatar';
import { TaskButton as Button } from '@/components/tasks/task-ui';
import { currentMonday } from '@/dates';
import { usePersonalisation } from '@/personalisation/personalisation-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';

export default function AccountScreen() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { own } = usePersonalisation();
  const ground = useScreenBackground();
  const [points, setPoints] = useState<number | null>(null);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [saving, setSaving] = useState(false);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      void readCalendar(currentMonday())
        .then((week) => {
          if (active) setPoints(week.total);
        })
        .catch(() => {
          if (active) setError(t('errors.generic'));
        });
      return () => {
        active = false;
      };
    }, [t]),
  );

  const changePassword = async () => {
    setSuccess(false);
    if (newPassword !== confirmPassword) {
      setError(t('account.passwordsDoNotMatch'));
      return;
    }
    if (newPassword.length < 8) {
      setError(t('account.passwordTooShort'));
      return;
    }
    setSaving(true);
    setError('');
    try {
      await apiClient.post('/api/auth/change-password', { currentPassword, newPassword });
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
      setSuccess(true);
    } catch (failure) {
      setError(
        t(
          failure instanceof ApiError && failure.status === 400
            ? 'account.currentPasswordIncorrect'
            : 'account.passwordChangeError',
        ),
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <ScrollView
      style={{ backgroundColor: ground }}
      contentContainerStyle={styles.page}
      keyboardShouldPersistTaps="handled"
    >
      <Card style={styles.card}>
        <Card.Title
          title={own?.nickname || user?.name}
          titleVariant="titleLarge"
          subtitle={user?.email}
          left={() => <Avatar avatar={own?.avatar} name={user?.name} size={48} />}
        />
        <List.Item title={t('account.name')} description={user?.name} />
        <List.Item title={t('account.email')} description={user?.email} />
        <List.Item
          title={t('account.role')}
          description={t(user?.role === 'ROLE_ADMIN' ? 'account.roleAdmin' : 'account.roleUser')}
        />
        <List.Item
          title={t('account.pointsThisWeek')}
          description={points === null ? '—' : String(points)}
        />
      </Card>
      <Card style={styles.card}>
        <Card.Title title={t('account.changePassword')} titleVariant="titleMedium" />
        <Card.Content style={styles.form}>
          <Banner
            visible={Boolean(error)}
            actions={[{ label: t('common.close'), onPress: () => setError('') }]}
          >
            {error}
          </Banner>
          {success ? <Text accessibilityRole="alert">{t('account.passwordChanged')}</Text> : null}
          <TextInput
            mode="outlined"
            label={t('account.currentPassword')}
            accessibilityLabel={t('account.currentPassword')}
            value={currentPassword}
            onChangeText={setCurrentPassword}
            secureTextEntry
            autoComplete="current-password"
          />
          <TextInput
            mode="outlined"
            label={t('account.newPassword')}
            accessibilityLabel={t('account.newPassword')}
            value={newPassword}
            onChangeText={setNewPassword}
            secureTextEntry
            autoComplete="new-password"
          />
          <TextInput
            mode="outlined"
            label={t('account.confirmPassword')}
            accessibilityLabel={t('account.confirmPassword')}
            value={confirmPassword}
            onChangeText={setConfirmPassword}
            secureTextEntry
            autoComplete="new-password"
          />
          <Button
            mode="contained"
            loading={saving}
            disabled={saving || !currentPassword || !newPassword || !confirmPassword}
            onPress={() => void changePassword()}
          >
            {t('account.changePassword')}
          </Button>
        </Card.Content>
      </Card>

    </ScrollView>
  );
}

const styles = StyleSheet.create({
  page: { gap: 24, padding: 16, paddingBottom: 32 },
  card: { borderRadius: 16 },
  form: { gap: 12 },
});
