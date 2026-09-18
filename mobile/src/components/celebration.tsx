import { useAudioPlayer } from 'expo-audio';
import { useEffect, useState } from 'react';
import { AccessibilityInfo, Animated, StyleSheet, useWindowDimensions, View } from 'react-native';
import { Portal } from 'react-native-paper';

const COLOURS = ['#e3b64d', '#4bbf91', '#d974a7', '#699dd8'];

export default function Celebration({ withSound, onDone }: { withSound: boolean; onDone: () => void }) {
  const [progress] = useState(() => new Animated.Value(0));
  const { width, height } = useWindowDimensions();
  const player = useAudioPlayer(require('../../assets/sounds/success.wav'));

  useEffect(() => {
    let active = true;
    const animation = Animated.timing(progress, { toValue: 1, duration: 1800, useNativeDriver: true });
    void AccessibilityInfo.isReduceMotionEnabled().then((reduced) => {
      if (!active) return;
      if (reduced) { onDone(); return; }
      animation.start(({ finished }) => { if (finished) onDone(); });
    });
    return () => { active = false; animation.stop(); };
  }, [progress, onDone]);

  useEffect(() => {
    if (withSound) player.play();
  }, [withSound, player]);

  return (
    <Portal>
      <View testID="success-celebration" style={[StyleSheet.absoluteFill, { pointerEvents: 'none' }]}>
        {Array.from({ length: 24 }, (_, index) => <Animated.View key={index} style={{
          position: 'absolute', width: 8, height: 14, backgroundColor: COLOURS[index % COLOURS.length],
          left: ((index * 47) % 100) / 100 * width,
          opacity: progress.interpolate({ inputRange: [0, 0.8, 1], outputRange: [1, 1, 0] }),
          transform: [
            { translateY: progress.interpolate({ inputRange: [0, 1], outputRange: [-30 - (index % 5) * 35, height] }) },
            { rotate: progress.interpolate({ inputRange: [0, 1], outputRange: ['0deg', `${360 + index * 30}deg`] }) },
          ],
        }} />)}
      </View>
    </Portal>
  );
}
