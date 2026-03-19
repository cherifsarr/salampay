import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get/get.dart' hide Response;
import '../utils/constants.dart';

class ApiService extends GetxService {
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  static const bool isDev = true;

  String get baseUrl => isDev ? AppConstants.devBaseUrl : AppConstants.baseUrl;

  Future<ApiService> init() async {
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(milliseconds: AppConstants.connectionTimeout),
      receiveTimeout: const Duration(milliseconds: AppConstants.receiveTimeout),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
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
          await clearSession();
          Get.offAllNamed('/login');
        }
        return handler.next(error);
      },
    ));

    return this;
  }

  // Auth endpoints
  Future<ApiResponse> login({required String email, required String password}) async {
    return _post('/auth/login', {'email': email, 'password': password});
  }

  Future<ApiResponse> logout() async {
    return _post('/auth/logout', {});
  }

  // Dashboard
  Future<ApiResponse> getDashboard() async {
    return _get('/dashboard');
  }

  Future<ApiResponse> getStats({String period = 'today'}) async {
    return _get('/stats', queryParams: {'period': period});
  }

  // QR Codes
  Future<ApiResponse> getQrCodes({int page = 1}) async {
    return _get('/qr-codes', queryParams: {'page': page});
  }

  Future<ApiResponse> createQrCode({required String type, double? amount, String? description}) async {
    return _post('/qr-codes', {
      'type': type,
      if (amount != null) 'amount': amount,
      if (description != null) 'description': description,
    });
  }

  Future<ApiResponse> deleteQrCode(String id) async {
    return _delete('/qr-codes/$id');
  }

  // Payment Links
  Future<ApiResponse> getPaymentLinks({int page = 1}) async {
    return _get('/payment-links', queryParams: {'page': page});
  }

  Future<ApiResponse> createPaymentLink({
    required String title,
    required double amount,
    String? description,
    bool isReusable = false,
    int? maxUses,
    DateTime? expiresAt,
  }) async {
    return _post('/payment-links', {
      'title': title,
      'amount': amount,
      if (description != null) 'description': description,
      'is_reusable': isReusable,
      if (maxUses != null) 'max_uses': maxUses,
      if (expiresAt != null) 'expires_at': expiresAt.toIso8601String(),
    });
  }

  Future<ApiResponse> deletePaymentLink(String id) async {
    return _delete('/payment-links/$id');
  }

  // Transactions
  Future<ApiResponse> getTransactions({int page = 1, String? status, String? type}) async {
    return _get('/transactions', queryParams: {
      'page': page,
      if (status != null) 'status': status,
      if (type != null) 'type': type,
    });
  }

  Future<ApiResponse> getTransaction(String id) async {
    return _get('/transactions/$id');
  }

  Future<ApiResponse> refundTransaction(String id) async {
    return _post('/transactions/$id/refund', {});
  }

  // Settlements
  Future<ApiResponse> getSettlements({int page = 1}) async {
    return _get('/settlements', queryParams: {'page': page});
  }

  Future<ApiResponse> requestSettlement() async {
    return _post('/settlements/request', {});
  }

  // Account
  Future<ApiResponse> getAccount() async {
    return _get('/account');
  }

  Future<ApiResponse> updateAccount(Map<String, dynamic> data) async {
    return _put('/account', data);
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

  Future<ApiResponse> _delete(String path) async {
    try {
      final response = await _dio.delete(path);
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
    } else if (e.type == DioExceptionType.connectionError) {
      message = 'Erreur de connexion';
    }
    return ApiResponse.error(message, statusCode: e.response?.statusCode);
  }

  // Session management
  Future<void> saveToken(String token) async {
    await _storage.write(key: AppConstants.tokenKey, value: token);
  }

  Future<String?> getToken() async {
    return await _storage.read(key: AppConstants.tokenKey);
  }

  Future<void> saveMerchant(Map<String, dynamic> merchant) async {
    await _storage.write(key: AppConstants.merchantKey, value: jsonEncode(merchant));
  }

  Future<Map<String, dynamic>?> getMerchant() async {
    final data = await _storage.read(key: AppConstants.merchantKey);
    if (data != null) return jsonDecode(data);
    return null;
  }

  Future<void> clearSession() async {
    await _storage.delete(key: AppConstants.tokenKey);
    await _storage.delete(key: AppConstants.merchantKey);
  }
}

class ApiResponse {
  final bool success;
  final dynamic data;
  final String? message;
  final int? statusCode;

  ApiResponse({required this.success, this.data, this.message, this.statusCode});

  factory ApiResponse.success(dynamic data) {
    return ApiResponse(success: true, data: data, message: data is Map ? data['message'] : null);
  }

  factory ApiResponse.error(String message, {int? statusCode}) {
    return ApiResponse(success: false, message: message, statusCode: statusCode);
  }
}
