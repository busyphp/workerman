<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use Closure;
use RuntimeException;
use think\Request;
use Workerman\Connection\ConnectionInterface;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Worker;

/**
 * Worker服务基本类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:21 PM BaseServer.php $
 */
abstract class BaseServer
{
    /**
     * @var Application
     */
    protected Application $app;
    
    /**
     * @var Worker
     */
    protected Worker $worker;
    
    /**
     * 应用初始化设置
     * @var Closure|null
     */
    protected ?Closure $appInit = null;
    
    /**
     * 系统根目录
     * @var string
     */
    protected string $rootPath = '';
    
    /**
     * Web根目录
     * @var string
     */
    protected string $webPath = '';
    
    /**
     * 指定协议，如：http://127.0.0.0:8888，设为true则无协议
     * @var string|bool
     */
    protected string|bool $socket = '';
    
    /**
     * 协议
     * @var string
     */
    protected string $protocol = '';
    
    /**
     * IP
     * @var string
     */
    protected string $host = '127.0.0.1';
    
    /**
     * 端口
     * @var string
     */
    protected string $port = '';
    
    /**
     * Worker配置
     * @var array
     */
    protected array $option = [];
    
    /**
     * Worker上下文
     * @var array
     */
    protected array $context = [];
    
    
    /**
     * 架构函数
     */
    public function __construct()
    {
        if (!$this->socket && (!$this->protocol || !$this->port)) {
            throw new RuntimeException(sprintf('%s no initial parameters are set', static::class));
        }
        
        $this->worker = new Worker($this->socket ? ($this->socket === true ? '' : $this->socket) : ($this->protocol . '://' . $this->host . ':' . $this->port), $this->context);
        $this->setOption($this->option);
        $this->prepareEvent();
    }
    
    
    /**
     * 设置Worker参数
     * @param array $options
     */
    public function setOption(array $options) : void
    {
        foreach ($options as $key => $val) {
            $this->worker->$key = $val;
        }
    }
    
    
    /**
     * 准备事件监听
     */
    protected function prepareEvent() : void
    {
        // 设置回调
        $this->worker->onWorkerStart  = [$this, 'onWorkerStart'];
        $this->worker->onWorkerStop   = [$this, 'onWorkerStop'];
        $this->worker->onWorkerReload = [$this, 'onWorkerReload'];
        $this->worker->onConnect      = [$this, 'onConnect'];
        $this->worker->onMessage      = [$this, 'onMessage'];
        $this->worker->onClose        = [$this, 'onClose'];
        $this->worker->onBufferFull   = [$this, 'onBufferFull'];
        $this->worker->onBufferDrain  = [$this, 'onBufferDrain'];
        $this->worker->onError        = [$this, 'onError'];
    }
    
    
    /**
     * 设置运行跟目录
     * @param string $path
     */
    public function setRootPath(string $path) : void
    {
        $this->rootPath = $path;
    }
    
    
    /**
     * 设置Web入口更目录
     * @param string $path
     */
    public function setWebPath(string $path) : void
    {
        $this->webPath = $path;
    }
    
    
    /**
     * 设置应用设置闭包
     * @param Closure $closure
     */
    public function setAppInit(Closure $closure) : void
    {
        $this->appInit = $closure;
    }
    
    
    /**
     * 预处理请求
     * @param WorkermanRequest $req
     * @return Request
     */
    protected function prepareRequest(WorkermanRequest $req) : Request
    {
        $header = $req->header() ?: [];
        
        $_SERVER['REQUEST_URI']    = $req->uri();
        $_SERVER['QUERY_STRING']   = $req->queryString();
        $_SERVER['PATH_INFO']      = $req->path();
        $_SERVER['REQUEST_METHOD'] = $req->method();
        $server                    = $_SERVER;
        
        foreach ($header as $key => $value) {
            $server["http_" . str_replace('-', '_', $key)] = $value;
        }
        
        /** @var Request $request */
        $request = $this->app->make('request', [], true);
        
        return $request->withHeader($header)
            ->withServer($server)
            ->withGet($req->get() ?: [])
            ->withPost($req->post() ?: [])
            ->withCookie($req->cookie() ?: [])
            ->withInput($req->rawBody())
            ->withFiles($req->file())
            ->setBaseUrl($server['PATH_INFO'])
            ->setUrl($server['REQUEST_URI'])
            ->setPathinfo(ltrim($server['PATH_INFO'], '/'));
    }
    
    
    /**
     * Worker子进程启动时的回调函数，每个子进程启动时都会执行
     * @param Worker $worker Worker对象
     * @return void
     */
    public function onWorkerStart(Worker $worker) : void
    {
        $this->app = new Application($this->rootPath);
        
        // 初始化应用程序
        if ($this->appInit instanceof Closure) {
            call_user_func_array($this->appInit, [$this->app]);
        }
        
        $this->app->bind('db', Db::class);
        
        $this->app->initialize();
    }
    
    
    /**
     * Worker子进程退出时的回调函数，每个子进程退出时都会执行
     * @param Worker $worker Worker对象
     * @return void
     */
    public function onWorkerStop(Worker $worker) : void
    {
    }
    
    
    /**
     * 设置Worker收到reload信号后执行的回调。
     * @param Worker $worker
     * @return void
     */
    public function onWorkerReload(Worker $worker) : void
    {
    }
    
    
    /**
     * 当客户端与Workerman建立连接时(TCP三次握手完成后)触发的回调函数。每个连接只会触发一次onConnect回调。
     * @param ConnectionInterface $connection 连接对象，用于操作客户端连接，如发送数据，关闭连接等
     * @return void
     */
    public function onConnect(ConnectionInterface $connection) : void
    {
    }
    
    
    /**
     * 当客户端通过连接发来数据时(Workerman收到数据时)触发的回调函数
     * @param ConnectionInterface $connection 连接对象，用于操作客户端连接，如发送数据，关闭连接等
     * @param mixed         $data 客户端连接上发来的数据，如果Worker指定了协议，则$data是对应协议decode（解码）了的数据。数据类型与协议decode()实现有关，websocket text frame 为字符串，HTTP协议为 {@see WorkermanRequest} 对象。
     * @return void
     */
    public function onMessage(ConnectionInterface $connection, mixed $data) : void
    {
    }
    
    
    /**
     * 当客户端连接与Workerman断开时触发的回调函数。不管连接是如何断开的，只要断开就会触发onClose。每个连接只会触发一次onClose
     * - 如果对端是由于断网或者断电等极端情况断开的连接，这时由于无法及时发送tcp的fin包给workerman，workerman就无法得知连接已经断开，也就无法及时触发onClose
     * - 由于udp是无连接的，所以当使用udp时不会触发onConnect回调，也不会触发onClose回调
     * @param ConnectionInterface $connection
     * @return void
     */
    public function onClose(ConnectionInterface $connection) : void
    {
    }
    
    
    /**
     * 每个连接都有一个单独的应用层发送缓冲区，如果客户端接收速度小于服务端发送速度，数据会在应用层缓冲区暂存，如果缓冲区满则会触发onBufferFull回调
     * @param ConnectionInterface $connection 连接对象，用于操作客户端连接，如发送数据，关闭连接等
     * @return void
     */
    public function onBufferFull(ConnectionInterface $connection) : void
    {
    }
    
    
    /**
     * 每个连接都有一个单独的应用层发送缓冲区，缓冲区大小由TcpConnection::$maxSendBufferSize决定，默认值为1MB，可以手动设置更改大小，更改后会对所有连接生效。
     * - 该回调在应用层发送缓冲区数据全部发送完毕后触发。一般与onBufferFull配合使用，例如在onBufferFull时停止向对端继续send数据，在onBufferDrain恢复写入数据。
     * @param ConnectionInterface $connection 连接对象，用于操作客户端连接，如发送数据，关闭连接等
     * @return void
     */
    public function onBufferDrain(ConnectionInterface $connection) : void
    {
    }
    
    
    /**
     * 当客户端的连接上发生错误时触发，目前错误类型有：
     * 1. 调用Connection::send由于客户端连接断开导致的失败（紧接着会触发onClose回调） (code:WORKERMAN_SEND_FAIL msg:client closed)
     * 2. 在触发onBufferFull后(发送缓冲区已满)，仍然调用Connection::send，并且发送缓冲区仍然是满的状态导致发送失败(不会触发onClose回调) (code:WORKERMAN_SEND_FAIL msg:send buffer full and drop package)
     * 3. 使用AsyncTcpConnection异步连接失败时(紧接着会触发onClose回调) (code:WORKERMAN_CONNECT_FAIL msg:stream_socket_client返回的错误消息)
     * @param ConnectionInterface $connection 连接对象，用于操作客户端连接，如发送数据，关闭连接等
     * @param int           $code 错误码
     * @param string        $msg 错误消息
     * @return void
     */
    public function onError(ConnectionInterface $connection, int $code, string $msg) : void
    {
    }
    
    
    /**
     * 启动服务
     */
    public function start() : void
    {
        Worker::runAll();
    }
    
    
    /**
     * 重启服务
     * @param bool $all 是否重启所有子进程
     */
    public function restart(bool $all = false) : void
    {
        if ($all) {
            posix_kill(posix_getppid(), SIGUSR1);
        } else {
            Worker::stopAll();
        }
    }
}
