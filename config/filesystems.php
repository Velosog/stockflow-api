<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The API stores no user uploads, so only the private local disk (logs,
    | framework files) is configured. Public/S3 disks were removed on purpose
    | to keep the attack surface and the configuration minimal.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [],

];
