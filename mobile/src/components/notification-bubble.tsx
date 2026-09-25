import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Animated, AppState, PanResponder, Pressable, StyleSheet, View } from 'react-native';
import { IconButton, Text, TouchableRipple, useTheme } from 'react-native-paper';

import Icon from '@/components/tasks/task-icon';

/** Share of its width a bubble has to travel before letting go dismisses it. */
const SWIPE_SHARE = 0.35;
/** A quick flick dismisses even when it travelled less (px per ms). */
const FLICK_SPEED = 0.6;
/** Movement before a drag counts as a horizontal swipe rather than a tap or a scroll. */
const DECIDE_AFTER = 8;
const LEAVE_MS = 180;

type Props = {
  title?: string | null;
  message?: string | null;
  meta?: string | null;
  icon: string;
  closeLabel: string;
  autoHideMs?: number;
  action?: { label: string; onPress: () => void };
  onOpen: () => void;
  onDismiss: () => void;
  testID?: string;
};

/**
 * One notification bubble. It closes with the X, a swipe to either side, or by itself after `autoHideMs`.
 * The timer waits while a finger rests on it and while the app is in the background.
 *
 * Every text gets its colour from the inverse pair of the theme explicitly, so no text inherits a colour
 * meant for a different background.
 */
export default function NotificationBubble({ title, message, meta, icon, closeLabel, autoHideMs = 0, action, onOpen, onDismiss, testID }: Props) {
  const { colors } = useTheme();
  const [shift] = useState(() => new Animated.Value(0));
  const [fade] = useState(() => new Animated.Value(1));
  const [held, setHeld] = useState(false);
  const [leaving, setLeaving] = useState(false);
  const [width, setWidth] = useState(360);
  // In a browser a drag ends with a click on the body. The bubble notes where each gesture started and whether
  // it turned into a swipe before the body hears about it, so that click does not open the notification.
  const gesture = useRef<{ x: number; y: number; swiped: boolean } | null>(null);

  const leave = useCallback((direction: number) => {
    if (leaving) return;
    setLeaving(true);
    const out = direction === 0
      ? Animated.timing(fade, { toValue: 0, duration: LEAVE_MS, useNativeDriver: false })
      : Animated.timing(shift, { toValue: direction * width * 1.2, duration: LEAVE_MS, useNativeDriver: false });
    out.start(() => onDismiss());
  }, [leaving, fade, shift, width, onDismiss]);

  const responder = useMemo(() => PanResponder.create({
    onMoveShouldSetPanResponder: (_, { dx, dy }) => Math.abs(dx) > DECIDE_AFTER && Math.abs(dx) > Math.abs(dy),
    // Take the gesture over from the pressable body once it is clearly sideways.
    onMoveShouldSetPanResponderCapture: (_, { dx, dy }) => Math.abs(dx) > DECIDE_AFTER && Math.abs(dx) > Math.abs(dy) * 1.5,
    onPanResponderGrant: () => setHeld(true),
    onPanResponderMove: Animated.event([null, { dx: shift }], { useNativeDriver: false }),
    onPanResponderTerminationRequest: () => false,
    onPanResponderRelease: (_, { dx, vx }) => {
      setHeld(false);
      if (Math.abs(dx) > width * SWIPE_SHARE || (Math.abs(vx) > FLICK_SPEED && Math.abs(dx) > DECIDE_AFTER * 3)) {
        leave(Math.sign(dx) || 1);
      } else {
        Animated.spring(shift, { toValue: 0, useNativeDriver: false, bounciness: 4 }).start();
      }
    },
    onPanResponderTerminate: () => {
      setHeld(false);
      Animated.spring(shift, { toValue: 0, useNativeDriver: false }).start();
    },
  }), [shift, width, leave]);

  useEffect(() => {
    if (!autoHideMs || held) return undefined;
    let remaining = autoHideMs;
    let startedAt = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;
    const start = () => {
      startedAt = Date.now();
      timer = setTimeout(() => leave(0), remaining);
    };
    const stop = () => {
      if (timer) clearTimeout(timer);
      timer = null;
      remaining = Math.max(0, remaining - (Date.now() - startedAt));
    };
    if (AppState.currentState === 'active') start();
    const appState = AppState.addEventListener('change', (state) => (state === 'active' ? start() : stop()));
    return () => {
      if (timer) clearTimeout(timer);
      appState.remove();
    };
  }, [autoHideMs, held, leave]);

  const opacity = useMemo(
    () => Animated.multiply(fade, shift.interpolate({ inputRange: [-400, 0, 400], outputRange: [0.2, 1, 0.2], extrapolate: 'clamp' })),
    [fade, shift],
  );
  const muted = colors.inverseOnSurface;
  const pan = responder.panHandlers;

  return (
    <Animated.View
      testID={testID}
      onLayout={(event) => { const measured = event.nativeEvent.layout.width; if (measured && Math.abs(measured - width) > 1) setWidth(measured); }}
      style={[styles.bubble, { backgroundColor: colors.inverseSurface, borderLeftColor: colors.inversePrimary, opacity, transform: [{ translateX: shift }] }]}
      accessibilityActions={[{ name: 'dismiss', label: closeLabel }]}
      onAccessibilityAction={({ nativeEvent }) => nativeEvent.actionName === 'dismiss' && leave(0)}
      {...pan}
      onStartShouldSetResponderCapture={(event) => {
        gesture.current = { x: event.nativeEvent.pageX, y: event.nativeEvent.pageY, swiped: false };
        return pan.onStartShouldSetResponderCapture?.(event) ?? false;
      }}
      onResponderGrant={(event) => {
        if (gesture.current) gesture.current.swiped = true;
        pan.onResponderGrant?.(event);
      }}
    >
      <View style={styles.icon} pointerEvents="none">
        <Icon source={icon} size={20} color={colors.inversePrimary} />
      </View>
      <Pressable
        accessibilityRole="button"
        onPress={({ nativeEvent }) => {
          const start = gesture.current;
          gesture.current = null;
          // No gesture behind the press means a keyboard or a screen reader asked to open it.
          if (start && (start.swiped || Math.hypot(nativeEvent.pageX - start.x, nativeEvent.pageY - start.y) > DECIDE_AFTER)) return;
          onOpen();
        }}
        onPressIn={() => setHeld(true)}
        onPressOut={() => setHeld(false)}
        style={styles.body}
      >
        {title ? <Text style={[styles.title, { color: colors.inverseOnSurface }]}>{title}</Text> : null}
        {message ? <Text style={[styles.message, { color: colors.inverseOnSurface }]}>{message}</Text> : null}
        {meta ? <Text style={[styles.meta, { color: muted }]}>{meta}</Text> : null}
      </Pressable>
      {action ? (
        <TouchableRipple onPress={action.onPress} accessibilityRole="button" borderless style={styles.action}>
          <Text style={[styles.actionLabel, { color: colors.inversePrimary }]}>{action.label}</Text>
        </TouchableRipple>
      ) : null}
      <IconButton
        icon="close"
        iconColor={colors.inverseOnSurface}
        size={20}
        style={styles.close}
        accessibilityLabel={closeLabel}
        onPress={() => leave(0)}
      />
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  bubble: {
    alignItems: 'flex-start',
    borderLeftWidth: 4,
    borderRadius: 12,
    elevation: 6,
    flexDirection: 'row',
    minHeight: 56,
    paddingLeft: 10,
    paddingVertical: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.25,
    shadowRadius: 6,
    width: '100%',
  },
  icon: {
    marginTop: 12,
  },
  body: {
    flex: 1,
    gap: 2,
    minWidth: 0,
    paddingHorizontal: 8,
    paddingVertical: 8,
  },
  title: {
    fontSize: 14,
    fontWeight: '600',
    lineHeight: 20,
  },
  message: {
    fontSize: 14,
    lineHeight: 20,
  },
  meta: {
    fontSize: 12,
    lineHeight: 16,
    opacity: 0.85,
  },
  action: {
    alignSelf: 'center',
    borderRadius: 20,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  actionLabel: {
    fontSize: 14,
    fontWeight: '600',
  },
  close: {
    margin: 2,
  },
});
