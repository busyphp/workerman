<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use BusyPHP\app\admin\model\system\task\SystemTask;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 后台任务服务
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2023 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2023/6/22 18:19 TaskServer.php $
 */
class TaskServer extends BaseServer
{
    protected string|bool $socket = true;
    
    
    public function onWorkerStart(Worker $worker) : void
    {
        parent::onWorkerStart($worker);
        
        Timer::add(0.001, function() {
            $this->runNextTask();
        });
    }
    
    
    /**
     * 执行下一个任务
     * @throws Throwable
     */
    protected function runNextTask()
    {
        $task = SystemTask::init();
        $task::setRunningServer(getmypid(), 'workerman');
        
        $info = $task->getWait();
        if ($info) {
            $task->run($info->id, getmypid());
        } else {
            sleep(3);
        }
    }
}