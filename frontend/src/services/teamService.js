import apiClient from './apiClient';

const teamService = {
    // Get all teams for current user
    async getTeams() {
        return await apiClient.get('/api/teams');
    },

    // Create a new team
    async createTeam(teamData) {
        return await apiClient.post('/api/teams', teamData);
    },

    // Update team
    async updateTeam(teamId, teamData) {
        return await apiClient.put(`/api/teams/${teamId}`, teamData);
    },

    // Get team members
    async getTeamMembers(teamId) {
        return await apiClient.get(`/api/teams/${teamId}/members`);
    },

    // Invite user to team
    async inviteToTeam(teamId, inviteData) {
        return await apiClient.post(`/api/teams/${teamId}/invite`, inviteData);
    },

    // Remove member from team
    async removeMember(teamId, userId) {
        return await apiClient.delete(`/api/teams/${teamId}/members/${userId}`);
    },

    // Get user invitations
    async getMyInvitations() {
        return await apiClient.get('/api/teams/invitations');
    },

    // Accept invitation
    async acceptInvitation(token) {
        return await apiClient.post(`/api/teams/invitations/${token}/accept`);
    }
};

export default teamService;
