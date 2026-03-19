import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../controllers/transaction_controller.dart';
import '../../models/transaction_model.dart';
import '../../theme/app_theme.dart';

class TransactionListView extends GetView<TransactionController> {
  const TransactionListView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Transactions'),
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.filter_list),
            onSelected: controller.setFilter,
            itemBuilder: (context) => [
              const PopupMenuItem(value: 'all', child: Text('Toutes')),
              const PopupMenuItem(value: 'completed', child: Text('Complétées')),
              const PopupMenuItem(value: 'pending', child: Text('En attente')),
              const PopupMenuItem(value: 'failed', child: Text('Échouées')),
            ],
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.transactions.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.transactions.isEmpty) {
          return const Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [Icon(Icons.receipt_long_outlined, size: 64, color: AppColors.textHint), SizedBox(height: 16), Text('Aucune transaction', style: TextStyle(color: AppColors.textSecondary))],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () => controller.loadTransactions(refresh: true),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: controller.transactions.length + (controller.hasMore.value ? 1 : 0),
            itemBuilder: (context, index) {
              if (index >= controller.transactions.length) {
                controller.loadTransactions();
                return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator()));
              }
              return _buildTransactionItem(controller.transactions[index]);
            },
          ),
        );
      }),
    );
  }

  Widget _buildTransactionItem(Transaction tx) {
    Color statusColor;
    switch (tx.status) {
      case TransactionStatus.completed:
        statusColor = AppColors.success;
        break;
      case TransactionStatus.failed:
        statusColor = AppColors.error;
        break;
      case TransactionStatus.refunded:
        statusColor = AppColors.warning;
        break;
      default:
        statusColor = AppColors.info;
    }

    return GestureDetector(
      onTap: () => _showTransactionDetail(tx),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
              child: Icon(Icons.payment, color: statusColor, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(tx.typeLabel, style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(tx.customerPhone ?? tx.reference ?? '', style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(tx.formattedAmount, style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.accent)),
                Text(tx.statusLabel, style: TextStyle(fontSize: 12, color: statusColor)),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _showTransactionDetail(Transaction tx) {
    Get.bottomSheet(
      Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [Text(tx.typeLabel, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)), Text(tx.statusLabel, style: TextStyle(color: tx.status == TransactionStatus.completed ? AppColors.success : AppColors.warning))],
            ),
            const SizedBox(height: 16),
            _buildDetailRow('Montant', tx.formattedAmount),
            _buildDetailRow('Frais', tx.formattedFee),
            _buildDetailRow('Net', tx.formattedNetAmount),
            if (tx.customerPhone != null) _buildDetailRow('Client', tx.customerPhone!),
            if (tx.reference != null) _buildDetailRow('Référence', tx.reference!),
            _buildDetailRow('Date', '${tx.createdAt.day}/${tx.createdAt.month}/${tx.createdAt.year}'),
            const SizedBox(height: 24),
            if (tx.status == TransactionStatus.completed)
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () {
                    Get.back();
                    controller.refundTransaction(tx);
                  },
                  child: const Text('Rembourser'),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(label, style: const TextStyle(color: AppColors.textSecondary)), Text(value, style: const TextStyle(fontWeight: FontWeight.w500))],
      ),
    );
  }
}
