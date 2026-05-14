@extends('emails.layout')

@section('title', 'Account Suspended – Model Boss')
@section('heading', 'Account Suspended')
@section('heading_bg', 'linear-gradient(135deg,#ef4444,#dc2626)')
@section('heading_icon', '🚫')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td>

            <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;text-align:center;">
                Hello, <strong style="color:#111827;">{{ $user->name }}</strong>.<br />
                Your Model Boss account has been suspended.
            </p>

            <!-- details card -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #fecaca;border-radius:16px;margin-bottom:20px;">
                <tr>
                    <td style="padding:24px 28px;">

                        @if($suspension->is_permanent)
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;width:120px;padding-bottom:10px;">Duration</td>
                                <td style="font-size:13px;font-weight:600;color:#ef4444;padding-bottom:10px;">Permanent</td>
                            </tr>
                        </table>
                        @else
                        @if($suspension->suspended_until)
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;width:120px;padding-bottom:10px;">Suspended Until</td>
                                <td style="font-size:13px;font-weight:600;color:#111827;padding-bottom:10px;">{{ $suspension->suspended_until->format('d M Y') }}</td>
                            </tr>
                        </table>
                        @endif
                        @endif

                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;width:120px;vertical-align:top;border-top:1px solid #e0d4f8;padding-top:12px;">Reason</td>
                                <td style="font-size:13px;color:#111827;line-height:1.6;border-top:1px solid #e0d4f8;padding-top:12px;">{{ $suspension->reason }}</td>
                            </tr>
                        </table>

                        @if($suspension->note)
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;">
                            <tr>
                                <td style="font-size:12px;color:#9ca3af;width:120px;vertical-align:top;border-top:1px solid #e0d4f8;padding-top:12px;">Note</td>
                                <td style="font-size:13px;color:#6b7280;line-height:1.6;border-top:1px solid #e0d4f8;padding-top:12px;">{{ $suspension->note }}</td>
                            </tr>
                        </table>
                        @endif

                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                If you believe this is a mistake, please contact our support team.
            </p>

        </td>
    </tr>
</table>
@endsection
