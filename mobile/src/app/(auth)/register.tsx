import AuthLanguage from '@/components/auth-language';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { Banner, Button, HelperText, Surface, Text, TextInput, useTheme } from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';

import { addressInvited, register } from '@/api/auth';
import { ApiError, OutOfReachError } from '@/api/client';
import { useAuth } from '@/auth/auth-context';

export default function RegisterScreen() {
  const { t } = useTranslation();
  const theme = useTheme();
  const { signIn } = useAuth();
  const { invite } = useLocalSearchParams<{ invite?: string }>();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [phoneNumber, setPhoneNumber] = useState('');
  const [secure, setSecure] = useState(true);
  const [emailLocked, setEmailLocked] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!invite) {
      return undefined;
    }

    let cancelled = false;

    addressInvited(invite)
      .then((invited) => {
        if (!cancelled && invited) {
          setEmail(invited);
          setEmailLocked(true);
        }
      })
      .catch(() => undefined);

    return () => {
      cancelled = true;
    };
  }, [invite]);

  const canSubmit =
    name.trim().length > 0 && email.trim().length > 0 && password.length > 0 && !submitting;

  const explain = (cause: unknown): string => {
    if (cause instanceof OutOfReachError) {
      return t('errors.outOfReach', { where: cause.where });
    }

    const said = cause instanceof ApiError ? (cause.body as { error?: string })?.error : null;

    if (said?.includes('already exists')) {
      return t('auth.duplicateEmail');
    }

    return t('auth.registerError');
  };

  const submit = async () => {
    setSubmitting(true);
    setError(null);
    setDone(null);

    try {
      const outcome = await register({
        name: name.trim(),
        email: email.trim(),
        password,
        ...(phoneNumber.trim() ? { phoneNumber: phoneNumber.trim() } : {}),
        ...(invite ? { inviteToken: invite } : {}),
      });

      if (!outcome.activationRequired) {
        await signIn(email.trim(), password, invite);

        return;
      }

      setDone(t('auth.registerSuccess'));
      setName('');
      setPassword('');
      setPhoneNumber('');
    } catch (cause) {
      setError(explain(cause));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={[styles.screen, { backgroundColor: theme.colors.background }]}>
      <KeyboardAvoidingView
        style={styles.screen}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
          <Surface style={styles.card} elevation={1}>
            <Text variant="headlineMedium" style={styles.heading}>
              {t('auth.register')}
            </Text>

            {invite ? (
              <Banner visible icon="account-multiple-plus-outline">
                {`${t('auth.invitedToTeam')} ${t('auth.registerToJoin')}`}
              </Banner>
            ) : null}

            <TextInput
              mode="outlined"
              label={t('auth.name')}
              accessibilityLabel={t('auth.name')}
              value={name}
              onChangeText={setName}
              autoComplete="name"
              textContentType="name"
              left={<TextInput.Icon icon="account-outline" />}
            />

            <TextInput
              mode="outlined"
              label={t('auth.email')}
              accessibilityLabel={t('auth.email')}
              value={email}
              onChangeText={setEmail}
              editable={!emailLocked}
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
              autoComplete="new-password"
              secureTextEntry={secure}
              textContentType="newPassword"
              left={<TextInput.Icon icon="lock-outline" />}
              right={
                <TextInput.Icon
                  icon={secure ? 'eye-outline' : 'eye-off-outline'}
                  accessibilityLabel={t(secure ? 'auth.showPassword' : 'auth.hidePassword')}
                  onPress={() => setSecure((on) => !on)}
                />
              }
            />

            <TextInput
              mode="outlined"
              label={t('auth.phoneNumberOptional')}
              accessibilityLabel={t('auth.phoneNumberOptional')}
              value={phoneNumber}
              onChangeText={setPhoneNumber}
              autoComplete="tel"
              keyboardType="phone-pad"
              inputMode="tel"
              textContentType="telephoneNumber"
              left={<TextInput.Icon icon="phone-outline" />}
              onSubmitEditing={() => canSubmit && void submit()}
            />

            <HelperText type={done ? 'info' : 'error'} visible={Boolean(error ?? done)}>
              {error ?? done}
            </HelperText>

            <Button
              mode="contained"
              disabled={!canSubmit}
              loading={submitting}
              onPress={() => void submit()}
              contentStyle={styles.buttonContent}>
              {t('auth.register')}
            </Button>
          </Surface>

          <View style={styles.footer}>
            <Text variant="bodyMedium" style={{ color: theme.colors.onSurfaceVariant }}>
              {t('auth.alreadyHaveAccount')}
            </Text>
            <Button mode="text" onPress={() => router.replace({ pathname: '/login', params: invite ? { invite } : {} })}>
              {t('auth.login')}
            </Button>
          </View>
          <AuthLanguage />
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },
  page: {
    flexGrow: 1,
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
    marginTop: 16,
  },
});
