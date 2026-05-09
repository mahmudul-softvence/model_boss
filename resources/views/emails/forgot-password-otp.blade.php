@extends('emails.layout')

@section('title', 'Reset Your Password – Model Boss')
@section('heading', 'Reset Your Password')
@section('subheading', 'Use the OTP below to reset your Model Boss password.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:32px 0;">
    <tr>
        <td align="center"
            class="info-card"
            style="background:#12112a;border:1px solid rgba(233,30,140,0.35);
                   border-radius:14px;padding:32px 24px;">

            <p class="txt-muted" style="margin:0 0 14px;font-size:10px;font-weight:700;
                       color:#6b4fa0;letter-spacing:5px;text-transform:uppercase;">
                One-Time Password
            </p>

            <p class="txt-white" style="margin:0;font-size:52px;font-weight:900;
                       color:#ffffff;letter-spacing:16px;font-variant-numeric:tabular-nums;">
                {{ $otp }}
            </p>

            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top:18px;">
                <tr>
                    <td style="background:rgba(233,30,140,0.1);border:1px solid rgba(233,30,140,0.25);
                               border-radius:40px;padding:6px 18px;">
                        <span style="font-size:11px;color:#c06090;">
                            ⏱&ensp;Valid for <strong class="txt-pink" style="color:#e91e8c;">10 minutes</strong>
                        </span>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

<p class="txt-muted" style="margin:0 0 32px;font-size:13px;color:#505570;line-height:1.7;text-align:center;">
    This code is single-use and will expire automatically.<br />
    <strong style="color:#7058a0;">Never share it with anyone.</strong>
</p>
@endsection
