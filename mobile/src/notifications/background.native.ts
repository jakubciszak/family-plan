import * as Notifications from 'expo-notifications';
import * as TaskManager from 'expo-task-manager';

import { retractedTags, tagOf } from '@/notifications/tray';

/**
 * When a notification stops being true (the other parent approved the task, the payout was settled, it was read
 * on another device), the server sends a silent message with its tag. Android runs this task for it also while
 * the app is closed, and the task takes the notification out of the tray.
 *
 * A task has to be defined before anything renders, which is why the entry file imports this module first.
 */
const RETRACT_TASK = 'family-plan-retract-notifications';

TaskManager.defineTask<Notifications.NotificationTaskPayload>(RETRACT_TASK, async ({ data }) => {
  const tags = new Set(data && !('actionIdentifier' in data) ? retractedTags(data.data) : []);
  if (tags.size === 0) return;

  const shown = await Notifications.getPresentedNotificationsAsync().catch(() => []);
  const matching = shown
    .filter(({ request }) => tags.has(tagOf(request.identifier, (request.content.data ?? {}) as Record<string, unknown>)))
    .map(({ request }) => request.identifier);

  // What the system showed for Firebase also answers to its bare tag, so nothing is missed if the list comes back empty.
  await Promise.all(
    [...new Set([...matching, ...tags])].map((identifier) => Notifications.dismissNotificationAsync(identifier).catch(() => undefined)),
  );
});

void Notifications.registerTaskAsync(RETRACT_TASK).catch(() => undefined);
