import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import teamService from '../services/teamService';
import {
    Button,
    Dialog,
    Icon,
    IconButton,
    Snackbar,
    TextField,
    CircularProgress,
} from '../components/md3';
import '../styles/TeamManagement.css';

const TeamManagement = ({ user, onMembershipChanged, onInspectMember }) => {
    const { t } = useTranslation();
    const [teams, setTeams] = useState([]);
    const [selectedTeam, setSelectedTeam] = useState(null);
    const [members, setMembers] = useState([]);
    const [invitations, setInvitations] = useState([]);
    const [pendingInvitations, setPendingInvitations] = useState([]);
    const [copiedInvitationId, setCopiedInvitationId] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [toast, setToast] = useState(null);
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [showInviteForm, setShowInviteForm] = useState(false);
    const [memberToRemove, setMemberToRemove] = useState(null);

    const [teamName, setTeamName] = useState('');
    const [teamDescription, setTeamDescription] = useState('');
    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState('member');

    useEffect(() => {
        loadTeams();
        loadMyInvitations();
    }, []);

    useEffect(() => {
        if (selectedTeam) {
            loadTeamMembers(selectedTeam.id);
        }
    }, [selectedTeam]);

    const loadTeams = async () => {
        try {
            setLoading(true);
            const response = await teamService.getTeams();
            setTeams(response.teams || []);
            setError(null);
        } catch (err) {
            setError(t('teams.errorLoadingTeams'));
            console.error('Error loading teams:', err);
        } finally {
            setLoading(false);
        }
    };

    const loadTeamMembers = async (teamId) => {
        try {
            const response = await teamService.getTeamMembers(teamId);
            setMembers(response.members || []);
            setPendingInvitations(response.invitations || []);
        } catch (err) {
            console.error('Error loading team members:', err);
        }
    };

    const loadMyInvitations = async () => {
        try {
            const response = await teamService.getMyInvitations();
            setInvitations(response.invitations || []);
        } catch (err) {
            console.error('Error loading invitations:', err);
        }
    };

    const handleCreateTeam = async (e) => {
        e.preventDefault();
        try {
            await teamService.createTeam({ name: teamName, description: teamDescription });
            setTeamName('');
            setTeamDescription('');
            setShowCreateForm(false);
            loadTeams();
            onMembershipChanged?.();
        } catch (err) {
            setError(t('teams.errorCreatingTeam'));
            console.error('Error creating team:', err);
        }
    };

    const handleInviteMember = async (e) => {
        e.preventDefault();
        if (!selectedTeam) return;

        try {
            await teamService.inviteToTeam(selectedTeam.id, { email: inviteEmail, role: inviteRole });
            setInviteEmail('');
            setInviteRole('member');
            setShowInviteForm(false);
            setToast(t('teams.invitationSent'));
            loadTeamMembers(selectedTeam.id);
        } catch (err) {
            setError(t('teams.errorSendingInvitation'));
            console.error('Error inviting member:', err);
        }
    };

    const handleCopyInvitationLink = async (invitation) => {
        try {
            await navigator.clipboard.writeText(invitation.invitationUrl);
            setCopiedInvitationId(invitation.id);
            setTimeout(() => setCopiedInvitationId(null), 2000);
        } catch (err) {
            console.error('Error copying invitation link:', err);
        }
    };

    const handleAcceptInvitation = async (token) => {
        try {
            await teamService.acceptInvitation(token);
            loadMyInvitations();
            loadTeams();
            onMembershipChanged?.();
            setToast(t('teams.invitationAccepted'));
        } catch (err) {
            setError(t('teams.errorAcceptingInvitation'));
            console.error('Error accepting invitation:', err);
        }
    };

    const handleRemoveMember = async () => {
        if (!selectedTeam || !memberToRemove) return;

        try {
            await teamService.removeMember(selectedTeam.id, memberToRemove.userId);
            loadTeamMembers(selectedTeam.id);
        } catch (err) {
            setError(t('teams.errorRemovingMember'));
            console.error('Error removing member:', err);
        } finally {
            setMemberToRemove(null);
        }
    };

    const iAdministerThisTeam = members.some(
        (entry) => entry.userId === user?.id && entry.role === 'admin'
    );

    const roleLabel = (role) =>
        t(`teams.role${role.charAt(0).toUpperCase()}${role.slice(1)}`);

    if (loading) {
        return <CircularProgress label={t('common.loading')} />;
    }

    return (
        <div className="team-management">
            <h1>{t('teams.title')}</h1>

            {error && (
                <div className="error-message" role="alert">
                    <Icon name="error" size={20} />
                    <span>{error}</span>
                </div>
            )}

            {invitations.length > 0 && (
                <div className="invitations-section">
                    <h2>{t('teams.pendingInvitations')}</h2>
                    <div className="invitations-list">
                        {invitations.map((invitation) => (
                            <div key={invitation.id} className="invitation-card">
                                <p>{t('teams.invitationText', { role: roleLabel(invitation.role) })}</p>
                                <Button icon="check" onClick={() => handleAcceptInvitation(invitation.token)}>
                                    {t('teams.acceptInvitation')}
                                </Button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="teams-section">
                <div className="teams-header">
                    <h2>{t('teams.myTeams')}</h2>
                    <Button
                        variant={showCreateForm ? 'text' : 'filled'}
                        icon={showCreateForm ? 'close' : 'add'}
                        onClick={() => setShowCreateForm(!showCreateForm)}
                    >
                        {showCreateForm ? t('common.cancel') : t('teams.createTeam')}
                    </Button>
                </div>

                {showCreateForm && (
                    <form onSubmit={handleCreateTeam} className="team-form">
                        <TextField
                            id="team-name"
                            type="text"
                            label={t('teams.teamName')}
                            value={teamName}
                            onChange={(e) => setTeamName(e.target.value)}
                            required
                            maxLength={255}
                        />
                        <TextField
                            as="textarea"
                            id="team-description"
                            label={t('teams.teamDescription')}
                            value={teamDescription}
                            onChange={(e) => setTeamDescription(e.target.value)}
                            rows={3}
                        />
                        <div className="form-actions">
                            <Button type="submit" tone="success" icon="check">
                                {t('teams.create')}
                            </Button>
                        </div>
                    </form>
                )}

                <div className="teams-list">
                    {teams.map((team) => (
                        <button
                            type="button"
                            key={team.id}
                            className={`team-card md-ripple-host ${selectedTeam?.id === team.id ? 'active' : ''}`}
                            aria-pressed={selectedTeam?.id === team.id}
                            onClick={() => setSelectedTeam(team)}
                        >
                            <span className="team-card__name">{team.name}</span>
                            {team.description && <span className="team-card__description">{team.description}</span>}
                        </button>
                    ))}
                </div>
            </div>

            {selectedTeam && (
                <div className="team-details">
                    <h2>{selectedTeam.name}</h2>

                    <div className="team-actions">
                        <Button
                            variant={showInviteForm ? 'text' : 'tonal'}
                            icon={showInviteForm ? 'close' : 'teamAdd'}
                            onClick={() => setShowInviteForm(!showInviteForm)}
                        >
                            {showInviteForm ? t('common.cancel') : t('teams.inviteMember')}
                        </Button>
                    </div>

                    {showInviteForm && (
                        <form onSubmit={handleInviteMember} className="invite-form">
                            <TextField
                                id="invite-email"
                                type="email"
                                label={t('teams.email')}
                                value={inviteEmail}
                                onChange={(e) => setInviteEmail(e.target.value)}
                                required
                            />
                            <TextField
                                as="select"
                                id="invite-role"
                                label={t('teams.role')}
                                value={inviteRole}
                                onChange={(e) => setInviteRole(e.target.value)}
                            >
                                <option value="member">{t('teams.roleMember')}</option>
                                <option value="admin">{t('teams.roleAdmin')}</option>
                            </TextField>
                            <div className="form-actions">
                                <Button type="submit" tone="success" icon="send">
                                    {t('teams.sendInvitation')}
                                </Button>
                            </div>
                        </form>
                    )}

                    <h3>{t('teams.members')}</h3>
                    <div className="members-list">
                        {members.map((member) => (
                            <div key={member.id} className="member-card">
                                <span className="md-avatar" aria-hidden="true">
                                    {member.userName?.trim()?.charAt(0) || '?'}
                                </span>
                                <div className="member-info">
                                    {onInspectMember && iAdministerThisTeam && member.role !== 'admin' ? (
                                        <button
                                            type="button"
                                            className="leaderboard-link member-link"
                                            onClick={() => onInspectMember({ id: member.userId, name: member.userName })}
                                        >
                                            {member.userName}
                                        </button>
                                    ) : (
                                        <strong>{member.userName}</strong>
                                    )}
                                    <span className="member-email">{member.userEmail}</span>
                                    <span className={`member-role role-${member.role}`}>
                                        <Icon name={member.role === 'admin' ? 'admin' : 'person'} size={14} />
                                        {roleLabel(member.role)}
                                    </span>
                                </div>
                                {member.role !== 'admin' && (
                                    <Button
                                        variant="text"
                                        tone="danger"
                                        icon="personRemove"
                                        onClick={() => setMemberToRemove(member)}
                                    >
                                        {t('teams.remove')}
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>

                    {pendingInvitations.length > 0 && (
                        <>
                            <h3>{t('teams.pendingInvitations')}</h3>
                            <p className="pending-invitations-hint">{t('teams.pendingInvitationsHint')}</p>
                            <div className="members-list">
                                {pendingInvitations.map((invitation) => (
                                    <div key={invitation.id} className="member-card invitation-card">
                                        <span className="md-avatar" aria-hidden="true">
                                            <Icon name="mail" size={20} />
                                        </span>
                                        <div className="member-info">
                                            <strong>{invitation.email}</strong>
                                            <span className={`member-role role-${invitation.role}`}>
                                                <Icon name={invitation.role === 'admin' ? 'admin' : 'person'} size={14} />
                                                {roleLabel(invitation.role)}
                                            </span>
                                            <span className="invitation-status">
                                                {invitation.accountExists
                                                    ? t('teams.invitationAwaitingAcceptance')
                                                    : t('teams.invitationNeedsRegistration')}
                                            </span>
                                        </div>
                                        {invitation.invitationUrl && (
                                            <div className="invitation-link">
                                                <input
                                                    type="text"
                                                    readOnly
                                                    value={invitation.invitationUrl}
                                                    onFocus={(e) => e.target.select()}
                                                    className="invitation-link-input"
                                                    aria-label={t('teams.copyLink')}
                                                />
                                                <IconButton
                                                    icon={copiedInvitationId === invitation.id ? 'check' : 'copy'}
                                                    variant="tonal"
                                                    label={copiedInvitationId === invitation.id
                                                        ? t('teams.linkCopied')
                                                        : t('teams.copyLink')}
                                                    onClick={() => handleCopyInvitationLink(invitation)}
                                                />
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            )}

            <Dialog
                open={!!memberToRemove}
                onClose={() => setMemberToRemove(null)}
                headline={t('teams.confirmRemoveMember')}
                actions={
                    <>
                        <Button variant="text" onClick={() => setMemberToRemove(null)}>
                            {t('common.cancel')}
                        </Button>
                        <Button tone="danger" icon="personRemove" onClick={handleRemoveMember}>
                            {t('teams.remove')}
                        </Button>
                    </>
                }
            >
                <p>{memberToRemove?.userName}</p>
            </Dialog>

            <Snackbar message={toast} onDismiss={() => setToast(null)} />
        </div>
    );
};

export default TeamManagement;
