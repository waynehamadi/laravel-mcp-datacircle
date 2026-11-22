<?php

declare(strict_types=1);

namespace Datacircle\LaravelMcp;

use Datacircle\LaravelMcp\Tools\BestPlans;
use Datacircle\LaravelMcp\Tools\ListApis;
use Datacircle\LaravelMcp\Tools\Provider;
use Datacircle\LaravelMcp\Tools\SearchPlans;

/**
 * Datacircle's four Laravel MCP tools: find the best GTM APIs. https://datacircle.dev
 */
final class Datacircle
{
    /**
     * datacircle_list_apis, datacircle_best_plans, datacircle_search_plans and datacircle_provider, for a server's
     * $tools: protected array $tools = [...Datacircle::TOOLS];
     */
    public const TOOLS = [
        ListApis::class,
        BestPlans::class,
        SearchPlans::class,
        Provider::class,
    ];
}
