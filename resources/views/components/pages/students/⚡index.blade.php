<?php

use App\Models\Student;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        $user = auth()->user();

        $students = match ($user->role?->code) {
            'ADMIN', 'KEPALA_SEKOLAH' => Student::query(),
            'OPERATOR' => $user->accessibleStudentsAsOperator(),
            'WALI_KELAS' => $user->accessibleStudentsAsHomeroom(),
            'STUDENT' => Student::query()->whereKey($user->student_id),
            default => Student::query()->whereRaw('1 = 0'),
        };

        return [
            'students' => $students
                ->orderBy('full_name')
                ->get(),
        ];
    }
};
?>

<div>
    <h1 class="text-2xl font-semibold">Daftar Siswa</h1>

    <div class="mt-6 overflow-hidden rounded-lg border bg-white">
        <table class="min-w-full divide-y">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium">NIS</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Nama</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Tahun Masuk</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Aksi</th>
                </tr>
            </thead>

            <tbody class="divide-y">
                @forelse ($students as $student)
                    <tr>
                        <td class="px-4 py-3">{{ $student->nis }}</td>
                        <td class="px-4 py-3">{{ $student->full_name }}</td>
                        <td class="px-4 py-3">{{ $student->entry_year }}</td>
                        <td class="px-4 py-3">{{ $student->status }}</td>
                        <td class="px-4 py-3">
                            <a
                                href="{{ route('students.show', $student) }}"
                                class="text-blue-600 hover:underline"
                            >
                                Lihat
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">
                            Belum ada data siswa.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>