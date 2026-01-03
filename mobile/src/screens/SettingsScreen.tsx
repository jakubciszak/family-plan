import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Switch,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { NativeStackScreenProps } from '@react-navigation/native-stack';
import { RootStackParamList } from '../navigation/AppNavigator';
import apiClient from '../services/apiClient';
import { PreferenceOption } from '../types/api';

type Props = NativeStackScreenProps<RootStackParamList, 'Settings'>;

const SettingsScreen: React.FC<Props> = ({ route }) => {
  const { userId } = route.params;
  const [preferences, setPreferences] = useState<PreferenceOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      setLoading(true);
      const data = await apiClient.getUserSettings(userId);
      
      // Find notification preferences or use defaults
      const notificationPrefs = data.preferences?.find(p => p.type === 'notifications');
      if (notificationPrefs) {
        setPreferences(notificationPrefs.options);
      } else {
        // Default preferences
        setPreferences([
          { name: 'email', enabled: true },
          { name: 'sms', enabled: false },
        ]);
      }
    } catch (error) {
      console.error('Failed to load settings:', error);
      // Set default preferences on error
      setPreferences([
        { name: 'email', enabled: true },
        { name: 'sms', enabled: false },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleToggle = (optionName: string) => {
    setPreferences(prev =>
      prev.map(opt =>
        opt.name === optionName ? { ...opt, enabled: !opt.enabled } : opt
      )
    );
  };

  const handleSave = async () => {
    try {
      setSaving(true);
      await apiClient.updateUserSettings(userId, {
        preference_type: 'notifications',
        options: preferences,
      });
      Alert.alert('Success', 'Settings saved successfully!');
    } catch (error) {
      console.error('Failed to save settings:', error);
      Alert.alert('Error', 'Failed to save settings. Please try again.');
    } finally {
      setSaving(false);
    }
  };

  const getChannelTitle = (name: string): string => {
    const titles: { [key: string]: string } = {
      email: 'Email',
      sms: 'SMS',
    };
    return titles[name] || name.toUpperCase();
  };

  const getChannelDescription = (name: string): string => {
    const descriptions: { [key: string]: string } = {
      email: 'Receive notifications via email',
      sms: 'Receive notifications via text message',
    };
    return descriptions[name] || `Receive notifications via ${name}`;
  };

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#4CAF50" />
        <Text style={styles.loadingText}>Loading settings...</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Notification Channels</Text>
        <Text style={styles.sectionDescription}>
          Choose how you want to receive notifications
        </Text>

        {preferences.map((option) => (
          <View key={option.name} style={styles.settingItem}>
            <View style={styles.settingInfo}>
              <Text style={styles.settingTitle}>
                {getChannelTitle(option.name)}
              </Text>
              <Text style={styles.settingDescription}>
                {getChannelDescription(option.name)}
              </Text>
            </View>
            <Switch
              value={option.enabled}
              onValueChange={() => handleToggle(option.name)}
              trackColor={{ false: '#ccc', true: '#81c784' }}
              thumbColor={option.enabled ? '#4CAF50' : '#f4f3f4'}
            />
          </View>
        ))}
      </View>

      <TouchableOpacity
        style={[styles.saveButton, saving && styles.saveButtonDisabled]}
        onPress={handleSave}
        disabled={saving}
      >
        {saving ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.saveButtonText}>Save Settings</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f5f5f5',
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  section: {
    backgroundColor: '#fff',
    margin: 16,
    padding: 16,
    borderRadius: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 8,
  },
  sectionDescription: {
    fontSize: 14,
    color: '#666',
    marginBottom: 16,
  },
  settingItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  settingInfo: {
    flex: 1,
    marginRight: 16,
  },
  settingTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 4,
  },
  settingDescription: {
    fontSize: 13,
    color: '#777',
  },
  saveButton: {
    backgroundColor: '#4CAF50',
    margin: 16,
    padding: 16,
    borderRadius: 8,
    alignItems: 'center',
  },
  saveButtonDisabled: {
    backgroundColor: '#ccc',
  },
  saveButtonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});

export default SettingsScreen;
