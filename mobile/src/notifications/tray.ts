/** While the app is closed the system shows a Firebase notification itself, and expo names it by this, with the tag inside. */
const SHOWN_BY_SYSTEM = /^expo-notifications:\/\/foreign_notifications\?/;

/** The server's tag of a notification in the tray, whoever put it there. */
export const tagOf = (identifier: string, data: Record<string, unknown> = {}): string => {
  if (typeof data.tag === 'string') return data.tag;
  if (!SHOWN_BY_SYSTEM.test(identifier)) return identifier;
  const tag = /[?&]tag=([^&]*)/.exec(identifier)?.[1];
  return tag ? decodeURIComponent(tag) : identifier;
};

/** The tags a retraction from the server names (FcmPushSender::retract), or none for anything else. */
export const retractedTags = (data: Record<string, unknown> | null | undefined): string[] => {
  if (data?.type !== 'retract' || typeof data.tags !== 'string') return [];
  try {
    const tags: unknown = JSON.parse(data.tags);
    return Array.isArray(tags) ? tags.filter((tag): tag is string => typeof tag === 'string' && tag !== '') : [];
  } catch {
    return [];
  }
};
