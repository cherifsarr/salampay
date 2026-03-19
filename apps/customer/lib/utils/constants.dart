class AppConstants {
  static const String appName = 'SalamPay';
  static const String appVersion = '1.0.0';

  // API Configuration
  static const String baseUrl = 'https://api.salampay.sn/v1';
  static const String devBaseUrl = 'http://10.0.2.2:8000/api/v1'; // Android emulator

  // Storage Keys
  static const String tokenKey = 'auth_token';
  static const String userKey = 'user_data';
  static const String pinSetKey = 'pin_set';
  static const String biometricEnabledKey = 'biometric_enabled';
  static const String languageKey = 'app_language';
  static const String themeKey = 'app_theme';

  // Validation
  static const int pinLength = 4;
  static const int otpLength = 6;
  static const int phoneMinLength = 9;
  static const int phoneMaxLength = 9;

  // Phone prefix for Senegal
  static const String countryCode = '+221';
  static const List<String> validPrefixes = ['70', '75', '76', '77', '78'];

  // Timeouts
  static const int connectionTimeout = 30000;
  static const int receiveTimeout = 30000;

  // Transaction limits
  static const double minTransferAmount = 100;
  static const double maxTransferAmount = 500000;
  static const double minDepositAmount = 500;
  static const double maxDepositAmount = 1000000;
}

class AppAssets {
  // Images
  static const String logo = 'assets/images/logo.png';
  static const String logoWhite = 'assets/images/logo_white.png';
  static const String onboarding1 = 'assets/images/onboarding_1.png';
  static const String onboarding2 = 'assets/images/onboarding_2.png';
  static const String onboarding3 = 'assets/images/onboarding_3.png';
  static const String emptyState = 'assets/images/empty_state.png';
  static const String successCheck = 'assets/images/success_check.png';

  // Icons
  static const String waveIcon = 'assets/icons/wave.svg';
  static const String orangeMoneyIcon = 'assets/icons/orange_money.svg';
  static const String freeMoneyIcon = 'assets/icons/free_money.svg';
  static const String wizallIcon = 'assets/icons/wizall.svg';
  static const String emoneyIcon = 'assets/icons/emoney.svg';
}
