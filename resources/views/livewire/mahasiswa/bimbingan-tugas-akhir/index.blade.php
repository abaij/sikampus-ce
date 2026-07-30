@section('title', 'Bimbingan Tugas Akhir — ' . config('app.name'))
@section('header_title', 'Bimbingan Tugas Akhir')
@section('header_subtitle', 'Pilih tugas akhir yang judulnya sudah disetujui untuk melihat riwayat bimbingan dan mengisi catatan Anda.')

@php $data = $this->data; @endphp

<div class="space-y-6">
    @if (! $data['has_tugas_akhir'])
        <div class="rounded-xl border border-amber-200 bg-amber-50/90 px-4 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex gap-3">
                    <i data-lucide="book-open" class="mt-0.5 h-6 w-6 shrink-0 text-amber-800" aria-hidden="true"></i>
                    <div>
                        <p class="font-semibold text-amber-950">Belum ada data tugas akhir</p>
                        <p class="mt-2 text-sm text-amber-950/90">{{ $data['pesan_belum_ajukan'] }}</p>
                    </div>
                </div>
                <a
                    href="{{ route('mahasiswa.akhir-studi.tugas-akhir') }}"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-amber-900/90 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-950"
                >
                    Pengajuan Tugas Akhir
                    <i data-lucide="chevron-right" class="h-4 w-4 opacity-90" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    @elseif ($data['pesan_tanpa_disetujui'] && $data['tugas_akhir_disetujui']->isEmpty())
        <div class="rounded-xl bg-neutral-50/90 px-4 py-5 shadow-border">
            <p class="text-sm text-neutral-700">{{ $data['pesan_tanpa_disetujui'] }}</p>
            <a href="{{ route('mahasiswa.akhir-studi.tugas-akhir') }}" class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-sky-700 hover:text-sky-900">
                Ke halaman pengajuan tugas akhir
                <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
            </a>
        </div>
    @else
        <div class="overflow-hidden rounded-xl bg-white shadow-border">
            <div class="border-b border-neutral-100 bg-neutral-50/80 px-4 py-3">
                <h2 class="text-sm font-semibold text-neutral-800">Tugas akhir disetujui</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Judul telah disetujui — riwayat bimbingan tersedia per entri di bawah.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="min-w-[200px] px-4 py-3">Judul</th>
                            <th class="whitespace-nowrap px-4 py-3">Topik TA</th>
                            <th class="whitespace-nowrap px-4 py-3">Semester TA</th>
                            <th class="whitespace-nowrap px-4 py-3">Bimbingan</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($data['tugas_akhir_disetujui'] as $row)
                            <tr wire:key="ta-{{ $row->id }}" class="align-top text-neutral-800">
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $row->judul ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ $row->topik ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-neutral-700">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i data-lucide="calendar" class="h-3.5 w-3.5 text-neutral-400" aria-hidden="true"></i>
                                        @if ($row->semester)
                                            {{ $row->semester->nama }}
                                            <span class="ml-1 text-xs text-neutral-500">({{ $row->semester->kode }})</span>
                                        @else
                                            —
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">{{ $row->bimbingan_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    <a
                                        href="{{ route('mahasiswa.akhir-studi.bimbingan-tugas-akhir.show', $row->id) }}"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-neutral-700 shadow-border transition hover:bg-neutral-50"
                                        title="Detail & riwayat bimbingan"
                                    >
                                        <i data-lucide="eye" class="h-4 w-4" aria-hidden="true"></i>
                                        <span class="hidden sm:inline">Detail</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
