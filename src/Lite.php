<?php
namespace PhalApi\AliyunOss;

use OSS\OssClient;
use OSS\Core\OssException;

class Lite {

    protected $config;

    protected $client;

    public function __construct($config = NULL) {
        $this->config = $config;
        if ($this->config === NULL) {
            $this->config = \PhalApi\DI()->config->get('app.AliyunOss');
        }
        $accessKeyId        = $this->config['accessKeyId'];
        $accessKeySecret    = $this->config['accessKeySecret'];
        $endpoint           = $this->config['endpoint'];
        $isCName            = $this->config['isCName'];
        $securityToken      = $this->config['securityToken'];
        $requestProxy       = $this->config['requestProxy'];
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName, $securityToken, $requestProxy);
            $ossClient->setTimeout(3600); // 设置Socket层传输数据的超时时间，单位秒，默认5184000秒。            
            $ossClient->setConnectTimeout(10); // 设置建立连接的超时时间，单位秒，默认10秒。
            $this->client = $ossClient;
        } catch (OssException $e) {
            \PhalApi\DI()->logger->error($e->getMessage());
        }
    }

    public function getClient() {
        return $this->client;
    }

    public function getConfig() {
        return $this->config;
    }

    public function uploadFile($bucket, $object, $filePath)
    {
        if (!file_exists($filePath)) {
            \PhalApi\DI()->logger->error('AliyunOss \ file not exists', $filePath);
            return false;
        }
        try{
            $res = $this->client->uploadFile($bucket, $object, $filePath);
            return $res;
        } catch(OssException $e) {
            \PhalApi\DI()->logger->error('AliyunOss \ uploadFile', $e->getMessage());
            return false;
        }
    }
}
