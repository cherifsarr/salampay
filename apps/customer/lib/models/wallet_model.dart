class Wallet {
  final int id;
  final String uuid;
  final String walletType;
  final String currency;
  final double balance;
  final double availableBalance;
  final double pendingBalance;
  final double? dailyLimit;
  final double? monthlyLimit;
  final String status;
  final DateTime createdAt;

  Wallet({
    required this.id,
    required this.uuid,
    required this.walletType,
    required this.currency,
    required this.balance,
    required this.availableBalance,
    required this.pendingBalance,
    this.dailyLimit,
    this.monthlyLimit,
    required this.status,
    required this.createdAt,
  });

  bool get isActive => status == 'active';
  bool get hasPendingBalance => pendingBalance > 0;

  String get formattedBalance {
    return '${balance.toStringAsFixed(0)} $currency';
  }

  String get formattedAvailableBalance {
    return '${availableBalance.toStringAsFixed(0)} $currency';
  }

  factory Wallet.fromJson(Map<String, dynamic> json) {
    return Wallet(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      walletType: json['wallet_type'] ?? 'main',
      currency: json['currency'] ?? 'XOF',
      balance: _parseDouble(json['balance']),
      availableBalance: _parseDouble(json['available_balance']),
      pendingBalance: _parseDouble(json['pending_balance']),
      dailyLimit: json['daily_limit'] != null
          ? _parseDouble(json['daily_limit'])
          : null,
      monthlyLimit: json['monthly_limit'] != null
          ? _parseDouble(json['monthly_limit'])
          : null,
      status: json['status'] ?? 'active',
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
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
      'wallet_type': walletType,
      'currency': currency,
      'balance': balance,
      'available_balance': availableBalance,
      'pending_balance': pendingBalance,
      'daily_limit': dailyLimit,
      'monthly_limit': monthlyLimit,
      'status': status,
      'created_at': createdAt.toIso8601String(),
    };
  }
}
