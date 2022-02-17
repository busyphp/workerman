<?php
declare(strict_types = 1);

namespace BusyPHP\workerman;

use BusyPHP\App;
use BusyPHP\Request;
use BusyPHP\workerman\middleware\ResetVarDumper;
use Closure;
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
 * Worker http server 命令行服务类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:20 PM Http.php $
 */
class HttpServer extends Server
{
    /**
     * @var Application
     */
    protected $app;
    
    /**
     * 系统根目录
     * @var string
     */
    protected $rootPath;
    
    /**
     * Web根目录
     * @var string
     */
    protected $root;
    
    /**
     * 应用初始化设置
     * @var Closure|null
     */
    protected $appInit;
    
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
     * 设置运行跟目录
     * @param string $path
     */
    public function setRootPath(string $path)
    {
        $this->rootPath = $path;
    }
    
    
    /**
     * 设置应用设置闭包
     * @param Closure $closure
     */
    public function appInit(Closure $closure)
    {
        $this->appInit = $closure;
    }
    
    
    /**
     * 设置Web入口更目录
     * @param string $path
     */
    public function setRoot(string $path)
    {
        $this->root = $path;
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
        if (!$this->app instanceof Application) {
            $this->app = new Application($this->rootPath);
            $this->app->bind(Application::class, App::class);
        }
        
        // 初始化应用程序
        if ($this->appInit) {
            call_user_func_array($this->appInit, [$this->app]);
        }
        
        $this->app->initialize();
        
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
                            posix_kill(posix_getppid(), SIGUSR1);
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
        $path = ltrim($req->path(), '/');
        $file = $this->root . $path;
        
        // 不是文件
        if (!is_file($file)) {
            $this->app->reset();
            
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
            
            $headers = $response->getHeader();
            unset($headers['Content-Length']);
            $res = new WorkerManResponse($response->getCode(), $headers, $response->getContent());
            
            // 设置Cookie
            foreach ($this->app->cookie->getCookie() as $name => $val) {
                [$value, $expire, $option] = $val;
                $res->cookie($name, $value, $expire, $option['path'], $option['domain'], (bool) $option['secure'], (bool) $option['httponly'], $option['samesite']);
            }
        } else {
            // 文件未修改则返回304
            if (!empty($ifModifiedSince = $req->header('If-Modified-Since'))) {
                if (filemtime($file) === strtotime($ifModifiedSince)) {
                    $tcpConnection->send(new WorkerManResponse(304));
                    
                    return;
                }
            }
            
            $res = new WorkerManResponse();
            $res->withFile($file);
        }
        
        $tcpConnection->send($res);
    }
    
    
    /**
     * 预处理请求
     * @param WorkerManRequest $req
     * @return Request
     */
    protected function prepareRequest(WorkerManRequest $req) : Request
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
