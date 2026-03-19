import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../controllers/auth_controller.dart';
import '../../controllers/home_controller.dart';
import '../../theme/app_theme.dart';

class SettingsView extends StatelessWidget {
  const SettingsView({super.key});

  @override
  Widget build(BuildContext context) {
    final homeController = Get.find<HomeController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Paramètres')),
      body: SingleChildScrollView(
        child: Column(
          children: [
            const SizedBox(height: 16),
            // Profile card
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16),
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: AppColors.primary,
                    child: Obx(() => Text(
                          homeController.currentUser.value?.initials ?? 'U',
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                        )),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Obx(() => Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              homeController.currentUser.value?.fullName ?? 'Utilisateur',
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                            ),
                            Text(
                              homeController.currentUser.value?.phone ?? '',
                              style: const TextStyle(color: AppColors.textSecondary),
                            ),
                          ],
                        )),
                  ),
                  IconButton(icon: const Icon(Icons.edit_outlined, color: AppColors.primary), onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 24),
            // Settings sections
            _buildSection('Compte', [
              _buildSettingsTile(Icons.person_outline, 'Profil', () {}),
              _buildSettingsTile(Icons.verified_user_outlined, 'Vérification KYC', () {}),
              _buildSettingsTile(Icons.lock_outline, 'Changer le code PIN', () {}),
              _buildSettingsTile(Icons.key_outlined, 'Changer le mot de passe', () {}),
            ]),
            _buildSection('Sécurité', [
              _buildSettingsTile(Icons.fingerprint, 'Authentification biométrique', () {}, trailing: Switch(value: false, onChanged: (v) {})),
              _buildSettingsTile(Icons.notifications_outlined, 'Notifications', () {}),
            ]),
            _buildSection('Support', [
              _buildSettingsTile(Icons.help_outline, 'Aide & FAQ', () {}),
              _buildSettingsTile(Icons.headset_mic_outlined, 'Contacter le support', () {}),
              _buildSettingsTile(Icons.privacy_tip_outlined, 'Politique de confidentialité', () {}),
              _buildSettingsTile(Icons.description_outlined, 'Conditions d\'utilisation', () {}),
            ]),
            _buildSection('', [
              _buildSettingsTile(Icons.logout, 'Déconnexion', () => _showLogoutDialog(context), textColor: AppColors.error),
            ]),
            const SizedBox(height: 24),
            const Text('SalamPay v1.0.0', style: TextStyle(color: AppColors.textHint, fontSize: 12)),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _buildSection(String title, List<Widget> children) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (title.isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textSecondary)),
          ),
        Container(
          margin: const EdgeInsets.symmetric(horizontal: 16),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
          child: Column(children: children),
        ),
        const SizedBox(height: 16),
      ],
    );
  }

  Widget _buildSettingsTile(IconData icon, String title, VoidCallback onTap, {Widget? trailing, Color? textColor}) {
    return ListTile(
      leading: Icon(icon, color: textColor ?? AppColors.textPrimary),
      title: Text(title, style: TextStyle(color: textColor ?? AppColors.textPrimary)),
      trailing: trailing ?? const Icon(Icons.chevron_right, color: AppColors.textHint),
      onTap: onTap,
    );
  }

  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Déconnexion'),
        content: const Text('Êtes-vous sûr de vouloir vous déconnecter ?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Annuler')),
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              Get.find<AuthController>().logout();
            },
            child: const Text('Déconnexion', style: TextStyle(color: AppColors.error)),
          ),
        ],
      ),
    );
  }
}
