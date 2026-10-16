<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentCsvImportService
{
    private const HEADERS = [
        'nis', 'nisn', 'nik', 'full_name', 'gender', 'entry_year',
        'status', 'phone', 'email', 'address',
    ];

    public function inspect(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return ['rows' => [], 'preview' => [], 'count' => 0, 'errors' => ['File CSV tidak dapat dibaca.']];
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return ['rows' => [], 'preview' => [], 'count' => 0, 'errors' => ['File CSV kosong.']];
            }
            $delimiter = count(str_getcsv($firstLine, ';')) > count(str_getcsv($firstLine, ',')) ? ';' : ',';
            rewind($handle);
            $headers = fgetcsv($handle, null, $delimiter, '"', '\\');
            if (! is_array($headers)) {
                return ['rows' => [], 'preview' => [], 'count' => 0, 'errors' => ['Baris judul CSV tidak dapat dibaca.']];
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            if (count($headers) !== count(array_unique($headers))) {
                return ['rows' => [], 'preview' => [], 'count' => 0, 'errors' => ['Nama kolom CSV tidak boleh berulang.']];
            }
            $missing = array_values(array_diff(['nis', 'full_name', 'gender', 'entry_year', 'status'], $headers));
            if ($missing !== []) {
                return ['rows' => [], 'preview' => [], 'count' => 0, 'errors' => ['Kolom wajib belum tersedia: '.implode(', ', $missing).'.']];
            }

            $rows = [];
            $preview = [];
            $errors = [];
            $seen = ['nis' => [], 'nisn' => [], 'nik' => []];
            $line = 1;
            while (($values = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
                $line++;
                if ($values === [null] || count(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if ($line > 501) {
                    $errors[] = 'CSV dibatasi sampai 500 baris data per proses.';
                    break;
                }
                if (count($values) !== count($headers)) {
                    $errors[] = "Baris {$line}: jumlah kolom tidak sama dengan baris judul.";

                    continue;
                }

                $raw = array_combine($headers, $values);
                $row = [];
                foreach (self::HEADERS as $header) {
                    $value = array_key_exists($header, $raw) ? trim((string) $raw[$header]) : '';
                    $row[$header] = $value === '' ? null : $value;
                }
                $row['gender'] = strtoupper((string) $row['gender']);
                $row['status'] = strtoupper((string) $row['status']);

                $validator = Validator::make($row, [
                    'nis' => ['required', 'string', 'max:255', 'unique:students,nis'],
                    'nisn' => ['nullable', 'string', 'max:255', 'unique:students,nisn'],
                    'nik' => ['nullable', 'digits:16', 'unique:students,nik'],
                    'full_name' => ['required', 'string', 'max:255'],
                    'gender' => ['required', Rule::in(['L', 'P'])],
                    'entry_year' => ['required', 'integer', 'between:2000,2100'],
                    'status' => ['required', Rule::in(['CALON', 'AKTIF', 'LULUS', 'PINDAH', 'MENGUNDURKAN_DIRI', 'TIDAK_LULUS'])],
                    'phone' => ['nullable', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'address' => ['nullable', 'string', 'max:5000'],
                ]);

                $duplicateMessages = [];
                foreach (['nis', 'nisn', 'nik'] as $uniqueField) {
                    $value = $row[$uniqueField];
                    if ($value !== null && isset($seen[$uniqueField][$value])) {
                        $duplicateMessages[] = ucfirst($uniqueField).': nilai muncul lebih dari sekali di file.';
                    }
                    if ($value !== null) {
                        $seen[$uniqueField][$value] = true;
                    }
                }
                if ($validator->fails() || $duplicateMessages !== []) {
                    foreach (array_merge($validator->errors()->all(), $duplicateMessages) as $message) {
                        if (count($errors) < 20) {
                            $errors[] = "Baris {$line}: {$message}";
                        }
                    }

                    continue;
                }

                $row['entry_year'] = (int) $row['entry_year'];
                $rows[] = $row;
                if (count($preview) < 10) {
                    $preview[] = ['nis' => $row['nis'], 'full_name' => $row['full_name'], 'gender' => $row['gender'], 'entry_year' => $row['entry_year'], 'status' => $row['status']];
                }
            }

            return ['rows' => $rows, 'preview' => $preview, 'count' => count($rows), 'errors' => $errors];
        } finally {
            fclose($handle);
        }
    }

    public function import(User $user, array $rows, StudentRegistryService $registry): int
    {
        return DB::transaction(function () use ($user, $rows, $registry): int {
            foreach ($rows as $row) {
                $registry->create($user, $row);
            }

            return count($rows);
        });
    }
}
