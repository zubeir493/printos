<?php

$s3Key = trim((string) env('AWS_ACCESS_KEY_ID', env('B2_KEY_ID', env('B2_APPLICATION_KEY_ID', ''))));
$s3Secret = trim((string) env('AWS_SECRET_ACCESS_KEY', env('B2_APPLICATION_KEY', '')));
$privateDisk = env('PRIVATE_FILESYSTEM_DISK', env('FILESYSTEM_CLOUD', 's3'));

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'private_disk' => $privateDisk,

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => true,
            'report' => true,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => $s3Key,
            'secret' => $s3Secret,
            'region' => env('AWS_DEFAULT_REGION', env('B2_REGION', 'us-west-002')),
            'bucket' => env('AWS_BUCKET', env('B2_BUCKET')),
            'url' => env('AWS_URL', env('B2_URL')),
            'endpoint' => env('AWS_ENDPOINT', env('B2_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
            'visibility' => 'private',
            'http' => [
                'verify' => filter_var(env('AWS_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
            ],
            'throw' => true,
            'report' => true,
        ],

        'b2' => [
            'driver' => 's3',
            'key' => env('B2_KEY_ID'),
            'secret' => env('B2_APPLICATION_KEY'),
            'region' => env('B2_REGION', 'us-west-002'),
            'bucket' => env('B2_BUCKET'),
            'endpoint' => env('B2_ENDPOINT', 'https://s3.us-west-002.backblazeb2.com'),
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'http' => [
                'verify' => filter_var(env('B2_VERIFY_SSL', env('AWS_VERIFY_SSL', true)), FILTER_VALIDATE_BOOL),
            ],
            'throw' => true,
            'report' => true,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
