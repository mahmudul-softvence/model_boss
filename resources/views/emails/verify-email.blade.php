@extends('emails.layout')

@section('title', 'Verify Your Email – Model Boss')
@section('heading', 'Verify Your Email')
@section('subheading', 'Click the button below to confirm your email address and activate your account.')

@section('body')
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:36px 0;">
    <tr>
        <td align="center">

            <!-- icon -->
            <div style="width:64px;height:64px;margin:0 auto 24px;
                        background:linear-gradient(145deg,#e91e8c,#9c27b0);
                        border-radius:50%;text-align:center;line-height:64px;font-size:28px;">
                ✉
            </div>

            <p class="txt-muted" style="margin:0 0 28px;font-size:14px;color:#505570;line-height:1.7;text-align:center;">
                Hello, <strong class="txt-white" style="color:#ffffff;">{{ $name }}</strong>!<br />
                Thanks for signing up. Please verify your email to get started.
            </p>

            <!-- CTA button -->
            <table cellpadding="0" cellspacing="0" border="0" align="center">
                <tr>
                    <td style="background:linear-gradient(135deg,#e91e8c,#9c27b0);
                               border-radius:10px;padding:1px;">
                        <a href="{{ $url }}"
                           style="display:inline-block;padding:14px 36px;
                                  background:linear-gradient(135deg,#e91e8c,#9c27b0);
                                  border-radius:10px;font-size:15px;font-weight:700;
                                  color:#ffffff;text-decoration:none;letter-spacing:0.5px;">
                            Verify Email Address
                        </a>
                    </td>
                </tr>
            </table>

            <!-- fallback link -->
            <p class="txt-muted" style="margin:24px 0 0;font-size:12px;color:#505570;text-align:center;">
                Or copy &amp; paste this link into your browser:<br />
                <a href="{{ $url }}" class="txt-pink"
                   style="color:#e91e8c;word-break:break-all;font-size:11px;">
                    {{ $url }}
                </a>
            </p>

            <p class="txt-muted" style="margin:20px 0 0;font-size:12px;color:#505570;text-align:center;">
                This link expires in <strong class="txt-pink" style="color:#e91e8c;">60 minutes</strong>.
            </p>

        </td>
    </tr>
</table>
@endsection
