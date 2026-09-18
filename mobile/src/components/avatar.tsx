import {
  adventurer,
  avataaars,
  bigSmile,
  bottts,
  croodles,
  funEmoji,
  lorelei,
  micah,
  miniavs,
  notionists,
  openPeeps,
  personas,
  pixelArt,
  thumbs,
} from '@dicebear/collection';
import { createAvatar } from '@dicebear/core';
import { Image } from 'expo-image';
import { StyleSheet, View } from 'react-native';
import { Text, useTheme } from 'react-native-paper';
import { SvgXml } from 'react-native-svg';

import type { Avatar as AvatarChoice } from '@/api/personalisation';
import { pictureSource } from '@/api/pictures';

export const AVATAR_STYLES = {
  adventurer,
  avataaars,
  'big-smile': bigSmile,
  bottts,
  croodles,
  'fun-emoji': funEmoji,
  lorelei,
  micah,
  miniavs,
  notionists,
  'open-peeps': openPeeps,
  personas,
  'pixel-art': pixelArt,
  thumbs,
};

export const STYLE_NAMES = Object.keys(AVATAR_STYLES);

export const SEEDS = [
  'rakieta', 'jez', 'smok', 'kotek', 'burza', 'malina', 'kometa', 'wafel',
  'tygrys', 'lisek', 'balon', 'gwiazda', 'pizza', 'sowa', 'delfin', 'kaktus',
];

const drawn = new Map<string, string>();

export const avatarSvg = (style: string, seed: string | null | undefined): string => {
  const key = `${style}|${seed}`;

  if (!drawn.has(key)) {
    const collection = (AVATAR_STYLES[style as keyof typeof AVATAR_STYLES] ??
      bottts) as typeof bottts;

    drawn.set(key, createAvatar(collection, { seed: String(seed || 'kot'), radius: 50 }).toString());
  }

  return drawn.get(key) as string;
};

const initialOf = (name: string | null | undefined): string =>
  String(name || '?').trim().charAt(0).toUpperCase() || '?';

type Props = {
  avatar?: AvatarChoice | null;
  name?: string | null;
  size?: number;
};

export default function Avatar({ avatar, name, size = 40 }: Props) {
  const theme = useTheme();
  const box = { width: size, height: size, borderRadius: size / 2 };

  if (avatar?.pictureId) {
    return (
      <Image
        source={pictureSource(avatar.pictureId)}
        style={[box, styles.picture]}
        contentFit="cover"
        accessibilityLabel={name ?? undefined}
      />
    );
  }

  if (avatar?.style) {
    return (
      <View style={box}>
        <SvgXml xml={avatarSvg(avatar.style, avatar.seed)} width={size} height={size} />
      </View>
    );
  }

  return (
    <View
      style={[box, styles.initials, { backgroundColor: theme.colors.primaryContainer }]}
      accessible={false}>
      <Text style={{ color: theme.colors.onPrimaryContainer, fontSize: size * 0.4 }}>
        {initialOf(name)}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  picture: {
    overflow: 'hidden',
  },
  initials: {
    alignItems: 'center',
    justifyContent: 'center',
  },
});
