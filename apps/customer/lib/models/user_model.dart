class User {
  final int id;
  final String uuid;
  final String phone;
  final String? email;
  final String? firstName;
  final String? lastName;
  final String userType;
  final String kycLevel;
  final String status;
  final bool phoneVerifiedAt;
  final bool hasPinSet;
  final String? profilePhotoUrl;
  final DateTime createdAt;

  User({
    required this.id,
    required this.uuid,
    required this.phone,
    this.email,
    this.firstName,
    this.lastName,
    required this.userType,
    required this.kycLevel,
    required this.status,
    required this.phoneVerifiedAt,
    required this.hasPinSet,
    this.profilePhotoUrl,
    required this.createdAt,
  });

  String get fullName {
    if (firstName == null && lastName == null) return phone;
    return '${firstName ?? ''} ${lastName ?? ''}'.trim();
  }

  String get displayName {
    if (firstName != null) return firstName!;
    return phone;
  }

  String get initials {
    if (firstName != null && lastName != null) {
      return '${firstName![0]}${lastName![0]}'.toUpperCase();
    }
    if (firstName != null) return firstName![0].toUpperCase();
    return phone.substring(phone.length - 2);
  }

  bool get isVerified => kycLevel != 'none' && kycLevel != 'basic';
  bool get isActive => status == 'active';

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'],
      firstName: json['first_name'],
      lastName: json['last_name'],
      userType: json['user_type'] ?? 'customer',
      kycLevel: json['kyc_level'] ?? 'none',
      status: json['status'] ?? 'active',
      phoneVerifiedAt: json['phone_verified_at'] != null,
      hasPinSet: json['has_pin_set'] ?? false,
      profilePhotoUrl: json['profile_photo_url'],
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'uuid': uuid,
      'phone': phone,
      'email': email,
      'first_name': firstName,
      'last_name': lastName,
      'user_type': userType,
      'kyc_level': kycLevel,
      'status': status,
      'phone_verified_at': phoneVerifiedAt,
      'has_pin_set': hasPinSet,
      'profile_photo_url': profilePhotoUrl,
      'created_at': createdAt.toIso8601String(),
    };
  }
}
