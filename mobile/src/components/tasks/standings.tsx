import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, View } from 'react-native';
import { Text } from 'react-native-paper';

import { readStandings, type Standing } from '@/api/calendar';
import { useAuth } from '@/auth/auth-context';
import { currentMonday, dayString, shiftDay } from '@/dates';
import { useAppTheme } from '@/theme/theme-context';

import { EmptyTasks, SectionHeading } from './task-ui';

export default function Standings({
  teamId,
  revision,
  canManage,
}: {
  teamId?: string;
  revision: number;
  canManage: boolean;
}) {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { theme } = useAppTheme();
  const [board, setBoard] = useState<{
    standings: Standing[];
    days?: string[];
    today?: string;
  } | null>(null);
  useFocusEffect(
    useCallback(() => {
      let active = true;
      if (teamId && revision >= 0)
        void readStandings(teamId, currentMonday())
          .then((value) => {
            if (active) setBoard(value);
          })
          .catch(() => {
            if (active) setBoard(null);
          });
      return () => {
        active = false;
      };
    }, [teamId, revision]),
  );
  if (!board?.standings.length) return null;
  const days =
    board.days ?? Array.from({ length: 7 }, (_, index) => shiftDay(currentMonday(), index));
  const cell = {
    paddingVertical: 8,
    paddingHorizontal: 6,
    width: 34,
    fontSize: 14,
    textAlign: 'center' as const,
  };
  return (
    <View
      testID="home-standings"
      style={{
        padding: 16,
        borderRadius: 16,
        gap: 12,
        backgroundColor: theme.palette.surfaceContainerLow,
      }}
    >
      <SectionHeading title={t('leaderboard.title')} icon="star-circle-outline" />
      {board.standings.some((row) => row.total > 0) ? (
        <ScrollView horizontal>
          <View>
            <View
              style={{
                flexDirection: 'row',
                borderBottomWidth: 1,
                borderColor: theme.colors.outlineVariant,
              }}
            >
              <Text style={cell}>#</Text>
              <Text style={[cell, { width: 120, textAlign: 'left' }]}>
                {t('leaderboard.member')}
              </Text>
              <Text style={[cell, { width: 56 }]}>{t('leaderboard.total')}</Text>
              {days.map((day, index) => (
                <Text key={day} style={cell}>
                  {t(`week.days.${['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'][index]}`)}
                </Text>
              ))}
            </View>
            {board.standings.map((row, index) => (
              <View
                key={row.userId}
                style={{
                  flexDirection: 'row',
                  alignItems: 'center',
                  backgroundColor:
                    row.userId === user?.id ? `${theme.colors.primary}14` : 'transparent',
                }}
              >
                <Text style={cell}>{index + 1}</Text>
                <Pressable
                  disabled={!canManage}
                  accessibilityRole={canManage ? 'button' : undefined}
                  onPress={() =>
                    router.push({ pathname: '/member', params: { id: row.userId, name: row.name } })
                  }
                  style={{ width: 120, paddingHorizontal: 6, paddingVertical: 8 }}
                >
                  <Text
                    style={{
                      fontSize: 14,
                      fontWeight: '500',
                      color: canManage ? theme.colors.primary : theme.colors.onSurface,
                    }}
                  >
                    {row.name}
                    {row.userId === user?.id ? ` · ${t('leaderboard.you')}` : ''}
                  </Text>
                </Pressable>
                <Text
                  style={[
                    cell,
                    {
                      width: 56,
                      fontWeight: '600',
                      borderRightWidth: 1,
                      borderColor: theme.colors.outlineVariant,
                    },
                  ]}
                >
                  {row.total}
                </Text>
                {days.map((day) => (
                  <Text
                    key={day}
                    style={[
                      cell,
                      {
                        backgroundColor:
                          day === (board.today ?? dayString(new Date()))
                            ? theme.palette.surfaceContainerHigh
                            : 'transparent',
                      },
                    ]}
                  >
                    {row.perDay?.[day] || ''}
                  </Text>
                ))}
              </View>
            ))}
          </View>
        </ScrollView>
      ) : (
        <EmptyTasks>{t('leaderboard.empty')}</EmptyTasks>
      )}
    </View>
  );
}
