<?php

namespace App\Imports;

use App\Models\Asset;
use App\Support\AssetCategories;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AssetsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    /** @var array<int,array{row:int,reason:string}> */
    public array $rejected = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            $serial = $row['serial_number'] ?? null;

            if ($name === '' || !in_array($category, AssetCategories::KEYS, true)) {
                $this->rejected[] = ['row' => $i + 2, 'reason' => 'Missing name or invalid category'];
                continue;
            }
            if (!empty($serial) && Asset::where('serial_number', $serial)->exists()) {
                $this->rejected[] = ['row' => $i + 2, 'reason' => "Duplicate serial_number {$serial}"];
                continue;
            }

            Asset::create([
                'name'          => $name,
                'category'      => $category,
                'manufacturer'  => $row['manufacturer'] ?? null,
                'model'         => $row['model'] ?? null,
                'serial_number' => $serial ?: null,
                'status'        => in_array($row['status'] ?? null, AssetCategories::STATUSES, true) ? $row['status'] : 'in_stock',
                'location'      => $row['location'] ?? null,
            ]);
            $this->created++;
        }
    }
}
