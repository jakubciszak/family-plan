import { requestNotificationPermission } from '@/notifications/device';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Platform, ScrollView, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  Button,
  Card,
  Chip,
  Divider,
  List,
  SegmentedButtons,
  Snackbar,
  Switch,
  Text,
  useTheme,
} from 'react-native-paper';

import type { Language, ThemeMode } from '@/api/personalisation';
import {
  readNotificationChannels,
  saveNotificationChannels,
  type ChannelChoice,
} from '@/api/user-settings';
import { useAuth } from '@/auth/auth-context';
import { usePersonalisation } from '@/personalisation/personalisation-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';

const MODE_ICONS: Record<ThemeMode, string> = {
  light: 'white-balance-sunny',
  dark: 'weather-night',
  system: 'theme-light-dark',
};

export default function SettingsScreen() {
  const { t, i18n } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();
  const { own, loading, save } = usePersonalisation();
  const { user, manages } = useAuth();
  const router = useRouter();

  const [channelLoad, setChannelLoad] = useState(0);
  const [channelError, setChannelError] = useState(false);
  const [channels, setChannels] = useState<ChannelChoice[] | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [said, setSaid] = useState<string | null>(null);

  useEffect(() => {
    if (!user) {
      return undefined;
    }

    let cancelled = false;

    readNotificationChannels(user.id)
      .then((held) => {
        if (!cancelled) {
          setChannels(held);
          setChannelError(false);
        }
      })
      .catch(() => {
        if (!cancelled) setChannelError(true);
      });

    return () => {
      cancelled = true;
    };
  }, [user, channelLoad]);

  const flip = (name: ChannelChoice['name']) =>
    setChannels(
      (current) =>
        current?.map((choice) =>
          choice.name === name ? { ...choice, enabled: !choice.enabled } : choice,
        ) ?? null,
    );

  const keepChannels = async () => {
    if (!user || !channels) {
      return;
    }

    setSaving(true);
    setError(null);

    try {
      await saveNotificationChannels(user.id, channels);
      setSaid(t('settings.save_success'));
    } catch {
      setError(t('settings.save_error'));
    } finally {
      setSaving(false);
    }
  };

  if (loading || !own) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
      <Banner
        visible={Boolean(error)}
        actions={[{ label: t('common.close'), onPress: () => setError(null) }]}
      >
        {error ?? ''}
      </Banner>

      <ScrollView contentContainerStyle={styles.page}>
        {Platform.OS !== 'web' && <Card mode="elevated"><Card.Content style={styles.section}>
          <Text variant="titleMedium">{t('notifications.deviceTitle')}</Text>
          <Text>{t('notifications.deviceHint')}</Text>
          <Button onPress={() => void requestNotificationPermission(true).catch(() => setError(t('common.error')))}>{t('notifications.devicePermission')}</Button>
        </Card.Content></Card>}
        {manages ? (
          <Card mode="elevated" style={styles.card}>
            <Card.Title
              title={t('schoolTimetable.title')}
              titleVariant="titleMedium"
              subtitle={t('schoolTimetable.settingsHint')}
              subtitleNumberOfLines={3}
            />
            <Divider />
            <List.Item
              testID="settings-school-timetable"
              onPress={() => router.push('/school-timetable')}
              title={t('schoolTimetable.open')}
              left={(props) => <List.Icon {...props} icon="calendar-import" />}
              right={(props) => <List.Icon {...props} icon="chevron-right" />}
            />
          </Card>
        ) : null}

        <Card mode="elevated" style={styles.card}>
          <Card.Title title={t('theme.appearance')} titleVariant="titleMedium" />
          <Card.Content style={styles.section}>
            <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
              {t('theme.appearanceDescription')}
            </Text>

            <View style={styles.chips}>
              {own.choices.themeModes.map((mode) => (
                <Chip
                  key={mode}
                  icon={MODE_ICONS[mode]}
                  selected={own.themeMode === mode}
                  showSelectedCheck={false}
                  onPress={() => void save({ themeMode: mode })}
                >
                  {t(`theme.${mode}`)}
                </Chip>
              ))}
            </View>
          </Card.Content>
        </Card>

        <Card mode="elevated" style={styles.card}>
          <Card.Title title={t('theme.language')} titleVariant="titleMedium" />
          <Card.Content style={styles.section}>
            <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
              {t('theme.languageDescription')}
            </Text>

            <SegmentedButtons
              value={
                own.language ??
                own.choices.languages.find((known) => i18n.language?.startsWith(known)) ??
                own.choices.languages[0]
              }
              onValueChange={(language) => void save({ language: language as Language })}
              buttons={own.choices.languages.map((language) => ({
                value: language,
                label: language.toUpperCase(),
              }))}
            />
          </Card.Content>
        </Card>

        <Card mode="elevated" style={styles.card}>
          <Card.Title
            title={t('settings.notification_channels')}
            titleVariant="titleMedium"
            subtitle={t('settings.channels_description')}
            subtitleNumberOfLines={2}
          />
          <Divider />

          {channels === null ? (
            <Card.Content style={styles.section}>
              {channelError ? (
                <>
                  <Text>{t('errors.generic')}</Text>
                  <Button
                    testID="notification-channel-retry"
                    onPress={() => setChannelLoad((value) => value + 1)}
                  >
                    {t('common.retry')}
                  </Button>
                </>
              ) : (
                <ActivityIndicator />
              )}
            </Card.Content>
          ) : null}
          {channels?.map((choice) => (
            <List.Item
              key={choice.name}
              onPress={() => flip(choice.name)}
              title={t(`settings.channel_${choice.name}`)}
              description={t(`settings.channel_${choice.name}_desc`)}
              descriptionNumberOfLines={2}
              right={() => (
                <View pointerEvents="none">
                  <Switch value={choice.enabled} />
                </View>
              )}
            />
          ))}

          {channels !== null ? (
            <Card.Actions>
              <Button
                mode="contained"
                icon="check"
                loading={saving}
                disabled={saving}
                onPress={() => void keepChannels()}
              >
                {saving ? t('settings.saving') : t('settings.save')}
              </Button>
            </Card.Actions>
          ) : null}
        </Card>
      </ScrollView>

      <Snackbar visible={Boolean(said)} onDismiss={() => setSaid(null)} duration={3000}>
        {said ?? ''}
      </Snackbar>
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
  card: {
    borderRadius: 16,
  },
  section: {
    gap: 12,
  },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
});
