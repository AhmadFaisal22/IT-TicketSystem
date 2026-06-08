<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaPolicy extends Model
{
    protected $fillable = ['department_id', 'priority', 'response_hours', 'resolution_hours'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public static function findForTicket(int $departmentId, string $priority): self
    {
        return static::where('department_id', $departmentId)
            ->where('priority', $priority)
            ->first()
            ?? static::whereNull('department_id')
                ->where('priority', $priority)
                ->firstOrNew([
                    'priority' => $priority,
                    'response_hours' => match ($priority) {
                        'critical' => 1, 'high' => 4, 'medium' => 8, default => 24,
                    },
                    'resolution_hours' => match ($priority) {
                        'critical' => 4, 'high' => 8, 'medium' => 24, default => 72,
                    },
                ]);
    }
}
