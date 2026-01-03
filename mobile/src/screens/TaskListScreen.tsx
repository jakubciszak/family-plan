import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RouteProp } from '@react-navigation/native';
import { RootStackParamList } from '../navigation/AppNavigator';
import apiClient from '../services/apiClient';
import { Task, User } from '../types/api';

type TaskListScreenNavigationProp = NativeStackNavigationProp<
  RootStackParamList,
  'TaskList'
>;

type TaskListScreenRouteProp = RouteProp<RootStackParamList, 'TaskList'>;

interface Props {
  navigation: TaskListScreenNavigationProp;
  route: TaskListScreenRouteProp & { params: { user: User } };
}

const TaskListScreen: React.FC<Props> = ({ route }) => {
  const { user } = route.params;
  const [tasks, setTasks] = useState<Task[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadTasks = async () => {
    try {
      const data = await apiClient.getTasks();
      setTasks(data.tasks);
    } catch (error) {
      console.error('Error loading tasks:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    loadTasks();
  }, []);

  const handleRefresh = () => {
    setRefreshing(true);
    loadTasks();
  };

  const handleAssign = async (taskId: string) => {
    try {
      await apiClient.assignTask(taskId, user.id);
      loadTasks();
    } catch (error) {
      console.error('Error assigning task:', error);
    }
  };

  const handleComplete = async (taskId: string) => {
    try {
      await apiClient.completeTask(taskId, user.id);
      loadTasks();
    } catch (error) {
      console.error('Error completing task:', error);
    }
  };

  const handleApprove = async (taskId: string) => {
    try {
      await apiClient.approveTask(taskId, user.id);
      loadTasks();
    } catch (error) {
      console.error('Error approving task:', error);
    }
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#4CAF50" />
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />
      }
    >
      {tasks.length === 0 ? (
        <View style={styles.centered}>
          <Text style={styles.emptyText}>No tasks yet</Text>
        </View>
      ) : (
        tasks.map((task) => (
          <TaskCard
            key={task.id}
            task={task}
            user={user}
            onAssign={handleAssign}
            onComplete={handleComplete}
            onApprove={handleApprove}
          />
        ))
      )}
    </ScrollView>
  );
};

interface TaskCardProps {
  task: Task;
  user: User;
  onAssign: (taskId: string) => void;
  onComplete: (taskId: string) => void;
  onApprove: (taskId: string) => void;
}

const TaskCard: React.FC<TaskCardProps> = ({
  task,
  user,
  onAssign,
  onComplete,
  onApprove,
}) => {
  const isAdmin = user.role === 'ROLE_ADMIN';
  const isAssigned = task.assignedUserId !== null;
  const isAssignedToCurrentUser = task.assignedUserId === user.id;
  const canComplete = task.status === 'pending' && isAssignedToCurrentUser;
  const canApprove = task.status === 'completed' && isAdmin;
  const canAssign = task.status === 'pending' && !isAssigned;

  const getStatusColor = () => {
    switch (task.status) {
      case 'pending':
        return '#FFA726';
      case 'completed':
        return '#42A5F5';
      case 'approved':
        return '#66BB6A';
      default:
        return '#999';
    }
  };

  return (
    <View style={styles.card}>
      <View style={styles.cardHeader}>
        <Text style={styles.taskName}>{task.name}</Text>
        <View style={[styles.statusBadge, { backgroundColor: getStatusColor() }]}>
          <Text style={styles.statusText}>{task.status}</Text>
        </View>
      </View>

      <Text style={styles.description}>{task.description}</Text>

      <View style={styles.taskMeta}>
        <Text style={styles.points}>{task.points} points</Text>
        <Text style={styles.frequency}>{task.frequency}</Text>
      </View>

      {isAssigned && (
        <Text style={styles.assignment}>
          Assigned to: {task.assignedUserName}
        </Text>
      )}

      <View style={styles.actions}>
        {canAssign && (
          <TouchableOpacity
            style={[styles.button, styles.buttonPrimary]}
            onPress={() => onAssign(task.id)}
          >
            <Text style={styles.buttonText}>Assign to Me</Text>
          </TouchableOpacity>
        )}
        {canComplete && (
          <TouchableOpacity
            style={[styles.button, styles.buttonSuccess]}
            onPress={() => onComplete(task.id)}
          >
            <Text style={styles.buttonText}>Complete</Text>
          </TouchableOpacity>
        )}
        {canApprove && (
          <TouchableOpacity
            style={[styles.button, styles.buttonPrimary]}
            onPress={() => onApprove(task.id)}
          >
            <Text style={styles.buttonText}>Approve</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  centered: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  emptyText: {
    fontSize: 16,
    color: '#999',
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 15,
    margin: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  taskName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    flex: 1,
  },
  statusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
  },
  statusText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: 'bold',
    textTransform: 'uppercase',
  },
  description: {
    fontSize: 14,
    color: '#666',
    marginBottom: 10,
  },
  taskMeta: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  points: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#4CAF50',
  },
  frequency: {
    fontSize: 14,
    color: '#999',
  },
  assignment: {
    fontSize: 14,
    color: '#666',
    fontStyle: 'italic',
    marginBottom: 10,
  },
  actions: {
    flexDirection: 'row',
    gap: 10,
  },
  button: {
    flex: 1,
    padding: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  buttonPrimary: {
    backgroundColor: '#4CAF50',
  },
  buttonSuccess: {
    backgroundColor: '#66BB6A',
  },
  buttonText: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 14,
  },
});

export default TaskListScreen;
