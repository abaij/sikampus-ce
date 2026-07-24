<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\PmbCamaba;
use App\Models\PmbPendaftaran;
use App\Models\PmbPeriode;
use App\Models\PmbUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login untuk camaba (calon mahasiswa baru).
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = PmbUser::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak valid.'],
            ]);
        }

        // Cek status user
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda tidak aktif.'],
            ]);
        }

        $token = $user->createToken('pmb_auth_token')->plainTextToken;

        // Cari data camaba
        $camaba = PmbCamaba::where('id_user', $user->id)->first();

        // Cari pendaftaran terbaru jika ada
        $pendaftaran = null;
        $pendaftaranStatus = null;
        if ($camaba) {
            $periodeAktif = PmbPeriode::where('is_active', true)->first();
            if ($periodeAktif) {
                $pendaftaran = PmbPendaftaran::where('id_camaba', $camaba->id)
                    ->where('id_periode', $periodeAktif->id)
                    ->first();
                $pendaftaranStatus = $pendaftaran ? $pendaftaran->status : null;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'camaba' => $camaba ? [
                    'id' => $camaba->id,
                    'nama' => $camaba->nama,
                    'email' => $camaba->email,
                ] : null,
                'pendaftaran' => $pendaftaran ? [
                    'id' => $pendaftaran->id,
                    'status' => $pendaftaran->status,
                    'no_pendaftaran' => $pendaftaran->no_pendaftaran,
                ] : null,
                'pendaftaran_status' => $pendaftaranStatus,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Login khusus untuk admin PMB.
     */
    public function adminLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = PmbUser::where('email', $credentials['email'])
            ->where('role', 'admin')
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password tidak valid.'],
            ]);
        }

        // Cek status user
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Akun admin tidak aktif.'],
            ]);
        }

        $token = $user->createToken('pmb_admin_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login admin berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Registrasi camaba baru.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:pmb_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = PmbUser::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'camaba',
            'status' => 'active',
        ]);

        $token = $user->createToken('pmb_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Profil pengguna admin yang sedang login (PMB).
     */
    public function adminMe(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PmbUser || ($user->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Perbarui nama dan email admin yang sedang login.
     */
    public function adminUpdateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PmbUser || ($user->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('pmb_users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Ubah password admin yang sedang login (wajib password saat ini).
     */
    public function adminUpdatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PmbUser || ($user->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah',
        ]);
    }
}
