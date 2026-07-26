@extends('emails.layout')

@section('title', 'Opponent Ready – Model Boss')
@section('heading', 'Opponent Ready')
@section('heading_bg', 'linear-gradient(180deg,#f59e0b 0%,#d97706 100%)')
@section('heading_icon_color', '#d97706')
@section('heading_icon', '⚡')
@section('subheading', $opponent_name . ' is ready for your challenge — join now!')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;text-align:center;">
                Hello, <strong style="color:#111827;">{{ $notifiable_name }}</strong>.<br />
                <strong style="color:#111827;">{{ $opponent_name }}</strong> has confirmed they are ready for your challenge.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#fef3c7;border:1px solid #f59e0b;border-radius:16px;margin-bottom:24px;">
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
                                <td style="font-size:12px;color:#9ca3af;padding-top:14px;border-top:1px solid #fde68a;">Ready By</td>
                                <td style="font-size:14px;font-weight:700;color:#d97706;text-align:right;padding-top:14px;border-top:1px solid #fde68a;">
                                    {{ $ready_expires_at ? \Carbon\Carbon::parse($ready_expires_at)->format('H:i') : 'N/A' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                You have 10 minutes to confirm your readiness.<br />
                If you don't join in time, the ready window will expire.
            </p>

        </td>
    </tr>
</table>
@endsection
