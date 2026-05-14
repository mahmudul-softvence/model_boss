<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>@yield('title', 'Model Boss')</title>
    <style>
        @media only screen and (max-width: 600px) {
            .card-pad { padding: 32px 20px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f5f0ff;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f0ff;">
<tr><td align="center" style="padding:40px 16px;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;width:100%;">

        <!-- MAIN CARD -->
        <tr>
            <td class="card-pad"
                style="background:#ffffff;border-radius:24px;border:1px solid #e0d4f8;
                       padding:44px 48px 36px;text-align:center;">

                <!-- Logo -->
                <img src="{{ asset('assets/logo/brand-logo.png') }}"
                     alt="Model Boss" width="170"
                     style="display:block;margin:0 auto 28px;max-width:170px;height:auto;border:0;" />

                <!-- Heading pill -->
                @hasSection('heading')
                <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin-bottom:28px;">
                    <tr>
                        <td style="background:@yield('heading_bg', 'linear-gradient(135deg,#3cb043,#27a63e)');
                                   border-radius:50px;padding:11px 28px 11px 14px;">
                            <table cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;padding-right:10px;">
                                        <div style="width:30px;height:30px;background:rgba(255,255,255,0.25);
                                                    border-radius:50%;text-align:center;line-height:30px;
                                                    font-size:16px;color:#ffffff;font-weight:900;">
                                            @yield('heading_icon', '✓')
                                        </div>
                                    </td>
                                    <td style="vertical-align:middle;">
                                        <span style="font-size:17px;font-weight:700;color:#ffffff;
                                                     letter-spacing:0.3px;white-space:nowrap;">
                                            @yield('heading')
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                @endif

                <!-- Subheading -->
                @hasSection('subheading')
                <p style="margin:-12px 0 24px;font-size:13px;color:#7880a0;line-height:1.6;text-align:center;">
                    @yield('subheading')
                </p>
                @endif

                <!-- BODY -->
                @yield('body')

            </td>
        </tr>

        <!-- FOOTER -->
        <tr>
            <td style="padding:20px 0 8px;text-align:center;">
                <p style="margin:0;font-size:12px;color:#b0a8c8;line-height:1.8;">
                    &copy; {{ date('Y') }} Model Boss &nbsp;&middot;&nbsp; All rights reserved
                </p>
            </td>
        </tr>

    </table>

</td></tr>
</table>

</body>
</html>
