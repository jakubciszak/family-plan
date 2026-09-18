import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Dialog, Portal, Text, TextInput } from 'react-native-paper';

import { rejectExecution, type TaskExecution } from '@/api/tasks';

export default function ReturnTaskDialog({ task, onClose, onReturned }: {
  task: TaskExecution;
  onClose: () => void;
  onReturned: () => Promise<void>;
}) {
  const { t } = useTranslation();
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(false);
  const submit = async () => {
    if (busy || !reason.trim() || reason.trim().length > 500) return;
    setBusy(true);
    setError(false);
    try {
      await rejectExecution(task.id, reason.trim());
      onClose();
      await onReturned();
    } catch {
      setError(true);
    } finally {
      setBusy(false);
    }
  };

  return <Portal><Dialog visible dismissable={!busy} onDismiss={onClose}>
    <Dialog.Title>{t('member.rejectHeadline', { name: task.name })}</Dialog.Title>
    <Dialog.Content style={{ gap: 12 }}>
      {error && <Text accessibilityRole="alert">{t('tasks.returnFailed')}</Text>}
      <TextInput mode="outlined" label={t('member.reasonLabel')} accessibilityLabel={t('member.reasonLabel')}
        value={reason} onChangeText={setReason} multiline maxLength={500} disabled={busy} />
      <Text variant="bodySmall">{t('member.reasonHint')}</Text>
    </Dialog.Content>
    <Dialog.Actions>
      <Button disabled={busy} onPress={onClose}>{t('common.cancel')}</Button>
      <Button loading={busy} disabled={busy || !reason.trim() || reason.trim().length > 500} onPress={() => void submit()}>{t('member.reject')}</Button>
    </Dialog.Actions>
  </Dialog></Portal>;
}
