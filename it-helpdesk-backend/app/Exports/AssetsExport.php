<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['assignee', 'department']);
    }

    public function headings(): array
    {
        return ['Asset Tag', 'Name', 'Category', 'Manufacturer', 'Model', 'Serial Number',
            'Status', 'Assignee', 'Department', 'Location', 'Purchase Date', 'Purchase Cost',
            'Warranty Expiry', 'Notes'];
    }

    public function map($asset): array
    {
        return [
            $asset->asset_tag,
            $asset->name,
            $asset->category,
            $asset->manufacturer,
            $asset->model,
            $asset->serial_number,
            $asset->status,
            $asset->assignee?->name,
            $asset->department?->name,
            $asset->location,
            optional($asset->purchase_date)->format('Y-m-d'),
            $asset->purchase_cost,
            optional($asset->warranty_expiry)->format('Y-m-d'),
            $asset->notes,
        ];
    }
}
