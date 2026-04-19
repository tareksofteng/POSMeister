<?php

namespace App\Traits;

/**
 * Auto-sets created_by and updated_by from the authenticated user.
 *
 * Add to any model that has `created_by` and `updated_by` columns.
 */
trait HasAuditFields
{
    protected static function bootHasAuditFields(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
