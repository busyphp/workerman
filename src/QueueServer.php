<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use BusyPHP\queue\Worker as QueueWorker;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 队列服务
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/6/10 17:23 QueueServer.php $
 */
class QueueServer extends BaseServer
{
    use WithConfig;
    
    protected string      $name;
    
    protected string|bool $socket = true;
    
    
    public function __construct(string $name)
    {
        parent::__construct();
        
        $this->name = $name;
        
        // 启动多少个进程
        $this->worker->count = max($this->getQueueConfig($name, 'number', 0), 1);
    }
    
    
    public function onWorkerStart(Worker $worker) : void
    {
        parent::onWorkerStart($worker);
        
        $delay      = $this->getQueueConfig($this->name, 'delay', 0);
        $sleep      = $this->getQueueConfig($this->name, 'sleep', 3);
        $tries      = $this->getQueueConfig($this->name, 'tries', 0);
        $timeout    = $this->getQueueConfig($this->name, 'timeout', 60);
        $connection = $this->getQueueConfig($this->name, 'connection');
        
        $queueWorker = $this->app->make(QueueWorker::class);
        Timer::add(0.001, function() use ($queueWorker, $delay, $sleep, $tries, $timeout, $connection) {
            $timeId = Timer::add($timeout, function() {
                $this->restart();
            });
            
            $queueWorker->runNextJob($connection, $this->name, $delay, $sleep, $tries);
            
            Timer::del($timeId);
        });
    }
}