<?php

use App\Models\AcademicYear;
use App\Models\Competency;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Enrollment;
use App\Models\Rombel;
use App\Models\Semester;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $schoolLogo = null;
    public ?string $schoolLogoPath = null;
    public string $yearName = '';
    public string $yearStartDate = '';
    public string $yearEndDate = '';
    public ?int $competencyYearId = null;
    public string $competencyCode = '';
    public string $competencyName = '';
    public ?string $competencyShortName = null;
    public ?int $rombelYearId = null;
    public ?int $rombelCompetencyId = null;
    public ?int $rombelGrade = 10;
    public string $rombelName = '';
    public string $curriculumName = '';
    public ?string $curriculumVersion = null;
    public string $subjectCode = '';
    public string $subjectName = '';
    public string $subjectCategory = 'GENERAL';
    public ?int $mappingCurriculumId = null;
    public ?int $mappingSubjectId = null;
    public ?int $mappingCompetencyId = null;
    public ?int $mappingGrade = 10;
    public ?int $mappingSemester = 1;
    public ?int $enrollmentStudentId = null;
    public ?int $enrollmentYearId = null;
    public ?int $enrollmentRombelId = null;
    public ?int $offeringMappingId = null;
    public ?int $offeringRombelId = null;
    public ?int $offeringSemesterId = null;
    public string $schoolName = '';
    public ?string $schoolNpsn = null;
    public ?string $schoolNss = null;
    public ?string $schoolAddress = null;
    public ?string $schoolVillage = null;
    public ?string $schoolDistrict = null;
    public ?string $schoolCity = null;
    public ?string $schoolProvince = null;
    public ?string $schoolPostalCode = null;
    public ?string $schoolPhone = null;
    public ?string $schoolEmail = null;
    public ?string $schoolWebsite = null;
    public ?string $principalName = null;
    public ?string $principalNip = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $school = School::query()->first();
        $this->schoolName = $school?->name ?? '';
        $this->schoolNpsn = $school?->npsn;
        $this->schoolNss = $school?->nss;
        $this->schoolAddress = $school?->address;
        $this->schoolVillage = $school?->village;
        $this->schoolDistrict = $school?->district;
        $this->schoolCity = $school?->city;
        $this->schoolProvince = $school?->province;
        $this->schoolPostalCode = $school?->postal_code;
        $this->schoolPhone = $school?->phone;
        $this->schoolEmail = $school?->email;
        $this->schoolWebsite = $school?->website;
        $this->principalName = $school?->principal_name;
        $this->principalNip = $school?->principal_nip;
        $this->schoolLogoPath = $school?->logo_path;
        $this->yearStartDate = now()->startOfYear()->toDateString();
        $this->yearEndDate = now()->addYear()->startOfYear()->subDay()->toDateString();
        $latestYear = AcademicYear::query()->latest('id')->value('id');
        $this->competencyYearId = $latestYear;
        $this->rombelYearId = $latestYear;
        $this->enrollmentYearId = $latestYear;
        $this->mappingCurriculumId = Curriculum::query()->latest('id')->value('id');
        $this->mappingSubjectId = Subject::query()->latest('id')->value('id');
        $this->offeringSemesterId = Semester::query()->latest('id')->value('id');
    }

    public function saveSchool(): void
    {
        $this->authorizeAdmin();
        $this->schoolName = trim($this->schoolName);
        $data = $this->validate([
            'schoolName' => ['required', 'string', 'max:255'],
            'schoolNpsn' => ['nullable', 'digits:8'],
            'schoolNss' => ['nullable', 'string', 'max:255'],
            'schoolAddress' => ['nullable', 'string', 'max:5000'],
            'schoolVillage' => ['nullable', 'string', 'max:255'],
            'schoolDistrict' => ['nullable', 'string', 'max:255'],
            'schoolCity' => ['nullable', 'string', 'max:255'],
            'schoolProvince' => ['nullable', 'string', 'max:255'],
            'schoolPostalCode' => ['nullable', 'string', 'max:10'],
            'schoolPhone' => ['nullable', 'string', 'max:255'],
            'schoolEmail' => ['nullable', 'email', 'max:255'],
            'schoolWebsite' => ['nullable', 'url', 'max:255'],
            'principalName' => ['nullable', 'string', 'max:255'],
            'principalNip' => ['nullable', 'string', 'max:255'],
        ]);
        $school = School::query()->first() ?? new School();
        $school->fill([
            'name' => trim($data['schoolName']),
            'npsn' => $data['schoolNpsn'],
            'nss' => $data['schoolNss'],
            'address' => $data['schoolAddress'],
            'village' => $data['schoolVillage'],
            'district' => $data['schoolDistrict'],
            'city' => $data['schoolCity'],
            'province' => $data['schoolProvince'],
            'postal_code' => $data['schoolPostalCode'],
            'phone' => $data['schoolPhone'],
            'email' => $data['schoolEmail'],
            'website' => $data['schoolWebsite'],
            'principal_name' => $data['principalName'],
            'principal_nip' => $data['principalNip'],
        ]);

        if ($this->schoolLogo) {
            $this->validate([
                'schoolLogo' => ['image', 'max:2048'],
            ]);
            $path = $this->schoolLogo->store('schools', 'public');
            $school->logo_path = $path;
            $this->schoolLogoPath = $path;
            $this->schoolLogo = null;
        }

        $school->save();
        session()->flash('success', 'Profil sekolah berhasil disimpan.');
    }

    public function saveYear(): void
    {
        $this->authorizeAdmin();
        $this->yearName = trim($this->yearName);
        $data = $this->validate([
            'yearName' => ['required', 'string', 'max:255', 'unique:academic_years,name'],
            'yearStartDate' => ['required', 'date'],
            'yearEndDate' => ['required', 'date', 'after:yearStartDate'],
        ]);

        DB::transaction(function () use ($data): void {
            $year = AcademicYear::query()->create([
                'name' => trim($data['yearName']),
                'start_date' => $data['yearStartDate'],
                'end_date' => $data['yearEndDate'],
                'status' => 'INACTIVE',
            ]);
            $year->semesters()->createMany([
                ['number' => 1, 'name' => 'Semester Ganjil', 'status' => 'INACTIVE'],
                ['number' => 2, 'name' => 'Semester Genap', 'status' => 'INACTIVE'],
            ]);
        });

        $this->reset('yearName');
        session()->flash('success', 'Tahun ajaran dan semester berhasil dibuat. Aktifkan periode saat siap digunakan.');
    }

    public function activateYear(int $id): void
    {
        $this->authorizeAdmin();
        DB::transaction(function () use ($id): void {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($id);
            $otherActiveYears = AcademicYear::query()->where('status', 'ACTIVE')->where('id', '!=', $year->id)->pluck('id');
            AcademicYear::query()->where('status', 'ACTIVE')->update(['status' => 'INACTIVE']);
            Semester::query()->whereIn('academic_year_id', $otherActiveYears)->where('status', 'ACTIVE')->update(['status' => 'INACTIVE']);
            $year->update(['status' => 'ACTIVE']);
        });
        session()->flash('success', 'Tahun ajaran aktif berhasil diperbarui.');
    }

    public function activateSemester(int $id): void
    {
        $this->authorizeAdmin();
        $this->resetValidation('yearName');
        $target = Semester::query()->with('academicYear')->findOrFail($id);
        if ($target->academicYear?->status !== 'ACTIVE') {
            $this->addError('yearName', 'Aktifkan tahun ajaran sebelum mengaktifkan semesternya.');
            return;
        }
        DB::transaction(function () use ($id): void {
            $semester = Semester::query()->lockForUpdate()->findOrFail($id);
            Semester::query()->where('academic_year_id', $semester->academic_year_id)->where('status', 'ACTIVE')->update(['status' => 'INACTIVE']);
            $semester->update(['status' => 'ACTIVE']);
        });
        session()->flash('success', 'Semester aktif berhasil diperbarui.');
    }

    public function saveCompetency(): void
    {
        $this->authorizeAdmin();
        $this->competencyCode = strtoupper(trim($this->competencyCode));
        $this->competencyName = trim($this->competencyName);
        $data = $this->validate([
            'competencyCode' => ['required', 'string', 'max:255', 'unique:competencies,code'],
            'competencyName' => ['required', 'string', 'max:255'],
            'competencyShortName' => ['nullable', 'string', 'max:255'],
        ]);
        Competency::query()->create([
            'code' => strtoupper(trim($data['competencyCode'])),
            'name' => trim($data['competencyName']),
            'short_name' => blank($data['competencyShortName']) ? null : trim($data['competencyShortName']),
            'is_active' => true,
        ]);
        $this->reset('competencyCode', 'competencyName', 'competencyShortName');
        session()->flash('success', 'Konsentrasi keahlian berhasil ditambahkan.');
    }

    public function saveRombel(): void
    {
        $this->authorizeAdmin();
        $this->rombelName = trim($this->rombelName);
        $data = $this->validate([
            'rombelYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'rombelCompetencyId' => ['required', 'integer', 'exists:competencies,id'],
            'rombelGrade' => ['required', 'integer', Rule::in([10, 11, 12])],
            'rombelName' => ['required', 'string', 'max:255'],
        ]);
        $name = trim($data['rombelName']);
        if (Rombel::query()->where('academic_year_id', $data['rombelYearId'])->where('name', $name)->exists()) {
            $this->addError('rombelName', 'Nama rombel sudah dipakai pada tahun ajaran ini.');
            return;
        }
        Rombel::query()->create([
            'academic_year_id' => $data['rombelYearId'], 'competency_id' => $data['rombelCompetencyId'],
            'grade' => $data['rombelGrade'], 'name' => $name, 'is_active' => true,
        ]);
        $this->reset('rombelName');
        session()->flash('success', 'Rombel berhasil ditambahkan.');
    }

    public function saveCurriculum(): void
    {
        $this->authorizeAdmin();
        $this->curriculumName = trim($this->curriculumName);
        $this->curriculumVersion = blank($this->curriculumVersion) ? null : trim($this->curriculumVersion);
        $data = $this->validate([
            'curriculumName' => ['required', 'string', 'max:255'],
            'curriculumVersion' => ['nullable', 'string', 'max:255'],
        ]);
        $name = trim($data['curriculumName']);
        $version = blank($data['curriculumVersion']) ? null : trim($data['curriculumVersion']);
        if (Curriculum::query()->where('name', $name)->where('version', $version)->exists()) {
            $this->addError('curriculumName', 'Nama dan versi kurikulum tersebut sudah tersedia.');
            return;
        }
        Curriculum::query()->create(['name' => $name, 'version' => $version, 'is_active' => true]);
        $this->reset('curriculumName', 'curriculumVersion');
        session()->flash('success', 'Kurikulum berhasil ditambahkan.');
    }

    public function saveSubject(): void
    {
        $this->authorizeAdmin();
        $this->subjectCode = strtoupper(trim($this->subjectCode));
        $this->subjectName = trim($this->subjectName);
        $data = $this->validate([
            'subjectCode' => ['required', 'string', 'max:255', 'unique:subjects,code'],
            'subjectName' => ['required', 'string', 'max:255'],
            'subjectCategory' => ['required', Rule::in(['GENERAL', 'VOCATIONAL'])],
        ]);
        Subject::query()->create([
            'code' => strtoupper(trim($data['subjectCode'])), 'name' => trim($data['subjectName']),
            'category' => $data['subjectCategory'], 'is_active' => true,
        ]);
        $this->reset('subjectCode', 'subjectName');
        session()->flash('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function saveMapping(): void
    {
        $this->authorizeAdmin();
        $data = $this->validate([
            'mappingCurriculumId' => ['required', 'integer', 'exists:curricula,id'],
            'mappingSubjectId' => ['required', 'integer', 'exists:subjects,id'],
            'mappingCompetencyId' => ['nullable', 'integer', 'exists:competencies,id'],
            'mappingGrade' => ['required', 'integer', Rule::in([10, 11, 12])],
            'mappingSemester' => ['required', 'integer', Rule::in([1, 2])],
        ]);
        $query = CurriculumSubject::query()->where('curriculum_id', $data['mappingCurriculumId'])
            ->where('subject_id', $data['mappingSubjectId'])->where('grade', $data['mappingGrade'])
            ->where('semester_number', $data['mappingSemester']);
        $data['mappingCompetencyId'] ? $query->where('competency_id', $data['mappingCompetencyId']) : $query->whereNull('competency_id');
        if ($query->exists()) {
            $this->addError('mappingSubjectId', 'Mapel ini sudah terdaftar untuk tingkat dan semester tersebut.');
            return;
        }
        CurriculumSubject::query()->create([
            'curriculum_id' => $data['mappingCurriculumId'], 'subject_id' => $data['mappingSubjectId'],
            'competency_id' => $data['mappingCompetencyId'], 'grade' => $data['mappingGrade'],
            'semester_number' => $data['mappingSemester'], 'is_active' => true,
        ]);
        session()->flash('success', 'Mapel berhasil dimasukkan ke struktur kurikulum.');
    }

    public function saveEnrollment(): void
    {
        $this->authorizeAdmin();
        $data = $this->validate([
            'enrollmentStudentId' => ['required', 'integer', 'exists:students,id'],
            'enrollmentYearId' => ['required', 'integer', 'exists:academic_years,id'],
            'enrollmentRombelId' => ['required', 'integer', 'exists:rombels,id'],
        ]);
        $rombel = Rombel::query()->findOrFail($data['enrollmentRombelId']);
        if ((int) $rombel->academic_year_id !== (int) $data['enrollmentYearId']) {
            $this->addError('enrollmentRombelId', 'Rombel harus berada pada tahun ajaran yang dipilih.');
            return;
        }
        if (Enrollment::query()->where('student_id', $data['enrollmentStudentId'])->where('academic_year_id', $data['enrollmentYearId'])->exists()) {
            $this->addError('enrollmentStudentId', 'Siswa sudah memiliki enrollment pada tahun ajaran ini.');
            return;
        }
        DB::transaction(function () use ($data, $rombel): void {
            $enrollment = Enrollment::query()->create([
                'student_id' => $data['enrollmentStudentId'], 'academic_year_id' => $data['enrollmentYearId'],
                'rombel_id' => $rombel->id, 'status' => 'ACTIVE',
            ]);
            \App\Models\AuditLog::query()->create([
                'user_id' => auth()->id(), 'action' => 'CREATE_ENROLLMENT',
                'resource_type' => Enrollment::class, 'resource_id' => $enrollment->id,
                'after_data' => ['student_id' => $enrollment->student_id, 'academic_year_id' => $enrollment->academic_year_id, 'rombel_id' => $enrollment->rombel_id],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);
        });
        session()->flash('success', 'Siswa berhasil ditempatkan di rombel.');
    }

    public function saveOffering(): void
    {
        $this->authorizeAdmin();
        $data = $this->validate([
            'offeringMappingId' => ['required', 'integer', 'exists:curriculum_subjects,id'],
            'offeringRombelId' => ['required', 'integer', 'exists:rombels,id'],
            'offeringSemesterId' => ['required', 'integer', 'exists:semesters,id'],
        ]);
        $mapping = CurriculumSubject::query()->findOrFail($data['offeringMappingId']);
        $rombel = Rombel::query()->findOrFail($data['offeringRombelId']);
        $semester = Semester::query()->findOrFail($data['offeringSemesterId']);
        if ((int) $semester->academic_year_id !== (int) $rombel->academic_year_id
            || (int) $mapping->grade !== (int) $rombel->grade
            || ($mapping->competency_id !== null && (int) $mapping->competency_id !== (int) $rombel->competency_id)
            || (int) $mapping->semester_number !== (int) $semester->number) {
            $this->addError('offeringMappingId', 'Kelas, semester, dan tingkat atau kompetensi mapel tidak cocok.');
            return;
        }
        if (SubjectOffering::query()->where('curriculum_subject_id', $mapping->id)->where('rombel_id', $rombel->id)->where('semester_id', $semester->id)->exists()) {
            $this->addError('offeringMappingId', 'Penawaran mapel ini sudah dibuat.');
            return;
        }
        SubjectOffering::query()->create([
            'curriculum_subject_id' => $mapping->id, 'rombel_id' => $rombel->id,
            'semester_id' => $semester->id, 'is_active' => true,
        ]);
        session()->flash('success', 'Mata pelajaran berhasil ditawarkan ke rombel.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role?->code === 'ADMIN', 403);
    }

    public function render()
    {
        $this->authorizeAdmin();
        return view('components.pages.admin.academic-setup', [
            'years' => AcademicYear::query()->with('semesters')->orderByDesc('start_date')->get(),
            'competencies' => Competency::query()->where('is_active', true)->orderBy('name')->get(),
            'rombels' => Rombel::query()->with(['academicYear', 'competency'])->where('is_active', true)->orderByDesc('academic_year_id')->orderBy('name')->get(),
            'curricula' => Curriculum::query()->where('is_active', true)->orderBy('name')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'mappings' => CurriculumSubject::query()->with(['curriculum', 'subject', 'competency'])->where('is_active', true)->orderBy('grade')->orderBy('semester_number')->get(),
            'students' => Student::query()->whereIn('status', ['CALON', 'AKTIF'])->orderBy('full_name')->get(),
            'semesters' => Semester::query()->with('academicYear')->orderByDesc('academic_year_id')->orderBy('number')->get(),
        ]);
    }
};
?>

<div class="space-y-8">
    <header>
        <h1 class="text-2xl font-semibold">Pengaturan Akademik</h1>
        <p class="mt-1 text-sm text-gray-600">Siapkan periode, kelas, kurikulum, dan penempatan siswa sebelum mengisi nilai.</p>
    </header>
    @if (session('success'))<div role="status" class="rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div>@endif

    <section class="card p-5"><h2 class="text-lg font-semibold">Profil sekolah</h2>
        <form wire:submit="saveSchool" class="mt-4 grid gap-3 md:grid-cols-3">
            <label class="text-sm md:col-span-2">Nama sekolah<input wire:model="schoolName" class="mt-2">@error('schoolName')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">NPSN (8 digit)<input wire:model="schoolNpsn" inputmode="numeric" maxlength="8" class="mt-2">@error('schoolNpsn')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">NSS<input wire:model="schoolNss" class="mt-2"></label>
            <label class="text-sm">Email sekolah<input type="email" wire:model="schoolEmail" class="mt-2">@error('schoolEmail')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Telepon<input wire:model="schoolPhone" class="mt-2"></label>
            <label class="text-sm">Website<input type="url" wire:model="schoolWebsite" class="mt-2">@error('schoolWebsite')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm md:col-span-3">Alamat<input wire:model="schoolAddress" class="mt-2"></label>
            <label class="text-sm">Desa/kelurahan<input wire:model="schoolVillage" class="mt-2"></label>
            <label class="text-sm">Kecamatan<input wire:model="schoolDistrict" class="mt-2"></label>
            <label class="text-sm">Kabupaten/kota<input wire:model="schoolCity" class="mt-2"></label>
            <label class="text-sm">Provinsi<input wire:model="schoolProvince" class="mt-2"></label>
            <label class="text-sm">Kode pos<input wire:model="schoolPostalCode" class="mt-2"></label>
            <label class="text-sm">Nama kepala sekolah<input wire:model="principalName" class="mt-2"></label>
            <label class="text-sm">NIP kepala sekolah<input wire:model="principalNip" class="mt-2"></label>

            <div class="md:col-span-3 flex flex-wrap items-center gap-4 py-2 border-y my-1">
                @if ($schoolLogo)
                    <img src="{{ $schoolLogo->temporaryUrl() }}" class="h-16 w-16 object-contain rounded border p-1 bg-white">
                @elseif ($schoolLogoPath)
                    <img src="{{ asset('storage/'.$schoolLogoPath) }}" class="h-16 w-16 object-contain rounded border p-1 bg-white">
                @else
                    <div class="h-16 w-16 rounded border border-dashed border-gray-300 flex items-center justify-center text-[10px] text-gray-400">Belum Ada Logo</div>
                @endif
                <label class="text-sm flex-1">
                    Logo Sekolah (PNG/JPG, maks 2MB)
                    <input type="file" wire:model="schoolLogo" accept="image/*" class="mt-2">
                    @error('schoolLogo')<span class="text-red-600 block mt-1">{{ $message }}</span>@enderror
                </label>
            </div>

            <button class="btn-primary md:col-span-3">Simpan profil</button>
        </form>
    </section>

    <section class="card p-5">
        <h2 class="text-lg font-semibold">Tahun ajaran</h2>
        <form wire:submit="saveYear" class="mt-4 grid gap-4 md:grid-cols-4">
            <label class="text-sm">Nama<input wire:model="yearName" placeholder="2026/2027" class="mt-2">@error('yearName')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Mulai<input type="date" wire:model="yearStartDate" class="mt-2">@error('yearStartDate')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Selesai<input type="date" wire:model="yearEndDate" class="mt-2">@error('yearEndDate')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <button class="self-end btn-primary">Buat tahun ajaran</button>
        </form>
        <div class="mt-4 divide-y">@forelse ($years as $year)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3"><div><strong>{{ $year->name }}</strong> <span class="text-sm text-gray-600">{{ $year->start_date->format('d/m/Y') }} – {{ $year->end_date->format('d/m/Y') }}</span><div class="mt-1 flex gap-2 text-xs">@foreach ($year->semesters as $semester)<span class="rounded bg-gray-100 px-2 py-1">{{ $semester->name }} · {{ $semester->status }}</span>@endforeach</div></div><div class="flex gap-2">@if ($year->status !== 'ACTIVE')<button wire:click="activateYear({{ $year->id }})" class="btn-secondary">Jadikan aktif</button>@endif @foreach ($year->semesters as $semester) @if ($semester->status !== 'ACTIVE')<button wire:click="activateSemester({{ $semester->id }})" class="btn-secondary">Aktifkan semester {{ $semester->number }}</button>@endif @endforeach</div></div>
        @empty <p class="py-3 text-sm text-gray-500">Belum ada tahun ajaran.</p>@endforelse</div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card p-5"><h2 class="text-lg font-semibold">Konsentrasi keahlian</h2>
            <form wire:submit="saveCompetency" class="mt-4 grid gap-3 sm:grid-cols-3">
                <label class="text-sm">Kode<input wire:model="competencyCode" class="mt-2">@error('competencyCode')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Nama<input wire:model="competencyName" class="mt-2">@error('competencyName')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Nama singkat<input wire:model="competencyShortName" class="mt-2"></label>
                <button class="btn-primary sm:col-span-3">Tambah konsentrasi</button>
            </form>
            <ul class="mt-3 list-inside list-disc text-sm">@foreach ($competencies as $item)<li>{{ $item->name }}</li>@endforeach</ul>
        </section>
        <section class="card p-5"><h2 class="text-lg font-semibold">Rombel</h2>
            <form wire:submit="saveRombel" class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Tahun ajaran<select wire:model="rombelYearId" class="mt-2"><option value="">Pilih</option>@foreach ($years as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select>@error('rombelYearId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Konsentrasi<select wire:model="rombelCompetencyId" class="mt-2"><option value="">Pilih</option>@foreach ($competencies as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>@error('rombelCompetencyId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Tingkat<select wire:model="rombelGrade" class="mt-2"><option value="10">10</option><option value="11">11</option><option value="12">12</option></select></label>
                <label class="text-sm">Nama<input wire:model="rombelName" placeholder="X RPL 1" class="mt-2">@error('rombelName')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <button class="btn-primary sm:col-span-2">Tambah rombel</button>
            </form>
            <ul class="mt-3 divide-y text-sm">@foreach ($rombels as $rombel)<li class="py-2">{{ $rombel->name }} · {{ $rombel->academicYear->name }}</li>@endforeach</ul>
        </section>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card p-5"><h2 class="text-lg font-semibold">Kurikulum dan mata pelajaran</h2>
            <form wire:submit="saveCurriculum" class="mt-4 grid gap-3 sm:grid-cols-3">
                <label class="text-sm sm:col-span-2">Nama kurikulum<input wire:model="curriculumName" class="mt-2">@error('curriculumName')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Versi<input wire:model="curriculumVersion" class="mt-2"></label>
                <button class="btn-primary sm:col-span-3">Tambah kurikulum</button>
            </form>
            <form wire:submit="saveSubject" class="mt-5 grid gap-3 sm:grid-cols-3">
                <label class="text-sm">Kode mapel<input wire:model="subjectCode" class="mt-2">@error('subjectCode')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Nama mapel<input wire:model="subjectName" class="mt-2">@error('subjectName')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Kategori<select wire:model="subjectCategory" class="mt-2"><option value="GENERAL">Umum</option><option value="VOCATIONAL">Kejuruan</option></select></label>
                <button class="btn-primary sm:col-span-3">Tambah mata pelajaran</button>
            </form>
        </section>
        <section class="card p-5"><h2 class="text-lg font-semibold">Struktur kurikulum</h2>
            <form wire:submit="saveMapping" class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="text-sm">Kurikulum<select wire:model="mappingCurriculumId" class="mt-2"><option value="">Pilih</option>@foreach ($curricula as $item)<option value="{{ $item->id }}">{{ $item->name }} {{ $item->version }}</option>@endforeach</select>@error('mappingCurriculumId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Mata pelajaran<select wire:model="mappingSubjectId" class="mt-2"><option value="">Pilih</option>@foreach ($subjects as $item)<option value="{{ $item->id }}">{{ $item->code }} · {{ $item->name }}</option>@endforeach</select>@error('mappingSubjectId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Konsentrasi<select wire:model="mappingCompetencyId" class="mt-2"><option value="">Semua konsentrasi</option>@foreach ($competencies as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                <label class="text-sm">Tingkat<select wire:model="mappingGrade" class="mt-2"><option value="10">10</option><option value="11">11</option><option value="12">12</option></select></label>
                <label class="text-sm">Semester<select wire:model="mappingSemester" class="mt-2"><option value="1">Ganjil</option><option value="2">Genap</option></select></label>
                <button class="self-end btn-primary">Masukkan mapel</button>
            </form>
        </section>
    </div>

    <section class="card p-5"><h2 class="text-lg font-semibold">Tawarkan mapel ke rombel</h2>
        <form wire:submit="saveOffering" class="mt-4 grid gap-3 md:grid-cols-4">
            <label class="text-sm">Mapel kurikulum<select wire:model="offeringMappingId" class="mt-2"><option value="">Pilih</option>@foreach ($mappings as $mapping)<option value="{{ $mapping->id }}">{{ $mapping->subject->name }} · Kelas {{ $mapping->grade }} · Semester {{ $mapping->semester_number }}</option>@endforeach</select>@error('offeringMappingId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Rombel<select wire:model="offeringRombelId" class="mt-2"><option value="">Pilih</option>@foreach ($rombels as $rombel)<option value="{{ $rombel->id }}">{{ $rombel->name }} · {{ $rombel->academicYear->name }}</option>@endforeach</select>@error('offeringRombelId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Semester<select wire:model="offeringSemesterId" class="mt-2"><option value="">Pilih</option>@foreach ($semesters as $semester)<option value="{{ $semester->id }}">{{ $semester->academicYear->name }} · {{ $semester->name }}</option>@endforeach</select>@error('offeringSemesterId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <button class="self-end btn-primary">Buat penawaran</button>
        </form>
    </section>

    <section class="card p-5"><h2 class="text-lg font-semibold">Tempatkan siswa ke rombel</h2>
        <form wire:submit="saveEnrollment" class="mt-4 grid gap-3 md:grid-cols-4">
            <label class="text-sm">Siswa<select wire:model="enrollmentStudentId" class="mt-2"><option value="">Pilih</option>@foreach ($students as $student)<option value="{{ $student->id }}">{{ $student->nis }} · {{ $student->full_name }}</option>@endforeach</select>@error('enrollmentStudentId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Tahun ajaran<select wire:model="enrollmentYearId" class="mt-2"><option value="">Pilih</option>@foreach ($years as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select>@error('enrollmentYearId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Rombel<select wire:model="enrollmentRombelId" class="mt-2"><option value="">Pilih</option>@foreach ($rombels as $rombel)<option value="{{ $rombel->id }}">{{ $rombel->name }} · {{ $rombel->academicYear->name }}</option>@endforeach</select>@error('enrollmentRombelId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <button class="self-end btn-primary">Tempatkan siswa</button>
        </form>
    </section>
</div>
