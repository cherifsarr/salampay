enum TransactionStatus { pending, completed, failed, refunded }

class Transaction {
  final int id;
  final String uuid;
  final String type;
  final TransactionStatus status;
  final double amount;
  final double fee;
  final double netAmount;
  final String currency;
  final String? reference;
  final String? customerName;
  final String? customerPhone;
  final String? paymentMethod;
  final String? qrCodeId;
  final String? paymentLinkId;
  final DateTime createdAt;

  Transaction({
    required this.id,
    required this.uuid,
    required this.type,
    required this.status,
    required this.amount,
    required this.fee,
    required this.netAmount,
    required this.currency,
    this.reference,
    this.customerName,
    this.customerPhone,
    this.paymentMethod,
    this.qrCodeId,
    this.paymentLinkId,
    required this.createdAt,
  });

  String get formattedAmount => '${amount.toStringAsFixed(0)} $currency';
  String get formattedNetAmount => '${netAmount.toStringAsFixed(0)} $currency';
  String get formattedFee => '${fee.toStringAsFixed(0)} $currency';

  String get statusLabel {
    switch (status) {
      case TransactionStatus.pending: return 'En attente';
      case TransactionStatus.completed: return 'Complété';
      case TransactionStatus.failed: return 'Échoué';
      case TransactionStatus.refunded: return 'Remboursé';
    }
  }

  String get typeLabel {
    switch (type) {
      case 'payment': return 'Paiement';
      case 'qr_payment': return 'Paiement QR';
      case 'link_payment': return 'Lien de paiement';
      case 'invoice_payment': return 'Facture';
      default: return type;
    }
  }

  factory Transaction.fromJson(Map<String, dynamic> json) {
    return Transaction(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      type: json['type'] ?? 'payment',
      status: _parseStatus(json['status']),
      amount: _parseDouble(json['amount']),
      fee: _parseDouble(json['fee']),
      netAmount: _parseDouble(json['net_amount']),
      currency: json['currency'] ?? 'XOF',
      reference: json['reference'],
      customerName: json['customer_name'],
      customerPhone: json['customer_phone'],
      paymentMethod: json['payment_method'],
      qrCodeId: json['qr_code_id']?.toString(),
      paymentLinkId: json['payment_link_id']?.toString(),
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : DateTime.now(),
    );
  }

  static TransactionStatus _parseStatus(String? status) {
    switch (status?.toLowerCase()) {
      case 'completed': return TransactionStatus.completed;
      case 'failed': return TransactionStatus.failed;
      case 'refunded': return TransactionStatus.refunded;
      default: return TransactionStatus.pending;
    }
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class Settlement {
  final int id;
  final String uuid;
  final String batchNumber;
  final double amount;
  final double fee;
  final double netAmount;
  final String currency;
  final int transactionCount;
  final String status;
  final String? payoutMethod;
  final String? payoutAccount;
  final DateTime periodStart;
  final DateTime periodEnd;
  final DateTime createdAt;
  final DateTime? processedAt;

  Settlement({
    required this.id,
    required this.uuid,
    required this.batchNumber,
    required this.amount,
    required this.fee,
    required this.netAmount,
    required this.currency,
    required this.transactionCount,
    required this.status,
    this.payoutMethod,
    this.payoutAccount,
    required this.periodStart,
    required this.periodEnd,
    required this.createdAt,
    this.processedAt,
  });

  String get formattedAmount => '${amount.toStringAsFixed(0)} $currency';
  String get formattedNetAmount => '${netAmount.toStringAsFixed(0)} $currency';

  String get statusLabel {
    switch (status) {
      case 'pending': return 'En attente';
      case 'processing': return 'En cours';
      case 'completed': return 'Versé';
      case 'failed': return 'Échoué';
      default: return status;
    }
  }

  factory Settlement.fromJson(Map<String, dynamic> json) {
    return Settlement(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      batchNumber: json['batch_number'] ?? '',
      amount: _parseDouble(json['amount']),
      fee: _parseDouble(json['fee']),
      netAmount: _parseDouble(json['net_amount']),
      currency: json['currency'] ?? 'XOF',
      transactionCount: json['transaction_count'] ?? 0,
      status: json['status'] ?? 'pending',
      payoutMethod: json['payout_method'],
      payoutAccount: json['payout_account'],
      periodStart: json['period_start'] != null ? DateTime.parse(json['period_start']) : DateTime.now(),
      periodEnd: json['period_end'] != null ? DateTime.parse(json['period_end']) : DateTime.now(),
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : DateTime.now(),
      processedAt: json['processed_at'] != null ? DateTime.parse(json['processed_at']) : null,
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}
