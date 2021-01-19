<?php

namespace Cann\Apollo;

use Cache;
use Cann\Apollo\Helpers\ApiHelper;
use Cann\Apollo\Helpers\ArrayHelper;

class Client
{
    protected $serviceUrl;         // apollo 服务端地址
    protected $appId;              // apollo 配置项目的appid
    protected $appSecret     = ''; // 秘钥，为空则不验签
    protected $cluster;            // 集群
    protected $clientIp      = '127.0.0.1'; // 绑定IP做灰度发布用
    protected $envNamespace  = '';
    protected $useCache      = false;
    protected $notifications = [];
    protected $releaseKeys   = [];

    public function __construct(string $serviceUrl, string $appId, string $cluster = 'default')
    {
        $this->appId      = $appId;
        $this->serviceUrl = $serviceUrl;
        $this->cluster    = $cluster ?: 'default';
    }

    public function setClientIp(string $ip)
    {
        $this->clientIp = $ip;

        return $this;
    }

    public function setUseCache(bool $useCache)
    {
        $this->useCache = $useCache;

        return $this;
    }

    public function setEnvNamespace(string $namespace)
    {
        $this->envNamespace = $namespace;

        return $this;
    }

    public function setNotifications(array $namespaces)
    {
        // 需要监听的 namespace
        foreach ($namespaces as $namespace) {
            $this->notifications[$namespace] = self::buildNotification($namespace);
        }

        return $this;
    }

    // 监听变更
    public function start()
    {
        // 默认监听 Env Namespace
        if ($this->envNamespace) {
            $this->notifications[$this->envNamespace] = self::buildNotification($this->envNamespace);
        }

        while (true) {

            $url = $this->serviceUrl . '/notifications/v2';

            $params = [
                'appId'         => $this->appId,
                'cluster'       => $this->cluster,
                'notifications' => json_encode(array_values($this->notifications))
            ];

            $response = ApiHelper::guzHttpRequest($url, $params, 'GET');

            if (! $response) {
                continue;
            }

            foreach ($response as $oneChange) {

                $notification = self::buildNotification($oneChange['namespaceName'], $oneChange['notificationId']);

                $this->notifications[$oneChange['namespaceName']] = $notification;
            }

            $this->syncConfigToLocal(\Arr::pluck($response, 'namespaceName'));
        }
    }

    //获取单个 namespace 的配置-无缓存的方式
    protected function pullConfig($namespace)
    {
        $url = $this->serviceUrl . '/configs/' . $this->appId . '/' . $this->cluster . '/' . $namespace;

        $params = [
            'ip'         => $this->clientIp,
            'releaseKey' => $this->releaseKeys[$namespace] ?? '',
        ];

        $response = ApiHelper::guzHttpRequest($url, $params, 'GET');

        if (! $response) {
            return [];
        }

        $this->releaseKeys[$namespace] = $response['releaseKey'];

        return $response['configurations'];
    }

    // 同步 Apollo 配置至本地
    protected function syncConfigToLocal(array $namespaces)
    {
        foreach ($namespaces as $namespace) {

            $config = $this->pullConfig($namespace);

            if ($namespace == $this->envNamespace) {
                $this->saveEnv($config);
            }

            else {
                $this->saveConfig($namespace, $config);
            }
        }
    }

    // 同步 .env 配置
    protected function saveEnv(array $envs)
    {
        if (file_exists(base_path('.env'))) {
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
            $envs = array_merge($dotenv->load(), $envs);
        }

        $content = '';

        foreach ($envs as $key => $value) {
            $content .= $key . '="' . $value . '"' . PHP_EOL;
        }

        file_put_contents(base_path('.env'), $content);

        $this->clearConfigCache();
    }

    // 同步 .env 配置
    protected function saveConfig(string $namespace, array $config)
    {
        $orgConfig = config('apollo', []);

        $orgConfig[$namespace] = ArrayHelper::jsonToArray($config);

        $content = '<?php' . PHP_EOL . 'return ' . var_export($orgConfig, true) . ';';

        file_put_contents(config_path('apollo.php'), $content);

        $this->clearConfigCache();
    }

    // 清除配置缓存
    protected function clearConfigCache()
    {
        // 清除配置缓存
        \Artisan::call('config:clear');

        // 重新生成配置缓存
        if ($this->useCache) {
            \Artisan::call('config:cache');
        }
    }

    protected static function buildNotification(string $namespace, int $notificationId = -1)
    {
        return [
            'namespaceName'  => $namespace,
            'notificationId' => $notificationId,
        ];
    }
}
