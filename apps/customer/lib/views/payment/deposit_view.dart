import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../controllers/payment_controller.dart';
import '../../theme/app_theme.dart';

class DepositView extends GetView<PaymentController> {
  const DepositView({super.key});

  @override
  Widget build(BuildContext context) {
    controller.loadDepositMethods();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Dépôt')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Montant', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(
              controller: controller.amountController,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              decoration: const InputDecoration(
                hintText: '0',
                suffixText: 'XOF',
                suffixStyle: TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: AppColors.textSecondary),
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [1000, 2000, 5000, 10000].map((amount) {
                return ActionChip(
                  label: Text('$amount'),
                  onPressed: () => controller.amountController.text = amount.toString(),
                );
              }).toList(),
            ),
            const SizedBox(height: 24),
            const Text('Mode de paiement', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 12),
            Obx(() {
              if (controller.isLoading.value && controller.depositMethods.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }
              return Column(
                children: controller.depositMethods.map((method) {
                  return Obx(() => _buildPaymentMethodTile(
                        method: method,
                        isSelected: controller.selectedMethod.value?.id == method.id,
                        onTap: () => controller.selectedMethod.value = method,
                      ));
                }).toList(),
              );
            }),
            const SizedBox(height: 32),
            Obx(() => ElevatedButton(
                  onPressed: controller.isLoading.value ? null : controller.initiateDeposit,
                  child: controller.isLoading.value
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)))
                      : const Text('Continuer'),
                )),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentMethodTile({required PaymentMethod method, required bool isSelected, required VoidCallback onTap}) {
    return GestureDetector(
      onTap: method.available ? onTap : null,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: isSelected ? AppColors.primary : AppColors.divider, width: isSelected ? 2 : 1),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
              child: const Icon(Icons.account_balance_wallet, color: AppColors.primary),
            ),
            const SizedBox(width: 12),
            Expanded(child: Text(method.name, style: TextStyle(fontWeight: FontWeight.w600, color: method.available ? AppColors.textPrimary : AppColors.textHint))),
            if (isSelected) const Icon(Icons.check_circle, color: AppColors.primary),
            if (!method.available) const Text('Indisponible', style: TextStyle(fontSize: 12, color: AppColors.textHint)),
          ],
        ),
      ),
    );
  }
}
