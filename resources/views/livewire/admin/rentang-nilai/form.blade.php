@section('title', ($rentangNilaiId ? 'Ubah' : 'Tambah') . ' Rentang Nilai — ' . config('app.name'))
@section('header_title', ($rentangNilaiId ? 'Ubah' : 'Tambah') . ' Rentang Nilai')
@section('header_icon', 'sliders-horizontal')

@section('nav')
    @include('admin.partials.nav')
@endsection

@section('breadcrumb')
    @include('admin.partials.breadcrumb', ['items' => [
        ['label' => 'Akademik'],
        ['label' => 'Rentang Nilai', 'route' => route('admin.akademik.rentang-nilai')],
        ['label' => $rentangNilaiId ? 'Ubah' : 'Tambah'],
    ]])
@endsection

<div>
    @if ($submitError !== '')
        <div class="mb-4 flex gap-3 rounded-lg border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-rose-600" aria-hidden="true"></i>
            <span>{{ $submitError }}</span>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-border">
            <div class="max-w-md">
                <label class="mb-1.5 block text-sm font-medium text-neutral-700">Jenjang {{ $rentangNilaiId ? '' : '(sama untuk semua baris)' }} *</label>
                <x-searchable-select
                    model="id_jenjang"
                    :options="$this->jenjangOptions"
                    placeholder="— Pilih jenjang —"
                />
                @error('id_jenjang') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($rentangNilaiId)
            <div class="rounded-2xl bg-white p-6 shadow-border">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Nilai Huruf *</label>
                        <input type="text" maxlength="10" wire:model="nilai_huruf" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nilai_huruf') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('nilai_huruf') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Angka Mutu *</label>
                        <input type="number" step="0.01" min="0" wire:model="nilai_angka" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nilai_angka') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('nilai_angka') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Batas Bawah (nilai akhir) *</label>
                        <input type="number" step="0.01" min="0" wire:model="nilai_rendah" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nilai_rendah') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('nilai_rendah') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Batas Atas (nilai akhir) *</label>
                        <input type="number" step="0.01" min="0" wire:model="nilai_tinggi" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 @error('nilai_tinggi') ring-2 ring-red-500 @enderror shadow-border" />
                        @error('nilai_tinggi') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm font-medium text-neutral-700">
                    <input type="checkbox" wire:model="is_lulus" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/20" />
                    Termasuk nilai lulus
                </label>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($baris as $index => $row)
                    <div wire:key="baris-{{ $index }}" class="rounded-2xl bg-white p-6 shadow-border">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <span class="text-xs font-bold uppercase tracking-wide text-neutral-500">Baris {{ $index + 1 }}</span>
                            <button
                                type="button"
                                wire:click="removeRow({{ $index }})"
                                @if (count($baris) <= 1) disabled @endif
                                title="{{ count($baris) <= 1 ? 'Minimal satu baris' : 'Hapus baris' }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40 shadow-border"
                            >
                                <i data-lucide="trash-2" class="h-4 w-4" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-neutral-600">Nilai Huruf *</label>
                                <input type="text" maxlength="10" placeholder="A, B, C" wire:model="baris.{{ $index }}.nilai_huruf" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-neutral-600">Angka Mutu *</label>
                                <input type="number" step="0.01" min="0" placeholder="4.00" wire:model="baris.{{ $index }}.nilai_angka" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-neutral-600">Batas Bawah *</label>
                                <input type="number" step="0.01" min="0" placeholder="85" wire:model="baris.{{ $index }}.nilai_rendah" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-neutral-600">Batas Atas *</label>
                                <input type="number" step="0.01" min="0" placeholder="100" wire:model="baris.{{ $index }}.nilai_tinggi" class="w-full rounded-lg px-3 py-2.5 text-sm outline-none focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-border" />
                            </div>
                        </div>
                        <label class="mt-3 flex items-center gap-2 text-sm font-medium text-neutral-700">
                            <input type="checkbox" wire:model="baris.{{ $index }}.is_lulus" class="h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900/20" />
                            Termasuk nilai lulus
                        </label>
                    </div>
                @endforeach

                <div class="flex justify-center">
                    <button type="button" wire:click="addRow" class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                        <i data-lucide="plus" class="h-4 w-4" aria-hidden="true"></i>
                        Tambah Rentang Nilai
                    </button>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.akademik.rentang-nilai') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-neutral-700 transition hover:bg-neutral-50 shadow-border">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-neutral-800">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i>
                {{ $rentangNilaiId ? 'Simpan Perubahan' : 'Simpan Semua' }}
            </button>
        </div>
    </form>
</div>
