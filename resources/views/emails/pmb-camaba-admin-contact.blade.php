<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px;">
    <p>Yth. <strong>{{ $namaCamaba }}</strong>,</p>
    <p>Anda menerima pesan dari panitia Penerimaan Mahasiswa Baru <strong>{{ config('app.name') }}</strong>.</p>
    <p style="color:#64748b;font-size:14px;margin:8px 0 0;">Pengirim: {{ $namaAdmin }} &lt;{{ $emailAdmin }}&gt;</p>

    <div style="margin: 24px 0; padding: 16px 20px; background: #f1f5f9; border-radius: 8px; border-left: 4px solid #2563eb;">
        <p style="margin: 0 0 12px; font-size: 16px; font-weight: bold; color: #0f172a;">{{ $subjectLine }}</p>
        <div style="white-space: pre-wrap; color: #334155;">{{ $bodyPlain }}</div>
    </div>

    <p style="font-size: 13px; color: #64748b;">Balas email ini untuk menghubungi panitia (alamat balasan mengarah ke pengirim).</p>
</body>
</html>
