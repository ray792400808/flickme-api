<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Database\Seeders\AdminSeeder;
use Database\Seeders\IncentiveSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IncentiveEndpointsTest extends TestCase
{
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_10_20_000001_create_admin_rbac_tables.php',
            'database/migrations/2026_10_21_034746_create_incentive_configs_table.php',
        ], '--no-interaction' => true])->assertExitCode(0);
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor');
            $table->string('action');
            $table->string('subject');
            $table->json('details');
            $table->bigInteger('created_at');
        });
        Schema::create('admin_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('actor_id');
            $table->string('scope');
            $table->string('key_hash');
            $table->string('body_hash');
            $table->json('result')->nullable();
            $table->bigInteger('created_at');
            $table->unique(['actor_id', 'scope', 'key_hash']);
        });
        $this->seed([AdminSeeder::class, IncentiveSeeder::class]);
        $token = AdminUser::where('email', 'admin@flickme.com')->firstOrFail()->issueToken();
        $this->headers = ['Authorization' => 'Bearer '.$token['token'], 'Idempotency-Key' => 'publish-1'];
    }

    public function test_defaults_match_the_three_prototypes_and_seeding_is_repeatable(): void
    {
        $this->getJson('/admin/check-in-rewards', $this->headers)->assertOk()->assertJsonPath('code', 200)
            ->assertJsonCount(7, 'data.draft')->assertJsonPath('data.draft.6.reward', 100);
        $this->getJson('/admin/tasks', $this->headers)->assertOk()->assertJsonCount(13, 'data.draft');
        $this->getJson('/admin/tasks?group='.urlencode('观看任务'), $this->headers)
            ->assertOk()->assertJsonCount(5, 'data.draft')->assertJsonPath('data.draft.0.sec', 180);
        $this->getJson('/admin/tasks?q=WHATSAPP', $this->headers)->assertOk()->assertJsonCount(1, 'data.draft');
        $this->getJson('/admin/watch-rewards', $this->headers)->assertOk()->assertJsonPath('data.draft', ['sec' => 5, 'coins' => 2, 'cap' => 600]);
        $this->seed(IncentiveSeeder::class);
        $this->assertDatabaseCount('incentive_configs', 3);
        $this->assertSame(4, DB::table('admin_permissions')->where('module', 'tasks')->count());
    }

    public function test_checkin_saves_drafts_and_publishes_idempotently(): void
    {
        $draft = $this->getJson('/admin/check-in-rewards', $this->headers)->json('data.draft');
        $draft[0]['reward'] = 88;
        $this->putJson('/admin/check-in-rewards', ['revision' => 1, 'draft' => $draft], $this->headers)
            ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.published', null);
        $response = $this->postJson('/admin/check-in-rewards/publish', ['revision' => 2], $this->headers)
            ->assertOk()->assertJsonPath('data.published.0.reward', 88)->assertJsonPath('data.published_revision', 2)->json('data');
        $this->postJson('/admin/check-in-rewards/publish', ['revision' => 2], $this->headers)->assertOk()->assertJsonPath('data', $response);
        $this->postJson('/admin/check-in-rewards/publish', ['revision' => 2], array_replace($this->headers, ['Idempotency-Key' => 'publish-2']))
            ->assertOk()->assertJsonPath('data', $response);
        $this->postJson('/admin/check-in-rewards/publish', ['revision' => 1], $this->headers)->assertConflict();
        $this->assertDatabaseCount('admin_audit_logs', 2);
        $draft[0]['reward'] = 99;
        $this->putJson('/admin/check-in-rewards', ['revision' => 2, 'draft' => $draft], $this->headers)
            ->assertOk()->assertJsonPath('data.published.0.reward', 88);
    }

    public function test_checkin_preserves_seven_fixed_days_and_rejects_stale_updates(): void
    {
        $draft = $this->getJson('/admin/check-in-rewards', $this->headers)->json('data.draft');
        $this->putJson('/admin/check-in-rewards', ['revision' => 2, 'draft' => $draft], $this->headers)
            ->assertConflict()->assertJsonPath('code', 1003);
        $draft[0]['day'] = 'Day 8';
        $this->putJson('/admin/check-in-rewards', ['revision' => 1, 'draft' => $draft], $this->headers)->assertUnprocessable();
        array_pop($draft);
        $this->putJson('/admin/check-in-rewards', ['revision' => 1, 'draft' => $draft], $this->headers)->assertUnprocessable();
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_tasks_support_create_edit_delete_and_separate_publication(): void
    {
        $task = ['group' => '观看任务', 'sec' => 240, 'reward' => 12, 'reset' => '每日', 'en' => true];
        $draft = $this->postJson('/admin/tasks', ['revision' => 1, 'task' => $task], $this->headers)
            ->assertOk()->assertJsonCount(14, 'data.draft')->assertJsonPath('data.draft.13.name', 'Watch dramas for 4 min')->json('data.draft');
        $id = $draft[13]['id'];
        $this->patchJson('/admin/tasks/'.$id, ['revision' => 2, 'task' => ['group' => '日常任务', 'name' => 'Share', 'en' => false]], $this->headers)
            ->assertOk()->assertJsonPath('data.draft.13.en', false)->assertJsonMissingPath('data.draft.13.sec');
        $this->postJson('/admin/tasks/publish', ['revision' => 3], $this->headers)->assertOk()->assertJsonCount(14, 'data.published');
        $this->deleteJson('/admin/tasks/'.$id, ['revision' => 3], $this->headers)
            ->assertOk()->assertJsonCount(13, 'data.draft')->assertJsonCount(14, 'data.published');
        $this->deleteJson('/admin/tasks/'.$id, ['revision' => 4], $this->headers)->assertNotFound();
        $this->getJson('/admin/tasks', $this->headers)->assertOk()->assertJsonPath('data.revision', 4);
    }

    public function test_watch_save_is_effective_immediately_and_validates_limits(): void
    {
        $this->putJson('/admin/watch-rewards', ['revision' => 1, 'draft' => ['sec' => 10, 'coins' => 3, 'cap' => 900]], $this->headers)
            ->assertOk()->assertJsonPath('data.published.sec', 10)->assertJsonPath('data.published_revision', 2);
        foreach ([['sec' => 0, 'coins' => 2, 'cap' => 600], ['sec' => 5, 'coins' => 10, 'cap' => 2], ['sec' => 5, 'coins' => 1.5, 'cap' => 600]] as $draft) {
            $this->putJson('/admin/watch-rewards', ['revision' => 2, 'draft' => $draft], $this->headers)
                ->assertUnprocessable()->assertJsonPath('code', 422)->assertJsonStructure(['data' => ['errors']]);
        }
        $this->getJson('/admin/watch-rewards', $this->headers)->assertOk()->assertJsonPath('data.revision', 2);
    }

    public function test_authentication_read_only_permissions_and_publish_keys_are_enforced(): void
    {
        $this->getJson('/admin/tasks')->assertUnauthorized();
        $operator = AdminUser::where('email', 'admin@flickme.com')->firstOrFail();
        $operator->roles()->detach();
        $permission = DB::table('admin_permissions')->where('code', 'tasks.view')->value('id');
        $operator->permissions()->attach($permission);
        $token = $operator->issueToken();
        $headers = ['Authorization' => 'Bearer '.$token['token']];
        $this->getJson('/admin/tasks', $headers)->assertOk();
        $this->postJson('/admin/tasks', ['revision' => 1], $headers)->assertForbidden();
        $this->postJson('/admin/tasks/publish', ['revision' => 1], $headers)->assertForbidden();
    }

    public function test_tasks_require_valid_watch_seconds_and_publish_key(): void
    {
        $this->postJson('/admin/tasks', ['revision' => 1, 'task' => ['group' => '观看任务', 'reward' => 5, 'reset' => '每日', 'en' => true]], $this->headers)
            ->assertUnprocessable();
        $headers = $this->headers;
        unset($headers['Idempotency-Key']);
        $this->postJson('/admin/tasks/publish', ['revision' => 1], $headers)->assertUnprocessable();
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_failed_audit_rolls_back_configuration_and_reseeding_preserves_edits(): void
    {
        $draft = ['sec' => 9, 'coins' => 3, 'cap' => 600];
        $this->putJson('/admin/watch-rewards', ['revision' => 1, 'draft' => $draft], $this->headers)->assertOk();
        $this->seed(IncentiveSeeder::class);
        $this->getJson('/admin/watch-rewards', $this->headers)->assertOk()->assertJsonPath('data.draft.sec', 9)->assertJsonPath('data.revision', 2);
        Schema::drop('admin_audit_logs');
        $draft['sec'] = 15;
        $this->putJson('/admin/watch-rewards', ['revision' => 2, 'draft' => $draft], $this->headers)->assertStatus(500)->assertJsonPath('code', 5000);
        $this->getJson('/admin/watch-rewards', $this->headers)->assertOk()->assertJsonPath('data.draft.sec', 9)->assertJsonPath('data.published.sec', 9)->assertJsonPath('data.revision', 2);
    }
}
