<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TicketsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['creator', 'assignee', 'department']);
    }

    public function headings(): array
    {
        return ['Ticket', 'Title', 'Status', 'Priority', 'Category', 'Subcategory', 'Department',
            'Created By', 'Assignee', 'Created At', 'Resolved At', 'SLA Breached'];
    }

    public function map($ticket): array
    {
        return [
            $ticket->ticket_number,
            $ticket->title,
            $ticket->status,
            $ticket->priority,
            $ticket->category,
            $ticket->subcategory,
            $ticket->department?->name,
            $ticket->creator?->name,
            $ticket->assignee?->name,
            $ticket->created_at?->format('Y-m-d H:i'),
            $ticket->resolved_at?->format('Y-m-d H:i'),
            ($ticket->sla_response_breached || $ticket->sla_resolution_breached) ? 'Yes' : 'No',
        ];
    }
}
