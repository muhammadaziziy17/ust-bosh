<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sandbox'da tizim env o'zgaruvchilari (.env'dan eskirgan qiymatlar)
     * testlarni buzmasligi uchun app boot bo'lishidan OLDIN env'ni qattiq belgilaymiz.
     * (RateLimiter kabi xizmatlar boot paytida config'ni o'qiydi.)
     */
    protected function setUp(): void
    {
        $forcedEnv = [
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:2fl+Ktvkfl+FuzU603C1Y6c+GZ7LQICUwBmMfpXHqOc=',
            'APP_DEBUG' => 'false',
            'APP_LOCALE' => 'uz',
            'APP_FALLBACK_LOCALE' => 'ru',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ];

        foreach ($forcedEnv as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }

        parent::setUp();

        // PreventRequestForgery::runningUnitTests() container'dagi 'env' binding'ini o'qiydi
        app()->instance('env', 'testing');

        // Testlar toza CI'da ham ishlashi uchun admin credential'larni qattiq belgilaymiz.
        // Har bir test o'zi xohlagan qiymatga override qilishi mumkin.
        config([
            'admin.email' => 'admin_test@ustbosh.uz',
            'admin.password' => 'secret123',
        ]);
    }
}
