<?php

namespace App\Support;

use App\Models\AuditLog;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAudit('created', null, $model->getAuditAttributes());
        });

        static::updated(function ($model) {
            $original = array_intersect_key($model->getOriginal(), $model->getAuditAttributes());
            $changes = $model->getChanges();
            $auditChanges = array_intersect_key($changes, $model->getAuditAttributes());

            if (! empty($auditChanges)) {
                $model->writeAudit('updated', $original, $auditChanges);
            }
        });

        static::deleted(function ($model) {
            $model->writeAudit('deleted', $model->getAuditAttributes(), null);
        });
    }

    public function writeAudit(string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    abstract protected function getAuditAttributes(): array;
}
