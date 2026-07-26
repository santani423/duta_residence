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

class SupervisorProfileInfo {
  const SupervisorProfileInfo({
    this.supervisorCode,
    this.whatsappNumber,
    this.employmentStatus,
    this.accountStatus,
  });

  final String? supervisorCode;
  final String? whatsappNumber;
  final String? employmentStatus;
  final String? accountStatus;

  factory SupervisorProfileInfo.fromJson(Map<String, dynamic> json) {
    return SupervisorProfileInfo(
      supervisorCode: json['supervisor_code']?.toString(),
      whatsappNumber: json['whatsapp_number']?.toString(),
      employmentStatus: json['employment_status']?.toString(),
      accountStatus: json['account_status']?.toString(),
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
    this.supervisorProfile,
  });

  final int id;
  final String name;
  final String username;
  final String? email;
  final String? phone;
  final String? residentId;
  final String? role;
  final CollectorProfileInfo? collectorProfile;
  final SupervisorProfileInfo? supervisorProfile;

  factory UserSession.fromJson(Map<String, dynamic> json) {
    final profileJson = json['collector_profile'];
    final supervisorProfileJson = json['supervisor_profile'];
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
      supervisorProfile: supervisorProfileJson is Map
          ? SupervisorProfileInfo.fromJson(
              Map<String, dynamic>.from(supervisorProfileJson),
            )
          : null,
    );
  }
}
