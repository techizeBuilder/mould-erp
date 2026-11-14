<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * An employee working a shift for a date range, overriding their default shift.
 * Ranges never overlap: assigning a new range trims or splits whatever it covers.
 */
class ShiftAssignment extends Model
{
    protected $fillable = ['employee_id', 'shift_id', 'start_date', 'end_date', 'created_by'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public static function assign(int $employeeId, int $shiftId, Carbon $start, Carbon $end, ?int $userId = null): self
    {
        return DB::transaction(function () use ($employeeId, $shiftId, $start, $end, $userId) {
            self::clear($employeeId, $start, $end);

            return self::create([
                'employee_id' => $employeeId,
                'shift_id' => $shiftId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'created_by' => $userId,
            ]);
        });
    }

    /** Removes the employee's assignments between the two dates, trimming or splitting ones that stick out. */
    public static function clear(int $employeeId, Carbon $start, Carbon $end): void
    {
        $overlapping = self::where('employee_id', $employeeId)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get();

        foreach ($overlapping as $a) {
            $before = $a->start_date->lt($start);
            $after = $a->end_date->gt($end);

            if ($before && $after) {
                $a->replicate()->fill(['start_date' => $end->copy()->addDay()->toDateString()])->save();
            }
            if ($before) {
                $a->update(['end_date' => $start->copy()->subDay()->toDateString()]);
            } elseif ($after) {
                $a->update(['start_date' => $end->copy()->addDay()->toDateString()]);
            } else {
                $a->delete();
            }
        }
    }
}
