<?php

namespace App\Models\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

trait HasUnixTimestamps
{
    public function getDateFormat(): string
    {
        return 'U';
    }

    public function initializeHasUnixTimestamps(): void
    {
        $this->mergeCasts(['created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime']);
    }

    public static function bootHasUnixTimestamps(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->usesTimestamps() && $model->getAttribute('created_at') === null) {
                $model->setAttribute('created_at', now());
            }
        });
    }

    protected function serializeDate(DateTimeInterface $date): int
    {
        return $date->getTimestamp();
    }
}
