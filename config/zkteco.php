<?php

return [
    'default' => 'main',
    'devices' => [
        'main' => [
            'host' => env('ZKTECO_HOST', '192.168.1.201'),
            'port' => (int) env('ZKTECO_PORT', 4370),
            'transport' => env('ZKTECO_TRANSPORT', 'tcp'),
            'comm_key' => (int) env('ZKTECO_COMM_KEY', 0),
            'timeout' => (float) env('ZKTECO_TIMEOUT', 5),
            'timezone' => env('ZKTECO_TIMEZONE', 'Africa/Addis_Ababa'),
        ],
    ],
    // The ERP synchronization service owns persistence and processing.
    'storage' => ['enabled' => false],
    'push' => ['enabled' => false],
];
