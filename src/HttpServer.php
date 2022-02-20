<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use BusyPHP\Request;
use BusyPHP\workerman\middleware\ResetVarDumper;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\VarDumper\VarDumper;
use think\exception\Handle;
use think\Response;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Lib\Timer;
use Workerman\Protocols\Http\Request as WorkerManRequest;
use Workerman\Protocols\Http\Response as WorkerManResponse;
use Workerman\Worker;


/**
 * HTTP服务器
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:20 PM HttpServer.php $
 */
class HttpServer extends BaseServer
{
    /**
     * 热更新配置
     * @var array
     */
    protected $monitor;
    
    /**
     * 最后一次热更新时间
     * @var int
     */
    protected $lastMtime;
    
    
    /**
     * 架构函数
     * @param string     $host 监听地址
     * @param int|string $port 监听端口
     * @param array      $context 参数
     * @param array      $option 配置
     */
    public function __construct(string $host, $port, array $context = [], array $option = [])
    {
        $this->protocol = 'http';
        $this->host     = $host;
        $this->port     = $port;
        $this->option   = $option;
        $this->context  = $context;
        parent::__construct();
    }
    
    
    /**
     * 设置热更新
     * @param int      $interval
     * @param string[] $path
     */
    public function setMonitor(int $interval = 2, array $path = [])
    {
        $this->monitor['interval'] = $interval;
        $this->monitor['path']     = $path;
    }
    
    
    /**
     * onWorkerStart 事件回调
     * @param Worker $worker
     */
    public function onWorkerStart(Worker $worker)
    {
        parent::onWorkerStart($worker);
        
        // 启动热更新
        $this->lastMtime = time();
        if (0 == $worker->id && $this->monitor) {
            $paths = $this->monitor['path'];
            $timer = $this->monitor['interval'] ?: 2;
            
            Timer::add($timer, function() use ($paths) {
                foreach ($paths as $path) {
                    $dir      = new RecursiveDirectoryIterator($path);
                    $iterator = new RecursiveIteratorIterator($dir);
                    
                    foreach ($iterator as $file) {
                        if (pathinfo((string) $file, PATHINFO_EXTENSION) != 'php') {
                            continue;
                        }
                        
                        if ($this->lastMtime < $file->getMTime()) {
                            echo '[update]' . $file . "\n";
                            
                            $this->restart(true);
                            $this->lastMtime = $file->getMTime();
                            
                            return;
                        }
                    }
                }
            });
        }
    }
    
    
    /**
     * onMessage 事件回调
     * @access public
     * @param TcpConnection    $tcpConnection
     * @param WorkerManRequest $req
     * @return void
     */
    public function onMessage(TcpConnection $tcpConnection, WorkerManRequest $req)
    {
        // 判断是否文件
        $path = ltrim($req->path(), '/');
        $file = $this->webPath . $path;
        if (is_file($file)) {
            // 文件未修改则返回304
            if (!empty($ifModifiedSince = $req->header('If-Modified-Since'))) {
                if (filemtime($file) === strtotime($ifModifiedSince)) {
                    $tcpConnection->send(new WorkerManResponse(304));
                    
                    return;
                }
            }
            
            $res = new WorkerManResponse();
            $res->withFile($file);
            $tcpConnection->send($res);
            
            return;
        }
        
        // 请求处理
        $this->app->reset();
        $this->app->setInConsole(false);
        
        // dump中间件
        if (class_exists(VarDumper::class)) {
            $this->app->middleware->add(ResetVarDumper::class);
        }
        
        $request = $this->prepareRequest($req);
        try {
            $response = $this->handleRequest($request);
        } catch (Throwable $e) {
            /** @var Handle $handle */
            $handle   = $this->app->make(Handle::class);
            $response = $handle->render($request, $e);
        }
        
        $headers           = $response->getHeader();
        $headers['Server'] = 'BusyPHP Workerman Server';
        if (!isset($headers['Transfer-Encoding'])) {
            unset($headers['Content-Length']);
        }
        
        $res = new WorkerManResponse($response->getCode(), $headers, $response->getContent());
        
        // 设置Cookie
        foreach ($this->app->cookie->getCookie() as $name => $val) {
            [$value, $expire, $option] = $val;
            $res->cookie($name, $value, $expire, $option['path'], $option['domain'], (bool) $option['secure'], (bool) $option['httponly'], $option['samesite']);
        }
        
        $tcpConnection->send($res);
    }
    
    
    /**
     * 处理请求
     * @param Request $request
     * @return Response
     */
    protected function handleRequest(Request $request) : Response
    {
        $level = ob_get_level();
        ob_start();
        
        $response = $this->app->http->run($request);
        $content  = $response->getContent();
        
        if (ob_get_level() == 0) {
            ob_start();
        }
        
        $this->app->http->end($response);
        
        if (ob_get_length() > 0) {
            $response->content(ob_get_contents() . $content);
        }
        
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        
        return $response;
    }
}
