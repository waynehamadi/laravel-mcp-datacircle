<?php

declare(strict_types=1);

namespace Datacircle\LaravelMcp;

use Laravel\Mcp\Server;

/**
 * An MCP server with Datacircle's four tools and nothing else: Mcp::local('datacircle', DatacircleServer::class) or
 * Mcp::web('/mcp/datacircle', DatacircleServer::class) in routes/ai.php.
 */
class DatacircleServer extends Server
{
    protected string $name = 'Datacircle';

    protected string $version = Client::VERSION;

    protected string $instructions = 'Find the best GTM APIs. Datacircle benchmarks all the GTM APIs and puts the results on https://datacircle.dev. datacircle_list_apis gives each API\'s slug, datacircle_best_plans who is best for one, datacircle_search_plans every plan by provider, plan name or price, and datacircle_provider one provider\'s ranks.';

    protected array $tools = Datacircle::TOOLS;
}
