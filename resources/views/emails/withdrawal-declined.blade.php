@extends('emails.layout')

@section('title', 'Withdrawal Declined – Model Boss')
@section('heading', 'Withdrawal Declined')
@section('heading_bg', 'linear-gradient(135deg,#ef4444,#dc2626)')
@section('heading_icon', '✕')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;text-align:center;">
                Hello, <strong style="color:#111827;">{{ $name }}</strong>.<br />
                Unfortunately your withdrawal request has been declined.
            </p>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #fecaca;border-radius:16px;margin-bottom:24px;">
                <tr>
                    <td style="padding:28px;">

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-bottom:14px;">Withdrawal No.</td>
                                <td style="font-size:13px;font-weight:600;color:#111827;text-align:right;padding-bottom:14px;">
                                    {{ $withdraw_no }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-top:14px;border-top:1px solid #e0d4f8;">Coins</td>
                                <td style="font-size:22px;font-weight:800;color:#ef4444;text-align:right;padding-top:14px;border-top:1px solid #e0d4f8;">
                                    {{ $coin_amount }} Coins
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                If you have any questions or need further assistance,<br />
                please contact our support team.
            </p>

        </td>
    </tr>
</table>
@endsection
