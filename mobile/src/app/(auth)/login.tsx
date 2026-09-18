import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { KeyboardAvoidingView, Platform, StyleSheet, View } from 'react-native';
import { Button, HelperText, Surface, Switch, Text, TextInput, useTheme } from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';

import AuthLanguage from '@/components/auth-language';
import { ApiError, OutOfReachError } from '@/api/client';
import { useAuth } from '@/auth/auth-context';

export default function LoginScreen() {
  const { t } = useTranslation();
  const { signIn } = useAuth();
  const theme = useTheme();
  const { invite } = useLocalSearchParams<{ invite?: string }>();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(true);
  const [secure, setSecure] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const canSubmit = email.trim().length > 0 && password.length > 0 && !submitting;

  const explain = (cause: unknown): string => {
    if (cause instanceof OutOfReachError) {
      return t('errors.outOfReach', { where: cause.where });
    }

    if (cause instanceof ApiError && cause.status === 401) {
      return t('auth.loginError');
    }

    return t('errors.generic');
  };

  const submit = async () => {
    setSubmitting(true);
    setError(null);

    try {
      await signIn(email.trim(), password, invite, remember);
    } catch (cause) {
      setError(explain(cause));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={[styles.screen, { backgroundColor: theme.colors.background }]}>
      <KeyboardAvoidingView
        style={styles.centre}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <Surface style={styles.card} elevation={1}>
          <Text variant="headlineMedium" style={styles.heading}>
            {t('app.name', 'Family Plan')}
          </Text>

          <TextInput
            mode="outlined"
            label={t('auth.email')}
            accessibilityLabel={t('auth.email')}
            value={email}
            onChangeText={setEmail}
            autoCapitalize="none"
            autoComplete="email"
            keyboardType="email-address"
            inputMode="email"
            textContentType="emailAddress"
            left={<TextInput.Icon icon="email-outline" />}
          />

          <TextInput
            mode="outlined"
            label={t('auth.password')}
            accessibilityLabel={t('auth.password')}
            value={password}
            onChangeText={setPassword}
            autoCapitalize="none"
            autoComplete="current-password"
            secureTextEntry={secure}
            textContentType="password"
            left={<TextInput.Icon icon="lock-outline" />}
            right={
              <TextInput.Icon
                icon={secure ? 'eye-outline' : 'eye-off-outline'}
                accessibilityLabel={t(secure ? 'auth.showPassword' : 'auth.hidePassword')}
                onPress={() => setSecure((on) => !on)}
              />
            }
            onSubmitEditing={() => canSubmit && void submit()}
          />

          <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}><Text>{t('auth.rememberMe')}</Text><Switch accessibilityLabel={t('auth.rememberMe')} value={remember} onValueChange={setRemember} /></View>
          <HelperText type="error" visible={Boolean(error)}>
            {error}
          </HelperText>

          <Button
            mode="contained"
            disabled={!canSubmit}
            loading={submitting}
            onPress={() => void submit()}
            contentStyle={styles.buttonContent}>
            {t('auth.login')}
          </Button>
        </Surface>

        <View style={styles.footer}>
          <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
            {invite ? t('auth.loginToJoin') : t('auth.noAccount')}
          </Text>
          <Button
            mode="text"
            onPress={() =>
              router.replace({ pathname: '/register', params: invite ? { invite } : {} })
            }>
            {t('auth.register')}
          </Button>
        </View>
        <AuthLanguage />
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },
  centre: {
    flex: 1,
    justifyContent: 'center',
    padding: 24,
  },
  card: {
    borderRadius: 28,
    gap: 16,
    padding: 24,
  },
  heading: {
    marginBottom: 8,
    textAlign: 'center',
  },
  buttonContent: {
    paddingVertical: 6,
  },
  footer: {
    alignItems: 'center',
    marginTop: 24,
  },
});
