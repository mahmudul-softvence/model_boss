<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Login OTP – Model Boss</title>
</head>
<body style="margin:0;padding:0;background-color:#07070f;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#07070f;">
<tr><td align="center" style="padding:40px 16px;">

    <!-- Card -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0"
           style="max-width:560px;width:100%;">

        <!-- ─── HEADER BAND ─── -->
        <tr>
            <td style="background:linear-gradient(135deg,#1c0530 0%,#160828 50%,#0d1a2e 100%);
                        border-radius:18px 18px 0 0;padding:44px 48px 40px;text-align:center;
                        border:1px solid rgba(233,30,140,0.15);border-bottom:none;">

                <!-- Logo -->
                <img src="{{ asset('assets/logo/brand-logo.png') }}"
                     alt="Model Boss"
                     width="160"
                     style="display:block;margin:0 auto;max-width:160px;height:auto;border:0;" />

                <!-- Main title -->
                <h1 style="margin:16px 0 0;font-size:30px;font-weight:800;color:#ffffff;
                            letter-spacing:-0.5px;line-height:1.2;">
                    Verify Your Login
                </h1>

            </td>
        </tr>

        <!-- ─── OTP BLOCK ─── -->
        <tr>
            <td style="background:#0d0d1c;padding:0 48px;
                        border-left:1px solid rgba(233,30,140,0.15);
                        border-right:1px solid rgba(233,30,140,0.15);">

                <!-- glowing code card -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="margin:32px 0;">
                    <tr>
                        <td align="center"
                            style="background:#12112a;
                                   border:1px solid rgba(233,30,140,0.35);
                                   border-radius:14px;
                                   padding:32px 24px;">

                            <p style="margin:0 0 14px;font-size:10px;font-weight:700;
                                       color:#6b4fa0;letter-spacing:5px;text-transform:uppercase;">
                                One-Time Password
                            </p>

                            <!-- OTP code -->
                            <p style="margin:0;font-size:52px;font-weight:900;
                                       color:#ffffff;letter-spacing:16px;
                                       font-variant-numeric:tabular-nums;">
                                {{ $otp }}
                            </p>

                            <!-- bottom pill -->
                            <table cellpadding="0" cellspacing="0" border="0" align="center"
                                   style="margin-top:18px;">
                                <tr>
                                    <td style="background:rgba(233,30,140,0.1);
                                               border:1px solid rgba(233,30,140,0.25);
                                               border-radius:40px;padding:6px 18px;">
                                        <span style="font-size:11px;color:#c06090;">
                                            ⏱&ensp;Valid for <strong style="color:#e91e8c;">10 minutes</strong>
                                        </span>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
                </table>

                <!-- helper text -->
                <p style="margin:0 0 32px;font-size:13px;color:#505570;
                           line-height:1.7;text-align:center;">
                    This code is single-use and will expire automatically.<br />
                    <strong style="color:#7058a0;">Never share it with anyone.</strong>
                </p>

            </td>
        </tr>

        <!-- ─── FOOTER ─── -->
        <tr>
            <td style="background:#09091a;border-radius:0 0 18px 18px;
                        padding:22px 48px 26px;text-align:center;
                        border:1px solid rgba(233,30,140,0.1);border-top:none;">

                <!-- thin top line -->
                <div style="height:1px;background:linear-gradient(90deg,transparent,rgba(156,39,176,0.3),transparent);margin-bottom:18px;"></div>

                <p style="margin:0;font-size:12px;color:#2e2e48;line-height:1.8;">
                    &copy; {{ date('Y') }} Model Boss &nbsp;·&nbsp; All rights reserved
                </p>

            </td>
        </tr>

    </table>
    <!-- /Card -->

</td></tr>
</table>

</body>
</html>
