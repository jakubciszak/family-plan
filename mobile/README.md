# 📱 Family Plan Mobile App

A React Native mobile application for the Family Plan system, providing identical functionality to the web frontend optimized for smartphones.

## 🎯 Features

- **JWT Authentication**: Secure token-based authentication
- **Task Management**: View, assign, complete, and approve tasks
- **Bonus Rules**: Admin interface for managing bonus point rules
- **Points System**: Real-time points tracking
- **Responsive Design**: Optimized for mobile devices
- **Offline Token Storage**: Secure token storage using AsyncStorage

## 🏗️ Architecture

- **React Native** 0.76.6 with TypeScript
- **React Navigation** for screen navigation
- **Axios** for API communication with JWT
- **AsyncStorage** for secure token persistence
- **Jest** & **React Native Testing Library** for testing

## 📋 Requirements

- Node.js 18+
- npm or yarn
- For iOS development:
  - macOS
  - Xcode 12+
  - CocoaPods
- For Android development:
  - Android Studio
  - Android SDK
  - JDK 11+

## 🚀 Installation

### 1. Install Dependencies

```bash
cd mobile
npm install
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` and set your API URL:
```
API_URL=http://your-backend-url:8080
```

For local development:
- iOS Simulator: `http://localhost:8080`
- Android Emulator: `http://10.0.2.2:8080`
- Physical device: Use your computer's IP address

### 3. iOS / Android native setup

This project is now configured for **Expo Go** testing (no `ios/` or `android/` folders required).

If you later decide to build native binaries, use Expo prebuild:

```bash
npx expo prebuild
```

## 🎮 Running the App (Expo)

### 1) Install dependencies

```bash
cd mobile
npm install
```

> If you hit version mismatches, run:
> ```bash
> npx expo install
> ```

### 2) Start Expo dev server

Domyślnie ustawione jest wystawianie dev-servera na IP hosta: `192.168.0.30`.

```bash
npm start
```

### 3) Test on a physical phone (recommended)

1. Install **Expo Go** from App Store / Google Play
2. Make sure your phone and computer are on the same Wi‑Fi
3. Scan the QR code shown by `expo start`

### (Optional) Launch directly for a platform

```bash
npm run ios
npm run android
```

## 🧪 Testing

The app follows Test-Driven Development (TDD) principles.

### Run All Tests

```bash
npm test
```

### Run Tests in Watch Mode

```bash
npm run test:watch
```

### Test Coverage

Tests cover:
- API client with JWT authentication
- Login screen functionality
- Task list operations
- Bonus rules management
- User authentication flow

## 📱 App Structure

```
mobile/
├── src/
│   ├── navigation/        # Navigation configuration
│   │   └── AppNavigator.tsx
│   ├── screens/          # Screen components
│   │   ├── LoginScreen.tsx
│   │   ├── HomeScreen.tsx
│   │   ├── TaskListScreen.tsx
│   │   └── BonusRulesScreen.tsx
│   ├── services/         # API services
│   │   └── apiClient.ts  # JWT-based API client
│   ├── types/            # TypeScript type definitions
│   │   └── api.ts
│   └── App.tsx           # Main app component
├── __tests__/            # Test files
├── package.json
└── tsconfig.json
```

## 🔐 Authentication

The mobile app uses **JWT (JSON Web Token)** authentication instead of session-based auth used by the web frontend.

### Login Flow

1. User enters credentials
2. App sends POST request to `/api/auth/login`
3. Backend returns JWT token
4. Token is stored securely in AsyncStorage
5. All subsequent requests include `Authorization: Bearer {token}` header

### Token Management

- Tokens are stored using `@react-native-async-storage/async-storage`
- Tokens persist across app restarts
- Tokens are cleared on logout

## 🎨 Screens

### Login Screen

- Email and password input
- Error handling
- Loading states
- Auto-navigation on success

### Home Screen

- User welcome message
- Points display
- Navigation to Tasks and Bonus Rules (admin only)
- Logout functionality

### Task List Screen

- View all tasks
- Assign tasks to yourself
- Complete assigned tasks
- Approve tasks (admin only)
- Pull-to-refresh
- Status indicators

### Bonus Rules Screen (Admin Only)

- View bonus rules
- Activate/deactivate rules
- Rule type indicators
- Pull-to-refresh

## 🔧 Backend Requirements

The mobile app requires backend API endpoints with JWT support. The backend must:

1. Return JWT token on successful login:
```json
{
  "message": "Login successful",
  "user": { ... },
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

2. Accept `Authorization: Bearer {token}` header on protected endpoints

3. Validate JWT tokens for authentication

## 🚧 Development Tips

### Debugging

- Use React Native Debugger or Flipper
- Check Metro bundler logs
- Use `console.log` for debugging (visible in terminal)

### Hot Reload

- Shake device or press `Cmd+D` (iOS) / `Cmd+M` (Android) for dev menu
- Enable Hot Reload for instant updates

### API Testing

Use ngrok or similar tools to expose local backend to physical devices:

```bash
ngrok http 8080
```

Then update `.env` with the ngrok URL.

## 📦 Building for Production

This repo is set up primarily for **Expo Go** testing.

To generate native projects locally:

```bash
npx expo prebuild
```

For store builds and CI, use **EAS Build** (recommended by Expo):

```bash
npx expo install eas-cli
npx eas build -p ios
npx eas build -p android
```

## 🐛 Troubleshooting

### Expo/Metro cache issues

```bash
npm start -- --reset-cache
```

## 📄 License

Same as the main Family Plan project.

## 🤝 Contributing

Follow the same TDD approach:
1. Write tests first
2. Implement functionality
3. Ensure all tests pass
4. Submit PR

## 🔗 Related Documentation

- [Main Project README](../README.md)
- [Backend Documentation](../BACKEND.md)
- [API Specification](../API_SPECIFICATION.md)
- [React Native Documentation](https://reactnative.dev)
