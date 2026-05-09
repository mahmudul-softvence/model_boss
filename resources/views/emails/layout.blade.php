<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="color-scheme" content="dark" />
    <meta name="supported-color-schemes" content="dark" />
    <title>@yield('title', 'Model Boss')</title>
    <style>
        :root { color-scheme: dark; }

        @media (prefers-color-scheme: light) {
            body,
            .body-bg   { background-color: #07070f !important; }
            .hero-bg   { background: linear-gradient(135deg,#1c0530 0%,#160828 50%,#0d1a2e 100%) !important; }
            .body-cell { background-color: #0d0d1c !important; }
            .foot-bg   { background-color: #09091a !important; }
            .txt-white { color: #ffffff !important; }
            .txt-muted { color: #505570 !important; }
            .txt-sub   { color: #7880a0 !important; }
            .txt-pink  { color: #e91e8c !important; }
            .txt-foot  { color: #2e2e48 !important; }
            .info-card { background-color: #12112a !important; border-color: rgba(233,30,140,0.35) !important; }
        }

        @media only screen and (max-width: 600px) {
            .card-pad  { padding: 28px 20px !important; }
            .body-pad  { padding: 0 20px !important; }
            .foot-pad  { padding: 20px !important; }
            .txt-h1    { font-size: 24px !important; }
        }
    </style>
</head>
<body class="body-bg" style="margin:0;padding:0;background-color:#07070f;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" class="body-bg" style="background-color:#07070f;">
<tr><td align="center" style="padding:40px 16px;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;">

        <!-- HEADER -->
        <tr>
            <td class="hero-bg card-pad"
                style="background:linear-gradient(135deg,#1c0530 0%,#160828 50%,#0d1a2e 100%);
                       border-radius:18px 18px 0 0;padding:44px 48px 40px;text-align:center;
                       border:1px solid rgba(233,30,140,0.15);border-bottom:none;">

                <img src="{{ asset('assets/logo/brand-logo.png') }}"
                     alt="Model Boss" width="160"
                     style="display:block;margin:0 auto;max-width:160px;height:auto;border:0;" />

                <h1 class="txt-white txt-h1"
                    style="margin:20px 0 0;font-size:28px;font-weight:800;color:#ffffff;
                           letter-spacing:-0.5px;line-height:1.2;">
                    @yield('heading')
                </h1>

                @hasSection('subheading')
                <p class="txt-sub" style="margin:10px 0 0;font-size:14px;color:#7880a0;line-height:1.6;">
                    @yield('subheading')
                </p>
                @endif

            </td>
        </tr>

        <!-- BODY -->
        <tr>
            <td class="body-cell body-pad"
                style="background:#0d0d1c;padding:0 48px;
                       border-left:1px solid rgba(233,30,140,0.15);
                       border-right:1px solid rgba(233,30,140,0.15);">
                @yield('body')
            </td>
        </tr>

        <!-- FOOTER -->
        <tr>
            <td class="foot-bg foot-pad"
                style="background:#09091a;border-radius:0 0 18px 18px;
                       padding:22px 48px 26px;text-align:center;
                       border:1px solid rgba(233,30,140,0.1);border-top:none;">

                <div style="height:1px;background:linear-gradient(90deg,transparent,rgba(156,39,176,0.3),transparent);margin-bottom:18px;"></div>

                <p class="txt-foot" style="margin:0;font-size:12px;color:#2e2e48;line-height:1.8;">
                    &copy; {{ date('Y') }} Model Boss &nbsp;·&nbsp; All rights reserved
                </p>
            </td>
        </tr>

    </table>

</td></tr>
</table>

</body>
</html>
