Workerman扩展
===============

> 支持异步数据处理，如批量发送邮件、短信等。可用于创建Http服务，Websocket服务、Tcp服务、Rpc服务以脱离Apache、Nginx等独立运行

## 安装方式

```shell script
composer require busyphp/workerman
```

## 服务命令

适用于 `http`，`tcp`，`websocket` 等服务<br />
`cd` 到到项目根目录下执行

### 启动命令

```shell script
php think workerman
php think workerman start
```

### 停止命令

```shell script
php think workerman stop
```

### 重启命令

```shell script
php think workerman restart
```

| 参数       | 默认值 | 说明                                                                  |
|:---------|:---:|:--------------------------------------------------------------------|
| --host   |  无  | 监听IP                                                                |
| --port   |  无  | 监听端口                                                                |
| --daemon |  无  | 是否常驻进程                                                              |
| --server |  无  | 指定启动的服务名称，如：`http`，`gateway.websocket`, `gateway.websocket.gateway` |

## 配置 `config/workerman.php`

```php
return [
    // 是否进入守护模式
    'daemonize' => false,
    
    // HTTP服务器配置
    'http'      => [
        // 是否启动HTTP服务
        'enable'     => false,
        
        // 监听IP
        'host'       => '127.0.0.1',
        
        // 监听端口
        'port'       => 2346,
        
        // 是否启用HTTPS
        'ssl'        => false,
        
        // 用于传递socket的上下文选项，参见 https://www.php.net/manual/zh/context.socket.php
        'context'    => [],
        
        // 启动进程数量，参见 https://www.workerman.net/doc/gateway-worker/process-count-seting.html
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
    ],
    
    // 长连接配置
    // 支持多种长连接服务
    // 格式：服务名称 => [服务配置]
    'gateway'   => [
        // 服务名称 => 服务配置
        
        'websocket' => [
            // Register注册中心进程配置
            'register' => [
                // 是否启用
                'enable'  => false,
                
                // 注册中心地址
                'address' => '127.0.0.1:1236'
            ],
            
            // BusinessWorker业务进程配置
            'business' => [
                // 是否启用
                'enable'     => false,
                
                // 启动进程数量，参见 https://www.workerman.net/doc/gateway-worker/process-count-seting.html
                'worker_num' => 1,
                
                // 回调事件处理类名，必须继承 \BusyPHP\workerman\GatewayEvents 类
                'handler'    => '\BusyPHP\workerman\GatewayEvents'
            ],
            
            // Gateway网关进程配置
            'gateway'  => [
                // 是否启用
                'enable'     => false,
                
                // 服务协议, 支持 tcp udp unix http websocket text
                'protocol'   => 'websocket',
                
                // 监听IP
                'host'       => '127.0.0.1',
                
                // 监听端口
                'port'       => 2348,
                
                // 直接指定协议，如：tcp://127.0.0.1:8080，指定后 host port 会失效
                'socket'     => '',
                
                // 用于传递socket的上下文选项，参见 https://www.php.net/manual/zh/context.socket.php
                'context'    => [],
                
                // 启动进程数量，参见 https://www.workerman.net/doc/gateway-worker/process-count-seting.html
                'worker_num' => 1,
                
                // Gateway所在服务器的内网IP
                // 多服务器分布式部署的时候需要填写真实的内网ip，不能填写127.0.0.1。注意：lanIp只能填写真实ip，不能填写域名或者其它字符串，无论如何都不能写0.0.0.0 .
                'lan_ip'     => '127.0.0.1',
                
                // Gateway进程启动后会监听一个本机端口，用来给BusinessWorker提供链接服务，然后Gateway与BusinessWorker之间就通过这个连接通讯。
                // 这里设置的是Gateway监听本机端口的起始端口。比如启动了4个Gateway进程，startPort为2000，则每个Gateway进程分别启动的本地端口一般为2000、2001、2002、2003。
                'start_port' => 2000,
                
                // 是否启用HTTPS，参见 https://www.workerman.net/doc/gateway-worker/secure-websocket-server.html
                'ssl'        => false,
                
                // 心跳设置
                'ping'       => [
                    // 心跳检测时间间隔 单位：秒。如果设置为0代表不做任何心跳检测。
                    'interval' => 0,
                    
                    // 客户端连续 limit 次 interval 时间内不发送任何数据(包括但不限于心跳数据)则断开链接，并触发onClose
                    // 如果设置为0代表客户端不用发送心跳数据，即通过TCP层面检测连接的连通性（极端情况至少10分钟才能检测到连接断开，甚至可能永远检测不到）
                    'limit'    => 0,
                    
                    // 指定服务端定时给客户端发送的心跳数据
                    'data'     => '',
                ]
            ]
        ],
    ],
    
    // 队列配置
    'queue'     => [
        'enable'  => false,
    
        // 进程配置
        'workers' => [
            // 队列名称 => [队列配置]
            'default' => [
                // 启动几个worker并行执行
                'number'     => 1,
            
                // 设置使用那个驱动执行，默认依据 `config/queue.php` 中的 `default` 确定
                'connection' => '',
            
                // 如果本次任务执行抛出异常且任务未被删除时，设置其下次执行前延迟多少秒
                'delay'      => 60,
            
                // 如果队列中无任务，则多长时间后重新检查
                'sleep'      => 3,
            
                // 如果任务已经超过尝试次数上限，0为不限，则触发当前任务类型下的failed()方法
                'tries'      => 0,
            
                // 进程的允许执行的最长时间，以秒为单位
                'timeout'    => 60,
            ],
        
            // 更多队列配置
        ],
    ],
    
    // 自定义服务配置
    'server'    => [
        // '服务名称' => '服务类，必须继承 \BusyPHP\workerman\BaseServer 类'
    ]
]
```