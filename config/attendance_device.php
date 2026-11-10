<?php

return [
    'enabled' => (bool) env('ATTENDANCE_DEVICE_ENABLED', false),
    'processing_enabled' => (bool) env('ATTENDANCE_DEVICE_PROCESSING_ENABLED', false),
    'id' => env('ATTENDANCE_DEVICE_ID', 'office-mb360'),
    'host' => env('ZKTECO_HOST', '192.168.1.201'),
    'port' => (int) env('ZKTECO_PORT', 4370),
    'transport' => env('ZKTECO_TRANSPORT', 'tcp'),
    'comm_key' => (int) env('ZKTECO_COMM_KEY', 0),
    'timeout' => (float) env('ZKTECO_TIMEOUT', 5),
    'timezone' => env('ZKTECO_TIMEZONE', 'Africa/Addis_Ababa'),
    'default_shift_id' => env('ATTENDANCE_DEVICE_DEFAULT_SHIFT_ID'),
    // Use sequential only after verifying that this device reports a constant direction.
    'pairing' => env('ATTENDANCE_DEVICE_PAIRING', 'device'),
    'sync_from' => env('ATTENDANCE_DEVICE_SYNC_FROM'),
    'queue_connection' => env('ATTENDANCE_DEVICE_QUEUE_CONNECTION', 'database'),
    'queue' => env('ATTENDANCE_DEVICE_QUEUE', 'attendance'),
];
