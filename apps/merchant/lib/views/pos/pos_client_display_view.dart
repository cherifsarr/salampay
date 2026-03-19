import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../controllers/pos_controller.dart';
import '../../theme/app_theme.dart';

/// Client-facing display for POS
/// This view is designed to be shown on a secondary device or customer-facing screen
/// It displays the QR code and payment amount for customers to scan
class PosClientDisplayView extends GetView<PosController> {
  const PosClientDisplayView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Obx(() {
          // Waiting for transaction
          if (controller.generatedQrCode.value.isEmpty && controller.paymentStatus.value != 'success') {
            return _buildWaitingScreen();
          }

          // Show QR code for customer
          if (controller.generatedQrCode.value.isNotEmpty && controller.paymentStatus.value == 'pending') {
            return _buildQrCodeScreen();
          }

          // Payment success
          if (controller.paymentStatus.value == 'success') {
            return _buildSuccessScreen();
          }

          return _buildWaitingScreen();
        }),
      ),
    );
  }

  Widget _buildWaitingScreen() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(32),
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.point_of_sale, size: 80, color: AppColors.primary),
          ),
          const SizedBox(height: 32),
          const Text(
            'SalamPay POS',
            style: TextStyle(fontSize: 32, fontWeight: FontWeight.bold, color: AppColors.primary),
          ),
          const SizedBox(height: 16),
          const Text(
            'En attente de transaction...',
            style: TextStyle(fontSize: 18, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 48),
          const CircularProgressIndicator(color: AppColors.primary),
        ],
      ),
    );
  }

  Widget _buildQrCodeScreen() {
    return Column(
      children: [
        // Header with merchant name
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(24),
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.primary, AppColors.primaryDark],
            ),
          ),
          child: const Column(
            children: [
              Text('SalamPay', style: TextStyle(color: Colors.white70, fontSize: 16)),
              SizedBox(height: 4),
              Text('Scannez pour payer', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
            ],
          ),
        ),

        // QR Code and amount
        Expanded(
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.1),
                        blurRadius: 30,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: Obx(() => QrImageView(
                        data: controller.generatedQrCode.value,
                        size: 280,
                        version: QrVersions.auto,
                        eyeStyle: const QrEyeStyle(
                          eyeShape: QrEyeShape.square,
                          color: AppColors.primary,
                        ),
                        dataModuleStyle: const QrDataModuleStyle(
                          dataModuleShape: QrDataModuleShape.square,
                          color: AppColors.primary,
                        ),
                      )),
                ),
                const SizedBox(height: 32),
                Obx(() => Text(
                      controller.formattedAmount,
                      style: const TextStyle(fontSize: 56, fontWeight: FontWeight.bold, color: AppColors.primary),
                    )),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.accent),
                    ),
                    const SizedBox(width: 12),
                    const Text('En attente du paiement...', style: TextStyle(fontSize: 16, color: AppColors.textSecondary)),
                  ],
                ),
              ],
            ),
          ),
        ),

        // Footer
        Container(
          padding: const EdgeInsets.all(16),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.security, color: AppColors.accent, size: 20),
              const SizedBox(width: 8),
              const Text('Paiement sécurisé par SalamPay', style: TextStyle(color: AppColors.textSecondary)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildSuccessScreen() {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [AppColors.success, Color(0xFF059669)],
        ),
      ),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(24),
              decoration: const BoxDecoration(
                color: Colors.white24,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check, size: 100, color: Colors.white),
            ),
            const SizedBox(height: 32),
            const Text(
              'Paiement Réussi!',
              style: TextStyle(fontSize: 36, fontWeight: FontWeight.bold, color: Colors.white),
            ),
            const SizedBox(height: 16),
            Obx(() => Text(
                  controller.formattedAmount,
                  style: const TextStyle(fontSize: 48, fontWeight: FontWeight.bold, color: Colors.white),
                )),
            const SizedBox(height: 24),
            const Text(
              'Merci pour votre paiement',
              style: TextStyle(fontSize: 20, color: Colors.white70),
            ),
          ],
        ),
      ),
    );
  }
}
