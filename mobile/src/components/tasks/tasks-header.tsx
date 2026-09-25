import Icon from '@/components/tasks/task-icon';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, useWindowDimensions, View } from 'react-native';
import { Badge, IconButton, Menu, Text } from 'react-native-paper';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { readCalendar } from '@/api/calendar';
import apiClient from '@/api/client';
import { useAuth } from '@/auth/auth-context';
import Avatar from '@/components/avatar';
import { currentMonday } from '@/dates';
import { useNotifications } from '@/notifications/notifications-context';
import { usePersonalisation } from '@/personalisation/personalisation-context';
import { useAppTheme } from '@/theme/theme-context';

export default function TasksHeader({ revision }: { revision: number }) {
  const { t } = useTranslation();
  const { user, signOut } = useAuth();
  const { own, save } = usePersonalisation();
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const notifications = useNotifications();
  const unread = notifications?.unreadCount ?? 0;
  const [menu, setMenu] = useState(false);
  const [total, setTotal] = useState(false);
  const [points, setPoints] = useState({ week: 0, balance: 0 });
  useFocusEffect(
    useCallback(() => {
      let active = true;
      if (user && revision >= 0) {
        void Promise.all([
          readCalendar(currentMonday()),
          apiClient.get<{ balance: number }>(`/api/users/${user.id}/points`),
        ])
          .then(([week, account]) => {
            if (active) setPoints({ week: week.total, balance: account.balance });
          })
          .catch(() => undefined);
      }
      return () => {
        active = false;
      };
    }, [user, revision]),
  );
  return (
    <View style={{ backgroundColor: theme.colors.surface, paddingTop: insets.top }}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          gap: 4,
          height: 64,
          paddingHorizontal: 16,
        }}
      >
        <Text numberOfLines={1} style={{ flex: 1, minWidth: 0, fontSize: 22, marginRight: 4 }}>
          {t('app.title')}
        </Text>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={t('personalise.title')}
          onPress={() => router.navigate('/personalise')}
          style={{ padding: 4 }}
        >
          <Avatar avatar={own?.avatar} name={own?.nickname || user?.name} size={32} />
        </Pressable>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={t(total ? 'user.pointsTotalHint' : 'user.pointsWeekHint')}
          onPress={() => setTotal((current) => !current)}
          style={{
            flexDirection: 'row',
            alignItems: 'center',
            gap: 6,
            borderRadius: 100,
            minHeight: 32,
            paddingHorizontal: 12,
            backgroundColor: theme.colors.primaryContainer,
          }}
        >
          <Icon source="star-circle-outline" size={18} color={theme.colors.onPrimaryContainer} />
          <Text style={{ fontSize: 14, fontWeight: '500', color: theme.colors.onPrimaryContainer }}>
            {t('user.points', { points: total ? points.balance : points.week })}
          </Text>
          {width >= 360 ? (
            <Text style={{ fontSize: 11, color: theme.colors.onPrimaryContainer, opacity: 0.8 }}>
              {t(total ? 'user.pointsTotal' : 'user.pointsWeek')}
            </Text>
          ) : null}
        </Pressable>
        {notifications ? (
          <View>
            <IconButton
              icon={unread > 0 ? 'bell-badge-outline' : 'bell-outline'}
              size={20}
              style={{ margin: 0 }}
              testID="notification-bell"
              accessibilityLabel={unread > 0 ? t('notifications.bellUnread', { count: unread }) : t('notifications.title')}
              onPress={notifications.openInbox}
            />
            {unread > 0 ? (
              <Badge size={16} style={{ position: 'absolute', top: 2, right: 0 }} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
                {unread > 99 ? '99+' : unread}
              </Badge>
            ) : null}
          </View>
        ) : null}
        <IconButton
          icon="logout"
          size={20}
          style={{ margin: 0 }}
          accessibilityLabel={t('auth.logout')}
          onPress={() => void signOut()}
        />
        <Menu
          visible={menu}
          onDismiss={() => setMenu(false)}
          anchor={
            <IconButton
              icon="dots-vertical"
              size={20}
              style={{ margin: 0 }}
              accessibilityLabel={t('user.accountMenu')}
              onPress={() => setMenu(true)}
            />
          }
        >
          <Menu.Item title={own?.nickname || user?.name} disabled />
          <Menu.Item
            title={t('nav.account')}
            leadingIcon="account-circle-outline"
            onPress={() => {
              setMenu(false);
              router.navigate('/account');
            }}
          />
          <Menu.Item
            title={t('nav.settings')}
            leadingIcon="cog-outline"
            onPress={() => {
              setMenu(false);
              router.navigate('/settings');
            }}
          />
          <Menu.Item
            title={t(`theme.${own?.themeMode ?? 'system'}`)}
            leadingIcon="theme-light-dark"
            onPress={() =>
              void save({
                themeMode:
                  own?.themeMode === 'light'
                    ? 'dark'
                    : own?.themeMode === 'dark'
                      ? 'system'
                      : 'light',
              })
            }
          />
        </Menu>
      </View>
    </View>
  );
}
