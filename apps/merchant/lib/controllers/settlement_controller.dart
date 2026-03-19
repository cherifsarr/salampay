import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../models/transaction_model.dart';
import '../services/api_service.dart';

class SettlementController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final settlements = <Settlement>[].obs;
  final isLoading = false.obs;
  final currentPage = 1.obs;
  final hasMore = true.obs;

  @override
  void onInit() {
    super.onInit();
    loadSettlements();
  }

  Future<void> loadSettlements({bool refresh = false}) async {
    if (refresh) {
      currentPage.value = 1;
      hasMore.value = true;
      settlements.clear();
    }

    if (!hasMore.value) return;

    isLoading.value = true;
    try {
      final response = await _api.getSettlements(page: currentPage.value);
      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          final newSettlements = data.map((s) => Settlement.fromJson(s)).toList();
          settlements.addAll(newSettlements);
          hasMore.value = newSettlements.isNotEmpty;
          currentPage.value++;
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> requestSettlement() async {
    final confirm = await Get.dialog<bool>(
      AlertDialog(
        title: const Text('Demander un versement'),
        content: const Text('Souhaitez-vous demander le versement de votre solde disponible ?'),
        actions: [
          TextButton(onPressed: () => Get.back(result: false), child: const Text('Annuler')),
          TextButton(onPressed: () => Get.back(result: true), child: const Text('Confirmer')),
        ],
      ),
    );

    if (confirm != true) return;

    isLoading.value = true;
    try {
      final response = await _api.requestSettlement();
      if (response.success) {
        loadSettlements(refresh: true);
        Get.snackbar('Succès', 'Demande de versement envoyée', backgroundColor: Colors.green, colorText: Colors.white);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Demande échouée', backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }
}
