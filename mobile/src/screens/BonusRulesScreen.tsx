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
import { BonusRule, User } from '../types/api';

type BonusRulesScreenNavigationProp = NativeStackNavigationProp<
  RootStackParamList,
  'BonusRules'
>;

type BonusRulesScreenRouteProp = RouteProp<RootStackParamList, 'BonusRules'>;

interface Props {
  navigation: BonusRulesScreenNavigationProp;
  route: BonusRulesScreenRouteProp & { params: { user: User } };
}

const BonusRulesScreen: React.FC<Props> = ({ route }) => {
  const { user } = route.params;
  const [rules, setRules] = useState<BonusRule[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const loadRules = async () => {
    try {
      const data = await apiClient.getBonusRules();
      setRules(data.rules || []);
    } catch (error) {
      console.error('Error loading bonus rules:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    loadRules();
  }, []);

  const handleRefresh = () => {
    setRefreshing(true);
    loadRules();
  };

  const handleActivate = async (ruleId: string) => {
    try {
      await apiClient.activateBonusRule(ruleId);
      loadRules();
    } catch (error) {
      console.error('Error activating rule:', error);
    }
  };

  const handleDeactivate = async (ruleId: string) => {
    try {
      await apiClient.deactivateBonusRule(ruleId);
      loadRules();
    } catch (error) {
      console.error('Error deactivating rule:', error);
    }
  };

  if (user.role !== 'ROLE_ADMIN') {
    return (
      <View style={styles.centered}>
        <Text style={styles.errorText}>Access Denied</Text>
        <Text style={styles.errorSubtext}>
          Only administrators can manage bonus rules.
        </Text>
      </View>
    );
  }

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
      {rules.length === 0 ? (
        <View style={styles.centered}>
          <Text style={styles.emptyText}>No bonus rules yet</Text>
        </View>
      ) : (
        rules.map((rule) => (
          <BonusRuleCard
            key={rule.id}
            rule={rule}
            onActivate={handleActivate}
            onDeactivate={handleDeactivate}
          />
        ))
      )}
    </ScrollView>
  );
};

interface BonusRuleCardProps {
  rule: BonusRule;
  onActivate: (ruleId: string) => void;
  onDeactivate: (ruleId: string) => void;
}

const BonusRuleCard: React.FC<BonusRuleCardProps> = ({
  rule,
  onActivate,
  onDeactivate,
}) => {
  const getRuleTypeLabel = (type: string) => {
    switch (type) {
      case 'consecutive_days':
        return 'Consecutive Days';
      case 'monthly_task_count':
        return 'Monthly Task Count';
      default:
        return type;
    }
  };

  const getRuleDescription = () => {
    if (rule.type === 'consecutive_days' && rule.config.requiredDays) {
      return `Complete task ${rule.config.requiredDays} consecutive days`;
    } else if (rule.type === 'monthly_task_count' && rule.config.requiredCount) {
      return `Complete ${rule.config.requiredCount} tasks in a month`;
    }
    return rule.description;
  };

  return (
    <View style={[styles.card, !rule.isActive && styles.cardInactive]}>
      <View style={styles.cardHeader}>
        <Text style={styles.ruleName}>{rule.name}</Text>
        <View
          style={[
            styles.statusBadge,
            { backgroundColor: rule.isActive ? '#66BB6A' : '#999' },
          ]}
        >
          <Text style={styles.statusText}>
            {rule.isActive ? 'Active' : 'Inactive'}
          </Text>
        </View>
      </View>

      <View style={styles.typeBadge}>
        <Text style={styles.typeBadgeText}>{getRuleTypeLabel(rule.type)}</Text>
      </View>

      <Text style={styles.description}>{rule.description}</Text>
      <Text style={styles.config}>{getRuleDescription()}</Text>

      <View style={styles.pointsContainer}>
        <Text style={styles.pointsLabel}>Bonus Points:</Text>
        <Text style={styles.pointsValue}>{rule.bonusPoints}</Text>
      </View>

      <View style={styles.actions}>
        {rule.isActive ? (
          <TouchableOpacity
            style={[styles.button, styles.buttonWarning]}
            onPress={() => onDeactivate(rule.id)}
          >
            <Text style={styles.buttonText}>Deactivate</Text>
          </TouchableOpacity>
        ) : (
          <TouchableOpacity
            style={[styles.button, styles.buttonSuccess]}
            onPress={() => onActivate(rule.id)}
          >
            <Text style={styles.buttonText}>Activate</Text>
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
  errorText: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#f44336',
    marginBottom: 10,
  },
  errorSubtext: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
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
  cardInactive: {
    opacity: 0.7,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  ruleName: {
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
  typeBadge: {
    backgroundColor: '#E3F2FD',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
    alignSelf: 'flex-start',
    marginBottom: 10,
  },
  typeBadgeText: {
    color: '#1976D2',
    fontSize: 12,
    fontWeight: 'bold',
  },
  description: {
    fontSize: 14,
    color: '#666',
    marginBottom: 5,
  },
  config: {
    fontSize: 14,
    color: '#999',
    fontStyle: 'italic',
    marginBottom: 10,
  },
  pointsContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 15,
  },
  pointsLabel: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#333',
    marginRight: 10,
  },
  pointsValue: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#4CAF50',
  },
  actions: {
    flexDirection: 'row',
  },
  button: {
    flex: 1,
    padding: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  buttonSuccess: {
    backgroundColor: '#66BB6A',
  },
  buttonWarning: {
    backgroundColor: '#FFA726',
  },
  buttonText: {
    color: '#fff',
    fontWeight: 'bold',
    fontSize: 14,
  },
});

export default BonusRulesScreen;
