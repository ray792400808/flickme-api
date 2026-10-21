<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\OperationsController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\CheckAdminPermission;
use Illuminate\Http\Request;
use Tests\TestCase;

class OperationsRoutesTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_all_operations_routes_use_rbac_authentication_and_permissions(): void
    {
        foreach (['chips', 'hot', 'launch', 'words', 'filters', 'recs'] as $resource) {
            foreach ([['GET', '', 'show', 'view'], ['PUT', '', 'update', 'edit'], ['POST', '/publish', 'publish', 'publish']] as [$method, $suffix, $action, $permission]) {
                $route = app('router')->getRoutes()->match(Request::create('/admin/ops/'.$resource.$suffix, $method));
                $this->assertSame(OperationsController::class.'@'.$action, $route->getActionName());
                $this->assertContains(AuthenticateAdmin::class, $route->gatherMiddleware());
                $this->assertContains(CheckAdminPermission::class.':ops.'.$permission, $route->gatherMiddleware());
            }
        }
    }

    public function test_operations_route_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/admin/ops/chips?channel=short')->assertUnauthorized();
    }
}
