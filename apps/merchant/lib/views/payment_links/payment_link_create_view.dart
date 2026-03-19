import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../controllers/payment_link_controller.dart';
import '../../theme/app_theme.dart';

class PaymentLinkCreateView extends GetView<PaymentLinkController> {
  const PaymentLinkCreateView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Nouveau lien de paiement')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('Titre', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(controller: controller.titleController, decoration: const InputDecoration(hintText: 'Ex: Paiement commande')),
            const SizedBox(height: 16),
            const Text('Montant', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(
              controller: controller.amountController,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(hintText: '5000', suffixText: 'XOF'),
            ),
            const SizedBox(height: 16),
            const Text('Description (optionnel)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
            const SizedBox(height: 8),
            TextFormField(controller: controller.descriptionController, maxLines: 2, decoration: const InputDecoration(hintText: 'Description du paiement')),
            const SizedBox(height: 24),
            Obx(() => SwitchListTile(
                  title: const Text('Lien réutilisable'),
                  subtitle: const Text('Peut être utilisé plusieurs fois'),
                  value: controller.isReusable.value,
                  onChanged: (v) => controller.isReusable.value = v,
                  contentPadding: EdgeInsets.zero,
                )),
            Obx(() => controller.isReusable.value
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(height: 16),
                      const Text('Nombre max d\'utilisations (optionnel)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
                      const SizedBox(height: 8),
                      TextFormField(
                        controller: controller.maxUsesController,
                        keyboardType: TextInputType.number,
                        inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                        decoration: const InputDecoration(hintText: 'Illimité si vide'),
                      ),
                    ],
                  )
                : const SizedBox.shrink()),
            const SizedBox(height: 32),
            Obx(() => ElevatedButton(
                  onPressed: controller.isLoading.value ? null : controller.createPaymentLink,
                  child: controller.isLoading.value
                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)))
                      : const Text('Créer le lien'),
                )),
          ],
        ),
      ),
    );
  }
}
