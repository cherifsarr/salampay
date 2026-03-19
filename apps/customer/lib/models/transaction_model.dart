enum TransactionType {
  deposit,
  withdrawal,
  transfer,
  payment,
  refund,
  fee,
}

enum TransactionStatus {
  pending,
  processing,
  completed,
  failed,
  cancelled,
  refunded,
}

class Transaction {
  final int id;
  final String uuid;
  final TransactionType type;
  final TransactionStatus status;
  final double amount;
  final double? fee;
  final String currency;
  final String? reference;
  final String? description;
  final String? providerReference;
  final String? provider;
  final TransactionParty? sender;
  final TransactionParty? recipient;
  final DateTime createdAt;
  final DateTime? completedAt;

  Transaction({
    required this.id,
    required this.uuid,
    required this.type,
    required this.status,
    required this.amount,
    this.fee,
    required this.currency,
    this.reference,
    this.description,
    this.providerReference,
    this.provider,
    this.sender,
    this.recipient,
    required this.createdAt,
    this.completedAt,
  });

  bool get isCredit => type == TransactionType.deposit ||
                       (type == TransactionType.transfer && recipient != null);
  bool get isDebit => type == TransactionType.withdrawal ||
                      type == TransactionType.payment ||
                      (type == TransactionType.transfer && sender != null);
  bool get isPending => status == TransactionStatus.pending ||
                        status == TransactionStatus.processing;
  bool get isCompleted => status == TransactionStatus.completed;
  bool get isFailed => status == TransactionStatus.failed ||
                       status == TransactionStatus.cancelled;

  String get formattedAmount {
    final sign = isCredit ? '+' : '-';
    return '$sign${amount.toStringAsFixed(0)} $currency';
  }

  String get typeLabel {
    switch (type) {
      case TransactionType.deposit:
        return 'Dépôt';
      case TransactionType.withdrawal:
        return 'Retrait';
      case TransactionType.transfer:
        return 'Transfert';
      case TransactionType.payment:
        return 'Paiement';
      case TransactionType.refund:
        return 'Remboursement';
      case TransactionType.fee:
        return 'Frais';
    }
  }

  String get statusLabel {
    switch (status) {
      case TransactionStatus.pending:
        return 'En attente';
      case TransactionStatus.processing:
        return 'En cours';
      case TransactionStatus.completed:
        return 'Terminé';
      case TransactionStatus.failed:
        return 'Échoué';
      case TransactionStatus.cancelled:
        return 'Annulé';
      case TransactionStatus.refunded:
        return 'Remboursé';
    }
  }

  factory Transaction.fromJson(Map<String, dynamic> json) {
    return Transaction(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      type: _parseType(json['type']),
      status: _parseStatus(json['status']),
      amount: _parseDouble(json['amount']),
      fee: json['fee'] != null ? _parseDouble(json['fee']) : null,
      currency: json['currency'] ?? 'XOF',
      reference: json['reference'],
      description: json['description'],
      providerReference: json['provider_reference'],
      provider: json['provider'],
      sender: json['sender'] != null
          ? TransactionParty.fromJson(json['sender'])
          : null,
      recipient: json['recipient'] != null
          ? TransactionParty.fromJson(json['recipient'])
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'])
          : null,
    );
  }

  static TransactionType _parseType(String? type) {
    switch (type?.toLowerCase()) {
      case 'deposit':
        return TransactionType.deposit;
      case 'withdrawal':
        return TransactionType.withdrawal;
      case 'transfer':
        return TransactionType.transfer;
      case 'payment':
        return TransactionType.payment;
      case 'refund':
        return TransactionType.refund;
      case 'fee':
        return TransactionType.fee;
      default:
        return TransactionType.payment;
    }
  }

  static TransactionStatus _parseStatus(String? status) {
    switch (status?.toLowerCase()) {
      case 'pending':
        return TransactionStatus.pending;
      case 'processing':
        return TransactionStatus.processing;
      case 'completed':
        return TransactionStatus.completed;
      case 'failed':
        return TransactionStatus.failed;
      case 'cancelled':
        return TransactionStatus.cancelled;
      case 'refunded':
        return TransactionStatus.refunded;
      default:
        return TransactionStatus.pending;
    }
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
      'type': type.name,
      'status': status.name,
      'amount': amount,
      'fee': fee,
      'currency': currency,
      'reference': reference,
      'description': description,
      'provider_reference': providerReference,
      'provider': provider,
      'sender': sender?.toJson(),
      'recipient': recipient?.toJson(),
      'created_at': createdAt.toIso8601String(),
      'completed_at': completedAt?.toIso8601String(),
    };
  }
}

class TransactionParty {
  final int? id;
  final String? name;
  final String? phone;
  final String? type;

  TransactionParty({
    this.id,
    this.name,
    this.phone,
    this.type,
  });

  factory TransactionParty.fromJson(Map<String, dynamic> json) {
    return TransactionParty(
      id: json['id'],
      name: json['name'],
      phone: json['phone'],
      type: json['type'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'phone': phone,
      'type': type,
    };
  }
}
