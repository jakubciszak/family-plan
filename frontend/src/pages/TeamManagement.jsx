import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import teamService from '../services/teamService';
import '../styles/TeamManagement.css';

const TeamManagement = () => {
    const { t } = useTranslation();
    const [teams, setTeams] = useState([]);
    const [selectedTeam, setSelectedTeam] = useState(null);
    const [members, setMembers] = useState([]);
    const [invitations, setInvitations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [showInviteForm, setShowInviteForm] = useState(false);

    // Form states
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
            await teamService.createTeam({
                name: teamName,
                description: teamDescription
            });
            setTeamName('');
            setTeamDescription('');
            setShowCreateForm(false);
            loadTeams();
        } catch (err) {
            setError(t('teams.errorCreatingTeam'));
            console.error('Error creating team:', err);
        }
    };

    const handleInviteMember = async (e) => {
        e.preventDefault();
        if (!selectedTeam) return;

        try {
            await teamService.inviteToTeam(selectedTeam.id, {
                email: inviteEmail,
                role: inviteRole
            });
            setInviteEmail('');
            setInviteRole('member');
            setShowInviteForm(false);
            alert(t('teams.invitationSent'));
        } catch (err) {
            setError(t('teams.errorSendingInvitation'));
            console.error('Error inviting member:', err);
        }
    };

    const handleAcceptInvitation = async (token) => {
        try {
            await teamService.acceptInvitation(token);
            loadMyInvitations();
            loadTeams();
            alert(t('teams.invitationAccepted'));
        } catch (err) {
            setError(t('teams.errorAcceptingInvitation'));
            console.error('Error accepting invitation:', err);
        }
    };

    const handleRemoveMember = async (userId) => {
        if (!selectedTeam) return;
        if (!window.confirm(t('teams.confirmRemoveMember'))) return;

        try {
            await teamService.removeMember(selectedTeam.id, userId);
            loadTeamMembers(selectedTeam.id);
        } catch (err) {
            setError(t('teams.errorRemovingMember'));
            console.error('Error removing member:', err);
        }
    };

    if (loading) {
        return <div className="loading">{t('common.loading')}</div>;
    }

    return (
        <div className="team-management">
            <h1>{t('teams.title')}</h1>

            {error && <div className="error-message">{error}</div>}

            {/* Pending Invitations */}
            {invitations.length > 0 && (
                <div className="invitations-section">
                    <h2>{t('teams.pendingInvitations')}</h2>
                    <div className="invitations-list">
                        {invitations.map((invitation) => (
                            <div key={invitation.id} className="invitation-card">
                                <p>
                                    {t('teams.invitationText', { 
                                        role: invitation.role 
                                    })}
                                </p>
                                <button 
                                    onClick={() => handleAcceptInvitation(invitation.token)}
                                    className="btn-primary"
                                >
                                    {t('teams.acceptInvitation')}
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Teams List */}
            <div className="teams-section">
                <div className="teams-header">
                    <h2>{t('teams.myTeams')}</h2>
                    <button 
                        onClick={() => setShowCreateForm(!showCreateForm)}
                        className="btn-primary"
                    >
                        {showCreateForm ? t('common.cancel') : t('teams.createTeam')}
                    </button>
                </div>

                {showCreateForm && (
                    <form onSubmit={handleCreateTeam} className="team-form">
                        <div className="form-group">
                            <label>{t('teams.teamName')}</label>
                            <input
                                type="text"
                                value={teamName}
                                onChange={(e) => setTeamName(e.target.value)}
                                required
                                maxLength={255}
                            />
                        </div>
                        <div className="form-group">
                            <label>{t('teams.teamDescription')}</label>
                            <textarea
                                value={teamDescription}
                                onChange={(e) => setTeamDescription(e.target.value)}
                                rows={3}
                            />
                        </div>
                        <button type="submit" className="btn-success">
                            {t('teams.create')}
                        </button>
                    </form>
                )}

                <div className="teams-list">
                    {teams.map((team) => (
                        <div 
                            key={team.id} 
                            className={`team-card ${selectedTeam?.id === team.id ? 'active' : ''}`}
                            onClick={() => setSelectedTeam(team)}
                        >
                            <h3>{team.name}</h3>
                            {team.description && <p>{team.description}</p>}
                        </div>
                    ))}
                </div>
            </div>

            {/* Team Details */}
            {selectedTeam && (
                <div className="team-details">
                    <h2>{selectedTeam.name}</h2>
                    
                    <div className="team-actions">
                        <button 
                            onClick={() => setShowInviteForm(!showInviteForm)}
                            className="btn-primary"
                        >
                            {showInviteForm ? t('common.cancel') : t('teams.inviteMember')}
                        </button>
                    </div>

                    {showInviteForm && (
                        <form onSubmit={handleInviteMember} className="invite-form">
                            <div className="form-group">
                                <label>{t('teams.email')}</label>
                                <input
                                    type="email"
                                    value={inviteEmail}
                                    onChange={(e) => setInviteEmail(e.target.value)}
                                    required
                                />
                            </div>
                            <div className="form-group">
                                <label>{t('teams.role')}</label>
                                <select
                                    value={inviteRole}
                                    onChange={(e) => setInviteRole(e.target.value)}
                                >
                                    <option value="member">{t('teams.roleMember')}</option>
                                    <option value="admin">{t('teams.roleAdmin')}</option>
                                </select>
                            </div>
                            <button type="submit" className="btn-success">
                                {t('teams.sendInvitation')}
                            </button>
                        </form>
                    )}

                    <h3>{t('teams.members')}</h3>
                    <div className="members-list">
                        {members.map((member) => (
                            <div key={member.id} className="member-card">
                                <div className="member-info">
                                    <strong>{member.userName}</strong>
                                    <span className="member-email">{member.userEmail}</span>
                                    <span className={`member-role role-${member.role}`}>
                                        {t(`teams.role${member.role.charAt(0).toUpperCase()}${member.role.slice(1)}`)}
                                    </span>
                                </div>
                                {member.role !== 'admin' && (
                                    <button 
                                        onClick={() => handleRemoveMember(member.userId)}
                                        className="btn-danger"
                                    >
                                        {t('teams.remove')}
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default TeamManagement;
