@extends('emails.layout')

@section('title', 'Withdrawal Declined – Model Boss')
@section('heading', 'Withdrawal Declined')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:36px 0 28px;">
    <tr>
        <td align="center">

            <!-- declined icon -->
            <div style="width:56px;height:56px;margin:0 auto 24px;
                        background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);
                        border-radius:50%;text-align:center;line-height:56px;font-size:22px;">
                ✕
            </div>

            <p class="txt-muted" style="margin:0 0 24px;font-size:14px;color:#505570;line-height:1.7;text-align:center;">
                Hello, <strong class="txt-white" style="color:#ffffff;">{{ $name }}</strong>.<br />
                Unfortunately your withdrawal request has been declined.
            </p>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   class="info-card"
                   style="background:#12112a;border:1px solid rgba(239,68,68,0.25);border-radius:14px;margin-bottom:28px;">
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
                                <td style="font-size:12px;color:#6b7a8d;padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);">Coins</td>
                                <td style="font-size:20px;font-weight:800;color:#ef4444;text-align:right;padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);">
                                    {{ $coin_amount }} Coins
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>

            <p class="txt-muted" style="margin:0 0 32px;font-size:13px;color:#505570;line-height:1.7;text-align:center;">
                If you have any questions or need further assistance,<br />
                please contact our support team.
            </p>

        </td>
    </tr>
</table>
@endsection
