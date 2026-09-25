import Icon from '@/components/tasks/task-icon';
import type { BottomTabBarProps } from 'expo-router/build/react-navigation/bottom-tabs';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';
import { Divider, List, Modal, Portal, Text, TouchableRipple, useTheme } from 'react-native-paper';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { useAuth } from '@/auth/auth-context';
import { usePersonalisation } from '@/personalisation/personalisation-context';

import { placesToShow, splitAtBar, type Place } from './places';

export const BAR_HEIGHT = 80;

type DestinationProps = {
  icon: string;
  label: string;
  active: boolean;
  onPress: () => void;
};

function Destination({ icon, label, active, onPress }: DestinationProps) {
  const theme = useTheme();

  return (
    <TouchableRipple
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ selected: active }}
      borderless
      style={styles.destination}>
      <View style={styles.stack}>
        <View style={[styles.pill, active && { backgroundColor: theme.colors.secondaryContainer }]}>
          <Icon
            source={icon}
            size={24}
            color={active ? theme.colors.onSecondaryContainer : theme.colors.onSurfaceVariant}
          />
        </View>
        <Text
          variant="labelMedium"
          numberOfLines={1}
          style={{ color: active ? theme.colors.onSurface : theme.colors.onSurfaceVariant }}>
          {label}
        </Text>
      </View>
    </TouchableRipple>
  );
}

export default function AppTabBar({ state, navigation }: BottomTabBarProps) {
  const { t } = useTranslation();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { own } = usePersonalisation();
  const { manages, isSuperAdmin } = useAuth();
  const [sheetOpen, setSheetOpen] = useState(false);

  const { onBar, behindMore } = splitAtBar(
    placesToShow({
      chosen: own && !own.places.navigation.includes('day-planning') ? [...own.navigation, 'day-planning'] : own?.navigation,
      built: state.routes.map((route) => route.name),
      manages,
      isSuperAdmin,
    })
  );

  const activeRoute = state.routes[state.index]?.name;
  const activeIsBehindMore = behindMore.some((place) => place.route === activeRoute);

  const go = (place: Place) => {
    setSheetOpen(false);
    navigation.navigate(place.route);
  };

  return (
    <>
      <View
        style={[
          styles.bar,
          {
            backgroundColor: theme.colors.elevation.level2,
            borderTopColor: theme.colors.outlineVariant,
            height: BAR_HEIGHT + insets.bottom,
            paddingBottom: insets.bottom,
          },
        ]}>
        {onBar.map((place) => (
          <Destination
            key={place.route}
            icon={place.icon}
            label={t(place.shortLabelKey ?? place.labelKey)}
            active={place.route === activeRoute}
            onPress={() => go(place)}
          />
        ))}

        {behindMore.length ? (
          <Destination
            icon="dots-horizontal"
            label={t('common.more')}
            active={activeIsBehindMore}
            onPress={() => setSheetOpen(true)}
          />
        ) : null}
      </View>

      <Portal>
        <Modal
          visible={sheetOpen}
          onDismiss={() => setSheetOpen(false)}
          contentContainerStyle={[
            styles.sheet,
            { backgroundColor: theme.colors.elevation.level3, paddingBottom: insets.bottom + 16 },
          ]}
          style={styles.sheetWrapper}>
          <View style={[styles.grabber, { backgroundColor: theme.colors.outlineVariant }]} />
          <Divider />
          {behindMore.map((place) => (
            <List.Item
              key={place.route}
              title={t(place.labelKey)}
              onPress={() => go(place)}
              left={(props) => <List.Icon {...props} icon={place.icon} />}
            />
          ))}
        </Modal>
      </Portal>
    </>
  );
}

const styles = StyleSheet.create({
  bar: {
    borderTopWidth: StyleSheet.hairlineWidth,
    flexDirection: 'row',
    paddingTop: 12,
  },
  destination: {
    flex: 1,
  },
  stack: {
    alignItems: 'center',
    gap: 4,
  },
  pill: {
    alignItems: 'center',
    borderRadius: 16,
    height: 32,
    justifyContent: 'center',
    width: 64,
  },
  sheetWrapper: {
    justifyContent: 'flex-end',
  },
  sheet: {
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    paddingTop: 12,
  },
  grabber: {
    alignSelf: 'center',
    borderRadius: 2,
    height: 4,
    marginBottom: 12,
    width: 32,
  },
});
