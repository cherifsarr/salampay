import 'package:get/get.dart';
import '../models/merchant_model.dart';
import '../models/transaction_model.dart';
import '../services/api_service.dart';

class DashboardController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final merchant = Rxn<Merchant>();
  final stats = Rx<MerchantStats>(MerchantStats.empty());
  final recentTransactions = <Transaction>[].obs;
  final isLoading = false.obs;
  final currentIndex = 0.obs;
  final selectedPeriod = 'today'.obs;

  @override
  void onInit() {
    super.onInit();
    loadMerchant();
    loadDashboard();
  }

  Future<void> loadMerchant() async {
    final data = await _api.getMerchant();
    if (data != null) {
      merchant.value = Merchant.fromJson(data);
    }
  }

  Future<void> loadDashboard() async {
    isLoading.value = true;
    try {
      final response = await _api.getDashboard();
      if (response.success) {
        final data = response.data['data'] ?? response.data;

        if (data['merchant'] != null) {
          merchant.value = Merchant.fromJson(data['merchant']);
          await _api.saveMerchant(data['merchant']);
        }

        if (data['stats'] != null) {
          stats.value = MerchantStats.fromJson(data['stats']);
        }

        if (data['recent_transactions'] != null) {
          recentTransactions.value = (data['recent_transactions'] as List)
              .map((t) => Transaction.fromJson(t))
              .toList();
        }
      }
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadStats(String period) async {
    selectedPeriod.value = period;
    final response = await _api.getStats(period: period);
    if (response.success) {
      final data = response.data['data'] ?? response.data;
      stats.value = MerchantStats.fromJson(data);
    }
  }

  void changeTab(int index) {
    currentIndex.value = index;
  }

  Future<void> refresh() async {
    await loadDashboard();
  }

  String get formattedBalance => merchant.value?.formattedBalance ?? '0 XOF';
}
