import 'package:get/get.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';

class HomeController extends GetxController {
  final ApiService _api = Get.find<ApiService>();

  final currentUser = Rxn<User>();
  final isLoading = false.obs;
  final currentIndex = 0.obs;

  @override
  void onInit() {
    super.onInit();
    loadUser();
  }

  Future<void> loadUser() async {
    final userData = await _api.getUser();
    if (userData != null) {
      currentUser.value = User.fromJson(userData);
    }
  }

  void changeTab(int index) {
    currentIndex.value = index;
  }

  Future<void> refreshProfile() async {
    isLoading.value = true;
    try {
      final response = await _api.getProfile();
      if (response.success) {
        final userData = response.data['data'] ?? response.data;
        await _api.saveUser(userData);
        currentUser.value = User.fromJson(userData);
      }
    } finally {
      isLoading.value = false;
    }
  }
}
