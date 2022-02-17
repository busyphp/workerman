<?php
namespace BusyPHP\workerman\command;

use BusyPHP\workerman\WithConfig;
use Closure;
use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;
use BusyPHP\workerman\HttpServer;
use Workerman\Worker as WorkerManWorker;


/**
 * Http服务类命令行类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2022 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2022/2/17 2:23 PM Worker.php $
 */
class HttpWorker extends Command
{
    use WithConfig;
    
    protected $config = [];
    
    
    public function configure()
    {
        $this->setName('bp:workerman:http')
            ->addArgument('action', Argument::OPTIONAL, "start|stop|restart|reload|status|connections", 'start')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'the host of WorkerMan server.', null)
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'the port of WorkerMan server.', null)
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run the WorkerMan server in daemon mode.')
            ->setDescription('Workerman HTTP Server for BusyPHP');
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
        
        // 服务器参数
        $option          = $this->getWorkerConfig('http.option') ?: [];
        $option['count'] = $this->getWorkerConfig('http.worker_num');
        $option['name']  = 'BusyPHP WorkerMan Http Server';
        
        // 是否启用HTTPS
        if ($this->getWorkerConfig('http.ssl')) {
            $option['transport'] = 'ssl';
        }
        
        // 实例服务器
        $host    = $this->getInputOption('host', $this->getWorkerConfig('http.host')) ?: '0.0.0.0';
        $port    = $this->getInputOption('port', $this->getWorkerConfig('http.port')) ?: '2346';
        $context = $this->getWorkerConfig('http.context') ?: [];
        $server  = new HttpServer($host, $port, $context, $option);
        $server->setRoot($this->app->getPublicPath());   // 设置应用根目录
        $server->setRootPath($this->app->getRootPath()); // 设置系统根目录
        
        // 应用设置
        if (($appInit = $this->getWorkerConfig('http.app_init', '')) instanceof Closure) {
            $server->appInit($appInit);
        }
        
        // 设置文件监控
        if (DIRECTORY_SEPARATOR !== '\\' && $this->getWorkerConfig('http.hot_update.enable', false)) {
            $interval = $this->getWorkerConfig('http.hot_update.interval') ?: 2;
            $paths    = $this->getWorkerConfig('http.hot_update.include') ?: [];
            $server->setMonitor($interval, $paths);
        }
        
        // 设置Worker静态属性
        WorkerManWorker::$pidFile = $this->app->getRuntimeRootPath("workerman-worker-{$port}.pid");
        WorkerManWorker::$logFile = $this->app->getRuntimeRootPath("workerman-log-{$port}.log");
        if ($stdoutFile = $this->getWorkerConfig('http.stdout_file')) {
            WorkerManWorker::$stdoutFile = $stdoutFile;
        }
        if ($this->getInputOption('daemon', $this->getWorkerConfig('http.daemonize', false))) {
            WorkerManWorker::$daemonize = true;
        }
        
        if (DIRECTORY_SEPARATOR == '\\') {
            $this->output->writeln('You can exit with <info>`CTRL-C`</info>');
        }
        
        $server->start();
    }
    
    
    /**
     * 获取参数
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
