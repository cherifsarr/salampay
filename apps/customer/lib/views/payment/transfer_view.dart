import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:pinput/pinput.dart';
import '../../controllers/payment_controller.dart';
import '../../theme/app_theme.dart';
import '../../utils/constants.dart';

class TransferView extends GetView<PaymentController> {
  const TransferView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Envoyer de l\'argent')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Destinataire', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(
              controller: controller.recipientController,
              keyboardType: TextInputType.phone,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(9)],
              decoration: InputDecoration(
                hintText: '77 123 45 67',
                prefixIcon: const Icon(Icons.person_outline),
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
              children: [500, 1000, 2000, 5000].map((amount) {
                return ActionChip(label: Text('$amount'), onPressed: () => controller.amountController.text = amount.toString());
              }).toList(),
            ),
            const SizedBox(height: 24),
            const Text('Motif (optionnel)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(
              controller: controller.descriptionController,
              maxLines: 2,
              decoration: const InputDecoration(hintText: 'Ex: Remboursement, cadeau...'),
            ),
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
                  onPressed: controller.isLoading.value ? null : controller.initiateTransfer,
                  child: controller.isLoading.value
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)))
                      : const Text('Envoyer'),
                )),
          ],
        ),
      ),
    );
  }
}
