<?php

declare(strict_types=1);

namespace Datacircle\LaravelMcp\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [McpServiceProvider::class];
    }

    /**
     * A tool's structured content, after it was asserted ok: laravel/mcp 1.0's TestResponse has no path assertions.
     *
     * @return array<string, mixed>
     */
    protected static function structured(TestResponse $response): array
    {
        $content = [];
        $response->assertOk()->assertStructuredContent(function (AssertableJson $json) use (&$content): void {
            $content = $json->etc()->toArray();
        });

        return $content;
    }
}
