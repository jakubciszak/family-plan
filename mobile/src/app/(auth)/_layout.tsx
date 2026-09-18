import { Redirect, Stack } from 'expo-router';

import { useAuth } from '@/auth/auth-context';

export default function AuthLayout() {
  const { user, restoring } = useAuth();

  if (restoring) {
    return null;
  }

  if (user) {
    return <Redirect href="/" />;
  }

  return <Stack screenOptions={{ headerShown: false }} />;
}
