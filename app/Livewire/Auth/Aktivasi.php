<?php

namespace App\Livewire\Auth;

use App\Mail\VerifyEmailActivation;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Wizard aktivasi mandiri (dosen/mahasiswa) — versi Livewire dari alur yang sama dengan
 * app/Http/Controllers/ActivationController.php (dipakai frontend Next.js). Logic query &
 * pembuatan akun sengaja diduplikasi (bukan diekstrak ke service bersama) karena kedua jalur
 * ini punya siklus hidup berbeda; link verifikasi di sini menunjuk ke route Livewire
 * 'verify-email', bukan FRONTEND_URL seperti pada ActivationController.
 */
class Aktivasi extends Component
{
    public int $step = 1;

    public string $role = '';

    public string $identifier = '';

    public bool $hasAccount = false;

    public bool $emailVerified = false;

    /** @var array{id:int,nama:string,nim?:string,kode_dosen?:string,email:?string}|null */
    public ?array $identifierData = null;

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $errorMessage = '';

    public string $successMessage = '';

    public function updatedRole(): void
    {
        $this->resetIdentifierState();
    }

    public function updatedIdentifier(): void
    {
        $this->resetIdentifierState();
    }

    private function resetIdentifierState(): void
    {
        $this->hasAccount = false;
        $this->emailVerified = false;
        $this->identifierData = null;
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function checkIdentifier(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $this->validate([
            'role' => ['required', 'string', 'in:mahasiswa,dosen'],
            'identifier' => ['required', 'string'],
        ], [], [
            'identifier' => $this->role === 'dosen' ? 'kode dosen' : 'NIM',
        ]);

        if ($this->role === 'mahasiswa') {
            $mahasiswa = Mahasiswa::where('nim', $this->identifier)->first();

            if (! $mahasiswa) {
                $this->errorMessage = 'NIM tidak ditemukan.';

                return;
            }

            if ($mahasiswa->id_user) {
                $user = User::find($mahasiswa->id_user);
                $this->hasAccount = true;
                $this->emailVerified = $user ? (bool) $user->email_verified_at : false;
                $this->identifierData = [
                    'id' => $mahasiswa->id,
                    'nama' => $mahasiswa->nama,
                    'nim' => $mahasiswa->nim,
                    'email' => $user?->email,
                ];

                return;
            }

            $this->hasAccount = false;
            $this->identifierData = [
                'id' => $mahasiswa->id,
                'nama' => $mahasiswa->nama,
                'nim' => $mahasiswa->nim,
                'email' => $mahasiswa->email,
            ];
        } else {
            $dosen = Dosen::where('kode_dosen', $this->identifier)->first();

            if (! $dosen) {
                $this->errorMessage = 'Kode dosen tidak ditemukan.';

                return;
            }

            if ($dosen->id_user) {
                $user = User::find($dosen->id_user);
                $this->hasAccount = true;
                $this->emailVerified = $user ? (bool) $user->email_verified_at : false;
                $this->identifierData = [
                    'id' => $dosen->id,
                    'nama' => $dosen->nama,
                    'kode_dosen' => $dosen->kode_dosen,
                    'email' => $user?->email,
                ];

                return;
            }

            $this->hasAccount = false;
            $this->identifierData = [
                'id' => $dosen->id,
                'nama' => $dosen->nama,
                'kode_dosen' => $dosen->kode_dosen,
                'email' => null,
            ];
        }

        $this->email = $this->identifierData['email'] ?? '';
        $this->step = 2;
    }

    public function register(): void
    {
        $this->errorMessage = '';

        $this->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'passwordConfirmation' => ['required', 'string', 'same:password'],
        ], [
            'passwordConfirmation.same' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        DB::beginTransaction();

        try {
            if ($this->role === 'mahasiswa') {
                $mahasiswa = Mahasiswa::where('nim', $this->identifier)
                    ->whereNull('id_user')
                    ->first();

                if (! $mahasiswa) {
                    DB::rollBack();
                    $this->errorMessage = 'NIM tidak ditemukan atau sudah memiliki akun.';

                    return;
                }

                if ($mahasiswa->email !== $this->email) {
                    $mahasiswa->update(['email' => $this->email]);
                }

                $username = $mahasiswa->nim;

                if (User::where('username', $username)->exists()) {
                    DB::rollBack();
                    $this->errorMessage = 'Username sudah digunakan. Hubungi admin untuk bantuan aktivasi.';

                    return;
                }

                $user = User::create([
                    'name' => $mahasiswa->nama,
                    'email' => $this->email,
                    'username' => $username,
                    'password' => Hash::make($this->password),
                    'role' => 'mahasiswa',
                    'status' => 'active',
                    'phone' => $mahasiswa->handphone,
                    'address' => $mahasiswa->alamat,
                    'zip' => $mahasiswa->kode_pos,
                ]);

                $mahasiswa->update(['id_user' => $user->id]);

                $mahasiswaId = $mahasiswa->id;
                $dosenId = null;
            } else {
                $dosen = Dosen::where('kode_dosen', $this->identifier)
                    ->whereNull('id_user')
                    ->first();

                if (! $dosen) {
                    DB::rollBack();
                    $this->errorMessage = 'Kode dosen tidak ditemukan atau sudah memiliki akun.';

                    return;
                }

                $username = $dosen->kode_dosen;

                if (User::where('username', $username)->exists()) {
                    DB::rollBack();
                    $this->errorMessage = 'Username sudah digunakan. Hubungi admin untuk bantuan aktivasi.';

                    return;
                }

                $user = User::create([
                    'name' => $dosen->nama,
                    'email' => $this->email,
                    'username' => $username,
                    'password' => Hash::make($this->password),
                    'role' => 'dosen',
                    'status' => 'active',
                    'phone' => $dosen->no_hp,
                    'address' => $dosen->alamat,
                    'zip' => $dosen->kode_pos,
                ]);

                $dosen->update(['id_user' => $user->id]);

                $mahasiswaId = null;
                $dosenId = $dosen->id;
            }

            $token = Str::random(64);

            DB::table('email_verifications')->insert([
                'email' => $this->email,
                'token' => $token,
                'role' => $this->role,
                'mahasiswa_id' => $mahasiswaId,
                'dosen_id' => $dosenId,
                'expires_at' => Carbon::now()->addHours(24),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $verificationUrl = route('verify-email', ['token' => $token, 'email' => $this->email]);
            Mail::to($this->email)->send(new VerifyEmailActivation($user, $verificationUrl));

            DB::commit();

            $this->step = 3;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorMessage = 'Terjadi kesalahan saat membuat akun: '.$e->getMessage();
        }
    }

    public function resendVerification(): void
    {
        $this->errorMessage = '';
        $this->successMessage = '';

        $user = User::where('email', $this->identifierData['email'] ?? null)->first();

        if (! $user) {
            $this->errorMessage = 'User tidak ditemukan.';

            return;
        }

        if ($user->email_verified_at) {
            $this->errorMessage = 'Email Anda sudah terverifikasi. Silakan login.';

            return;
        }

        $token = Str::random(64);

        DB::table('email_verifications')
            ->where('email', $user->email)
            ->whereNull('verified_at')
            ->delete();

        DB::table('email_verifications')->insert([
            'email' => $user->email,
            'token' => $token,
            'role' => $this->role,
            'mahasiswa_id' => $this->role === 'mahasiswa' ? $this->identifierData['id'] : null,
            'dosen_id' => $this->role === 'dosen' ? $this->identifierData['id'] : null,
            'expires_at' => Carbon::now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $verificationUrl = route('verify-email', ['token' => $token, 'email' => $user->email]);
        Mail::to($user->email)->send(new VerifyEmailActivation($user, $verificationUrl));

        $this->successMessage = 'Email verifikasi telah dikirim ulang. Silakan cek inbox email Anda.';
    }

    public function back(): void
    {
        $this->step = 1;
        $this->hasAccount = false;
        $this->emailVerified = false;
        $this->errorMessage = '';
        $this->successMessage = '';
    }

    public function render()
    {
        return view('livewire.auth.aktivasi')->extends('layouts.web');
    }
}
