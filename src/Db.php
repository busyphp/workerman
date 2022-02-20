<?php

namespace BusyPHP\workerman;

/**
 * Db
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/19 9:43 PM Db.php $
 */
class Db extends \BusyPHP\Db
{
    protected function getConnectionConfig(string $name) : array
    {
        $config = parent::getConnectionConfig($name);
        
        //打开断线重连
        $config['break_reconnect'] = true;
        
        return $config;
    }
}