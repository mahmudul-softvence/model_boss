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
                            <a href="{{ $follow_back_url }}" target="_blank"
                                style="margin:0;font-size:18px;font-weight:800;color:#111827;text-decoration:none;">
                                {{ $follower_name }}
                            </a>
                            <p style="margin:8px 0 20px;font-size:13px;color:#6b7280;">
                                is now following you.
                            </p>

                            <!-- Follow back button -->
                            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto;">
                                <tr>
                                    <td style="border-radius:50px;background:linear-gradient(180deg,#80e080 0%,#32a832 100%);">
                                        <a href="{{ $follow_back_url }}" target="_blank"
                                            style="display:inline-block;padding:13px 36px;font-size:15px;font-weight:700;
                                                   color:#ffffff;text-decoration:none;border-radius:50px;white-space:nowrap;">
                                            ✓&nbsp; Follow back
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="margin:0 0 8px;font-size:13px;color:#9ca3af;line-height:1.7;text-align:center;">
                    Tap “Follow back” to view their profile and follow them too.
                </p>

            </td>
        </tr>
    </table>
@endsection
