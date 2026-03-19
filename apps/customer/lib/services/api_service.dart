import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get/get.dart' hide Response;
import '../utils/constants.dart';

class ApiService extends GetxService {
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  static const bool isDev = true; // Toggle for development

  String get baseUrl => isDev ? AppConstants.devBaseUrl : AppConstants.baseUrl;

  Future<ApiService> init() async {
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(milliseconds: AppConstants.connectionTimeout),
      receiveTimeout: const Duration(milliseconds: AppConstants.receiveTimeout),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: AppConstants.tokenKey);
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          await _storage.delete(key: AppConstants.tokenKey);
          await _storage.delete(key: AppConstants.userKey);
          Get.offAllNamed('/login');
        }
        return handler.next(error);
      },
    ));

    return this;
  }

  // Auth endpoints
  Future<ApiResponse> register({
    required String phone,
    required String password,
    String? firstName,
    String? lastName,
  }) async {
    return _post('/auth/register', {
      'phone': phone,
      'password': password,
      'password_confirmation': password,
      if (firstName != null) 'first_name': firstName,
      if (lastName != null) 'last_name': lastName,
    });
  }

  Future<ApiResponse> login({
    required String phone,
    required String password,
  }) async {
    return _post('/auth/login', {
      'phone': phone,
      'password': password,
    });
  }

  Future<ApiResponse> loginWithPin({
    required String phone,
    required String pin,
  }) async {
    return _post('/auth/login/pin', {
      'phone': phone,
      'pin': pin,
    });
  }

  Future<ApiResponse> requestOtp(String phone) async {
    return _post('/auth/otp/request', {'phone': phone});
  }

  Future<ApiResponse> verifyOtp(String phone, String code) async {
    return _post('/auth/otp/verify', {
      'phone': phone,
      'code': code,
    });
  }

  Future<ApiResponse> setPin(String pin) async {
    return _post('/auth/pin/set', {
      'pin': pin,
      'pin_confirmation': pin,
    });
  }

  Future<ApiResponse> changePin(String currentPin, String newPin) async {
    return _post('/auth/pin/change', {
      'current_pin': currentPin,
      'new_pin': newPin,
      'new_pin_confirmation': newPin,
    });
  }

  Future<ApiResponse> logout() async {
    return _post('/auth/logout', {});
  }

  // User endpoints
  Future<ApiResponse> getProfile() async {
    return _get('/user/profile');
  }

  Future<ApiResponse> updateProfile(Map<String, dynamic> data) async {
    return _put('/user/profile', data);
  }

  // Wallet endpoints
  Future<ApiResponse> getWallets() async {
    return _get('/wallets');
  }

  Future<ApiResponse> getWalletBalance(String walletId) async {
    return _get('/wallets/$walletId/balance');
  }

  Future<ApiResponse> getWalletTransactions(String walletId, {int page = 1}) async {
    return _get('/wallets/$walletId/transactions', queryParams: {'page': page});
  }

  // Payment endpoints
  Future<ApiResponse> getDepositMethods() async {
    return _get('/payments/deposit/methods');
  }

  Future<ApiResponse> initiateDeposit({
    required String provider,
    required double amount,
  }) async {
    return _post('/payments/deposit', {
      'provider': provider,
      'amount': amount,
    });
  }

  Future<ApiResponse> getWithdrawalMethods() async {
    return _get('/payments/withdraw/methods');
  }

  Future<ApiResponse> initiateWithdrawal({
    required String provider,
    required String mobile,
    required double amount,
    required String pin,
  }) async {
    return _post('/payments/withdraw', {
      'provider': provider,
      'mobile': mobile,
      'amount': amount,
      'pin': pin,
    });
  }

  Future<ApiResponse> transfer({
    required String recipientPhone,
    required double amount,
    required String pin,
    String? description,
  }) async {
    return _post('/payments/transfer', {
      'recipient_phone': recipientPhone,
      'amount': amount,
      'pin': pin,
      if (description != null) 'description': description,
    });
  }

  Future<ApiResponse> scanQrCode(String qrData) async {
    return _post('/payments/qr/scan', {'qr_data': qrData});
  }

  // Transaction endpoints
  Future<ApiResponse> getTransactions({int page = 1, String? type}) async {
    return _get('/transactions', queryParams: {
      'page': page,
      if (type != null) 'type': type,
    });
  }

  Future<ApiResponse> getTransaction(String id) async {
    return _get('/transactions/$id');
  }

  // Helper methods
  Future<ApiResponse> _get(String path, {Map<String, dynamic>? queryParams}) async {
    try {
      final response = await _dio.get(path, queryParameters: queryParams);
      return ApiResponse.success(response.data);
    } on DioException catch (e) {
      return _handleError(e);
    } catch (e) {
      return ApiResponse.error(e.toString());
    }
  }

  Future<ApiResponse> _post(String path, Map<String, dynamic> data) async {
    try {
      final response = await _dio.post(path, data: data);
      return ApiResponse.success(response.data);
    } on DioException catch (e) {
      return _handleError(e);
    } catch (e) {
      return ApiResponse.error(e.toString());
    }
  }

  Future<ApiResponse> _put(String path, Map<String, dynamic> data) async {
    try {
      final response = await _dio.put(path, data: data);
      return ApiResponse.success(response.data);
    } on DioException catch (e) {
      return _handleError(e);
    } catch (e) {
      return ApiResponse.error(e.toString());
    }
  }

  ApiResponse _handleError(DioException e) {
    String message = 'Une erreur est survenue';

    if (e.response != null) {
      final data = e.response?.data;
      if (data is Map<String, dynamic>) {
        message = data['message'] ?? data['error'] ?? message;
      }
    } else if (e.type == DioExceptionType.connectionTimeout) {
      message = 'Délai de connexion dépassé';
    } else if (e.type == DioExceptionType.receiveTimeout) {
      message = 'Délai de réponse dépassé';
    } else if (e.type == DioExceptionType.connectionError) {
      message = 'Erreur de connexion. Vérifiez votre connexion internet.';
    }

    return ApiResponse.error(message, statusCode: e.response?.statusCode);
  }

  // Token management
  Future<void> saveToken(String token) async {
    await _storage.write(key: AppConstants.tokenKey, value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: AppConstants.tokenKey);
  }

  Future<void> clearToken() async {
    await _storage.delete(key: AppConstants.tokenKey);
  }

  Future<void> saveUser(Map<String, dynamic> user) async {
    await _storage.write(key: AppConstants.userKey, value: jsonEncode(user));
  }

  Future<Map<String, dynamic>?> getUser() async {
    final data = await _storage.read(key: AppConstants.userKey);
    if (data != null) {
      return jsonDecode(data);
    }
    return null;
  }

  Future<void> clearUser() async {
    await _storage.delete(key: AppConstants.userKey);
  }
}

class ApiResponse {
  final bool success;
  final dynamic data;
  final String? message;
  final int? statusCode;

  ApiResponse({
    required this.success,
    this.data,
    this.message,
    this.statusCode,
  });

  factory ApiResponse.success(dynamic data) {
    return ApiResponse(
      success: true,
      data: data,
      message: data is Map ? data['message'] : null,
    );
  }

  factory ApiResponse.error(String message, {int? statusCode}) {
    return ApiResponse(
      success: false,
      message: message,
      statusCode: statusCode,
    );
  }
}
