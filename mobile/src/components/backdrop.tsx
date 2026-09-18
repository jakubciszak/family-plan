import { Image } from 'expo-image';
import { StyleSheet, useWindowDimensions, View } from 'react-native';
import { useTheme } from 'react-native-paper';
import Svg, { Circle, Defs, Pattern, Rect } from 'react-native-svg';

import type { Backdrop as BackdropChoice } from '@/api/personalisation';
import { pictureSource } from '@/api/pictures';

export const PATTERNS = ['plain', 'dots', 'waves', 'grid', 'stars', 'bubbles', 'confetti'];

type Props = {
  backdrop?: BackdropChoice | null;
};

function Patterned({ pattern }: { pattern: string }) {
  const theme = useTheme();
  const { width, height } = useWindowDimensions();
  const primary = theme.colors.primary;
  const tertiary = theme.colors.tertiary;
  const secondary = theme.colors.secondary;

  if (pattern === 'dots') {
    return (
      <Svg style={StyleSheet.absoluteFill} width={width} height={height}>
        <Defs>
          <Pattern id="dots" width={18} height={18} patternUnits="userSpaceOnUse">
            <Circle cx={9} cy={9} r={1.5} fill={primary} fillOpacity={0.22} />
          </Pattern>
        </Defs>
        <Rect width={width} height={height} fill="url(#dots)" />
      </Svg>
    );
  }

  if (pattern === 'grid') {
    return (
      <Svg style={StyleSheet.absoluteFill} width={width} height={height}>
        <Defs>
          <Pattern id="grid" width={28} height={28} patternUnits="userSpaceOnUse">
            <Rect width={28} height={1} fill={primary} fillOpacity={0.14} />
            <Rect width={1} height={28} fill={primary} fillOpacity={0.14} />
          </Pattern>
        </Defs>
        <Rect width={width} height={height} fill="url(#grid)" />
      </Svg>
    );
  }

  if (pattern === 'stars') {
    return (
      <Svg style={StyleSheet.absoluteFill} width={width} height={height}>
        <Defs>
          <Pattern id="starsNear" width={90} height={90} patternUnits="userSpaceOnUse">
            <Circle cx={45} cy={45} r={1} fill={tertiary} fillOpacity={0.55} />
          </Pattern>
          <Pattern
            id="starsFar"
            width={140}
            height={140}
            patternUnits="userSpaceOnUse"
            x={40}
            y={70}>
            <Circle cx={70} cy={70} r={1} fill={primary} fillOpacity={0.35} />
          </Pattern>
        </Defs>
        <Rect width={width} height={height} fill="url(#starsNear)" />
        <Rect width={width} height={height} fill="url(#starsFar)" />
      </Svg>
    );
  }

  if (pattern === 'confetti') {
    return (
      <Svg style={StyleSheet.absoluteFill} width={width} height={height}>
        <Defs>
          <Pattern
            id="confetti"
            width={22}
            height={22}
            patternUnits="userSpaceOnUse"
            patternTransform="rotate(45)">
            <Rect width={6} height={22} fill={tertiary} fillOpacity={0.14} />
          </Pattern>
        </Defs>
        <Rect width={width} height={height} fill="url(#confetti)" />
      </Svg>
    );
  }

  if (pattern === 'waves') {
    const centre = { x: width / 2, y: height * 1.2 };
    const furthest = Math.hypot(Math.max(centre.x, width - centre.x), centre.y);
    const rings = Math.ceil(furthest / 60);

    return (
      <Svg style={StyleSheet.absoluteFill} width={width} height={height}>
        {Array.from({ length: rings }, (_, ring) => (
          <Circle
            key={ring}
            cx={centre.x}
            cy={centre.y}
            r={ring * 60 + 11}
            stroke={primary}
            strokeOpacity={0.12}
            strokeWidth={22}
            fill="none"
          />
        ))}
      </Svg>
    );
  }

  if (pattern === 'bubbles') {
    return (
      <Svg style={StyleSheet.absoluteFill} width={width} height={height}>
        <Circle cx={width * 0.15} cy={height * 0.25} r={70} fill={primary} fillOpacity={0.16} />
        <Circle cx={width * 0.8} cy={height * 0.6} r={110} fill={tertiary} fillOpacity={0.16} />
        <Circle cx={width * 0.45} cy={height * 0.9} r={90} fill={secondary} fillOpacity={0.16} />
      </Svg>
    );
  }

  return null;
}

export default function Backdrop({ backdrop }: Props) {
  const theme = useTheme();

  if (!backdrop) {
    return null;
  }

  if (backdrop.pictureId) {
    return (
      <View style={StyleSheet.absoluteFill} pointerEvents="none">
        <Image
          source={pictureSource(backdrop.pictureId)}
          style={StyleSheet.absoluteFill}
          contentFit="cover"
        />
        <View
          style={[
            StyleSheet.absoluteFill,
            {
              backgroundColor: theme.colors.background,
              opacity: (backdrop.dimming ?? 40) / 100,
            },
          ]}
        />
      </View>
    );
  }

  if (backdrop.pattern === 'plain') {
    return null;
  }

  return (
    <View style={StyleSheet.absoluteFill} pointerEvents="none">
      <Patterned pattern={backdrop.pattern} />
    </View>
  );
}
