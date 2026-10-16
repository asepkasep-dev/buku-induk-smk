<?php

use App\Models\ReportScore;
use App\Models\ReportScoreItem;
use App\Services\GradePredicateResolver;
use App\Services\ReportScoreLedgerService;
use Livewire\Component;

new class extends Component
{
    public ReportScore $reportScore;

    public int $penBobot = 50;

    public int $ketBobot = 50;

    public int $kkm = 70;

    public string $newPenLabel = '';

    public int $newPenScore = 0;

    public string $newKetLabel = '';

    public int $newKetScore = 0;

    /** @var array<int, array{id: int, label: string, score: int}> */
    public array $penItems = [];

    /** @var array<int, array{id: int, label: string, score: int}> */
    public array $ketItems = [];

    public function mount(ReportScore $reportScore): void
    {
        $this->authorize('view', $reportScore);

        $this->reportScore = $reportScore->load([
            'subjectOffering.curriculumSubject.subject',
            'subjectOffering.semester.academicYear',
            'student',
        ]);

        $this->reloadLedger();
    }

    public function addPen(): void
    {
        $this->authorize('update', $this->reportScore);

        $this->validate([
            'newPenLabel' => ['required', 'string', 'max:255'],
            'newPenScore' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $this->ledger()->addItem(
            auth()->user(),
            $this->reportScore,
            ReportScoreItem::TYPE_PENGETAHUAN,
            $this->newPenLabel,
            $this->newPenScore,
        );

        $this->reset('newPenLabel');
        $this->newPenScore = 0;

        $this->reloadLedger();
    }

    public function addKet(): void
    {
        $this->authorize('update', $this->reportScore);

        $this->validate([
            'newKetLabel' => ['required', 'string', 'max:255'],
            'newKetScore' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $this->ledger()->addItem(
            auth()->user(),
            $this->reportScore,
            ReportScoreItem::TYPE_KETERAMPILAN,
            $this->newKetLabel,
            $this->newKetScore,
        );

        $this->reset('newKetLabel');
        $this->newKetScore = 0;

        $this->reloadLedger();
    }

    public function removePen(int $id): void
    {
        $this->removeItem($id);
    }

    public function removeKet(int $id): void
    {
        $this->removeItem($id);
    }

    public function saveItems(): void
    {
        $this->authorize('update', $this->reportScore);

        $this->validate([
            'penItems' => ['array'],
            'penItems.*.label' => ['required', 'string', 'max:255'],
            'penItems.*.score' => ['required', 'integer', 'min:0', 'max:100'],
            'ketItems' => ['array'],
            'ketItems.*.label' => ['required', 'string', 'max:255'],
            'ketItems.*.score' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $this->penItems = $this->sanitizeRows($this->penItems);
        $this->ketItems = $this->sanitizeRows($this->ketItems);

        $service = $this->ledger();
        $user = auth()->user();
        $all = [...$this->penItems, ...$this->ketItems];

        foreach ($this->currentItemModels() as $item) {
            $row = collect($all)->firstWhere('id', $item->id);

            if ($row === null) {
                $service->removeItem($user, $item);

                continue;
            }

            if ($row['label'] !== $item->label || (int) $row['score'] !== (int) $item->score) {
                $service->updateItem($user, $item, $row['label'], (int) $row['score']);
            }
        }

        $this->reloadLedger();

        session()->flash('success', 'Ledger nilai tersimpan dan nilai akhir dihitung ulang.');
    }

    public function saveWeights(): void
    {
        $this->authorize('update', $this->reportScore);

        $this->validate([
            'penBobot' => ['required', 'integer', 'min:0', 'max:100'],
            'ketBobot' => ['required', 'integer', 'min:0', 'max:100'],
            'kkm' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $this->ledger()->updateOfferingWeights(
            auth()->user(),
            $this->reportScore->subjectOffering,
            $this->penBobot,
            $this->ketBobot,
            $this->kkm,
        );

        $this->reloadLedger();

        session()->flash('success', 'Bobot & KKM disimpan; seluruh nilai terkait dihitung ulang.');
    }

    private function removeItem(int $id): void
    {
        $this->authorize('update', $this->reportScore);

        $item = $this->reportScore->items()->findOrFail($id);

        $this->ledger()->removeItem(auth()->user(), $item);

        $this->reloadLedger();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ReportScoreItem>
     */
    private function currentItemModels()
    {
        return $this->reportScore->items()->get();
    }

    /**
     * @param  array<int, array{id: int, label: string, score: int}>  $rows
     * @return array<int, array{id: int, label: string, score: int}>
     */
    private function sanitizeRows(array $rows): array
    {
        return array_values(array_map(fn ($row) => [
            'id' => (int) $row['id'],
            'label' => trim((string) $row['label']),
            'score' => max(0, min(100, (int) $row['score'])),
        ], $rows));
    }

    private function ledger(): ReportScoreLedgerService
    {
        return app(ReportScoreLedgerService::class);
    }

    private function reloadLedger(): void
    {
        $this->reportScore->refresh();
        $offering = $this->reportScore->subjectOffering->refresh();

        $this->penBobot = (int) $offering->pengetahuan_weight;
        $this->ketBobot = (int) $offering->keterampilan_weight;
        $this->kkm = (int) ($offering->kkm ?? GradePredicateResolver::DEFAULT_KKM);

        $map = fn ($items) => $items
            ->map(fn (ReportScoreItem $item) => [
                'id' => $item->id,
                'label' => $item->label,
                'score' => (int) $item->score,
            ])
            ->values()
            ->all();

        $this->penItems = $map($this->reportScore->items()->where('type', ReportScoreItem::TYPE_PENGETAHUAN)->get());
        $this->ketItems = $map($this->reportScore->items()->where('type', ReportScoreItem::TYPE_KETERAMPILAN)->get());
    }

    public function with(): array
    {
        $resolver = app(GradePredicateResolver::class);

        $final = $this->reportScore->final_score;
        $resolved = $resolver->resolve($final === null ? null : (float) $final, $this->kkm);

        $offering = $this->reportScore->subjectOffering;

        return [
            'subjectName' => $offering->curriculumSubject?->subject?->name ?? '-',
            'classname' => $offering->rombel?->name ?? '-',
            'semesterLabel' => trim(($offering->semester?->name ?? '').' — '.($offering->semester?->academicYear?->name ?? '')),
            'rataPen' => $this->reportScore->pengetahuan_avg,
            'rataKet' => $this->reportScore->keterampilan_avg,
            'akhir' => $final,
            'interval' => $resolver->interval($this->kkm),
            'huruf' => $resolved['letter'] ?? '-',
            'predikat' => $resolved['predicate'] ?? '-',
            'ranges' => $resolver->ranges($this->kkm),
            'tuntas' => $resolved['tuntas'],
            'editable' => $this->reportScore->status === 'DRAFT'
                && auth()->user()->can('update', $this->reportScore),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Input Nilai Rapor (Ledger)</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ $reportScore->student?->full_name ?? '-' }} · {{ $subjectName }}
            </p>
        </div>
        <a href="{{ route('report-scores.edit', $reportScore) }}" class="text-sm text-blue-600 hover:underline">Kembali ke Nilai</a>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    @if (!$editable)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Nilai berstatus <strong>{{ $reportScore->status }}</strong>. Ledger hanya dapat diubah saat status DRAFT.
        </div>
    @endif

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="grid gap-4 sm:grid-cols-3">
            <div><div class="text-sm text-gray-500">Kelas</div><div class="mt-1 font-medium">{{ $classname }}</div></div>
            <div><div class="text-sm text-gray-500">Semester</div><div class="mt-1 font-medium">{{ $semesterLabel }}</div></div>
            <div><div class="text-sm text-gray-500">Status</div><div class="mt-1 font-medium">{{ $reportScore->status }}</div></div>
        </div>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Bobot & Kriteria Ketuntasan</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">Bobot Pengetahuan (%)</label>
                <input type="number" min="0" max="100" @disabled(!$editable) wire:model.live="penBobot" class="mt-1 w-full rounded-lg border px-3 py-2 disabled:bg-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium">Bobot Keterampilan (%)</label>
                <input type="number" min="0" max="100" @disabled(!$editable) wire:model.live="ketBobot" class="mt-1 w-full rounded-lg border px-3 py-2 disabled:bg-gray-100">
            </div>
            <div>
                <label class="block text-sm font-medium">KKM Mapel</label>
                <input type="number" min="0" max="100" @disabled(!$editable) wire:model.live="kkm" class="mt-1 w-full rounded-lg border px-3 py-2 disabled:bg-gray-100">
            </div>
        </div>
        @error('penBobot')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
        @error('ketBobot')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
        @error('kkm')<div class="mt-1 text-sm text-red-600">{{ $message }}</div>@enderror
        <p class="mt-2 text-xs text-gray-500">Total bobot: {{ $penBobot + $ketBobot }}%. Nilai akhir memakai rasio bobot ini. Simpan untuk menghitung ulang.</p>
        @if ($editable)
            <button type="button" wire:click="saveWeights" class="mt-3 rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white">Simpan Bobot & KKM</button>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ([
            ['title' => 'Nilai Pengetahuan', 'prop' => 'penItems', 'items' => $penItems, 'label' => 'newPenLabel', 'score' => 'newPenScore', 'add' => 'addPen', 'remove' => 'removePen'],
            ['title' => 'Nilai Keterampilan', 'prop' => 'ketItems', 'items' => $ketItems, 'label' => 'newKetLabel', 'score' => 'newKetScore', 'add' => 'addKet', 'remove' => 'removeKet'],
        ] as $panel)
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold">{{ $panel['title'] }}</h2>
                <div class="mt-4 space-y-2">
                    @foreach ($panel['items'] as $i => $item)
                        <div class="flex items-center gap-2">
                            <input type="text" @disabled(!$editable) wire:model="{{ $panel['prop'] }}.{{ $i }}.label" class="w-full rounded-lg border px-3 py-2 text-sm disabled:bg-gray-100">
                            <input type="number" min="0" max="100" @disabled(!$editable) wire:model="{{ $panel['prop'] }}.{{ $i }}.score" class="w-20 rounded-lg border px-3 py-2 text-sm disabled:bg-gray-100">
                            @if ($editable)
                                <button type="button" wire:click="{{ $panel['remove'] }}({{ $item['id'] }})" class="text-red-600 hover:underline text-sm">×</button>
                            @endif
                        </div>
                    @endforeach
                    @if (empty($panel['items']))<p class="text-sm text-gray-400">Belum ada item.</p>@endif
                </div>

                @if ($editable)
                    <div class="mt-4 flex items-end gap-2 border-t pt-4">
                        <div class="flex-1">
                            <label class="block text-xs text-gray-500">Label item baru</label>
                            <input type="text" wire:model="{{ $panel['label'] }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                        </div>
                        <div class="w-20">
                            <label class="block text-xs text-gray-500">Skor</label>
                            <input type="number" min="0" max="100" wire:model="{{ $panel['score'] }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                        </div>
                        <button type="button" wire:click="{{ $panel['add'] }}" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white">+ Tambah</button>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    @if ($editable)
        <div class="flex justify-end">
            <button type="button" wire:click="saveItems" class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white">Simpan Ledger & Hitung Nilai</button>
        </div>
    @endif

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="text-lg font-semibold">Hasil Akhir (dihitung otomatis)</h2>
        <p class="mt-1 text-sm text-gray-500">Predikat mengikuti KKM {{ $kkm }} (interval = (100 − KKM) ÷ 3 = {{ number_format($interval, 2) }}).</p>

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
                            <td class="px-3 py-1">{{ $r['letter'] }} · {{ $r['label'] }}</td>
                            <td class="px-3 py-1">{{ $r['max'] === 100 ? $r['min'].'–100' : ($r['min'] === 0 ? '< '.$kkm : $r['min'].'–'.$r['max']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-gray-50 p-4"><div class="text-sm text-gray-500">Rata-rata Pengetahuan</div><div class="mt-1 text-xl font-semibold">{{ $rataPen === null ? '-' : number_format((float) $rataPen, 1) }}</div></div>
            <div class="rounded-lg bg-gray-50 p-4"><div class="text-sm text-gray-500">Rata-rata Keterampilan</div><div class="mt-1 text-xl font-semibold">{{ $rataKet === null ? '-' : number_format((float) $rataKet, 1) }}</div></div>
            <div class="rounded-lg bg-gray-50 p-4"><div class="text-sm text-gray-500">Nilai Akhir</div><div class="mt-1 text-2xl font-semibold">{{ $akhir ?? '-' }}</div></div>
            <div class="rounded-lg bg-gray-50 p-4"><div class="text-sm text-gray-500">Predikat</div><div class="mt-1 text-2xl font-semibold">{{ $huruf }} <span class="text-sm font-normal text-gray-500">{{ $predikat }}</span></div></div>
            <div class="rounded-lg p-4 {{ $tuntas ? 'bg-green-50' : 'bg-red-50' }}">
                <div class="text-sm text-gray-500">Ketuntasan (KKM {{ $kkm }})</div>
                <div class="mt-1 text-lg font-semibold {{ $tuntas ? 'text-green-700' : 'text-red-700' }}">{{ $akhir === null ? '-' : ($tuntas ? 'TUNTAS' : 'BELUM TUNTAS') }}</div>
            </div>
        </div>

        @if ($reportScore->description)
            <div class="mt-4 rounded-lg border p-4 text-sm text-gray-700">
                <div class="font-medium">Deskripsi rapor</div>
                <p class="mt-1">{{ $reportScore->description }}</p>
            </div>
        @endif
    </section>
</div>
