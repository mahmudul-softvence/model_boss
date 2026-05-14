@extends('emails.layout')

@section('title', 'New Withdrawal Request – Model Boss')
@section('heading', 'New Withdrawal Request')
@section('heading_bg', 'linear-gradient(135deg,#9c27b0,#6a1b9a)')
@section('heading_icon', '↑')
@section('subheading', 'A user has submitted a new withdrawal request.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #d4bef0;border-radius:16px;margin-bottom:24px;">
                <tr>
                    <td style="padding:28px;">

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-bottom:14px;">User</td>
                                <td style="font-size:13px;font-weight:600;color:#111827;text-align:right;padding-bottom:14px;">
                                    {{ $user_name }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding:14px 0;border-top:1px solid #e0d4f8;">
                                    Withdrawal No.
                                </td>
                                <td style="font-size:13px;font-weight:600;color:#111827;text-align:right;padding:14px 0;border-top:1px solid #e0d4f8;">
                                    {{ $withdraw_no }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding:14px 0;border-top:1px solid #e0d4f8;">
                                    Coins
                                </td>
                                <td style="font-size:13px;font-weight:600;color:#111827;text-align:right;padding:14px 0;border-top:1px solid #e0d4f8;">
                                    {{ $coin_amount }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;padding-top:14px;border-top:1px solid #e0d4f8;">
                                    USD Value
                                </td>
                                <td style="font-size:24px;font-weight:800;color:#9c27b0;text-align:right;padding-top:14px;border-top:1px solid #e0d4f8;">
                                    ${{ number_format($usd_amount, 2) }}
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                Please review and action this request in the admin panel.
            </p>

        </td>
    </tr>
</table>
@endsection
