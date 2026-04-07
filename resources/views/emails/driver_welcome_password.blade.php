<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Account Password</title>
</head>
<body style="margin:0;background:#f4f6fb;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(31,41,55,0.12);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);padding:20px 24px;text-align:center;">
                            <img src="{{ asset('img/logo.png') }}" alt="H&amp;R Ice Cream" style="max-width:170px;width:100%;height:auto;display:block;margin:0 auto 12px;">
                            <p style="margin:0;color:#cbd5e1;font-size:13px;letter-spacing:0.6px;text-transform:uppercase;">Driver Account Access</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 24px;">
                            <h2 style="margin:0 0 10px;font-size:24px;line-height:1.3;color:#111827;">Welcome, {{ $driver->name }}!</h2>
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#4b5563;">
                                Your driver profile was successfully created in the H&amp;R Ice Cream system.
                            </p>
                            <p style="margin:0 0 10px;font-size:14px;color:#374151;">Your temporary password:</p>
                            <div style="background:#eef2ff;border:1px dashed #6366f1;border-radius:10px;padding:14px 16px;margin:0 0 16px;">
                                <p style="margin:0;font-size:24px;font-weight:700;letter-spacing:1.4px;color:#312e81;text-align:center;">
                                    {{ $temporaryPassword }}
                                </p>
                            </div>
                            <p style="margin:0 0 12px;font-size:14px;line-height:1.7;color:#4b5563;">
                                Please keep this password secure and update it after your first login.
                            </p>
                            <p style="margin:0;font-size:13px;color:#9ca3af;">
                                If you were not expecting this account, please contact the administrator.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
