<?php

namespace PhalApi\AliyunOss;

use OSS\OssClient;
use OSS\Core\OssException;

class Lite
{
    protected $config;

    protected $client;

    public function __construct($config = null)
    {
        $di = \PhalApi\DI();
        $this->config = $config;
        if (is_null($this->config)) {
            $this->config = $di->config->get('app.AliyunOss');
        }
        $accessKeyId = $this->config['accessKeyId'];
        $accessKeySecret = $this->config['accessKeySecret'];
        $endpoint = $this->config['endpoint'];
        $isCName = $this->config['isCName'];
        $securityToken = $this->config['securityToken'];
        $requestProxy = $this->config['requestProxy'];
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint, $isCName, $securityToken, $requestProxy);
            $ossClient->setTimeout(3600); // 设置Socket层传输数据的超时时间，单位秒，默认5184000秒。
            $ossClient->setConnectTimeout(10); // 设置建立连接的超时时间，单位秒，默认10秒。
            $this->client = $ossClient;
        } catch (OssException $e) {
            $di->logger->error($e->getMessage());
        }
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function uploadFile($bucket, $object, $filePath, $options = null)
    {
        $di = \PhalApi\DI();
        if (!file_exists($filePath)) {
            $di->logger->error('AliyunOss # uploadFile # file not exists', $filePath);

            return false;
        }
        if (!$this->client->doesBucketExist($bucket)) {
            $di->logger->error('AliyunOss # uploadFile # Bucket not exists', $bucket);

            return false;
        }
        try {
            $res = $this->client->uploadFile($bucket, $object, $filePath, $options);

            return $res;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # uploadFile', $e->getMessage());

            return false;
        }
    }

    public function multiuploadFile($bucket, $object, $filePath, $options = null)
    {
        $di = \PhalApi\DI();
        if (!file_exists($filePath)) {
            $di->logger->error('AliyunOss # multiuploadFile # file not exists', $filePath);

            return false;
        }
        if (!$this->client->doesBucketExist($bucket)) {
            $di->logger->error('AliyunOss # multiuploadFile # Bucket not exists', $bucket);

            return false;
        }
        if (empty($options)) {
            $options = array(
                OssClient::OSS_CHECK_MD5 => true,
                OssClient::OSS_PART_SIZE => 1,
            );
        }
        try {
            $res = $this->client->multiuploadFile($bucket, $object, $filePath, $options);

            return $res;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # multiuploadFile', $e->getMessage());

            return false;
        }
    }

    public function copyObject($fromBucket, $fromObject, $toBucket, $toObject)
    {
        $di = \PhalApi\DI();
        if (!$this->client->doesBucketExist($fromBucket)) {
            $di->logger->error('AliyunOss # copyObject # Bucket not exists', $fromBucket);

            return false;
        }
        if (!$this->client->doesObjectExist($fromBucket, $fromObject)) {
            $di->logger->error('AliyunOss # copyObject # file not exists', $fromObject);

            return false;
        }
        if (empty($toBucket)) {
            $toBucket = $fromBucket;
        } else {
            if (!$this->client->doesBucketExist($toBucket)) {
                $di->logger->error('AliyunOss # copyObject # dst Bucket not exists', $toBucket);

                return false;
            }
        }
        try {
            $res = $this->client->copyObject($fromBucket, $fromObject, $toBucket, $toObject);

            return $res;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # copyObject', $e->getMessage());

            return false;
        }
    }

    public function uploadPartCopy($fromBucket, $fromObject, $toBucket, $toObject)
    {
        $di = \PhalApi\DI();
        if (!$this->client->doesBucketExist($fromBucket)) {
            $di->logger->error('AliyunOss # uploadPartCopy # Bucket not exists', $fromBucket);

            return false;
        }
        if (!$this->client->doesObjectExist($fromBucket, $fromObject)) {
            $di->logger->error('AliyunOss # uploadPartCopy # file not exists', $fromObject);

            return false;
        }
        if (empty($toBucket)) {
            $toBucket = $fromBucket;
        } else {
            if (!$this->client->doesBucketExist($toBucket)) {
                $di->logger->error('AliyunOss # uploadPartCopy # dst Bucket not exists', $toBucket);

                return false;
            }
        }
        try {
            // 初始化分片
            $uploadId = $this->client->initiateMultipartUpload($toBucket, $toObject);
            $copyId = 1;
            // 逐个分片拷贝
            $eTag = $this->client->uploadPartCopy($fromBucket, $fromObject, $toBucket, $toObject, $copyId, $uploadId);
            $upload_parts[] = array(
                'PartNumber' => $copyId,
                'ETag' => $eTag,
            );
            // 完成分片拷贝
            $result = $this->client->completeMultipartUpload($toBucket, $toObject, $uploadId, $upload_parts);

            return $result;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # uploadPartCopy', $e->getMessage());

            return false;
        }
    }

    public function uploadPart($bucket, $object, $filePath)
    {
        $di = \PhalApi\DI();
        if (!file_exists($filePath)) {
            $di->logger->error('AliyunOss # uploadPart # file not exists', $filePath);

            return false;
        }
        if (!$this->client->doesBucketExist($bucket)) {
            $di->logger->error('AliyunOss # uploadPart # Bucket not exists', $bucket);

            return false;
        }
        try {
            /**
             *  步骤1：初始化一个分片上传事件，获取uploadId。
             */
            $uploadId = $this->client->initiateMultipartUpload($bucket, $object);
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # uploadPart # initiateMultipartUpload FAILED', $e->getMessage());

            return false;
        }
        /*
         * 步骤2：上传分片。
         */
        $partSize = 10 * 1024 * 1024;
        $uploadFileSize = filesize($filePath);
        $pieces = $this->client->generateMultiuploadParts($uploadFileSize, $partSize);
        $responseUploadPart = array();
        $uploadPosition = 0;
        $isCheckMd5 = true;
        foreach ($pieces as $i => $piece) {
            $fromPos = $uploadPosition + (int) $piece[OssClient::OSS_SEEK_TO];
            $toPos = (int) $piece[OssClient::OSS_LENGTH] + $fromPos - 1;
            $upOptions = array(
                OssClient::OSS_FILE_UPLOAD => $filePath,
                OssClient::OSS_PART_NUM => ($i + 1),
                OssClient::OSS_SEEK_TO => $fromPos,
                OssClient::OSS_LENGTH => $toPos - $fromPos + 1,
                OssClient::OSS_CHECK_MD5 => $isCheckMd5,
            );
            // MD5校验
            if ($isCheckMd5) {
                $contentMd5 = OssUtil::getMd5SumForFile($filePath, $fromPos, $toPos);
                $upOptions[OssClient::OSS_CONTENT_MD5] = $contentMd5;
            }
            // 上传分片
            try {
                $responseUploadPart[] = $this->client->uploadPart($bucket, $object, $uploadId, $upOptions);
            } catch (OssException $e) {
                $di->logger->error("AliyunOss # uploadPart # part#{$i} FAILED", $e->getMessage());

                return;
            }
        }
        // $uploadParts是由每个分片的ETag和分片号（PartNumber）组成的数组。
        $uploadParts = array();
        foreach ($responseUploadPart as $i => $eTag) {
            $uploadParts[] = array(
                'PartNumber' => ($i + 1),
                'ETag' => $eTag,
            );
        }
        /*
         * 步骤3：完成上传。
         */
        try {
            // 在执行该操作时，需要提供所有有效的$uploadParts。OSS收到提交的$uploadParts后，会逐一验证每个分片的有效性。当所有的数据分片验证通过后，OSS将把这些分片组合成一个完整的文件。
            $result = $this->client->completeMultipartUpload($bucket, $object, $uploadId, $uploadParts);

            return $result;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # uploadPart # completeMultipartUpload FAILED', $e->getMessage());

            return false;
        }
    }

    public function putObject($bucket, $object, $content, $options = null)
    {
        $di = \PhalApi\DI();
        if (empty($content)) {
            $di->logger->error('AliyunOss # putObject # content is empty');

            return false;
        }
        if (!$this->client->doesBucketExist($bucket)) {
            $di->logger->error('AliyunOss # putObject # Bucket not exists', $bucket);

            return false;
        }
        try {
            $res = $this->client->putObject($bucket, $object, $content, $options);

            return $res;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # putObject', $e->getMessage());

            return false;
        }
    }

    public function listObjects($bucket, $prefix = '', $delimiter = '/', $maxkeys = 100, $nextMarker = '')
    {
        $di = \PhalApi\DI();
        if (!$this->client->doesBucketExist($bucket)) {
            $di->logger->error('AliyunOss # listObjects # Bucket not exists', $bucket);

            return false;
        }
        $options = array(
            'delimiter' => $delimiter,
            'prefix' => $prefix,
            'max-keys' => $maxkeys,
            'marker' => $nextMarker,
        );
        try {
            $listObjectInfo = $this->client->listObjects($bucket, $options);
            $objectList = $listObjectInfo->getObjectList();
            $prefixList = $listObjectInfo->getPrefixList();
            $objects = array();
            $prefixs = array();
            if (!empty($objectList)) {
                foreach ($objectList as $objectInfo) {
                    array_push($objects, $objectInfo->getKey());
                }
            }
            if (!empty($prefixList)) {
                foreach ($prefixList as $prefixInfo) {
                    array_push($prefixs, $prefixInfo->getPrefix());
                }
            }

            return array(
                'object' => $objects,
                'directory' => $prefixs,
            );
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # listObjects', $e->getMessage());

            return false;
        }
    }

    public function deleteObject($bucket, $object)
    {
        $di = \PhalApi\DI();
        if (!$this->client->doesBucketExist($bucket)) {
            $di->logger->error('AliyunOss # deleteObject # Bucket not exists', $bucket);

            return false;
        }
        if (!$this->client->doesObjectExist($bucket, $object)) {
            $di->logger->error('AliyunOss # deleteObject # file not exists', $object);

            return true;
        }
        try {
            $res = $this->client->deleteObject($bucket, $object);

            return $res;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # deleteObject', $e->getMessage());

            return false;
        }
    }

    public function deleteObjects($bucket, $objects)
    {
        $di = \PhalApi\DI();
        try {
            $res = $this->client->deleteObjects($bucket, $objects);

            return $res;
        } catch (OssException $e) {
            $di->logger->error('AliyunOss # deleteObjects', $e->getMessage());

            return false;
        }
    }
}
