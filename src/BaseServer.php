<?php
namespace BusyPHP\workerman;

use Workerman\Connection\TcpConnection;
use Workerman\Worker;

/**
 * Worker服务基本类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:21 PM BaseServer.php $
 * @method void onWorkerStart(Worker $worker) Worker子进程启动时的回调函数，每个子进程启动时都会执行。
 * @method void onWorkerStop(Worker $worker) Worker子进程退出时的回调函数，每个子进程退出时都会执行。
 * @method void onWorkerReload(Worker $worker) Worker收到reload信号后执行的回调
 * @method void onConnect(TcpConnection $tcpConnection) 当客户端与Workerman建立连接时(TCP三次握手完成后)触发的回调函数。每个连接只会触发一次onConnect回调。
 * @method void onMessage(TcpConnection $tcpConnection, $data) 当客户端通过连接发来数据时(Workerman收到数据时)触发的回调函数
 * @method void onClose(TcpConnection $tcpConnection) 当客户端连接与Workerman断开时触发的回调函数。不管连接是如何断开的，只要断开就会触发onClose。每个连接只会触发一次onClose
 * @method void onBufferFull(TcpConnection $tcpConnection) 每个连接都有一个单独的应用层发送缓冲区，如果客户端接收速度小于服务端发送速度，数据会在应用层缓冲区暂存，如果缓冲区满则会触发onBufferFull回调。
 * @method void onBufferDrain(TcpConnection $tcpConnection) 每个连接都有一个单独的应用层发送缓冲区，缓冲区大小由TcpConnection::$maxSendBufferSize决定，默认值为1MB，可以手动设置更改大小，更改后会对所有连接生效。该回调在应用层发送缓冲区数据全部发送完毕后触发。一般与onBufferFull配合使用，例如在onBufferFull时停止向对端继续send数据，在onBufferDrain恢复写入数据。调。
 * @method void onError(TcpConnection $tcpConnection, $code, $msg) 当客户端的连接上发生错误时触发
 */
abstract class BaseServer
{
    /**
     * @var Worker
     */
    protected $worker;
    
    /**
     * 指定协议，如：http://127.0.0.0:8888
     * @var string
     */
    protected $socket = '';
    
    /**
     * 协议
     * @var string
     */
    protected $protocol = 'http';
    
    /**
     * IP
     * @var string
     */
    protected $host = '0.0.0.0';
    
    /**
     * 端口
     * @var string
     */
    protected $port = '2346';
    
    /**
     * Worker配置
     * @var array
     */
    protected $option = [];
    
    /**
     * Worker上下文
     * @var array
     */
    protected $context = [];
    
    
    /**
     * 架构函数
     */
    public function __construct()
    {
        $this->worker = new Worker($this->socket ?: ($this->protocol . '://' . $this->host . ':' . $this->port), $this->context);
        
        // 设置参数
        foreach ($this->option as $key => $val) {
            $this->worker->$key = $val;
        }
        
        // 设置回调
        if (method_exists($this, 'onWorkerStart')) {
            $this->worker->onWorkerStart = [$this, 'onWorkerStart'];
        }
        if (method_exists($this, 'onWorkerStop')) {
            $this->worker->onWorkerStop = [$this, 'onWorkerStop'];
        }
        if (method_exists($this, 'onWorkerReload')) {
            $this->worker->onWorkerReload = [$this, 'onWorkerReload'];
        }
        if (method_exists($this, 'onConnect')) {
            $this->worker->onConnect = [$this, 'onConnect'];
        }
        if (method_exists($this, 'onMessage')) {
            $this->worker->onMessage = [$this, 'onMessage'];
        }
        if (method_exists($this, 'onClose')) {
            $this->worker->onClose = [$this, 'onClose'];
        }
        if (method_exists($this, 'onBufferFull')) {
            $this->worker->onBufferFull = [$this, 'onBufferFull'];
        }
        if (method_exists($this, 'onBufferDrain')) {
            $this->worker->onBufferDrain = [$this, 'onBufferDrain'];
        }
        if (method_exists($this, 'onError')) {
            $this->worker->onError = [$this, 'onError'];
        }
    }
    
    
    /**
     * 启动服务
     */
    public function start()
    {
        Worker::runAll();
    }
    
    
    /**
     * 停止服务
     */
    public function stop()
    {
        Worker::stopAll();
    }
}
