<?php

namespace App\Observers;

use App\Domain\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class ModelAuditObserver
{
    public function created(Model $model): void
    {
        $context = [];
        if ($model instanceof \App\Models\StockMovement) {
            $context = [
                'ref' => $model->ref,
                'movement_type' => $model->type,
            ];
        }
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.created', $model, null, $model->getAttributes(), $context);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        $old = array_intersect_key($model->getOriginal(), $changes);
        $context = [];
        if ($model instanceof \App\Models\StockMovement) {
            $context = [
                'ref' => $model->ref,
                'movement_type' => $model->type,
            ];
        }
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.updated', $model, $old, $changes, $context);
    }

    public function deleted(Model $model): void
    {
        $context = [];
        if ($model instanceof \App\Models\StockMovement) {
            $context = [
                'ref' => $model->ref,
                'movement_type' => $model->type,
            ];
        }
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.deleted', $model, $model->getAttributes(), null, $context);
    }

    public function restored(Model $model): void
    {
        $context = [];
        if ($model instanceof \App\Models\StockMovement) {
            $context = [
                'ref' => $model->ref,
                'movement_type' => $model->type,
            ];
        }
        app(AuditLogger::class)->log(strtolower(class_basename($model)).'.restored', $model, null, $model->getAttributes(), $context);
    }
}
