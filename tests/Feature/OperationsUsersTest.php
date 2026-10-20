<?php

namespace Tests\Feature;

use App\Models\AdminAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperationsUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_users_and_administrators_cannot_authenticate(): void
    {
        $admin = AdminAccount::factory()->create();
        $headers = $this->headersFor($admin);
        $user = User::factory()->create();
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->getJson('/admin/v1/users/'.$user->id, $headers)->assertNotFound();
        $this->assertNull(app('auth')->createUserProvider('users')->retrieveById($user->id));
        $admin->delete();
        $this->getJson('/admin/v1/auth/me', $headers)->assertUnauthorized();
        $this->assertSoftDeleted('admin_accounts', ['id' => $admin->id]);
    }

    public function test_banned_users_cannot_authenticate_or_restore_sessions(): void
    {
        $user = User::factory()->create(['status' => 'banned']);
        $provider = app('auth')->createUserProvider('users');
        $this->assertNull($provider->retrieveById($user->id));
        $this->assertNull($provider->retrieveByCredentials(['email' => $user->email, 'password' => 'password']));
        $user->status = 'active';
        $user->save();
        $this->assertNotNull($provider->retrieveById($user->id));
    }

    public function test_all_six_configuration_resources_round_trip_without_publishing_drafts(): void
    {
        $headers = $this->headersFor(AdminAccount::factory()->create());
        foreach (['hot', 'launch', 'words', 'filters', 'recs'] as $resource) {
            $document = $this->getJson('/admin/v1/ops/'.$resource, $headers)->assertOk()->json('data');
            $this->putJson('/admin/v1/ops/'.$resource, ['revision' => 1, 'draft' => $document['draft']], $headers)->assertOk()->assertJsonPath('data.revision', 2);
            $this->postJson('/admin/v1/ops/'.$resource.'/publish', ['revision' => 2], $headers)->assertOk()->assertJsonPath('data.published_revision', 2);
        }
        $this->assertDatabaseCount('admin_audit_logs', 10);
    }

    private function headersFor(AdminAccount $account, string $key = 'request-1'): array
    {
        $token = bin2hex(random_bytes(32));
        Cache::put('admin:tokens:'.hash('sha256', $token), ['id' => $account->id, 'version' => $account->token_version], now()->addHour());

        return ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => $key];
    }

    public function test_drafts_are_versioned_and_channels_publish_independently(): void
    {
        $headers = $this->headersFor(AdminAccount::factory()->create());
        $draft = $this->getJson('/admin/v1/ops/chips?channel=short', $headers)->assertOk()->json('data.draft');
        $this->putJson('/admin/v1/ops/chips?channel=short', ['revision' => 1, 'draft' => $draft], $headers)->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.published_revision', null);
        $this->putJson('/admin/v1/ops/chips?channel=short', ['revision' => 1, 'draft' => $draft], $headers)->assertConflict();
        $this->postJson('/admin/v1/ops/chips/publish?channel=short', ['revision' => 2], $headers)->assertOk()->assertJsonPath('data.published_revision', 2);
        $this->postJson('/admin/v1/ops/chips/publish?channel=short', ['revision' => 2], $headers)->assertOk();
        $this->getJson('/admin/v1/ops/chips?channel=stories', $headers)->assertOk()->assertJsonPath('data.revision', 1)->assertJsonPath('data.published_revision', null);
        $this->assertDatabaseCount('admin_audit_logs', 2);
        $draft[0]['enabled'] = false;
        $this->putJson('/admin/v1/ops/chips?channel=short', ['revision' => 2, 'draft' => $draft], $headers)->assertUnprocessable();
    }

    public function test_operations_validate_languages_dates_references_and_idempotency(): void
    {
        $headers = $this->headersFor(AdminAccount::factory()->create());
        $this->getJson('/admin/v1/ops/words', $headers)->assertOk();
        $item = ['id' => 'word-1', 'names' => ['en' => 'Hello', 'zh-Hans' => '', 'zh-Hant' => '', 'es' => '', 'pt' => '', 'id' => '', 'th' => ''], 'enabled' => true, 'starts_at' => null, 'ends_at' => null];
        $this->putJson('/admin/v1/ops/words', ['revision' => 1, 'draft' => [$item]], $headers)->assertOk();
        $this->postJson('/admin/v1/ops/words/publish', ['revision' => 2], $headers)->assertOk();
        $this->postJson('/admin/v1/ops/words/publish', ['revision' => 3], $headers)->assertConflict();
        $item['ends_at'] = 1767225600;
        $item['starts_at'] = 1769904000;
        $this->putJson('/admin/v1/ops/words', ['revision' => 2, 'draft' => [$item]], $headers)->assertUnprocessable();
        $this->getJson('/admin/v1/ops/unknown', $headers)->assertNotFound();
        $this->getJson('/admin/v1/ops/chips', $headers)->assertUnprocessable();
        $this->getJson('/admin/v1/ops/hot?channel=short', $headers)->assertUnprocessable();
        $this->getJson('/admin/v1/ops/launch', $headers)->assertOk();
        $this->putJson('/admin/v1/ops/launch', ['revision' => 1, 'draft' => ['enabled' => true, 'fallback_drama_id' => 1, 'cover_url' => '', 'button_text' => ['en' => 'Go', 'zh-Hans' => '', 'zh-Hant' => '', 'es' => '', 'pt' => '', 'id' => '', 'th' => '']]], $headers)->assertUnprocessable();
    }

    public function test_read_only_permissions_and_user_versions_are_enforced(): void
    {
        $reader = $this->headersFor(AdminAccount::factory()->create(['role_key' => 'service']));
        $user = User::factory()->create(['uid' => '000123', 'bonus' => 20]);
        $this->getJson('/admin/v1/users?q=000123&per_page=10', $reader)->assertOk()->assertJsonPath('data.data.0.uid', '000123')->assertJsonMissingPath('data.data.0.password');
        $this->patchJson('/admin/v1/users/'.$user->id.'/status', ['status' => 'banned', 'reason' => 'test', 'version' => 1], $reader)->assertForbidden();
        $admin = $this->headersFor(AdminAccount::factory()->create());
        $this->patchJson('/admin/v1/users/'.$user->id.'/status', ['status' => 'banned', 'reason' => 'test', 'version' => 1], $admin)->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson('/admin/v1/users/'.$user->id.'/status', ['status' => 'active', 'reason' => 'test', 'version' => 1], $admin)->assertConflict();
        $this->getJson('/admin/v1/ops/words', $reader)->assertForbidden();
        $this->getJson('/admin/v1/users')->assertUnauthorized();
    }

    public function test_coin_approval_is_atomic_and_idempotent(): void
    {
        $user = User::factory()->create(['uid' => '000123', 'bonus' => 20]);
        $requester = AdminAccount::factory()->create();
        $headers = $this->headersFor($requester);
        $body = ['uid' => '000123', 'currency' => 'bonus', 'amount' => -10, 'reason' => '纠错'];
        $id = $this->postJson('/admin/v1/coin-adjustments', $body, $headers)->assertCreated()->json('data.id');
        $this->assertIsInt($id);
        $this->assertEquals(20, $user->fresh()->bonus);
        $this->postJson('/admin/v1/coin-adjustments', $body, $headers)->assertCreated()->assertJsonPath('data.id', $id);
        $this->postJson('/admin/v1/coin-adjustments', array_replace($body, ['amount' => -9]), $headers)->assertConflict();
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'approve', 'version' => 1], $headers)->assertForbidden();
        $reviewer = $this->headersFor(AdminAccount::factory()->create());
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'approve', 'version' => 1], $reviewer)->assertOk()->assertJsonPath('data.status', 'approved');
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'approve', 'version' => 1], $reviewer)->assertOk();
        $this->assertEquals(10, $user->fresh()->bonus);
        $this->assertDatabaseCount('wallet_entries', 1);
        $reviewer['Idempotency-Key'] = 'other';
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'approve', 'version' => 1], $reviewer)->assertConflict();
    }

    public function test_insufficient_balance_and_review_permissions_do_not_change_money(): void
    {
        $user = User::factory()->create(['uid' => '000124', 'bonus' => 5]);
        $headers = $this->headersFor(AdminAccount::factory()->create());
        $id = $this->postJson('/admin/v1/coin-adjustments', ['uid' => $user->uid, 'currency' => 'bonus', 'amount' => -10, 'reason' => 'test'], $headers)->assertCreated()->json('data.id');
        $reviewer = $this->headersFor(AdminAccount::factory()->create());
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'approve', 'version' => 1], $reviewer)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_entries', 0);
        $this->assertDatabaseHas('coin_adjustments', ['id' => $id, 'status' => 'pending', 'version' => 1]);
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'reject', 'version' => 1], $reviewer)->assertUnprocessable();
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'reject', 'reason' => '余额不足', 'version' => 1], $reviewer)->assertOk();
        $this->assertEquals(5, $user->fresh()->bonus);
        $this->postJson('/admin/v1/coin-adjustments/'.$id.'/review', ['decision' => 'approve', 'version' => 1], $this->headersFor(AdminAccount::factory()->create(['role_key' => 'service'])))->assertForbidden();
    }
}
