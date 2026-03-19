import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../models/transaction_model.dart';
import '../services/api_service.dart';

class TransactionController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final transactions = <Transaction>[].obs;
  final isLoading = false.obs;
  final currentPage = 1.obs;
  final hasMore = true.obs;
  final selectedFilter = 'all'.obs;

  @override
  void onInit() {
    super.onInit();
    loadTransactions();
  }

  Future<void> loadTransactions({bool refresh = false}) async {
    if (refresh) {
      currentPage.value = 1;
      hasMore.value = true;
      transactions.clear();
    }

    if (!hasMore.value) return;

    isLoading.value = true;
    try {
      final response = await _api.getTransactions(
        page: currentPage.value,
        status: selectedFilter.value != 'all' ? selectedFilter.value : null,
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          final newTx = data.map((t) => Transaction.fromJson(t)).toList();
          transactions.addAll(newTx);
          hasMore.value = newTx.isNotEmpty;
          currentPage.value++;
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  void setFilter(String filter) {
    selectedFilter.value = filter;
    loadTransactions(refresh: true);
  }

  Future<void> refundTransaction(Transaction tx) async {
    final confirm = await Get.dialog<bool>(
      AlertDialog(
        title: const Text('Confirmer le remboursement'),
        content: Text('Rembourser ${tx.formattedAmount} ?'),
        actions: [
          TextButton(onPressed: () => Get.back(result: false), child: const Text('Annuler')),
          TextButton(
            onPressed: () => Get.back(result: true),
            child: const Text('Rembourser', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    isLoading.value = true;
    try {
      final response = await _api.refundTransaction(tx.uuid);
      if (response.success) {
        loadTransactions(refresh: true);
        Get.snackbar('Succès', 'Remboursement effectué', backgroundColor: Colors.green, colorText: Colors.white);
      } else {
        Get.snackbar('Erreur', response.message ?? 'Remboursement échoué', backgroundColor: Colors.red, colorText: Colors.white);
      }
    } finally {
      isLoading.value = false;
    }
  }
}
