import 'package:get/get.dart';
import '../models/wallet_model.dart';
import '../models/transaction_model.dart';
import '../services/api_service.dart';

class WalletController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final wallets = <Wallet>[].obs;
  final mainWallet = Rxn<Wallet>();
  final transactions = <Transaction>[].obs;
  final isLoading = false.obs;
  final isLoadingMore = false.obs;
  final currentPage = 1.obs;
  final hasMore = true.obs;

  @override
  void onInit() {
    super.onInit();
    loadWallets();
  }

  Future<void> loadWallets() async {
    isLoading.value = true;
    try {
      final response = await _api.getWallets();
      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          wallets.value = data.map((w) => Wallet.fromJson(w)).toList();
          mainWallet.value = wallets.firstWhereOrNull((w) => w.walletType == 'main');
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadTransactions({bool refresh = false}) async {
    if (mainWallet.value == null) return;

    if (refresh) {
      currentPage.value = 1;
      hasMore.value = true;
      transactions.clear();
    }

    if (!hasMore.value) return;

    isLoadingMore.value = true;
    try {
      final response = await _api.getWalletTransactions(
        mainWallet.value!.uuid,
        page: currentPage.value,
      );

      if (response.success) {
        final data = response.data['data'] ?? response.data;
        if (data is List) {
          final newTransactions = data.map((t) => Transaction.fromJson(t)).toList();
          transactions.addAll(newTransactions);
          hasMore.value = newTransactions.isNotEmpty;
          currentPage.value++;
        }
      }
    } finally {
      isLoadingMore.value = false;
    }
  }

  Future<void> refreshAll() async {
    await loadWallets();
    await loadTransactions(refresh: true);
  }

  String get formattedBalance {
    if (mainWallet.value == null) return '0 XOF';
    return mainWallet.value!.formattedBalance;
  }

  double get balance {
    return mainWallet.value?.balance ?? 0;
  }
}
