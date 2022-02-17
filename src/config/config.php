<?php

return [
    
    // HTTP服务器配置
    'http' => [
        // 监听IP
        'host'       => '0.0.0.0',
        
        // 监听端口
        'port'       => 2346,
        
        // 是否启用HTTPS
        'ssl'        => false,
        
        // 是否进入守护模式
        'daemonize'  => false,
        
        // 启动进程数量
        'worker_num' => 1,
        
        // 是否启用热更新
        'hot_update' => [
            'enable'   => env('APP_DEBUG', false),
            'interval' => 1,
            'include'  => [
                app()->getRootPath() . 'app',
                app()->getRootPath() . 'core',
                app()->getRootPath() . 'extend',
            ],
        ]
    ]
];