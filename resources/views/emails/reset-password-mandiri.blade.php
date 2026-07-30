<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0;">Reset Password</h1>
    </div>

    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p>Halo <strong>{{ $user->name }}</strong>,</p>

        <p>Kami menerima permintaan untuk mengatur ulang password akun Sistem Informasi Akademik (SIAK) Anda.</p>

        <p>Silakan klik tombol di bawah ini untuk membuat password baru:</p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}"
               style="display: inline-block; background: #0ea5e9; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Reset Password
            </a>
        </div>

        <p style="font-size: 12px; color: #666;">Atau salin dan tempel link berikut di browser Anda:</p>
        <p style="font-size: 12px; color: #0ea5e9; word-break: break-all;">{{ $resetUrl }}</p>

        <p style="font-size: 12px; color: #666; margin-top: 30px;">
            <strong>Catatan:</strong> Link reset password ini akan kedaluwarsa dalam {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60) }} menit.
        </p>

        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

        <p style="font-size: 12px; color: #666; margin: 0;">
            Jika Anda tidak meminta reset password, abaikan email ini — password Anda tidak akan berubah.
        </p>
    </div>
</body>
</html>
