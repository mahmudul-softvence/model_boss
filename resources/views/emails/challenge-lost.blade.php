@extends('emails.layout')

@section('title', 'Challenge Result – Model Boss')
@section('heading', 'Challenge Lost')
@section('heading_bg', 'linear-gradient(180deg,#f87171 0%,#b91c1c 100%)')
@section('heading_icon', '✕')
@section('heading_icon_color', '#ef4444')
@section('subheading', 'This time the win went to ' . $opponent_name . '.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;text-align:center;">
                Hello, <strong style="color:#111827;">{{ $notifiable_name }}</strong>.<br />
                Unfortunately you lost your challenge against <strong style="color:#111827;">{{ $opponent_name }}</strong>.
            </p>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #fecaca;border-radius:16px;margin-bottom:24px;">
                <tr>
                    <td style="padding:28px;">

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-bottom:14px;">Challenge No.</td>
                                <td style="font-size:13px;font-weight:600;color:#111827;text-align:right;padding-bottom:14px;">
                                    {{ $challenge_no }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-top:14px;border-top:1px solid #e0d4f8;">Stake forfeited</td>
                                <td style="font-size:22px;font-weight:800;color:#ef4444;text-align:right;padding-top:14px;border-top:1px solid #e0d4f8;">
                                    {{ number_format($stake, 2) }} Coins
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                Better luck next time — jump back in and start a new challenge.
            </p>

        </td>
    </tr>
</table>
@endsection
