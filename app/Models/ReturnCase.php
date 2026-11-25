<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnCase extends Model
{
    public const TYPES = [
        'exchange' => 'Exchange',
        'return' => 'Return',
        'problem' => 'Report a problem',
    ];

    public const REASONS = [
        'fit' => 'Size or fit',
        'incorrect_item' => 'Wrong or missing item',
        'damaged' => 'Damage or defect',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'approved' => 'Approved for handling',
        'declined' => 'Declined',
        'closed' => 'Closed',
    ];

    protected $fillable = ['order_id', 'reference', 'type', 'reason', 'description'];

    protected $hidden = ['staff_note', 'status_history', 'handled_by'];

    protected $attributes = ['status' => 'submitted'];

    protected function casts(): array
    {
        return ['status_history' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function nextOptions(): array
    {
        $allowed = match ($this->status) {
            'submitted' => ['under_review'],
            'under_review' => ['approved', 'declined'],
            'approved' => ['closed'],
            default => [],
        };

        return array_intersect_key(self::STATUSES, array_flip($allowed));
    }

    public function advance(string $status, User $staff, ?string $note): void
    {
        abort_unless($staff->role === 'admin', 403);

        DB::transaction(function () use ($status, $staff, $note): void {
            $record = self::query()->lockForUpdate()->findOrFail($this->getKey());
            if (! array_key_exists($status, $record->nextOptions())) {
                throw ValidationException::withMessages(['status' => 'That status is no longer available. Refresh and try again.']);
            }

            $history = $record->status_history ?: [];
            $history[] = [
                'from' => $record->status, 'to' => $status, 'staff_id' => $staff->getKey(),
                'at' => now()->toISOString(),
            ];
            $record->forceFill([
                'status' => $status, 'staff_note' => $note, 'handled_by' => $staff->getKey(),
                'status_history' => $history,
            ])->save();
        }, 3);

        $this->refresh();
    }
}
