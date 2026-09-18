import * as Clipboard from 'expo-clipboard';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RefreshControl, ScrollView, Share, StyleSheet, View } from 'react-native';
import {
  ActivityIndicator,
  Banner,
  Button,
  Card,
  Chip,
  Dialog,
  Divider,
  IconButton,
  List,
  Portal,
  SegmentedButtons,
  Snackbar,
  Text,
  TextInput,
  useTheme,
} from 'react-native-paper';

import {
  acceptInvitation,
  createTeam,
  inviteToTeam,
  readTeamMembers,
  listMyInvitations,
  listTeams,
  removeMember,
  updateTeam,
  type Invitation,
  type Member,
  type Team,
  type TeamRole,
} from '@/api/teams';
import { useAuth } from '@/auth/auth-context';
import Avatar from '@/components/avatar';
import { useScreenBackground } from '@/personalisation/use-screen-background';

type Crew = Record<string, Member[]>;

export default function TeamsScreen() {
  const { t } = useTranslation();
  const { refreshMemberships, isSuperAdmin } = useAuth();
  const theme = useTheme();
  const ground = useScreenBackground();

  const [teams, setTeams] = useState<Team[]>([]);
  const [crew, setCrew] = useState<Crew>({});
  const [pending, setPending] = useState<Record<string, Invitation[]>>({});
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [said, setSaid] = useState<string | null>(null);

  const [making, setMaking] = useState(false);
  const [newName, setNewName] = useState('');
  const [newDescription, setNewDescription] = useState('');

  const [renaming, setRenaming] = useState<Team | null>(null);
  const [renamedName, setRenamedName] = useState('');
  const [renamedDescription, setRenamedDescription] = useState('');

  const [inviting, setInviting] = useState<Team | null>(null);
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState<TeamRole>('member');

  const [dropping, setDropping] = useState<{
    team: Team;
    member: Member;
  } | null>(null);

  const load = useCallback(async () => {
    try {
      const [mine, waiting] = await Promise.all([listTeams(), listMyInvitations()]);
      setTeams(mine);
      setInvitations(waiting);
      const groups = await Promise.all(
        mine.map(async (team) => ({
          teamId: team.id,
          ...(await readTeamMembers(team.id)),
        })),
      );
      setCrew(Object.fromEntries(groups.map((group) => [group.teamId, group.members])));
      setPending(
        Object.fromEntries(groups.map((group) => [group.teamId, group.invitations ?? []])),
      );
      setError(null);
    } catch {
      setError(t('teams.errorLoadingTeams'));
    }
  }, [t]);

  useFocusEffect(
    useCallback(() => {
      void load().finally(() => setLoading(false));
    }, [load]),
  );

  const refresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const attempt = async (action: () => Promise<unknown>, wentWrong: string, worked?: string) => {
    setBusy(true);

    try {
      await action();
      await Promise.all([load(), refreshMemberships()]);

      if (worked) {
        setSaid(worked);
      }
    } catch {
      setError(wentWrong);
    } finally {
      setBusy(false);
    }
  };

  if (loading) {
    return (
      <View style={[styles.centre, { backgroundColor: ground }]}>
        <ActivityIndicator />
      </View>
    );
  }

  return (
    <View style={[styles.screen, { backgroundColor: ground }]}>
      <Banner
        visible={Boolean(error)}
        actions={[{ label: t('common.close'), onPress: () => setError(null) }]}
      >
        {error ?? ''}
      </Banner>

      <ScrollView
        contentContainerStyle={styles.page}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => void refresh()} />}
      >
        {invitations.length ? (
          <Card mode="elevated" style={styles.card}>
            <Card.Title title={t('teams.pendingInvitations')} titleVariant="titleMedium" />
            {invitations.map((invitation) => (
              <List.Item
                key={invitation.id}
                onPress={() =>
                  void attempt(
                    () => acceptInvitation(invitation.token),
                    t('teams.errorAcceptingInvitation'),
                    t('teams.invitationAccepted'),
                  )
                }
                title={invitation.teamName}
                description={t('teams.invitationText', {
                  role: t(invitation.role === 'admin' ? 'teams.roleAdmin' : 'teams.roleMember'),
                })}
                right={() => (
                  <Button
                    mode="contained-tonal"
                    disabled={busy}
                    onPress={() =>
                      void attempt(
                        () => acceptInvitation(invitation.token),
                        t('teams.errorAcceptingInvitation'),
                        t('teams.invitationAccepted'),
                      )
                    }
                  >
                    {t('teams.acceptInvitation')}
                  </Button>
                )}
              />
            ))}
          </Card>
        ) : null}

        {teams.map((team) => (
          <Card key={team.id} mode="elevated" style={styles.card} testID={`team-${team.name}`}>
            <Card.Title
              title={team.name}
              titleVariant="titleMedium"
              subtitle={team.description ?? undefined}
              right={() => (
                <View style={styles.titleSide}>
                  <Chip compact>
                    {t(team.role === 'admin' ? 'teams.roleAdmin' : 'teams.roleMember')}
                  </Chip>
                  {isSuperAdmin || team.role === 'admin' ? (
                    <IconButton
                      icon="pencil-outline"
                      accessibilityLabel={t('common.edit')}
                      disabled={busy}
                      onPress={() => {
                        setRenaming(team);
                        setRenamedName(team.name);
                        setRenamedDescription(team.description ?? '');
                      }}
                    />
                  ) : null}
                </View>
              )}
            />
            <Divider />

            <List.Subheader>{t('teams.members')}</List.Subheader>
            {(crew[team.id] ?? []).map((member) => (
              <List.Item
                key={member.id}
                title={member.face?.nickname || member.givenName || member.userName}
                description={member.userEmail}
                onPress={
                  isSuperAdmin || team.role === 'admin'
                    ? () =>
                        router.push({
                          pathname: '/member',
                          params: {
                            from: 'teams',
                            id: member.userId,
                            name: member.face?.nickname || member.givenName || member.userName,
                          },
                        })
                    : undefined
                }
                left={() => (
                  <View style={styles.face}>
                    <Avatar
                      avatar={member.face?.avatar}
                      name={member.face?.nickname || member.userName}
                      size={40}
                    />
                  </View>
                )}
                right={() =>
                  (isSuperAdmin || team.role === 'admin') && member.role !== 'admin' ? (
                    <IconButton
                      icon="account-remove-outline"
                      accessibilityLabel={t('teams.remove')}
                      disabled={busy}
                      onPress={() => setDropping({ team, member })}
                    />
                  ) : (
                    <Chip compact style={styles.roleChip}>
                      {t(member.role === 'admin' ? 'teams.roleAdmin' : 'teams.roleMember')}
                    </Chip>
                  )
                }
              />
            ))}

            {(pending[team.id] ?? []).length ? (
              <Card.Content style={{ gap: 12 }}>
                <Text variant="titleMedium">{t('teams.pendingInvitations')}</Text>
                <Text variant="bodySmall">{t('teams.pendingInvitationsHint')}</Text>
                {(pending[team.id] ?? []).map((invitation) => (
                  <View
                    key={invitation.id}
                    style={{ gap: 8 }}
                    testID={`pending-invitation-${invitation.id}`}
                  >
                    <Text>
                      {invitation.email} ·{' '}
                      {t(invitation.role === 'admin' ? 'teams.roleAdmin' : 'teams.roleMember')}
                    </Text>
                    <Text variant="bodySmall">
                      {t(
                        invitation.accountExists
                          ? 'teams.invitationAwaitingAcceptance'
                          : 'teams.invitationNeedsRegistration',
                      )}
                    </Text>
                    {invitation.expiresAt ? (
                      <Text variant="bodySmall">
                        {t('teams.expiresAt')}: {invitation.expiresAt.slice(0, 10)}
                      </Text>
                    ) : null}
                    {invitation.invitationUrl ? (
                      <>
                        <TextInput
                          mode="outlined"
                          accessibilityLabel={t('teams.invitationLink')}
                          label={t('teams.invitationLink')}
                          value={invitation.invitationUrl}
                          readOnly
                          selectTextOnFocus
                        />
                        <Button
                          accessibilityLabel={t('teams.copyLink')}
                          icon="content-copy"
                          onPress={() =>
                            void Clipboard.setStringAsync(invitation.invitationUrl!)
                              .then(() => setSaid(t('teams.linkCopied')))
                              .catch(() => setError(t('errors.generic')))
                          }
                        >
                          {t('teams.copyLink')}
                        </Button>
                        <Button
                          accessibilityLabel={t('teams.shareInvitationLink')}
                          icon="share-variant"
                          onPress={() =>
                            void Share.share({
                              message: invitation.invitationUrl!,
                            }).catch(() => setError(t('errors.generic')))
                          }
                        >
                          {t('teams.shareInvitationLink')}
                        </Button>
                      </>
                    ) : null}
                  </View>
                ))}
              </Card.Content>
            ) : null}
            {isSuperAdmin || team.role === 'admin' ? (
              <Card.Actions>
                <Button
                  mode="contained-tonal"
                  icon="account-plus-outline"
                  onPress={() => {
                    setInviting(team);
                    setInviteEmail('');
                    setInviteRole('member');
                  }}
                >
                  {t('teams.inviteMember')}
                </Button>
              </Card.Actions>
            ) : null}
          </Card>
        ))}

        <Button mode="contained" icon="plus" onPress={() => setMaking(true)}>
          {t('teams.createTeam')}
        </Button>
      </ScrollView>

      <Portal>
        <Dialog visible={making} onDismiss={() => setMaking(false)}>
          <Dialog.Title>{t('teams.createTeam')}</Dialog.Title>
          <Dialog.Content style={styles.dialog}>
            <TextInput
              mode="outlined"
              label={t('teams.teamName')}
              accessibilityLabel={t('teams.teamName')}
              value={newName}
              onChangeText={setNewName}
            />
            <TextInput
              mode="outlined"
              label={t('teams.teamDescription')}
              accessibilityLabel={t('teams.teamDescription')}
              value={newDescription}
              onChangeText={setNewDescription}
              multiline
            />
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setMaking(false)}>{t('common.cancel')}</Button>
            <Button
              disabled={!newName.trim() || busy}
              onPress={() => {
                setMaking(false);
                void attempt(
                  () => createTeam(newName.trim(), newDescription.trim()),
                  t('teams.errorCreatingTeam'),
                ).then(() => {
                  setNewName('');
                  setNewDescription('');
                });
              }}
            >
              {t('teams.create')}
            </Button>
          </Dialog.Actions>
        </Dialog>

        <Dialog visible={Boolean(renaming)} onDismiss={() => setRenaming(null)}>
          <Dialog.Title>{t('teams.editTeam')}</Dialog.Title>
          <Dialog.Content style={styles.dialog}>
            <TextInput
              mode="outlined"
              label={t('teams.teamName')}
              accessibilityLabel={t('teams.teamName')}
              value={renamedName}
              onChangeText={setRenamedName}
            />
            <TextInput
              mode="outlined"
              label={t('teams.teamDescription')}
              accessibilityLabel={t('teams.teamDescription')}
              value={renamedDescription}
              onChangeText={setRenamedDescription}
              multiline
            />
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setRenaming(null)}>{t('common.cancel')}</Button>
            <Button
              disabled={!renamedName.trim() || busy}
              onPress={() => {
                const team = renaming;
                setRenaming(null);

                if (team) {
                  void attempt(
                    () => updateTeam(team.id, renamedName.trim(), renamedDescription.trim()),
                    t('teams.errorUpdatingTeam'),
                    t('teams.teamUpdated'),
                  );
                }
              }}
            >
              {t('common.save')}
            </Button>
          </Dialog.Actions>
        </Dialog>

        <Dialog visible={Boolean(inviting)} onDismiss={() => setInviting(null)}>
          <Dialog.Title>{t('teams.inviteMember')}</Dialog.Title>
          <Dialog.Content style={styles.dialog}>
            <TextInput
              mode="outlined"
              label={t('teams.email')}
              accessibilityLabel={t('teams.email')}
              value={inviteEmail}
              onChangeText={setInviteEmail}
              autoCapitalize="none"
              keyboardType="email-address"
              inputMode="email"
            />
            <SegmentedButtons
              value={inviteRole}
              onValueChange={(role) => setInviteRole(role as TeamRole)}
              buttons={[
                { value: 'member', label: t('teams.roleMember') },
                { value: 'admin', label: t('teams.roleAdmin') },
              ]}
            />
            <Text variant="bodySmall" style={{ color: theme.colors.onSurfaceVariant }}>
              {t('teams.pendingInvitationsHint')}
            </Text>
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setInviting(null)}>{t('common.cancel')}</Button>
            <Button
              disabled={!inviteEmail.trim() || busy}
              onPress={() => {
                const team = inviting;
                setInviting(null);

                if (team) {
                  void attempt(
                    () => inviteToTeam(team.id, inviteEmail.trim(), inviteRole),
                    t('teams.errorSendingInvitation'),
                    t('teams.invitationSent'),
                  );
                }
              }}
            >
              {t('teams.sendInvitation')}
            </Button>
          </Dialog.Actions>
        </Dialog>

        <Dialog visible={Boolean(dropping)} onDismiss={() => setDropping(null)}>
          <Dialog.Title>{t('teams.remove')}</Dialog.Title>
          <Dialog.Content>
            <Text variant="bodyMedium">{t('teams.confirmRemoveMember')}</Text>
          </Dialog.Content>
          <Dialog.Actions>
            <Button onPress={() => setDropping(null)}>{t('common.cancel')}</Button>
            <Button
              textColor={theme.colors.error}
              onPress={() => {
                const pair = dropping;
                setDropping(null);

                if (pair) {
                  void attempt(
                    () => removeMember(pair.team.id, pair.member.userId),
                    t('teams.errorRemovingMember'),
                  );
                }
              }}
            >
              {t('teams.remove')}
            </Button>
          </Dialog.Actions>
        </Dialog>
      </Portal>

      <Snackbar visible={Boolean(said)} onDismiss={() => setSaid(null)} duration={3000}>
        {said ?? ''}
      </Snackbar>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
  },
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
  titleSide: {
    alignItems: 'center',
    flexDirection: 'row',
    paddingRight: 4,
  },
  roleChip: {
    marginRight: 12,
  },
  face: {
    justifyContent: 'center',
    paddingLeft: 16,
  },
  dialog: {
    gap: 12,
  },
});
