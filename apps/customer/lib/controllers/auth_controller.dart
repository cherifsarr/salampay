import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../routes/app_routes.dart';
import '../utils/constants.dart';

class AuthController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final phoneController = TextEditingController();
  final passwordController = TextEditingController();
  final confirmPasswordController = TextEditingController();
  final firstNameController = TextEditingController();
  final lastNameController = TextEditingController();
  final otpController = TextEditingController();
  final pinController = TextEditingController();
  final confirmPinController = TextEditingController();

  final isLoading = false.obs;
  final isPasswordVisible = false.obs;
  final currentUser = Rxn<User>();
  final phoneToVerify = ''.obs;

  @override
  void onClose() {
    phoneController.dispose();
    passwordController.dispose();
    confirmPasswordController.dispose();
    firstNameController.dispose();
    lastNameController.dispose();
    otpController.dispose();
    pinController.dispose();
    confirmPinController.dispose();
    super.onClose();
  }

  String? validatePhone(String? value) {
    if (value == null || value.isEmpty) {
      return 'Le numéro de téléphone est requis';
    }
    final phone = value.replaceAll(RegExp(r'\s+'), '');
    if (phone.length < AppConstants.phoneMinLength) {
      return 'Numéro de téléphone invalide';
    }
    final prefix = phone.substring(0, 2);
    if (!AppConstants.validPrefixes.contains(prefix)) {
      return 'Préfixe invalide (70, 75, 76, 77, 78)';
    }
    return null;
  }

  String? validatePassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Le mot de passe est requis';
    }
    if (value.length < 8) {
      return 'Le mot de passe doit contenir au moins 8 caractères';
    }
    return null;
  }

  String? validatePin(String? value) {
    if (value == null || value.isEmpty) {
      return 'Le code PIN est requis';
    }
    if (value.length != AppConstants.pinLength) {
      return 'Le code PIN doit contenir ${AppConstants.pinLength} chiffres';
    }
    if (!RegExp(r'^\d+$').hasMatch(value)) {
      return 'Le code PIN ne doit contenir que des chiffres';
    }
    return null;
  }

  void togglePasswordVisibility() {
    isPasswordVisible.value = !isPasswordVisible.value;
  }

  String get formattedPhone {
    final phone = phoneController.text.replaceAll(RegExp(r'\s+'), '');
    return '${AppConstants.countryCode}$phone';
  }

  Future<void> register() async {
    if (isLoading.value) return;

    isLoading.value = true;

    try {
      final response = await _api.register(
        phone: formattedPhone,
        password: passwordController.text,
        firstName: firstNameController.text.isNotEmpty
            ? firstNameController.text
            : null,
        lastName: lastNameController.text.isNotEmpty
            ? lastNameController.text
            : null,
      );

      if (response.success) {
        phoneToVerify.value = formattedPhone;
        Get.snackbar(
          'Succès',
          'Compte créé. Veuillez vérifier votre numéro.',
          backgroundColor: Colors.green,
          colorText: Colors.white,
        );
        Get.toNamed(AppRoutes.otp);
      } else {
        Get.snackbar(
          'Erreur',
          response.message ?? 'Inscription échouée',
          backgroundColor: Colors.red,
          colorText: Colors.white,
        );
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> login() async {
    if (isLoading.value) return;

    isLoading.value = true;

    try {
      final response = await _api.login(
        phone: formattedPhone,
        password: passwordController.text,
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        final token = data['token'];
        final userData = data['user'];

        await _api.saveToken(token);
        await _api.saveUser(userData);

        currentUser.value = User.fromJson(userData);

        // Check if PIN is set
        if (currentUser.value!.hasPinSet) {
          Get.offAllNamed(AppRoutes.home);
        } else {
          Get.offAllNamed(AppRoutes.pinSetup);
        }
      } else {
        Get.snackbar(
          'Erreur',
          response.message ?? 'Connexion échouée',
          backgroundColor: Colors.red,
          colorText: Colors.white,
        );
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> requestOtp() async {
    if (isLoading.value) return;

    isLoading.value = true;

    try {
      final phone = phoneToVerify.value.isNotEmpty
          ? phoneToVerify.value
          : formattedPhone;

      final response = await _api.requestOtp(phone);

      if (response.success) {
        Get.snackbar(
          'Code envoyé',
          'Un code de vérification a été envoyé à votre numéro',
          backgroundColor: Colors.green,
          colorText: Colors.white,
        );
      } else {
        Get.snackbar(
          'Erreur',
          response.message ?? 'Envoi du code échoué',
          backgroundColor: Colors.red,
          colorText: Colors.white,
        );
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> verifyOtp() async {
    if (isLoading.value) return;

    isLoading.value = true;

    try {
      final response = await _api.verifyOtp(
        phoneToVerify.value,
        otpController.text,
      );

      if (response.success) {
        Get.snackbar(
          'Succès',
          'Numéro vérifié avec succès',
          backgroundColor: Colors.green,
          colorText: Colors.white,
        );
        Get.offAllNamed(AppRoutes.login);
      } else {
        Get.snackbar(
          'Erreur',
          response.message ?? 'Code incorrect',
          backgroundColor: Colors.red,
          colorText: Colors.white,
        );
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> setPin() async {
    if (isLoading.value) return;

    if (pinController.text != confirmPinController.text) {
      Get.snackbar(
        'Erreur',
        'Les codes PIN ne correspondent pas',
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
      return;
    }

    isLoading.value = true;

    try {
      final response = await _api.setPin(pinController.text);

      if (response.success) {
        Get.snackbar(
          'Succès',
          'Code PIN configuré avec succès',
          backgroundColor: Colors.green,
          colorText: Colors.white,
        );
        Get.offAllNamed(AppRoutes.home);
      } else {
        Get.snackbar(
          'Erreur',
          response.message ?? 'Configuration du PIN échouée',
          backgroundColor: Colors.red,
          colorText: Colors.white,
        );
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> logout() async {
    isLoading.value = true;

    try {
      await _api.logout();
    } finally {
      await _api.clearToken();
      await _api.clearUser();
      currentUser.value = null;
      isLoading.value = false;
      Get.offAllNamed(AppRoutes.login);
    }
  }

  Future<void> checkAuthStatus() async {
    final token = await _api.getToken();
    if (token != null) {
      final userData = await _api.getUser();
      if (userData != null) {
        currentUser.value = User.fromJson(userData);
        Get.offAllNamed(AppRoutes.home);
        return;
      }
    }
    Get.offAllNamed(AppRoutes.login);
  }
}
