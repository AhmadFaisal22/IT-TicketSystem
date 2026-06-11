<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_tag', 'last_name', 'first_name', 'name', 'category', 'manufacturer', 'model',
        'serial_number', 'status', 'assigned_to', 'department_id',
        'location', 'purchase_date', 'purchase_cost', 'warranty_expiry', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            if (empty($asset->asset_tag)) {
                $asset->asset_tag = static::generateTag();
            }
        });
    }

    private static function generateTag(): string
    {
        $last = static::max('id') ?? 0;
        return 'AST-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AssetHistory::class)->orderByDesc('created_at');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function logHistory(int $userId, string $action, ?string $field = null, ?string $old = null, ?string $new = null): void
    {
        $this->histories()->create([
            'user_id'    => $userId,
            'action'     => $action,
            'field'      => $field,
            'old_value'  => $old,
            'new_value'  => $new,
            'created_at' => now(),
        ]);
    }
}
