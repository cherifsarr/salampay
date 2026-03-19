import 'package:get/get.dart';
import '../views/auth/login_view.dart';
import '../views/auth/register_view.dart';
import '../views/auth/otp_view.dart';
import '../views/auth/pin_setup_view.dart';
import '../views/home/home_view.dart';
import '../views/wallet/wallet_view.dart';
import '../views/payment/deposit_view.dart';
import '../views/payment/transfer_view.dart';
import '../views/payment/withdraw_view.dart';
import '../views/payment/qr_scan_view.dart';
import '../views/settings/settings_view.dart';
import '../controllers/auth_controller.dart';
import '../controllers/home_controller.dart';
import '../controllers/wallet_controller.dart';
import '../controllers/payment_controller.dart';

class AppRoutes {
  static const String splash = '/';
  static const String login = '/login';
  static const String register = '/register';
  static const String otp = '/otp';
  static const String pinSetup = '/pin-setup';
  static const String home = '/home';
  static const String wallet = '/wallet';
  static const String deposit = '/deposit';
  static const String transfer = '/transfer';
  static const String withdraw = '/withdraw';
  static const String qrScan = '/qr-scan';
  static const String settings = '/settings';

  static List<GetPage> pages = [
    GetPage(
      name: login,
      page: () => const LoginView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => AuthController());
      }),
    ),
    GetPage(
      name: register,
      page: () => const RegisterView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => AuthController());
      }),
    ),
    GetPage(
      name: otp,
      page: () => const OtpView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => AuthController());
      }),
    ),
    GetPage(
      name: pinSetup,
      page: () => const PinSetupView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => AuthController());
      }),
    ),
    GetPage(
      name: home,
      page: () => const HomeView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => HomeController());
        Get.lazyPut(() => WalletController());
      }),
    ),
    GetPage(
      name: wallet,
      page: () => const WalletView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => WalletController());
      }),
    ),
    GetPage(
      name: deposit,
      page: () => const DepositView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => PaymentController());
      }),
    ),
    GetPage(
      name: transfer,
      page: () => const TransferView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => PaymentController());
      }),
    ),
    GetPage(
      name: withdraw,
      page: () => const WithdrawView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => PaymentController());
      }),
    ),
    GetPage(
      name: qrScan,
      page: () => const QrScanView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => PaymentController());
      }),
    ),
    GetPage(
      name: settings,
      page: () => const SettingsView(),
    ),
  ];
}
