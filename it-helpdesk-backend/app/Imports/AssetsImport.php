<?php

namespace App\Imports;

use App\Models\Asset;
use App\Models\AssetCategory;
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
            $tag = trim((string) ($row['asset_tag'] ?? $row['fixed_assets_tag'] ?? ''));

            if ($category === '' || !AssetCategory::where('name', $category)->exists()) {
                $this->rejected[] = ['row' => $i + 2, 'reason' => 'Invalid category'];
                continue;
            }
            if ($tag !== '' && Asset::where('asset_tag', $tag)->exists()) {
                $this->rejected[] = ['row' => $i + 2, 'reason' => "Duplicate asset_tag {$tag}"];
                continue;
            }
            if (!empty($serial) && Asset::where('serial_number', $serial)->exists()) {
                $this->rejected[] = ['row' => $i + 2, 'reason' => "Duplicate serial_number {$serial}"];
                continue;
            }

            Asset::create([
                'asset_tag'     => $tag !== '' ? $tag : null, // null -> auto-generated fallback
                'name'          => $name !== '' ? $name : null,
                'last_name'     => $row['last_name'] ?? null,
                'first_name'    => $row['first_name'] ?? null,
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
