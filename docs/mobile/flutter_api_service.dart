import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiConfig {
  static const String baseUrl = 'http://10.152.172.154:8001/api';
  static const String tokenKey = 'school_api_token';
}

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => 'ApiException(statusCode: $statusCode, message: $message)';
}

class AuthUser {
  AuthUser({
    required this.id,
    required this.role,
    required this.email,
    required this.name,
  });

  final int id;
  final String role;
  final String email;
  final String name;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: (json['id'] as num?)?.toInt() ?? 0,
      role: (json['role'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      name: (json['name'] ?? '').toString(),
    );
  }
}

class LoginResponse {
  LoginResponse({
    required this.token,
    required this.user,
  });

  final String token;
  final AuthUser user;

  factory LoginResponse.fromJson(Map<String, dynamic> json) {
    return LoginResponse(
      token: (json['token'] ?? '').toString(),
      user: AuthUser.fromJson((json['user'] as Map<String, dynamic>?) ?? <String, dynamic>{}),
    );
  }
}

class SchoolApiService {
  Future<LoginResponse> login({
    required String login,
    required String password,
    String deviceName = 'flutter-app',
  }) async {
    final response = await http.post(
      Uri.parse('${ApiConfig.baseUrl}/auth/login'),
      headers: _jsonHeaders(),
      body: jsonEncode({
        'login': login,
        'password': password,
        'device_name': deviceName,
      }),
    );

    final data = _decodeJson(response);
    if (response.statusCode != 200) {
      throw ApiException(_extractMessage(data), statusCode: response.statusCode);
    }

    final loginResponse = LoginResponse.fromJson(data);
    await _saveToken(loginResponse.token);
    return loginResponse;
  }

  Future<void> logout() async {
    final response = await http.post(
      Uri.parse('${ApiConfig.baseUrl}/auth/logout'),
      headers: await _authorizedJsonHeaders(),
    );

    if (response.statusCode != 200) {
      final data = _decodeJson(response);
      throw ApiException(_extractMessage(data), statusCode: response.statusCode);
    }

    await clearToken();
  }

  Future<AuthUser> getMe() async {
    final response = await http.get(
      Uri.parse('${ApiConfig.baseUrl}/auth/me'),
      headers: await _authorizedJsonHeaders(),
    );

    final data = _decodeJson(response);
    if (response.statusCode != 200) {
      throw ApiException(_extractMessage(data), statusCode: response.statusCode);
    }

    return AuthUser.fromJson((data['user'] as Map<String, dynamic>?) ?? <String, dynamic>{});
  }

  Future<Map<String, dynamic>> getDashboardSummary() async {
    return _getMap('/dashboard/summary');
  }

  Future<List<dynamic>> getClasses() async {
    return _getList('/classes');
  }

  Future<List<dynamic>> getAnnouncements() async {
    return _getList('/announcements');
  }

  Future<List<dynamic>> getNotifications() async {
    return _getList('/notifications');
  }

  Future<List<dynamic>> getMessages() async {
    return _getList('/messages');
  }

  Future<Map<String, dynamic>> updateProfile({
    String? name,
    String? phone,
    String? address,
    String? bio,
    File? imageFile,
  }) async {
    if (imageFile == null) {
      final response = await http.patch(
        Uri.parse('${ApiConfig.baseUrl}/auth/profile'),
        headers: await _authorizedJsonHeaders(),
        body: jsonEncode({
          'name': name,
          'phone': phone,
          'address': address,
          'bio': bio,
        }..removeWhere((key, value) => value == null || value == '')),
      );

      final data = _decodeJson(response);
      if (response.statusCode != 200) {
        throw ApiException(_extractMessage(data), statusCode: response.statusCode);
      }

      return data;
    }

    final token = await getToken();
    if (token == null || token.isEmpty) {
      throw ApiException('No API token found.');
    }

    final request = http.MultipartRequest(
      'PATCH',
      Uri.parse('${ApiConfig.baseUrl}/auth/profile'),
    );

    request.headers['Accept'] = 'application/json';
    request.headers['Authorization'] = 'Bearer $token';

    if (name != null && name.isNotEmpty) request.fields['name'] = name;
    if (phone != null && phone.isNotEmpty) request.fields['phone'] = phone;
    if (address != null && address.isNotEmpty) request.fields['address'] = address;
    if (bio != null && bio.isNotEmpty) request.fields['bio'] = bio;

    request.files.add(await http.MultipartFile.fromPath('image', imageFile.path));

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    final data = _decodeJson(response);

    if (response.statusCode != 200) {
      throw ApiException(_extractMessage(data), statusCode: response.statusCode);
    }

    return data;
  }

  Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(ApiConfig.tokenKey);
  }

  Future<void> clearToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(ApiConfig.tokenKey);
  }

  Future<void> _saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(ApiConfig.tokenKey, token);
  }

  Future<Map<String, String>> _authorizedJsonHeaders() async {
    final token = await getToken();
    if (token == null || token.isEmpty) {
      throw ApiException('No API token found.');
    }

    return _jsonHeaders(token: token);
  }

  Map<String, String> _jsonHeaders({String? token}) {
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  Future<Map<String, dynamic>> _getMap(String path) async {
    final response = await http.get(
      Uri.parse('${ApiConfig.baseUrl}$path'),
      headers: await _authorizedJsonHeaders(),
    );

    final data = _decodeJson(response);
    if (response.statusCode != 200) {
      throw ApiException(_extractMessage(data), statusCode: response.statusCode);
    }

    return data;
  }

  Future<List<dynamic>> _getList(String path) async {
    final data = await _getMap(path);
    final payload = data['data'];
    if (payload is List<dynamic>) {
      return payload;
    }

    return <dynamic>[];
  }

  Map<String, dynamic> _decodeJson(http.Response response) {
    final body = response.body.trim();
    if (body.isEmpty) {
      return <String, dynamic>{};
    }

    final decoded = jsonDecode(body);
    if (decoded is Map<String, dynamic>) {
      return decoded;
    }

    return <String, dynamic>{'data': decoded};
  }

  String _extractMessage(Map<String, dynamic> data) {
    if (data['message'] != null) {
      return data['message'].toString();
    }

    final errors = data['errors'];
    if (errors is Map<String, dynamic>) {
      for (final value in errors.values) {
        if (value is List && value.isNotEmpty) {
          return value.first.toString();
        }
      }
    }

    return 'Request failed.';
  }
}
