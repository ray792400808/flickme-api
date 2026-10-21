<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\LogAdminOperation;
use App\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SearchWordsContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([AuthenticateAdmin::class, CheckAdminPermission::class, LogAdminOperation::class]);
        $actor = new AdminUser(['name' => 'Test operator', 'email' => 'operator@example.com']);
        $actor->id = 1;
        app('auth')->resolveUsersUsing(fn (): AdminUser => $actor);
        Schema::create('operation_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('channel')->default('');
            $table->integer('revision')->default(1);
            $table->integer('published_revision')->nullable();
            $table->bigInteger('published_at')->nullable();
            $table->json('draft');
            $table->json('published')->nullable();
            $table->unique(['resource', 'channel']);
        });
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->integer('actor_id');
            $table->string('actor');
            $table->string('action');
            $table->string('subject');
            $table->json('details');
            $table->bigInteger('created_at');
        });
        Schema::create('admin_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->integer('actor_id');
            $table->string('scope');
            $table->string('key_hash');
            $table->string('body_hash');
            $table->json('result')->nullable();
            $table->bigInteger('created_at');
            $table->unique(['actor_id', 'scope', 'key_hash']);
        });
    }

    public function test_prototype_fields_are_saved_and_sorted_stably(): void
    {
        $draft = [$this->word('last', 7), $this->word('first', 3, 'draft'), $this->word('same-a', 5), $this->word('same-b', 5)];
        $this->putJson('/admin/ops/words', ['revision' => 1, 'draft' => $draft])->assertOk()
            ->assertJsonPath('data.revision', 2)->assertJsonPath('data.draft.0.id', 'first')
            ->assertJsonPath('data.draft.0.status', 'draft')->assertJsonPath('data.draft.1.id', 'same-a')
            ->assertJsonPath('data.draft.2.id', 'same-b')->assertJsonPath('data.draft.3.sort', 7);
        $this->getJson('/admin/ops/words')->assertOk()->assertJsonPath('data.draft.0.sort', 3);
    }

    public function test_publishing_excludes_new_drafts_and_preserves_existing_online_versions(): void
    {
        $this->putJson('/admin/ops/words', ['revision' => 1, 'draft' => [$this->word('existing')]])->assertOk();
        $this->publish(2, 'first');
        $edited = $this->word('existing', 9, 'draft');
        $edited['names']['en'] = 'Unpublished edit';
        $this->putJson('/admin/ops/words', ['revision' => 2, 'draft' => [$edited, $this->word('new-draft', 2, 'draft'), $this->word('new-live', 3)]])->assertOk();
        $this->publish(3, 'second');
        $published = $this->published();
        $this->assertSame(['existing', 'new-live'], array_column($published, 'id'));
        $this->assertSame('existing', $published[0]['names']['en']);
        $this->assertSame(1, $published[0]['sort']);
        $edited['status'] = 'published';
        $edited['enabled'] = false;
        $this->putJson('/admin/ops/words', ['revision' => 3, 'draft' => [$edited]])->assertOk();
        $this->publish(4, 'third');
        $this->assertCount(1, $this->published());
        $this->assertSame('Unpublished edit', $this->published()[0]['names']['en']);
        $this->assertFalse($this->published()[0]['enabled']);
        $details = json_decode(DB::table('admin_audit_logs')->where('action', 'ops.publish')->latest('id')->value('details'), true);
        $this->assertSame($this->published(), $details['after']);
    }

    public function test_legacy_payloads_without_sort_or_status_still_publish(): void
    {
        $word = $this->word('legacy');
        unset($word['sort'], $word['status']);
        $this->putJson('/admin/ops/words', ['revision' => 1, 'draft' => [$word]])->assertOk()
            ->assertJsonPath('data.draft.0.sort', 1)->assertJsonPath('data.draft.0.status', 'published');
        $this->publish(2, 'legacy');
        $this->assertSame('legacy', $this->published()[0]['id']);
        $this->postJson('/admin/ops/words/publish', ['revision' => 2], ['Idempotency-Key' => 'legacy'])->assertOk();
        $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'ops.publish')->count());
    }

    public function test_invalid_metadata_and_stale_revisions_do_not_change_config(): void
    {
        foreach (['sort' => -1, 'status' => 'invalid', 'unexpected' => true] as $key => $value) {
            $word = array_replace($this->word('invalid'), [$key => $value]);
            $this->putJson('/admin/ops/words', ['revision' => 1, 'draft' => [$word]])->assertUnprocessable()->assertJsonPath('code', 422);
        }
        $this->putJson('/admin/ops/words', ['revision' => 2, 'draft' => [$this->word('stale')]])
            ->assertConflict()->assertJsonPath('code', 1003);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    /** @return array<string, mixed> */
    private function word(string $id, int $sort = 1, string $status = 'published'): array
    {
        return ['id' => $id, 'sort' => $sort, 'status' => $status,
            'names' => ['en' => $id, 'es' => '', 'id' => '', 'pt' => '', 'th' => '', 'zh-Hans' => '', 'zh-Hant' => ''],
            'enabled' => true, 'starts_at' => null, 'ends_at' => null];
    }

    private function publish(int $revision, string $key): void
    {
        $this->postJson('/admin/ops/words/publish', ['revision' => $revision], ['Idempotency-Key' => $key])->assertOk();
    }

    /** @return array<int, array<string, mixed>> */
    private function published(): array
    {
        return json_decode(DB::table('operation_configs')->where('resource', 'words')->value('published'), true);
    }
}
