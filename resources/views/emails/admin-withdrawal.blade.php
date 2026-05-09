@extends('emails.layout')

@section('title', 'New Withdrawal Request – Model Boss')
@section('heading', 'New Withdrawal Request')
@section('subheading', 'A user has submitted a new withdrawal request.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:36px 0 28px;">
    <tr>
        <td align="center">

            <!-- alert icon -->
            <div style="width:64px;height:64px;margin:0 auto 24px;
                        background:linear-gradient(145deg,#e91e8c,#9c27b0);
                        border-radius:50%;text-align:center;line-height:64px;font-size:28px;">
                ↑
            </div>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   class="info-card"
                   style="background:#12112a;border:1px solid rgba(233,30,140,0.35);border-radius:14px;margin-bottom:28px;">
                <tr>
                    <td style="padding:28px;">

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;padding-bottom:14px;">User</td>
                                <td style="font-size:13px;font-weight:600;color:#ffffff;text-align:right;padding-bottom:14px;">
                                    {{ $user_name }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;
                                           padding:14px 0;border-top:1px solid rgba(255,255,255,0.05);">
                                    Withdrawal No.
                                </td>
                                <td style="font-size:13px;font-weight:600;color:#ffffff;text-align:right;
                                           padding:14px 0;border-top:1px solid rgba(255,255,255,0.05);">
                                    {{ $withdraw_no }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;
                                           padding:14px 0;border-top:1px solid rgba(255,255,255,0.05);">
                                    Coins
                                </td>
                                <td style="font-size:13px;font-weight:600;color:#ffffff;text-align:right;
                                           padding:14px 0;border-top:1px solid rgba(255,255,255,0.05);">
                                    {{ $coin_amount }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;
                                           padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);">
                                    USD Value
                                </td>
                                <td style="font-size:22px;font-weight:800;color:#e91e8c;text-align:right;
                                           padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);">
                                    ${{ number_format($usd_amount, 2) }}
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p class="txt-muted" style="margin:0 0 32px;font-size:13px;color:#505570;line-height:1.7;text-align:center;">
                Please review and action this request in the admin panel.
            </p>

        </td>
    </tr>
</table>
@endsection
