import { ImageManipulator, SaveFormat } from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';

import { keepPicture, type KeptPicture, type Purpose } from '@/api/pictures';

const LARGEST: Record<Purpose, number> = { avatar: 512, backdrop: 1600 };

const QUALITY: Record<Purpose, number> = { avatar: 0.9, backdrop: 0.82 };

/**
 * Shrinks a picked image on the device, so only something small and sane ever leaves it.
 */
const shrinkForUpload = async (uri: string, purpose: Purpose): Promise<string> => {
  const context = ImageManipulator.manipulate(uri);
  const loaded = await context.renderAsync();

  const longest = LARGEST[purpose];
  const scale = Math.min(1, longest / Math.max(loaded.width, loaded.height));

  const shrunk =
    scale < 1
      ? await context
          .resize({
            width: Math.max(1, Math.round(loaded.width * scale)),
            height: Math.max(1, Math.round(loaded.height * scale)),
          })
          .renderAsync()
      : loaded;

  const saved = await shrunk.saveAsync({
    base64: true,
    compress: QUALITY[purpose],
    format: SaveFormat.JPEG,
  });

  if (!saved.base64) {
    throw new Error('no bytes came back from the manipulator');
  }

  return `data:image/jpeg;base64,${saved.base64}`;
};

export const pickAndKeep = async (purpose: Purpose): Promise<KeptPicture | null> => {
  const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();

  if (!permission.granted) {
    throw new Error('no permission to read pictures');
  }

  const picked = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ['images'],
    allowsEditing: purpose === 'avatar',
    aspect: purpose === 'avatar' ? [1, 1] : undefined,
    quality: 1,
  });

  if (picked.canceled || !picked.assets?.[0]) {
    return null;
  }

  return keepPicture(purpose, await shrinkForUpload(picked.assets[0].uri, purpose));
};
