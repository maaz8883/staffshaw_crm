<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Login OTP</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f8;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f8;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                {{-- Header gradient bar --}}
                <tr>
                    <td style="background:linear-gradient(135deg,#2E86C1 0%,#6B3A7D 50%,#A01830 100%);padding:0;height:6px;"></td>
                </tr>

                {{-- Logo / Brand --}}
                <tr>
                    <td align="center" style="padding:36px 40px 24px;">
                        <div style="display:inline-block;background:linear-gradient(135deg,#2E86C1,#6B3A7D,#A01830);-webkit-background-clip:text;padding:9px;">
                            <span style="color: #fff; font-size:26px;font-weight:800;background:linear-gradient(135deg,#2E86C1,#6B3A7D,#A01830);-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-0.5px;">
                                CRM Portal
                            </span>
                        </div>
                        <p style="margin:6px 0 0;color:#9095b0;font-size:13px;">Secure Login Verification</p>
                    </td>
                </tr>

                {{-- Title --}}
                <tr>
                    <td align="center" style="padding:0 40px 20px;">
                        <h1 style="margin:0;font-size:22px;font-weight:700;color:#1a1a2e;">OTP Login Request</h1>
                        <p style="margin:8px 0 0;color:#6c757d;font-size:14px;line-height:1.6;">
                            A login request was made for the following account.
                        </p>
                    </td>
                </tr>

                {{-- User Info Card --}}
                <tr>
                    <td style="padding:0 40px 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0"
                            style="background:#f8f9fc;border-radius:12px;border:1px solid #e8eaf0;overflow:hidden;">
                            <tr>
                                <td style="padding:20px 24px;border-bottom:1px solid #e8eaf0;">
                                    <span style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9095b0;font-weight:600;">User Name</span>
                                    <p style="margin:4px 0 0;font-size:16px;font-weight:600;color:#1a1a2e;">{{ $userName }}</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px 24px;">
                                    <span style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#9095b0;font-weight:600;">User Email</span>
                                    <p style="margin:4px 0 0;font-size:16px;font-weight:600;color:#2E86C1;">{{ $userEmail }}</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- OTP Code --}}
                <tr>
                    <td align="center" style="padding:0 40px 28px;">
                        <p style="margin:0 0 12px;font-size:13px;color:#6c757d;font-weight:500;">Your One-Time Password</p>
                        <div style="display:inline-block;background:linear-gradient(135deg,#2E86C1 0%,#6B3A7D 50%,#A01830 100%);border-radius:14px;padding:3px;">
                            <div style="background:#ffffff;border-radius:12px;padding:18px 48px;">
                                <span style="padding: 6px; color: #fff;font-size:42px;font-weight:900;letter-spacing:10px;background:linear-gradient(135deg,#2E86C1,#6B3A7D,#A01830);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-family:'Courier New',monospace;">
                                    {{ $otpCode }}
                                </span>
                            </div>
                        </div>
                    </td>
                </tr>

                {{-- Expiry notice --}}
                <tr>
                    <td align="center" style="padding:0 40px 28px;">
                        <table cellpadding="0" cellspacing="0"
                            style="background:#fff8e6;border:1px solid #fad98a;border-radius:10px;padding:0;">
                            <tr>
                                <td style="padding:12px 20px;">
                                    <span style="font-size:13px;color:#7a4a00;">
                                        ⏱ &nbsp;This OTP expires in <strong>5 minutes</strong>.
                                        Do not share it with anyone.
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Security note --}}
                <tr>
                    <td style="padding:0 40px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0"
                            style="background:#fde8ec;border-radius:10px;border:1px solid #f5b8c4;">
                            <tr>
                                <td style="padding:14px 20px;">
                                    <span style="font-size:13px;color:#6b0f1e;line-height:1.6;">
                                        🔒 &nbsp;If you did not request this OTP, please ignore this email.
                                        Your account remains secure.
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="background:#f8f9fc;border-top:1px solid #e8eaf0;padding:20px 40px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#9095b0;line-height:1.6;">
                            This is an automated message from <strong>CRM Portal</strong>.<br>
                            Please do not reply to this email.
                        </p>
                    </td>
                </tr>

                {{-- Bottom gradient bar --}}
                <tr>
                    <td style="background:linear-gradient(135deg,#2E86C1 0%,#6B3A7D 50%,#A01830 100%);padding:0;height:4px;"></td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
