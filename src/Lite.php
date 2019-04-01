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

    public function copyObject($bucket, $from_object, $to_object)
    {
        if (!$this->client->doesObjectExist($bucket, $from_object)) {
            \PhalApi\DI()->logger->error('AliyunOss \ file not exists', $from_object);
            return false;
        }
        try{
            $res = $this->client->copyObject($bucket, $from_object, $object, $to_object);
            return $res;
        } catch(OssException $e) {
            \PhalApi\DI()->logger->error('AliyunOss \ copyObject', $e->getMessage());
            return false;
        }
    }

    public function uploadPartCopy($bucket, $src_object, $dst_object)
    {
        if (!$this->client->doesObjectExist($bucket, $src_object)) {
            \PhalApi\DI()->logger->error('AliyunOss \ file not exists', $from_object);
            return false;
        }
        try{
            // 初始化分片。
            $upload_id = $this->client->initiateMultipartUpload($bucket, $dst_object);
            $copyId = 1;
            // 逐个分片拷贝。
            $eTag = $this->client->uploadPartCopy($bucket, $src_object, $bucket, $dst_object, $copyId, $upload_id);
            $upload_parts[] = array(
                'PartNumber' => $copyId,
                'ETag' => $eTag,
            );
            // 完成分片拷贝。
            $result = $this->client->completeMultipartUpload($bucket, $dst_object, $upload_id, $upload_parts);
            return $result;
        } catch(OssException $e) {
            \PhalApi\DI()->logger->error('AliyunOss \ uploadPartCopy', $e->getMessage());
            return false;
        }
    }

    public function listObjects($bucket, $prefix = '', $delimiter = '/', $maxkeys = 100, $nextMarker = '')
    {
        $options = array(
            'delimiter' => $delimiter,
            'prefix' => $prefix,
            'max-keys' => $maxkeys,
            'marker' => $nextMarker,
        );
        try{
            $listObjectInfo = $this->client->listObjects($bucket, $options);
            $objectList = $listObjectInfo->getObjectList(); // object list
            $prefixList = $listObjectInfo->getPrefixList(); // directory list
            return array(
                'object' => $objectList,
                'directory' => $prefixList
            );
        } catch(OssException $e) {
            \PhalApi\DI()->logger->error('AliyunOss \ listObjects', $e->getMessage());
            return false;
        }
    }

    public function deleteObject($bucket, $object)
    {
        try{
            $res = $this->client->deleteObject($bucket, $object);
            return $res;
        } catch(OssException $e) {
            \PhalApi\DI()->logger->error('AliyunOss \ deleteObject', $e->getMessage());
            return false;
        }
    }

    public function deleteObjects($bucket, $objects)
    {
        try{
            $res = $this->client->deleteObjects($bucket, $objects);
            return $res;
        } catch(OssException $e) {
            \PhalApi\DI()->logger->error('AliyunOss \ deleteObjects', $e->getMessage());
            return false;
        }
    }
}
