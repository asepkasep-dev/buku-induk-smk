<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class StudentImportService
{
    private const HEADERS = [
        'nis', 'nisn', 'nik', 'full_name', 'gender', 'entry_year',
        'status', 'phone', 'email', 'address',
    ];

    private const REQUIRED_COLUMNS = ['nis', 'full_name', 'gender', 'entry_year', 'status'];

    private const MAX_ROWS = 500;

    public function inspect(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->fail('Format file harus XLSX atau XLS.');
        }

        try {
            $reader = $extension === 'xls' ? new Xls : new Xlsx;
            $spreadsheet = IOFactory::createReader($reader::class)->load($file->getRealPath());
        } catch (\Throwable) {
            return $this->fail('File Excel tidak dapat dibaca. Pastikan format .xlsx dan tidak rusak.');
        }

        $sheet = $spreadsheet->getSheet(0);
        // Raw values (no cell-format reformatting) so years/ids stay as plain digits.
        $grid = $sheet->toArray(null, true, false, false);

        if ($grid === []) {
            return $this->fail('File Excel kosong.');
        }

        $headerRow = array_shift($grid);
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headerRow);
        $headers = array_values($headers);

        if (count($headers) !== count(array_filter($headers, fn ($h) => $h !== ''))) {
            return $this->fail('Nama kolom tidak boleh kosong atau berulang.');
        }

        if (count($headers) !== count(array_unique($headers))) {
            return $this->fail('Nama kolom tidak boleh berulang.');
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));
        if ($missing !== []) {
            return $this->fail('Kolom wajib belum tersedia: '.implode(', ', $missing).'.');
        }

        $width = count($headers);
        $rows = [];
        $preview = [];
        $errors = [];
        $seen = ['nis' => [], 'nisn' => [], 'nik' => []];

        foreach ($grid as $index => $values) {
            $line = $index + 2; // sheet row number (1 header + 1-based offset)

            if (count(array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                $errors[] = 'Impor dibatasi sampai '.self::MAX_ROWS.' baris data per proses.';
                break;
            }

            $values = array_slice(array_pad($values, $width, null), 0, $width);
            $raw = array_combine($headers, $values);

            $row = [];
            foreach (self::HEADERS as $header) {
                $value = array_key_exists($header, $raw) && $raw[$header] !== null
                    ? trim((string) $raw[$header])
                    : '';
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
                $preview[] = [
                    'nis' => $row['nis'],
                    'full_name' => $row['full_name'],
                    'gender' => $row['gender'],
                    'entry_year' => $row['entry_year'],
                    'status' => $row['status'],
                ];
            }
        }

        return ['rows' => $rows, 'preview' => $preview, 'count' => count($rows), 'errors' => $errors];
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

    private function fail(string $message): array
    {
        return ['rows' => [], 'preview' => [], 'count' => 0, 'errors' => [$message]];
    }
}
