@props(['status'])

@php
    $map = [
        'AKTIF' => ['badge-green', 'Aktif'],
        'CALON' => ['badge-amber', 'Calon'],
        'LULUS' => ['badge-blue', 'Lulus'],
        'PINDAH' => ['badge-gray', 'Pindah'],
        'MENGUNDURKAN_DIRI' => ['badge-gray', 'Mengundurkan Diri'],
        'TIDAK_LULUS' => ['badge-rose', 'Tidak Lulus'],
    ];

    [$tone, $label] = $map[strtoupper((string) $status)] ?? ['badge-gray', $status];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$tone]) }}>{{ $label }}</span>
