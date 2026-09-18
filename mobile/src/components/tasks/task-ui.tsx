import Icon from '@/components/tasks/task-icon';
import type { ComponentProps, ReactNode } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';
import { Button, Text } from 'react-native-paper';

import { useAppTheme } from '@/theme/theme-context';

export function SectionHeading({
  title,
  icon,
  expanded,
  onPress,
  children,
}: {
  title: string;
  icon: string;
  expanded?: boolean;
  onPress?: () => void;
  children?: ReactNode;
}) {
  const { theme } = useAppTheme();
  const content = (
    <>
      <Icon source={icon} size={20} color={theme.colors.onSurfaceVariant} />
      <Text
        style={{ flex: 1, fontSize: 16, fontWeight: '500', color: theme.colors.onSurfaceVariant }}
      >
        {title}
      </Text>
      {children}
      {onPress ? <Icon source={expanded ? 'chevron-up' : 'chevron-down'} size={20} /> : null}
    </>
  );
  return onPress ? (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={title}
      accessibilityState={{ expanded }}
      aria-expanded={expanded}
      onPress={onPress}
      style={taskStyles.heading}
    >
      {content}
    </Pressable>
  ) : (
    <View style={taskStyles.heading}>{content}</View>
  );
}

export function TaskBadge({
  children,
  icon,
  tone = 'neutral',
}: {
  children: ReactNode;
  icon?: string;
  tone?: 'neutral' | 'primary' | 'success' | 'error';
}) {
  const { theme } = useAppTheme();
  const p = theme.palette;
  const colors =
    tone === 'primary'
      ? [p.primaryContainer, p.onPrimaryContainer]
      : tone === 'success'
        ? [p.successContainer, p.onSuccessContainer]
        : tone === 'error'
          ? [p.errorContainer, p.onErrorContainer]
          : [p.surfaceContainerHigh, p.onSurfaceVariant];
  return (
    <View style={[taskStyles.badge, { backgroundColor: colors[0] }]}>
      {icon ? <Icon source={icon} size={16} color={colors[1]} /> : null}
      <Text style={{ fontSize: 12, fontWeight: '500', color: colors[1] }}>{children}</Text>
    </View>
  );
}

export function EmptyTasks({ children }: { children: ReactNode }) {
  const { theme } = useAppTheme();
  return (
    <Text
      style={{
        paddingVertical: 24,
        paddingHorizontal: 8,
        fontSize: 14,
        lineHeight: 20,
        textAlign: 'center',
        color: theme.colors.onSurfaceVariant,
      }}
    >
      {children}
    </Text>
  );
}

export const taskStyles = StyleSheet.create({
  section: { gap: 12 },
  heading: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    minHeight: 36,
    paddingHorizontal: 4,
  },
  card: {
    borderRadius: 16,
    padding: 16,
    gap: 12,
    boxShadow: '0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.08)',
  },
  name: { fontSize: 16, lineHeight: 24, fontWeight: '500', letterSpacing: 0.15 },
  badges: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 8 },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    minHeight: 28,
    paddingHorizontal: 12,
    borderRadius: 8,
  },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, paddingTop: 4 },
  button: { flexGrow: 1 },
});

export function TaskButton({
  children,
  contentStyle,
  icon,
  ...props
}: ComponentProps<typeof Button>) {
  return (
    <Button
      icon={typeof icon === 'string' ? (props) => <Icon source={icon} {...props} /> : icon}
      accessibilityLabel={typeof children === 'string' ? children : undefined}
      {...props}
      contentStyle={[{ minHeight: 48 }, contentStyle]}
    >
      {children}
    </Button>
  );
}
