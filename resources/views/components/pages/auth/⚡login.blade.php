<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $email = '';

    public string $password = '';

    public function login()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt([
            'email' => $this->email,
            'password' => $this->password,
        ])) {
            $this->addError('email', 'Email atau password salah.');

            return;
        }

        session()->regenerate();

        return redirect()->route('dashboard');
    }
};
?>

<div>
    <h1>Login Buku Induk Siswa</h1>

    <form wire:submit="login">
        <div>
            <label for="email">Email</label>

            <input
                type="email"
                id="email"
                wire:model="email"
            >

            @error('email')
                <div>{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                wire:model="password"
            >

            @error('password')
                <div>{{ $message }}</div>
            @enderror
        </div>

        <button type="submit">
            Login
        </button>
    </form>
</div>