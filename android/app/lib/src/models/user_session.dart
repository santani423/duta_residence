class CollectorProfileInfo {
  const CollectorProfileInfo({
    this.collectorCode,
    this.whatsappNumber,
    this.employmentStatus,
    this.accountStatus,
    this.photoPath,
  });

  final String? collectorCode;
  final String? whatsappNumber;
  final String? employmentStatus;
  final String? accountStatus;
  final String? photoPath;

  factory CollectorProfileInfo.fromJson(Map<String, dynamic> json) {
    return CollectorProfileInfo(
      collectorCode: json['collector_code']?.toString(),
      whatsappNumber: json['whatsapp_number']?.toString(),
      employmentStatus: json['employment_status']?.toString(),
      accountStatus: json['account_status']?.toString(),
      photoPath: json['photo_path']?.toString(),
    );
  }
}

class UserSession {
  const UserSession({
    required this.id,
    required this.name,
    required this.username,
    this.email,
    this.phone,
    this.residentId,
    this.role,
    this.collectorProfile,
  });

  final int id;
  final String name;
  final String username;
  final String? email;
  final String? phone;
  final String? residentId;
  final String? role;
  final CollectorProfileInfo? collectorProfile;

  factory UserSession.fromJson(Map<String, dynamic> json) {
    final profileJson = json['collector_profile'];
    return UserSession(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? '-',
      username: json['username']?.toString() ?? '-',
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      residentId: json['resident_id']?.toString(),
      role: json['role']?.toString(),
      collectorProfile: profileJson is Map
          ? CollectorProfileInfo.fromJson(Map<String, dynamic>.from(profileJson))
          : null,
    );
  }
}
