<?php

use App\Models\Student;
use App\Services\StudentRegistryService;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public Student $student;

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

    public function mount(Student $student): void
    {
        $this->authorize('update', $student);
        $this->student = $student;
        $this->nis = $student->nis;
        $this->nisn = $student->nisn;
        $this->nik = $student->nik;
        $this->fullName = $student->full_name;
        $this->gender = $student->gender;
        $this->entryYear = (string) $student->entry_year;
        $this->status = $student->status;
        $this->phone = $student->phone;
        $this->email = $student->email;
        $this->address = $student->address;
    }

    public function save()
    {
        $this->authorize('update', $this->student);

        $validated = $this->validate([
            'nis' => ['required', 'string', 'max:255', Rule::unique('students', 'nis')->ignore($this->student->id)],
            'nisn' => ['nullable', 'string', 'max:255', Rule::unique('students', 'nisn')->ignore($this->student->id)],
            'nik' => ['nullable', 'digits:16', Rule::unique('students', 'nik')->ignore($this->student->id)],
            'fullName' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'entryYear' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['required', Rule::in(['CALON', 'AKTIF', 'LULUS', 'PINDAH', 'MENGUNDURKAN_DIRI', 'TIDAK_LULUS'])],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->student = app(StudentRegistryService::class)->update(
            auth()->user(),
            $this->student,
            [
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
            ]
        );

        session()->flash('success', 'Data siswa berhasil diperbarui.');

        return $this->redirect(route('students.show', $this->student));
    }
};
?>

<div>
    <h1 class="text-2xl font-semibold">Ubah Data Siswa</h1>
    <p class="mt-1 text-sm text-gray-500">{{ $student->full_name }} · NIS {{ $student->nis }}</p>

    <form wire:submit="save" class="mt-6 space-y-6 rounded-lg border bg-white p-6">
        @include('components.pages.students.form-fields')

        <div class="flex justify-end gap-3">
            <a href="{{ route('students.show', $student) }}" class="rounded border px-4 py-2 text-sm">Batal</a>
            <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Simpan perubahan
            </button>
        </div>
    </form>
</div>
