<?php
namespace BusyPHP\workerman;

use BusyPHP\App;

/**
 * Application
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:22 PM Application.php $
 */
class Application extends App
{
    /**
     * 重置
     */
    public function reset()
    {
        $this->beginTime = microtime(true);
        $this->beginMem  = memory_get_usage();
        $this->db->clearQueryTimes();
    }
    
    
    /**
     * 是否运行在命令行下
     * @return bool
     */
    public function runningInConsole() : bool
    {
        return false;
    }
}
