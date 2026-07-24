<?php

namespace App\Http\Controllers;

use App\Models\Mahasiswa;
use App\Models\Dosen;
use App\Models\User;
use App\Mail\VerifyEmailActivation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ActivationController extends Controller
{
    /**
     * Cek NIM atau kode dosen untuk aktivasi akun
     */
    public function checkIdentifier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:mahasiswa,dosen'],
            'identifier' => ['required', 'string'],
        ]);

        $role = $validated['role'];
        $identifier = $validated['identifier'];

        if ($role === 'mahasiswa') {
            $mahasiswa = Mahasiswa::where('nim', $identifier)->first();

            if (!$mahasiswa) {
                return response()->json([
                    'message' => 'NIM tidak ditemukan.',
                    'found' => false,
                    'has_account' => false,
                ], 404);
            }

            // Jika sudah punya user, kembalikan info user
            if ($mahasiswa->id_user) {
                $user = User::find($mahasiswa->id_user);
                return response()->json([
                    'found' => true,
                    'has_account' => true,
                    'email_verified' => $user ? (bool) $user->email_verified_at : false,
                    'data' => [
                        'id' => $mahasiswa->id,
                        'nama' => $mahasiswa->nama,
                        'nim' => $mahasiswa->nim,
                        'email' => $user ? $user->email : null,
                    ],
                ]);
            }

            return response()->json([
                'found' => true,
                'has_account' => false,
                'data' => [
                    'id' => $mahasiswa->id,
                    'nama' => $mahasiswa->nama,
                    'nim' => $mahasiswa->nim,
                    'email' => $mahasiswa->email,
                ],
            ]);
        } else {
            $dosen = Dosen::where('kode_dosen', $identifier)->first();

            if (!$dosen) {
                return response()->json([
                    'message' => 'Kode dosen tidak ditemukan.',
                    'found' => false,
                    'has_account' => false,
                ], 404);
            }

            // Jika sudah punya user, kembalikan info user
            if ($dosen->id_user) {
                $user = User::find($dosen->id_user);
                return response()->json([
                    'found' => true,
                    'has_account' => true,
                    'email_verified' => $user ? (bool) $user->email_verified_at : false,
                    'data' => [
                        'id' => $dosen->id,
                        'nama' => $dosen->nama,
                        'kode_dosen' => $dosen->kode_dosen,
                        'email' => $user ? $user->email : null,
                    ],
                ]);
            }

            return response()->json([
                'found' => true,
                'has_account' => false,
                'data' => [
                    'id' => $dosen->id,
                    'nama' => $dosen->nama,
                    'kode_dosen' => $dosen->kode_dosen,
                    'email' => null, // Dosen tidak punya kolom email
                ],
            ]);
        }
    }

    /**
     * Daftar akun baru dan kirim email verifikasi
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:mahasiswa,dosen'],
            'identifier' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $role = $validated['role'];
        $identifier = $validated['identifier'];

        DB::beginTransaction();
        try {
            if ($role === 'mahasiswa') {
                $mahasiswa = Mahasiswa::where('nim', $identifier)
                    ->whereNull('id_user')
                    ->first();

                if (!$mahasiswa) {
                    return response()->json([
                        'message' => 'NIM tidak ditemukan atau sudah memiliki akun.',
                    ], 404);
                }

                // Update email di tabel mahasiswa jika berbeda
                if ($mahasiswa->email !== $validated['email']) {
                    $mahasiswa->update(['email' => $validated['email']]);
                }

                $username = $mahasiswa->nim;
                if (!$username) {
                    return response()->json([
                        'message' => 'NIM mahasiswa tidak ditemukan, username tidak dapat dibuat.',
                    ], 422);
                }

                if (User::where('username', $username)->exists()) {
                    return response()->json([
                        'message' => 'Username sudah digunakan. Hubungi admin untuk bantuan aktivasi.',
                    ], 422);
                }

                // Buat user
                $user = User::create([
                    'name' => $mahasiswa->nama,
                    'email' => $validated['email'],
                    'username' => $username,
                    'password' => Hash::make($validated['password']),
                    'role' => 'mahasiswa',
                    'status' => 'active',
                    'phone' => $mahasiswa->handphone,
                    'address' => $mahasiswa->alamat,
                    'zip' => $mahasiswa->kode_pos,
                ]);

                // Update id_user di tabel mahasiswa
                $mahasiswa->update(['id_user' => $user->id]);

                $mahasiswaId = $mahasiswa->id;
                $dosenId = null;
            } else {
                $dosen = Dosen::where('kode_dosen', $identifier)
                    ->whereNull('id_user')
                    ->first();

                if (!$dosen) {
                    return response()->json([
                        'message' => 'Kode dosen tidak ditemukan atau sudah memiliki akun.',
                    ], 404);
                }

                $username = $dosen->kode_dosen;
                if (!$username) {
                    return response()->json([
                        'message' => 'Kode dosen tidak ditemukan, username tidak dapat dibuat.',
                    ], 422);
                }

                if (User::where('username', $username)->exists()) {
                    return response()->json([
                        'message' => 'Username sudah digunakan. Hubungi admin untuk bantuan aktivasi.',
                    ], 422);
                }

                // Buat user
                $user = User::create([
                    'name' => $dosen->nama,
                    'email' => $validated['email'],
                    'username' => $username,
                    'password' => Hash::make($validated['password']),
                    'role' => 'dosen',
                    'status' => 'active',
                    'phone' => $dosen->no_hp,
                    'address' => $dosen->alamat,
                    'zip' => $dosen->kode_pos,
                ]);

                // Update id_user di tabel dosen
                $dosen->update(['id_user' => $user->id]);

                $mahasiswaId = null;
                $dosenId = $dosen->id;
            }

            // Generate token untuk verifikasi email
            $token = Str::random(64);
            $expiresAt = Carbon::now()->addHours(24);

            DB::table('email_verifications')->insert([
                'email' => $validated['email'],
                'token' => $token,
                'role' => $role,
                'mahasiswa_id' => $mahasiswaId,
                'dosen_id' => $dosenId,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Kirim email verifikasi
            // Gunakan frontend URL untuk verifikasi (dari env atau default)
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            $verificationUrl = "{$frontendUrl}/verify-email?token={$token}&email=" . urlencode($validated['email']);
            Mail::to($validated['email'])->send(new VerifyEmailActivation($user, $verificationUrl));

            DB::commit();

            return response()->json([
                'message' => 'Akun berhasil dibuat. Silakan cek email Anda untuk verifikasi.',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat akun: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verifikasi email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $verification = DB::table('email_verifications')
            ->where('email', $validated['email'])
            ->where('token', $validated['token'])
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$verification) {
            return response()->json([
                'message' => 'Token verifikasi tidak valid atau sudah kedaluwarsa.',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Update email_verified_at di tabel users
            $user = User::where('email', $validated['email'])->first();
            if ($user) {
                $user->update(['email_verified_at' => now()]);
            }

            // Update verified_at di tabel email_verifications
            DB::table('email_verifications')
                ->where('id', $verification->id)
                ->update(['verified_at' => now()]);

            DB::commit();

            return response()->json([
                'message' => 'Email berhasil diverifikasi. Anda dapat login sekarang.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan saat verifikasi email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Kirim ulang email verifikasi
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:mahasiswa,dosen'],
            'identifier' => ['required', 'string'],
        ]);

        $role = $validated['role'];
        $identifier = $validated['identifier'];

        try {
            if ($role === 'mahasiswa') {
                $mahasiswa = Mahasiswa::where('nim', $identifier)->first();
                if (!$mahasiswa || !$mahasiswa->id_user) {
                    return response()->json([
                        'message' => 'NIM tidak ditemukan atau belum memiliki akun.',
                    ], 404);
                }
                $user = User::find($mahasiswa->id_user);
            } else {
                $dosen = Dosen::where('kode_dosen', $identifier)->first();
                if (!$dosen || !$dosen->id_user) {
                    return response()->json([
                        'message' => 'Kode dosen tidak ditemukan atau belum memiliki akun.',
                    ], 404);
                }
                $user = User::find($dosen->id_user);
            }

            if (!$user) {
                return response()->json([
                    'message' => 'User tidak ditemukan.',
                ], 404);
            }

            // Jika sudah terverifikasi, tidak perlu kirim ulang
            if ($user->email_verified_at) {
                return response()->json([
                    'message' => 'Email Anda sudah terverifikasi. Silakan login.',
                ], 400);
            }

            // Generate token baru untuk verifikasi email
            $token = Str::random(64);
            $expiresAt = Carbon::now()->addHours(24);

            // Hapus token lama yang belum terverifikasi
            DB::table('email_verifications')
                ->where('email', $user->email)
                ->whereNull('verified_at')
                ->delete();

            // Simpan token baru
            DB::table('email_verifications')->insert([
                'email' => $user->email,
                'token' => $token,
                'role' => $role,
                'mahasiswa_id' => $role === 'mahasiswa' ? $mahasiswa->id : null,
                'dosen_id' => $role === 'dosen' ? $dosen->id : null,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Kirim email verifikasi
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            $verificationUrl = "{$frontendUrl}/verify-email?token={$token}&email=" . urlencode($user->email);
            Mail::to($user->email)->send(new VerifyEmailActivation($user, $verificationUrl));

            return response()->json([
                'message' => 'Email verifikasi telah dikirim ulang. Silakan cek inbox email Anda.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat mengirim email verifikasi: ' . $e->getMessage(),
            ], 500);
        }
    }
}
