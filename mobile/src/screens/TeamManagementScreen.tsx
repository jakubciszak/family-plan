import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  FlatList,
  StyleSheet,
  Alert,
  Modal,
  ScrollView,
} from 'react-native';
import apiClient from '../services/apiClient';
import { Team, TeamMember, TeamInvitation } from '../types/api';

export default function TeamManagementScreen() {
  const [teams, setTeams] = useState<Team[]>([]);
  const [selectedTeam, setSelectedTeam] = useState<Team | null>(null);
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [invitations, setInvitations] = useState<TeamInvitation[]>([]);
  const [loading, setLoading] = useState(true);
  
  // Modal states
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [showMembersModal, setShowMembersModal] = useState(false);
  
  // Form states
  const [teamName, setTeamName] = useState('');
  const [teamDescription, setTeamDescription] = useState('');
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState<'admin' | 'member'>('member');

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      await Promise.all([loadTeams(), loadMyInvitations()]);
    } catch (error) {
      Alert.alert('Error', 'Failed to load data');
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const loadTeams = async () => {
    try {
      const response = await apiClient.getTeams();
      setTeams(response.teams);
    } catch (error) {
      console.error('Error loading teams:', error);
    }
  };

  const loadMyInvitations = async () => {
    try {
      const response = await apiClient.getMyInvitations();
      setInvitations(response.invitations);
    } catch (error) {
      console.error('Error loading invitations:', error);
    }
  };

  const loadTeamMembers = async (teamId: string) => {
    try {
      const response = await apiClient.getTeamMembers(teamId);
      setMembers(response.members);
    } catch (error) {
      console.error('Error loading team members:', error);
    }
  };

  const handleCreateTeam = async () => {
    if (!teamName.trim()) {
      Alert.alert('Error', 'Team name is required');
      return;
    }

    try {
      await apiClient.createTeam({
        name: teamName,
        description: teamDescription,
      });
      setTeamName('');
      setTeamDescription('');
      setShowCreateModal(false);
      loadTeams();
      Alert.alert('Success', 'Team created successfully');
    } catch (error) {
      Alert.alert('Error', 'Failed to create team');
      console.error(error);
    }
  };

  const handleInviteMember = async () => {
    if (!inviteEmail.trim()) {
      Alert.alert('Error', 'Email is required');
      return;
    }
    if (!selectedTeam) return;

    try {
      await apiClient.inviteToTeam(selectedTeam.id, {
        email: inviteEmail,
        role: inviteRole,
      });
      setInviteEmail('');
      setInviteRole('member');
      setShowInviteModal(false);
      Alert.alert('Success', 'Invitation sent successfully');
    } catch (error) {
      Alert.alert('Error', 'Failed to send invitation');
      console.error(error);
    }
  };

  const handleAcceptInvitation = async (token: string) => {
    try {
      await apiClient.acceptInvitation(token);
      loadMyInvitations();
      loadTeams();
      Alert.alert('Success', 'Invitation accepted successfully');
    } catch (error) {
      Alert.alert('Error', 'Failed to accept invitation');
      console.error(error);
    }
  };

  const handleRemoveMember = async (userId: string) => {
    if (!selectedTeam) return;

    Alert.alert(
      'Confirm',
      'Are you sure you want to remove this member?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Remove',
          style: 'destructive',
          onPress: async () => {
            try {
              await apiClient.removeMember(selectedTeam.id, userId);
              loadTeamMembers(selectedTeam.id);
              Alert.alert('Success', 'Member removed successfully');
            } catch (error) {
              Alert.alert('Error', 'Failed to remove member');
              console.error(error);
            }
          },
        },
      ]
    );
  };

  const openMembersModal = async (team: Team) => {
    setSelectedTeam(team);
    await loadTeamMembers(team.id);
    setShowMembersModal(true);
  };

  const renderTeamCard = ({ item }: { item: Team }) => (
    <TouchableOpacity
      style={styles.teamCard}
      onPress={() => openMembersModal(item)}
    >
      <Text style={styles.teamName}>{item.name}</Text>
      {item.description && (
        <Text style={styles.teamDescription}>{item.description}</Text>
      )}
    </TouchableOpacity>
  );

  const renderInvitation = ({ item }: { item: TeamInvitation }) => (
    <View style={styles.invitationCard}>
      <Text style={styles.invitationText}>
        Team invitation as {item.role}
      </Text>
      <TouchableOpacity
        style={styles.acceptButton}
        onPress={() => handleAcceptInvitation(item.token)}
      >
        <Text style={styles.buttonText}>Accept</Text>
      </TouchableOpacity>
    </View>
  );

  const renderMember = ({ item }: { item: TeamMember }) => (
    <View style={styles.memberCard}>
      <View style={styles.memberInfo}>
        <Text style={styles.memberName}>{item.userName}</Text>
        <Text style={styles.memberEmail}>{item.userEmail}</Text>
        <Text style={[styles.memberRole, styles[`role${item.role}`]]}>
          {item.role.toUpperCase()}
        </Text>
      </View>
      {item.role !== 'admin' && (
        <TouchableOpacity
          style={styles.removeButton}
          onPress={() => handleRemoveMember(item.userId)}
        >
          <Text style={styles.buttonText}>Remove</Text>
        </TouchableOpacity>
      )}
    </View>
  );

  if (loading) {
    return (
      <View style={styles.centered}>
        <Text>Loading...</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      {/* Pending Invitations */}
      {invitations.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Pending Invitations</Text>
          <FlatList
            data={invitations}
            renderItem={renderInvitation}
            keyExtractor={(item) => item.id}
            scrollEnabled={false}
          />
        </View>
      )}

      {/* Teams List */}
      <View style={styles.section}>
        <View style={styles.header}>
          <Text style={styles.sectionTitle}>My Teams</Text>
          <TouchableOpacity
            style={styles.createButton}
            onPress={() => setShowCreateModal(true)}
          >
            <Text style={styles.buttonText}>Create Team</Text>
          </TouchableOpacity>
        </View>
        <FlatList
          data={teams}
          renderItem={renderTeamCard}
          keyExtractor={(item) => item.id}
          scrollEnabled={false}
        />
      </View>

      {/* Create Team Modal */}
      <Modal
        visible={showCreateModal}
        animationType="slide"
        transparent={true}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Create Team</Text>
            <TextInput
              style={styles.input}
              placeholder="Team Name"
              value={teamName}
              onChangeText={setTeamName}
            />
            <TextInput
              style={[styles.input, styles.textArea]}
              placeholder="Description (optional)"
              value={teamDescription}
              onChangeText={setTeamDescription}
              multiline
              numberOfLines={3}
            />
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.button, styles.cancelButton]}
                onPress={() => setShowCreateModal(false)}
              >
                <Text style={styles.buttonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.button, styles.submitButton]}
                onPress={handleCreateTeam}
              >
                <Text style={styles.buttonText}>Create</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Members Modal */}
      <Modal
        visible={showMembersModal}
        animationType="slide"
        transparent={true}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>
              {selectedTeam?.name} - Members
            </Text>
            <TouchableOpacity
              style={styles.inviteButton}
              onPress={() => {
                setShowMembersModal(false);
                setShowInviteModal(true);
              }}
            >
              <Text style={styles.buttonText}>Invite Member</Text>
            </TouchableOpacity>
            <FlatList
              data={members}
              renderItem={renderMember}
              keyExtractor={(item) => item.id}
            />
            <TouchableOpacity
              style={[styles.button, styles.cancelButton]}
              onPress={() => setShowMembersModal(false)}
            >
              <Text style={styles.buttonText}>Close</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>

      {/* Invite Modal */}
      <Modal
        visible={showInviteModal}
        animationType="slide"
        transparent={true}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Invite Member</Text>
            <TextInput
              style={styles.input}
              placeholder="Email"
              value={inviteEmail}
              onChangeText={setInviteEmail}
              keyboardType="email-address"
              autoCapitalize="none"
            />
            <View style={styles.roleSelector}>
              <TouchableOpacity
                style={[
                  styles.roleButton,
                  inviteRole === 'member' && styles.roleButtonActive,
                ]}
                onPress={() => setInviteRole('member')}
              >
                <Text style={styles.roleButtonText}>Member</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[
                  styles.roleButton,
                  inviteRole === 'admin' && styles.roleButtonActive,
                ]}
                onPress={() => setInviteRole('admin')}
              >
                <Text style={styles.roleButtonText}>Admin</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.button, styles.cancelButton]}
                onPress={() => setShowInviteModal(false)}
              >
                <Text style={styles.buttonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.button, styles.submitButton]}
                onPress={handleInviteMember}
              >
                <Text style={styles.buttonText}>Send</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  section: {
    marginBottom: 20,
    paddingHorizontal: 16,
    paddingTop: 16,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#333',
  },
  teamCard: {
    backgroundColor: '#fff',
    padding: 16,
    borderRadius: 8,
    marginBottom: 12,
    elevation: 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  teamName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 4,
  },
  teamDescription: {
    fontSize: 14,
    color: '#666',
  },
  invitationCard: {
    backgroundColor: '#fff3cd',
    padding: 16,
    borderRadius: 8,
    marginBottom: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ffc107',
  },
  invitationText: {
    flex: 1,
    fontSize: 14,
    color: '#856404',
  },
  memberCard: {
    backgroundColor: '#f8f9fa',
    padding: 12,
    borderRadius: 8,
    marginBottom: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#dee2e6',
  },
  memberInfo: {
    flex: 1,
  },
  memberName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#333',
  },
  memberEmail: {
    fontSize: 14,
    color: '#666',
    marginTop: 2,
  },
  memberRole: {
    fontSize: 12,
    fontWeight: 'bold',
    marginTop: 4,
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 12,
    alignSelf: 'flex-start',
  },
  roleadmin: {
    backgroundColor: '#d1ecf1',
    color: '#0c5460',
  },
  rolemember: {
    backgroundColor: '#d4edda',
    color: '#155724',
  },
  createButton: {
    backgroundColor: '#007bff',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
  },
  acceptButton: {
    backgroundColor: '#28a745',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
  },
  inviteButton: {
    backgroundColor: '#007bff',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 6,
    marginBottom: 16,
    alignItems: 'center',
  },
  removeButton: {
    backgroundColor: '#dc3545',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
  },
  buttonText: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 14,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 20,
    width: '90%',
    maxHeight: '80%',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 16,
    color: '#333',
  },
  input: {
    borderWidth: 1,
    borderColor: '#ced4da',
    borderRadius: 6,
    padding: 12,
    marginBottom: 12,
    fontSize: 16,
  },
  textArea: {
    height: 80,
    textAlignVertical: 'top',
  },
  roleSelector: {
    flexDirection: 'row',
    marginBottom: 16,
  },
  roleButton: {
    flex: 1,
    padding: 12,
    borderWidth: 1,
    borderColor: '#ced4da',
    alignItems: 'center',
    marginHorizontal: 4,
    borderRadius: 6,
  },
  roleButtonActive: {
    backgroundColor: '#007bff',
    borderColor: '#007bff',
  },
  roleButtonText: {
    fontSize: 14,
    fontWeight: 'bold',
  },
  modalButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 16,
  },
  button: {
    flex: 1,
    padding: 12,
    borderRadius: 6,
    alignItems: 'center',
    marginHorizontal: 4,
  },
  cancelButton: {
    backgroundColor: '#6c757d',
  },
  submitButton: {
    backgroundColor: '#28a745',
  },
});
