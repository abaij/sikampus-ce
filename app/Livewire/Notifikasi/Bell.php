<?php

namespace App\Livewire\Notifikasi;

use App\Models\Notifikasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Bell extends Component
{
    public bool $open = false;

    /**
     * tipe notifikasi -> nama rute Blade. Kolom `url` yang tersimpan di baris notifikasi
     * adalah path siak-frontend (mis. '/dosen/perwalian/persetujuan-krs' untuk
     * krs_diajukan — lihat KrsController::store), bukan rute panel Livewire ini. Jadi
     * navigasi saat notifikasi diklik dipetakan lewat tipe di sini, bukan dibaca
     * langsung dari kolom url tersebut.
     */
    private const TIPE_ROUTE_MAP = [
        'krs_diajukan' => 'dosen.krs',
        'krs_disetujui' => 'mahasiswa.krs',
        'nilai_final' => 'mahasiswa.nilai.semester',
        'pembayaran_acc' => 'mahasiswa.tagihan',
    ];

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Notifikasi::where('id_user', Auth::id())->belumDibaca()->count();
    }

    #[Computed]
    public function items()
    {
        if (! $this->open) {
            return collect();
        }

        return Notifikasi::where('id_user', Auth::id())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function openItem(int $id)
    {
        $notifikasi = Notifikasi::findOrFail($id);
        abort_if($notifikasi->id_user !== Auth::id(), 403, 'Anda tidak memiliki akses ke notifikasi ini.');

        if ($notifikasi->dibaca_pada === null) {
            $notifikasi->update(['dibaca_pada' => now()]);
        }

        $this->open = false;
        unset($this->unreadCount);

        $routeName = self::TIPE_ROUTE_MAP[$notifikasi->tipe] ?? null;

        if ($routeName && Route::has($routeName)) {
            return redirect()->route($routeName);
        }
    }

    public function markAllAsRead(): void
    {
        Notifikasi::where('id_user', Auth::id())->belumDibaca()->update(['dibaca_pada' => now()]);

        unset($this->unreadCount, $this->items);
    }

    /**
     * Sama dengan formatWaktuLalu di siak-frontend/components/NotificationBell.tsx, ditulis
     * ulang manual (bukan Carbon::diffForHumans) supaya outputnya konsisten Indonesia
     * apa pun nilai APP_LOCALE di server.
     */
    public function formatWaktuLalu(Carbon $waktu): string
    {
        $diffSec = max(0, now()->diffInSeconds($waktu));

        if ($diffSec < 60) {
            return 'Baru saja';
        }

        $diffMin = intdiv($diffSec, 60);
        if ($diffMin < 60) {
            return "{$diffMin} menit lalu";
        }

        $diffJam = intdiv($diffMin, 60);
        if ($diffJam < 24) {
            return "{$diffJam} jam lalu";
        }

        $diffHari = intdiv($diffJam, 24);
        if ($diffHari < 7) {
            return "{$diffHari} hari lalu";
        }

        return $waktu->translatedFormat('j M Y');
    }

    public function render()
    {
        return view('livewire.notifikasi.bell');
    }
}
