<?php

return [
    'microservices' => [
        'baseUrl' => 'https://gtw-int.rankingcoach.com',
    ],
    'monolith' => [
        'baseUrl' => 'https://api.rankingcoach.com/api',
        'db_caching' => [
            'active' => true,
            'db_table' => 'cloud_api_cache',
            'general_operations_caching_lifetime' => 3600,
            'webinar_cache_lifetime' => 604800
        ],
        'db_logging' => [
            'active' => false,
            'db_table' => '',
            'log_request_from_caches_to_kibana' => false,
            'max_logged_request_length' => 7000,
            'log_response_to_kibana' => true
        ],
        'store' => [
            'engine' => 'Application_Store_Kohana_Orm'
        ],
    ],
    'requestSettings' => [
        'headers' => [
            'Connection' => 'Keep-Alive',
            'Keep-Alive' => '600',
            'Accept-Charset' => 'ISO-8859-1,UTF-8;q=0.7,*;q=0.7',
            'Accept-Language' => 'de,en;q=0.7,en-us;q=0.3',
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
            'x-api-key' => 'apps-symfony'
        ],
        'http_errors' => false,
        'timeout' => 600
    ]
];