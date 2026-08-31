<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Buku Induk Siswa' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>

    <body class="min-h-screen bg-gray-100 text-gray-900">
        @auth
            <header class="border-b bg-white">
                <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                    <div>
                        <h1 class="text-xl font-semibold">Buku Induk Siswa SMK</h1>

                        <nav class="mt-2 flex gap-4 text-sm">
                            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">
                                Dashboard
                            </a>

                            <a href="{{ route('students.index') }}" class="text-blue-600 hover:underline">
                                Siswa
                            </a>
                        </nav>
                    </div>

                    <div class="text-right text-sm">
                        <div class="font-medium">{{ auth()->user()->name }}</div>
                        <div class="text-gray-500">
                            {{ auth()->user()->role?->name ?? '-' }}
                        </div>
                    </div>
                </div>
            </header>
        @endauth

        <main class="mx-auto max-w-7xl px-6 py-8">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>