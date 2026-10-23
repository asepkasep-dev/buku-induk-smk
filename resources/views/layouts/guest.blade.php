<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? 'Masuk · Buku Induk Siswa SMK' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>

    <body class="min-h-screen bg-slate-100 text-gray-900">
        <div class="grid min-h-screen lg:grid-cols-2">
            {{-- Brand panel --}}
            <div class="relative hidden overflow-hidden bg-gradient-to-br from-brand-800 via-brand-700 to-indigo-800 lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-2xl"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-indigo-400/20 blur-3xl"></div>

                <div class="relative flex items-center gap-3 text-white">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/15 text-lg font-bold ring-1 ring-white/25">BI</span>
                    <span class="text-lg font-semibold">Buku Induk Siswa SMK</span>
                </div>

                <div class="relative text-white">
                    <h2 class="text-3xl font-semibold leading-tight">Sistem administrasi akademik sekolah</h2>
                    <p class="mt-3 max-w-md text-blue-100">
                        Kelola buku induk, rapor, dan data siswa dalam satu tempat — aman dan terpusat di jaringan sekolah.
                    </p>
                </div>

                <p class="relative text-sm text-blue-200">&copy; {{ now()->year }} {{ \App\Models\School::query()->first()?->name ?? 'Buku Induk Siswa SMK' }}</p>
            </div>

            {{-- Form panel --}}
            <div class="flex items-center justify-center p-6 sm:p-12">
                <div class="w-full max-w-md animate-reveal">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-600 text-base font-bold text-white">BI</span>
                        <span class="text-base font-semibold text-gray-800">Buku Induk Siswa SMK</span>
                    </div>

                    {{ $slot }}
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
