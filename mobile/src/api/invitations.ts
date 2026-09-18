import { acceptInvitation } from './teams';

/**
 * A pending invitation only becomes a membership once the account accepts it, and
 * the account cannot do that until it is signed in. Registering or logging in
 * with a token in hand therefore has to take it up afterwards.
 */
export const takeUpInvitation = async (token?: string): Promise<void> => {
  if (!token) {
    return;
  }

  await acceptInvitation(token).catch(() => undefined);
};
