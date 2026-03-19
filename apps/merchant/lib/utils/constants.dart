class AppConstants {
  static const String appName = 'SalamPay Merchant';
  static const String appVersion = '1.0.0';

  // API Configuration
  static const String baseUrl = 'https://api.salampay.sn/v1/merchant';
  static const String devBaseUrl = 'http://10.0.2.2:8000/api/v1/merchant';

  // Storage Keys
  static const String tokenKey = 'merchant_token';
  static const String merchantKey = 'merchant_data';
  static const String apiKeyKey = 'api_key';
  static const String pinSetKey = 'pin_set';

  // Validation
  static const int pinLength = 4;
  static const int phoneMinLength = 9;
  static const int phoneMaxLength = 9;

  // Phone prefix for Senegal
  static const String countryCode = '+221';

  // Timeouts
  static const int connectionTimeout = 30000;
  static const int receiveTimeout = 30000;

  // QR Code
  static const double qrSize = 250.0;
  static const String qrPrefix = 'salampay://pay/';
}

class AppAssets {
  static const String logo = 'assets/images/logo.png';
  static const String logoWhite = 'assets/images/logo_white.png';
  static const String emptyState = 'assets/images/empty_state.png';
  static const String qrPlaceholder = 'assets/images/qr_placeholder.png';
}
