import { Redirect, Tabs } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';
import { Banner, Text, useTheme } from 'react-native-paper';

import { ActionPlanProvider, useActionPlans } from '@/action-plans/context';

import TasksHeader from '@/components/tasks/tasks-header';
import { useAuth } from '@/auth/auth-context';
import Backdrop from '@/components/backdrop';
import NotificationCentre from '@/components/notification-centre';
import AppTabBar from '@/navigation/app-tab-bar';
import { usePersonalisation } from '@/personalisation/personalisation-context';
import { useDressedUp } from '@/personalisation/use-screen-background';

export default function AppLayout() {
  const { user, restoring } = useAuth();
  if (restoring) return null;
  if (!user) return <Redirect href="/login" />;
  return <ActionPlanProvider key={user.id} userId={user.id}><AuthenticatedLayout /></ActionPlanProvider>;
}

function AuthenticatedLayout() {
  const { focused } = useActionPlans();
  const { t } = useTranslation();
  const { user, restoring } = useAuth();
  const { own, error, reload } = usePersonalisation();
  const theme = useTheme();
  const dressedUp = useDressedUp();

  if (restoring) {
    return null;
  }

  if (!user) {
    return <Redirect href="/login" />;
  }

  const seeThrough = dressedUp ? 'transparent' : undefined;

  return (
    <View style={[styles.stage, { backgroundColor: theme.colors.background }]}>
      {!focused && <Backdrop backdrop={own?.backdrop} />}
      {error && !focused && <Banner visible={error} actions={[{ label: t('common.retry'), onPress: () => void reload() }]}>
        {error ? t('errors.generic') : ''}
      </Banner>}

      <Tabs
        tabBar={(props) => focused ? null : <AppTabBar {...props} />}
        screenOptions={{
          headerShown: !focused,
          sceneStyle: { backgroundColor: seeThrough ?? theme.colors.background },
          headerStyle: { backgroundColor: theme.colors.surface },
          headerTitleStyle: { color: theme.colors.onSurface },
          headerShadowVisible: false,
          header: ({ options }) => <View style={{ backgroundColor: theme.colors.surface }}>
            <TasksHeader revision={0} />
            <View style={{ padding: 16, flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              {options.headerLeft?.({ tintColor: theme.colors.onSurface, canGoBack: true })}
              <Text accessibilityRole="header" style={{ fontSize: 28, lineHeight: 36, flex: 1 }}>{options.title}</Text>
            </View>
          </View>,
        }}>
        <Tabs.Screen name="index" options={{ title: t('nav.tasks'), headerShown: false }} />
        <Tabs.Screen name="action-plans" options={{ title: t('actionPlans.title') }} />
        <Tabs.Screen name="teams" options={{ title: t('nav.teams') }} />
        <Tabs.Screen name="allowance" options={{ title: t('nav.allowance') }} />
        <Tabs.Screen name="task-types" options={{ title: t('nav.taskTypes') }} />
        <Tabs.Screen name="bonus-rules" options={{ title: t('nav.bonusRules') }} />
        <Tabs.Screen name="status-change-rules" options={{ title: t('nav.statusChangeRules') }} />
        <Tabs.Screen name="notification-events" options={{ title: t('nav.notificationEvents') }} />
        <Tabs.Screen name="personalise" options={{ title: t('personalise.title') }} />
        <Tabs.Screen name="account" options={{ title: t('nav.account') }} />
        <Tabs.Screen name="settings" options={{ title: t('nav.settings') }} />
        <Tabs.Screen name="member" options={{ title: t('member.tasksSection') }} />
      </Tabs>

      {!focused && <NotificationCentre />}
    </View>
  );
}

const styles = StyleSheet.create({
  stage: {
    flex: 1,
  },
});
