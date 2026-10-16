<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="nis" class="block text-sm font-medium">NIS <span class="text-red-600">*</span></label>
        <input id="nis" type="text" wire:model="nis" required maxlength="255" class="mt-1 w-full rounded border-gray-300">
        @error('nis') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="nisn" class="block text-sm font-medium">NISN</label>
        <input id="nisn" type="text" wire:model="nisn" maxlength="255" class="mt-1 w-full rounded border-gray-300">
        @error('nisn') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="nik" class="block text-sm font-medium">NIK (16 digit)</label>
        <input id="nik" type="text" inputmode="numeric" wire:model="nik" maxlength="16" class="mt-1 w-full rounded border-gray-300">
        @error('nik') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="fullName" class="block text-sm font-medium">Nama lengkap <span class="text-red-600">*</span></label>
        <input id="fullName" type="text" wire:model="fullName" required maxlength="255" class="mt-1 w-full rounded border-gray-300">
        @error('fullName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="gender" class="block text-sm font-medium">Jenis kelamin <span class="text-red-600">*</span></label>
        <select id="gender" wire:model="gender" required class="mt-1 w-full rounded border-gray-300">
            <option value="L">Laki-laki</option>
            <option value="P">Perempuan</option>
        </select>
        @error('gender') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="entryYear" class="block text-sm font-medium">Tahun masuk <span class="text-red-600">*</span></label>
        <input id="entryYear" type="number" wire:model="entryYear" required min="2000" max="2100" class="mt-1 w-full rounded border-gray-300">
        @error('entryYear') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-medium">Status <span class="text-red-600">*</span></label>
        <select id="status" wire:model="status" required class="mt-1 w-full rounded border-gray-300">
            <option value="CALON">Calon</option>
            <option value="AKTIF">Aktif</option>
            <option value="LULUS">Lulus</option>
            <option value="PINDAH">Pindah</option>
            <option value="MENGUNDURKAN_DIRI">Mengundurkan diri</option>
            <option value="TIDAK_LULUS">Tidak lulus</option>
        </select>
        @error('status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="phone" class="block text-sm font-medium">Nomor telepon</label>
        <input id="phone" type="tel" wire:model="phone" maxlength="255" class="mt-1 w-full rounded border-gray-300">
        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium">Email</label>
        <input id="email" type="email" wire:model="email" maxlength="255" class="mt-1 w-full rounded border-gray-300">
        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label for="address" class="block text-sm font-medium">Alamat</label>
        <textarea id="address" wire:model="address" rows="3" class="mt-1 w-full rounded border-gray-300"></textarea>
        @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
