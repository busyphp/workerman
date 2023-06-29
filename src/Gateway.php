<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use BusyPHP\helper\ArrayHelper;
use GatewayWorker\Lib\Gateway as WorkermanGateway;
use think\facade\Config;

/**
 * Gateway
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/6/29 11:16 Gateway.php $
 * @mixin WorkermanGateway
 */
class Gateway
{
    protected static self   $instance;
    
    protected static string $gatewayName;
    
    
    /**
     * 切换{@see WorkermanGateway}服务
     * @param string $name 服务名称 `config/workerman.php` 中的 `gateway` 服务名称
     * @return static
     */
    public static function server(string $name = '') : static
    {
        static::$gatewayName = $name;
        
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }
    
    
    /**
     * 初始化注册中心地址
     */
    protected static function initRegisterAddress() : void
    {
        if (!isset(static::$gatewayName)) {
            static::$gatewayName = '';
        }
        
        $config = (array) Config::get('workerman.gateway', []);
        if (!static::$gatewayName) {
            $keys = array_keys($config);
            if ($keys) {
                static::$gatewayName = $keys[0];
            }
        }
        if (!static::$gatewayName) {
            return;
        }
        $address = ArrayHelper::get($config, static::$gatewayName . '.register.address');
        if ($address) {
            WorkermanGateway::$registerAddress = $address;
        }
    }
    
    
    public static function __callStatic(string $name, array $arguments)
    {
        static::initRegisterAddress();
        
        return call_user_func_array([WorkermanGateway::class, $name], $arguments);
    }
    
    
    public function __call(string $name, array $arguments)
    {
        static::initRegisterAddress();
        
        return call_user_func_array([WorkermanGateway::class, $name], $arguments);
    }
}