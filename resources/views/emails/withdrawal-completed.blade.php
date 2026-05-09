@extends('emails.layout')

@section('title', 'Withdrawal Completed – Model Boss')
@section('heading', 'Withdrawal Completed')
@section('subheading', 'Your withdrawal request has been successfully processed.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:36px 0 28px;">
    <tr>
        <td align="center">

            <!-- success icon -->
            <div style="width:64px;height:64px;margin:0 auto 24px;
                        background:linear-gradient(145deg,#10b981,#059669);
                        border-radius:50%;text-align:center;line-height:64px;font-size:28px;">
                ✓
            </div>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   class="info-card"
                   style="background:#12112a;border:1px solid rgba(16,185,129,0.25);border-radius:14px;margin-bottom:28px;">
                <tr>
                    <td style="padding:28px;">

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;padding-bottom:14px;">Withdrawal No.</td>
                                <td style="font-size:13px;font-weight:600;color:#ffffff;text-align:right;padding-bottom:14px;">
                                    {{ $withdraw_no }}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);">Amount (USD)</td>
                                <td style="font-size:22px;font-weight:800;color:#10b981;text-align:right;padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);">
                                    ${{ number_format($usd_amount, 2) }}
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p class="txt-muted" style="margin:0 0 32px;font-size:13px;color:#505570;line-height:1.7;text-align:center;">
                The funds have been sent to your registered account.<br />
                If you have any questions, contact our support team.
            </p>

        </td>
    </tr>
</table>
@endsection
