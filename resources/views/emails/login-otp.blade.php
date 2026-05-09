<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Login OTP – Model Boss</title>
</head>

<body style="margin:0;padding:0;background-color:#0a0a14;font-family:'Segoe UI',Arial,sans-serif;">

    <!-- Outer wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0"
        style="background-color:#0a0a14;min-height:100vh;">
        <tr>
            <td align="center" style="padding:40px 16px;">

                <!-- Card -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="max-width:560px;width:100%;background-color:#12121f;border-radius:16px;overflow:hidden;border:1px solid rgba(233,30,140,0.18);">

                    <!-- Top accent bar -->
                    <tr>
                        <td style="height:4px;background:linear-gradient(90deg,#e91e8c 0%,#9c27b0 50%,#00bcd4 100%);">
                        </td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding:36px 40px 24px;">
                            <!-- Logo placeholder – swap src for a hosted logo URL -->
                            <div
                                style="display:inline-block;padding:10px 24px;background:linear-gradient(135deg,#e91e8c,#9c27b0);border-radius:50px;">
                                <span
                                    style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:2px;text-transform:uppercase;">
                                    MODEL BOSS
                                </span>
                            </div>

                            <p
                                style="margin:20px 0 0;font-size:13px;color:#9b59b6;letter-spacing:3px;text-transform:uppercase;">
                                Where Supporters Become Legends
                            </p>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding:0 40px;">
                            <div
                                style="height:1px;background:linear-gradient(90deg,transparent,rgba(233,30,140,0.4),transparent);">
                            </div>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:32px 40px 0;">
                            <h1 style="margin:0 0 8px;font-size:24px;font-weight:700;color:#ffffff;line-height:1.3;">
                                Login Verification
                            </h1>
                            <p style="margin:0 0 24px;font-size:15px;color:#7f8c9a;line-height:1.7;">
                                Hello! Someone (hopefully you) just attempted to sign in to your
                                <strong style="color:#e91e8c;">Model Boss</strong> account.
                                Use the OTP below to complete your login.
                            </p>
                        </td>
                    </tr>

                    <!-- OTP Box -->
                    <tr>
                        <td align="center" style="padding:0 40px 28px;">
                            <table cellpadding="0" cellspacing="0" border="0"
                                style="width:100%;background:linear-gradient(135deg,rgba(233,30,140,0.08),rgba(156,39,176,0.08));border:1px solid rgba(233,30,140,0.3);border-radius:12px;">
                                <tr>
                                    <td align="center" style="padding:28px 20px;">
                                        <p
                                            style="margin:0 0 8px;font-size:11px;font-weight:600;color:#9b59b6;letter-spacing:3px;text-transform:uppercase;">
                                            Your One-Time Password
                                        </p>
                                        <p
                                            style="margin:0;font-size:48px;font-weight:800;color:#ffffff;letter-spacing:14px;
                                                  background:linear-gradient(90deg,#e91e8c,#00bcd4);
                                                  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
                                                  background-clip:text;">
                                            {{ $otp }}
                                        </p>
                                        <p style="margin:12px 0 0;font-size:12px;color:#7f8c9a;">
                                            &#x23F1;&nbsp; Expires in <strong style="color:#e91e8c;">10 minutes</strong>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Info row -->
                    <tr>
                        <td style="padding:0 40px 32px;">
                            <!-- Warning box -->
                            <table cellpadding="0" cellspacing="0" border="0"
                                style="width:100%;background:rgba(0,188,212,0.06);border-left:3px solid #00bcd4;border-radius:0 8px 8px 0;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <p style="margin:0;font-size:13px;color:#7f8c9a;line-height:1.6;">
                                            &#x26A0;&nbsp;
                                            If you did not attempt to log in, please ignore this email.
                                            Your account remains secure.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding:0 40px;">
                            <div
                                style="height:1px;background:linear-gradient(90deg,transparent,rgba(233,30,140,0.25),transparent);">
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding:24px 40px 32px;">
                            <p style="margin:0 0 6px;font-size:12px;color:#4a4a6a;">
                                &copy; {{ date('Y') }} Model Boss. All rights reserved.
                            </p>
                            <p style="margin:0;font-size:11px;color:#3a3a5a;">
                                This is an automated message — please do not reply.
                            </p>
                        </td>
                    </tr>

                    <!-- Bottom accent bar -->
                    <tr>
                        <td style="height:4px;background:linear-gradient(90deg,#00bcd4 0%,#9c27b0 50%,#e91e8c 100%);">
                        </td>
                    </tr>

                </table>
                <!-- /Card -->

            </td>
        </tr>
    </table>

</body>

</html>
