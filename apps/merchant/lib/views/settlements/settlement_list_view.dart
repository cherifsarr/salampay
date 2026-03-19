import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../controllers/settlement_controller.dart';
import '../../theme/app_theme.dart';

class SettlementListView extends GetView<SettlementController> {
  const SettlementListView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Versements')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: controller.requestSettlement,
        backgroundColor: AppColors.primary,
        icon: const Icon(Icons.account_balance, color: Colors.white),
        label: const Text('Demander', style: TextStyle(color: Colors.white)),
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.settlements.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.settlements.isEmpty) {
          return const Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [Icon(Icons.account_balance_wallet_outlined, size: 64, color: AppColors.textHint), SizedBox(height: 16), Text('Aucun versement', style: TextStyle(color: AppColors.textSecondary))],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () => controller.loadSettlements(refresh: true),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: controller.settlements.length,
            itemBuilder: (context, index) => _buildSettlementCard(controller.settlements[index]),
          ),
        );
      }),
    );
  }

  Widget _buildSettlementCard(dynamic settlement) {
    Color statusColor;
    switch (settlement.status) {
      case 'completed':
        statusColor = AppColors.success;
        break;
      case 'failed':
        statusColor = AppColors.error;
        break;
      case 'processing':
        statusColor = AppColors.info;
        break;
      default:
        statusColor = AppColors.warning;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Batch #${settlement.batchNumber}', style: const TextStyle(fontWeight: FontWeight.w600)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
                child: Text(settlement.statusLabel, style: TextStyle(fontSize: 12, color: statusColor)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(settlement.formattedNetAmount, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppColors.primary)),
          const SizedBox(height: 8),
          Row(
            children: [
              Text('${settlement.transactionCount} transactions', style: const TextStyle(color: AppColors.textSecondary)),
              const SizedBox(width: 16),
              Text('Frais: ${settlement.fee.toStringAsFixed(0)} XOF', style: const TextStyle(color: AppColors.textSecondary)),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Période: ${_formatDate(settlement.periodStart)} - ${_formatDate(settlement.periodEnd)}',
            style: const TextStyle(fontSize: 12, color: AppColors.textHint),
          ),
        ],
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year}';
  }
}
