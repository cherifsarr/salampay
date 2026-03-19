import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../services/api_service.dart';
import '../routes/app_routes.dart';

class PaymentMethod {
  final String id;
  final String name;
  final String icon;
  final bool available;

  PaymentMethod({
    required this.id,
    required this.name,
    required this.icon,
    this.available = true,
  });

  factory PaymentMethod.fromJson(Map<String, dynamic> json) {
    return PaymentMethod(
      id: json['id'] ?? json['provider'] ?? '',
      name: json['name'] ?? '',
      icon: json['icon'] ?? '',
      available: json['available'] ?? true,
    );
  }
}

class PaymentController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final amountController = TextEditingController();
  final recipientController = TextEditingController();
  final pinController = TextEditingController();
  final descriptionController = TextEditingController();

  final depositMethods = <PaymentMethod>[].obs;
  final withdrawMethods = <PaymentMethod>[].obs;
  final selectedMethod = Rxn<PaymentMethod>();
  final isLoading = false.obs;
  final checkoutUrl = ''.obs;

  @override
  void onClose() {
    amountController.dispose();
    recipientController.dispose();
    pinController.dispose();
    descriptionController.dispose();
    super.onClose();
  }

  void clearForm() {
    amountController.clear();
    recipientController.clear();
    pinController.clear();
    descriptionController.clear();
    selectedMethod.value = null;
    checkoutUrl.value = '';
  }

  Future<void> loadDepositMethods() async {
    isLoading.value = true;
    try {
      final response = await _api.getDepositMethods();
      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          depositMethods.value = data.map((m) => PaymentMethod.fromJson(m)).toList();
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadWithdrawMethods() async {
    isLoading.value = true;
    try {
      final response = await _api.getWithdrawalMethods();
      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          withdrawMethods.value = data.map((m) => PaymentMethod.fromJson(m)).toList();
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> initiateDeposit() async {
    if (selectedMethod.value == null) {
      Get.snackbar('Erreur', 'Veuillez sélectionner un mode de paiement',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    final amount = double.tryParse(amountController.text);
    if (amount == null || amount <= 0) {
      Get.snackbar('Erreur', 'Montant invalide',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    isLoading.value = true;
    try {
      final response = await _api.initiateDeposit(
        provider: selectedMethod.value!.id,
        amount: amount,
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        checkoutUrl.value = data['checkout_url'] ?? '';

        if (checkoutUrl.value.isNotEmpty) {
          Get.snackbar('Succès', 'Redirection vers le paiement...',
              backgroundColor: Colors.green, colorText: Colors.white);
          // TODO: Open WebView for payment
        }
      } else {
        Get.snackbar('Erreur', response.message ?? 'Dépôt échoué',
            backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> initiateWithdrawal() async {
    if (selectedMethod.value == null) {
      Get.snackbar('Erreur', 'Veuillez sélectionner un mode de retrait',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    final amount = double.tryParse(amountController.text);
    if (amount == null || amount <= 0) {
      Get.snackbar('Erreur', 'Montant invalide',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    if (recipientController.text.isEmpty) {
      Get.snackbar('Erreur', 'Numéro de téléphone requis',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    if (pinController.text.length != 4) {
      Get.snackbar('Erreur', 'Code PIN invalide',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    isLoading.value = true;
    try {
      final response = await _api.initiateWithdrawal(
        provider: selectedMethod.value!.id,
        mobile: recipientController.text,
        amount: amount,
        pin: pinController.text,
      );

      if (response.success) {
        Get.snackbar('Succès', 'Retrait initié avec succès',
            backgroundColor: Colors.green, colorText: Colors.white);
        clearForm();
        Get.offAllNamed(AppRoutes.home);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Retrait échoué',
            backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> initiateTransfer() async {
    final amount = double.tryParse(amountController.text);
    if (amount == null || amount <= 0) {
      Get.snackbar('Erreur', 'Montant invalide',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    if (recipientController.text.isEmpty) {
      Get.snackbar('Erreur', 'Numéro du destinataire requis',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    if (pinController.text.length != 4) {
      Get.snackbar('Erreur', 'Code PIN invalide',
          backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    isLoading.value = true;
    try {
      final response = await _api.transfer(
        recipientPhone: '+221${recipientController.text}',
        amount: amount,
        pin: pinController.text,
        description: descriptionController.text.isNotEmpty
            ? descriptionController.text
            : null,
      );

      if (response.success) {
        Get.snackbar('Succès', 'Transfert effectué avec succès',
            backgroundColor: Colors.green, colorText: Colors.white);
        clearForm();
        Get.offAllNamed(AppRoutes.home);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Transfert échoué',
            backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> scanQrCode(String qrData) async {
    isLoading.value = true;
    try {
      final response = await _api.scanQrCode(qrData);

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        // Handle QR payment data
        Get.snackbar('QR Code', 'Code scanné: ${data['type'] ?? 'payment'}',
            backgroundColor: Colors.green, colorText: Colors.white);
      } else {
        Get.snackbar('Erreur', response.message ?? 'QR Code invalide',
            backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }
}
