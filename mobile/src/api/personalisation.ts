import apiClient from './client';

export type ThemeMode = 'light' | 'dark' | 'system';

export type Language = 'pl' | 'en';

export type Avatar = {
  style: string;
  seed: string;
  pictureId: string | null;
};

export type Backdrop = {
  pattern: string;
  pictureId: string | null;
  dimming: number;
};

export type Personalisation = {
  userId: string;
  nickname: string | null;
  theme: string;
  themeMode: ThemeMode;
  language: Language | null;
  avatar: Avatar;
  backdrop: Backdrop;
  home: string[];
  navigation: string[];
  celebrates: boolean;
  makesSound: boolean;
  places: {
    home: string[];
    navigation: string[];
  };
  choices: {
    themeModes: ThemeMode[];
    languages: Language[];
  };
};

export type PersonalisationChanges = Partial<
  Pick<
    Personalisation,
    'nickname' | 'theme' | 'themeMode' | 'language' | 'home' | 'navigation' | 'celebrates' | 'makesSound'
  >
> & {
  avatar?: Partial<Avatar>;
  backdrop?: Partial<Backdrop>;
};

export const readPersonalisation = (): Promise<Personalisation> =>
  apiClient.get<Personalisation>('/api/personalisation');

export const savePersonalisation = (changes: PersonalisationChanges): Promise<Personalisation> =>
  apiClient.put<Personalisation>('/api/personalisation', changes);
