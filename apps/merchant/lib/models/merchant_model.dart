class Merchant {
  final int id;
  final String uuid;
  final String businessName;
  final String? businessType;
  final String? registrationNumber;
  final String phone;
  final String? email;
  final String? address;
  final String? logoUrl;
  final String kybStatus;
  final String status;
  final double walletBalance;
  final DateTime createdAt;

  Merchant({
    required this.id,
    required this.uuid,
    required this.businessName,
    this.businessType,
    this.registrationNumber,
    required this.phone,
    this.email,
    this.address,
    this.logoUrl,
    required this.kybStatus,
    required this.status,
    required this.walletBalance,
    required this.createdAt,
  });

  bool get isVerified => kybStatus == 'approved';
  bool get isActive => status == 'active';

  String get initials {
    final words = businessName.split(' ');
    if (words.length >= 2) {
      return '${words[0][0]}${words[1][0]}'.toUpperCase();
    }
    return businessName.substring(0, 2).toUpperCase();
  }

  String get formattedBalance => '${walletBalance.toStringAsFixed(0)} XOF';

  factory Merchant.fromJson(Map<String, dynamic> json) {
    return Merchant(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      businessName: json['business_name'] ?? '',
      businessType: json['business_type'],
      registrationNumber: json['registration_number'],
      phone: json['phone'] ?? '',
      email: json['email'],
      address: json['address'],
      logoUrl: json['logo_url'],
      kybStatus: json['kyb_status'] ?? 'pending',
      status: json['status'] ?? 'active',
      walletBalance: _parseDouble(json['wallet_balance']),
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : DateTime.now(),
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'business_name': businessName,
      'business_type': businessType,
      'registration_number': registrationNumber,
      'phone': phone,
      'email': email,
      'address': address,
      'logo_url': logoUrl,
      'kyb_status': kybStatus,
      'status': status,
      'wallet_balance': walletBalance,
      'created_at': createdAt.toIso8601String(),
    };
  }
}

class MerchantStats {
  final double todaySales;
  final int todayTransactions;
  final double weekSales;
  final int weekTransactions;
  final double monthSales;
  final int monthTransactions;
  final double pendingSettlement;

  MerchantStats({
    required this.todaySales,
    required this.todayTransactions,
    required this.weekSales,
    required this.weekTransactions,
    required this.monthSales,
    required this.monthTransactions,
    required this.pendingSettlement,
  });

  factory MerchantStats.fromJson(Map<String, dynamic> json) {
    return MerchantStats(
      todaySales: _parseDouble(json['today_sales']),
      todayTransactions: json['today_transactions'] ?? 0,
      weekSales: _parseDouble(json['week_sales']),
      weekTransactions: json['week_transactions'] ?? 0,
      monthSales: _parseDouble(json['month_sales']),
      monthTransactions: json['month_transactions'] ?? 0,
      pendingSettlement: _parseDouble(json['pending_settlement']),
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  factory MerchantStats.empty() {
    return MerchantStats(
      todaySales: 0,
      todayTransactions: 0,
      weekSales: 0,
      weekTransactions: 0,
      monthSales: 0,
      monthTransactions: 0,
      pendingSettlement: 0,
    );
  }
}
