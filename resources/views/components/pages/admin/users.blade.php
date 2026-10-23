<?php

use App\Models\AuditLog;
use App\Models\ClassAssignment;
use App\Models\OperatorRombelScope;
use App\Models\Role;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $roleCode = 'OPERATOR';
    public ?int $studentId = null;
    public string $password = '';
    public string $passwordConfirmation = '';
    public ?int $operatorUserId = null;
    public ?int $operatorRombelId = null;
    public ?int $homeroomUserId = null;
    public ?int $homeroomRombelId = null;
    public ?int $homeroomYearId = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function saveUser(): void
    {
        $this->authorizeAdmin();
        $this->name = trim($this->name);
        $this->email = strtolower(trim($this->email));
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roleCode' => ['required', 'string', 'exists:roles,code'],
            'studentId' => [Rule::requiredIf($this->roleCode === 'STUDENT'), 'nullable', 'integer', 'exists:students,id'],
            'password' => ['required', 'string', 'min:12', 'same:passwordConfirmation'],
        ], [
            'password.same' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi harus sedikitnya 12 karakter.',
        ]);

        if ($data['roleCode'] !== 'STUDENT' && $data['studentId']) {
            $this->addError('studentId', 'Tautan ke data siswa hanya berlaku untuk akun siswa.');
            return;
        }
        if ($data['roleCode'] === 'STUDENT' && User::query()->where('student_id', $data['studentId'])->exists()) {
            $this->addError('studentId', 'Data siswa tersebut sudah terhubung ke akun lain.');
            return;
        }

        DB::transaction(function () use ($data): void {
            $role = Role::query()->where('code', $data['roleCode'])->firstOrFail();
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'role_id' => $role->id,
                'student_id' => $data['roleCode'] === 'STUDENT' ? $data['studentId'] : null,
                'password' => $data['password'],
            ]);
            AuditLog::query()->create([
                'user_id' => auth()->id(), 'action' => 'CREATE_SCHOOL_USER',
                'resource_type' => User::class, 'resource_id' => $user->id,
                'after_data' => ['role' => $role->code],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);
        });

        $this->reset('name', 'email', 'studentId', 'password', 'passwordConfirmation');
        $this->roleCode = 'OPERATOR';
        session()->flash('success', 'Akun berhasil dibuat. Sampaikan kata sandi awal melalui cara yang aman.');
    }

    public function assignOperatorRombel(): void
    {
        $this->authorizeAdmin();
        $data = $this->validate([
            'operatorUserId' => ['required', 'integer', 'exists:users,id'],
            'operatorRombelId' => ['required', 'integer', 'exists:rombels,id'],
        ]);
        $user = User::query()->with('role')->findOrFail($data['operatorUserId']);
        if ($user->role?->code !== 'OPERATOR') {
            $this->addError('operatorUserId', 'Pilih akun dengan peran Operator / TU.');
            return;
        }

        DB::transaction(function () use ($data, $user): void {
            $scope = OperatorRombelScope::query()->firstOrNew([
                'user_id' => $user->id, 'rombel_id' => $data['operatorRombelId'],
            ]);
            $wasActive = $scope->exists && $scope->is_active;
            $scope->is_active = true;
            $scope->save();
            if (! $wasActive) {
                AuditLog::query()->create([
                    'user_id' => auth()->id(), 'action' => 'ASSIGN_OPERATOR_ROMBEL',
                    'resource_type' => OperatorRombelScope::class, 'resource_id' => $scope->id,
                    'after_data' => ['user_id' => $user->id, 'rombel_id' => $data['operatorRombelId']],
                    'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
                ]);
            }
        });
        session()->flash('success', 'Ruang lingkup operator berhasil diperbarui.');
    }

    public function assignHomeroom(): void
    {
        $this->authorizeAdmin();
        $data = $this->validate([
            'homeroomUserId' => ['required', 'integer', 'exists:users,id'],
            'homeroomRombelId' => ['required', 'integer', 'exists:rombels,id'],
            'homeroomYearId' => ['required', 'integer', 'exists:academic_years,id'],
        ]);
        $user = User::query()->with('role')->findOrFail($data['homeroomUserId']);
        $rombel = Rombel::query()->findOrFail($data['homeroomRombelId']);
        if ($user->role?->code !== 'WALI_KELAS') {
            $this->addError('homeroomUserId', 'Pilih akun dengan peran Wali Kelas.');
            return;
        }
        if ((int) $rombel->academic_year_id !== (int) $data['homeroomYearId']) {
            $this->addError('homeroomRombelId', 'Rombel harus sesuai dengan tahun ajaran yang dipilih.');
            return;
        }
        $occupied = ClassAssignment::query()->where('rombel_id', $rombel->id)
            ->where('academic_year_id', $data['homeroomYearId'])->where('status', 'ACTIVE')
            ->where('user_id', '!=', $user->id)->exists();
        if ($occupied) {
            $this->addError('homeroomRombelId', 'Rombel sudah memiliki wali kelas aktif.');
            return;
        }

        DB::transaction(function () use ($data, $user, $rombel): void {
            $assignment = ClassAssignment::query()
                ->where('user_id', $user->id)->where('rombel_id', $rombel->id)
                ->where('academic_year_id', $data['homeroomYearId'])->first();
            $before = $assignment?->status;
            if ($assignment) {
                $assignment->update(['status' => 'ACTIVE']);
            } else {
                $assignment = ClassAssignment::query()->create([
                    'user_id' => $user->id, 'rombel_id' => $rombel->id,
                    'academic_year_id' => $data['homeroomYearId'], 'status' => 'ACTIVE',
                ]);
            }
            if ($before !== 'ACTIVE') {
                AuditLog::query()->create([
                    'user_id' => auth()->id(), 'action' => 'ASSIGN_HOMEROOM',
                    'resource_type' => ClassAssignment::class, 'resource_id' => $assignment->id,
                    'before_data' => ['status' => $before],
                    'after_data' => ['user_id' => $user->id, 'rombel_id' => $rombel->id, 'academic_year_id' => $data['homeroomYearId']],
                    'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
                ]);
            }
        });
        session()->flash('success', 'Wali kelas berhasil ditugaskan.');
    }

    public function toggleOperatorScope(int $id): void
    {
        $this->authorizeAdmin();
        DB::transaction(function () use ($id): void {
            $scope = OperatorRombelScope::query()->lockForUpdate()->findOrFail($id);
            $before = $scope->is_active;
            $scope->update(['is_active' => ! $before]);
            AuditLog::query()->create([
                'user_id' => auth()->id(), 'action' => 'TOGGLE_OPERATOR_ROMBEL',
                'resource_type' => OperatorRombelScope::class, 'resource_id' => $scope->id,
                'before_data' => ['is_active' => $before], 'after_data' => ['is_active' => ! $before],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);
        });
    }

    public function endHomeroom(int $id): void
    {
        $this->authorizeAdmin();
        DB::transaction(function () use ($id): void {
            $assignment = ClassAssignment::query()->lockForUpdate()->findOrFail($id);
            $assignment->update(['status' => 'INACTIVE', 'end_date' => now()->toDateString()]);
            AuditLog::query()->create([
                'user_id' => auth()->id(), 'action' => 'END_HOMEROOM_ASSIGNMENT',
                'resource_type' => ClassAssignment::class, 'resource_id' => $assignment->id,
                'before_data' => ['status' => 'ACTIVE'], 'after_data' => ['status' => 'INACTIVE'],
                'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
            ]);
        });
        session()->flash('success', 'Penugasan wali kelas telah diakhiri.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role?->code === 'ADMIN', 403);
    }

    public function render()
    {
        $this->authorizeAdmin();
        return view('components.pages.admin.users', [
            'users' => User::query()->with(['role', 'student'])->orderBy('name')->get(),
            'operators' => User::query()->whereHas('role', fn ($q) => $q->where('code', 'OPERATOR'))->orderBy('name')->get(),
            'homeroomUsers' => User::query()->whereHas('role', fn ($q) => $q->where('code', 'WALI_KELAS'))->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'students' => Student::query()->whereDoesntHave('user')->orderBy('full_name')->get(),
            'rombels' => Rombel::query()->with('academicYear')->where('is_active', true)->orderByDesc('academic_year_id')->orderBy('name')->get(),
            'operatorScopes' => OperatorRombelScope::query()->with(['user', 'rombel.academicYear'])->orderByDesc('id')->get(),
            'classAssignments' => ClassAssignment::query()->with(['user', 'rombel', 'academicYear'])->orderByDesc('id')->get(),
            'years' => \App\Models\AcademicYear::query()->orderByDesc('start_date')->get(),
        ]);
    }
};
?>

<div class="space-y-8">
    <header><h1 class="text-2xl font-semibold">Pengelolaan Akun</h1><p class="mt-1 text-sm text-gray-600">Buat akun staf dan atur akses berdasarkan rombel tugasnya.</p></header>
    @if (session('success'))<div role="status" class="rounded border border-green-200 bg-green-50 px-4 py-3 text-green-800">{{ session('success') }}</div>@endif

    <section class="card p-5"><h2 class="text-lg font-semibold">Buat akun</h2>
        <form wire:submit="saveUser" class="mt-4 grid gap-4 md:grid-cols-3">
            <label class="text-sm">Nama<input wire:model="name" class="mt-2">@error('name')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Email<input type="email" wire:model="email" class="mt-2">@error('email')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Peran<select wire:model.live="roleCode" class="mt-2">@foreach ($roles as $role)<option value="{{ $role->code }}">{{ $role->name }}</option>@endforeach</select>@error('roleCode')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            @if ($roleCode === 'STUDENT')<label class="text-sm">Data siswa<select wire:model="studentId" class="mt-2"><option value="">Pilih siswa</option>@foreach ($students as $student)<option value="{{ $student->id }}">{{ $student->nis }} · {{ $student->full_name }}</option>@endforeach</select>@error('studentId')<span class="text-red-600">{{ $message }}</span>@enderror</label>@endif
            <label class="text-sm">Kata sandi awal<input type="password" wire:model="password" autocomplete="new-password" class="mt-2">@error('password')<span class="text-red-600">{{ $message }}</span>@enderror</label>
            <label class="text-sm">Ulangi kata sandi<input type="password" wire:model="passwordConfirmation" autocomplete="new-password" class="mt-2"></label>
            <button class="btn-primary md:col-span-3">Buat akun</button>
        </form>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card p-5"><h2 class="text-lg font-semibold">Akses operator</h2>
            <form wire:submit="assignOperatorRombel" class="mt-4 grid gap-3">
                <label class="text-sm">Operator<select wire:model="operatorUserId" class="mt-2"><option value="">Pilih operator</option>@foreach ($operators as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>@error('operatorUserId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Rombel<select wire:model="operatorRombelId" class="mt-2"><option value="">Pilih rombel</option>@foreach ($rombels as $rombel)<option value="{{ $rombel->id }}">{{ $rombel->name }} · {{ $rombel->academicYear->name }}</option>@endforeach</select>@error('operatorRombelId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <button class="btn-primary">Simpan akses</button>
            </form>
            <ul class="mt-4 divide-y text-sm">@foreach ($operatorScopes as $scope)<li class="flex items-center justify-between gap-2 py-2"><span>{{ $scope->user->name }} · {{ $scope->rombel->name }} <span class="text-gray-500">{{ $scope->is_active ? 'Aktif' : 'Nonaktif' }}</span></span><button wire:click="toggleOperatorScope({{ $scope->id }})" class="text-blue-700 underline">{{ $scope->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></li>@endforeach</ul>
        </section>
        <section class="card p-5"><h2 class="text-lg font-semibold">Penugasan wali kelas</h2>
            <form wire:submit="assignHomeroom" class="mt-4 grid gap-3">
                <label class="text-sm">Wali kelas<select wire:model="homeroomUserId" class="mt-2"><option value="">Pilih wali kelas</option>@foreach ($homeroomUsers as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>@error('homeroomUserId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Tahun ajaran<select wire:model="homeroomYearId" class="mt-2"><option value="">Pilih tahun ajaran</option>@foreach ($years as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select>@error('homeroomYearId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <label class="text-sm">Rombel<select wire:model="homeroomRombelId" class="mt-2"><option value="">Pilih rombel</option>@foreach ($rombels as $rombel)<option value="{{ $rombel->id }}">{{ $rombel->name }} · {{ $rombel->academicYear->name }}</option>@endforeach</select>@error('homeroomRombelId')<span class="text-red-600">{{ $message }}</span>@enderror</label>
                <button class="btn-primary">Tugaskan wali kelas</button>
            </form>
            <ul class="mt-4 divide-y text-sm">@foreach ($classAssignments as $assignment)<li class="flex items-center justify-between gap-2 py-2"><span>{{ $assignment->user->name }} · {{ $assignment->rombel->name }} · {{ $assignment->academicYear->name }} <span class="text-gray-500">{{ $assignment->status === 'ACTIVE' ? 'Aktif' : 'Nonaktif' }}</span></span>@if ($assignment->status === 'ACTIVE')<button wire:click="endHomeroom({{ $assignment->id }})" class="text-blue-700 underline">Akhiri</button>@endif</li>@endforeach</ul>
        </section>
    </div>

    <section class="card p-5"><h2 class="text-lg font-semibold">Daftar akun</h2>
        <div class="mt-4 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="py-2 pr-4">Nama</th><th class="py-2 pr-4">Email</th><th class="py-2 pr-4">Peran</th><th class="py-2">Terkait siswa</th></tr></thead><tbody>@foreach ($users as $user)<tr class="border-b"><td class="py-2 pr-4">{{ $user->name }}</td><td class="py-2 pr-4">{{ $user->email }}</td><td class="py-2 pr-4">{{ $user->role?->name ?? 'Belum ada peran' }}</td><td class="py-2">{{ $user->student?->full_name ?? '—' }}</td></tr>@endforeach</tbody></table></div>
    </section>
</div>
