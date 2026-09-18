import { useTheme } from 'react-native-paper';

import { usePersonalisation } from './personalisation-context';

export const useDressedUp = (): boolean => {
  const { own } = usePersonalisation();
  const backdrop = own?.backdrop;

  return Boolean(backdrop && (backdrop.pictureId || backdrop.pattern !== 'plain'));
};

/**
 * Screens let a chosen backdrop through instead of painting over it.
 */
export const useScreenBackground = (): string => {
  const theme = useTheme();

  return useDressedUp() ? 'transparent' : theme.colors.background;
};
