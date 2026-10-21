<?php

namespace Tests\Feature;

use App\Common\ResponseDef;
use App\Common\ResponseUtil;
use App\Exceptions\Handler;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\LogAdminOperation;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class BusinessResponseContractTest extends TestCase
{
    public function test_application_uses_shared_exception_handler(): void
    {
        $this->assertInstanceOf(Handler::class, app(ExceptionHandler::class));
    }

    public function test_shared_exception_handler_preserves_retry_headers(): void
    {
        Route::get('/admin/__contract/rate-limit', function (): void {
            throw new TooManyRequestsHttpException(30);
        });
        $this->getJson('/admin/__contract/rate-limit')->assertStatus(429)
            ->assertHeader('Retry-After', '30')->assertJsonPath('code', 429)
            ->assertJsonPath('data', null)->assertJsonStructure(['trace_id']);
    }

    /**
     * A basic feature test example.
     */
    public function test_response_factory_preserves_unicode_and_http_status(): void
    {
        $response = ResponseUtil::success(['title' => '首页分类']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['code' => 200, 'message' => 'ok', 'data' => ['title' => '首页分类'], 'trace_id' => null], json_decode($response->getContent(), true));
        $this->assertSame(409, ResponseUtil::error(ResponseDef::CODE_REVISION_STALE, ResponseDef::MSG_REVISION_STALE, 409)->getStatusCode());
    }

    public function test_missing_token_has_standard_envelope(): void
    {
        $this->getJson('/admin/ops/chips?channel=short')->assertUnauthorized()
            ->assertJsonPath('code', 401)->assertJsonPath('data', null)
            ->assertJsonPath('message', ResponseDef::MSG_TOKEN_MISSING);
    }

    public function test_operations_validation_errors_are_nested_in_data(): void
    {
        $this->withoutMiddleware([AuthenticateAdmin::class, CheckAdminPermission::class, LogAdminOperation::class]);
        $this->getJson('/admin/ops/chips')->assertUnprocessable()
            ->assertJsonPath('code', 422)->assertJsonStructure(['code', 'message', 'data' => ['errors' => ['channel']]])
            ->assertJsonMissingPath('errors');
        $this->putJson('/admin/ops/words', ['data' => ['revision' => 1, 'draft' => []]])
            ->assertUnprocessable()->assertJsonStructure(['data' => ['errors' => ['revision', 'draft']]]);
    }

    public function test_read_success_and_conflict_follow_response_contract(): void
    {
        $this->withoutMiddleware([AuthenticateAdmin::class, CheckAdminPermission::class, LogAdminOperation::class]);
        Schema::create('operation_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('resource');
            $table->string('channel')->default('');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('published_revision')->nullable();
            $table->bigInteger('published_at')->nullable();
            $table->json('draft');
            $table->json('published')->nullable();
            $table->unique(['resource', 'channel']);
        });

        $this->getJson('/admin/ops/chips?channel=short')->assertOk()
            ->assertJsonPath('code', 200)->assertJsonPath('message', 'ok')
            ->assertJsonPath('data.revision', 1)->assertJsonPath('data.draft.0.names.zh-Hans', '推荐');
        $this->putJson('/admin/ops/chips?channel=short', ['revision' => 2, 'draft' => []])
            ->assertConflict()->assertJsonPath('code', ResponseDef::CODE_REVISION_STALE)->assertJsonPath('data', null);
        $this->postJson('/admin/ops/chips/publish?channel=short', ['revision' => 1])
            ->assertUnprocessable()->assertJsonPath('code', 422)->assertJsonPath('data', null);
    }

    public function test_missing_resource_has_standard_envelope(): void
    {
        $this->withoutMiddleware([AuthenticateAdmin::class, CheckAdminPermission::class, LogAdminOperation::class]);
        $this->getJson('/admin/ops/unknown')->assertNotFound()
            ->assertJsonPath('code', 404)->assertJsonPath('data', null);
    }

    public function test_server_errors_do_not_expose_internal_details(): void
    {
        $this->withoutMiddleware([AuthenticateAdmin::class, CheckAdminPermission::class, LogAdminOperation::class]);
        $this->mock(OperationsController::class, function (MockInterface $mock): void {
            $mock->shouldReceive('show')->once()->andThrow(new \RuntimeException('SQL password=secret'));
        });
        $this->getJson('/admin/ops/words')->assertStatus(500)
            ->assertJsonPath('code', ResponseDef::CODE_SYSTEM_ERROR)
            ->assertJsonPath('message', ResponseDef::MSG_SERVER_ERROR)
            ->assertJsonPath('data', null)->assertJsonMissingPath('exception');
    }
}
