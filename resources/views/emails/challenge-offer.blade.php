@extends('emails.layout')

@section('title', 'New Challenge – Model Boss')
@section('heading', 'You Have Been Challenged')
@section('heading_bg', 'linear-gradient(180deg,#b18bf5 0%,#7c3aed 100%)')
@section('heading_icon_color', '#7c3aed')
@section('heading_icon', '⚔')
@section('subheading', ($challenger_name ?: 'A player').' has challenged you to a match.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center">

            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background:#ede8f8;border:1px solid #d8c9f5;border-radius:16px;margin-bottom:24px;">
                <tr>
                    <td style="padding:28px;text-align:center;">
                        <p style="margin:0;font-size:18px;font-weight:800;color:#111827;">
                            {{ $challenger_name ?: 'A player' }}
                        </p>
                        <p style="margin:8px 0 0;font-size:13px;color:#6b7280;">
                            wants to take you on for <strong>{{ $amount }}</strong> points.
                        </p>
                        <p style="margin:12px 0 0;font-size:12px;color:#9ca3af;">
                            Challenge&nbsp;#{{ $challenge_no }}
                        </p>
                    </td>
                </tr>
            </table>

            <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                Open the app to accept or decline this challenge before it expires.
            </p>

        </td>
    </tr>
</table>
@endsection
