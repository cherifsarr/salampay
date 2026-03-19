import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../models/merchant_model.dart';
import '../services/api_service.dart';
import '../routes/app_routes.dart';

class AuthController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final emailController = TextEditingController();
  final passwordController = TextEditingController();
  final isLoading = false.obs;
  final isPasswordVisible = false.obs;
  final currentMerchant = Rxn<Merchant>();

  @override
  void onClose() {
    emailController.dispose();
    passwordController.dispose();
    super.onClose();
  }

  void togglePasswordVisibility() {
    isPasswordVisible.value = !isPasswordVisible.value;
  }

  Future<void> login() async {
    if (isLoading.value) return;

    if (emailController.text.isEmpty || passwordController.text.isEmpty) {
      Get.snackbar('Erreur', 'Veuillez remplir tous les champs', backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    isLoading.value = true;

    try {
      final response = await _api.login(
        email: emailController.text.trim(),
        password: passwordController.text,
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        final token = data['token'];
        final merchantData = data['merchant'];

        await _api.saveToken(token);
        await _api.saveMerchant(merchantData);

        currentMerchant.value = Merchant.fromJson(merchantData);
        Get.offAllNamed(AppRoutes.dashboard);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Connexion échouée', backgroundColor: Colors.red, colorText: Colors.white);
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
      await _api.clearSession();
      currentMerchant.value = null;
      isLoading.value = false;
      Get.offAllNamed(AppRoutes.login);
    }
  }

  Future<void> checkAuthStatus() async {
    final token = await _api.getToken();
    if (token != null) {
      final merchantData = await _api.getMerchant();
      if (merchantData != null) {
        currentMerchant.value = Merchant.fromJson(merchantData);
        Get.offAllNamed(AppRoutes.dashboard);
        return;
      }
    }
    Get.offAllNamed(AppRoutes.login);
  }
}
