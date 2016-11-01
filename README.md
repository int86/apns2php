# apns2php

## Introduction
apns2php implemente the HTTP/2 Protocol by PHP curl_multi

## Notice
* PHP>=5.5.24 提供对HTTP/2的支持
* libcurl需要 >=7.38.0
* openssl >=1.0.2
* Nghttp2


## Example

``` php

    $push = new Apns(Apns::ENVIRONMENT_PRODUCTION);
    $push->setCertFile($pem);
    $push->setTopic($topic);
    
    $playload = array(
        'aps' => array(
            'alert' => 'alert',
            "badge" => 1,
            "sound" => "default",
        ),
    );
    $push->setPayload($playload);
    $push->setThreads(2);
    $push->connect();
    $datas[] = [
        'token' => 'token',//apns token
        'id' => 'id', // 自定义id
    ];    
    $result = $push->send($datas);
    var_dump($result);
    
```


## Test

* 单机8核16g阿里云vps
* CentOS release 6.5 (Final) (2.6.32-573.18.1.el6.x86_64)
* PHP=7.0.12
* OpenSSL/1.1.0b [下载](https://www.openssl.org/source/openssl-1.1.0b.tar.gz)
* libcurl/7.50.3 [下载](https://curl.haxx.se/download/curl-7.50.3.tar.gz)
* nghttp2/1.16.0 [下载](https://github.com/nghttp2/nghttp2/releases/download/v1.16.0/nghttp2-1.16.0.tar.gz)
* 开启线程数 `40`

线上实测，每秒推送`50000`条














