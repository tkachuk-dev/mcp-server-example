<?php

declare(strict_types=1);

return [

    /*
    | sha256 hashes of service tokens accepted on /internal/ai (JSON array, several entries allow rotation).
    */
    'token_hashes' => json_decode((string) env('MCP_SERVICE_TOKEN_HASHES', '[]'), true) ?: [],

    'rate_limit_per_minute' => (int) env('INTERNAL_AI_RATE_LIMIT_PER_MINUTE', 120),

    'cache_ttl_seconds' => (int) env('INTERNAL_AI_CACHE_TTL', 600),

];
