<?php

declare(strict_types=1);

namespace Datacircle\LaravelMcp\Tests;

use Datacircle\LaravelMcp\Client;
use Datacircle\LaravelMcp\Datacircle;
use Datacircle\LaravelMcp\DatacircleServer;
use Datacircle\LaravelMcp\Tools\BestPlans;
use Datacircle\LaravelMcp\Tools\ListApis;
use Datacircle\LaravelMcp\Tools\Provider;
use Datacircle\LaravelMcp\Tools\SearchPlans;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

class ToolsTest extends TestCase
{
    public function test_the_server_lists_four_read_only_tools_with_their_schemas(): void
    {
        DatacircleServer::tools()->assertRegistered(Datacircle::TOOLS);

        $tools = array_map(fn (string $tool): array => $this->app->make($tool)->toArray(), Datacircle::TOOLS);

        $this->assertSame(
            ['datacircle_list_apis', 'datacircle_best_plans', 'datacircle_search_plans', 'datacircle_provider'],
            array_column($tools, 'name'),
        );
        foreach ($tools as $tool) {
            $this->assertSame(['readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => true],
                array_diff_key($tool['annotations'], ['title' => true]));
            $this->assertStringContainsString('https://datacircle.dev', $tool['description']);
        }
        $this->assertSame('Who is best for an API', $tools[1]['title']);
        $this->assertSame(['slug'], $tools[1]['inputSchema']['required']);
        $this->assertEquals(['type' => 'integer', 'description' => 'How many plans to give, 1 to 50. 10 when left out', 'minimum' => 1, 'maximum' => 50],
            $tools[1]['inputSchema']['properties']['limit']);
        $this->assertSame(['score', 'provider', 'price'], $tools[2]['inputSchema']['properties']['sort']['enum']);
        $this->assertArrayNotHasKey('required', $tools[2]['inputSchema']);
    }

    public function test_list_apis_puts_the_most_words_matched_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/apis' => Http::response([
            ['api' => 'Employee phone', 'slug' => 'phone-finder', 'plans' => 3, 'providers' => 2, 'terms' => 'dropped'],
            ['api' => 'Employee email', 'slug' => 'email-finder', 'plans' => 5, 'providers' => 4],
            ['api' => 'Email validation', 'slug' => 'email-verification', 'plans' => 2, 'providers' => 2],
        ])]);

        $response = DatacircleServer::tool(ListApis::class, ['query' => 'email finder']);
        $result = self::structured($response->assertName('datacircle_list_apis')->assertSee('"slug":"email-finder"'));

        $this->assertSame(3, $result['total']);
        $this->assertSame(['email-finder', 'phone-finder', 'email-verification'], array_column($result['apis'], 'slug'));
        $this->assertArrayNotHasKey('terms', $result['apis'][1]);
    }

    public function test_best_plans_sends_the_slug_and_limit_and_briefs_each_plan(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response([
            'api' => 'Employee email', 'slug' => 'email-finder', 'total' => 1,
            'plans' => [['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5, 'aggregators' => ['x']]],
        ])]);

        $result = self::structured(DatacircleServer::tool(BestPlans::class, ['slug' => 'Email Finder', 'limit' => '70']));

        $this->assertSame(['rank' => 1, 'provider' => 'A', 'plan' => 'Pro', 'score' => 80.5, 'perThousand' => 2.5], $result['plans'][0]);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.datacircle.dev/v1/apis/email-finder/best?limit=50'
            && str_starts_with($request->header('User-Agent')[0], 'laravel-mcp-datacircle/'));
    }

    public function test_a_missing_slug_asks_for_one_without_a_call(): void
    {
        Http::fake();

        DatacircleServer::tool(BestPlans::class)->assertHasErrors(['Give an API\'s slug']);
        DatacircleServer::tool(Provider::class, ['slug' => ['x']])->assertHasErrors(['Give a provider\'s slug']);
        Http::assertNothingSent();
    }

    public function test_an_answer_of_4xx_is_an_error_result_in_the_apis_words(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response(['error' => 'no API has the slug nope'], 404)]);

        DatacircleServer::tool(BestPlans::class, ['slug' => 'nope'])
            ->assertHasErrors(['no API has the slug nope. datacircle_list_apis gives every API\'s slug']);
    }

    public function test_a_failure_of_the_server_is_thrown_and_laravel_mcp_reports_it(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::response('', 503)]);
        config(['app.debug' => true]);

        DatacircleServer::tool(Provider::class, ['slug' => 'bright-data'])->assertHasErrors(['datacircle: 503']);

        $this->expectExceptionMessage('datacircle: 503');
        (new Provider)->handle(new Request(['slug' => 'bright-data']));
    }

    public function test_search_plans_takes_a_provider_sent_as_the_api_as_its_name(): void
    {
        Http::fake(function (HttpRequest $request) {
            return isset($request->data()['api'])
                ? Http::response(['error' => 'api must be one of: Employee email'], 400)
                : Http::response(['total' => 1, 'offset' => 0, 'plans' => [['provider' => 'Bright Data', 'plan' => 'Free']]]);
        });

        $result = self::structured(DatacircleServer::tool(SearchPlans::class, ['api' => 'Bright Data', 'sort' => 'cheap']));

        $this->assertSame(1, $result['total']);
        $this->assertStringContainsString('No API is named "Bright Data"', $result['note']);

        Http::assertSent(fn (HttpRequest $request): bool => $request->data() === ['query' => 'Bright Data']);
    }

    public function test_search_plans_retries_a_slug_with_its_words(): void
    {
        Http::fake(fn (HttpRequest $request) => Http::response(['total' => $request->data()['query'] === 'bright data' ? 2 : 0, 'offset' => 0, 'plans' => []]));

        $result = self::structured(DatacircleServer::tool(SearchPlans::class, ['query' => 'bright-data']));

        $this->assertSame(2, $result['total']);
        $this->assertStringContainsString('"bright data"', $result['note']);
    }

    public function test_provider_gives_its_best_rank_first(): void
    {
        Http::fake(['api.datacircle.dev/v1/providers/bright-data' => Http::response([
            'provider' => 'Bright Data', 'slug' => 'bright-data',
            'apis' => [
                ['api' => 'A', 'slug' => 'a', 'rank' => null, 'plans' => [['plan' => 'x', 'rank' => null]]],
                ['api' => 'B', 'slug' => 'b', 'rank' => 7, 'plans' => [['plan' => 'y', 'rank' => 9], ['plan' => 'z', 'rank' => 7, 'score' => 50]]],
                ['api' => 'C', 'slug' => 'c', 'rank' => 2, 'plans' => [['plan' => 'w', 'rank' => 2]]],
            ],
        ])]);

        $result = self::structured(DatacircleServer::tool(Provider::class, ['slug' => 'Bright Data']));

        $this->assertSame(['c', 'b', 'a'], array_column($result['apis'], 'slug'));
        $this->assertSame(['plan' => 'z', 'score' => 50], $result['apis'][1]['best']);
        $this->assertSame([3, 2], [$result['total'], $result['ranked']]);
    }

    public function test_a_429_is_waited_for_once(): void
    {
        Http::fake(['api.datacircle.dev/*' => Http::sequence()
            ->push(['error' => 'slow down'], 429, ['Retry-After' => '1'])
            ->push(['provider' => 'X', 'slug' => 'x', 'apis' => []])]);

        $this->assertSame(0, self::structured(DatacircleServer::tool(Provider::class, ['slug' => 'x']))['total']);

        Http::assertSentCount(2);
    }

    public function test_the_containers_client_is_the_tools(): void
    {
        Http::fake(['127.0.0.1:9/v1/apis' => Http::response([])]);
        $this->app->instance(Client::class, new Client('http://127.0.0.1:9/v1/'));

        $this->assertSame(0, self::structured(DatacircleServer::tool(ListApis::class))['total']);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'http://127.0.0.1:9/v1/apis');
    }

    public function test_slugify(): void
    {
        $this->assertSame('bright-data', Client::slugify(' Bright Data '));
        $this->assertSame('societe-generale', Client::slugify('Société Générale'));
        $this->assertSame('', Client::slugify(null));
    }
}
