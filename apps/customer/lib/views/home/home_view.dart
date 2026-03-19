import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../controllers/home_controller.dart';
import '../../controllers/wallet_controller.dart';
import '../../theme/app_theme.dart';
import '../../routes/app_routes.dart';
import '../wallet/wallet_view.dart';
import '../settings/settings_view.dart';

class HomeView extends GetView<HomeController> {
  const HomeView({super.key});

  @override
  Widget build(BuildContext context) {
    final walletController = Get.find<WalletController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Obx(() => IndexedStack(
            index: controller.currentIndex.value,
            children: [
              _buildHomeTab(walletController),
              const WalletView(),
              const SettingsView(),
            ],
          )),
      bottomNavigationBar: Obx(() => BottomNavigationBar(
            currentIndex: controller.currentIndex.value,
            onTap: controller.changeTab,
            items: const [
              BottomNavigationBarItem(icon: Icon(Icons.home_outlined), activeIcon: Icon(Icons.home), label: 'Accueil'),
              BottomNavigationBarItem(icon: Icon(Icons.account_balance_wallet_outlined), activeIcon: Icon(Icons.account_balance_wallet), label: 'Portefeuille'),
              BottomNavigationBarItem(icon: Icon(Icons.settings_outlined), activeIcon: Icon(Icons.settings), label: 'Paramètres'),
            ],
          )),
    );
  }

  Widget _buildHomeTab(WalletController walletController) {
    return RefreshIndicator(
      onRefresh: walletController.refreshAll,
      child: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            backgroundColor: AppColors.primary,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppColors.primary, AppColors.primaryDark],
                  ),
                ),
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
                                    const Text('Bonjour,', style: TextStyle(color: Colors.white70, fontSize: 14)),
                                    Text(
                                      controller.currentUser.value?.displayName ?? 'Utilisateur',
                                      style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                                    ),
                                  ],
                                )),
                            CircleAvatar(
                              backgroundColor: Colors.white24,
                              child: Obx(() => Text(
                                    controller.currentUser.value?.initials ?? 'U',
                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                                  )),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),
                        const Text('Solde disponible', style: TextStyle(color: Colors.white70, fontSize: 14)),
                        const SizedBox(height: 4),
                        Obx(() => Text(
                              walletController.formattedBalance,
                              style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold),
                            )),
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
                      _buildQuickAction(Icons.add, 'Dépôt', AppColors.success, () => Get.toNamed(AppRoutes.deposit)),
                      _buildQuickAction(Icons.send, 'Envoyer', AppColors.primary, () => Get.toNamed(AppRoutes.transfer)),
                      _buildQuickAction(Icons.download, 'Retrait', AppColors.warning, () => Get.toNamed(AppRoutes.withdraw)),
                      _buildQuickAction(Icons.qr_code_scanner, 'Scanner', AppColors.secondary, () => Get.toNamed(AppRoutes.qrScan)),
                    ],
                  ),
                  const SizedBox(height: 24),
                  const Text('Transactions récentes', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary)),
                  const SizedBox(height: 12),
                  Obx(() {
                    if (walletController.isLoading.value) {
                      return const Center(child: CircularProgressIndicator());
                    }
                    if (walletController.transactions.isEmpty) {
                      return Container(
                        padding: const EdgeInsets.all(32),
                        child: const Column(
                          children: [
                            Icon(Icons.receipt_long_outlined, size: 64, color: AppColors.textHint),
                            SizedBox(height: 16),
                            Text('Aucune transaction', style: TextStyle(color: AppColors.textSecondary, fontSize: 16)),
                          ],
                        ),
                      );
                    }
                    return ListView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: walletController.transactions.take(5).length,
                      itemBuilder: (context, index) {
                        final tx = walletController.transactions[index];
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

  Widget _buildQuickAction(IconData icon, String label, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(16)),
            child: Icon(icon, color: color, size: 28),
          ),
          const SizedBox(height: 8),
          Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
        ],
      ),
    );
  }

  Widget _buildTransactionItem(dynamic tx) {
    final isCredit = tx.isCredit;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: (isCredit ? AppColors.success : AppColors.error).withOpacity(0.1),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(isCredit ? Icons.arrow_downward : Icons.arrow_upward, color: isCredit ? AppColors.success : AppColors.error, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tx.typeLabel, style: const TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary)),
                Text(tx.statusLabel, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
              ],
            ),
          ),
          Text(tx.formattedAmount, style: TextStyle(fontWeight: FontWeight.bold, color: isCredit ? AppColors.success : AppColors.error)),
        ],
      ),
    );
  }
}
