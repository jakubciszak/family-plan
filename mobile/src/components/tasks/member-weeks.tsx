import AsyncStorage from '@react-native-async-storage/async-storage';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, View } from 'react-native';

import type { Member } from '@/api/teams';
import Avatar from '@/components/avatar';
import PointsCalendar from '@/components/points-calendar';
import { useAppTheme } from '@/theme/theme-context';

export default function MemberWeeks({
  members,
  storageKey,
  revision,
  canManage,
}: {
  members: Member[];
  storageKey: string;
  revision: number;
  canManage: boolean;
}) {
  const { t } = useTranslation();
  const { theme } = useAppTheme();
  const [hidden, setHidden] = useState<string[]>([]);
  const [loaded, setLoaded] = useState(false);
  useEffect(() => {
    let active = true;
    void AsyncStorage.getItem(storageKey)
      .then((value) => {
        const parsed = value ? JSON.parse(value) : [];
        if (active && Array.isArray(parsed))
          setHidden(parsed.filter((id) => typeof id === 'string'));
      })
      .catch(() => undefined)
      .finally(() => {
        if (active) setLoaded(true);
      });
    return () => {
      active = false;
    };
  }, [storageKey]);
  const toggle = (userId: string) => {
    const next = hidden.includes(userId)
      ? hidden.filter((id) => id !== userId)
      : [...hidden, userId];
    setHidden(next);
    void AsyncStorage.setItem(storageKey, JSON.stringify(next)).catch(() => undefined);
  };
  if (!members.length) return null;
  return (
    <View testID="member-weeks" style={{ gap: 12 }}>
      <View
        accessibilityLabel={t('week.whoIsShown')}
        style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}
      >
        {members.map((member) => (
          <Pressable
            key={member.userId}
            accessibilityRole="button"
            accessibilityLabel={member.userName}
            accessibilityState={{ selected: !hidden.includes(member.userId) }}
            disabled={!loaded}
            onPress={() => toggle(member.userId)}
            style={{
              width: 40,
              height: 40,
              borderRadius: 12,
              alignItems: 'center',
              justifyContent: 'center',
              borderWidth: 1,
              borderColor: hidden.includes(member.userId)
                ? theme.colors.outlineVariant
                : 'transparent',
              backgroundColor: hidden.includes(member.userId)
                ? theme.palette.surfaceContainerLow
                : theme.colors.primaryContainer,
            }}
          >
            <Avatar avatar={member.face?.avatar} name={member.userName} size={32} />
          </Pressable>
        ))}
      </View>
      {members
        .filter((member) => !hidden.includes(member.userId))
        .map((member) => (
          <PointsCalendar
            key={member.userId}
            userId={member.userId}
            title={member.userName}
            canManage={canManage}
            revision={revision}
          />
        ))}
    </View>
  );
}
