@extends('emails.layout')

@section('title', 'Coins Received – Model Boss')
@section('heading', 'Coins Received')
@section('subheading', $sender_name . ' just sent you coins.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #bbf7d0;border-radius:16px;margin-bottom:24px;">
                <tr>
                    <td style="padding:28px;">

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-bottom:14px;">From</td>
                                <td style="font-size:13px;font-weight:600;color:#111827;text-align:right;padding-bottom:14px;">
                                    {{ $sender_name }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-top:14px;border-top:1px solid #e0d4f8;">Coins received</td>
                                <td style="font-size:24px;font-weight:800;color:#10b981;text-align:right;padding-top:14px;border-top:1px solid #e0d4f8;">
                                    {{ number_format($amount, 2) }}
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                The coins have been added to your balance.<br />
                Open the app to view your wallet.
            </p>

        </td>
    </tr>
</table>
@endsection
