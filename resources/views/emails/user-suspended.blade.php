@extends('emails.layout')

@section('title', 'Account Suspended – Model Boss')
@section('heading', 'Account Suspended')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:36px 0 28px;">
    <tr>
        <td>

            <!-- alert icon -->
            <div style="width:56px;height:56px;margin:0 auto 24px;
                        background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);
                        border-radius:50%;text-align:center;line-height:56px;font-size:24px;">
                🚫
            </div>

            <p class="txt-muted" style="margin:0 0 24px;font-size:14px;color:#505570;line-height:1.7;text-align:center;">
                Hello, <strong class="txt-white" style="color:#ffffff;">{{ $user->name }}</strong>.<br />
                Your Model Boss account has been suspended.
            </p>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   class="info-card"
                   style="background:#12112a;border:1px solid rgba(239,68,68,0.25);border-radius:14px;margin-bottom:24px;">
                <tr>
                    <td style="padding:24px 28px;">

                        @if($suspension->is_permanent)
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px;">
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;width:120px;padding-bottom:10px;">Duration</td>
                                <td style="font-size:13px;font-weight:600;color:#ef4444;padding-bottom:10px;">Permanent</td>
                            </tr>
                        </table>
                        @else
                        @if($suspension->suspended_until)
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px;">
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;width:120px;padding-bottom:10px;">Suspended Until</td>
                                <td style="font-size:13px;font-weight:600;color:#ffffff;padding-bottom:10px;">{{ $suspension->suspended_until->format('d M Y') }}</td>
                            </tr>
                        </table>
                        @endif
                        @endif

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;width:120px;vertical-align:top;">Reason</td>
                                <td style="font-size:13px;color:#ffffff;line-height:1.6;">{{ $suspension->reason }}</td>
                            </tr>
                        </table>

                        @if($suspension->note)
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.05);">
                            <tr>
                                <td style="font-size:12px;color:#6b7a8d;width:120px;vertical-align:top;">Note</td>
                                <td style="font-size:13px;color:#7880a0;line-height:1.6;">{{ $suspension->note }}</td>
                            </tr>
                        </table>
                        @endif

                    </td>
                </tr>
            </table>

            <p class="txt-muted" style="margin:0 0 32px;font-size:13px;color:#505570;line-height:1.7;text-align:center;">
                If you believe this is a mistake, please contact our support team.
            </p>

        </td>
    </tr>
</table>
@endsection
