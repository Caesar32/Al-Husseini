class StaffAttendanceSummary {
  final int totalEmployees;
  final int presentCount;
  final int lateCount;
  final int absentCount;
  final String attendanceRate;
  final List<StaffMemberAttendance> staffList;

  StaffAttendanceSummary({
    required this.totalEmployees,
    required this.presentCount,
    required this.lateCount,
    required this.absentCount,
    required this.attendanceRate,
    required this.staffList,
  });

  factory StaffAttendanceSummary.fromJson(Map<String, dynamic> json) {
    final summary = json['summary'] ?? {};
    final list = json['data'] as List? ?? [];

    return StaffAttendanceSummary(
      totalEmployees: summary['total_employees'] ?? 0,
      presentCount: summary['present_count'] ?? 0,
      lateCount: summary['late_count'] ?? 0,
      absentCount: summary['absent_count'] ?? 0,
      attendanceRate: summary['attendance_rate'] ?? '0%',
      staffList: list.map((e) => StaffMemberAttendance.fromJson(e)).toList(),
    );
  }
}

class StaffMemberAttendance {
  final int employeeId;
  final String employeeCode;
  final String name;
  final String role;
  final String? phone;
  final String status;
  final String statusLabel;
  final String? checkInTime;
  final String? checkOutTime;
  final int lateMinutes;

  StaffMemberAttendance({
    required this.employeeId,
    required this.employeeCode,
    required this.name,
    required this.role,
    this.phone,
    required this.status,
    required this.statusLabel,
    this.checkInTime,
    this.checkOutTime,
    required this.lateMinutes,
  });

  factory StaffMemberAttendance.fromJson(Map<String, dynamic> json) {
    return StaffMemberAttendance(
      employeeId: json['employee_id'] is int ? json['employee_id'] : int.parse(json['employee_id'].toString()),
      employeeCode: json['employee_code'] ?? '',
      name: json['name'] ?? '',
      role: json['role'] ?? 'فني / موظف',
      phone: json['phone'],
      status: json['status'] ?? 'absent',
      statusLabel: json['status_label'] ?? 'غائب',
      checkInTime: json['check_in_time'],
      checkOutTime: json['check_out_time'],
      lateMinutes: json['late_minutes'] ?? 0,
    );
  }
}
