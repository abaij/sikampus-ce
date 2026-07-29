@section('title', 'Detail Jadwal — ' . config('app.name'))
@section('header_title', 'Detail Jadwal')

@section('breadcrumb')
    <div class="flex flex-wrap items-center gap-3 text-sm">
        <a
            href="{{ route('dosen.jadwal.show', ['kelasId' => $kelasId, 'id_semester' => $idSemester !== '' ? $idSemester : null]) }}"
            class="inline-flex items-center gap-2 font-medium text-sky-600 hover:text-sky-700"
        >
            <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
            Jadwal per kelas
        </a>
        <span class="text-neutral-300">|</span>
        <a href="{{ route('dosen.jadwal') }}" class="font-medium text-neutral-600 hover:text-sky-700">
            Semua jadwal mengajar
        </a>
    </div>
@endsection

@php
    $jadwal = $this->jadwal;
    $kelas = $jadwal->kelas;
    $km = $kelas?->kurikulumMatkul;
    $hariLabel = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu' => 'Minggu'];
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('status_bahasan'))
        <div class="flex gap-3 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <i data-lucide="check-circle" class="h-5 w-5 shrink-0 text-emerald-600" aria-hidden="true"></i>
            <span>{{ session('status_bahasan') }}</span>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white shadow-border">
        <div class="border-b border-neutral-100 bg-neutral-50 px-5 py-4">
            <span class="rounded bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">{{ $km?->kodeMatkulLabel() ?? '—' }}</span>
            <h2 class="mt-2 text-lg font-semibold text-neutral-900">{{ $km?->namaMatkulLabel() ?? '—' }}</h2>
            <p class="mt-1 text-sm text-neutral-500">
                Kelas {{ $kelas?->kode ?? '-' }}
                @if ($kelas?->semester)
                    · {{ $kelas->semester->nama }} ({{ $kelas->semester->kode }})
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-4 p-5 text-sm text-neutral-600">
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="graduation-cap" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                {{ $kelas?->prodi?->nama ?? '-' }}{{ $kelas?->prodi?->jenjang?->nama ? ' ('.$kelas->prodi->jenjang->nama.')' : '' }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="users" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                Kelompok: {{ $kelas?->kelompokKelas?->nama ?? '-' }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <i data-lucide="user" class="h-4 w-4 text-neutral-400" aria-hidden="true"></i>
                Dosen: {{ $jadwal->dosen->map(fn ($jd) => $jd->dosen?->nama)->filter()->implode(', ') ?: '-' }}
            </span>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-neutral-900">Informasi Jadwal</h3>
            @unless ($editing)
                <button
                    type="button"
                    wire:click="startEdit"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium text-neutral-700 shadow-border hover:bg-neutral-50"
                >
                    <i data-lucide="pencil" class="h-3.5 w-3.5 text-neutral-400" aria-hidden="true"></i>
                    Edit
                </button>
            @endunless
        </div>

        @if (! $editing)
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-neutral-500">Hari</p>
                    <p class="mt-1 text-sm text-neutral-900">{{ $hariLabel[strtolower((string) $jadwal->hari)] ?? $jadwal->hari ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">Tanggal (jika hanya sekali pertemuan)</p>
                    <p class="mt-1 text-sm text-neutral-900">{{ $jadwal->tanggal?->translatedFormat('d F Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">Jam</p>
                    <p class="mt-1 text-sm text-neutral-900">{{ substr((string) $jadwal->jam_mulai, 0, 5) }} – {{ substr((string) $jadwal->jam_selesai, 0, 5) }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">Ruangan</p>
                    <p class="mt-1 text-sm text-neutral-900">{{ $jadwal->ruangan?->nama ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-neutral-500">Jenis Kuliah</p>
                    <p class="mt-1 text-sm text-neutral-900">{{ $jadwal->jenisKuliah?->nama ?? '—' }}</p>
                </div>
            </div>
        @else
            <form wire:submit="saveJadwal" class="space-y-5">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Hari</label>
                        <x-searchable-select
                            model="hari"
                            :options="collect(\App\Models\Jadwal::HARI)->mapWithKeys(fn ($h) => [$h => ucfirst($h)])->all()"
                            placeholder="— Tidak berulang mingguan —"
                        />
                        @error('hari') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Tanggal (jika hanya sekali pertemuan)</label>
                        <input type="date" wire:model="tanggal" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('tanggal') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('tanggal') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ruangan</label>
                        <x-searchable-select model="id_ruangan" :options="$this->ruanganOptions" placeholder="— Pilih ruangan —" />
                        @error('id_ruangan') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenis Kuliah</label>
                        <x-searchable-select model="id_jenis_kuliah" :options="$this->jenisKuliahOptions" placeholder="— Pilih jenis kuliah —" />
                        @error('id_jenis_kuliah') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-neutral-200 pt-4">
                    <button type="button" wire:click="cancelEdit" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 shadow-border hover:bg-neutral-50">
                        Batal
                    </button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                        <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                        Simpan
                    </button>
                </div>
            </form>
        @endif
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-border">
        <h3 class="mb-4 text-sm font-semibold text-neutral-900">Bahasan Pertemuan</h3>
        <form wire:submit="saveBahasan" class="space-y-4">
            <textarea wire:model="bahasan" rows="4" placeholder="Ringkasan topik/bahasan pertemuan ini" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('bahasan') ring-2 ring-red-500 @enderror shadow-border"></textarea>
            @error('bahasan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex justify-end border-t border-neutral-200 pt-4">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                    <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                    Simpan Bahasan
                </button>
            </div>
        </form>
    </div>

    <div class="flex gap-3 rounded-2xl border border-dashed border-neutral-200 bg-neutral-50 px-5 py-4 text-sm text-neutral-600">
        <i data-lucide="info" class="h-5 w-5 shrink-0 text-neutral-400" aria-hidden="true"></i>
        <span>Kelola kehadiran, materi perkuliahan, dan tugas untuk sesi ini belum tersedia di panel admin — modul-modul tersebut menyusul terpisah.</span>
    </div>
</div>
