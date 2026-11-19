<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="display:none;max-height:0;overflow:hidden;">A birthday message from {{ $senderName }}.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;background:#fff;border-radius:20px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr><td style="background:#082f49;padding:28px;text-align:center;">
                    <img src="{{ $logoUrl }}" width="64" height="64" alt="{{ $schoolName }}" style="display:inline-block;border-radius:50%;background:#fff;object-fit:contain;padding:4px;">
                    <p style="margin:12px 0 0;color:#fde68a;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">A Water Spring celebration</p>
                    <h1 style="margin:8px 0 0;color:#fff;font-size:30px;line-height:1.2;">Happy Birthday, {{ $celebrantName }}!</h1>
                </td></tr>
                <tr><td style="padding:32px 30px;">
                    <p style="margin:0 0 18px;font-size:17px;line-height:1.7;">Dear {{ $celebrantName }},</p>
                    <p style="margin:0 0 20px;font-size:18px;font-weight:700;color:#0369a1;">Today, we celebrate you!</p>
                    <div style="white-space:pre-line;font-size:16px;line-height:1.75;color:#334155;">{{ $personalMessage }}</div>
                    <div style="margin-top:28px;padding-top:22px;border-top:1px solid #e2e8f0;">
                        <p style="margin:0;color:#64748b;font-size:14px;">With warm wishes,</p>
                        <p style="margin:5px 0 0;font-weight:700;color:#0f172a;">{{ $senderName }}</p>
                    </div>
                </td></tr>
                <tr><td style="background:#f8fafc;padding:18px 28px;text-align:center;color:#64748b;font-size:12px;line-height:1.6;">
                    Sent through {{ $schoolName }}. Your contact details were not shown to the sender.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
