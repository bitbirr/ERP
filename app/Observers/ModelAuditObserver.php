<?php

namespace App\Observers;

use App\Domain\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class ModelAuditObserver
{
    public function created(Model $model): void
    {
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.created', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $old = array_intersect_key($model->getOriginal(), $changes);
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.updated', $model, $old, $changes);
    }

    public function deleted(Model $model): void
    {
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.deleted', $model, $model->getAttributes(), null);
    }

    public function restored(Model $model): void
    {
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.restored', $model, null, $model->getAttributes());
    }
}
