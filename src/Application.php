<?php
namespace BusyPHP\workerman;

use think\App;
use think\exception\Handle;
use think\exception\HttpException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http as WorkerHttp;
use Workerman\Worker;

/**
 * Application
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:22 PM Application.php $
 */
class Application extends App
{
    /**
     * @var Worker
     */
    public $workerman;
    
    
    /**
     * 处理Worker请求
     * @access public
     * @param TcpConnection $connection
     * @param void
     */
    public function worker(TcpConnection $connection)
    {
        try {
            $this->beginTime = microtime(true);
            $this->beginMem  = memory_get_usage();
            $this->db->clearQueryTimes();
            
            $pathinfo = ltrim(strpos($_SERVER['REQUEST_URI'], '?') ? strstr($_SERVER['REQUEST_URI'], '?', true) : $_SERVER['REQUEST_URI'], '/');
            
            $this->request
                ->setPathinfo($pathinfo)
                ->withInput($GLOBALS['HTTP_RAW_POST_DATA']);
            
            while (ob_get_level() > 1) {
                ob_end_clean();
            }
            
            ob_start();
            $response = $this->http->run();
            $content  = ob_get_clean();
            
            ob_start();
            
            $response->send();
            $this->http->end($response);
            
            $content .= ob_get_clean() ?: '';
            
            $this->httpResponseCode($response->getCode());
            
            foreach ($response->getHeader() as $name => $val) {
                // 发送头部信息
                WorkerHttp::header($name . (!is_null($val) ? ':' . $val : ''));
            }
            
            if (strtolower($_SERVER['HTTP_CONNECTION']) === "keep-alive") {
                $connection->send($content);
            } else {
                $connection->close($content);
            }
        } catch (HttpException | \Exception | \Throwable $e) {
            $this->exception($connection, $e);
        }
    }
    
    
    /**
     * 是否运行在命令行下
     * @return bool
     */
    public function runningInConsole() : bool
    {
        return false;
    }
    
    
    protected function httpResponseCode($code = 200)
    {
        WorkerHttp::responseCode($code);
    }
    
    
    protected function exception($connection, $e)
    {
        if ($e instanceof \Exception) {
            $handler = $this->make(Handle::class);
            $handler->report($e);
            
            $resp    = $handler->render($this->request, $e);
            $content = $resp->getContent();
            $code    = $resp->getCode();
            
            $this->httpResponseCode($code);
            $connection->send($content);
        } else {
            $this->httpResponseCode(500);
            $connection->send($e->getMessage());
        }
    }
}
