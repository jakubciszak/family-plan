// Background tasks are defined before the router starts: Android runs them with the app closed, and then
// only this file and what it imports get loaded.
import './src/notifications/background';
import 'expo-router/entry';
