<?php
namespace BusyPHP\workerman\command;

use BusyPHP\exception\ClassNotExtendsException;
use BusyPHP\exception\ClassNotFoundException;
use BusyPHP\helper\ArrayHelper;
use BusyPHP\workerman\BaseServer;
use BusyPHP\workerman\GatewayEvents;
use BusyPHP\workerman\WithConfig;
use Closure;
use GatewayWorker\BusinessWorker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;
use BusyPHP\workerman\HttpServer;
use Workerman\Worker;


/**
 * Server
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:23 PM Server.php $
 */
class Server extends Command
{
    use WithConfig;
    
    protected $config = [];
    
    
    public function configure()
    {
        $this->setName('workerman')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status|connections", 'start')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'the host of WorkerMan server.')
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'the port of WorkerMan server.')
            ->addOption('server', 's', Option::VALUE_OPTIONAL, 'Specify the name of the enabled service')
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the WorkerMan server in daemon mode.')
            ->setDescription('Workerman for BusyPHP');
    }
    
    
    public function handle()
    {
        $action = $this->input->getArgument('action');
        if (DIRECTORY_SEPARATOR !== '\\') {
            if (!in_array($action, ['start', 'stop', 'reload', 'restart', 'status', 'connections'])) {
                $this->output->writeln("<error>Invalid argument action:{$action}, Expected start|stop|restart|reload|status|connections .</error>");
                
                return;
            }
            
            global $argv;
            array_shift($argv);
            array_shift($argv);
            array_unshift($argv, 'think', $action);
        } elseif ('start' != $action) {
            $this->output->writeln("<error>Not Support action:{$action} on Windows.</error>");
            
            return;
        }
        
        if ('start' == $action) {
            $this->output->writeln('Starting Workerman http server...');
        }
        
        // ????????????
        if ($server = trim($this->getInputOption('server', ''))) {
            // ??????HTTP??????
            if ($server === 'http') {
                $this->startHttpServer();
            } else {
                // ???????????????????????????
                if (0 === stripos($server, 'gateway.')) {
                    $name = substr($server, 8);
                    if (!$name) {
                        $this->output->writeln("<error>The '$server' input is incorrect, example: gateway.websocket or gateway.websocket.(register|business|gateway)</error>");
                        
                        return;
                    }
                    
                    // ????????????????????????window??????
                    if (false !== strpos($name, '.')) {
                        $arr  = explode('.', $name);
                        $name = trim($arr[0] ?? '');
                        $key  = trim($arr[1] ?? '');
                        if (!$name || !$key || !in_array($key, ['register', 'r', 'business', 'b', 'gateway', 'g'])) {
                            $this->output->writeln("<error>The '$server' input is incorrect, example: gateway.websocket or gateway.websocket.(register|business|gateway)</error>");
                            
                            return;
                        }
                        
                        switch ($key) {
                            case 'r':
                            case 'register':
                                $this->startRegisterWorker($name);
                            break;
                            case 'b':
                            case 'business':
                                $this->startBusinessWorker($name);
                            break;
                            case 'g':
                            case 'gateway':
                                $this->startGatewayWorker($name);
                            break;
                        }
                    }
                    
                    //
                    // ??????????????????
                    else {
                        $gatewayConfig = $this->getGatewayConfig($name, '', []);
                        if (!$gatewayConfig) {
                            $this->output->writeln("<error>The '$name' server configuration could not be found</error>");
                            
                            return;
                        }
                        
                        // ?????????Register??????
                        if (ArrayHelper::get($gatewayConfig, 'register.enable')) {
                            $this->startRegisterWorker($name);
                        }
                        
                        // ?????????BusinessWorker??????
                        if (ArrayHelper::get($gatewayConfig, 'business.enable')) {
                            $this->startBusinessWorker($name);
                        }
                        
                        // ?????????gateway??????
                        if (ArrayHelper::get($gatewayConfig, 'gateway.enable')) {
                            $this->startGatewayWorker($name);
                        }
                    }
                }
                
                //
                // ???????????????????????????
                elseif (0 === stripos($server, 'server.')) {
                    $name = substr($server, 7);
                    if (!$name) {
                        $this->output->writeln("<error>The '$server' input is incorrect, example: server.(name)</error>");
                        
                        return;
                    }
                    
                    try {
                        $this->startUseServer($name);
                    } catch (ClassNotFoundException | ClassNotExtendsException $e) {
                        $this->output->writeln("<error>{$e->getMessage()}</error>");
                        
                        return;
                    }
                }
                
                //
                // ??????
                else {
                    $this->output->writeln("<error>The '$server' input is incorrect, example: server.(name) or gateway.websocket or gateway.websocket.(register|business|gateway)</error>");
                    
                    return;
                }
            }
        } else {
            // ??????HTTP?????????
            if ($this->getWorkerConfig('http.enable')) {
                $this->startHttpServer();
            }
            
            // ?????????????????????
            foreach ($this->getWorkerConfig('gateway') ?: [] as $name => $config) {
                // ?????????Register??????
                if (ArrayHelper::get($config, 'register.enable')) {
                    $this->startRegisterWorker($name);
                }
                
                // ?????????BusinessWorker??????
                if (ArrayHelper::get($config, 'business.enable')) {
                    $this->startBusinessWorker($name);
                }
                
                // ?????????gateway??????
                if (ArrayHelper::get($config, 'gateway.enable')) {
                    $this->startGatewayWorker($name);
                }
            }
            
            // ?????????????????????
            foreach ($this->getWorkerConfig('server') ?: [] as $name => $class) {
                if (is_subclass_of($class, BaseServer::class)) {
                    try {
                        $this->startUseServer($name);
                    } catch (ClassNotFoundException | ClassNotExtendsException $e) {
                        $this->output->writeln("<error>{$e->getMessage()}</error>");
                        
                        return;
                    }
                }
            }
        }
        
        $runtimePath = $this->app->getRuntimeRootPath('workerman') . DIRECTORY_SEPARATOR;
        if (!is_dir($runtimePath)) {
            if (!mkdir($runtimePath, 0755, true)) {
                $this->output->writeln("<error>Write without permission $runtimePath</error>");
                
                return;
            }
        }
        
        // ??????Worker????????????
        Worker::$pidFile    = $runtimePath . 'run.pid';
        Worker::$logFile    = $runtimePath . 'run.log';
        Worker::$stdoutFile = $runtimePath . 'stdout.log';
        if ($this->getInputOption('daemon', $this->getWorkerConfig('daemonize', false))) {
            Worker::$daemonize = true;
        }
        
        if (DIRECTORY_SEPARATOR == '\\') {
            $this->output->writeln('You can exit with <info>`CTRL-C`</info>');
        }
        
        Worker::runAll();
    }
    
    
    /**
     * ??????HTTP?????????
     */
    protected function startHttpServer()
    {
        // ???????????????
        $option          = $this->getWorkerConfig('http.option') ?: [];
        $option['count'] = $this->getWorkerConfig('http.worker_num');
        $option['name']  = 'BusyPHP Http Server';
        
        // ????????????HTTPS
        if ($this->getWorkerConfig('http.ssl')) {
            $option['transport'] = 'ssl';
        }
        
        // ???????????????
        $host    = $this->getInputOption('host', $this->getWorkerConfig('http.host')) ?: '0.0.0.0';
        $port    = $this->getInputOption('port', $this->getWorkerConfig('http.port')) ?: '2346';
        $context = $this->getWorkerConfig('http.context') ?: [];
        $server  = new HttpServer($host, $port, $context, $option);
        $server->setWebPath($this->app->getPublicPath());   // ?????????????????????
        $server->setRootPath($this->app->getRootPath());    // ?????????????????????
        
        // ????????????
        if (($appInit = $this->getWorkerConfig('http.app_init', '')) instanceof Closure) {
            $server->setAppInit($appInit);
        }
        
        // ??????????????????
        if (DIRECTORY_SEPARATOR !== '\\' && $this->getWorkerConfig('http.hot_update.enable', false)) {
            $interval = $this->getWorkerConfig('http.hot_update.interval') ?: 2;
            $paths    = $this->getWorkerConfig('http.hot_update.include') ?: [];
            $server->setMonitor($interval, $paths);
        }
    }
    
    
    /**
     * ?????????????????????
     */
    protected function startUseServer(string $name)
    {
        $class = $this->getWorkerConfig("server.$name", '');
        if (!$class || !class_exists($class)) {
            throw new ClassNotFoundException($class);
        }
        
        if (!is_subclass_of($class, BaseServer::class)) {
            throw new ClassNotExtendsException($class, BaseServer::class);
        }
        
        /** @var BaseServer $server */
        $server = new $class;
        $server->setOption(['name' => "BusyPHP $name Server"]);
        $server->setRootPath($this->app->getRootPath());
        $server->setWebPath($this->app->getPublicPath());
    }
    
    
    /**
     * ????????????????????????
     * @param string $name
     * @return string
     */
    protected function getRegisterAddress(string $name) : string
    {
        return $this->getGatewayConfig($name, 'register.address', '');
    }
    
    
    /**
     * ??????????????????
     * @param string $name
     */
    protected function startRegisterWorker(string $name)
    {
        $registerWorker       = new Register("text://{$this->getRegisterAddress($name)}");
        $registerWorker->name = "BusyPHP $name Register Server";
    }
    
    
    /**
     * ??????BusinessWorker
     * @param string $name
     */
    protected function startBusinessWorker(string $name)
    {
        $businessWorker                  = new BusinessWorker();
        $businessWorker->name            = "BusyPHP $name Business Server";
        $businessWorker->registerAddress = $this->getRegisterAddress($name);
        $businessWorker->eventHandler    = $this->getGatewayConfig($name, 'business.handler', '') ?: GatewayEvents::class;
        $businessWorker->count           = max($this->getGatewayConfig($name, 'business.worker_num', 0), 1);
    }
    
    
    /**
     * ??????GatewayWorker
     * @param string $name
     */
    protected function startGatewayWorker(string $name)
    {
        $url = $this->getGatewayConfig($name, 'gateway.socket');
        if (!$url) {
            $protocol = $this->getGatewayConfig($name, 'gateway.protocol') ?: 'websocket';
            $host     = $this->getInputOption('host', $this->getGatewayConfig($name, 'gateway.host'));
            $port     = $this->getInputOption('port', $this->getGatewayConfig($name, 'gateway.port'));
            $url      = "$protocol://$host:$port";
        }
        
        $gatewayWorker                       = new Gateway($url, $this->getGatewayConfig($name, 'gateway.context') ?: []);
        $gatewayWorker->registerAddress      = $this->getRegisterAddress($name);
        $gatewayWorker->name                 = "BusyPHP $name Gateway Server";
        $gatewayWorker->count                = max($this->getGatewayConfig($name, 'gateway.worker_num'), 1);
        $gatewayWorker->lanIp                = $this->getGatewayConfig($name, 'gateway.lan_ip') ?: '127.0.0.1';
        $gatewayWorker->startPort            = $this->getGatewayConfig($name, 'gateway.start_port') ?: 2000;
        $gatewayWorker->pingInterval         = $this->getGatewayConfig($name, 'gateway.ping.interval', 0);
        $gatewayWorker->pingNotResponseLimit = $this->getGatewayConfig($name, 'gateway.ping.limit', 0);
        $gatewayWorker->pingData             = $this->getGatewayConfig($name, 'gateway.ping.data', '');
        
        // ??????ssl
        if ($this->getGatewayConfig($name, 'gateway.ssl')) {
            $gatewayWorker->transport = 'ssl';
        }
    }
    
    
    /**
     * ????????????
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function getInputOption(string $key, $default = null)
    {
        if ($this->input->hasOption($key)) {
            return $this->input->getOption($key);
        } else {
            return $default;
        }
    }
}
