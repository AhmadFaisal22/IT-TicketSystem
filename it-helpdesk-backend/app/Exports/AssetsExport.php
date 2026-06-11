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
        return ['Asset Tag', 'Last Name', 'First Name', 'Department', 'Category', 'Manufacturer', 'Model', 'Serial Number',
            'Status', 'Assignee', 'Location', 'Notes'];
    }

    public function map($asset): array
    {
        return [
            $asset->asset_tag,
            $asset->last_name,
            $asset->first_name,
            $asset->department?->name,
            $asset->category,
            $asset->manufacturer,
            $asset->model,
            $asset->serial_number,
            $asset->status,
            $asset->assignee?->name,
            $asset->location,
            $asset->notes,
        ];
    }
}
