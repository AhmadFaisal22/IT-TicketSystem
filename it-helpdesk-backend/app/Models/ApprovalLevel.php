<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalLevel extends Model
{
    protected $fillable = ['name', 'department_id', 'level_order', 'approver_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function ticketApprovals(): HasMany
    {
        return $this->hasMany(TicketApproval::class);
    }
}
