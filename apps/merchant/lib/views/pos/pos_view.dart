import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../controllers/pos_controller.dart';
import '../../theme/app_theme.dart';
import '../../routes/app_routes.dart';

class PosView extends GetView<PosController> {
  const PosView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Point de Vente'),
        actions: [
          IconButton(
            icon: const Icon(Icons.tv),
            tooltip: 'Affichage client',
            onPressed: () => _showClientDisplayOptions(context),
          ),
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: controller.newTransaction,
          ),
        ],
      ),
      body: Obx(() {
        // Show QR code if payment is pending
        if (controller.generatedQrCode.value.isNotEmpty) {
          return _buildPaymentScreen();
        }

        // Show success screen
        if (controller.paymentStatus.value == 'success') {
          return _buildSuccessScreen();
        }

        // Show keypad
        return _buildKeypadScreen();
      }),
    );
  }

  Widget _buildKeypadScreen() {
    return Column(
      children: [
        // Amount display
        Expanded(
          flex: 2,
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: const BoxDecoration(
              color: AppColors.primary,
              borderRadius: BorderRadius.only(
                bottomLeft: Radius.circular(32),
                bottomRight: Radius.circular(32),
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Text('Montant à encaisser', style: TextStyle(color: Colors.white70, fontSize: 16)),
                const SizedBox(height: 16),
                Obx(() => Text(
                      controller.formattedAmount,
                      style: const TextStyle(color: Colors.white, fontSize: 48, fontWeight: FontWeight.bold),
                    )),
                const SizedBox(height: 16),
                // Description field
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  decoration: BoxDecoration(
                    color: Colors.white24,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: TextField(
                    controller: controller.descriptionController,
                    style: const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      hintText: 'Description (optionnel)',
                      hintStyle: TextStyle(color: Colors.white54),
                      border: InputBorder.none,
                      prefixIcon: Icon(Icons.note_outlined, color: Colors.white54),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),

        // Keypad
        Expanded(
          flex: 3,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                Expanded(child: _buildKeypadRow(['1', '2', '3'])),
                Expanded(child: _buildKeypadRow(['4', '5', '6'])),
                Expanded(child: _buildKeypadRow(['7', '8', '9'])),
                Expanded(child: _buildKeypadRow(['C', '0', '⌫'])),
                const SizedBox(height: 16),
                // Generate QR button
                SizedBox(
                  width: double.infinity,
                  height: 56,
                  child: Obx(() => ElevatedButton.icon(
                        onPressed: controller.isValidAmount && !controller.isLoading.value
                            ? controller.generatePaymentQr
                            : null,
                        icon: controller.isLoading.value
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(strokeWidth: 2, valueColor: AlwaysStoppedAnimation<Color>(Colors.white)),
                              )
                            : const Icon(Icons.qr_code),
                        label: Text(controller.isLoading.value ? 'Génération...' : 'Générer QR Code'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.accent,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                        ),
                      )),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildKeypadRow(List<String> keys) {
    return Row(
      children: keys.map((key) => Expanded(child: _buildKeypadButton(key))).toList(),
    );
  }

  Widget _buildKeypadButton(String key) {
    return Padding(
      padding: const EdgeInsets.all(4),
      child: Material(
        color: key == 'C' ? AppColors.error.withOpacity(0.1) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            if (key == 'C') {
              controller.clearAmount();
            } else if (key == '⌫') {
              controller.deleteDigit();
            } else {
              controller.appendDigit(key);
            }
          },
          child: Center(
            child: Text(
              key,
              style: TextStyle(
                fontSize: key == '⌫' ? 24 : 28,
                fontWeight: FontWeight.w600,
                color: key == 'C' ? AppColors.error : AppColors.textPrimary,
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildPaymentScreen() {
    return Column(
      children: [
        Expanded(
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 20)],
                  ),
                  child: Obx(() => QrImageView(
                        data: controller.generatedQrCode.value,
                        size: 250,
                        version: QrVersions.auto,
                      )),
                ),
                const SizedBox(height: 24),
                Obx(() => Text(
                      controller.formattedAmount,
                      style: const TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: AppColors.primary),
                    )),
                const SizedBox(height: 8),
                const Text('Demandez au client de scanner ce QR code', style: TextStyle(color: AppColors.textSecondary)),
                const SizedBox(height: 24),
                Obx(() => controller.paymentStatus.value == 'pending'
                    ? Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.accent),
                          ),
                          const SizedBox(width: 12),
                          const Text('En attente du paiement...', style: TextStyle(color: AppColors.textSecondary)),
                        ],
                      )
                    : const SizedBox.shrink()),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(24),
          child: SizedBox(
            width: double.infinity,
            child: OutlinedButton(
              onPressed: controller.cancelPayment,
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                side: const BorderSide(color: AppColors.error),
                foregroundColor: AppColors.error,
              ),
              child: const Text('Annuler'),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildSuccessScreen() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: AppColors.success.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.check, size: 64, color: AppColors.success),
          ),
          const SizedBox(height: 24),
          const Text('Paiement reçu!', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppColors.success)),
          const SizedBox(height: 8),
          Obx(() => Text(controller.formattedAmount, style: const TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: AppColors.textPrimary))),
          const SizedBox(height: 48),
          ElevatedButton.icon(
            onPressed: controller.newTransaction,
            icon: const Icon(Icons.add),
            label: const Text('Nouvelle vente'),
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 16),
            ),
          ),
        ],
      ),
    );
  }

  void _showClientDisplayOptions(BuildContext context) {
    Get.bottomSheet(
      Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Affichage Client',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
            ),
            const SizedBox(height: 8),
            const Text(
              'Affichez le QR code sur un écran client ou un autre appareil',
              style: TextStyle(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 24),
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.tablet_android, color: AppColors.primary),
              ),
              title: const Text('Ouvrir sur cet appareil'),
              subtitle: const Text('Mode plein écran pour écran client'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () {
                Get.back();
                Get.toNamed(AppRoutes.posClientDisplay);
              },
            ),
            const Divider(),
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.accent.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.qr_code, color: AppColors.accent),
              ),
              title: const Text('Afficher le code de connexion'),
              subtitle: const Text('Scanner depuis l\'app client SalamPay'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () {
                Get.back();
                _showConnectionCode(context);
              },
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  void _showConnectionCode(BuildContext context) {
    // Generate a unique session code for this POS terminal
    const sessionCode = 'POS-ABC123'; // This would be dynamically generated

    Get.dialog(
      AlertDialog(
        title: const Text('Code de connexion'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Scannez ce code depuis l\'application SalamPay pour afficher l\'écran client',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.divider),
              ),
              child: QrImageView(
                data: 'salampay://pos/$sessionCode',
                size: 180,
                version: QrVersions.auto,
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              decoration: BoxDecoration(
                color: AppColors.background,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                sessionCode,
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, letterSpacing: 2),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Get.back(), child: const Text('Fermer')),
        ],
      ),
    );
  }
}
