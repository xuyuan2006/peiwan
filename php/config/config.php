<?php
/**
 * 实际生效的配置文件。install.php 会覆盖本文件。
 */
return [
    'app' => [
        'name'     => '陪玩点单平台',
        'url'      => '',
        'debug'    => true,
        'timezone' => 'Asia/Shanghai',
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'peiwang',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'pay' => [
        'channel' => 'manual',
        'epay'    => [
            'api_url' => '',
            'pid'     => '',
            'key'     => '',
        ],
    ],
];
