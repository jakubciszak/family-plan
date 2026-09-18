import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FlatList, StyleSheet, View } from 'react-native';
import { Button, HelperText, Text, TouchableRipple, useTheme } from 'react-native-paper';
import { SvgXml } from 'react-native-svg';

import type { Avatar as AvatarChoice } from '@/api/personalisation';
import { pickAndKeep } from '@/personalisation/pictures';

import Avatar, { avatarSvg, SEEDS, STYLE_NAMES } from './avatar';

const TILE = 48;

type Props = {
  avatar: AvatarChoice;
  name?: string | null;
  onPick: (choice: Partial<AvatarChoice>) => Promise<void> | void;
};

export default function AvatarPicker({ avatar, name, onPick }: Props) {
  const { t } = useTranslation();
  const theme = useTheme();
  const [style, setStyle] = useState(avatar.style || 'bottts');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const upload = async () => {
    setBusy(true);
    setError(null);

    try {
      const kept = await pickAndKeep('avatar');

      if (kept) {
        await onPick({ pictureId: kept.id });
      }
    } catch {
      setError(t('personalise.pictureFailed'));
    } finally {
      setBusy(false);
    }
  };

  const chosenRing = (chosen: boolean) =>
    chosen ? { borderColor: theme.colors.primary, borderWidth: 3 } : undefined;

  return (
    <View style={styles.wrap}>
      <View style={styles.now}>
        <Avatar avatar={avatar} name={name} size={72} />
        <Button accessibilityLabel={t('personalise.ownPicture')} mode="contained-tonal" icon="camera-plus-outline" loading={busy} onPress={() => void upload()}>
          {t('personalise.ownPicture')}
        </Button>
      </View>

      <HelperText type="error" visible={Boolean(error)}>
        {error}
      </HelperText>

      <Text variant="labelLarge">{t('personalise.avatarStyle')}</Text>
      <FlatList
        horizontal
        data={STYLE_NAMES}
        keyExtractor={(named) => named}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.strip}
        initialNumToRender={6}
        windowSize={3}
        renderItem={({ item: named }) => (
          <TouchableRipple
            borderless
            accessibilityRole="button"
            accessibilityLabel={named}
            accessibilityState={{ selected: style === named }}
            onPress={() => setStyle(named)}
            style={[styles.tile, chosenRing(style === named)]}>
            <SvgXml xml={avatarSvg(named, avatar.seed || SEEDS[0])} width={TILE} height={TILE} />
          </TouchableRipple>
        )}
      />

      <FlatList
        horizontal
        data={SEEDS}
        keyExtractor={(seed) => seed}
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.strip}
        initialNumToRender={6}
        windowSize={3}
        renderItem={({ item: seed }) => (
          <TouchableRipple
            borderless
            accessibilityRole="button"
            accessibilityLabel={seed}
            accessibilityState={{ selected: avatar.seed === seed && avatar.style === style }}
            onPress={() => void onPick({ style, seed, pictureId: null })}
            style={[styles.tile, chosenRing(avatar.seed === seed && avatar.style === style)]}>
            <SvgXml xml={avatarSvg(style, seed)} width={TILE} height={TILE} />
          </TouchableRipple>
        )}
      />

      <Button
        mode="text"
        icon="dice-multiple-outline"
        onPress={() =>
          void onPick({ style, seed: Math.random().toString(36).slice(2, 10), pictureId: null })
        }>
        {t('personalise.surpriseMe')}
      </Button>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    gap: 8,
  },
  now: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 16,
  },
  strip: {
    gap: 8,
    paddingVertical: 4,
  },
  tile: {
    borderRadius: TILE / 2,
    height: TILE,
    overflow: 'hidden',
    width: TILE,
  },
});
