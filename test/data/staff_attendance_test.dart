import 'package:flutter_test/flutter_test.dart';

import 'package:alhusseini_app/data/models/staff_attendance.dart';

void main() {
  group('StaffAttendanceSummary.fromJson', () {
    test('reads the complete response body from the root', () {
      final attendance = StaffAttendanceSummary.fromJson({
        'status': 'success',
        'summary': {
          'total_employees': 3,
          'present_count': 2,
          'late_count': 1,
          'absent_count': 1,
          'attendance_rate': '67%',
        },
        'data': [
          {
            'employee_id': 1,
            'employee_code': 'EMP-001',
            'name': 'أحمد',
            'role': 'فني',
            'phone': '01000000000',
            'status': 'present',
            'status_label': 'حاضر',
            'check_in_time': '08:00 ص',
            'check_out_time': null,
            'late_minutes': 0,
          },
          {
            'employee_id': 2,
            'employee_code': 'EMP-002',
            'name': 'محمود',
            'role': 'موظف',
            'phone': null,
            'status': 'late',
            'status_label': 'متأخر',
            'check_in_time': '08:20 ص',
            'check_out_time': '04:00 م',
            'late_minutes': 20,
          },
        ],
      });

      expect(attendance.totalEmployees, 3);
      expect(attendance.presentCount, 2);
      expect(attendance.lateCount, 1);
      expect(attendance.absentCount, 1);
      expect(attendance.attendanceRate, '67%');
      expect(attendance.staffList, hasLength(2));
      expect(attendance.staffList[0].employeeId, 1);
      expect(attendance.staffList[0].employeeCode, 'EMP-001');
      expect(attendance.staffList[0].phone, '01000000000');
      expect(attendance.staffList[0].checkInTime, '08:00 ص');
      expect(attendance.staffList[1].checkOutTime, '04:00 م');
      expect(attendance.staffList[1].lateMinutes, 20);
    });

    test('uses defaults for missing and null values', () {
      final attendance = StaffAttendanceSummary.fromJson({
        'summary': null,
        'data': null,
      });

      expect(attendance.totalEmployees, 0);
      expect(attendance.presentCount, 0);
      expect(attendance.lateCount, 0);
      expect(attendance.absentCount, 0);
      expect(attendance.attendanceRate, '0%');
      expect(attendance.staffList, isEmpty);
    });

    test('accepts numeric strings and nullable server strings', () {
      final attendance = StaffAttendanceSummary.fromJson({
        'summary': {
          'total_employees': '1',
          'present_count': '1',
          'late_count': '0',
          'absent_count': '0',
        },
        'data': [
          {
            'employee_id': '8',
            'employee_code': 1008,
            'name': 'سعيد',
            'phone': 12345,
            'check_in_time': 8,
            'check_out_time': null,
            'late_minutes': '5',
          },
        ],
      });

      expect(attendance.totalEmployees, 1);
      expect(attendance.presentCount, 1);
      expect(attendance.staffList.single.employeeId, 8);
      expect(attendance.staffList.single.employeeCode, '1008');
      expect(attendance.staffList.single.phone, '12345');
      expect(attendance.staffList.single.checkInTime, '8');
      expect(attendance.staffList.single.checkOutTime, isNull);
      expect(attendance.staffList.single.lateMinutes, 5);
    });
  });
}
