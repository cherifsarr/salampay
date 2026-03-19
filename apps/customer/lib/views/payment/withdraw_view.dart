import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:pinput/pinput.dart';
import '../../controllers/payment_controller.dart';
import '../../theme/app_theme.dart';
import '../../utils/constants.dart';

class WithdrawView extends GetView<PaymentController> {
  const WithdrawView({super.key});

  @override
  Widget build(BuildContext context) {
    controller.loadWithdrawMethods();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Retrait')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Numéro de téléphone', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(
              controller: controller.recipientController,
              keyboardType: TextInputType.phone,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(9)],
              decoration: InputDecoration(
                hintText: '77 123 45 67',
                prefixIcon: const Icon(Icons.phone_outlined),
                prefixText: '${AppConstants.countryCode} ',
              ),
            ),
            const SizedBox(height: 24),
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
              children: [5000, 10000, 20000, 50000].map((amount) {
                return ActionChip(label: Text('$amount'), onPressed: () => controller.amountController.text = amount.toString());
              }).toList(),
            ),
            const SizedBox(height: 24),
            const Text('Mode de retrait', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 12),
            Obx(() {
              if (controller.isLoading.value && controller.withdrawMethods.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }
              return Column(
                children: controller.withdrawMethods.map((method) {
                  return Obx(() => _buildPaymentMethodTile(
                        method: method,
                        isSelected: controller.selectedMethod.value?.id == method.id,
                        onTap: () => controller.selectedMethod.value = method,
                      ));
                }).toList(),
              );
            }),
            const SizedBox(height: 24),
            const Text('Code PIN', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            Center(
              child: Pinput(
                length: AppConstants.pinLength,
                controller: controller.pinController,
                obscureText: true,
                obscuringCharacter: '●',
                defaultPinTheme: PinTheme(
                  width: 56,
                  height: 56,
                  textStyle: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.divider)),
                ),
                focusedPinTheme: PinTheme(
                  width: 56,
                  height: 56,
                  textStyle: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.primary, width: 2)),
                ),
              ),
            ),
            const SizedBox(height: 32),
            Obx(() => ElevatedButton(
                  onPressed: controller.isLoading.value ? null : controller.initiateWithdrawal,
                  child: controller.isLoading.value
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)))
                      : const Text('Retirer'),
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
