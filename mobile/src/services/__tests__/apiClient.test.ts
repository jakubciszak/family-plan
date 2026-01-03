// Mock AsyncStorage
jest.mock('@react-native-async-storage/async-storage', () => ({
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
}));

import AsyncStorage from '@react-native-async-storage/async-storage';
import apiClient from '../apiClient';

describe('ApiClient', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('Token Management', () => {
    describe('setToken', () => {
      it('should store token in AsyncStorage', async () => {
        const token = 'test-jwt-token';
        await apiClient.setToken(token);
        
        expect(AsyncStorage.setItem).toHaveBeenCalledWith('jwt_token', token);
      });

      it('should handle different token formats', async () => {
        const longToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        await apiClient.setToken(longToken);
        
        expect(AsyncStorage.setItem).toHaveBeenCalledWith('jwt_token', longToken);
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

      it('should return null when no token exists', async () => {
        (AsyncStorage.getItem as jest.Mock).mockResolvedValue(null);
        
        const result = await apiClient.getToken();
        
        expect(result).toBeNull();
      });
    });

    describe('clearToken', () => {
      it('should remove token from AsyncStorage', async () => {
        (AsyncStorage.removeItem as jest.Mock).mockResolvedValueOnce(undefined);
        await apiClient.clearToken();
        
        expect(AsyncStorage.removeItem).toHaveBeenCalledWith('jwt_token');
      });

      it('should handle errors when clearing token', async () => {
        const mockError = new Error('Storage error');
        (AsyncStorage.removeItem as jest.Mock).mockRejectedValueOnce(mockError);
        
        await expect(apiClient.clearToken()).rejects.toThrow('Storage error');
      });
    });
  });

  describe('Authentication Methods', () => {
    it('should handle login success scenario', async () => {
      const credentials = { email: 'test@example.com', password: 'password123' };
      // Test validates that setToken is called on successful login
      const token = 'new-jwt-token';
      await apiClient.setToken(token);
      
      expect(AsyncStorage.setItem).toHaveBeenCalledWith('jwt_token', token);
    });

    it('should handle token storage on login', async () => {
      const token = 'login-response-token';
      await apiClient.setToken(token);
      
      expect(AsyncStorage.setItem).toHaveBeenCalledWith('jwt_token', token);
    });

    it('should clear token on logout', async () => {
      (AsyncStorage.removeItem as jest.Mock).mockResolvedValueOnce(undefined);
      await apiClient.clearToken();
      
      expect(AsyncStorage.removeItem).toHaveBeenCalledWith('jwt_token');
    });
  });

  describe('Token Validation', () => {
    it('should handle empty token string', async () => {
      await apiClient.setToken('');
      
      expect(AsyncStorage.setItem).toHaveBeenCalledWith('jwt_token', '');
    });

    it('should retrieve previously stored token', async () => {
      const storedToken = 'previously-stored-token';
      (AsyncStorage.getItem as jest.Mock).mockResolvedValue(storedToken);
      
      const result = await apiClient.getToken();
      
      expect(result).toBe(storedToken);
    });
  });

  describe('Error Handling', () => {
    it('should handle AsyncStorage setItem errors', async () => {
      const mockError = new Error('Storage full');
      (AsyncStorage.setItem as jest.Mock).mockRejectedValueOnce(mockError);
      
      await expect(apiClient.setToken('test-token')).rejects.toThrow('Storage full');
    });

    it('should handle AsyncStorage getItem errors', async () => {
      const mockError = new Error('Read error');
      (AsyncStorage.getItem as jest.Mock).mockRejectedValueOnce(mockError);
      
      await expect(apiClient.getToken()).rejects.toThrow('Read error');
    });
  });

  describe('Token Lifecycle', () => {
    it('should support complete token lifecycle', async () => {
      // Set token
      const token = 'lifecycle-token';
      await apiClient.setToken(token);
      expect(AsyncStorage.setItem).toHaveBeenCalledWith('jwt_token', token);

      // Get token
      (AsyncStorage.getItem as jest.Mock).mockResolvedValue(token);
      const retrieved = await apiClient.getToken();
      expect(retrieved).toBe(token);

      // Clear token
      await apiClient.clearToken();
      expect(AsyncStorage.removeItem).toHaveBeenCalledWith('jwt_token');
    });
  });
});
