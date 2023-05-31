<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use BusyPHP\workerman\command\Server;


/**
 * 服务类
 * @author busy^life <busy.life@qq.com>
 * @author liu21st <liu21st@gmail.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:20 PM Service.php $
 */
class Service extends \think\Service
{
    public function register()
    {
        $this->commands(Server::class);
    }
}
