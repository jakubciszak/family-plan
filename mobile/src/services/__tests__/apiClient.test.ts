// Mock AsyncStorage
jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
}));

import AsyncStorage from '@react-native-async-storage/async-storage';
import apiClient from '../apiClient';

describe('ApiClient Token Management', () => {
  // Testing token management functions
  // Full API integration tests would require a running backend
  
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('setToken', () => {
    it('should store token in AsyncStorage', async () => {
      const token = 'test-jwt-token';
      await apiClient.setToken(token);
      
      expect(AsyncStorage.setItem).toHaveBeenCalledWith('jwt_token', token);
    });
  });

  describe('getToken', () => {
    it('should retrieve token from AsyncStorage', async () => {
      const token = 'test-jwt-token';
      (AsyncStorage.getItem as jest.Mock).mockResolvedValue(token);
      
      const result = await apiClient.getToken();
      
      expect(AsyncStorage.getItem).toHaveBeenCalledWith('jwt_token');
      expect(result).toBe(token);
    });
  });

  describe('clearToken', () => {
    it('should remove token from AsyncStorage', async () => {
      await apiClient.clearToken();
      
      expect(AsyncStorage.removeItem).toHaveBeenCalledWith('jwt_token');
    });
  });
});
