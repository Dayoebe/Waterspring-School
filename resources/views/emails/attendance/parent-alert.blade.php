<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student attendance update</title>
</head>
<body style="margin:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:620px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:28px;">
            <p style="margin:0 0 8px;color:#64748b;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;">Watersprings International School Akure</p>
            <h1 style="margin:0 0 20px;font-size:24px;">Attendance update</h1>
            <p style="margin:0 0 14px;line-height:1.6;">Dear {{ $parent->name }},</p>
            <p style="margin:0 0 18px;line-height:1.6;">
                {{ $attendanceRecord->studentRecord->user->name }} was marked
                <strong>{{ ucfirst($attendanceRecord->status) }}</strong> on
                {{ $attendanceRecord->attendanceSession->attendance_date->format('l, j F Y') }}.
            </p>
            <div style="background:#f8fafc;border-radius:12px;padding:16px;margin-bottom:18px;line-height:1.7;">
                <div><strong>Class:</strong> {{ $attendanceRecord->attendanceSession->myClass?->name ?? 'Not specified' }}</div>
                @if ($attendanceRecord->attendanceSession->section)
                    <div><strong>Section:</strong> {{ $attendanceRecord->attendanceSession->section->name }}</div>
                @endif
                @if ($attendanceRecord->remark)
                    <div><strong>Remark:</strong> {{ $attendanceRecord->remark }}</div>
                @endif
            </div>
            <p style="margin:0;line-height:1.6;color:#475569;">Please contact the school if you need clarification about this attendance record.</p>
        </div>
    </div>
</body>
</html>
