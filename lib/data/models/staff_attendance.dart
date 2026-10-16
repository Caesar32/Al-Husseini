import 'json_read.dart';

class StaffAttendanceSummary {
  final int totalEmployees;
  final int presentCount;
  final int lateCount;
  final int absentCount;
  final String attendanceRate;
  final List<StaffMemberAttendance> staffList;

  const StaffAttendanceSummary({
    required this.totalEmployees,
    required this.presentCount,
    required this.lateCount,
    required this.absentCount,
    required this.attendanceRate,
    required this.staffList,
  });

  factory StaffAttendanceSummary.fromJson(Map<String, dynamic> json) {
    final summary = readMap(json['summary']);

    return StaffAttendanceSummary(
      totalEmployees: readInt(summary['total_employees']),
      presentCount: readInt(summary['present_count']),
      lateCount: readInt(summary['late_count']),
      absentCount: readInt(summary['absent_count']),
      attendanceRate: readString(
        summary['attendance_rate'],
        fallback: '0%',
      ),
      staffList: readModelList(
        json['data'],
        StaffMemberAttendance.fromJson,
      ),
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

  const StaffMemberAttendance({
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
      employeeId: readInt(json['employee_id']),
      employeeCode: readString(json['employee_code']),
      name: readString(json['name']),
      role: readString(json['role'], fallback: 'فني / موظف'),
      phone: readStringOrNull(json['phone']),
      status: readString(json['status'], fallback: 'absent'),
      statusLabel: readString(json['status_label'], fallback: 'غائب'),
      checkInTime: readStringOrNull(json['check_in_time']),
      checkOutTime: readStringOrNull(json['check_out_time']),
      lateMinutes: readInt(json['late_minutes']),
    );
  }
}
