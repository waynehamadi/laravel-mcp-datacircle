# Datacircle tools for Laravel MCP

Find the best GTM APIs. We benchmark all the GTM APIs and we put the results on our website.

https://datacircle.dev

This package gives a [Laravel MCP](https://github.com/laravel/mcp) server four tools that call Datacircle's public API, https://api.datacircle.dev/v1. You need no key and no account.

| Tool | What the tool gives | Call |
| --- | --- | --- |
| `datacircle_list_apis` | Every GTM API on Datacircle, with the API's slug, number of plans, number of providers and best plan | `GET /apis` |
| `datacircle_best_plans` | One API's plans, sorted by score out of 100, best first. Each plan comes with a rank and a price per 1,000 calls | `GET /apis/{slug}/best` |
| `datacircle_search_plans` | Plans that match a provider name or plan name. Search all APIs or one API. Sort the plans by score, provider or price | `GET /plans` |
| `datacircle_provider` | The APIs a provider sells, and the provider's rank on each API | `GET /providers/{slug}` |

## Install

```bash
composer require datacircle/laravel-mcp-tools
```

The package needs PHP 8.2 or later, Laravel 12 or 13 and `laravel/mcp` 1.0 or later.

## Start the Datacircle server

`DatacircleServer` is an MCP server with four tools. Register the server in `routes/ai.php`:

```php
use Datacircle\LaravelMcp\DatacircleServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('datacircle', DatacircleServer::class);
Mcp::web('/mcp/datacircle', DatacircleServer::class);
```

An MCP client starts the local server with `php artisan mcp:start datacircle`. The web server answers at `/mcp/datacircle`.

## Add the tools to your own server

`Datacircle::TOOLS` lists the four tool classes. Put them in your server's `$tools`:

```php
namespace App\Mcp\Servers;

use Datacircle\LaravelMcp\Datacircle;
use Laravel\Mcp\Server;

class GtmServer extends Server
{
    protected array $tools = [
        ...Datacircle::TOOLS,
    ];
}
```

To add some of the tools, list each one yourself:

```php
use Datacircle\LaravelMcp\Tools\BestPlans;
use Datacircle\LaravelMcp\Tools\Provider;

protected array $tools = [
    BestPlans::class,
    Provider::class,
];
```

To let clients search the tools, put them under `ToolSearch`:

```php
use Datacircle\LaravelMcp\Datacircle;
use Laravel\Mcp\Server\Tools\ToolSearch;

protected array $tools = [
    ToolSearch::class => Datacircle::TOOLS,
];
```

## Test a tool

```php
use Datacircle\LaravelMcp\DatacircleServer;
use Datacircle\LaravelMcp\Tools\BestPlans;

DatacircleServer::tool(BestPlans::class, ['slug' => 'email-finder', 'limit' => 3])
    ->assertOk()
    ->assertSee('email-finder');
```

## Tool results

Each tool returns a JSON object, as text and as structured content. `datacircle_best_plans` and `datacircle_search_plans` return plans. Each plan has a rank, provider, plan name, score, price per 1,000 (`perThousand`, in dollars) and a page on datacircle.dev. The page on datacircle.dev lists every field.

An API error, like an unknown slug, gives an error result with the API's own message. The model can then call again. On a network failure, the tool throws an exception. Laravel MCP reports the exception and returns an error result.

The tools are read-only and idempotent. They call through Laravel's HTTP client. In your tests, `Http::fake()` fakes the tool calls.

`Client` sets the API address and the timeout in seconds. The timeout is 30 seconds by default. To change the timeout, bind a client in a service provider:

```php
use Datacircle\LaravelMcp\Client;

$this->app->bind(Client::class, fn () => new Client(timeout: 10));
```

## Network

Each tool call makes one HTTPS GET request to https://api.datacircle.dev/v1 and nothing else. The request holds only the tool's own arguments: no key and nothing from your app or your app's users. The API takes up to 60 calls a minute from one IP address. The tool waits once, for a minute at most, when the API asks the tool to wait. The package stores nothing.

## Links

- Datacircle: https://datacircle.dev
- The API: https://datacircle.dev/api
- The API's spec: https://api.datacircle.dev/v1/openapi.json
- What each key means: https://datacircle.dev/fields

MIT license.
