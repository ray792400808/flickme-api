<?php

namespace App\Services;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

class UnixPasswordTokenRepository extends DatabaseTokenRepository
{
    protected function getPayload(mixed $email, #[\SensitiveParameter] mixed $token): array
    {
        return ['email' => $email, 'token' => $this->hasher->make($token), 'created_at' => now()->getTimestamp()];
    }

    protected function tokenExpired(mixed $createdAt): bool
    {
        return (int) $createdAt + $this->expires < now()->getTimestamp();
    }

    protected function tokenRecentlyCreated(mixed $createdAt): bool
    {
        return $this->throttle > 0 && (int) $createdAt + $this->throttle > now()->getTimestamp();
    }

    public function deleteExpired(): void
    {
        $this->getTable()->where('created_at', '<', now()->getTimestamp() - $this->expires)->delete();
    }
}
