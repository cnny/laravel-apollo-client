<?php

namespace Cann\Apollo\Commands;

use Cann\Apollo\Client;
use Illuminate\Console\Command;

class ClientStart extends Command
{
    protected $signature = 'apollo-client:start {url} {appId} {cluster?}
        {--useCache}
        {--notifications=}
        {--envNamespace=}';

    public function handle()
    {
        $serviceUrl = $this->argument('url');
        $appId      = $this->argument('appId');
        $cluster    = $this->argument('cluster');

        $client = new Client($serviceUrl, $appId, $cluster);

        // 设置需要监听的 namespaces， 逗号分隔
        $client->setNotifications(explode(',', $this->option('notifications')));

        // .env 对应的 Namespace，默认 env.properties
        if ($envNamespace = $this->option('envNamespace')) {
            $client->setEnvNamespace($envNamespace);
        }

        // 是否需要缓存
        if ($useCache = $this->option('useCache')) {
            $client->setUseCache(boolval($useCache));
        }

        $client->start();
    }
}
