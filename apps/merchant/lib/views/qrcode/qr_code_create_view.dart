import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../controllers/qr_code_controller.dart';
import '../../theme/app_theme.dart';

class QrCodeCreateView extends GetView<QrCodeController> {
  const QrCodeCreateView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Nouveau QR Code')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Type de QR Code', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            Obx(() => Row(
                  children: [
                    Expanded(child: _buildTypeOption('static', 'Statique', 'Montant fixe', controller.selectedType.value == 'static')),
                    const SizedBox(width: 12),
                    Expanded(child: _buildTypeOption('dynamic', 'Dynamique', 'Montant variable', controller.selectedType.value == 'dynamic')),
                  ],
                )),
            const SizedBox(height: 24),
            Obx(() => controller.selectedType.value == 'static'
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Montant', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
                      const SizedBox(height: 8),
                      TextFormField(
                        controller: controller.amountController,
                        keyboardType: TextInputType.number,
                        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                        decoration: const InputDecoration(hintText: 'Ex: 5000', suffixText: 'XOF'),
                      ),
                    ],
                  )
                : const SizedBox.shrink()),
            const SizedBox(height: 16),
            const Text('Description (optionnel)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(controller: controller.descriptionController, maxLines: 2, decoration: const InputDecoration(hintText: 'Ex: Paiement repas midi')),
            const SizedBox(height: 32),
            Obx(() => ElevatedButton(
                  onPressed: controller.isLoading.value ? null : controller.createQrCode,
                  child: controller.isLoading.value
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)))
                      : const Text('Créer le QR Code'),
                )),
          ],
        ),
      ),
    );
  }

  Widget _buildTypeOption(String value, String title, String subtitle, bool isSelected) {
    return GestureDetector(
      onTap: () => controller.selectedType.value = value,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: isSelected ? AppColors.primary : AppColors.divider, width: isSelected ? 2 : 1),
        ),
        child: Column(
          children: [
            Icon(value == 'static' ? Icons.qr_code : Icons.qr_code_scanner, color: isSelected ? AppColors.primary : AppColors.textSecondary, size: 32),
            const SizedBox(height: 8),
            Text(title, style: TextStyle(fontWeight: FontWeight.w600, color: isSelected ? AppColors.primary : AppColors.textPrimary)),
            Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
          ],
        ),
      ),
    );
  }
}
