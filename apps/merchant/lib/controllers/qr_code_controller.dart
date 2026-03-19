import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../models/qr_code_model.dart';
import '../services/api_service.dart';
import '../routes/app_routes.dart';

class QrCodeController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final qrCodes = <QrCode>[].obs;
  final selectedQrCode = Rxn<QrCode>();
  final isLoading = false.obs;
  final currentPage = 1.obs;
  final hasMore = true.obs;

  // Form fields
  final amountController = TextEditingController();
  final descriptionController = TextEditingController();
  final selectedType = 'static'.obs;

  @override
  void onInit() {
    super.onInit();
    loadQrCodes();
  }

  @override
  void onClose() {
    amountController.dispose();
    descriptionController.dispose();
    super.onClose();
  }

  Future<void> loadQrCodes({bool refresh = false}) async {
    if (refresh) {
      currentPage.value = 1;
      hasMore.value = true;
      qrCodes.clear();
    }

    if (!hasMore.value) return;

    isLoading.value = true;
    try {
      final response = await _api.getQrCodes(page: currentPage.value);
      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          final newCodes = data.map((q) => QrCode.fromJson(q)).toList();
          qrCodes.addAll(newCodes);
          hasMore.value = newCodes.isNotEmpty;
          currentPage.value++;
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> createQrCode() async {
    if (isLoading.value) return;

    double? amount;
    if (amountController.text.isNotEmpty) {
      amount = double.tryParse(amountController.text);
      if (amount == null || amount <= 0) {
        Get.snackbar('Erreur', 'Montant invalide', backgroundColor: Colors.red, colorText: Colors.white);
        return;
      }
    }

    isLoading.value = true;
    try {
      final response = await _api.createQrCode(
        type: selectedType.value,
        amount: amount,
        description: descriptionController.text.isNotEmpty ? descriptionController.text : null,
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        final newQrCode = QrCode.fromJson(data);
        qrCodes.insert(0, newQrCode);
        selectedQrCode.value = newQrCode;

        clearForm();
        Get.back();
        Get.snackbar('Succès', 'QR Code créé', backgroundColor: Colors.green, colorText: Colors.white);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Création échouée', backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> deleteQrCode(QrCode qrCode) async {
    isLoading.value = true;
    try {
      final response = await _api.deleteQrCode(qrCode.uuid);
      if (response.success) {
        qrCodes.remove(qrCode);
        Get.snackbar('Succès', 'QR Code supprimé', backgroundColor: Colors.green, colorText: Colors.white);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Suppression échouée', backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  void clearForm() {
    amountController.clear();
    descriptionController.clear();
    selectedType.value = 'static';
  }

  void selectQrCode(QrCode qrCode) {
    selectedQrCode.value = qrCode;
  }
}
