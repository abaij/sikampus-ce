<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0;">Verifikasi Email Anda</h1>
    </div>
    
    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p>Halo <strong>{{ $user->name }}</strong>,</p>
        
        <p>Terima kasih telah melakukan aktivasi akun di Sistem Informasi Akademik (SIAK).</p>
        
        <p>Untuk menyelesaikan proses aktivasi, silakan klik tombol di bawah ini untuk memverifikasi email Anda:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $verificationUrl }}" 
               style="display: inline-block; background: #0ea5e9; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Verifikasi Email
            </a>
        </div>
        
        <p style="font-size: 12px; color: #666;">Atau salin dan tempel link berikut di browser Anda:</p>
        <p style="font-size: 12px; color: #0ea5e9; word-break: break-all;">{{ $verificationUrl }}</p>
        
        <p style="font-size: 12px; color: #666; margin-top: 30px;">
            <strong>Catatan:</strong> Link verifikasi ini akan kedaluwarsa dalam 24 jam. Jika Anda tidak melakukan aktivasi akun, abaikan email ini.
        </p>
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        
        <p style="font-size: 12px; color: #666; margin: 0;">
            Jika Anda tidak melakukan aktivasi akun, abaikan email ini.
        </p>
    </div>
</body>
</html>

