<?php

namespace BusyPHP\workerman;

use BusyPHP\App;

/**
 * WithConfig
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 4:09 PM WithConfig.php $
 * @property App $app
 */
trait WithConfig
{
    /**
     * 获取Worker配置
     * @param string $name
     * @param mixed  $default
     * @return array|mixed
     */
    public function getWorkerConfig(string $name, $default = null)
    {
        $app = $this->app ?? App::getInstance();
        
        return $app->config->get("busy-workerman.{$name}", $default);
    }
    
    
    /**
     * 获取gateway配置
     * @param string|int $name
     * @param string     $key
     * @param mixed      $default
     * @return array|mixed
     */
    protected function getGatewayConfig($name, string $key, $default = null)
    {
        $key = $key ? ".{$key}" : '';
        
        return $this->getWorkerConfig("gateway.{$name}{$key}", $default);
    }
}