<?php

use App\Services\GradePredicateResolver;

beforeEach(function () {
    $this->resolver = new GradePredicateResolver;
});

test('KKM 70 menghasilkan pita C 70-79, B 80-89, A 90-100', function () {
    $ranges = collect($this->resolver->ranges(70))->keyBy('letter');

    expect($ranges['A'])->toMatchArray(['min' => 90, 'max' => 100])
        ->and($ranges['B'])->toMatchArray(['min' => 80, 'max' => 89])
        ->and($ranges['C'])->toMatchArray(['min' => 70, 'max' => 79])
        ->and($ranges['D'])->toMatchArray(['min' => 0, 'max' => 69]);
});

test('KKM 75 menghasilkan pita C 75-83, B 84-91, A 92-100', function () {
    $ranges = collect($this->resolver->ranges(75))->keyBy('letter');

    expect($ranges['A'])->toMatchArray(['min' => 92, 'max' => 100])
        ->and($ranges['B'])->toMatchArray(['min' => 84, 'max' => 91])
        ->and($ranges['C'])->toMatchArray(['min' => 75, 'max' => 83]);
});

test('huruf mengikuti titik potong riil pada KKM 75', function () {
    expect($this->resolver->letter(74.9, 75))->toBe('D')
        ->and($this->resolver->letter(75, 75))->toBe('C')
        ->and($this->resolver->letter(83.3, 75))->toBe('C')
        ->and($this->resolver->letter(83.4, 75))->toBe('B')
        ->and($this->resolver->letter(91.6, 75))->toBe('B')
        ->and($this->resolver->letter(91.7, 75))->toBe('A')
        ->and($this->resolver->letter(100, 75))->toBe('A');
});

test('huruf null saat nilai null dan label predikat terisi', function () {
    expect($this->resolver->letter(null, 70))->toBeNull()
        ->and($this->resolver->predicate('A'))->toBe('Sangat Baik')
        ->and($this->resolver->predicate('D'))->toBe('Perlu Perbaikan');
});

test('ketuntasan membandingkan nilai akhir dengan KKM', function () {
    expect($this->resolver->isTuntas(75, 75))->toBeTrue()
        ->and($this->resolver->isTuntas(74, 75))->toBeFalse()
        ->and($this->resolver->isTuntas(null, 75))->toBeFalse();
});

test('resolve mengembalikan huruf, predikat, dan ketuntasan sekaligus', function () {
    expect($this->resolver->resolve(92, 75))->toBe([
        'letter' => 'A',
        'predicate' => 'Sangat Baik',
        'tuntas' => true,
    ]);
});
