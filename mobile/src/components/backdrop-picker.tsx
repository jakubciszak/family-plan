import Slider from '@react-native-community/slider';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';
import { Button, Chip, HelperText, Text, useTheme } from 'react-native-paper';

import type { Backdrop as BackdropChoice } from '@/api/personalisation';
import { pickAndKeep } from '@/personalisation/pictures';

import { PATTERNS } from './backdrop';

type Props = {
  backdrop: BackdropChoice;
  onPick: (choice: Partial<BackdropChoice>) => Promise<void> | void;
};

export default function BackdropPicker({ backdrop, onPick }: Props) {
  const { t } = useTranslation();
  const theme = useTheme();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [dimming, setDimming] = useState(backdrop.dimming ?? 40);

  const upload = async () => {
    setBusy(true);
    setError(null);

    try {
      const kept = await pickAndKeep('backdrop');

      if (kept) {
        await onPick({ pictureId: kept.id, dimming });
      }
    } catch {
      setError(t('personalise.pictureFailed'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.wrap}>
      <View style={styles.patterns}>
        {PATTERNS.map((pattern) => (
          <Chip
            key={pattern}
            selected={!backdrop.pictureId && backdrop.pattern === pattern}
            showSelectedCheck
            onPress={() => void onPick({ pattern, pictureId: null })}>
            {t(`personalise.backdrops.${pattern}`)}
          </Chip>
        ))}
      </View>

      <Button accessibilityLabel={t('personalise.ownBackdrop')} mode="contained-tonal" icon="image-plus" loading={busy} onPress={() => void upload()}>
        {t('personalise.ownBackdrop')}
      </Button>

      <HelperText type="error" visible={Boolean(error)}>
        {error}
      </HelperText>

      {backdrop.pictureId ? (
        <View>
          <Text variant="labelLarge">{t('personalise.dimming', { percent: Math.round(dimming) })}</Text>
          <Slider
            minimumValue={0}
            maximumValue={100}
            step={5}
            value={dimming}
            onValueChange={setDimming}
            onSlidingComplete={(picked) => void onPick({ dimming: Math.round(picked) })}
            minimumTrackTintColor={theme.colors.primary}
            maximumTrackTintColor={theme.colors.surfaceVariant}
            thumbTintColor={theme.colors.primary}
          />
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    gap: 12,
  },
  patterns: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
});
