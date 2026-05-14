@extends('emails.layout')

@section('title', 'Reset Your Password – Model Boss')
@section('heading', 'Reset Your Password')
@section('heading_bg', 'linear-gradient(135deg,#9c27b0,#6a1b9a)')
@section('heading_icon', '🔑')
@section('subheading', 'Use the OTP below to reset your Model Boss password.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center"
            style="background:#f7f0ff;border:1px solid #f0b8d4;
                   border-radius:16px;padding:32px 24px;">

            <p style="margin:0 0 16px;font-size:10px;font-weight:700;
                       color:#9ca3af;letter-spacing:6px;text-transform:uppercase;">
                One-Time Password
            </p>

            <p style="margin:0;font-size:54px;font-weight:900;
                      color:#111827;letter-spacing:14px;font-variant-numeric:tabular-nums;">
                {{ $otp }}
            </p>

            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top:18px;">
                <tr>
                    <td style="background:#fce4ec;border:1px solid #f8bbd0;
                               border-radius:40px;padding:7px 20px;">
                        <span style="font-size:12px;color:#c06090;">
                            🕐&ensp;Valid for <strong style="color:#e91e8c;">10 minutes</strong>
                        </span>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

<p style="margin:0 0 8px;font-size:13px;color:#b0a8c0;line-height:1.7;text-align:center;">
    This code is single-use and will expire automatically.<br />
    <strong style="color:#7c3aed;font-size:14px;">Never share it with anyone.</strong>
</p>
@endsection
