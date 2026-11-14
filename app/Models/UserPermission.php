<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    public const ACTIONS = ['show', 'view', 'create', 'edit', 'delete'];

    /** Rights a module gets when it is switched on for a sub-admin. Delete must be granted explicitly. */
    public const DEFAULT_ACCESS = [
        'can_show' => true,
        'can_view' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => false,
    ];

    protected $fillable = ['user_id', 'module', 'can_show', 'can_view', 'can_create', 'can_edit', 'can_delete'];

    protected function casts(): array
    {
        return [
            'can_show' => 'boolean',
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
