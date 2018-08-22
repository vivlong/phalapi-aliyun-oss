# 阿里云OSS扩展
PhalApi 2.x扩展类库，基于Aliyun的OSS扩展。

## 安装和配置
修改项目下的composer.json文件，并添加：  
```
    "phalapi/aliyun-oss":"dev-master"
```
然后执行```composer update```。  

安装成功后，添加以下配置到/path/to/phalapi/config/app.php文件：  
```php
    /**
     * 阿里云OSS相关配置
     */
    'AliyunOss' =>  array(
        'accessKeyId'       => '<yourAccessKeyId>',
        'accessKeySecret'   => '<yourAccessKeySecret>',
        'bucket'            => '<yourBucketName>',
        'endpoint'          => 'http://oss-cn-hangzhou.aliyuncs.com',
        'isCName'           => false,
        'securityToken'     => null,
        'requestProxy'      => null,
    ),
```
并根据自己的情况修改填充。 

## 注册
在/path/to/phalapi/config/di.php文件中，注册：  
```php
$di->aliyunOss = function() {
        return new \PhalApi\AliyunOss\Lite();
};
```

## 使用
第一种使用方式：上传本地文件：
```php
  \PhalApi\DI()->aliyunOss->uploadFile($bucket, $object, $filePath);
```

第二种使用方式：或者，直接使用已经提供的默认上传接口。在composer.json中追加配置：
```
"autoload": {
    "psr-4": {
        "AliyunOss\\": "vendor/phalapi/AliyunOss/src/oss"
    }
}
```  

