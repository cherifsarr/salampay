import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../controllers/dashboard_controller.dart';
import '../../controllers/transaction_controller.dart';
import '../../theme/app_theme.dart';
import '../../routes/app_routes.dart';
import '../transactions/transaction_list_view.dart';
import '../settings/settings_view.dart';

class DashboardView extends GetView<DashboardController> {
  const DashboardView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Obx(() => IndexedStack(
            index: controller.currentIndex.value,
            children: [_buildHomeTab(), const TransactionListView(), const SettingsView()],
          )),
      bottomNavigationBar: Obx(() => BottomNavigationBar(
            currentIndex: controller.currentIndex.value,
            onTap: controller.changeTab,
            items: const [
              BottomNavigationBarItem(icon: Icon(Icons.dashboard_outlined), activeIcon: Icon(Icons.dashboard), label: 'Tableau de bord'),
              BottomNavigationBarItem(icon: Icon(Icons.receipt_long_outlined), activeIcon: Icon(Icons.receipt_long), label: 'Transactions'),
              BottomNavigationBarItem(icon: Icon(Icons.settings_outlined), activeIcon: Icon(Icons.settings), label: 'Paramètres'),
            ],
          )),
    );
  }

  Widget _buildHomeTab() {
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 180,
            pinned: true,
            backgroundColor: AppColors.primary,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [AppColors.primary, AppColors.primaryDark])),
                child: SafeArea(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Obx(() => Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(controller.merchant.value?.businessName ?? 'Marchand', style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                                    if (controller.merchant.value?.isVerified == true)
                                      Row(children: [const Icon(Icons.verified, color: AppColors.accent, size: 16), const SizedBox(width: 4), const Text('Vérifié', style: TextStyle(color: AppColors.accent, fontSize: 12))]),
                                  ],
                                )),
                            Obx(() => CircleAvatar(
                                  backgroundColor: Colors.white24,
                                  child: Text(controller.merchant.value?.initials ?? 'M', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                                )),
                          ],
                        ),
                        const SizedBox(height: 20),
                        const Text('Solde disponible', style: TextStyle(color: Colors.white70, fontSize: 14)),
                        Obx(() => Text(controller.formattedBalance, style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold))),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Quick actions
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      _buildQuickAction(Icons.point_of_sale, 'POS', () => Get.toNamed(AppRoutes.pos)),
                      _buildQuickAction(Icons.qr_code, 'QR Code', () => Get.toNamed(AppRoutes.qrCodes)),
                      _buildQuickAction(Icons.link, 'Liens', () => Get.toNamed(AppRoutes.paymentLinks)),
                      _buildQuickAction(Icons.account_balance_wallet, 'Versement', () => Get.toNamed(AppRoutes.settlements)),
                    ],
                  ),
                  const SizedBox(height: 24),
                  // Stats cards
                  const Text('Statistiques', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                  const SizedBox(height: 12),
                  Obx(() => Row(
                        children: [
                          Expanded(child: _buildStatCard('Aujourd\'hui', '${controller.stats.value.todaySales.toStringAsFixed(0)} XOF', '${controller.stats.value.todayTransactions} tx', AppColors.accent)),
                          const SizedBox(width: 12),
                          Expanded(child: _buildStatCard('Ce mois', '${controller.stats.value.monthSales.toStringAsFixed(0)} XOF', '${controller.stats.value.monthTransactions} tx', AppColors.primary)),
                        ],
                      )),
                  const SizedBox(height: 24),
                  // Recent transactions
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Transactions récentes', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                      TextButton(onPressed: () => controller.changeTab(1), child: const Text('Voir tout')),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Obx(() {
                    if (controller.isLoading.value && controller.recentTransactions.isEmpty) {
                      return const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()));
                    }
                    if (controller.recentTransactions.isEmpty) {
                      return Container(
                        padding: const EdgeInsets.all(32),
                        child: const Column(children: [Icon(Icons.receipt_long_outlined, size: 48, color: AppColors.textHint), SizedBox(height: 8), Text('Aucune transaction', style: TextStyle(color: AppColors.textSecondary))]),
                      );
                    }
                    return ListView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: controller.recentTransactions.take(5).length,
                      itemBuilder: (context, index) {
                        final tx = controller.recentTransactions[index];
                        return _buildTransactionItem(tx);
                      },
                    );
                  }),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickAction(IconData icon, String label, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(16)),
            child: Icon(icon, color: AppColors.primary, size: 28),
          ),
          const SizedBox(height: 8),
          Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
        ],
      ),
    );
  }

  Widget _buildStatCard(String title, String value, String subtitle, Color color) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
          const SizedBox(height: 8),
          Text(value, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
          Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textHint)),
        ],
      ),
    );
  }

  Widget _buildTransactionItem(dynamic tx) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: AppColors.accent.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
            child: const Icon(Icons.payment, color: AppColors.accent, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tx.typeLabel, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
                Text(tx.customerPhone ?? tx.statusLabel, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
              ],
            ),
          ),
          Text(tx.formattedAmount, style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.accent)),
        ],
      ),
    );
  }
}
