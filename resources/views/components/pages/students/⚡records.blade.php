<?php

use App\Models\AttendanceSummary;
use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\DiplomaScore;
use App\Models\Enrollment;
use App\Models\Extracurricular;
use App\Models\ExtracurricularRecord;
use App\Models\Graduation;
use App\Models\Guardian;
use App\Models\PKLRecord;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\UKKRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Student $student;
    public ?int $guardianId = null;
    public string $guardianName = '';
    public ?string $guardianNik = null;
    public ?string $guardianPhone = null;
    public ?string $guardianEmail = null;
    public ?string $guardianOccupation = null;
    public ?string $guardianAddress = null;
    public string $guardianRelationship = 'FATHER';
    public bool $guardianIsPrimary = false;

    public ?int $attendanceSemesterId = null;
    public int $sick = 0;
    public int $excused = 0;
    public int $absent = 0;

    public string $extracurricularName = '';
    public ?int $activityId = null;
    public ?int $activitySemesterId = null;
    public ?string $activityPredicate = null;
    public ?string $activityDescription = null;

    public ?int $pklId = null;
    public string $pklPartner = '';
    public ?string $pklLocation = null;
    public string $pklStartDate = '';
    public string $pklEndDate = '';
    public ?int $pklCompetencyScore = null;
    public ?string $pklCompetencyPredicate = null;
    public ?int $pklAttitudeScore = null;
    public ?string $pklAttitudePredicate = null;
    public ?string $pklDescription = null;

    public ?int $ukkCompetencyId = null;
    public ?int $ukkScore = null;
    public ?string $ukkPredicate = null;

    public ?int $diplomaSubjectId = null;
    public ?float $diplomaScore = null;
    public ?string $diplomaPredicate = null;

    public ?string $graduationNotes = null;
    public string $documentType = '';
    public $documentFile = null;

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        $this->student = $student;
        $activeSemester = Semester::query()->where('status', 'ACTIVE')->latest('id')->first();
        $this->attendanceSemesterId = $activeSemester?->id;
        $this->activitySemesterId = $activeSemester?->id;
        $this->pklStartDate = now()->toDateString();
        $this->pklEndDate = now()->toDateString();
        $this->graduationNotes = $student->graduation?->notes;
    }

    private function authorizePermission(string $permission): void
    {
        $this->authorize('view', $this->student);
        abort_unless(auth()->user()->hasPermission($permission), 403);
    }

    private function audit(string $action, string $type, ?int $id, array $after, ?array $before = null): void
    {
        AuditLog::query()->create([
            'user_id' => auth()->id(), 'action' => $action,
            'resource_type' => $type, 'resource_id' => $id,
            'before_data' => $before, 'after_data' => $after,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);
    }

    private function ensureSemesterEnrollment(int $semesterId, string $field): bool
    {
        $semester = Semester::query()->findOrFail($semesterId);
        $hasEnrollment = $this->student->enrollments()
            ->where('academic_year_id', $semester->academic_year_id)
            ->where('status', 'ACTIVE')->exists();
        if (! $hasEnrollment) {
            $this->addError($field, 'Siswa harus memiliki enrollment aktif pada tahun ajaran semester ini.');
        }

        return $hasEnrollment;
    }

    private function graduationRequirementsComplete(): bool
    {
        $enrollment = Enrollment::query()
            ->with(['academicYear', 'rombel'])
            ->where('student_id', $this->student->id)
            ->where('status', 'ACTIVE')
            ->get()
            ->sortByDesc(fn (Enrollment $item) => $item->academicYear?->start_date)
            ->first();

        if (! $enrollment?->rombel) {
            return false;
        }

        $requiredSubjectIds = SubjectOffering::query()
            ->where('rombel_id', $enrollment->rombel_id)
            ->where('is_active', true)
            ->whereHas('curriculumSubject', fn ($query) => $query->where('is_active', true))
            ->with('curriculumSubject')
            ->get()
            ->pluck('curriculumSubject.subject_id')
            ->filter()
            ->unique()
            ->values();

        if ($requiredSubjectIds->isEmpty()) {
            return false;
        }

        $finalizedSubjectCount = $this->student->diplomaScores()
            ->whereIn('subject_id', $requiredSubjectIds)
            ->where('status', 'FINALIZED')
            ->distinct('subject_id')
            ->count('subject_id');

        return $finalizedSubjectCount === $requiredSubjectIds->count()
            && $this->student->ukkRecords()
                ->where('competency_id', $enrollment->rombel->competency_id)
                ->where('status', 'FINALIZED')
                ->exists();
    }

    public function saveGuardian(): void
    {
        $this->authorizePermission('MANAGE_GUARDIAN');
        $this->resetValidation('guardianId');
        $data = $this->validate([
            'guardianId' => ['nullable', 'integer', 'exists:guardians,id'],
            'guardianName' => [Rule::requiredIf(! $this->guardianId), 'nullable', 'string', 'max:255'],
            'guardianNik' => ['nullable', 'digits:16', Rule::unique('guardians', 'nik')->ignore($this->guardianId)],
            'guardianPhone' => ['nullable', 'string', 'max:255'],
            'guardianEmail' => ['nullable', 'email', 'max:255'],
            'guardianOccupation' => ['nullable', 'string', 'max:255'],
            'guardianAddress' => ['nullable', 'string', 'max:5000'],
            'guardianRelationship' => ['required', Rule::in(['FATHER', 'MOTHER', 'GUARDIAN', 'FOSTER_GUARDIAN', 'INSTITUTION_GUARDIAN', 'OTHER'])],
            'guardianIsPrimary' => ['boolean'],
        ]);

        DB::transaction(function () use ($data): void {
            $guardian = $data['guardianId']
                ? Guardian::query()->lockForUpdate()->findOrFail($data['guardianId'])
                : Guardian::query()->create([
                    'full_name' => trim($data['guardianName']),
                    'nik' => $data['guardianNik'], 'phone' => $data['guardianPhone'],
                    'email' => $data['guardianEmail'], 'occupation' => $data['guardianOccupation'],
                    'address' => $data['guardianAddress'],
                ]);

            if (StudentGuardian::query()->where('student_id', $this->student->id)->where('guardian_id', $guardian->id)->exists()) {
                $this->addError('guardianId', 'Wali tersebut sudah terhubung ke data siswa ini.');
                return;
            }
            if ($data['guardianIsPrimary']) {
                StudentGuardian::query()->where('student_id', $this->student->id)->update(['is_primary' => false]);
            }
            $link = StudentGuardian::query()->create([
                'student_id' => $this->student->id, 'guardian_id' => $guardian->id,
                'relationship' => $data['guardianRelationship'], 'is_primary' => $data['guardianIsPrimary'],
            ]);
            $this->audit('LINK_STUDENT_GUARDIAN', StudentGuardian::class, $link->id, [
                'student_id' => $this->student->id, 'guardian_id' => $guardian->id,
                'relationship' => $link->relationship, 'is_primary' => $link->is_primary,
            ]);
        });

        if (! $this->getErrorBag()->has('guardianId')) {
            $this->reset('guardianId', 'guardianName', 'guardianNik', 'guardianPhone', 'guardianEmail', 'guardianOccupation', 'guardianAddress', 'guardianIsPrimary');
            $this->guardianRelationship = 'FATHER';
            session()->flash('success', 'Data wali berhasil disimpan.');
        }
    }

    public function makePrimaryGuardian(int $id): void
    {
        $this->authorizePermission('MANAGE_GUARDIAN');
        DB::transaction(function () use ($id): void {
            $link = StudentGuardian::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            StudentGuardian::query()->where('student_id', $this->student->id)->update(['is_primary' => false]);
            $link->update(['is_primary' => true]);
            $this->audit('SET_PRIMARY_GUARDIAN', StudentGuardian::class, $link->id, ['is_primary' => true]);
        });
        session()->flash('success', 'Wali utama berhasil diperbarui.');
    }

    public function saveAttendance(): void
    {
        $this->authorizePermission('MANAGE_ATTENDANCE');
        $this->resetValidation('attendanceSemesterId');
        $data = $this->validate([
            'attendanceSemesterId' => ['required', 'integer', 'exists:semesters,id'],
            'sick' => ['required', 'integer', 'min:0', 'max:1000'],
            'excused' => ['required', 'integer', 'min:0', 'max:1000'],
            'absent' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);
        if (! $this->ensureSemesterEnrollment($data['attendanceSemesterId'], 'attendanceSemesterId')) return;

        $saved = DB::transaction(function () use ($data): bool {
            $record = AttendanceSummary::query()->where('student_id', $this->student->id)
                ->where('semester_id', $data['attendanceSemesterId'])->lockForUpdate()->first();
            if ($record && $record->status !== 'DRAFT') {
                $this->addError('attendanceSemesterId', 'Ringkasan kehadiran sudah dikunci atau difinalisasi.');
                return false;
            }
            $before = $record?->only(['sick', 'excused', 'absent']);
            $record ??= new AttendanceSummary(['student_id' => $this->student->id, 'semester_id' => $data['attendanceSemesterId']]);
            $record->fill(['sick' => $data['sick'], 'excused' => $data['excused'], 'absent' => $data['absent'], 'status' => 'DRAFT']);
            $record->save();
            $this->audit('SAVE_ATTENDANCE_SUMMARY', AttendanceSummary::class, $record->id, ['changed_fields' => ['sick', 'excused', 'absent']], $before);
            return true;
        });
        if ($saved) session()->flash('success', 'Ringkasan kehadiran tersimpan sebagai draft.');
    }

    public function lockAttendance(int $id): void
    {
        $this->authorizePermission('LOCK_ATTENDANCE');
        DB::transaction(function () use ($id): void {
            $record = AttendanceSummary::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'DRAFT', 409);
            $record->update(['status' => 'LOCKED', 'locked_at' => now(), 'locked_by' => auth()->id()]);
            $this->audit('LOCK_ATTENDANCE', AttendanceSummary::class, $record->id, ['status' => 'LOCKED']);
        });
    }

    public function finalizeAttendance(int $id): void
    {
        $this->authorizePermission('FINALIZE_ATTENDANCE');
        DB::transaction(function () use ($id): void {
            $record = AttendanceSummary::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'LOCKED', 409);
            $record->update(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => auth()->id()]);
            $this->audit('FINALIZE_ATTENDANCE', AttendanceSummary::class, $record->id, ['status' => 'FINALIZED']);
        });
    }

    public function addExtracurricular(): void
    {
        abort_unless(auth()->user()->role?->code === 'ADMIN', 403);
        $data = $this->validate(['extracurricularName' => ['required', 'string', 'max:255', 'unique:extracurriculars,name']]);
        DB::transaction(function () use ($data): void {
            $activity = Extracurricular::query()->create(['name' => trim($data['extracurricularName']), 'is_active' => true]);
            $this->audit('CREATE_EXTRACURRICULAR', Extracurricular::class, $activity->id, ['name' => $activity->name]);
        });
        $this->reset('extracurricularName');
        session()->flash('success', 'Kegiatan ekstrakurikuler berhasil ditambahkan.');
    }

    public function saveExtracurricular(): void
    {
        $this->authorizePermission('MANAGE_EXTRACURRICULAR');
        $this->resetValidation('activityId');
        $data = $this->validate([
            'activityId' => ['required', 'integer', 'exists:extracurriculars,id'],
            'activitySemesterId' => ['required', 'integer', 'exists:semesters,id'],
            'activityPredicate' => ['nullable', 'string', 'max:100'],
            'activityDescription' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! $this->ensureSemesterEnrollment($data['activitySemesterId'], 'activitySemesterId')) return;
        $saved = DB::transaction(function () use ($data): bool {
            $record = ExtracurricularRecord::query()->where('student_id', $this->student->id)
                ->where('extracurricular_id', $data['activityId'])->where('semester_id', $data['activitySemesterId'])
                ->lockForUpdate()->first();
            if ($record && $record->status !== 'DRAFT') {
                $this->addError('activityId', 'Catatan ekstrakurikuler sudah dikunci atau difinalisasi.');
                return false;
            }
            $before = $record?->only(['predicate', 'description']);
            $record ??= new ExtracurricularRecord([
                'student_id' => $this->student->id,
                'extracurricular_id' => $data['activityId'], 'semester_id' => $data['activitySemesterId'],
            ]);
            $record->fill(['predicate' => $data['activityPredicate'], 'description' => $data['activityDescription'], 'status' => 'DRAFT']);
            $record->save();
            $this->audit('SAVE_EXTRACURRICULAR_RECORD', ExtracurricularRecord::class, $record->id, ['changed_fields' => ['predicate', 'description']], $before);
            return true;
        });
        if ($saved) session()->flash('success', 'Catatan ekstrakurikuler tersimpan sebagai draft.');
    }

    public function lockExtracurricular(int $id): void
    {
        $this->authorizePermission('LOCK_EXTRACURRICULAR');
        DB::transaction(function () use ($id): void {
            $record = ExtracurricularRecord::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'DRAFT', 409);
            $record->update(['status' => 'LOCKED', 'locked_at' => now(), 'locked_by' => auth()->id()]);
            $this->audit('LOCK_EXTRACURRICULAR', ExtracurricularRecord::class, $record->id, ['status' => 'LOCKED']);
        });
    }

    public function finalizeExtracurricular(int $id): void
    {
        $this->authorizePermission('FINALIZE_EXTRACURRICULAR');
        DB::transaction(function () use ($id): void {
            $record = ExtracurricularRecord::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'LOCKED', 409);
            $record->update(['status' => 'FINALIZED', 'finalized_at' => now(), 'finalized_by' => auth()->id()]);
            $this->audit('FINALIZE_EXTRACURRICULAR', ExtracurricularRecord::class, $record->id, ['status' => 'FINALIZED']);
        });
    }

    public function savePkl(): void
    {
        $this->authorizePermission('MANAGE_PKL');
        $this->resetValidation('pklId');
        $data = $this->validate([
            'pklId' => ['nullable', 'integer', 'exists:pkl_records,id'],
            'pklPartner' => ['required', 'string', 'max:255'], 'pklLocation' => ['nullable', 'string', 'max:5000'],
            'pklStartDate' => ['required', 'date'], 'pklEndDate' => ['required', 'date', 'after_or_equal:pklStartDate'],
            'pklCompetencyScore' => ['nullable', 'integer', 'between:0,100'], 'pklCompetencyPredicate' => ['nullable', 'string', 'max:100'],
            'pklAttitudeScore' => ['nullable', 'integer', 'between:0,100'], 'pklAttitudePredicate' => ['nullable', 'string', 'max:100'],
            'pklDescription' => ['nullable', 'string', 'max:5000'],
        ]);
        $saved = DB::transaction(function () use ($data): bool {
            $record = $data['pklId'] ? PKLRecord::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($data['pklId']) : new PKLRecord(['student_id' => $this->student->id]);
            if ($record->exists && $record->status !== 'DRAFT') {
                $this->addError('pklId', 'Catatan PKL yang telah difinalisasi tidak dapat diubah.');
                return false;
            }
            $record->fill([
                'partner_name' => trim($data['pklPartner']), 'location' => $data['pklLocation'],
                'start_date' => $data['pklStartDate'], 'end_date' => $data['pklEndDate'],
                'competency_score' => $data['pklCompetencyScore'], 'competency_predicate' => $data['pklCompetencyPredicate'],
                'attitude_score' => $data['pklAttitudeScore'], 'attitude_predicate' => $data['pklAttitudePredicate'],
                'description' => $data['pklDescription'], 'status' => 'DRAFT',
            ]);
            $record->save();
            $this->audit('SAVE_PKL_RECORD', PKLRecord::class, $record->id, ['changed_fields' => ['partner_name', 'location', 'start_date', 'end_date', 'competency_score', 'attitude_score']]);
            return true;
        });
        if ($saved) {
            $this->reset('pklId', 'pklPartner', 'pklLocation', 'pklCompetencyScore', 'pklCompetencyPredicate', 'pklAttitudeScore', 'pklAttitudePredicate', 'pklDescription');
            session()->flash('success', 'Catatan PKL tersimpan sebagai draft.');
        }
    }

    public function editPkl(int $id): void
    {
        $this->authorizePermission('MANAGE_PKL');
        $record = PKLRecord::query()->where('student_id', $this->student->id)->findOrFail($id);
        abort_unless($record->status === 'DRAFT', 409);
        $this->pklId = $record->id;
        $this->pklPartner = $record->partner_name;
        $this->pklLocation = $record->location;
        $this->pklStartDate = $record->start_date->toDateString();
        $this->pklEndDate = $record->end_date->toDateString();
        $this->pklCompetencyScore = $record->competency_score;
        $this->pklCompetencyPredicate = $record->competency_predicate;
        $this->pklAttitudeScore = $record->attitude_score;
        $this->pklAttitudePredicate = $record->attitude_predicate;
        $this->pklDescription = $record->description;
    }

    public function finalizePkl(int $id): void
    {
        $this->authorizePermission('FINALIZE_PKL');
        DB::transaction(function () use ($id): void {
            $record = PKLRecord::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'DRAFT', 409);
            abort_unless($record->competency_score !== null && $record->attitude_score !== null, 422, 'Nilai kompetensi dan sikap wajib diisi sebelum finalisasi.');
            $record->update(['status' => 'FINALIZED']);
            $this->audit('FINALIZE_PKL', PKLRecord::class, $record->id, ['status' => 'FINALIZED']);
        });
    }

    public function saveUkk(): void
    {
        $this->authorizePermission('MANAGE_UKK');
        $this->resetValidation('ukkCompetencyId');
        $data = $this->validate([
            'ukkCompetencyId' => ['required', 'integer', 'exists:competencies,id'],
            'ukkScore' => ['nullable', 'integer', 'between:0,100'], 'ukkPredicate' => ['nullable', 'string', 'max:100'],
        ]);
        $saved = DB::transaction(function () use ($data): bool {
            $record = UKKRecord::query()->where('student_id', $this->student->id)->where('competency_id', $data['ukkCompetencyId'])->lockForUpdate()->first();
            if ($record && $record->status !== 'DRAFT') {
                $this->addError('ukkCompetencyId', 'Nilai UKK telah difinalisasi.');
                return false;
            }
            $record ??= new UKKRecord(['student_id' => $this->student->id, 'competency_id' => $data['ukkCompetencyId']]);
            $record->fill(['final_score' => $data['ukkScore'], 'predicate' => $data['ukkPredicate'], 'status' => 'DRAFT']);
            $record->save();
            $this->audit('SAVE_UKK_RECORD', UKKRecord::class, $record->id, ['changed_fields' => ['final_score', 'predicate']]);
            return true;
        });
        if ($saved) session()->flash('success', 'Nilai UKK tersimpan sebagai draft.');
    }

    public function finalizeUkk(int $id): void
    {
        $this->authorizePermission('FINALIZE_UKK');
        DB::transaction(function () use ($id): void {
            $record = UKKRecord::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'DRAFT', 409);
            abort_unless($record->final_score !== null, 422, 'Nilai UKK harus diisi sebelum finalisasi.');
            $record->update(['status' => 'FINALIZED']);
            $this->audit('FINALIZE_UKK', UKKRecord::class, $record->id, ['status' => 'FINALIZED']);
        });
    }

    public function saveDiplomaScore(): void
    {
        $this->authorizePermission('MANAGE_DIPLOMA_SCORE');
        $this->resetValidation('diplomaSubjectId');
        $data = $this->validate([
            'diplomaSubjectId' => ['required', 'integer', 'exists:subjects,id'],
            'diplomaScore' => ['nullable', 'numeric', 'between:0,100'], 'diplomaPredicate' => ['nullable', 'string', 'max:100'],
        ]);
        $saved = DB::transaction(function () use ($data): bool {
            $record = DiplomaScore::query()->where('student_id', $this->student->id)->where('subject_id', $data['diplomaSubjectId'])->lockForUpdate()->first();
            if ($record && $record->status !== 'DRAFT') {
                $this->addError('diplomaSubjectId', 'Nilai ijazah telah difinalisasi.');
                return false;
            }
            $record ??= new DiplomaScore(['student_id' => $this->student->id, 'subject_id' => $data['diplomaSubjectId']]);
            $record->fill(['final_score' => $data['diplomaScore'], 'predicate' => $data['diplomaPredicate'], 'status' => 'DRAFT']);
            $record->save();
            $this->audit('SAVE_DIPLOMA_SCORE', DiplomaScore::class, $record->id, ['changed_fields' => ['final_score', 'predicate']]);
            return true;
        });
        if ($saved) session()->flash('success', 'Nilai ijazah tersimpan sebagai draft.');
    }

    public function finalizeDiplomaScore(int $id): void
    {
        $this->authorizePermission('FINALIZE_DIPLOMA_SCORE');
        DB::transaction(function () use ($id): void {
            $record = DiplomaScore::query()->where('student_id', $this->student->id)->lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'DRAFT', 409);
            abort_unless($record->final_score !== null, 422, 'Nilai harus diisi sebelum finalisasi.');
            $record->update(['status' => 'FINALIZED', 'finalized_at' => now()]);
            $this->audit('FINALIZE_DIPLOMA_SCORE', DiplomaScore::class, $record->id, ['status' => 'FINALIZED']);
        });
    }

    public function saveGraduation(): void
    {
        $this->authorizePermission('MANAGE_DIPLOMA_SCORE');
        $data = $this->validate(['graduationNotes' => ['nullable', 'string', 'max:5000']]);
        DB::transaction(function () use ($data): void {
            $record = Graduation::query()->where('student_id', $this->student->id)
                ->lockForUpdate()->firstOrNew(['student_id' => $this->student->id]);
            abort_unless($record->status === 'DRAFT', 409);
            $before = $record->exists ? $record->only(['notes']) : null;
            $record->notes = $data['graduationNotes'];
            $record->status = 'DRAFT';
            $record->save();
            $this->audit('SAVE_GRADUATION_DRAFT', Graduation::class, $record->id, [
                'status' => 'DRAFT', 'notes' => $record->notes,
            ], $before);
        });
        session()->flash('success', 'Catatan kelulusan tersimpan sebagai draft.');
    }

    public function verifyGraduation(): void
    {
        $this->authorizePermission('VERIFY_GRADUATION');
        $this->resetValidation('graduationNotes');
        $verified = DB::transaction(function (): bool {
            $record = Graduation::query()->where('student_id', $this->student->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'DRAFT', 409);
            if (! $this->graduationRequirementsComplete()) {
                $this->addError('graduationNotes', 'Lengkapi dan finalisasi nilai semua mata pelajaran kurikulum rombel terakhir serta nilai UKK kompetensi terakhir sebelum verifikasi kelulusan.');
                return false;
            }
            $record->update(['status' => 'VERIFIED', 'verified_by' => auth()->id(), 'verified_at' => now()]);
            $this->audit('VERIFY_GRADUATION', Graduation::class, $record->id, ['status' => 'VERIFIED']);
            return true;
        });
        if ($verified) session()->flash('success', 'Kelulusan berhasil diverifikasi.');
    }

    public function finalizeGraduation(): void
    {
        $this->authorizePermission('FINALIZE_GRADUATION');
        DB::transaction(function (): void {
            $student = Student::query()->lockForUpdate()->findOrFail($this->student->id);
            $record = Graduation::query()->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'VERIFIED', 409);
            abort_unless($student->status === 'AKTIF', 422, 'Hanya siswa aktif yang dapat dinyatakan lulus.');
            abort_unless($this->graduationRequirementsComplete(), 422,
                'Semua nilai mata pelajaran kurikulum rombel terakhir dan nilai UKK kompetensi terakhir wajib difinalisasi.');
            $record->update(['status' => 'FINALIZED', 'finalized_by' => auth()->id(), 'finalized_at' => now()]);
            $student->update(['status' => 'LULUS']);
            $this->audit('FINALIZE_GRADUATION', Graduation::class, $record->id, ['status' => 'FINALIZED', 'student_status' => 'LULUS']);
            $this->audit('GRADUATE_STUDENT', Student::class, $student->id, ['status' => 'LULUS'], ['status' => 'AKTIF']);
        });
        session()->flash('success', 'Kelulusan difinalisasi dan status siswa diperbarui menjadi LULUS.');
    }

    public function uploadDocument(): void
    {
        $this->authorizePermission('MANAGE_STUDENT_DOCUMENT');
        $data = $this->validate([
            'documentType' => ['required', 'string', 'max:255'],
            'documentFile' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);
        $path = $this->documentFile->store("students/{$this->student->id}/documents", 'local');
        try {
            DB::transaction(function () use ($data, $path): void {
                $document = StudentDocument::query()->create([
                    'student_id' => $this->student->id, 'document_type' => trim($data['documentType']),
                    'file_name' => basename($this->documentFile->getClientOriginalName()), 'file_path' => $path,
                    'mime_type' => $this->documentFile->getMimeType(), 'file_size' => $this->documentFile->getSize(),
                    'uploaded_by' => auth()->id(), 'uploaded_at' => now(), 'status' => 'ACTIVE',
                ]);
                $this->audit('UPLOAD_STUDENT_DOCUMENT', StudentDocument::class, $document->id, [
                    'student_id' => $this->student->id, 'document_type' => $document->document_type,
                    'mime_type' => $document->mime_type, 'file_size' => $document->file_size,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        $this->reset('documentFile', 'documentType');
        session()->flash('success', 'Dokumen berhasil diunggah ke penyimpanan privat.');
    }

    public function archiveDocument(int $id): void
    {
        $this->authorizePermission('MANAGE_STUDENT_DOCUMENT');
        DB::transaction(function () use ($id): void {
            $document = StudentDocument::query()->where('student_id', $this->student->id)
                ->lockForUpdate()->findOrFail($id);
            abort_unless($document->status === 'ACTIVE', 409);
            $document->update(['status' => 'ARCHIVED']);
            $this->audit('ARCHIVE_STUDENT_DOCUMENT', StudentDocument::class, $document->id, ['status' => 'ARCHIVED']);
        });
        session()->flash('success', 'Dokumen dipindahkan ke arsip.');
    }

    public function with(): array
    {
        $this->authorize('view', $this->student);
        $user = auth()->user();
        $relations = [
            'attendanceSummaries.semester.academicYear',
            'extracurricularRecords.extracurricular', 'extracurricularRecords.semester.academicYear',
            'pklRecords', 'ukkRecords.competency', 'diplomaScores.subject', 'graduation',
        ];
        if ($user->hasPermission('MANAGE_GUARDIAN')) {
            $relations[] = 'studentGuardians.guardian';
        }
        if ($user->hasPermission('MANAGE_STUDENT_DOCUMENT')) {
            $relations[] = 'documents.uploadedBy';
        }
        $this->student->load($relations);
        return [
            'semesters' => Semester::query()->with('academicYear')->orderByDesc('academic_year_id')->orderBy('number')->get(),
            'activities' => Extracurricular::query()->where('is_active', true)->orderBy('name')->get(),
            'competencies' => Competency::query()->where('is_active', true)->orderBy('name')->get(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
            'guardians' => $user->hasPermission('MANAGE_GUARDIAN')
                ? Guardian::query()->whereDoesntHave('studentGuardians', fn ($query) => $query->where('student_id', $this->student->id))->orderBy('full_name')->get()
                : collect(),
            'canManageGuardian' => $user->hasPermission('MANAGE_GUARDIAN'),
            'canManageDocuments' => $user->hasPermission('MANAGE_STUDENT_DOCUMENT'),
            'canManageAttendance' => $user->hasPermission('MANAGE_ATTENDANCE'),
            'canLockAttendance' => $user->hasPermission('LOCK_ATTENDANCE'),
            'canFinalizeAttendance' => $user->hasPermission('FINALIZE_ATTENDANCE'),
            'canManageExtracurricular' => $user->hasPermission('MANAGE_EXTRACURRICULAR'),
            'canLockExtracurricular' => $user->hasPermission('LOCK_EXTRACURRICULAR'),
            'canFinalizeExtracurricular' => $user->hasPermission('FINALIZE_EXTRACURRICULAR'),
            'canManagePkl' => $user->hasPermission('MANAGE_PKL'),
            'canFinalizePkl' => $user->hasPermission('FINALIZE_PKL'),
            'canManageUkk' => $user->hasPermission('MANAGE_UKK'),
            'canFinalizeUkk' => $user->hasPermission('FINALIZE_UKK'),
            'canManageDiploma' => $user->hasPermission('MANAGE_DIPLOMA_SCORE'),
            'canFinalizeDiploma' => $user->hasPermission('FINALIZE_DIPLOMA_SCORE'),
            'canVerifyGraduation' => $user->hasPermission('VERIFY_GRADUATION'),
            'canFinalizeGraduation' => $user->hasPermission('FINALIZE_GRADUATION'),
        ];
    }
};
?>

<div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-semibold">Catatan Akademik Siswa</h1><p class="mt-1 text-sm text-gray-600">{{ $student->nis }} · {{ $student->full_name }}</p></div>
        <a href="{{ route('students.show', $student) }}" class="text-sm text-blue-700 underline">Kembali ke data siswa</a>
    </header>
    @if (session('success'))<div role="status" class="rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div>@endif

    <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Orang tua dan wali</h2>
    @if ($canManageGuardian)
        <form wire:submit="saveGuardian" class="mt-4 grid gap-3 md:grid-cols-3">
            <label class="text-sm">Gunakan data wali yang sudah ada<select wire:model.live="guardianId" class="mt-1 w-full rounded border-gray-300"><option value="">Tambah wali baru</option>@foreach($guardians as $guardian)<option value="{{ $guardian->id }}">{{ $guardian->full_name }} · {{ $guardian->phone }}</option>@endforeach</select>@error('guardianId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            @if (!$guardianId)
            <label class="text-sm">Nama wali<input wire:model="guardianName" class="mt-1 w-full rounded border-gray-300">@error('guardianName')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">NIK (16 digit)<input wire:model="guardianNik" inputmode="numeric" maxlength="16" class="mt-1 w-full rounded border-gray-300">@error('guardianNik')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Telepon<input wire:model="guardianPhone" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm">Email<input type="email" wire:model="guardianEmail" class="mt-1 w-full rounded border-gray-300">@error('guardianEmail')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Pekerjaan<input wire:model="guardianOccupation" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm md:col-span-3">Alamat<input wire:model="guardianAddress" class="mt-1 w-full rounded border-gray-300"></label>
            @endif
            <label class="text-sm">Hubungan<select wire:model="guardianRelationship" class="mt-1 w-full rounded border-gray-300"><option value="FATHER">Ayah</option><option value="MOTHER">Ibu</option><option value="GUARDIAN">Wali</option><option value="FOSTER_GUARDIAN">Orang tua asuh</option><option value="INSTITUTION_GUARDIAN">Wali lembaga</option><option value="OTHER">Lainnya</option></select></label>
            <label class="flex items-center gap-2 text-sm md:mt-6"><input type="checkbox" wire:model="guardianIsPrimary"> Jadikan wali utama</label>
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white">Hubungkan wali</button>
        </form>
    @endif
        @if ($canManageGuardian)
        <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="py-2 pr-3">Nama</th><th class="py-2 pr-3">Hubungan</th><th class="py-2 pr-3">Kontak</th><th class="py-2 pr-3">Utama</th><th></th></tr></thead><tbody>@forelse($student->studentGuardians as $link)<tr class="border-b"><td class="py-2 pr-3">{{ $link->guardian->full_name }}</td><td class="py-2 pr-3">{{ str_replace('_', ' ', $link->relationship) }}</td><td class="py-2 pr-3">{{ $link->guardian->phone ?? $link->guardian->email ?? '—' }}</td><td class="py-2 pr-3">{{ $link->is_primary ? 'Ya' : 'Tidak' }}</td><td>@if(!$link->is_primary)<button wire:click="makePrimaryGuardian({{ $link->id }})" class="text-blue-700 underline">Jadikan utama</button>@endif</td></tr>@empty<tr><td colspan="5" class="py-3 text-gray-500">Belum ada wali terkait.</td></tr>@endforelse</tbody></table></div>
        @else
        <p class="mt-4 text-sm text-gray-600">Informasi orang tua dan wali memerlukan izin khusus.</p>
        @endif
    </section>

    <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Kehadiran per semester</h2>
        @if($canManageAttendance)
        <form wire:submit="saveAttendance" class="mt-4 grid gap-3 md:grid-cols-5">
            <label class="text-sm md:col-span-2">Semester<select wire:model="attendanceSemesterId" class="mt-1 w-full rounded border-gray-300"><option value="">Pilih semester</option>@foreach($semesters as $semester)<option value="{{ $semester->id }}">{{ $semester->academicYear->name }} · {{ $semester->name }}</option>@endforeach</select>@error('attendanceSemesterId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Sakit<input type="number" min="0" max="1000" wire:model="sick" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Izin<input type="number" min="0" max="1000" wire:model="excused" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Alpa<input type="number" min="0" max="1000" wire:model="absent" class="mt-1 w-full rounded border-gray-300"></label>
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white md:col-span-5">Simpan draft kehadiran</button>
        </form>@endif
        <ul class="mt-4 divide-y text-sm">@forelse($student->attendanceSummaries as $record)<li class="flex flex-wrap items-center justify-between gap-2 py-2"><span>{{ $record->semester->academicYear->name }} · {{ $record->semester->name }} — Sakit {{ $record->sick }}, Izin {{ $record->excused }}, Alpa {{ $record->absent }} · {{ $record->status }}</span><span class="flex gap-2">@if($canLockAttendance && $record->status==='DRAFT')<button wire:click="lockAttendance({{ $record->id }})" class="text-blue-700 underline">Kunci</button>@endif @if($canFinalizeAttendance && $record->status==='LOCKED')<button wire:click="finalizeAttendance({{ $record->id }})" class="text-blue-700 underline">Finalisasi</button>@endif</span></li>@empty<li class="py-2 text-gray-500">Belum ada ringkasan kehadiran.</li>@endforelse</ul>
    </section>

    <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Ekstrakurikuler</h2>
        @if(auth()->user()->role?->code==='ADMIN')<form wire:submit="addExtracurricular" class="mt-3 flex flex-wrap gap-3"><input wire:model="extracurricularName" placeholder="Nama kegiatan baru" class="min-w-64 rounded border-gray-300">@error('extracurricularName')<span class="text-red-600">{{ $message }}</span>@enderror<button class="rounded border px-3 py-2 text-sm">Tambah kegiatan</button></form>@endif
        @if($canManageExtracurricular)<form wire:submit="saveExtracurricular" class="mt-4 grid gap-3 md:grid-cols-4">
            <label class="text-sm">Kegiatan<select wire:model="activityId" class="mt-1 w-full rounded border-gray-300"><option value="">Pilih</option>@foreach($activities as $activity)<option value="{{ $activity->id }}">{{ $activity->name }}</option>@endforeach</select>@error('activityId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Semester<select wire:model="activitySemesterId" class="mt-1 w-full rounded border-gray-300"><option value="">Pilih</option>@foreach($semesters as $semester)<option value="{{ $semester->id }}">{{ $semester->academicYear->name }} · {{ $semester->name }}</option>@endforeach</select>@error('activitySemesterId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Predikat<input wire:model="activityPredicate" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Deskripsi<input wire:model="activityDescription" class="mt-1 w-full rounded border-gray-300"></label>
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white md:col-span-4">Simpan draft kegiatan</button>
        </form>@endif
        <ul class="mt-4 divide-y text-sm">@forelse($student->extracurricularRecords as $record)<li class="flex flex-wrap justify-between gap-2 py-2"><span>{{ $record->extracurricular->name }} · {{ $record->semester->academicYear->name }} {{ $record->semester->name }} · {{ $record->predicate ?? '—' }} · {{ $record->status }}</span><span class="flex gap-2">@if($canLockExtracurricular && $record->status==='DRAFT')<button wire:click="lockExtracurricular({{ $record->id }})" class="text-blue-700 underline">Kunci</button>@endif @if($canFinalizeExtracurricular && $record->status==='LOCKED')<button wire:click="finalizeExtracurricular({{ $record->id }})" class="text-blue-700 underline">Finalisasi</button>@endif</span></li>@empty<li class="py-2 text-gray-500">Belum ada catatan kegiatan.</li>@endforelse</ul>
    </section>

    <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Praktik Kerja Lapangan (PKL)</h2>
        @if($canManagePkl)<form wire:submit="savePkl" class="mt-4 grid gap-3 md:grid-cols-3">
            <label class="text-sm">Tempat PKL<input wire:model="pklPartner" class="mt-1 w-full rounded border-gray-300">@error('pklPartner')<span class="text-red-600">{{ $message }}</span>@enderror</label><label class="text-sm">Lokasi<input wire:model="pklLocation" class="mt-1 w-full rounded border-gray-300"></label>
            <div class="grid grid-cols-2 gap-2"><label class="text-sm">Mulai<input type="date" wire:model="pklStartDate" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Selesai<input type="date" wire:model="pklEndDate" class="mt-1 w-full rounded border-gray-300">@error('pklEndDate')<span class="text-red-600">{{ $message }}</span>@enderror</label></div>
            <label class="text-sm">Nilai kompetensi<input type="number" min="0" max="100" wire:model="pklCompetencyScore" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Predikat kompetensi<input wire:model="pklCompetencyPredicate" class="mt-1 w-full rounded border-gray-300"></label>
            <label class="text-sm">Nilai sikap<input type="number" min="0" max="100" wire:model="pklAttitudeScore" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Predikat sikap<input wire:model="pklAttitudePredicate" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Deskripsi<input wire:model="pklDescription" class="mt-1 w-full rounded border-gray-300"></label>
            <button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white md:col-span-3">{{ $pklId ? 'Ubah draft PKL' : 'Tambah draft PKL' }}</button>
        </form>@endif
        <ul class="mt-4 divide-y text-sm">@forelse($student->pklRecords as $record)<li class="flex flex-wrap justify-between gap-2 py-2"><span>{{ $record->partner_name }} · {{ $record->start_date->format('d/m/Y') }}–{{ $record->end_date->format('d/m/Y') }} · Kompetensi {{ $record->competency_score ?? '—' }} · {{ $record->status }}</span><span class="flex gap-2">@if($canManagePkl && $record->status==='DRAFT')<button wire:click="editPkl({{ $record->id }})" class="text-blue-700 underline">Ubah</button>@endif @if($canFinalizePkl && $record->status==='DRAFT')<button wire:click="finalizePkl({{ $record->id }})" class="text-blue-700 underline">Finalisasi</button>@endif</span></li>@empty<li class="py-2 text-gray-500">Belum ada catatan PKL.</li>@endforelse</ul>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Uji Kompetensi Keahlian (UKK)</h2>
            @if($canManageUkk)<form wire:submit="saveUkk" class="mt-4 grid gap-3"><label class="text-sm">Konsentrasi<select wire:model="ukkCompetencyId" class="mt-1 w-full rounded border-gray-300"><option value="">Pilih</option>@foreach($competencies as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>@error('ukkCompetencyId')<span class="text-red-600">{{ $message }}</span>@enderror</label><label class="text-sm">Nilai<input type="number" min="0" max="100" wire:model="ukkScore" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Predikat<input wire:model="ukkPredicate" class="mt-1 w-full rounded border-gray-300"></label><button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white">Simpan draft UKK</button></form>@endif
            <ul class="mt-4 divide-y text-sm">@foreach($student->ukkRecords as $record)<li class="flex justify-between gap-2 py-2"><span>{{ $record->competency->name }} · {{ $record->final_score ?? '—' }} · {{ $record->status }}</span>@if($canFinalizeUkk && $record->status==='DRAFT')<button wire:click="finalizeUkk({{ $record->id }})" class="text-blue-700 underline">Finalisasi</button>@endif</li>@endforeach</ul>
        </section>
        <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Nilai ijazah</h2>
            @if($canManageDiploma)<form wire:submit="saveDiplomaScore" class="mt-4 grid gap-3"><label class="text-sm">Mata pelajaran<select wire:model="diplomaSubjectId" class="mt-1 w-full rounded border-gray-300"><option value="">Pilih</option>@foreach($subjects as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>@error('diplomaSubjectId')<span class="text-red-600">{{ $message }}</span>@enderror</label><label class="text-sm">Nilai<input type="number" min="0" max="100" step="0.01" wire:model="diplomaScore" class="mt-1 w-full rounded border-gray-300"></label><label class="text-sm">Predikat<input wire:model="diplomaPredicate" class="mt-1 w-full rounded border-gray-300"></label><button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white">Simpan draft nilai ijazah</button></form>@endif
            <ul class="mt-4 divide-y text-sm">@foreach($student->diplomaScores as $record)<li class="flex justify-between gap-2 py-2"><span>{{ $record->subject->name }} · {{ $record->final_score ?? '—' }} · {{ $record->status }}</span>@if($canFinalizeDiploma && $record->status==='DRAFT')<button wire:click="finalizeDiplomaScore({{ $record->id }})" class="text-blue-700 underline">Finalisasi</button>@endif</li>@endforeach</ul>
        </section>
    </div>

    <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Kelulusan</h2>
        @if($canManageDiploma)<form wire:submit="saveGraduation" class="mt-4 space-y-3"><label class="block text-sm">Catatan verifikasi<textarea wire:model="graduationNotes" rows="3" class="mt-1 w-full rounded border-gray-300"></textarea></label><button class="rounded border px-4 py-2 text-sm">Simpan draft kelulusan</button></form>@endif
        @error('graduationNotes')<p role="alert" class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
        @if($student->graduation)<div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm"><span>Status: <strong>{{ $student->graduation->status }}</strong> · {{ $student->graduation->notes }}</span><span class="flex gap-2">@if($canVerifyGraduation && $student->graduation->status==='DRAFT')<button wire:click="verifyGraduation" class="rounded border px-3 py-1">Verifikasi</button>@endif @if($canFinalizeGraduation && $student->graduation->status==='VERIFIED')<button wire:click="finalizeGraduation" wire:confirm="Finalisasi akan mengubah status siswa menjadi LULUS. Lanjutkan?" class="rounded bg-blue-600 px-3 py-1 text-white">Finalisasi kelulusan</button>@endif</span></div>@else<p class="mt-3 text-sm text-gray-500">Belum ada draft kelulusan.</p>@endif
    </section>

    <section class="rounded-lg border bg-white p-5"><h2 class="text-lg font-semibold">Dokumen siswa</h2>
        @if($canManageDocuments)<form wire:submit="uploadDocument" class="mt-4 grid gap-3 md:grid-cols-3"><label class="text-sm">Jenis dokumen<input wire:model="documentType" placeholder="Akta kelahiran" class="mt-1 w-full rounded border-gray-300">@error('documentType')<span class="text-red-600">{{ $message }}</span>@enderror</label><label class="text-sm md:col-span-2">File PDF/JPG/PNG, maks. 10 MB<input type="file" wire:model="documentFile" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 w-full rounded border-gray-300">@error('documentFile')<span class="text-red-600">{{ $message }}</span>@enderror</label><button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white md:col-span-3">Unggah ke penyimpanan privat</button></form>@endif
        @if($canManageDocuments)
        <ul class="mt-4 divide-y text-sm">@forelse($student->documents as $document)<li class="flex flex-wrap items-center justify-between gap-2 py-2"><span>{{ $document->document_type }} · {{ $document->file_name }} · {{ $document->status }}</span><span class="flex gap-3"><a href="{{ route('students.documents.download', [$student, $document]) }}" class="text-blue-700 underline">Unduh</a>@if($document->status==='ACTIVE')<button wire:click="archiveDocument({{ $document->id }})" wire:confirm="Arsipkan dokumen ini?" class="text-blue-700 underline">Arsipkan</button>@endif</span></li>@empty<li class="py-2 text-gray-500">Belum ada dokumen tersimpan.</li>@endforelse</ul>
        @else
        <p class="mt-4 text-sm text-gray-600">Dokumen siswa memerlukan izin khusus untuk dilihat.</p>
        @endif
    </section>
</div>
