<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError(
                'email',
                'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.'
            );

            return;
        }

        if (! Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
        ])) {
            RateLimiter::hit($key, 60);
            $this->addError('email', 'Email atau password salah.');

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->route('dashboard');
    }
};
?>

<div class="card p-8">
    <h1 class="text-2xl font-semibold text-gray-900">Masuk ke akun Anda</h1>
    <p class="mt-1.5 text-sm text-gray-500">Gunakan akun yang diberikan operator sekolah.</p>

    <form wire:submit="login" class="mt-7 space-y-5">
        <div>
            <label for="email" class="field-label">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                autocomplete="username"
                placeholder="nama@sekolah.sch.id"
                wire:model="email"
            >

            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="field-label">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                placeholder="••••••••"
                wire:model="password"
            >

            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="btn-primary w-full">
            Masuk
        </button>
    </form>
</div>
