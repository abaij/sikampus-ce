<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Ulang PMB Selesai</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #047857 0%, #10b981 100%); padding: 28px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 22px;">Daftar Ulang Selesai</h1>
        <p style="color: #d1fae5; margin: 10px 0 0; font-size: 14px;">{{ config('app.name') }} — Penerimaan Mahasiswa Baru</p>
    </div>

    <div style="background: #f8fafc; padding: 28px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px;">
        <p>Yth. <strong>{{ $namaCamaba }}</strong>,</p>
        <p>Selamat! Proses <strong>daftar ulang</strong> Anda telah <strong>selesai</strong> dan telah kami verifikasi. Berikut program studi dan nomor induk mahasiswa (NIM) Anda:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <tr>
                <td colspan="2" style="background: #047857; color: #fff; padding: 10px 14px; font-weight: bold;">Data daftar ulang</td>
            </tr>
            <tr>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0; width: 38%; color: #64748b;">Nomor pendaftaran</td>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0;"><strong>{{ $noPendaftaran }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Program studi</td>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0;">
                    <strong>{{ $namaProdi }}</strong>
                    @if(!empty($kodeProdi))<span style="color: #64748b;"> ({{ $kodeProdi }})</span>@endif
                </td>
            </tr>
            <tr>
                <td style="padding: 10px 14px; color: #64748b;">NIM</td>
                <td style="padding: 10px 14px;">
                    @if(!empty($nim))
                        <strong style="font-family: monospace; letter-spacing: 0.05em;">{{ $nim }}</strong>
                    @else
                        <span style="color: #64748b;">Akan diinformasikan menyusul oleh bagian akademik.</span>
                    @endif
                </td>
            </tr>
        </table>

        <p style="font-size: 14px; color: #475569;">Silakan login ke portal PMB untuk melihat status terkini. Jika ada pertanyaan, hubungi bagian PMB kampus.</p>

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

        <p style="font-size: 12px; color: #94a3b8; margin: 0;">
            Email ini dikirim otomatis, mohon tidak membalas langsung ke alamat ini.
        </p>
    </div>
</body>
</html>
