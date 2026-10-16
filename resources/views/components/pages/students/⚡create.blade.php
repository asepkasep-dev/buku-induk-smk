<?php

use App\Models\Student;
use App\Models\Rombel;
use App\Services\StudentRegistryService;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $nis = '';

    public ?string $nisn = null;

    public ?string $nik = null;

    public string $fullName = '';

    public string $gender = 'L';

    public string $entryYear = '';

    public string $status = 'CALON';

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $address = null;

    public ?int $rombelId = null;

    public function mount(): void
    {
        $this->authorize('create', Student::class);
    }

    public function save()
    {
        $this->authorize('create', Student::class);

        $validated = $this->validate([
            'nis' => ['required', 'string', 'max:255', 'unique:students,nis'],
            'nisn' => ['nullable', 'string', 'max:255', 'unique:students,nisn'],
            'nik' => ['nullable', 'digits:16', 'unique:students,nik'],
            'fullName' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'entryYear' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['required', Rule::in(['CALON', 'AKTIF', 'LULUS', 'PINDAH', 'MENGUNDURKAN_DIRI', 'TIDAK_LULUS'])],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'rombelId' => [Rule::requiredIf(auth()->user()->role?->code === 'OPERATOR'), 'nullable', 'integer', 'exists:rombels,id'],
        ]);

        $student = app(StudentRegistryService::class)->create(auth()->user(), [
            'nis' => trim($validated['nis']),
            'nisn' => blank($validated['nisn']) ? null : trim($validated['nisn']),
            'nik' => blank($validated['nik']) ? null : trim($validated['nik']),
            'full_name' => trim($validated['fullName']),
            'gender' => $validated['gender'],
            'entry_year' => (int) $validated['entryYear'],
            'status' => $validated['status'],
            'phone' => blank($validated['phone']) ? null : trim($validated['phone']),
            'email' => blank($validated['email']) ? null : trim($validated['email']),
            'address' => blank($validated['address']) ? null : trim($validated['address']),
        ], $validated['rombelId'] ? (int) $validated['rombelId'] : null);

        session()->flash('success', 'Data siswa berhasil ditambahkan.');

        return $this->redirect(route('students.show', $student));
    }

    public function with(): array
    {
        $rombels = Rombel::query()->with('academicYear')->where('is_active', true);
        if (auth()->user()->role?->code === 'OPERATOR') {
            $rombels->whereHas('academicYear', fn ($query) => $query->where('status', 'ACTIVE'))
                ->whereIn('id', auth()->user()->activeOperatorRombelScopes()->select('rombel_id'));
        }

        return ['availableRombels' => $rombels->orderByDesc('academic_year_id')->orderBy('name')->get()];
    }
};
?>

<div>
    <h1 class="text-2xl font-semibold">Tambah Siswa</h1>

    <form wire:submit="save" class="mt-6 space-y-6 rounded-lg border bg-white p-6">
        @include('components.pages.students.form-fields')

        <div>
            <label for="rombelId" class="block text-sm font-medium">Rombel{{ auth()->user()->role?->code === 'OPERATOR' ? ' *' : ' (opsional)' }}</label>
            <select id="rombelId" wire:model="rombelId" class="mt-1 w-full rounded border-gray-300">
                <option value="">{{ auth()->user()->role?->code === 'OPERATOR' ? 'Pilih rombel tugas' : 'Nanti ditempatkan oleh admin' }}</option>
                @foreach ($availableRombels as $rombel)
                    <option value="{{ $rombel->id }}">{{ $rombel->name }} · {{ $rombel->academicYear->name }}</option>
                @endforeach
            </select>
            @error('rombelId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('students.index') }}" class="rounded border px-4 py-2 text-sm">Batal</a>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Simpan siswa
            </button>
        </div>
    </form>
</div>
