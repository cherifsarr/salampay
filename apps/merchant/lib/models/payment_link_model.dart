class PaymentLink {
  final int id;
  final String uuid;
  final String code;
  final String title;
  final String? description;
  final double amount;
  final String currency;
  final bool isReusable;
  final int? maxUses;
  final int usedCount;
  final String status;
  final String shortUrl;
  final DateTime createdAt;
  final DateTime? expiresAt;

  PaymentLink({
    required this.id,
    required this.uuid,
    required this.code,
    required this.title,
    this.description,
    required this.amount,
    required this.currency,
    required this.isReusable,
    this.maxUses,
    required this.usedCount,
    required this.status,
    required this.shortUrl,
    required this.createdAt,
    this.expiresAt,
  });

  bool get isActive => status == 'active';
  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);
  bool get hasReachedLimit => maxUses != null && usedCount >= maxUses!;

  String get formattedAmount => '${amount.toStringAsFixed(0)} $currency';
  String get statusLabel {
    if (isExpired) return 'Expiré';
    if (hasReachedLimit) return 'Limite atteinte';
    return status == 'active' ? 'Actif' : 'Inactif';
  }

  factory PaymentLink.fromJson(Map<String, dynamic> json) {
    return PaymentLink(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      code: json['code'] ?? '',
      title: json['title'] ?? '',
      description: json['description'],
      amount: _parseDouble(json['amount']),
      currency: json['currency'] ?? 'XOF',
      isReusable: json['is_reusable'] ?? false,
      maxUses: json['max_uses'],
      usedCount: json['used_count'] ?? 0,
      status: json['status'] ?? 'active',
      shortUrl: json['short_url'] ?? '',
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : DateTime.now(),
      expiresAt: json['expires_at'] != null ? DateTime.parse(json['expires_at']) : null,
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
      'code': code,
      'title': title,
      'description': description,
      'amount': amount,
      'currency': currency,
      'is_reusable': isReusable,
      'max_uses': maxUses,
      'used_count': usedCount,
      'status': status,
      'short_url': shortUrl,
      'created_at': createdAt.toIso8601String(),
      'expires_at': expiresAt?.toIso8601String(),
    };
  }
}
