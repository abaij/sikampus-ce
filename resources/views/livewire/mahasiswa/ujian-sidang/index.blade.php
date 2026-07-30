@section('title', 'Ujian Sidang — ' . config('app.name'))
@section('header_title', 'Ujian Sidang')
@section('header_subtitle', 'Daftar pengajuan ujian sidang per semester. Buka detail untuk melihat jadwal dan dosen penguji.')

@php
    $ctx = $this->ctx;
    $rows = $ctx['ujian_sidang'];
    $statusBadgeClass = fn (?string $s) => match ($s) {
        'approved' => 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        'submitted' => 'bg-sky-50 text-sky-800 ring-sky-100',
        'rejected' => 'bg-rose-50 text-rose-800 ring-rose-100',
        'returned' => 'bg-amber-50 text-amber-900 ring-amber-100',
        default => 'bg-neutral-100 text-neutral-700 ring-neutral-200',
    };
    $statusLabel = fn (?string $s) => match ($s) {
        'draft' => 'Draft', 'submitted' => 'Terkirim', 'approved' => 'Disetujui',
        'rejected' => 'Ditolak', 'returned' => 'Dikembalikan', default => $s ?? '—',
    };
@endphp

<div class="overflow-hidden rounded-xl bg-white shadow-border">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-neutral-100 bg-neutral-50/80 px-4 py-3">
        <div>
            <h2 class="text-sm font-semibold text-neutral-800">Daftar ujian sidang</h2>
            <p class="mt-0.5 text-xs text-neutral-500">Ringkasan per semester. Gunakan aksi untuk melihat detail.</p>
        </div>
        @if ($ctx['eligible_pengajuan'] && $rows->isNotEmpty())
            <a href="{{ route('mahasiswa.akhir-studi.ujian-sidang.pengajuan') }}" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-sky-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                <i data-lucide="plus-circle" class="h-4 w-4" aria-hidden="true"></i>
                Ajukan ujian sidang
            </a>
        @endif
    </div>

    @if ($rows->isEmpty())
        <div class="px-4 py-12 text-center">
            <p class="text-sm text-neutral-600">Belum ada data ujian sidang.</p>
            @if ($ctx['eligible_pengajuan'])
                <a href="{{ route('mahasiswa.akhir-studi.ujian-sidang.pengajuan') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    <i data-lucide="plus-circle" class="h-4 w-4" aria-hidden="true"></i>
                    Ajukan ujian sidang
                    <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                </a>
            @else
                <p class="mt-2 text-xs text-neutral-500">{{ $ctx['pesan_tidak_eligible'] ?? 'Pengajuan baru tidak tersedia untuk saat ini (misalnya belum ada tugas akhir atau judul belum disetujui).' }}</p>
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-neutral-200 bg-neutral-50/50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Judul TA</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Tanggal daftar</th>
                        <th class="px-4 py-3">Mulai ujian</th>
                        <th class="px-4 py-3">Selesai ujian</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($rows as $us)
                        <tr wire:key="us-{{ $us->id }}" class="hover:bg-neutral-50/80">
                            <td class="max-w-[280px] px-4 py-3 font-medium text-neutral-900">
                                <span class="line-clamp-2 block text-sm font-normal text-neutral-600">{{ $us->tugasAkhir->judul ?? '' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusBadgeClass($us->status) }}">{{ $statusLabel($us->status) }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-neutral-700">{{ $us->tanggal_daftar?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-neutral-700">{{ $us->tanggal_ujian_mulai?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-neutral-700">{{ $us->tanggal_ujian_selesai?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                <a
                                    href="{{ route('mahasiswa.akhir-studi.ujian-sidang.show', $us->id) }}"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-neutral-700 shadow-border transition hover:bg-neutral-50"
                                    title="Lihat detail"
                                >
                                    <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
