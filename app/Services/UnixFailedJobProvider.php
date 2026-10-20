<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;

class UnixFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    public function log(mixed $connection, mixed $queue, mixed $payload, mixed $exception): ?string
    {
        $uuid = json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['uuid'];
        $this->getTable()->insert(['uuid' => $uuid, 'connection' => $connection, 'queue' => $queue, 'payload' => $payload, 'exception' => (string) $exception, 'failed_at' => now()->getTimestamp()]);

        return $uuid;
    }

    public function flush(mixed $hours = null, mixed $queue = null): void
    {
        $this->getTable()->when($hours, fn (Builder $query): Builder => $query->where('failed_at', '<=', now()->subHours($hours)->getTimestamp()))->when($queue !== null, fn (Builder $query): Builder => $query->where('queue', $queue))->delete();
    }

    public function prune(DateTimeInterface $before): int
    {
        return $this->getTable()->where('failed_at', '<', $before->getTimestamp())->delete();
    }
}
