<?php

namespace Tests\Feature;

use App\Models\AdminAccount;
use App\Models\AdminRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_active_super_admin_cannot_be_removed_by_another_role(): void
    {
        $admin = AdminAccount::factory()->create();
        AdminRole::query()->where('key', 'ops')->firstOrFail()->update(['permissions' => ['system' => 'rw']]);
        $operator = AdminAccount::factory()->create(['role_key' => 'ops']);
        $headers = $this->headersFor($operator);
        $this->deleteJson('/admin/v1/accounts/'.$admin->id, [], $headers)->assertUnprocessable();
        $this->patchJson('/admin/v1/accounts/'.$admin->id, ['name' => $admin->name, 'role_key' => 'content', 'status' => 'active'], $headers)->assertUnprocessable();
        $this->postJson('/admin/v1/accounts/'.$admin->id.'/reset-password', [], $headers)->assertUnprocessable();
    }

    public function test_password_reset_invalidates_sessions_and_expired_links_are_rejected(): void
    {
        $admin = AdminAccount::factory()->create();
        $operator = AdminAccount::factory()->create(['role_key' => 'ops']);
        $oldSession = $this->headersFor($operator);
        $response = $this->postJson('/admin/v1/accounts/'.$operator->id.'/reset-password', [], $this->headersFor($admin))->assertOk();
        $this->getJson('/admin/v1/auth/me', $oldSession)->assertUnauthorized();
        $operator->refresh()->update(['activation_expires_at' => now()->subMinute()]);
        $this->postJson('/admin/v1/auth/activate', ['token' => $response->json('data.activationToken'), 'password' => 'new-password-long', 'password_confirmation' => 'new-password-long'])->assertUnprocessable();
    }

    public function test_role_reassignment_revokes_old_tokens_and_duplicate_email_is_rejected(): void
    {
        $admin = AdminAccount::factory()->create();
        $operator = AdminAccount::factory()->create(['role_key' => 'ops']);
        $oldSession = $this->headersFor($operator);
        $headers = $this->headersFor($admin);
        $this->patchJson('/admin/v1/accounts/'.$operator->id, ['name' => $operator->name, 'role_key' => 'content', 'status' => 'active'], $headers)->assertOk();
        $this->getJson('/admin/v1/auth/me', $oldSession)->assertUnauthorized();
        $this->postJson('/admin/v1/accounts', ['name' => 'Duplicate', 'email' => $operator->email, 'role_key' => 'ops'], $headers)->assertUnprocessable();
    }

    public function test_bootstrap_imports_only_once(): void
    {
        config(['admin.email' => 'initial@example.com', 'admin.password_hash' => Hash::make('initial-long-password')]);
        $this->artisan('admin:bootstrap', ['--no-interaction' => true])->assertExitCode(0);
        $this->artisan('admin:bootstrap', ['--no-interaction' => true])->assertExitCode(0);
        $this->assertDatabaseCount('admin_accounts', 1);
        $this->postJson('/admin/v1/auth/login', ['email' => 'initial@example.com', 'password' => 'initial-long-password'])->assertOk();
    }

    private function headersFor(AdminAccount $account): array
    {
        $token = bin2hex(random_bytes(32));
        Cache::put('admin:tokens:'.hash('sha256', $token), ['id' => $account->id, 'version' => $account->token_version], now()->addHour());

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_multiple_accounts_can_login_and_disabled_accounts_cannot(): void
    {
        $first = AdminAccount::factory()->create();
        $second = AdminAccount::factory()->create(['role_key' => 'ops']);
        foreach ([$first, $second] as $account) {
            $this->postJson('/admin/v1/auth/login', ['email' => $account->email, 'password' => 'administrator-password'])->assertOk()->assertJsonStructure(['data' => ['accessToken']]);
        }
        $second->update(['status' => 'disabled']);
        $this->postJson('/admin/v1/auth/login', ['email' => $second->email, 'password' => 'administrator-password'])->assertUnauthorized();
    }

    public function test_invitation_is_one_time_and_does_not_expose_secrets(): void
    {
        $admin = AdminAccount::factory()->create();
        $headers = $this->headersFor($admin);
        $response = $this->postJson('/admin/v1/accounts', ['name' => 'Operator', 'email' => 'operator@example.com', 'role_key' => 'ops'], $headers)->assertCreated();
        $token = $response->json('data.activationToken');
        $this->assertArrayNotHasKey('activation_hash', $response->json('data.account'));
        $body = ['token' => $token, 'password' => 'new-secure-password', 'password_confirmation' => 'new-secure-password'];
        $this->postJson('/admin/v1/auth/activate', $body)->assertOk();
        $this->postJson('/admin/v1/auth/activate', $body)->assertUnprocessable();
        $this->postJson('/admin/v1/auth/login', ['email' => 'operator@example.com', 'password' => 'new-secure-password'])->assertOk();
    }

    public function test_non_system_role_cannot_manage_accounts(): void
    {
        $ops = AdminAccount::factory()->create(['role_key' => 'ops']);
        $this->getJson('/admin/v1/accounts', $this->headersFor($ops))->assertForbidden();
        $this->postJson('/admin/v1/accounts', ['name' => 'No', 'email' => 'no@example.com', 'role_key' => 'admin'], $this->headersFor($ops))->assertForbidden();
    }

    public function test_disabled_account_loses_its_existing_session(): void
    {
        $admin = AdminAccount::factory()->create();
        $ops = AdminAccount::factory()->create(['role_key' => 'ops']);
        $headers = $this->headersFor($ops);
        $this->patchJson('/admin/v1/accounts/'.$ops->id, ['name' => $ops->name, 'role_key' => 'ops', 'status' => 'disabled'], $this->headersFor($admin))->assertOk();
        $this->getJson('/admin/v1/auth/me', $headers)->assertUnauthorized();
    }

    public function test_current_admin_cannot_disable_or_delete_itself(): void
    {
        $admin = AdminAccount::factory()->create();
        $headers = $this->headersFor($admin);
        $this->patchJson('/admin/v1/accounts/'.$admin->id, ['name' => $admin->name, 'role_key' => 'admin', 'status' => 'disabled'], $headers)->assertUnprocessable();
        $this->deleteJson('/admin/v1/accounts/'.$admin->id, [], $headers)->assertUnprocessable();
    }

    public function test_role_changes_apply_to_existing_sessions_and_admin_is_immutable(): void
    {
        $admin = AdminAccount::factory()->create();
        $headers = $this->headersFor($admin);
        $ops = AdminAccount::factory()->create(['role_key' => 'ops']);
        $opsHeaders = $this->headersFor($ops);
        $role = AdminRole::query()->where('key', 'ops')->firstOrFail();
        $this->patchJson('/admin/v1/roles/'.$role->id, ['permissions' => ['content' => 'rw']], $headers)->assertOk();
        $this->getJson('/admin/v1/auth/me', $opsHeaders)->assertOk()->assertJsonPath('data.permissions.content', 'rw');
        $adminRole = AdminRole::query()->where('key', 'admin')->firstOrFail();
        $this->patchJson('/admin/v1/roles/'.$adminRole->id, ['permissions' => ['system' => 'none']], $headers)->assertUnprocessable();
    }
}
