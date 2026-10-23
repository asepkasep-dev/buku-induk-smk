<?php

use App\Models\Student;
use App\Services\StudentImportService;
use App\Services\StudentRegistryService;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?UploadedFile $importFile = null;
    public array $previewRows = [];
    public array $rowErrors = [];
    public ?int $readyCount = null;

    public function mount(): void
    {
        $this->authorize('create', Student::class);
        abort_unless(auth()->user()->role?->code === 'ADMIN', 403);
    }

    private function validateFile(): void
    {
        $this->resetValidation();
        $this->validate([
            'importFile' => ['required', 'file', 'max:10240', 'extensions:xlsx,xls'],
        ]);
    }

    public function previewImport(): void
    {
        $this->authorize('create', Student::class);
        abort_unless(auth()->user()->role?->code === 'ADMIN', 403);
        $this->validateFile();
        $result = app(StudentImportService::class)->inspect($this->importFile);
        $this->previewRows = $result['preview'];
        $this->rowErrors = $result['errors'];
        $this->readyCount = $result['errors'] === [] ? $result['count'] : null;
    }

    public function import(): mixed
    {
        $this->authorize('create', Student::class);
        abort_unless(auth()->user()->role?->code === 'ADMIN', 403);
        $this->validateFile();
        $service = app(StudentImportService::class);
        $result = $service->inspect($this->importFile);
        $this->previewRows = $result['preview'];
        $this->rowErrors = $result['errors'];
        $this->readyCount = null;
        if ($result['errors'] !== []) {
            $this->addError('importFile', 'Perbaiki baris yang ditandai sebelum mengimpor.');

            return null;
        }
        if ($result['count'] === 0) {
            $this->addError('importFile', 'Tidak ada baris siswa untuk diimpor.');

            return null;
        }

        $count = $service->import(auth()->user(), $result['rows'], app(StudentRegistryService::class));
        session()->flash('success', "{$count} data siswa berhasil diimpor.");

        return $this->redirect(route('students.index'));
    }
};
?>

<div class="mx-auto max-w-4xl space-y-6">
    <header>
        <h1 class="text-2xl font-semibold">Impor data siswa</h1>
        <p class="mt-1 text-sm text-gray-600">Unggah berkas Excel (.xlsx) untuk menambahkan banyak siswa sekaligus. Semua baris diperiksa sebelum disimpan.</p>
    </header>

    <section class="card p-6">
        <p class="text-sm text-gray-600">
            Gunakan judul kolom pada baris pertama:
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">nis, nisn, nik, full_name, gender, entry_year, status, phone, email, address</code>.
            Kolom NIS, nama lengkap, jenis kelamin (L/P), tahun masuk, dan status wajib diisi. Baris yang tidak valid membatalkan seluruh impor.
        </p>
        <p class="mt-2 text-sm">
            <a href="{{ route('students.import-template') }}" class="font-medium text-brand-700 hover:underline">Unduh template Excel (.xlsx)</a>.
            Maksimal {{ number_format(500) }} baris dan ukuran file 10 MB.
        </p>

        <form wire:submit="previewImport" class="mt-5 space-y-4">
            <div>
                <label for="import-file" class="field-label">Berkas Excel (.xlsx)</label>
                <input
                    type="file"
                    id="import-file"
                    wire:model="importFile"
                    accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                >
            </div>

            @error('importFile')<p class="field-error">{{ $message }}</p>@enderror

            <div wire:loading wire:target="importFile" class="text-sm text-gray-600">Mengunggah berkas…</div>

            <button type="submit" class="btn-primary">Periksa berkas</button>
        </form>
    </section>

    @if ($rowErrors !== [])
        <section role="alert" class="rounded-lg border border-red-200 bg-red-50 p-5">
            <h2 class="font-semibold text-red-800">Berkas belum bisa diimpor</h2>
            <ul class="mt-2 list-inside list-disc text-sm text-red-700">@foreach ($rowErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
    @endif

    @if ($readyCount !== null)
        <section class="card p-5">
            <h2 class="font-semibold">Pratinjau · {{ $readyCount }} baris siap</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b"><th class="py-2 pr-4">NIS</th><th class="py-2 pr-4">Nama</th><th class="py-2 pr-4">JK</th><th class="py-2 pr-4">Tahun masuk</th><th class="py-2">Status</th></tr></thead>
                    <tbody>
                        @foreach ($previewRows as $row)<tr class="border-b"><td class="py-2 pr-4">{{ $row['nis'] }}</td><td class="py-2 pr-4">{{ $row['full_name'] }}</td><td class="py-2 pr-4">{{ $row['gender'] }}</td><td class="py-2 pr-4">{{ $row['entry_year'] }}</td><td class="py-2">{{ $row['status'] }}</td></tr>@endforeach
                    </tbody>
                </table>
            </div>
            @if ($readyCount > count($previewRows))<p class="mt-2 text-xs text-gray-500">Menampilkan 10 baris pertama.</p>@endif
            <button wire:click="import" wire:confirm="Impor {{ $readyCount }} data siswa sekarang?" class="btn-primary mt-4">Impor {{ $readyCount }} siswa</button>
        </section>
    @endif
</div>
