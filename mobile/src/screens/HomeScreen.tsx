import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  StyleSheet,
  ActivityIndicator,
} from 'react-native';
import { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { RootStackParamList } from '../navigation/AppNavigator';
import apiClient from '../services/apiClient';
import { User } from '../types/api';

type HomeScreenNavigationProp = NativeStackNavigationProp<
  RootStackParamList,
  'Home'
>;

interface Props {
  navigation: HomeScreenNavigationProp;
  route: any;
}

const HomeScreen: React.FC<Props> = ({ navigation }) => {
  const [user, setUser] = useState<User | null>(null);
  const [userPoints, setUserPoints] = useState(0);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadUserData();
  }, []);

  const loadUserData = async () => {
    try {
      const userData = await apiClient.getCurrentUser();
      setUser(userData);

      const pointsData = await apiClient.getUserPoints(userData.id);
      setUserPoints(pointsData.balance);
    } catch (error) {
      console.error('Error loading user data:', error);
      navigation.replace('Login');
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = async () => {
    try {
      await apiClient.logout();
      navigation.replace('Login');
    } catch (error) {
      console.error('Error logging out:', error);
      navigation.replace('Login');
    }
  };

  if (loading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#4CAF50" testID="loading-indicator" />
      </View>
    );
  }

  if (!user) {
    return null;
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.welcome}>Welcome, {user.name}!</Text>
        <View style={styles.pointsContainer}>
          <Text style={styles.pointsLabel}>Your Points:</Text>
          <Text style={styles.pointsValue}>{userPoints}</Text>
        </View>
      </View>

      <View style={styles.menu}>
        <TouchableOpacity
          style={styles.menuButton}
          onPress={() => navigation.navigate('TaskList', { user })}
        >
          <Text style={styles.menuButtonText}>📋 Tasks</Text>
        </TouchableOpacity>

        {user.role === 'ROLE_ADMIN' && (
          <TouchableOpacity
            style={styles.menuButton}
            onPress={() => navigation.navigate('BonusRules', { user })}
          >
            <Text style={styles.menuButtonText}>🎁 Bonus Rules</Text>
          </TouchableOpacity>
        )}

        <TouchableOpacity
          style={[styles.menuButton, styles.logoutButton]}
          onPress={handleLogout}
        >
          <Text style={styles.menuButtonText}>🚪 Logout</Text>
        </TouchableOpacity>
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
  },
  header: {
    backgroundColor: '#4CAF50',
    padding: 20,
    alignItems: 'center',
  },
  welcome: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#fff',
    marginBottom: 15,
  },
  pointsContainer: {
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 20,
  },
  pointsLabel: {
    fontSize: 14,
    color: '#fff',
    textAlign: 'center',
  },
  pointsValue: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#fff',
    textAlign: 'center',
  },
  menu: {
    padding: 20,
  },
  menuButton: {
    backgroundColor: '#fff',
    padding: 20,
    borderRadius: 8,
    marginBottom: 15,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  menuButtonText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    textAlign: 'center',
  },
  logoutButton: {
    backgroundColor: '#f44336',
  },
});

export default HomeScreen;
