<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportProgress extends Model
{
    use HasFactory;

    protected $table = 'import_progress';

    protected $fillable = [
        'import_id',
        'user_id',
        'status',
        'progress',
        'message',
        'total_products',
        'processed',
        'imported',
        'duplicates',
        'errors',
        'file_name',
        'started_at',
        'completed_at',
        'cancelled_at',
        'error_message',
        'summary',
        'metadata'
    ];

    protected $casts = [
        'progress' => 'integer',
        'total_products' => 'integer',
        'processed' => 'integer',
        'imported' => 'integer',
        'duplicates' => 'integer',
        'errors' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'summary' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the import
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active imports
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['processing', 'queued']);
    }

    /**
     * Scope for completed imports
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed imports
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_products === 0) {
            return 0;
        }

        return min(100, max(0, intval(($this->processed / $this->total_products) * 100)));
    }

    /**
     * Check if import is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if import is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if import is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if import is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['processing', 'queued']);
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? $this->cancelled_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get duration in human readable format
     */
    public function getHumanDurationAttribute(): ?string
    {
        $duration = $this->duration;

        if ($duration === null) {
            return null;
        }

        if ($duration < 60) {
            return "{$duration} segundos";
        } elseif ($duration < 3600) {
            $minutes = intval($duration / 60);
            return "{$minutes} minutos";
        } else {
            $hours = intval($duration / 3600);
            $minutes = intval(($duration % 3600) / 60);
            return "{$hours}h {$minutes}m";
        }
    }

    /**
     * Get estimated time remaining
     */
    public function getEstimatedTimeRemainingAttribute(): ?int
    {
        if (!$this->isActive() || $this->processed === 0) {
            return null;
        }

        $duration = $this->duration ?? 0;
        $avgTimePerProduct = $duration / $this->processed;
        $remaining = $this->total_products - $this->processed;

        return intval($remaining * $avgTimePerProduct);
    }
}
