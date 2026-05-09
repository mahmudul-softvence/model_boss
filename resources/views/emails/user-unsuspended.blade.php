@extends('emails.layout')

@section('title', 'Account Restored – Model Boss')
@section('heading', 'Account Restored')
@section('subheading', 'Great news — your account suspension has been lifted.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:36px 0 28px;">
    <tr>
        <td align="center">

            <!-- success icon -->
            <div style="width:64px;height:64px;margin:0 auto 24px;
                        background:linear-gradient(145deg,#10b981,#059669);
                        border-radius:50%;text-align:center;line-height:64px;font-size:28px;">
                ✓
            </div>

            <p class="txt-muted" style="margin:0 0 24px;font-size:14px;color:#505570;line-height:1.7;text-align:center;">
                Hello, <strong class="txt-white" style="color:#ffffff;">{{ $name }}</strong>!<br />
                You now have full access to your Model Boss account again.
            </p>

            <!-- info card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   class="info-card"
                   style="background:#12112a;border:1px solid rgba(16,185,129,0.25);border-radius:14px;margin-bottom:28px;">
                <tr>
                    <td style="padding:20px 24px;text-align:center;">
                        <p style="margin:0;font-size:13px;color:#10b981;font-weight:600;">
                            ✓ &nbsp;Account fully restored
                        </p>
                        <p class="txt-muted" style="margin:8px 0 0;font-size:12px;color:#505570;">
                            All features and access have been re-enabled.
                        </p>
                    </td>
                </tr>
            </table>

            <!-- CTA button -->
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin-bottom:32px;">
                <tr>
                    <td style="background:linear-gradient(135deg,#e91e8c,#9c27b0);border-radius:10px;padding:1px;">
                        <a href="{{ config('app.frontend_url') }}/login"
                           style="display:inline-block;padding:14px 36px;
                                  background:linear-gradient(135deg,#e91e8c,#9c27b0);
                                  border-radius:10px;font-size:15px;font-weight:700;
                                  color:#ffffff;text-decoration:none;letter-spacing:0.5px;">
                            Login to Your Account
                        </a>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>
@endsection
