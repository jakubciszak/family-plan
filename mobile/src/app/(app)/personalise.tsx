import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ScrollView, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Button,
  Card,
  Divider,
  List,
  Switch,
  Text,
  TextInput,
  TouchableRipple,
  useTheme,
} from 'react-native-paper';

import Celebration from '@/components/celebration';
import AvatarPicker from '@/components/avatar-picker';
import BackdropPicker from '@/components/backdrop-picker';
import LayoutArranger from '@/components/layout-arranger';
import { usePersonalisation } from '@/personalisation/personalisation-context';
import { useScreenBackground } from '@/personalisation/use-screen-background';

const COLOURS = [
  '#2e7d5b',
  '#1565c0',
  '#8e24aa',
  '#d81b60',
  '#ef6c00',
  '#00838f',
  '#5d4037',
  '#455a64',
  '#c62828',
  '#6a1b9a',
  '#2e7d32',
  '#f9a825',
];

export default function PersonaliseScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const ground = useScreenBackground();
  const { own, loading, save, reload } = usePersonalisation();
  const [nicknameDraft, setNickname] = useState<string | null>(null);
  const [colourDraft, setColourDraft] = useState<string | null>(null);
  const [celebrating, setCelebrating] = useState(false);
  const endCelebration = useCallback(() => setCelebrating(false), []);
  const colour = colourDraft ?? own?.theme ?? '';
  const nickname = nicknameDraft ?? own?.nickname ?? '';

  if (loading || !own) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        {loading ? (
          <ActivityIndicator />
        ) : (
          <>
            <Text>{t('errors.generic')}</Text>
            <Button onPress={() => void reload()}>{t('common.retry')}</Button>
          </>
        )}
      </View>
    );
  }

  return (
    <ScrollView style={{ backgroundColor: ground }} contentContainerStyle={styles.page}>
      {celebrating ? <Celebration withSound={own.makesSound} onDone={endCelebration} /> : null}
      <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
        {t('personalise.lead')}
      </Text>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('personalise.meSection')} titleVariant="titleMedium" />
        <Card.Content>
          <TextInput
            mode="outlined"
            label={t('personalise.nickname')}
            accessibilityLabel={t('personalise.nickname')}
            value={nickname}
            onChangeText={setNickname}
            onBlur={() => {
              if (nickname !== (own.nickname ?? '')) {
                void save({ nickname }).then(() => setNickname(null));
              }
            }}
            maxLength={40}
          />
          <Text variant="bodySmall" style={styles.hint}>
            {t('personalise.nicknameHint')}
          </Text>

          <View style={styles.avatar}>
            <AvatarPicker
              avatar={own.avatar}
              name={own.nickname}
              onPick={(avatar) => save({ avatar: { ...own.avatar, ...avatar } })}
            />
          </View>
        </Card.Content>
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('personalise.colourSection')} titleVariant="titleMedium" />
        <Card.Content>
          <View style={styles.swatches}>
            {COLOURS.map((colour) => (
              <TouchableRipple
                key={colour}
                borderless
                accessibilityRole="button"
                accessibilityLabel={colour}
                accessibilityState={{ selected: own.theme === colour }}
                onPress={() => void save({ theme: colour })}
                style={[
                  styles.swatch,
                  { backgroundColor: colour },
                  own.theme === colour && {
                    borderColor: theme.colors.onSurface,
                    borderWidth: 3,
                  },
                ]}
              >
                <View />
              </TouchableRipple>
            ))}
          </View>
          <TextInput
            mode="outlined"
            label={t('personalise.ownColour')}
            accessibilityLabel={t('personalise.ownColour')}
            value={colour}
            onChangeText={setColourDraft}
            autoCapitalize="none"
            maxLength={7}
            error={Boolean(colourDraft) && !/^#[0-9a-f]{6}$/i.test(colour)}
            style={{ marginTop: 16 }}
          />
          <Button
            disabled={!/^#[0-9a-f]{6}$/i.test(colour)}
            onPress={() =>
              void save({ theme: colour.toLowerCase() }).then(() => setColourDraft(null))
            }
          >
            {t('common.save')}
          </Button>
        </Card.Content>
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('personalise.backdropSection')} titleVariant="titleMedium" />
        <Card.Content>
          <BackdropPicker
            backdrop={own.backdrop}
            onPick={(backdrop) => save({ backdrop: { ...own.backdrop, ...backdrop } })}
          />
        </Card.Content>
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('personalise.homeSection')} titleVariant="titleMedium" />
        <LayoutArranger
          all={own.places.home}
          chosen={own.home}
          labelFor={(place) => t(`personalise.places.home.${place}`)}
          onChange={(home) => void save({ home })}
          testID="arranger-home"
        />
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('personalise.navSection')} titleVariant="titleMedium" />
        <LayoutArranger
          all={own.places.navigation}
          chosen={own.navigation}
          labelFor={(place) => t(`personalise.places.navigation.${place}`)}
          onChange={(navigation) => void save({ navigation })}
          testID="arranger-navigation"
        />
      </Card>

      <Card mode="elevated" style={styles.card}>
        <Card.Title title={t('personalise.celebrationSection')} titleVariant="titleMedium" />
        <List.Item
          onPress={() => void save({ celebrates: !own.celebrates })}
          title={t('personalise.celebrates')}
          right={() => (
            <View pointerEvents="none">
              <Switch value={own.celebrates} />
            </View>
          )}
        />
        <Divider />
        <List.Item
          onPress={() => void save({ makesSound: !own.makesSound })}
          title={t('personalise.makesSound')}
          right={() => (
            <View pointerEvents="none">
              <Switch value={own.makesSound} />
            </View>
          )}
        />
        <Card.Actions>
          <Button
            accessibilityLabel={t('personalise.tryIt')}
            icon="star-four-points-outline"
            onPress={() => setCelebrating(true)}
          >
            {t('personalise.tryIt')}
          </Button>
        </Card.Actions>
      </Card>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  centre: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
  },
  page: {
    gap: 16,
    padding: 16,
    paddingBottom: 32,
  },
  card: {
    borderRadius: 16,
  },
  hint: {
    marginTop: 4,
    opacity: 0.7,
  },
  swatches: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  swatch: {
    borderRadius: 24,
    height: 48,
    width: 48,
  },
  avatar: {
    marginTop: 16,
  },
});
