import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../models/payment_link_model.dart';
import '../services/api_service.dart';

class PaymentLinkController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final paymentLinks = <PaymentLink>[].obs;
  final isLoading = false.obs;
  final currentPage = 1.obs;
  final hasMore = true.obs;

  // Form fields
  final titleController = TextEditingController();
  final amountController = TextEditingController();
  final descriptionController = TextEditingController();
  final maxUsesController = TextEditingController();
  final isReusable = false.obs;
  final expiresAt = Rxn<DateTime>();

  @override
  void onInit() {
    super.onInit();
    loadPaymentLinks();
  }

  @override
  void onClose() {
    titleController.dispose();
    amountController.dispose();
    descriptionController.dispose();
    maxUsesController.dispose();
    super.onClose();
  }

  Future<void> loadPaymentLinks({bool refresh = false}) async {
    if (refresh) {
      currentPage.value = 1;
      hasMore.value = true;
      paymentLinks.clear();
    }

    if (!hasMore.value) return;

    isLoading.value = true;
    try {
      final response = await _api.getPaymentLinks(page: currentPage.value);
      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          final newLinks = data.map((l) => PaymentLink.fromJson(l)).toList();
          paymentLinks.addAll(newLinks);
          hasMore.value = newLinks.isNotEmpty;
          currentPage.value++;
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> createPaymentLink() async {
    if (isLoading.value) return;

    if (titleController.text.isEmpty) {
      Get.snackbar('Erreur', 'Titre requis', backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    final amount = double.tryParse(amountController.text);
    if (amount == null || amount <= 0) {
      Get.snackbar('Erreur', 'Montant invalide', backgroundColor: Colors.red, colorText: Colors.white);
      return;
    }

    isLoading.value = true;
    try {
      final response = await _api.createPaymentLink(
        title: titleController.text,
        amount: amount,
        description: descriptionController.text.isNotEmpty ? descriptionController.text : null,
        isReusable: isReusable.value,
        maxUses: maxUsesController.text.isNotEmpty ? int.tryParse(maxUsesController.text) : null,
        expiresAt: expiresAt.value,
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        paymentLinks.insert(0, PaymentLink.fromJson(data));
        clearForm();
        Get.back();
        Get.snackbar('Succès', 'Lien créé', backgroundColor: Colors.green, colorText: Colors.white);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Création échouée', backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> deletePaymentLink(PaymentLink link) async {
    isLoading.value = true;
    try {
      final response = await _api.deletePaymentLink(link.uuid);
      if (response.success) {
        paymentLinks.remove(link);
        Get.snackbar('Succès', 'Lien supprimé', backgroundColor: Colors.green, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }

  void clearForm() {
    titleController.clear();
    amountController.clear();
    descriptionController.clear();
    maxUsesController.clear();
    isReusable.value = false;
    expiresAt.value = null;
  }
}
