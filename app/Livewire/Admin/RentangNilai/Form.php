<?php

namespace App\Livewire\Admin\RentangNilai;

use App\Models\Jenjang;
use App\Models\RentangNilai;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Form extends Component
{
    // ---- Mode: null = create (banyak baris sekaligus untuk satu jenjang), diisi = edit (satu baris) ----
    public ?int $rentangNilaiId = null;

    public string $submitError = '';

    // FK boleh ?int karena diikat lewat <x-searchable-select> (entangle), bukan <select> polos.
    public ?int $id_jenjang = null;

    // ---- Mode create: banyak baris untuk jenjang yang sama sekaligus, sama seperti pola batch
    // di App\Livewire\Admin\Krs\Form ----
    /** @var array<int, array{nilai_huruf: string, nilai_angka: string, nilai_rendah: string, nilai_tinggi: string, is_lulus: bool}> */
    public array $baris = [];

    // ---- Mode edit: satu baris ----
    public string $nilai_huruf = '';

    public string $nilai_angka = '';

    public string $nilai_rendah = '';

    public string $nilai_tinggi = '';

    public bool $is_lulus = true;

    public function mount(?int $id = null): void
    {
        $this->rentangNilaiId = $id;

        if ($id === null) {
            $this->baris = [$this->emptyBaris()];

            return;
        }

        $rentangNilai = RentangNilai::findOrFail($id);

        $this->id_jenjang = $rentangNilai->id_jenjang;
        $this->nilai_huruf = $rentangNilai->nilai_huruf;
        $this->nilai_angka = (string) $rentangNilai->nilai_angka;
        $this->nilai_rendah = (string) $rentangNilai->nilai_rendah;
        $this->nilai_tinggi = (string) $rentangNilai->nilai_tinggi;
        $this->is_lulus = $rentangNilai->is_lulus !== false;
    }

    /**
     * @return array{nilai_huruf: string, nilai_angka: string, nilai_rendah: string, nilai_tinggi: string, is_lulus: bool}
     */
    private function emptyBaris(): array
    {
        return [
            'nilai_huruf' => '',
            'nilai_angka' => '',
            'nilai_rendah' => '',
            'nilai_tinggi' => '',
            'is_lulus' => true,
        ];
    }

    public function addRow(): void
    {
        $this->baris[] = $this->emptyBaris();
    }

    public function removeRow(int $index): void
    {
        if (count($this->baris) <= 1) {
            return;
        }

        unset($this->baris[$index]);
        $this->baris = array_values($this->baris);
    }

    #[Computed]
    public function jenjangOptions()
    {
        return Jenjang::whereNull('deleted_at')->orderBy('nama')->get(['id', 'nama', 'kode']);
    }

    /**
     * Sama persis dengan aturan di RentangNilaiController::update.
     */
    protected function editRules(): array
    {
        $uniqueHuruf = Rule::unique('rentang_nilai', 'nilai_huruf')
            ->where('id_jenjang', $this->id_jenjang)
            ->ignore($this->rentangNilaiId);

        return [
            'id_jenjang' => ['required', 'integer', 'exists:jenjang,id'],
            'nilai_huruf' => ['required', 'string', 'max:10', $uniqueHuruf],
            'nilai_angka' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'nilai_rendah' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'nilai_tinggi' => ['required', 'numeric', 'min:0', 'max:999.99', 'gte:nilai_rendah'],
        ];
    }

    public function save()
    {
        $this->submitError = '';

        return $this->rentangNilaiId ? $this->saveEdit() : $this->saveCreate();
    }

    /**
     * Sama persis dengan RentangNilaiController::update.
     */
    protected function saveEdit()
    {
        $validated = $this->validate($this->editRules());
        $validated['is_lulus'] = $this->is_lulus;

        RentangNilai::findOrFail($this->rentangNilaiId)->update($validated);

        session()->flash('status', 'Rentang nilai berhasil diperbarui.');

        return redirect()->route('admin.akademik.rentang-nilai');
    }

    /**
     * Sama persis dengan RentangNilaiController::store, dipanggil berulang per baris — meniru
     * perilaku halaman create di frontend yang mengirim POST satu per satu (bukan satu transaksi):
     * kalau salah satu baris gagal, baris sebelumnya yang sudah tersimpan TIDAK di-rollback.
     */
    protected function saveCreate()
    {
        if (! $this->id_jenjang) {
            $this->submitError = 'Pilih jenjang terlebih dahulu.';

            return null;
        }

        $hurufSeen = [];
        $created = 0;

        foreach ($this->baris as $index => $row) {
            $huruf = strtoupper(trim($row['nilai_huruf'] ?? ''));
            if ($huruf !== '' && in_array($huruf, $hurufSeen, true)) {
                $this->submitError = 'Baris '.($index + 1).": nilai huruf \"{$row['nilai_huruf']}\" duplikat di form.";
                break;
            }

            $validated = $this->validateRow($row, 'Baris '.($index + 1));
            if ($validated === null) {
                break;
            }

            RentangNilai::create($validated);
            $hurufSeen[] = $huruf;
            $created++;
        }

        if ($this->submitError !== '') {
            if ($created > 0) {
                $this->submitError .= ' '.$created.' baris sebelumnya sudah tersimpan di basis data.';
            }

            return null;
        }

        session()->flash('status', $created === 1 ? 'Rentang nilai berhasil dibuat.' : "{$created} rentang nilai berhasil dibuat.");

        return redirect()->route('admin.akademik.rentang-nilai');
    }

    /**
     * @param  array{nilai_huruf?: string, nilai_angka?: string, nilai_rendah?: string, nilai_tinggi?: string, is_lulus?: bool}  $row
     * @return array<string, mixed>|null
     */
    private function validateRow(array $row, string $rowLabel): ?array
    {
        $uniqueHuruf = Rule::unique('rentang_nilai', 'nilai_huruf')->where('id_jenjang', $this->id_jenjang);

        $validator = Validator::make(
            array_merge($row, ['id_jenjang' => $this->id_jenjang]),
            [
                'id_jenjang' => ['required', 'integer', 'exists:jenjang,id'],
                'nilai_huruf' => ['required', 'string', 'max:10', $uniqueHuruf],
                'nilai_angka' => ['required', 'numeric', 'min:0', 'max:999.99'],
                'nilai_rendah' => ['required', 'numeric', 'min:0', 'max:999.99'],
                'nilai_tinggi' => ['required', 'numeric', 'min:0', 'max:999.99', 'gte:nilai_rendah'],
            ]
        );

        if ($validator->fails()) {
            $this->submitError = $rowLabel.': '.$validator->errors()->first();

            return null;
        }

        $validated = $validator->validated();
        $validated['nilai_huruf'] = trim($validated['nilai_huruf']);
        $validated['is_lulus'] = (bool) ($row['is_lulus'] ?? true);

        return $validated;
    }

    public function render()
    {
        return view('livewire.admin.rentang-nilai.form')->extends('layouts.web');
    }
}
