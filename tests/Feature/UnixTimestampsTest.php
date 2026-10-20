<?php

namespace Tests\Feature;

use App\Models\AdminAccount;
use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnixTimestampsTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        $admin = AdminAccount::factory()->create();
        $token = bin2hex(random_bytes(32));
        Cache::put('admin:tokens:'.hash('sha256', $token), ['id' => $admin->id, 'version' => $admin->token_version], now()->addHour());

        return ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'timestamp-test'];
    }

    public function test_models_store_and_serialize_seconds_including_soft_deletes(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20T04:00:00Z'));
        $user = User::factory()->create();
        $this->assertSame(now()->getTimestamp(), (int) DB::table('users')->where('id', $user->id)->value('created_at'));
        $this->assertSame(now()->getTimestamp(), $user->toArray()['created_at']);
        $this->assertSame(now()->getTimestamp(), $user->toArray()['email_verified_at']);
        $user->delete();
        $this->assertSame(now()->getTimestamp(), (int) DB::table('users')->where('id', $user->id)->value('deleted_at'));
        $log = AdminAuditLog::create(['actor_id' => 1, 'actor' => 'test', 'action' => 'test', 'subject' => 'test']);
        $this->assertSame(now()->getTimestamp(), $log->toArray()['created_at']);
        $this->assertStringContainsString('int', Schema::getColumnType('users', 'created_at'));
    }

    public function test_login_system_time_and_configuration_use_seconds(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20T04:00:00Z'));
        $admin = AdminAccount::factory()->create();
        $this->postJson('/admin/v1/auth/login', ['email' => $admin->email, 'password' => 'administrator-password'])->assertOk()->assertJsonPath('data.expiresAt', now()->addMinutes(config('admin.token_minutes'))->getTimestamp());
        $headers = $this->headers();
        $this->getJson('/v1/server/time')->assertOk()->assertJsonPath('data.timestamp', now()->getTimestamp())->assertJsonMissingPath('data.iso8601')->assertJsonMissingPath('data.timestampMs');
        $item = ['id' => 'word', 'names' => ['en' => 'Hello', 'zh-Hans' => '', 'zh-Hant' => '', 'es' => '', 'pt' => '', 'id' => '', 'th' => ''], 'enabled' => true, 'starts_at' => 0, 'ends_at' => now()->getTimestamp() + 3600];
        $this->putJson('/admin/v1/ops/words', ['revision' => 1, 'draft' => [$item]], $headers)->assertOk();
        $this->postJson('/admin/v1/ops/words/publish', ['revision' => 2], $headers)->assertOk()->assertJsonPath('data.published_at', now()->getTimestamp());
        $item['starts_at'] = '2026-10-20T04:00:00Z';
        $this->putJson('/admin/v1/ops/words', ['revision' => 2, 'draft' => [$item]], $headers)->assertUnprocessable();
        $item['starts_at'] = now()->getTimestamp() * 1000;
        $this->putJson('/admin/v1/ops/words', ['revision' => 2, 'draft' => [$item]], $headers)->assertUnprocessable();
    }

    public function test_password_tokens_and_failed_jobs_work_with_integer_storage(): void
    {
        $user = User::factory()->create();
        $broker = app('auth.password')->broker();
        $token = $broker->createToken($user);
        $this->assertTrue($broker->tokenExists($user, $token));
        $this->assertSame(now()->getTimestamp(), (int) DB::table('password_reset_tokens')->value('created_at'));
        $this->travel(61)->minutes();
        $this->assertFalse($broker->tokenExists($user, $token));
        $broker->getRepository()->deleteExpired();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $provider = app('queue.failer');
        $provider->log('sync', 'default', json_encode(['uuid' => '00000000-0000-4000-8000-000000000001']), new \RuntimeException('test'));
        $this->assertSame(now()->getTimestamp(), (int) DB::table('failed_jobs')->value('failed_at'));
        $this->travel(2)->hours();
        $provider->prune(now()->subHour());
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_migration_preserves_legacy_dates_and_converts_embedded_schedules(): void
    {
        $migration = require database_path('migrations/2026_10_20_081903_convert_business_times_to_unix_seconds.php');
        $user = User::factory()->create();
        $timestamp = $user->created_at->getTimestamp();
        $migration->down();
        $this->assertSame(Carbon::createFromTimestampUTC($timestamp)->format('Y-m-d H:i:s'), DB::table('users')->where('id', $user->id)->value('created_at'));
        DB::table('operation_configs')->insert(['resource' => 'words', 'channel' => '', 'draft' => json_encode([['starts_at' => '2026-10-20T04:00:00Z', 'ends_at' => null]])]);
        $migration->up();
        $this->assertSame($timestamp, (int) DB::table('users')->where('id', $user->id)->value('created_at'));
        $this->assertNull(DB::table('users')->where('id', $user->id)->value('deleted_at'));
        $draft = json_decode(DB::table('operation_configs')->where('resource', 'words')->value('draft'), true);
        $this->assertSame(Carbon::parse('2026-10-20T04:00:00Z')->getTimestamp(), $draft[0]['starts_at']);
        $this->assertNull($draft[0]['ends_at']);
    }
}
