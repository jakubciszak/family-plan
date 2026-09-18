import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';
import { Divider, IconButton, List, Switch, Text } from 'react-native-paper';

type Props = {
  all: string[];
  chosen: string[];
  labelFor: (place: string) => string;
  onChange: (next: string[]) => void;
  testID?: string;
};

export default function LayoutArranger({ all, chosen, labelFor, onChange, testID }: Props) {
  const { t } = useTranslation();

  const shown = chosen.filter((place) => all.includes(place));
  const hidden = all.filter((place) => !shown.includes(place));

  const move = (from: number, to: number) => {
    if (to < 0 || to >= shown.length) {
      return;
    }

    const next = [...shown];
    const [lifted] = next.splice(from, 1);
    next.splice(to, 0, lifted);
    onChange(next);
  };

  return (
    <View testID={testID}>
      {shown.length ? (
        shown.map((place, index) => (
          <View key={place}>
            <View style={styles.row}>
              <Text variant="bodyLarge" style={styles.label} numberOfLines={1}>
                {labelFor(place)}
              </Text>
              <IconButton
                icon="arrow-up"
                accessibilityLabel={t('personalise.moveUp')}
                disabled={index === 0}
                onPress={() => move(index, index - 1)}
              />
              <IconButton
                icon="arrow-down"
                accessibilityLabel={t('personalise.moveDown')}
                disabled={index === shown.length - 1}
                onPress={() => move(index, index + 1)}
              />
              <Switch
                value
                accessibilityLabel={t('personalise.shown')}
                onValueChange={() => onChange(shown.filter((kept) => kept !== place))}
              />
            </View>
            <Divider />
          </View>
        ))
      ) : (
        <Text variant="bodyMedium" style={styles.empty}>
          {t('personalise.nothingShown')}
        </Text>
      )}

      {hidden.map((place) => (
        <List.Item
          key={place}
          onPress={() => onChange([...shown, place])}
          title={labelFor(place)}
          titleStyle={styles.hiddenLabel}
          right={() => (
            <View pointerEvents="none">
              <Switch value={false} accessibilityLabel={t('personalise.shown')} />
            </View>
          )}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    alignItems: 'center',
    flexDirection: 'row',
    paddingLeft: 16,
    paddingRight: 8,
  },
  label: {
    flex: 1,
  },
  hiddenLabel: {
    opacity: 0.6,
  },
  empty: {
    padding: 16,
  },
});
