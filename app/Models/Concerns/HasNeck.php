<?php

namespace App\Models\Concerns;

use App\Models\Neck;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record made with a neck from Neck Master. The neck's values are also kept on the record,
 * for its generated name and for sales lines, and always follow the chosen neck.
 */
trait HasNeck
{
    public function neck(): BelongsTo
    {
        return $this->belongsTo(Neck::class);
    }

    /** Runs before the generated name is built (see HasGeneratedName). */
    public function syncSpecs(): void
    {
        if ($this->neck_id && ($neck = Neck::find($this->neck_id))) {
            $this->neck_size_mm = $neck->neck_size_mm;
            $this->neck_height_mm = $neck->neck_height_mm;
            $this->neck_type = $neck->neck_type;
        }
    }
}
