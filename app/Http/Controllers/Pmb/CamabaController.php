<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Mail\PmbCamabaAdminContactMail;
use App\Models\PmbCamaba;
use App\Models\PmbEmailLog;
use App\Models\PmbPeriode;
use App\Models\PmbUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Daftar akun calon mahasiswa (`pmb_camaba`) untuk admin — terpisah dari filter "pendaftar per periode".
 */
class CamabaController extends Controller
{
    /**
     * Paginasi semua camaba dengan status pendaftaran pada periode terpilih (default: periode aktif).
     *
     * Query `pendaftaran_status`: nilai kolom `pmb_pendaftaran.status`, atau `belum_daftar` untuk camaba
     * tanpa baris pendaftaran pada `id_periode` yang diminta.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $search = $request->get('search');
        $status = $request->get('status');
        $pendaftaranStatusParam = $request->get('pendaftaran_status');
        $pendaftaranStatus = is_string($pendaftaranStatusParam) && $pendaftaranStatusParam !== ''
            ? $pendaftaranStatusParam
            : null;

        $periodeAktifId = PmbPeriode::query()->where('is_active', true)->value('id');

        $idPeriodeParam = $request->get('id_periode');
        $eagerPeriodeId = $periodeAktifId;
        if ($idPeriodeParam !== null && $idPeriodeParam !== '') {
            $id = (int) $idPeriodeParam;
            if ($id > 0) {
                $eagerPeriodeId = $id;
            }
        }

        $with = [];
        $with['pendaftarans'] = static function ($q) use ($eagerPeriodeId): void {
            if ($eagerPeriodeId) {
                $q->where('id_periode', $eagerPeriodeId)
                    ->select(['id', 'id_camaba', 'id_periode', 'status', 'no_pendaftaran', 'tanggal_pendaftaran']);
            } else {
                $q->whereRaw('1 = 0');
            }
        };

        $query = PmbCamaba::query()->with($with);

        if ($pendaftaranStatus !== null && $eagerPeriodeId) {
            if ($pendaftaranStatus === 'belum_daftar') {
                $query->whereDoesntHave('pendaftarans', static function ($q) use ($eagerPeriodeId): void {
                    $q->where('id_periode', $eagerPeriodeId);
                });
            } else {
                $query->whereHas('pendaftarans', static function ($q) use ($eagerPeriodeId, $pendaftaranStatus): void {
                    $q->where('id_periode', $eagerPeriodeId)
                        ->where('status', $pendaftaranStatus);
                });
            }
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('no_hp', 'like', "%{$search}%")
                    ->orWhere('no_wa', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Detail satu camaba (biodata + relasi wilayah, sama cakupan dengan endpoint pendaftar/{id}).
     */
    public function show(PmbCamaba $camaba): JsonResponse
    {
        $periodeAktifId = PmbPeriode::query()->where('is_active', true)->value('id');

        $with = ['user', 'kotaLahir', 'kota', 'kecamatan', 'provinsi', 'negara', 'agama'];
        $with['pendaftarans'] = static function ($q) use ($periodeAktifId): void {
            if ($periodeAktifId) {
                $q->where('id_periode', $periodeAktifId)
                    ->select(['id', 'id_camaba', 'id_periode', 'status', 'no_pendaftaran', 'tanggal_pendaftaran']);
            } else {
                $q->whereRaw('1 = 0');
            }
        };
        $with['emailLogs'] = static function ($q): void {
            $q->orderByDesc('created_at')
                ->limit(100)
                ->select([
                    'id',
                    'id_camaba',
                    'email',
                    'subject',
                    'body',
                    'status',
                    'error',
                    'sent_at',
                    'created_at',
                ]);
        };

        $camaba->load($with);

        return response()->json([
            'success' => true,
            'data' => $camaba,
        ]);
    }

    /**
     * Admin mengirim email ke alamat email camaba (subjek + isi pesan).
     */
    public function kirimEmailKontak(Request $request, PmbCamaba $camaba): JsonResponse
    {
        $user = $request->user();
        if (! $user || ($user->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:15000'],
        ]);

        $email = trim((string) $camaba->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Calon mahasiswa tidak memiliki alamat email yang valid.',
            ], 422);
        }

        try {
            Mail::to($email)->send(new PmbCamabaAdminContactMail(
                namaCamaba: $camaba->nama ?? 'Calon Mahasiswa',
                subjectLine: $validated['subject'],
                bodyPlain: $validated['body'],
                namaAdmin: $user->name ?? 'Panitia PMB',
                emailAdmin: $user->email ?? config('mail.from.address'),
            ));

            $log = PmbEmailLog::query()->create([
                'id_camaba' => $camaba->id,
                'email' => $email,
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $log = PmbEmailLog::query()->create([
                'id_camaba' => $camaba->id,
                'email' => $email,
                'subject' => $validated['subject'],
                'body' => $validated['body'],
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 250),
            ]);

            Log::warning('PMB: gagal kirim email kontak ke camaba', [
                'id_camaba' => $camaba->id,
                'pmb_email_log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengirim email: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil dikirim ke '.$email,
        ]);
    }

    /**
     * Hapus (soft delete) data camaba dan akun `pmb_users` terkait jika peran camaba.
     */
    public function destroy(Request $request, PmbCamaba $camaba): JsonResponse
    {
        $user = $request->user();
        if (! $user || ($user->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $idUser = $camaba->id_user;
            $camaba->delete();

            if ($idUser) {
                $pmbUser = PmbUser::query()->find($idUser);
                if ($pmbUser && ($pmbUser->role ?? '') === 'camaba') {
                    $pmbUser->tokens()->delete();
                    $pmbUser->delete();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('PMB: gagal menghapus camaba', [
                'id_camaba' => $camaba->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data camaba berhasil dihapus.',
        ]);
    }
}
