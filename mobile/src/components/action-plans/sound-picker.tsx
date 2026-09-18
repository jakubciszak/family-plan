import { useAudioPlayer, setAudioModeAsync } from 'expo-audio';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { Button, Text } from 'react-native-paper';

import { REMINDER_SOUNDS, type ReminderSound } from '@/api/action-plans';
import ChoicePicker from './choice-picker';

export function useReminderSound() {
  const soft = useAudioPlayer(require('../../../assets/sounds/plan-soft.wav'));
  const bell = useAudioPlayer(require('../../../assets/sounds/plan-bell.wav'));
  const double = useAudioPlayer(require('../../../assets/sounds/plan-double.wav'));
  const melody = useAudioPlayer(require('../../../assets/sounds/plan-melody.wav'));
  const players = useMemo(() => ({ soft, bell, double, melody }), [soft, bell, double, melody]);
  const [unavailable, setUnavailable] = useState(false);
  const stop = useCallback(() => Object.values(players).forEach((player) => player.pause()), [players]);
  const enable = useCallback(async () => {
    try {
      await setAudioModeAsync({ playsInSilentMode: true, shouldPlayInBackground: false });
      setUnavailable(false);
    } catch { setUnavailable(true); }
  }, []);
  useEffect(() => {
    void setAudioModeAsync({ playsInSilentMode: true, shouldPlayInBackground: false }).catch(() => setUnavailable(true));
  }, []);
  const play = useCallback(async (sound: ReminderSound) => {
    try {
      stop();
      const player = players[sound] ?? players.soft;
      await player.seekTo(0);
      player.play();
      setUnavailable(false);
    } catch { setUnavailable(true); }
  }, [players, stop]);
  return { play, stop, unavailable, enable };
}

export default function SoundPicker({ value, onChange, disabled, audio }: {
  value: ReminderSound; onChange: (value: ReminderSound) => void; disabled?: boolean;
  audio: ReturnType<typeof useReminderSound>;
}) {
  const { t } = useTranslation();
  return <View style={{ gap: 8 }}>
    <ChoicePicker label={t('actionPlans.soundChoice')} value={value} disabled={disabled}
      options={REMINDER_SOUNDS.map((sound) => ({ value: sound, label: t(`actionPlans.sounds.${sound}`) }))}
      onChange={(sound) => { audio.stop(); onChange(sound as ReminderSound); }} />
    <Button accessibilityLabel={t('actionPlans.previewSound')} icon="volume-high" disabled={disabled} onPress={() => void audio.play(value)}>{t('actionPlans.previewSound')}</Button>
    {audio.unavailable && <View accessibilityLiveRegion="polite"><Text>{t('actionPlans.soundUnavailable')}</Text>
      <Button onPress={() => void audio.enable()}>{t('actionPlans.enableSound')}</Button></View>}
  </View>;
}
