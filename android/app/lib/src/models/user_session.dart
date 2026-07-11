class UserSession {
  const UserSession({
    required this.id,
    required this.name,
    required this.username,
    this.email,
    this.phone,
    this.residentId,
    this.role,
  });

  final int id;
  final String name;
  final String username;
  final String? email;
  final String? phone;
  final String? residentId;
  final String? role;

  factory UserSession.fromJson(Map<String, dynamic> json) {
    return UserSession(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: json['name']?.toString() ?? '-',
      username: json['username']?.toString() ?? '-',
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      residentId: json['resident_id']?.toString(),
      role: json['role']?.toString(),
    );
  }
}
