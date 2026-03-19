enum QrCodeType { static, dynamic }

class QrCode {
  final int id;
  final String uuid;
  final String code;
  final QrCodeType type;
  final double? amount;
  final String? description;
  final String? storeId;
  final String? storeName;
  final bool isActive;
  final int scanCount;
  final DateTime createdAt;
  final DateTime? expiresAt;

  QrCode({
    required this.id,
    required this.uuid,
    required this.code,
    required this.type,
    this.amount,
    this.description,
    this.storeId,
    this.storeName,
    required this.isActive,
    required this.scanCount,
    required this.createdAt,
    this.expiresAt,
  });

  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);
  bool get hasFixedAmount => amount != null && amount! > 0;

  String get typeLabel => type == QrCodeType.static ? 'Statique' : 'Dynamique';
  String get formattedAmount => amount != null ? '${amount!.toStringAsFixed(0)} XOF' : 'Variable';

  String get qrData => 'salampay://pay/$code';

  factory QrCode.fromJson(Map<String, dynamic> json) {
    return QrCode(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      code: json['code'] ?? '',
      type: json['type'] == 'dynamic' ? QrCodeType.dynamic : QrCodeType.static,
      amount: json['amount'] != null ? _parseDouble(json['amount']) : null,
      description: json['description'],
      storeId: json['store_id']?.toString(),
      storeName: json['store_name'],
      isActive: json['is_active'] ?? true,
      scanCount: json['scan_count'] ?? 0,
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : DateTime.now(),
      expiresAt: json['expires_at'] != null ? DateTime.parse(json['expires_at']) : null,
    );
  }

  static double _parseDouble(dynamic value) {
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
      'type': type.name,
      'amount': amount,
      'description': description,
      'store_id': storeId,
      'store_name': storeName,
      'is_active': isActive,
      'scan_count': scanCount,
      'created_at': createdAt.toIso8601String(),
      'expires_at': expiresAt?.toIso8601String(),
    };
  }
}
