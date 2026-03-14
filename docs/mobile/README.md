# Mobile API Integration

This folder contains ready-to-copy examples for connecting a Flutter app or a React Native app to this Laravel API.

## Current LAN base URL

Use this base URL when your phone and Mac are on the same Wi-Fi:

```txt
http://10.152.172.154:8001/api
```

Change it later for production.

## API authentication flow

1. `POST /auth/login`
2. Save the returned Bearer token
3. Send `Authorization: Bearer <token>` on protected requests
4. Call `GET /auth/me` to load current user
5. Call `POST /auth/logout` to revoke current token

Example login body:

```json
{
  "login": "admin@example.com",
  "password": "password123",
  "device_name": "mobile-app"
}
```

## Files

- `flutter_api_service.dart`
- `react_native_api.ts`

## Flutter setup

Packages:

```yaml
dependencies:
  http: ^1.2.2
  shared_preferences: ^2.3.2
```

Then copy `flutter_api_service.dart` into your Flutter project, for example:

```txt
lib/services/school_api_service.dart
```

## React Native setup

Packages:

```bash
npm install axios @react-native-async-storage/async-storage
```

Then copy `react_native_api.ts` into your React Native project, for example:

```txt
src/services/schoolApi.ts
```

## Notes

- For a real phone, do not use `localhost` or `127.0.0.1`.
- Use the Mac LAN IP instead.
- If you later configure `SECURITY_ADMIN_IP_ALLOWLIST`, admin and super-admin API access will only work from allowed IPs.
- Image upload must use `multipart/form-data`.
