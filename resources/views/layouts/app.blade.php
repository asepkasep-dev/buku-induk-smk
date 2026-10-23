<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Buku Induk Siswa' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>

    <body class="min-h-screen bg-slate-50 text-gray-900">
        @auth
            <header class="sticky top-0 z-20 bg-gradient-to-r from-brand-800 to-indigo-700 shadow-md">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/15 text-lg font-bold text-white ring-1 ring-white/25">
                            BI
                        </span>
                        <div>
                            <h1 class="text-lg font-semibold text-white">Buku Induk Siswa SMK</h1>

                            <nav class="mt-1 flex flex-wrap gap-1 text-sm">
                                @php
                                    $navClass = fn ($active) => 'rounded-lg px-2.5 py-1 font-medium transition-colors duration-150 '
                                        .($active ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white');
                                @endphp

                                <a href="{{ route('dashboard') }}" class="{{ $navClass(request()->routeIs('dashboard')) }}">
                                    Dashboard
                                </a>

                                @if (auth()->user()->hasPermission('VIEW_STUDENT'))
                                    <a href="{{ route('students.index') }}" class="{{ $navClass(request()->routeIs('students.*')) }}">
                                        Siswa
                                    </a>
                                @endif

                                @if (auth()->user()->role?->code === 'ADMIN')
                                    <a href="{{ route('admin.academic-setup') }}" class="{{ $navClass(request()->routeIs('admin.academic-setup')) }}">
                                        Pengaturan Akademik
                                    </a>
                                    <a href="{{ route('admin.users') }}" class="{{ $navClass(request()->routeIs('admin.users')) }}">
                                        Pengelolaan Akun
                                    </a>
                                @endif
                            </nav>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-sm text-white">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-white/20 text-sm font-semibold uppercase ring-1 ring-white/30">
                            {{ Str::of(auth()->user()->name)->trim()->explode(' ')->take(2)->map(fn ($w) => Str::substr($w, 0, 1))->implode('') }}
                        </span>
                        <div class="text-right leading-tight">
                            <div class="font-medium">{{ auth()->user()->name }}</div>
                            <div class="text-blue-100">
                                {{ auth()->user()->role?->name ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </header>
        @endauth

        <main class="mx-auto max-w-7xl animate-reveal px-6 py-8">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
