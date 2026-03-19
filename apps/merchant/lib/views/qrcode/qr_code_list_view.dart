import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../controllers/qr_code_controller.dart';
import '../../theme/app_theme.dart';
import '../../routes/app_routes.dart';

class QrCodeListView extends GetView<QrCodeController> {
  const QrCodeListView({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Mes QR Codes')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => Get.toNamed(AppRoutes.qrCodeCreate),
        backgroundColor: AppColors.primary,
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.qrCodes.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.qrCodes.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.qr_code, size: 64, color: AppColors.textHint),
                const SizedBox(height: 16),
                const Text('Aucun QR Code', style: TextStyle(color: AppColors.textSecondary, fontSize: 16)),
                const SizedBox(height: 24),
                ElevatedButton.icon(onPressed: () => Get.toNamed(AppRoutes.qrCodeCreate), icon: const Icon(Icons.add), label: const Text('Créer un QR Code')),
              ],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () => controller.loadQrCodes(refresh: true),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: controller.qrCodes.length,
            itemBuilder: (context, index) => _buildQrCodeCard(controller.qrCodes[index]),
          ),
        );
      }),
    );
  }

  Widget _buildQrCodeCard(dynamic qrCode) {
    return GestureDetector(
      onTap: () => _showQrCodeDetail(qrCode),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
        child: Row(
          children: [
            QrImageView(data: qrCode.qrData, size: 60, version: QrVersions.auto),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(qrCode.typeLabel, style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text(qrCode.formattedAmount, style: const TextStyle(color: AppColors.accent, fontWeight: FontWeight.bold)),
                  Text('${qrCode.scanCount} scans', style: const TextStyle(fontSize: 12, color: AppColors.textSecondary)),
                ],
              ),
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline, color: AppColors.error),
              onPressed: () => controller.deleteQrCode(qrCode),
            ),
          ],
        ),
      ),
    );
  }

  void _showQrCodeDetail(dynamic qrCode) {
    Get.bottomSheet(
      Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            QrImageView(data: qrCode.qrData, size: 200, version: QrVersions.auto),
            const SizedBox(height: 16),
            Text(qrCode.typeLabel, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            Text(qrCode.formattedAmount, style: const TextStyle(fontSize: 24, color: AppColors.accent, fontWeight: FontWeight.bold)),
            if (qrCode.description != null) Text(qrCode.description!, style: const TextStyle(color: AppColors.textSecondary)),
            const SizedBox(height: 24),
            Row(
              children: [
                Expanded(child: OutlinedButton.icon(onPressed: () {}, icon: const Icon(Icons.share), label: const Text('Partager'))),
                const SizedBox(width: 12),
                Expanded(child: ElevatedButton.icon(onPressed: () {}, icon: const Icon(Icons.download), label: const Text('Télécharger'))),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
