<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="nis" class="field-label">NIS <span class="text-rose-600">*</span></label>
        <input id="nis" type="text" wire:model="nis" required maxlength="255">
        @error('nis') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="nisn" class="field-label">NISN</label>
        <input id="nisn" type="text" wire:model="nisn" maxlength="255">
        @error('nisn') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="nik" class="field-label">NIK (16 digit)</label>
        <input id="nik" type="text" inputmode="numeric" wire:model="nik" maxlength="16">
        @error('nik') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="fullName" class="field-label">Nama lengkap <span class="text-rose-600">*</span></label>
        <input id="fullName" type="text" wire:model="fullName" required maxlength="255">
        @error('fullName') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="gender" class="field-label">Jenis kelamin <span class="text-rose-600">*</span></label>
        <select id="gender" wire:model="gender" required>
            <option value="L">Laki-laki</option>
            <option value="P">Perempuan</option>
        </select>
        @error('gender') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="entryYear" class="field-label">Tahun masuk <span class="text-rose-600">*</span></label>
        <input id="entryYear" type="number" wire:model="entryYear" required min="2000" max="2100">
        @error('entryYear') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="status" class="field-label">Status <span class="text-rose-600">*</span></label>
        <select id="status" wire:model="status" required>
            <option value="CALON">Calon</option>
            <option value="AKTIF">Aktif</option>
            <option value="LULUS">Lulus</option>
            <option value="PINDAH">Pindah</option>
            <option value="MENGUNDURKAN_DIRI">Mengundurkan diri</option>
            <option value="TIDAK_LULUS">Tidak lulus</option>
        </select>
        @error('status') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="phone" class="field-label">Nomor telepon</label>
        <input id="phone" type="tel" wire:model="phone" maxlength="255">
        @error('phone') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" type="email" wire:model="email" maxlength="255">
        @error('email') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="address" class="field-label">Alamat</label>
        <textarea id="address" wire:model="address" rows="3"></textarea>
        @error('address') <p class="field-error">{{ $message }}</p> @enderror
    </div>
</div>
