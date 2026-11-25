<!DOCTYPE html>
<html lang="{{ \App\Core\Localization\Locales::htmlLang() }}" dir="{{ \App\Core\Localization\Locales::direction() }}">
<head>
    <meta charset="utf-8">
</head>
<body style="margin: 0; padding: 0; background-color: #6C7A89;">
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #6C7A89;">
    <tr>
        <td style="text-align: center;padding: 50px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto;">
                <tr>
                    <td style="background-color: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.16);">
                        <h2 style="margin-bottom: 20px;">{{ __('Reset Password') }}</h2>
                        <p>
                            {{ __('You are receiving this email because we received a password reset request for your account. Your code is below:') }}
                            <br>
                            <br>
                            <br>
                            <code style="padding: 5px; background-color: #9ca3af46;">{{ $token }}</code>
                            <br>
                            <br>
                            <a href="{{ $resetLink }}" style="background-color: #007BFF; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">{{ __('Reset Password') }}</a>
                        </p>
                        <p>
                            {{ __('If you did not request a password reset, no further action is required.') }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
