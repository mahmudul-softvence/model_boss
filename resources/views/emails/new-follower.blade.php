@extends('emails.layout')

@section('title', 'New Follower – Model Boss')
@section('heading', 'You Have a New Follower')
@section('subheading', $follower_name . ' just started following you.')

@section('body')
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
        <tr>
            <td align="center">

                <table width="100%" cellpadding="0" cellspacing="0" border="0"
                    style="background:#ede8f8;border:1px solid #d8c9f5;border-radius:16px;margin-bottom:24px;">
                    <tr>
                        <td style="padding:28px;text-align:center;">
                            <a href="https://modelbossoffers.com/artist/{{ $follower_id }}" target="_blank"
                                style="margin:0;font-size:18px;font-weight:800;color:#111827;">
                                {{ $follower_name }}
                            </a>
                            <p style="margin:8px 0 0;font-size:13px;color:#6b7280;">
                                is now following you.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                    Open the app to view their profile and follow back.
                </p>

            </td>
        </tr>
    </table>
@endsection
