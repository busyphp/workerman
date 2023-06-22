<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use BusyPHP\App;
use think\Request;
use Workerman\Worker;

/**
 * GatewayWorker事件类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:21 PM GatewayEvents.php $
 */
class GatewayEvents
{
    /** @var App */
    protected static $app;
    
    
    /**
     * BusinessWorker进程启动时触发。每个进程生命周期内都只会触发一次
     * @param Worker $worker
     */
    public static function onWorkerStart(Worker $worker) : void
    {
        self::$app = new Application;
        self::$app->initialize();
    }
    
    
    /**
     * BusinessWorker进程退出时触发。每个进程生命周期内都只会触发一次
     * @param Worker $worker
     */
    public static function onWorkerStop(Worker $worker) : void
    {
    }
    
    
    /**
     * 当客户端连接上gateway进程时(TCP三次握手完毕时)触发
     * @param string $clientId 客户端ID
     */
    public static function onConnect(string $clientId) : void
    {
    }
    
    
    /**
     * 当客户端连接上gateway完成websocket握手时触发
     * @param string $clientId 客户端ID
     * @param array  $data 请求数据
     */
    public static function onWebSocketConnect(string $clientId, array $data) : void
    {
        // 分离headers
        $headers = [];
        foreach ($data['server'] ?? [] as $key => $value) {
            if (0 === stripos($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        
        // 解析PATH_INFO
        $path                        = parse_url($data['server']['REQUEST_URI'], PHP_URL_PATH) ?: '/';
        $data['server']['PATH_INFO'] = $path;
        
        
        // 解析请求
        /** @var Request $request */
        $request = static::$app->make('request');
        $request->withGet($data['get'] ?? [])
            ->withServer($data['server'] ?? [])
            ->withHeader($headers)
            ->withCookie($data['cookie'] ?? [])
            ->setBaseUrl($path)
            ->setUrl($data['server']['REQUEST_URI'] ?? '/')
            ->setPathinfo(ltrim($path, '/'));
        
        static::$app->instance('request', $request);
        static::onOpen($clientId, $request);
    }
    
    
    /**
     * 当客户端连接上gateway完成websocket握手时触发
     * @param string  $clientId 客户端ID
     * @param Request $request 请求对象
     */
    public static function onOpen(string $clientId, Request $request) : void
    {
    }
    
    
    /**
     * 当客户端发来数据(Gateway进程收到数据)后触发
     * @param string $clientId 客户端ID
     * @param mixed  $data 收到的数据
     */
    public static function onMessage(string $clientId, mixed $data) : void
    {
    }
    
    
    /**
     * 当用户断开连接时触发的方法
     * @param string $clientId 客户端ID
     */
    public static function onClose(string $clientId) : void
    {
    }
}
