@extends('emails.layout')

@section('title', 'Verify Your Email – Model Boss')
@section('heading', 'Verify Your Email')
@section('subheading', 'Click the button below to confirm your email address and activate your account.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center"
            style="background:#ede8f8;border:1px solid #d4bef0;
                   border-radius:16px;padding:32px 24px;">

            <div style="width:64px;height:64px;margin:0 auto 20px;
                        background:linear-gradient(145deg,#e91e8c,#9c27b0);
                        border-radius:50%;text-align:center;line-height:64px;font-size:28px;">
                ✉
            </div>

            <p style="margin:0 0 24px;font-size:14px;color:#4b5563;line-height:1.7;text-align:center;">
                Hello, <strong style="color:#111827;">{{ $name }}</strong>!<br />
                Thanks for signing up. Please verify your email to get started.
            </p>

            <!-- CTA button -->
            <table cellpadding="0" cellspacing="0" border="0" align="center">
                <tr>
                    <td style="background:linear-gradient(135deg,#e91e8c,#9c27b0);border-radius:12px;">
                        <a href="{{ $url }}"
                           style="display:inline-block;padding:14px 36px;
                                  background:linear-gradient(135deg,#e91e8c,#9c27b0);
                                  border-radius:12px;font-size:15px;font-weight:700;
                                  color:#ffffff;text-decoration:none;letter-spacing:0.5px;">
                            Verify Email Address
                        </a>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

<p style="margin:0 0 6px;font-size:12px;color:#9ca3af;text-align:center;">
    Or copy &amp; paste this link into your browser:<br />
    <a href="{{ $url }}" style="color:#9c27b0;word-break:break-all;font-size:11px;">
        {{ $url }}
    </a>
</p>

<p style="margin:10px 0 8px;font-size:12px;color:#9ca3af;text-align:center;">
    This link expires in <strong style="color:#e91e8c;">60 minutes</strong>.
</p>
@endsection
