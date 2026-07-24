<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pendaftaran PMB</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 640px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); padding: 28px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 22px;">Pendaftaran Berhasil Dikirim</h1>
        <p style="color: #e0e7ff; margin: 10px 0 0; font-size: 14px;">{{ config('app.name') }} — Penerimaan Mahasiswa Baru</p>
    </div>

    <div style="background: #f8fafc; padding: 28px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px;">
        <p>Yth. <strong>{{ $namaCamaba }}</strong>,</p>
        <p>Terima kasih telah menyelesaikan pendaftaran. Bukti pembayaran Anda telah kami terima dan pendaftaran dicatat sebagai <strong>terkirim (submitted)</strong>. Berikut ringkasan data pendaftaran Anda:</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <tr>
                <td colspan="2" style="background: #1e40af; color: #fff; padding: 10px 14px; font-weight: bold;">Data pendaftaran</td>
            </tr>
            <tr>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0; width: 38%; color: #64748b;">Nomor pendaftaran</td>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0;"><strong>{{ $noPendaftaran }}</strong></td>
            </tr>
            @if($tanggalPendaftaran)
            <tr>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Tanggal daftar</td>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0;">{{ $tanggalPendaftaran }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Periode</td>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0;">{{ $namaPeriode }}</td>
            </tr>
            @if($jalurMasuk)
            <tr>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Jalur masuk</td>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0;">{{ $jalurMasuk }}</td>
            </tr>
            @endif
            @if($jenisDaftar)
            <tr>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Jenis daftar</td>
                <td style="padding: 10px 14px; border-bottom: 1px solid #e2e8f0;">{{ $jenisDaftar }}</td>
            </tr>
            @endif
            <tr>
                <td style="padding: 10px 14px; color: #64748b;">Nomor kuitansi</td>
                <td style="padding: 10px 14px;"><strong>{{ $noKuitansi }}</strong></td>
            </tr>
        </table>

        @if(count($prodiPilihan) > 0)
        <p style="margin-top: 24px; font-weight: bold; color: #1e293b;">Program studi pilihan</p>
        <ol style="margin: 0 0 20px; padding-left: 22px;">
            @foreach($prodiPilihan as $p)
            <li style="margin-bottom: 6px;">
                {{ $p['nama'] }}
                @if(!empty($p['kode']))<span style="color: #64748b;"> ({{ $p['kode'] }})</span>@endif
                @if(!empty($p['jenjang']))<span style="color: #64748b;"> — {{ $p['jenjang'] }}</span>@endif
            </li>
            @endforeach
        </ol>
        @endif

        <p style="font-weight: bold; color: #1e293b;">Rincian biaya &amp; total</p>
        <table style="width: 100%; border-collapse: collapse; margin: 8px 0 16px; background: #fff; border-radius: 8px; overflow: hidden;">
            @foreach($rincianBiaya as $row)
            <tr>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">{{ $row['nama'] }}</td>
                <td style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0; text-align: right;">Rp {{ number_format((float) $row['jumlah'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr style="background: #eff6ff;">
                <td style="padding: 10px 12px; font-weight: bold;">Total</td>
                <td style="padding: 10px 12px; text-align: right; font-weight: bold;">Rp {{ number_format($totalBiaya, 0, ',', '.') }}</td>
            </tr>
        </table>

        <p style="font-size: 14px; color: #475569;">Tim kami akan memverifikasi pembayaran Anda. Silakan login ke portal PMB untuk memantau status pendaftaran.</p>

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">

        <p style="font-size: 12px; color: #94a3b8; margin: 0;">
            Email ini dikirim otomatis, mohon tidak membalas langsung ke alamat ini. Jika Anda tidak melakukan pendaftaran, abaikan pesan ini.
        </p>
    </div>
</body>
</html>
