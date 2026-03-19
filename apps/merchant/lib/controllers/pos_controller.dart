import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../services/api_service.dart';

class PosController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final amountController = TextEditingController();
  final descriptionController = TextEditingController();

  final isLoading = false.obs;
  final currentAmount = '0'.obs;
  final generatedQrCode = ''.obs;
  final paymentStatus = ''.obs; // 'pending', 'success', 'failed'
  final lastTransaction = Rxn<Map<String, dynamic>>();

  @override
  void onClose() {
    amountController.dispose();
    descriptionController.dispose();
    super.onClose();
  }

  void appendDigit(String digit) {
    if (currentAmount.value == '0') {
      currentAmount.value = digit;
    } else if (currentAmount.value.length < 10) {
      currentAmount.value += digit;
    }
    amountController.text = currentAmount.value;
  }

  void deleteDigit() {
    if (currentAmount.value.length > 1) {
      currentAmount.value = currentAmount.value.substring(0, currentAmount.value.length - 1);
    } else {
      currentAmount.value = '0';
    }
    amountController.text = currentAmount.value;
  }

  void clearAmount() {
    currentAmount.value = '0';
    amountController.clear();
    generatedQrCode.value = '';
    paymentStatus.value = '';
  }

  double get amount => double.tryParse(currentAmount.value) ?? 0;

  String get formattedAmount {
    final value = amount;
    return '${value.toStringAsFixed(0)} XOF';
  }

  bool get isValidAmount => amount >= 100;

  Future<void> generatePaymentQr() async {
    if (!isValidAmount) {
      Get.snackbar('Erreur', 'Montant minimum: 100 XOF', backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    isLoading.value = true;
    paymentStatus.value = 'pending';

    try {
      // Create a dynamic QR code for this payment
      final response = await _api.createQrCode(
        type: 'dynamic',
        amount: amount,
        description: descriptionController.text.isNotEmpty ? descriptionController.text : 'Paiement POS',
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        generatedQrCode.value = 'salampay://pay/${data['code']}';

        // Start polling for payment status
        _pollPaymentStatus(data['uuid'] ?? data['id'].toString());
      } else {
        paymentStatus.value = 'failed';
        Get.snackbar('Erreur', response.message ?? 'Génération échouée', backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> _pollPaymentStatus(String qrCodeId) async {
    // Poll every 3 seconds for up to 5 minutes
    for (int i = 0; i < 100; i++) {
      await Future.delayed(const Duration(seconds: 3));

      if (paymentStatus.value != 'pending') break;

      try {
        // Check if QR code has been used (payment received)
        final response = await _api.getQrCodeStatus(qrCodeId);
        if (response.success) {
          final data = response.data['data'] ?? response.data;
          if (data['last_payment'] != null) {
            paymentStatus.value = 'success';
            lastTransaction.value = data['last_payment'];
            Get.snackbar('Paiement reçu!', formattedAmount, backgroundColor: Colors.green, colorText: Colors.white);
            break;
          }
        }
      } catch (e) {
        // Continue polling
      }
    }
  }

  void cancelPayment() {
    paymentStatus.value = '';
    generatedQrCode.value = '';
  }

  void newTransaction() {
    clearAmount();
    descriptionController.clear();
    lastTransaction.value = null;
  }
}
