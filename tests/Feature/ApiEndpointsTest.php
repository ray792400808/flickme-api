<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    public function test_app_locales_are_available(): void
    {
        $this->getJson('/v1/app/locales')
            ->assertOk()
            ->assertJsonPath('data.default', 'en')
            ->assertJsonPath('data.supported.1', 'zh-Hans');
    }

    public function test_server_time_is_available(): void
    {
        $this->getJson('/v1/server/time')
            ->assertOk()
            ->assertJsonStructure(['data' => ['iso8601', 'timestampMs', 'timezone']]);
    }

    public function test_admin_system_info_requires_authentication(): void
    {
        $this->getJson('/admin/v1/system/info')
            ->assertUnauthorized();
    }
}
