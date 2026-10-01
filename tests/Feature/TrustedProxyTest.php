<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_forwarded_host_is_used_when_local_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/', [
                'X-Forwarded-Host' => 'demo.ngrok-free.app',
                'X-Forwarded-Proto' => 'https',
            ]);

        $response->assertOk();
        $response->assertSee('https://demo.ngrok-free.app/css/theme.css', false);
        $response->assertSee('https://demo.ngrok-free.app/js/app.js', false);
    }

    public function test_forwarded_host_is_ignored_when_proxy_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/', [
                'X-Forwarded-Host' => 'demo.ngrok-free.app',
                'X-Forwarded-Proto' => 'https',
            ]);

        $response->assertOk();
        $response->assertDontSee('demo.ngrok-free.app', false);
        $response->assertSee('/css/theme.css', false);
    }
}
