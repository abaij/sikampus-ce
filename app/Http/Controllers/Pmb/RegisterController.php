<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Mail\PmbPendaftaranSubmittedMail;
use App\Models\PmbBiaya;
use App\Models\PmbCamaba;
use App\Models\PmbDaftarUlang;
use App\Models\PmbDokumen;
use App\Models\PmbFaq;
use App\Models\PmbHasilSeleksi;
use App\Models\PmbPembayaran;
use App\Models\PmbPendaftaran;
use App\Models\PmbPeriode;
use App\Models\PmbPersyaratan;
use App\Models\PmbProdiPilih;
use App\Models\PmbUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /**
     * Step 1: Registrasi awal - simpan nama, email, password ke pmb_camaba dan pmb_users
     */
    public function step1(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:pmb_users,email'],
            'no_hp' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            DB::beginTransaction();

            // Buat user di pmb_users
            $user = PmbUser::create([
                'name' => $validated['nama'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'camaba',
                'status' => 'active',
            ]);

            // Buat camaba di pmb_camaba
            $camaba = PmbCamaba::create([
                'id_user' => $user->id,
                'nama' => $validated['nama'],
                'email' => $validated['email'],
                'no_hp' => $validated['no_hp'],
                'status' => 'active',
            ]);

            DB::commit();

            $token = $user->createToken('pmb_auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registrasi berhasil',
                'data' => [
                    'camaba_id' => $camaba->id,
                    'user_id' => $user->id,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan registrasi: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 2: Pilih prodi, jalur masuk, dan jenis daftar
     */
    public function step2(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_camaba' => ['required', 'exists:pmb_camaba,id'],
            'id_prodi' => ['required', 'array', 'min:1'],
            'id_prodi.*' => ['required', 'exists:prodi,id'],
            'id_jalur_masuk' => ['required', 'exists:jalur_masuk,id'],
            'id_jenis_daftar' => ['required', 'exists:jenis_daftar,id'],
        ]);

        try {
            DB::beginTransaction();

            // Cari periode aktif
            $periode = PmbPeriode::where('is_active', true)->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            // Satukan ID prodi unik (hindari duplikat dari client & bentrok unique index)
            $validated['id_prodi'] = array_values(array_unique(array_map(
                static fn ($id) => (int) $id,
                $validated['id_prodi']
            )));

            // Validasi jumlah prodi yang dipilih tidak melebihi maksimum
            $jumlahProdi = count($validated['id_prodi']);
            if ($jumlahProdi > $periode->pilih_prodi_max) {
                return response()->json([
                    'success' => false,
                    'message' => "Maksimal pilihan prodi adalah {$periode->pilih_prodi_max}",
                ], 422);
            }

            // Cek apakah sudah ada pendaftaran
            $pendaftaran = PmbPendaftaran::where('id_camaba', $validated['id_camaba'])
                ->where('id_periode', $periode->id)
                ->first();

            if (! $pendaftaran) {
                // Buat pendaftaran baru
                $noPendaftaran = $this->generateNoPendaftaran($periode->id);
                $pendaftaran = PmbPendaftaran::create([
                    'id_camaba' => $validated['id_camaba'],
                    'id_periode' => $periode->id,
                    'tanggal_pendaftaran' => now(),
                    'no_pendaftaran' => $noPendaftaran,
                    'status' => 'pending',
                    'id_jalur_masuk' => $validated['id_jalur_masuk'],
                    'id_jenis_daftar' => $validated['id_jenis_daftar'],
                ]);
            } else {
                // Update pendaftaran yang sudah ada
                $pendaftaran->update([
                    'id_jalur_masuk' => $validated['id_jalur_masuk'],
                    'id_jenis_daftar' => $validated['id_jenis_daftar'],
                ]);
            }

            // Hapus pilihan prodi yang lama (hard delete: soft delete membiarkan baris
            // sehingga unique key `unique_prodi_pilih` (id_pendaftaran, id_prodi) memblokir INSERT ulang)
            DB::table('pmb_prodi_pilih')->where('id_pendaftaran', $pendaftaran->id)->delete();

            // Simpan pilihan prodi baru
            $prodiPilihData = [];
            foreach ($validated['id_prodi'] as $idProdi) {
                $prodiPilih = PmbProdiPilih::create([
                    'id_pendaftaran' => $pendaftaran->id,
                    'id_prodi' => $idProdi,
                ]);
                $prodiPilihData[] = $prodiPilih;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pilihan prodi berhasil disimpan',
                'data' => [
                    'pendaftaran' => $pendaftaran->fresh(),
                    'prodi_pilih' => $prodiPilihData,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pilihan prodi: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 3: Update biodata lengkap camaba (alur pendaftaran awal).
     */
    public function step3(Request $request): JsonResponse
    {
        return $this->processBiodataUpdate($request);
    }

    /**
     * Perbarui biodata camaba yang sedang login (dashboard).
     */
    public function updateMyBiodata(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || $user->role !== 'camaba') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $camaba = PmbCamaba::where('id_user', $user->id)->first();

        if (! $camaba) {
            return response()->json([
                'success' => false,
                'message' => 'Data camaba tidak ditemukan',
            ], 404);
        }

        $request->merge(['id_camaba' => $camaba->id]);

        return $this->processBiodataUpdate($request);
    }

    /**
     * Validasi & simpan biodata camaba (step3 pendaftaran atau edit dari dashboard).
     */
    private function processBiodataUpdate(Request $request): JsonResponse
    {
        $idCamaba = $request->input('id_camaba');
        $camabaExisting = $idCamaba ? PmbCamaba::query()->find($idCamaba) : null;

        $rules = [
            'id_camaba' => ['required', 'exists:pmb_camaba,id'],
            'id_kota_lahir' => ['required', 'string'],
            'tanggal_lahir' => ['required', 'date'],
            'jenis_kelamin' => ['required', 'in:Laki-laki,Perempuan'],
            'no_hp' => ['required', 'string'],
            'no_wa' => ['required', 'string'],
            'alamat' => ['required', 'string'],
            'kode_pos' => ['required', 'string'],
            'rt' => ['required', 'string'],
            'rw' => ['required', 'string'],
            'dusun' => ['nullable', 'string'],
            'kelurahan' => ['nullable', 'string'],
            'id_kota' => ['required', 'string'],
            'id_kecamatan' => ['required', 'string'],
            'id_provinsi' => ['required', 'string'],
            'id_negara' => ['required', 'exists:negara,id'],
            'foto' => [
                'nullable',
                'image',
                'max:2048',
                Rule::requiredIf(fn () => ! ($camabaExisting && filled($camabaExisting->foto))),
            ],
            'no_ktp' => ['required', 'string'],
            'no_kk' => ['nullable', 'string'],
            'no_npwp' => ['nullable', 'string'],
            'no_sim' => ['nullable', 'string'],
            'no_kps' => ['nullable', 'string'],
            'nama_ayah' => ['nullable', 'string'],
            'nama_ibu' => ['required', 'string'],
            'nama_wali' => ['nullable', 'string'],
            'no_hp_ayah' => ['nullable', 'string'],
            'no_hp_ibu' => ['nullable', 'string'],
            'no_hp_wali' => ['nullable', 'string'],
            'alamat_ayah' => ['nullable', 'string'],
            'alamat_ibu' => ['nullable', 'string'],
            'alamat_wali' => ['nullable', 'string'],
            'id_agama' => ['nullable', 'exists:agama,id'],
            'status_perkawinan' => ['nullable', 'in:Tidak Kawin,Kawin'],
            'kewarganegaraan' => ['nullable', 'in:WNI,WNA'],
            'asal_sekolah' => ['nullable', 'string'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'npsn' => ['nullable', 'string', 'max:20'],
        ];

        if ($request->has('nama') || $request->has('email')) {
            $rules['nama'] = ['required', 'string', 'max:255'];
            $emailRules = ['required', 'email', 'max:255', Rule::unique('pmb_camaba', 'email')->ignore($idCamaba)];
            if ($camabaExisting?->id_user) {
                $emailRules[] = Rule::unique('pmb_users', 'email')->ignore($camabaExisting->id_user);
            } else {
                $emailRules[] = Rule::unique('pmb_users', 'email');
            }
            $rules['email'] = $emailRules;
        }

        $validated = $request->validate($rules);

        try {
            $camaba = PmbCamaba::findOrFail($validated['id_camaba']);

            // Handle upload foto
            if ($request->hasFile('foto')) {
                if ($camaba->foto) {
                    Storage::disk('public')->delete($camaba->foto);
                }
                $fotoPath = $request->file('foto')->store('pmb/camaba/foto', 'public');
                $validated['foto'] = $fotoPath;
            } else {
                unset($validated['foto']);
            }

            unset($validated['id_camaba']);
            $camaba->update($validated);

            if ($camaba->id_user) {
                $userPayload = [];
                if (array_key_exists('nama', $validated)) {
                    $userPayload['name'] = $validated['nama'];
                }
                if (array_key_exists('email', $validated)) {
                    $userPayload['email'] = $validated['email'];
                }
                if ($userPayload !== []) {
                    PmbUser::where('id', $camaba->id_user)->update($userPayload);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Biodata berhasil diperbarui',
                'data' => $camaba->fresh()->load(['kotaLahir', 'kota', 'kecamatan', 'provinsi', 'negara', 'agama']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui biodata: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 4: Upload persyaratan yang diperlukan
     */
    public function step4(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_camaba' => ['required', 'exists:pmb_camaba,id'],
        ]);

        try {
            // Cari periode aktif
            $periode = PmbPeriode::where('is_active', true)->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            // Cek apakah sudah ada pendaftaran
            $pendaftaran = PmbPendaftaran::where('id_camaba', $validated['id_camaba'])
                ->where('id_periode', $periode->id)
                ->first();

            if (! $pendaftaran) {
                // Buat pendaftaran baru
                $noPendaftaran = $this->generateNoPendaftaran($periode->id);
                $pendaftaran = PmbPendaftaran::create([
                    'id_camaba' => $validated['id_camaba'],
                    'id_periode' => $periode->id,
                    'tanggal_pendaftaran' => now(),
                    'no_pendaftaran' => $noPendaftaran,
                    'status' => 'pending',
                ]);
            }

            // Ambil semua persyaratan untuk periode ini
            $persyaratanList = PmbPersyaratan::where('id_periode', $periode->id)->get();

            foreach ($persyaratanList as $persyaratan) {
                $fileKey = 'persyaratan_'.$persyaratan->id;

                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);

                    // Validasi file
                    $request->validate([
                        $fileKey => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // max 5MB
                    ]);

                    // Simpan file
                    $filePath = $file->store('pmb/dokumen/'.$pendaftaran->id, 'public');

                    // Cek apakah dokumen sudah ada
                    $dokumen = PmbDokumen::where('id_pendaftaran', $pendaftaran->id)
                        ->where('id_persyaratan', $persyaratan->id)
                        ->first();

                    if ($dokumen) {
                        // Hapus file lama
                        if ($dokumen->file) {
                            Storage::disk('public')->delete($dokumen->file);
                        }
                        // Update dokumen
                        $dokumen->update([
                            'file' => $filePath,
                            'tanggal_upload' => now(),
                            'status' => 'pending',
                        ]);
                    } else {
                        // Buat dokumen baru
                        PmbDokumen::create([
                            'id_pendaftaran' => $pendaftaran->id,
                            'id_persyaratan' => $persyaratan->id,
                            'nama' => $persyaratan->nama,
                            'file' => $filePath,
                            'tanggal_upload' => now(),
                            'status' => 'pending',
                        ]);
                    }
                } elseif ($persyaratan->is_wajib) {
                    $existingDokumen = PmbDokumen::where('id_pendaftaran', $pendaftaran->id)
                        ->where('id_persyaratan', $persyaratan->id)
                        ->first();

                    if (! $existingDokumen || ! filled($existingDokumen->file)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Persyaratan wajib '{$persyaratan->nama}' belum diupload",
                        ], 422);
                    }
                }
            }

            $dokumenLengkap = PmbDokumen::where('id_pendaftaran', $pendaftaran->id)
                ->with('persyaratan')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Persyaratan berhasil disimpan',
                'data' => [
                    'pendaftaran' => $pendaftaran->fresh(),
                    'dokumen' => $dokumenLengkap,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload persyaratan: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 5: Upload bukti pembayaran
     */
    public function step5(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_camaba' => ['required', 'exists:pmb_camaba,id'],
            'keterangan' => ['nullable', 'string'],
        ]);

        // Normalize keterangan: jika empty string, ubah menjadi null
        if (isset($validated['keterangan']) && trim((string) $validated['keterangan']) === '') {
            $validated['keterangan'] = null;
        }

        try {
            // Cari periode aktif
            $periode = PmbPeriode::where('is_active', true)->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            // Cari pendaftaran
            $pendaftaran = PmbPendaftaran::where('id_camaba', $validated['id_camaba'])
                ->where('id_periode', $periode->id)
                ->first();

            if (! $pendaftaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pendaftaran tidak ditemukan',
                ], 404);
            }

            $pendaftaran->loadMissing('jalurMasuk');

            // Ambil semua biaya untuk periode aktif
            $biayaList = PmbBiaya::where('id_periode', $periode->id)->get();

            /** Bebas bukti / penyelesaian tanpa pembayaran: tidak ada rincian biaya, atau jalur masuk bebas biaya. */
            $waivesPayment = $biayaList->isEmpty()
                || (bool) ($pendaftaran->jalurMasuk?->is_free_of_charge ?? false);

            $hadBukti = PmbPembayaran::where('id_pendaftaran', $pendaftaran->id)
                ->whereNotNull('file')
                ->exists();

            $request->validate([
                'file' => [
                    'nullable',
                    'file',
                    'mimes:pdf,jpg,jpeg,png',
                    'max:5120',
                    Rule::requiredIf(fn () => ! $waivesPayment && ! $hadBukti),
                ],
            ]);

            if ($request->hasFile('file') && $biayaList->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada rincian biaya untuk melampirkan bukti pembayaran.',
                ], 422);
            }

            // Hitung total biaya
            $totalBiaya = $biayaList->sum('jumlah');
            $wasSubmitted = $pendaftaran->status === 'submitted';
            $noKuitansi = '';

            if ($request->hasFile('file')) {
                $noKuitansi = $this->generateNoKuitansi($pendaftaran->id);
                $filePath = $request->file('file')->store('pmb/pembayaran/'.$pendaftaran->id, 'public');

                foreach ($biayaList as $biaya) {
                    $pembayaran = PmbPembayaran::where('id_pendaftaran', $pendaftaran->id)
                        ->where('id_biaya', $biaya->id)
                        ->first();

                    if ($pembayaran) {
                        if ($pembayaran->file) {
                            Storage::disk('public')->delete($pembayaran->file);
                        }
                        $pembayaran->update([
                            'no_kuitansi' => $noKuitansi,
                            'jumlah' => $biaya->jumlah,
                            'keterangan' => $validated['keterangan'] ?? $pembayaran->keterangan,
                            'file' => $filePath,
                            'status' => 'pending',
                        ]);
                    } else {
                        PmbPembayaran::create([
                            'id_pendaftaran' => $pendaftaran->id,
                            'id_biaya' => $biaya->id,
                            'no_kuitansi' => $noKuitansi,
                            'jumlah' => $biaya->jumlah,
                            'keterangan' => $validated['keterangan'] ?? null,
                            'file' => $filePath,
                            'status' => 'pending',
                        ]);
                    }
                }
            } else {
                PmbPembayaran::where('id_pendaftaran', $pendaftaran->id)
                    ->update(['keterangan' => $validated['keterangan']]);

                $firstBayar = PmbPembayaran::where('id_pendaftaran', $pendaftaran->id)
                    ->whereNotNull('file')
                    ->first();
                $noKuitansi = $firstBayar?->no_kuitansi ?? $this->generateNoKuitansi($pendaftaran->id);
            }

            $pembayaranList = PmbPembayaran::where('id_pendaftaran', $pendaftaran->id)
                ->with('biaya')
                ->get()
                ->all();

            if (! $wasSubmitted) {
                $pendaftaran->update(['status' => 'submitted']);
            }

            $camaba = PmbCamaba::find($validated['id_camaba']);
            if (! $wasSubmitted && $camaba && filter_var($camaba->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    $pendaftaranForMail = $pendaftaran->fresh([
                        'periode',
                        'jalurMasuk',
                        'jenisDaftar',
                    ]);

                    if ($pendaftaranForMail) {
                        $pendaftaranForMail->load(['prodiPilih.prodi.jenjang']);

                        $tanggalPendaftaranStr = $pendaftaranForMail->tanggal_pendaftaran
                            ? $pendaftaranForMail->tanggal_pendaftaran->format('d/m/Y')
                            : null;

                        $prodiPilihan = [];
                        foreach ($pendaftaranForMail->prodiPilih as $pp) {
                            $pr = $pp->prodi;
                            $jenjang = $pr?->jenjang;
                            $prodiPilihan[] = [
                                'nama' => $pr->nama ?? '-',
                                'kode' => $pr->kode ?? '',
                                'jenjang' => $jenjang ? (string) ($jenjang->nama ?? $jenjang->kode ?? '') : '',
                            ];
                        }

                        $rincianBiaya = $biayaList->map(static function (PmbBiaya $b) {
                            return [
                                'nama' => $b->nama,
                                'jumlah' => (float) ($b->jumlah ?? 0),
                            ];
                        })->all();

                        Mail::to($camaba->email)->send(new PmbPendaftaranSubmittedMail(
                            namaCamaba: $camaba->nama ?? 'Calon Mahasiswa',
                            noPendaftaran: $pendaftaranForMail->no_pendaftaran ?? '-',
                            tanggalPendaftaran: $tanggalPendaftaranStr,
                            namaPeriode: $pendaftaranForMail->periode?->nama ?? $periode->nama,
                            jalurMasuk: $pendaftaranForMail->jalurMasuk?->nama ?? null,
                            jenisDaftar: $pendaftaranForMail->jenisDaftar?->nama ?? null,
                            prodiPilihan: $prodiPilihan,
                            noKuitansi: $noKuitansi,
                            rincianBiaya: $rincianBiaya,
                            totalBiaya: (float) $totalBiaya,
                        ));
                    }
                } catch (\Throwable $e) {
                    Log::warning('PMB: gagal mengirim email konfirmasi pendaftaran', [
                        'id_camaba' => $validated['id_camaba'],
                        'id_pendaftaran' => $pendaftaran->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $successMessage = $request->hasFile('file')
                ? 'Bukti pembayaran berhasil diupload'
                : 'Data pembayaran berhasil diperbarui';
            if ($waivesPayment && ! $request->hasFile('file')) {
                $successMessage = 'Pendaftaran berhasil diselesaikan.';
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => [
                    'pendaftaran' => $pendaftaran->fresh(),
                    'pembayaran' => $pembayaranList,
                    'total_biaya' => $totalBiaya,
                    'no_kuitansi' => $noKuitansi,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupload bukti pembayaran: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get periode aktif dan persyaratan/biaya yang diperlukan
     */
    public function getPeriodeAktif(): JsonResponse
    {
        try {
            $periode = PmbPeriode::where('is_active', true)
                ->with(['persyaratan', 'biaya'])
                ->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            // Ambil relasi persyaratan dan biaya (urut untuk tampilan publik)
            $persyaratan = PmbPersyaratan::where('id_periode', $periode->id)
                ->orderBy('is_wajib', 'desc')
                ->orderBy('nama')
                ->get();
            $biaya = PmbBiaya::where('id_periode', $periode->id)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => $periode,
                    'persyaratan' => $persyaratan,
                    'biaya' => $biaya,
                    'pilih_prodi_max' => $periode->pilih_prodi_max,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data periode: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * FAQ publik untuk periode PMB yang sedang aktif.
     */
    public function getFaqPeriodeAktif(): JsonResponse
    {
        try {
            $periode = PmbPeriode::where('is_active', true)->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            $faqs = PmbFaq::query()
                ->where('id_periode', $periode->id)
                ->orderBy('urutan')
                ->orderBy('id')
                ->get(['id', 'pertanyaan', 'jawaban', 'urutan']);

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => [
                        'id' => $periode->id,
                        'nama' => $periode->nama,
                        'kode' => $periode->kode,
                    ],
                    'faqs' => $faqs,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil FAQ: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get data pendaftaran yang sudah ada untuk autofill form
     */
    public function getPendaftaranData(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user || $user->role !== 'camaba') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Cari camaba (wilayah & agama untuk tampilan biodata dashboard)
            $camaba = PmbCamaba::where('id_user', $user->id)
                ->with(['kotaLahir', 'kota', 'kecamatan', 'provinsi', 'negara', 'agama'])
                ->first();

            if (! $camaba) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data camaba tidak ditemukan',
                ], 404);
            }

            // Cari periode aktif
            $periode = PmbPeriode::where('is_active', true)->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            // Cari pendaftaran dengan relasi
            $pendaftaran = PmbPendaftaran::where('id_camaba', $camaba->id)
                ->where('id_periode', $periode->id)
                ->with([
                    'jalurMasuk',
                    'jenisDaftar',
                    'hasilSeleksi:id,id_pendaftaran,status',
                ])
                ->first();

            // Ambil prodi pilihan dengan relasi prodi
            $prodiPilih = [];
            $daftarUlang = null;
            if ($pendaftaran) {
                $prodiPilih = PmbProdiPilih::where('id_pendaftaran', $pendaftaran->id)
                    ->with('prodi.jenjang')
                    ->get();

                $daftarUlang = PmbDaftarUlang::where('id_pendaftaran', $pendaftaran->id)
                    ->with(['prodi:id,nama,kode'])
                    ->first();
            }

            // Ambil dokumen yang sudah diupload
            $dokumen = [];
            if ($pendaftaran) {
                $dokumen = PmbDokumen::where('id_pendaftaran', $pendaftaran->id)
                    ->with('persyaratan')
                    ->get();
            }

            // Ambil data pembayaran
            $pembayaran = [];
            if ($pendaftaran) {
                $pembayaran = PmbPembayaran::where('id_pendaftaran', $pendaftaran->id)
                    ->with('biaya')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'camaba' => $camaba,
                    'pendaftaran' => $pendaftaran,
                    'prodi_pilih' => $prodiPilih,
                    'dokumen' => $dokumen,
                    'pembayaran' => $pembayaran,
                    'daftar_ulang' => $daftarUlang,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data pendaftaran: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload bukti pembayaran daftar ulang + pilihan prodi (hanya jika lulus seleksi).
     * Prodi harus salah satu dari pilihan prodi saat mendaftar. File boleh kosong jika bukti sudah ada (perbarui prodi saja).
     */
    public function uploadDaftarUlangBukti(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user || $user->role !== 'camaba') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $camaba = PmbCamaba::where('id_user', $user->id)->first();

            if (! $camaba) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data camaba tidak ditemukan',
                ], 404);
            }

            $periode = PmbPeriode::where('is_active', true)->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            $pendaftaran = PmbPendaftaran::where('id_camaba', $camaba->id)
                ->where('id_periode', $periode->id)
                ->first();

            if (! $pendaftaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pendaftaran tidak ditemukan',
                ], 404);
            }

            $hasil = PmbHasilSeleksi::where('id_pendaftaran', $pendaftaran->id)->first();

            if (! $hasil || $hasil->status !== 'lulus') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unggah bukti daftar ulang hanya untuk peserta yang dinyatakan lulus seleksi.',
                ], 422);
            }

            $allowedProdiIds = PmbProdiPilih::where('id_pendaftaran', $pendaftaran->id)
                ->pluck('id_prodi')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if ($allowedProdiIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data program studi pilihan tidak ditemukan.',
                ], 422);
            }

            $request->validate([
                'id_prodi' => ['required', 'integer', Rule::in($allowedProdiIds)],
                'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            $existing = PmbDaftarUlang::where('id_pendaftaran', $pendaftaran->id)->first();

            $existing?->loadMissing('pendaftaran.camaba');
            $herregistrasiSelesai = $existing && (
                ($existing->pendaftaran?->camaba?->status_herregistrasi ?? null) === 'herregistrasi'
                || ($existing->getRawOriginal('status') ?? '') === 'acc'
            );
            if ($herregistrasiSelesai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Daftar ulang sudah disetujui dan tidak dapat diubah.',
                ], 422);
            }

            if (! $request->hasFile('file') && ! ($existing && $existing->file_bukti_bayar)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File bukti pembayaran wajib diunggah.',
                ], 422);
            }

            $filePath = $existing?->file_bukti_bayar;

            if ($request->hasFile('file')) {
                if ($existing && $existing->file_bukti_bayar) {
                    Storage::disk('public')->delete($existing->file_bukti_bayar);
                }
                $filePath = $request->file('file')->store('pmb/daftar-ulang/'.$pendaftaran->id, 'public');
            }

            $daftarUlang = PmbDaftarUlang::updateOrCreate(
                ['id_pendaftaran' => $pendaftaran->id],
                [
                    'id_prodi' => (int) $request->input('id_prodi'),
                    'tanggal_daftar_ulang' => now()->toDateString(),
                    'status' => 'pending',
                    'file_bukti_bayar' => $filePath,
                ]
            );

            $pendaftaran->loadMissing('camaba');
            $pendaftaran->camaba?->update(['status_herregistrasi' => 'pending']);

            $daftarUlang->load(['prodi:id,nama,kode']);

            return response()->json([
                'success' => true,
                'message' => $request->hasFile('file')
                    ? 'Bukti pembayaran daftar ulang berhasil diunggah.'
                    : 'Pilihan program studi daftar ulang diperbarui.',
                'data' => [
                    'daftar_ulang' => $daftarUlang,
                ],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah bukti: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload ulang dokumen persyaratan (camaba dashboard).
     * Hanya jika status belum verified/acc; file lama dihapus dari storage.
     */
    public function reuploadDokumen(Request $request, PmbDokumen $dokumen): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user || $user->role !== 'camaba') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            $camaba = PmbCamaba::where('id_user', $user->id)->first();

            if (! $camaba) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data camaba tidak ditemukan',
                ], 404);
            }

            $periode = PmbPeriode::where('is_active', true)->first();

            if (! $periode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada periode pendaftaran yang aktif',
                ], 404);
            }

            $pendaftaran = PmbPendaftaran::where('id_camaba', $camaba->id)
                ->where('id_periode', $periode->id)
                ->first();

            if (! $pendaftaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pendaftaran tidak ditemukan',
                ], 404);
            }

            if ((int) $dokumen->id_pendaftaran !== (int) $pendaftaran->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak termasuk pendaftaran Anda.',
                ], 403);
            }

            $status = strtolower((string) ($dokumen->status ?? 'pending'));

            if (in_array($status, ['verified', 'acc'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen sudah disetujui (ACC) dan tidak dapat diunggah ulang.',
                ], 422);
            }

            $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ]);

            if ($dokumen->file) {
                Storage::disk('public')->delete($dokumen->file);
            }

            $filePath = $request->file('file')->store('pmb/dokumen/'.$pendaftaran->id, 'public');

            $dokumen->update([
                'file' => $filePath,
                'tanggal_upload' => now(),
                'status' => 'pending',
                'keterangan' => null,
            ]);

            $dokumen->load(['persyaratan']);

            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil diunggah ulang. Menunggu verifikasi panitia.',
                'data' => $dokumen,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah ulang dokumen: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate nomor pendaftaran
     */
    private function generateNoPendaftaran(int $idPeriode): string
    {
        $periode = PmbPeriode::find($idPeriode);
        $kode = $periode->kode ?? 'PMB';
        $year = date('Y');
        $month = date('m');

        // Hitung jumlah pendaftaran di periode ini
        $count = PmbPendaftaran::where('id_periode', $idPeriode)->count() + 1;

        return sprintf('%s%s%s%04d', $kode, $year, $month, $count);
    }

    /**
     * Generate nomor kuitansi
     */
    private function generateNoKuitansi(int $idPendaftaran): string
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        // Hitung jumlah pembayaran hari ini
        $count = PmbPembayaran::whereDate('created_at', today())->count() + 1;

        return sprintf('KWT%s%s%s%04d', $year, $month, $day, $count);
    }
}
