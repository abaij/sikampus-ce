<?php

namespace App\Providers;

use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\Setting;
use App\Support\Plugins\PluginBootManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Daftarkan service provider tiap plugin yang enabled (lihat tabel `plugins`
        // dan app/Support/Plugins/PluginBootManager) sebelum Laravel mem-parse
        // routes/web.php & routes/api.php, supaya route/migration milik plugin ikut
        // ter-load lewat mekanisme native loadRoutesFrom()/loadMigrationsFrom().
        PluginBootManager::bootEnabledPlugins($this->app);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pengaturan SMTP (menu Pengaturan > Sistem > SMTP) disimpan di tabel settings (prefix
        // app_mail_*), bukan .env — supaya berlaku di semua instance/container tanpa perlu akses
        // filesystem, dan tetap kepakai walau config di-cache (php artisan config:cache), karena
        // baris ini jalan lagi di setiap bootstrap request/command, bukan cuma sekali saat cache
        // dibuat. Kalau belum ada satu pun baris app_mail_* (instalasi baru/fresh), config dari
        // .env tetap dipakai apa adanya. Dibungkus try/catch supaya tidak mematahkan
        // `php artisan migrate` pertama kali sebelum tabel settings ada.
        try {
            if (Schema::hasTable('settings')) {
                $this->applyMailSettingsFromDatabase();
            }
        } catch (\Throwable $e) {
            // Database belum siap (mis. migrate pertama kali) — abaikan, .env tetap dipakai.
        }

        // Log semua percobaan kirim email (aktivasi, reset password, dst) ke channel 'mail'
        // (storage/logs/mail.log — lihat config/logging.php), terpisah dari laravel.log supaya
        // gagal kirim gampang ditelusuri. Kegagalan pengiriman untuk Mailable ShouldQueue (mis.
        // SMTP transport error) tidak lewat MessageSent — exception-nya terjadi lebih dulu — jadi
        // ditangani lewat method failed() masing-masing Mailable (lihat
        // app/Mail/VerifyEmailActivation.php, app/Mail/ResetPasswordMandiri.php).
        Event::listen(function (MessageSending $event): void {
            Log::channel('mail')->info('Mengirim email', [
                'to' => collect($event->message->getTo())->map(fn ($address) => $address->getAddress())->all(),
                'subject' => $event->message->getSubject(),
            ]);
        });

        Event::listen(function (MessageSent $event): void {
            Log::channel('mail')->info('Email berhasil dikirim', [
                'to' => collect($event->message->getTo())->map(fn ($address) => $address->getAddress())->all(),
                'subject' => $event->message->getSubject(),
            ]);
        });

        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            $frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
            $email = urlencode((string) $user->email);
            $token = urlencode($token);

            return "{$frontendUrl}/reset-password?token={$token}&email={$email}";
        });

        // Dibagikan lewat View Composer (bukan @php di layouts/web.blade.php) karena view anak yang
        // extends layout ini (mis. auth/login.blade.php) merender @section-nya SEBELUM parent — jadi
        // variabel @php di parent tidak pernah terlihat oleh section milik anak. Composer men-supply
        // variabel yang sama ke kedua view secara independen, jadi favicon, brand mark header,
        // footer, dan brand mark halaman login semuanya konsisten dari satu sumber.
        View::composer(['layouts.web', 'layouts.dosen', 'layouts.mahasiswa', 'layouts.prodi', 'auth.login', 'livewire.auth.aktivasi', 'livewire.auth.verify-email', 'livewire.auth.forgot-password', 'livewire.auth.reset-password'], function ($view): void {
            $univSettings = Setting::whereIn('key', ['app_univ_name', 'app_univ_logo'])->pluck('value', 'key');
            $namaPerguruanTinggi = trim((string) $univSettings->get('app_univ_name'));
            $logoPerguruanTinggi = trim((string) $univSettings->get('app_univ_logo'));

            $logoPerguruanTinggiSrc = null;
            if ($logoPerguruanTinggi !== '') {
                $logoPerguruanTinggiSrc = str_starts_with($logoPerguruanTinggi, 'http') || str_starts_with($logoPerguruanTinggi, 'data:image')
                    ? $logoPerguruanTinggi
                    : asset(ltrim($logoPerguruanTinggi, '/'));
            }

            $view->with(compact('namaPerguruanTinggi', 'logoPerguruanTinggiSrc'));
        });

        // Hanya untuk navbar panel (layouts.web), bukan halaman login — semester aktif tidak relevan
        // sebelum user masuk.
        View::composer('layouts.web', function ($view): void {
            $semesterAktif = Semester::where('is_active', true)->whereNull('deleted_at')->value('nama');

            $view->with('semesterAktif', $semesterAktif);
        });

        // Sidebar dosen (layouts.dosen) butuh kode_dosen & status kaprodi/sekprodi untuk tombol
        // "Administrasi Prodi" — dibagikan lewat composer dengan alasan sama seperti di atas
        // (variabel @php di parent tidak terlihat oleh @section milik anak).
        View::composer('layouts.dosen', function ($view): void {
            $user = auth()->user();
            $dosen = $user ? Dosen::where('id_user', $user->id)->first() : null;

            $view->with([
                'dosenSidebarKodeDosen' => $dosen?->kode_dosen,
                'dosenSidebarFotoUrl' => $dosen?->foto ? asset('storage/'.ltrim($dosen->foto, '/')) : null,
                'dosenHasProdiScope' => $user?->hasProdiScope() ?? false,
                'dosenProdiPortalUrl' => route('prodi.dashboard'),
            ]);
        });

        // Sidebar mahasiswa (layouts.mahasiswa) butuh NIM & foto untuk kartu info user — dibagikan
        // lewat composer dengan alasan sama seperti layouts.dosen di atas.
        View::composer('layouts.mahasiswa', function ($view): void {
            $user = auth()->user();
            $mahasiswa = $user ? Mahasiswa::where('id_user', $user->id)->first() : null;

            $view->with([
                'mahasiswaSidebarNim' => $mahasiswa?->nim,
                'mahasiswaSidebarFotoUrl' => $mahasiswa?->foto ? asset('storage/'.ltrim($mahasiswa->foto, '/')) : null,
            ]);
        });

        // Sidebar portal prodi (layouts.prodi) butuh info dosen (nama/foto) + daftar prodi yang
        // di-manage user ini (kaprodi dan/atau sekprodi, bisa lebih dari satu) untuk kartu identitas
        // & badge peran — dibagikan lewat composer dengan alasan sama seperti layouts.dosen di atas.
        View::composer('layouts.prodi', function ($view): void {
            $user = auth()->user();
            $dosen = $user ? Dosen::where('id_user', $user->id)->first() : null;

            $kaprodiIds = $user?->getKaprodiProdiIds() ?? [];
            $sekprodiIds = $user?->getSekprodiProdiIds() ?? [];
            $prodiIds = array_values(array_unique(array_merge($kaprodiIds, $sekprodiIds)));

            $prodiScopeList = Prodi::with('jenjang')
                ->whereIn('id', $prodiIds)
                ->orderBy('nama')
                ->get()
                ->map(fn (Prodi $prodi) => [
                    'id' => $prodi->id,
                    'nama' => $prodi->nama,
                    'kode_jenjang' => $prodi->jenjang?->kode,
                    'peran' => in_array($prodi->id, $kaprodiIds, true) ? 'Kepala Prodi' : 'Sekretaris Prodi',
                ]);

            $view->with([
                'prodiSidebarFotoUrl' => $dosen?->foto ? asset('storage/'.ltrim($dosen->foto, '/')) : null,
                'prodiSidebarKodeDosen' => $dosen?->kode_dosen,
                'prodiScopeList' => $prodiScopeList,
            ]);
        });
    }

    /**
     * Timpa config('mail.*') dari tabel settings kalau adminnya sudah pernah menyimpan lewat
     * halaman Pengaturan > Sistem > SMTP. Field mana pun yang belum pernah diisi (mis. baru
     * upgrade dari versi yang masih pakai .env) jatuh balik ke nilai dari .env/config default.
     *
     * Public (bukan private) supaya bisa dipanggil langsung dari test setelah menyimpan
     * pengaturan baru, tanpa perlu memicu ulang seluruh boot() (yang juga mendaftarkan ulang
     * event listener & view composer — sesuatu yang tidak diinginkan di tengah test).
     */
    public function applyMailSettingsFromDatabase(): void
    {
        $settings = Setting::where('key', 'like', 'app_mail_%')->pluck('value', 'key');

        if ($settings->isEmpty()) {
            return;
        }

        $host = trim((string) ($settings['app_mail_host'] ?? ''));
        if ($host !== '') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
            ]);
        }

        $port = trim((string) ($settings['app_mail_port'] ?? ''));
        if ($port !== '') {
            config(['mail.mailers.smtp.port' => (int) $port]);
        }

        if (array_key_exists('app_mail_username', $settings->all())) {
            config(['mail.mailers.smtp.username' => (string) $settings['app_mail_username'] ?: null]);
        }

        if (array_key_exists('app_mail_password', $settings->all())) {
            config(['mail.mailers.smtp.password' => (string) $settings['app_mail_password'] ?: null]);
        }

        if (array_key_exists('app_mail_encryption', $settings->all())) {
            config(['mail.mailers.smtp.scheme' => (string) $settings['app_mail_encryption'] ?: null]);
        }

        $fromAddress = trim((string) ($settings['app_mail_from_address'] ?? ''));
        if ($fromAddress !== '') {
            config(['mail.from.address' => $fromAddress]);
        }

        $fromName = trim((string) ($settings['app_mail_from_name'] ?? ''));
        if ($fromName !== '') {
            config(['mail.from.name' => $fromName]);
        }
    }
}
