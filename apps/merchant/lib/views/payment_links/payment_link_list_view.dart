import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../controllers/payment_link_controller.dart';
import '../../theme/app_theme.dart';
import '../../routes/app_routes.dart';

class PaymentLinkListView extends GetView<PaymentLinkController> {
  const PaymentLinkListView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Liens de paiement')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => Get.toNamed(AppRoutes.paymentLinkCreate),
        backgroundColor: AppColors.primary,
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.paymentLinks.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.paymentLinks.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.link, size: 64, color: AppColors.textHint),
                const SizedBox(height: 16),
                const Text('Aucun lien de paiement', style: TextStyle(color: AppColors.textSecondary, fontSize: 16)),
                const SizedBox(height: 24),
                ElevatedButton.icon(onPressed: () => Get.toNamed(AppRoutes.paymentLinkCreate), icon: const Icon(Icons.add), label: const Text('Créer un lien')),
              ],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () => controller.loadPaymentLinks(refresh: true),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: controller.paymentLinks.length,
            itemBuilder: (context, index) => _buildLinkCard(controller.paymentLinks[index]),
          ),
        );
      }),
    );
  }

  Widget _buildLinkCard(dynamic link) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(link.title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: link.isActive ? AppColors.success.withOpacity(0.1) : AppColors.error.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
                child: Text(link.statusLabel, style: TextStyle(fontSize: 12, color: link.isActive ? AppColors.success : AppColors.error)),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(link.formattedAmount, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.primary)),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(child: Text(link.shortUrl, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12), overflow: TextOverflow.ellipsis)),
              IconButton(
                icon: const Icon(Icons.copy, size: 18),
                onPressed: () {
                  Clipboard.setData(ClipboardData(text: link.shortUrl));
                  Get.snackbar('Copié', 'Lien copié dans le presse-papier', snackPosition: SnackPosition.BOTTOM);
                },
              ),
              IconButton(icon: const Icon(Icons.share, size: 18), onPressed: () {}),
            ],
          ),
          Text('${link.usedCount} utilisations', style: const TextStyle(fontSize: 12, color: AppColors.textHint)),
        ],
      ),
    );
  }
}
