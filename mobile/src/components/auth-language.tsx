import AsyncStorage from '@react-native-async-storage/async-storage';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { SegmentedButtons } from 'react-native-paper';

export default function AuthLanguage() {
  const { i18n } = useTranslation();
  useEffect(() => {
    let active = true;
    void AsyncStorage.getItem('familyplan.language')
      .then((saved) => {
        if (active && (saved === 'pl' || saved === 'en')) void i18n.changeLanguage(saved);
      })
      .catch(() => undefined);
    return () => {
      active = false;
    };
  }, [i18n]);
  return (
    <View style={{ alignSelf: 'center', marginTop: 16 }}>
      <SegmentedButtons
        value={i18n.language.startsWith('en') ? 'en' : 'pl'}
        onValueChange={(language) => {
          void i18n.changeLanguage(language);
          void AsyncStorage.setItem('familyplan.language', language).catch(() => undefined);
        }}
        buttons={[
          { value: 'pl', label: 'PL' },
          { value: 'en', label: 'EN' },
        ]}
      />
    </View>
  );
}
