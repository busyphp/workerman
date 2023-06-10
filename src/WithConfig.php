<?php
declare(strict_types = 1);

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
     * @return mixed
     */
    public function getWorkerConfig(string $name, mixed $default = null) : mixed
    {
        $app = $this->app ?? App::getInstance();
        
        return $app->config->get("workerman.{$name}", $default);
    }
    
    
    /**
     * 获取gateway配置
     * @param string $name
     * @param string     $key
     * @param mixed|null $default
     * @return array|mixed
     */
    protected function getGatewayConfig(string $name, string $key, mixed $default = null) : mixed
    {
        $key = $key ? ".$key" : '';
        
        return $this->getWorkerConfig("gateway.$name$key", $default);
    }
    
    
    /**
     * 获取queue配置
     * @param string $name
     * @param string     $key
     * @param mixed|null $default
     * @return array|mixed
     */
    protected function getQueueConfig(string $name, string $key, mixed $default = null) : mixed
    {
        $key = $key ? ".$key" : '';
        
        return $this->getWorkerConfig("queue.workers.$name$key", $default);
    }
}