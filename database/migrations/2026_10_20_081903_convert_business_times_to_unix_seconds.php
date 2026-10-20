<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FIELDS = [
        'users' => ['created_at', 'updated_at', 'deleted_at', 'email_verified_at'],
        'admin_accounts' => ['created_at', 'updated_at', 'deleted_at', 'activation_expires_at', 'last_login_at'],
        'admin_roles' => ['created_at', 'updated_at'],
        'admin_audit_logs' => ['created_at'],
        'tags' => ['created_at', 'updated_at', 'deleted_at'],
        'dramas' => ['created_at', 'updated_at', 'deleted_at'],
        'story_characters' => ['created_at', 'updated_at', 'deleted_at'],
        'operation_configs' => ['published_at'],
        'admin_idempotency_keys' => ['created_at'],
        'coin_adjustments' => ['created_at', 'updated_at'],
        'wallet_entries' => ['created_at', 'expires_at'],
        'password_reset_tokens' => ['created_at'],
        'failed_jobs' => ['failed_at'],
    ];

    public function up(): void
    {
        $this->convert(true);
        $this->convertDocuments(true);
    }

    public function down(): void
    {
        $this->convert(false);
        $this->convertDocuments(false);
    }

    private function convert(bool $toSeconds): void
    {
        foreach (self::FIELDS as $table => $fields) {
            foreach ($fields as $field) {
                $temp = $field.'_converted';
                if (! Schema::hasColumn($table, $temp)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($temp, $toSeconds): void {
                        if ($toSeconds) {
                            $blueprint->bigInteger($temp)->nullable();
                        } else {
                            $blueprint->timestamp($temp)->nullable();
                        }
                    });
                }
                $key = $table === 'password_reset_tokens' ? 'email' : 'id';
                DB::table($table)->select([$key, $field])->orderBy($key)->chunkById(250, function ($rows) use ($table, $key, $field, $temp, $toSeconds): void {
                    foreach ($rows as $row) {
                        $value = $row->{$field};
                        $converted = $value === null ? null : ($toSeconds ? Carbon::parse($value, config('app.timezone'))->getTimestamp() : Carbon::createFromTimestampUTC((int) $value)->format('Y-m-d H:i:s'));
                        DB::table($table)->where($key, $row->{$key})->update([$temp => $converted]);
                    }
                }, $key);
                $indexes = array_filter(Schema::getIndexes($table), fn (array $index): bool => in_array($field, $index['columns'], true));
                foreach ($indexes as $index) {
                    Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                        if ($index['unique']) {
                            $blueprint->dropUnique($index['name']);
                        } else {
                            $blueprint->dropIndex($index['name']);
                        }
                    });
                }
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($field));
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($temp, $field));
                foreach ($indexes as $index) {
                    Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                        if ($index['unique']) {
                            $blueprint->unique($index['columns'], $index['name']);
                        } else {
                            $blueprint->index($index['columns'], $index['name']);
                        }
                    });
                }
            }
        }
    }

    private function convertDocuments(bool $toSeconds): void
    {
        foreach (['operation_configs' => ['draft', 'published'], 'admin_idempotency_keys' => ['result'], 'admin_audit_logs' => ['details']] as $table => $fields) {
            DB::table($table)->orderBy('id')->chunkById(250, function ($rows) use ($table, $fields, $toSeconds): void {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($fields as $field) {
                        if ($row->{$field} !== null) {
                            $updates[$field] = json_encode($this->convertValues(json_decode($row->{$field}, true, flags: JSON_THROW_ON_ERROR), $toSeconds), JSON_THROW_ON_ERROR);
                        }
                    }
                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
    }

    private function convertValues(mixed $value, bool $toSeconds): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => &$item) {
            if (in_array($key, ['created_at', 'updated_at', 'deleted_at', 'registered_at', 'published_at', 'starts_at', 'ends_at', 'expires_at', 'expiresAt', 'activation_expires_at', 'last_login_at', 'email_verified_at'], true) && $item !== null) {
                if ($toSeconds) {
                    $item = is_numeric($item) ? (int) ($item > 100000000000 ? $item / 1000 : $item) : Carbon::parse($item)->getTimestamp();
                } elseif (is_int($item)) {
                    $item = Carbon::createFromTimestampUTC($item)->toIso8601ZuluString();
                }
            } else {
                $item = $this->convertValues($item, $toSeconds);
            }
        }

        return $value;
    }
};
