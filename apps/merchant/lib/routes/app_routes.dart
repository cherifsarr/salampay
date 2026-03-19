import 'package:get/get.dart';
import '../views/auth/login_view.dart';
import '../views/dashboard/dashboard_view.dart';
import '../views/qrcode/qr_code_list_view.dart';
import '../views/qrcode/qr_code_create_view.dart';
import '../views/payment_links/payment_link_list_view.dart';
import '../views/payment_links/payment_link_create_view.dart';
import '../views/transactions/transaction_list_view.dart';
import '../views/settlements/settlement_list_view.dart';
import '../views/settings/settings_view.dart';
import '../controllers/auth_controller.dart';
import '../controllers/dashboard_controller.dart';
import '../controllers/qr_code_controller.dart';
import '../controllers/payment_link_controller.dart';
import '../controllers/transaction_controller.dart';
import '../controllers/settlement_controller.dart';

class AppRoutes {
  static const String login = '/login';
  static const String dashboard = '/dashboard';
  static const String qrCodes = '/qr-codes';
  static const String qrCodeCreate = '/qr-codes/create';
  static const String paymentLinks = '/payment-links';
  static const String paymentLinkCreate = '/payment-links/create';
  static const String transactions = '/transactions';
  static const String settlements = '/settlements';
  static const String settings = '/settings';

  static List<GetPage> pages = [
    GetPage(
      name: login,
      page: () => const LoginView(),
      binding: BindingsBuilder(() => Get.lazyPut(() => AuthController())),
    ),
    GetPage(
      name: dashboard,
      page: () => const DashboardView(),
      binding: BindingsBuilder(() {
        Get.lazyPut(() => DashboardController());
        Get.lazyPut(() => TransactionController());
      }),
    ),
    GetPage(
      name: qrCodes,
      page: () => const QrCodeListView(),
      binding: BindingsBuilder(() => Get.lazyPut(() => QrCodeController())),
    ),
    GetPage(
      name: qrCodeCreate,
      page: () => const QrCodeCreateView(),
      binding: BindingsBuilder(() => Get.lazyPut(() => QrCodeController())),
    ),
    GetPage(
      name: paymentLinks,
      page: () => const PaymentLinkListView(),
      binding: BindingsBuilder(() => Get.lazyPut(() => PaymentLinkController())),
    ),
    GetPage(
      name: paymentLinkCreate,
      page: () => const PaymentLinkCreateView(),
      binding: BindingsBuilder(() => Get.lazyPut(() => PaymentLinkController())),
    ),
    GetPage(
      name: transactions,
      page: () => const TransactionListView(),
      binding: BindingsBuilder(() => Get.lazyPut(() => TransactionController())),
    ),
    GetPage(
      name: settlements,
      page: () => const SettlementListView(),
      binding: BindingsBuilder(() => Get.lazyPut(() => SettlementController())),
    ),
    GetPage(name: settings, page: () => const SettingsView()),
  ];
}
