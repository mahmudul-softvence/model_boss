@extends('emails.layout')

@section('title', 'You Won – Model Boss')
@section('heading', 'Challenge Won')
@section('subheading', 'Congratulations — you beat ' . $opponent_name . '!')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;text-align:center;">
                Hello, <strong style="color:#111827;">{{ $notifiable_name }}</strong>.<br />
                You won your challenge against <strong style="color:#111827;">{{ $opponent_name }}</strong>.
            </p>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #bbf7d0;border-radius:16px;margin-bottom:24px;">
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
                                <td style="font-size:12px;color:#9ca3af;padding-top:14px;border-top:1px solid #e0d4f8;">Winnings</td>
                                <td style="font-size:24px;font-weight:800;color:#10b981;text-align:right;padding-top:14px;border-top:1px solid #e0d4f8;">
                                    {{ number_format($payout, 2) }} Coins
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                Your winnings have been added to your balance.<br />
                Open the app to view your wallet.
            </p>

        </td>
    </tr>
</table>
@endsection
