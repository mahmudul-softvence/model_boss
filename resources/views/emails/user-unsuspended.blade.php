@extends('emails.layout')

@section('title', 'Account Restored – Model Boss')
@section('heading', 'Account Restored')
@section('subheading', 'Great news — your account suspension has been lifted.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;text-align:center;">
                Hello, <strong style="color:#111827;">{{ $name }}</strong>!<br />
                You now have full access to your Model Boss account again.
            </p>

            <!-- info card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #bbf7d0;border-radius:16px;margin-bottom:24px;">
                <tr>
                    <td style="padding:20px 24px;text-align:center;">
                        <p style="margin:0;font-size:13px;color:#10b981;font-weight:600;">
                            ✓ &nbsp;Account fully restored
                        </p>
                        <p style="margin:8px 0 0;font-size:12px;color:#6b7280;">
                            All features and access have been re-enabled.
                        </p>
                    </td>
                </tr>
            </table>

            <!-- CTA button -->
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin-bottom:8px;">
                <tr>
                    <td style="background:linear-gradient(135deg,#e91e8c,#9c27b0);border-radius:12px;">
                        <a href="{{ config('app.frontend_url') }}/login"
                           style="display:inline-block;padding:14px 36px;
                                  background:linear-gradient(135deg,#e91e8c,#9c27b0);
                                  border-radius:12px;font-size:15px;font-weight:700;
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
