<?php
/**
 * 配置示例文件
 * 复制为 config.php 后修改，或直接访问 install.php 由安装向导生成。
 */
return [
    'app' => [
        // 站点名称（后台设置里也能改，这里只是兜底）
        'name'     => '陪玩点单平台',
        // 站点根地址，例如 https://peiwang.com ；留空则自动识别当前域名
        'url'      => '',
        // 调试模式：上线后请改为 false
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
        // manual = 人工审核充值（默认，无需营业执照）
        // epay   = 易支付网关（需自行填入 pid / key）
        'channel' => 'manual',
        'epay'    => [
            'api_url' => '',
            'pid'     => '',
            'key'     => '',
        ],
    ],
];
