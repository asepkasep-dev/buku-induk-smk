<?php

use Livewire\Component;

/**
 * STUB Fase 1 (Frontend-only) — data dummy, belum menyentuh database.
 * Backend (model item nilai, bobot/KKM per offering, service recompute, policy)
 * menyusul sesuai alur PRD -> Frontend -> Backend -> Uji coba.
 */
new class extends Component
{
    public string $studentName = 'Ahmad Fauzi';

    public string $subjectName = 'Pemrograman Web & Perangkat';

    public string $classname = 'X RPL 1';

    public string $semesterLabel = 'Ganjil — 2026/2027';

    public string $status = 'DRAFT';

    public int $penBobot = 60;

    public int $ketBobot = 40;

    public int $kkm = 75;

    public array $pen = [
        ['label' => 'UH 1 — Dasar HTML/CSS', 'score' => 82],
        ['label' => 'UH 2 — JavaScript', 'score' => 90],
        ['label' => 'Tugas Proyek', 'score' => 78],
    ];

    public array $ket = [
        ['label' => 'Praktikum 1 — Landing Page', 'score' => 88],
        ['label' => 'Praktikum 2 — CRUD Sederhana', 'score' => 76],
    ];

    public function addPen(): void
    {
        $this->pen[] = ['label' => 'Item Pengetahuan baru', 'score' => 0];
    }

    public function removePen(int $i): void
    {
        unset($this->pen[$i]);
        $this->pen = array_values($this->pen);
    }

    public function addKet(): void
    {
        $this->ket[] = ['label' => 'Item Keterampilan baru', 'score' => 0];
    }

    public function removeKet(int $i): void
    {
        unset($this->ket[$i]);
        $this->ket = array_values($this->ket);
    }

    public function save(): void
    {
        session()->flash('info', 'Contoh tampilan (stub). Penyimpanan asli aktif setelah backend Fase 1 dibuat.');
    }

    private function avg(array $items): ?float
    {
        $scores = array_map(fn ($i) => (float) $i['score'], $items);

        return $scores === [] ? null : array_sum($scores) / count($scores);
    }

    // Interval predikat diturunkan dari KKM: I = (100 - KKM) / 3.
    // D < KKM | C [KKM, KKM+I) | B [KKM+I, KKM+2I) | A [KKM+2I, 100].
    private function interval(): float
    {
        return (100 - $this->kkm) / 3;
    }

    private function huruf(?float $nilai): string
    {
        if ($nilai === null) {
            return '-';
        }

        $i = $this->interval();

        return match (true) {
            $nilai >= $this->kkm + 2 * $i => 'A',
            $nilai >= $this->kkm + $i => 'B',
            $nilai >= $this->kkm => 'C',
            default => 'D',
        };
    }

    // Batas rentang integer untuk legend (nilai bulat terendah tiap pita).
    private function ranges(): array
    {
        $i = $this->interval();
        $startB = (int) ceil($this->kkm + $i);
        $startA = (int) ceil($this->kkm + 2 * $i);

        return [
            ['letter' => 'A', 'range' => $startA.'–100'],
            ['letter' => 'B', 'range' => $startB.'–'.($startA - 1)],
            ['letter' => 'C', 'range' => $this->kkm.'–'.($startB - 1)],
            ['letter' => 'D', 'range' => '< '.$this->kkm],
        ];
    }

    public function with(): array
    {
        $rataPen = $this->avg($this->pen);
        $rataKet = $this->avg($this->ket);

        $akhir = null;
        if ($rataPen !== null && $rataKet !== null) {
            $bobot = max($this->penBobot + $this->ketBobot, 1);
            $akhir = (int) round(($rataPen * $this->penBobot + $rataKet * $this->ketBobot) / $bobot);
        }

        return [
            'rataPen' => $rataPen,
            'rataKet' => $rataKet,
            'akhir' => $akhir,
            'interval' => $this->interval(),
            'huruf' => $this->huruf($akhir === null ? null : (float) $akhir),
            'tuntas' => $akhir !== null && $akhir >= $this->kkm,
            'ranges' => $this->ranges(),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">CONTOH TAMPILAN — stub, data dummy</span>
            <h1 class="mt-2 text-2xl font-semibold">Input Nilai Rapor (Ledger)</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $studentName }} · {{ $subjectName }}</p>
        </div>
        <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">Kembali</a>
    </div>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-3">
            <div><div class="text-sm text-gray-500">Kelas</div><div class="mt-1 font-medium">{{ $classname }}</div></div>
            <div><div class="text-sm text-gray-500">Semester</div><div class="mt-1 font-medium">{{ $semesterLabel }}</div></div>
            <div><div class="text-sm text-gray-500">Status</div><div class="mt-1 font-medium">{{ $status }}</div></div>
        </div>
    </section>

    @if (session('info'))
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">{{ session('info') }}</div>
    @endif

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Bobot & Kriteria Ketuntasan</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">Bobot Pengetahuan (%)</label>
                <input type="number" min="0" max="100" wire:model.live="penBobot" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium">Bobot Keterampilan (%)</label>
                <input type="number" min="0" max="100" wire:model.live="ketBobot" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium">KKM Mapel</label>
                <input type="number" min="0" max="100" wire:model.live="kkm" class="mt-1 w-full rounded-lg border px-3 py-2">
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-500">Total bobot saat ini: {{ $penBobot + $ketBobot }}% (ideal 100%). Perubahan bobot/KKM memicu hitung ulang nilai akhir.</p>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Nilai Pengetahuan</h2>
                <button type="button" wire:click="addPen" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">+ Tambah</button>
            </div>
            <div class="mt-4 space-y-2">
                @foreach ($pen as $i => $item)
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="pen.{{ $i }}.label" class="w-full rounded-lg border px-3 py-2 text-sm">
                        <input type="number" min="0" max="100" wire:model.live="pen.{{ $i }}.score" class="w-24 rounded-lg border px-3 py-2 text-sm">
                        <button type="button" wire:click="removePen({{ $i }})" class="text-red-600 hover:underline text-sm">×</button>
                    </div>
                @endforeach
                @if (empty($pen))<p class="text-sm text-gray-400">Belum ada item.</p>@endif
            </div>
            <div class="mt-4 text-sm">Rata-rata Pengetahuan: <span class="font-semibold">{{ $rataPen === null ? '-' : number_format($rataPen, 1) }}</span></div>
        </section>

        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Nilai Keterampilan</h2>
                <button type="button" wire:click="addKet" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white">+ Tambah</button>
            </div>
            <div class="mt-4 space-y-2">
                @foreach ($ket as $i => $item)
                    <div class="flex items-center gap-2">
                        <input type="text" wire:model="ket.{{ $i }}.label" class="w-full rounded-lg border px-3 py-2 text-sm">
                        <input type="number" min="0" max="100" wire:model.live="ket.{{ $i }}.score" class="w-24 rounded-lg border px-3 py-2 text-sm">
                        <button type="button" wire:click="removeKet({{ $i }})" class="text-red-600 hover:underline text-sm">×</button>
                    </div>
                @endforeach
                @if (empty($ket))<p class="text-sm text-gray-400">Belum ada item.</p>@endif
            </div>
            <div class="mt-4 text-sm">Rata-rata Keterampilan: <span class="font-semibold">{{ $rataKet === null ? '-' : number_format($rataKet, 1) }}</span></div>
        </section>
    </div>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Hasil Akhir (dihitung otomatis)</h2>
        <p class="mt-1 text-sm text-gray-500">Predikat mengikuti KKM yang diinput (interval = (100 − KKM) ÷ 3 = {{ number_format($interval, 2) }}).</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full max-w-md border-collapse text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-500">
                        <th class="px-3 py-1">Predikat</th>
                        <th class="px-3 py-1">Rentang Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ranges as $r)
                        <tr class="border-b {{ $r['letter'] === $huruf ? 'bg-blue-50 font-semibold' : '' }}">
                            <td class="px-3 py-1">{{ $r['letter'] }}</td>
                            <td class="px-3 py-1">{{ $r['range'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-gray-50 p-4"><div class="text-sm text-gray-500">Nilai Akhir</div><div class="mt-1 text-2xl font-semibold">{{ $akhir ?? '-' }}</div></div>
            <div class="rounded-lg bg-gray-50 p-4"><div class="text-sm text-gray-500">Predikat / Huruf</div><div class="mt-1 text-2xl font-semibold">{{ $huruf }}</div></div>
            <div class="rounded-lg p-4 {{ $tuntas ? 'bg-green-50' : 'bg-red-50' }}">
                <div class="text-sm text-gray-500">Ketuntasan (KKM {{ $kkm }})</div>
                <div class="mt-1 text-lg font-semibold {{ $tuntas ? 'text-green-700' : 'text-red-700' }}">{{ $akhir === null ? '-' : ($tuntas ? 'TUNTAS' : 'BELUM TUNTAS') }}</div>
            </div>
        </div>
        <button type="button" wire:click="save" class="mt-5 rounded-lg bg-blue-600 px-4 py-2 font-medium text-white">Simpan (belum aktif — stub)</button>
    </section>
</div>
