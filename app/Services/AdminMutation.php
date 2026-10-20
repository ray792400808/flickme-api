<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMutation
{
    public static function run(Request $request, string $scope, Closure $operation): array
    {
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && strlen($key) > 0 && strlen($key) <= 200, 422, '必须提供 Idempotency-Key，最多200字节');

        return DB::transaction(function () use ($request, $scope, $operation, $key): array {
            $identity = ['actor_id' => $request->user()->id, 'scope' => $scope, 'key_hash' => hash('sha256', $key)];
            $hash = hash('sha256', json_encode($request->all(), JSON_THROW_ON_ERROR));
            DB::table('admin_idempotency_keys')->insertOrIgnore($identity + ['body_hash' => $hash, 'created_at' => now()->getTimestamp()]);
            $record = DB::table('admin_idempotency_keys')->where($identity)->lockForUpdate()->first();
            abort_unless(hash_equals($record->body_hash, $hash), 409, '幂等键已用于不同请求');
            if ($record->result !== null) {
                return json_decode($record->result, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = $operation();
            DB::table('admin_idempotency_keys')->where('id', $record->id)->update(['result' => json_encode($result, JSON_THROW_ON_ERROR)]);

            return $result;
        }, 3);
    }

    public static function audit(Request $request, string $action, string $subject, array $details): void
    {
        DB::table('admin_audit_logs')->insert(['actor_id' => $request->user()->id, 'actor' => $request->user()->email, 'action' => $action, 'subject' => $subject, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()->getTimestamp()]);
    }
}
