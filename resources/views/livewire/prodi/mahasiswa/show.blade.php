@php
    $mahasiswa = $this->mahasiswa;
    $dosenWali = $this->dosenWali;

    $statusBadgeClass = function (?string $nama) {
        $nama = mb_strtolower(trim((string) $nama));

        return match (true) {
            $nama === '' => 'bg-neutral-100 text-neutral-600',
            str_contains($nama, 'aktif') => 'bg-emerald-50 text-emerald-700',
            str_contains($nama, 'cuti') => 'bg-amber-50 text-amber-700',
            str_contains($nama, 'lulus') => 'bg-blue-50 text-blue-700',
            str_contains($nama, 'dropout') => 'bg-rose-50 text-rose-700',
            default => 'bg-neutral-100 text-neutral-600',
        };
    };

    $tagihanStatusBadge = function (?string $status) {
        return match ($status) {
            'paid' => ['bg-emerald-100 text-emerald-700', 'Lunas'],
            'expired' => ['bg-rose-100 text-rose-700', 'Kadaluarsa'],
            default => ['bg-amber-100 text-amber-700', 'Belum Lunas'],
        };
    };
@endphp

@section('title', 'Detail Mahasiswa — ' . config('app.name'))
@section('header_title', 'Detail Mahasiswa')
@section('header_subtitle', $mahasiswa->nama)

@section('page_actions')
    <a
        href="{{ route('prodi.mahasiswa') }}"
        class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-border transition hover:bg-neutral-50"
    >
        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
        Kembali
    </a>
@endsection

<div>
    {{-- Tab Navigation --}}
    <div class="mb-6 border-b border-neutral-200">
        <nav class="-mb-px flex flex-wrap gap-6">
            @foreach ([['key' => 'biodata', 'label' => 'Biodata'], ['key' => 'nilai', 'label' => 'Nilai'], ['key' => 'tagihan', 'label' => 'Tagihan']] as $tabItem)
                <button
                    type="button"
                    wire:click="setTab('{{ $tabItem['key'] }}')"
                    class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-semibold transition {{ $tab === $tabItem['key'] ? 'border-neutral-900 text-neutral-900' : 'border-transparent text-neutral-500 hover:border-neutral-300 hover:text-neutral-700' }}"
                >
                    {{ $tabItem['label'] }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab: Biodata --}}
    @if ($tab === 'biodata')
        <div class="space-y-6">
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-900">Identitas Diri</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Nama Lengkap</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->nama }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">NIM</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->nim ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Jenis Kelamin</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">
                            {{ $mahasiswa->jenis_kelamin === 'L' ? 'Laki-laki' : ($mahasiswa->jenis_kelamin === 'P' ? 'Perempuan' : '—') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Tempat, Tanggal Lahir</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">
                            {{ $mahasiswa->id_tempat_lahir ?? '—' }}{{ $mahasiswa->tanggal_lahir ? ', '.$mahasiswa->tanggal_lahir->translatedFormat('d F Y') : '' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">No. KTP</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->no_ktp ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Email</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">NIS / NISN</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->nis ?? '—' }}{{ $mahasiswa->nisn ? ' / '.$mahasiswa->nisn : '' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Sekolah Asal</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->sekolah_asal ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-900">Kontak</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">No. HP</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->handphone ?? $mahasiswa->no_wa ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">No. WA</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->no_wa ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-900">Alamat</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Alamat</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->alamat ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">RT / RW</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->rt ?? '—' }} / {{ $mahasiswa->rw ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Dusun / Kelurahan</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->dusun ?? '—' }} / {{ $mahasiswa->kelurahan ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Kode Pos</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->kode_pos ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Kota</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->kota->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Provinsi</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->provinsi->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Negara</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->negara->nama ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-900">Data Akademik</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Program Studi</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">
                            {{ $mahasiswa->prodi->nama ?? '—' }}
                            @if ($mahasiswa->prodi?->kode)
                                <span class="text-xs text-neutral-400">({{ $mahasiswa->prodi->kode }})</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Semester Masuk</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->semester_masuk->kode ?? $mahasiswa->semester_masuk->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Kelompok Kelas</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->kelompok_kelas->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Status Akademik</p>
                        <p class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadgeClass($mahasiswa->status_akademik->nama ?? null) }}">
                                {{ $mahasiswa->status_akademik->nama ?? '—' }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Jalur Masuk</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->jalur_masuk->nama ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-500">Jenis Daftar</p>
                        <p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->jenis_daftar->nama ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-sky-100 bg-sky-50/60 p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-sky-800">Dosen Wali</h3>
                @if ($dosenWali)
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">Nama</p>
                            <p class="mt-1 text-sm font-medium text-neutral-900">{{ $dosenWali['nama'] ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-sky-700">NIDN</p>
                            <p class="mt-1 text-sm font-medium text-neutral-900">{{ $dosenWali['nidn'] ?? '—' }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-neutral-600">Belum ada dosen wali yang ditetapkan.</p>
                @endif
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-border">
                <h3 class="mb-4 text-sm font-semibold text-neutral-900">Data Orang Tua / Wali</h3>

                <div class="mb-6">
                    <h4 class="mb-3 text-xs font-semibold uppercase text-neutral-500">Ayah</h4>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><p class="text-xs text-neutral-500">Nama</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->ayah ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">NIK</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->nik_ayah ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">Pendidikan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->pendidikan_ayah->nama ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">Pekerjaan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->pekerjaan_ayah->nama ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">Penghasilan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->penghasilan_ayah->nama ?? '—' }}</p></div>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="mb-3 text-xs font-semibold uppercase text-neutral-500">Ibu</h4>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><p class="text-xs text-neutral-500">Nama</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->ibu ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">NIK</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->nik_ibu ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">Pendidikan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->pendidikan_ibu->nama ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">Pekerjaan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->pekerjaan_ibu->nama ?? '—' }}</p></div>
                        <div><p class="text-xs text-neutral-500">Penghasilan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->penghasilan_ibu->nama ?? '—' }}</p></div>
                    </div>
                </div>

                @if ($mahasiswa->wali)
                    <div>
                        <h4 class="mb-3 text-xs font-semibold uppercase text-neutral-500">Wali</h4>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><p class="text-xs text-neutral-500">Nama</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->wali ?? '—' }}</p></div>
                            <div><p class="text-xs text-neutral-500">NIK</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->nik_wali ?? '—' }}</p></div>
                            <div><p class="text-xs text-neutral-500">Pendidikan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->pendidikan_wali->nama ?? '—' }}</p></div>
                            <div><p class="text-xs text-neutral-500">Pekerjaan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->pekerjaan_wali->nama ?? '—' }}</p></div>
                            <div><p class="text-xs text-neutral-500">Penghasilan</p><p class="mt-1 text-sm font-medium text-neutral-900">{{ $mahasiswa->penghasilan_wali->nama ?? '—' }}</p></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Tab: Nilai --}}
    @if ($tab === 'nilai')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            @php $nilaiBySemester = $this->nilaiBySemester; @endphp

            @if ($nilaiBySemester->isEmpty())
                <p class="py-12 text-center text-sm text-neutral-500">Belum ada data nilai. Nilai akan tampil setelah KRS disetujui dan nilai diinput.</p>
            @else
                <div class="space-y-8">
                    @foreach ($nilaiBySemester as $group)
                        <div>
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 pb-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-neutral-900">{{ $group['semester']->nama }}</h4>
                                    <p class="mt-0.5 text-xs text-neutral-500">Kode: {{ $group['semester']->kode }}</p>
                                </div>
                                <div class="flex items-center gap-6 text-sm">
                                    <div class="text-right">
                                        <p class="text-neutral-500">Total SKS</p>
                                        <p class="font-semibold text-neutral-900">{{ $group['total_sks'] }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-neutral-500">SKS dengan Nilai</p>
                                        <p class="font-semibold text-neutral-900">{{ $group['total_sks_dengan_nilai'] }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-neutral-500">IP Semester</p>
                                        <p class="text-lg font-semibold text-neutral-900">{{ number_format($group['ip'], 2) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-xl shadow-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                        <tr>
                                            <th class="px-4 py-3">Kode</th>
                                            <th class="px-4 py-3">Mata Kuliah</th>
                                            <th class="px-4 py-3">Kelas</th>
                                            <th class="px-4 py-3">Dosen</th>
                                            <th class="px-4 py-3 text-center">SKS</th>
                                            <th class="px-4 py-3 text-center">Angka Mutu</th>
                                            <th class="px-4 py-3 text-center">Huruf Mutu</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-100">
                                        @foreach ($group['nilai_list'] as $row)
                                            @php $matkul = $row['krs']->kelas->kurikulumMatkul->matkul ?? null; $nilai = $row['nilai']; @endphp
                                            <tr wire:key="nilai-{{ $row['krs']->id }}">
                                                <td class="px-4 py-3 font-mono text-xs text-neutral-900">{{ $matkul->kode ?? '—' }}</td>
                                                <td class="px-4 py-3 text-neutral-900">{{ $matkul->nama ?? '—' }}</td>
                                                <td class="px-4 py-3 text-neutral-600">{{ $row['krs']->kelas->nama ?? '—' }}</td>
                                                <td class="px-4 py-3 text-neutral-600">{{ $row['krs']->kelas->dosenPic->nama ?? '—' }}</td>
                                                <td class="px-4 py-3 text-center font-semibold text-neutral-900">{{ $row['sks'] }}</td>
                                                <td class="px-4 py-3 text-center font-semibold text-neutral-900">{{ $nilai?->angka_mutu !== null ? number_format((float) $nilai->angka_mutu, 2) : '—' }}</td>
                                                <td class="px-4 py-3 text-center font-semibold text-neutral-900">{{ $nilai->huruf_mutu ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Tab: Tagihan --}}
    @if ($tab === 'tagihan')
        <div class="rounded-2xl bg-white p-6 shadow-border">
            @php $tagihanBySemester = $this->tagihanBySemester; @endphp

            @if ($tagihanBySemester->isEmpty())
                <p class="py-12 text-center text-sm text-neutral-500">Belum ada data tagihan.</p>
            @else
                <div class="space-y-8">
                    @foreach ($tagihanBySemester as $group)
                        <div>
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 pb-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-neutral-900">{{ $group['semester']->nama }}</h4>
                                    <p class="mt-0.5 text-xs text-neutral-500">Kode: {{ $group['semester']->kode }}</p>
                                </div>
                                <p class="text-sm text-neutral-600">
                                    Total tagihan: <span class="font-semibold text-neutral-900">Rp{{ number_format($group['total_tagihan_semester'], 0, ',', '.') }}</span>
                                    &middot; Pembayaran: <span class="font-semibold text-emerald-700">Rp{{ number_format($group['total_pembayaran_semester'], 0, ',', '.') }}</span>
                                </p>
                            </div>

                            <div class="overflow-x-auto rounded-xl shadow-border">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                        <tr>
                                            <th class="px-4 py-3">No. Tagihan</th>
                                            <th class="px-4 py-3">Tanggal</th>
                                            <th class="px-4 py-3 text-right">Total Tagihan</th>
                                            <th class="px-4 py-3 text-right">Pembayaran</th>
                                            <th class="px-4 py-3 text-right">Sisa</th>
                                            <th class="px-4 py-3 text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-100">
                                        @foreach ($group['tagihan_list'] as $row)
                                            @php [$badgeClass, $badgeLabel] = $tagihanStatusBadge($row['tagihan']->status); @endphp
                                            <tr wire:key="tagihan-{{ $row['tagihan']->id }}">
                                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $row['tagihan']->no_tagihan }}</td>
                                                <td class="px-4 py-3 text-neutral-600">{{ $row['tagihan']->tanggal_tagihan?->translatedFormat('d F Y') ?? '—' }}</td>
                                                <td class="px-4 py-3 text-right tabular-nums text-neutral-700">Rp{{ number_format((float) $row['tagihan']->total, 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-right tabular-nums text-emerald-700">Rp{{ number_format($row['total_pembayaran'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-right tabular-nums text-neutral-700">Rp{{ number_format($row['sisa_tagihan'], 0, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">{{ $badgeLabel }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
