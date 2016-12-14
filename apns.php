<?php

/**
 * Created by PhpStorm.
 * User: bjd
 * Date: 2016/10/27
 * Time: 17:36
 */
class Apns
{

    const ENVIRONMENT_SANDBOX = 0;
    const ENVIRONMENT_PRODUCTION = 1;

    protected $_nEnvironment;
    protected $_aHTTPServiceURLs = array(
        'https://api.development.push.apple.com:443', // Sandbox environment
        'https://api.push.apple.com:443', // Production environment
    );

    protected $_aHTTPErrorResponseMessages = array(
        200 => 'Success',
        400 => 'Bad request',
        403 => 'There was an error with the certificate',
        405 => 'The request used a bad :method value. Only POST requests are supported',
        410 => 'The device token is no longer active for the topic',
        413 => 'The notification payload was too large',
        429 => 'The server received too many requests for the same device token',
        500 => 'Internal server error',
        503 => 'The server is shutting down and unavailable',
        0 => 'Internal error',
    );


    protected $_sProviderCertificateFile;
    protected $_spassword;
    private $_topic;
    private $_payload;

    private $_mh = null;
    private $_chs = array();
    private $_threads = 2;


    public function __construct($nEnvironment)
    {
        $this->_nEnvironment = $this->_aHTTPServiceURLs[$nEnvironment];
    }

    public function setCertFile($file, $spassword = '')
    {
        $this->_sProviderCertificateFile = $file;
        $this->_spassword = $spassword;
        return $this;
    }

    public function setTopic($topic)
    {
        $this->_topic = $topic;
        return $this;
    }

    public function setPayload($payload)
    {
        $this->_payload = json_encode($payload);
        return $this;
    }

    public function setThreads($threads)
    {
        $this->_threads = $threads;
    }

    public function connect()
    {
        if (empty($this->_nEnvironment) || empty($this->_sProviderCertificateFile)
            || empty($this->_topic))
        ) {
            die("init check error");
        }

        $this->_mh = curl_multi_init();//创建多个curl语柄

        if (!$this->_mh) {
            die("curl multi init error");
        }

        for ($i = 0; $i < $this->_threads; $i++) {
            $this->_initCurl($i);//创建多个curl语柄
        }
    }

    public function close()
    {
        foreach ($this->_chs as $ch) {
            curl_close($ch);
            curl_multi_remove_handle($this->_mh, $ch);   //释放资源
        }
        $this->_chs = [];
        curl_multi_close($this->_mh);
    }

    public function send($datas)
    {
        $startime = getmicrotime();
        $reslut = [];
        $i = 0;
        foreach ($datas as $data) {
            $i++;
            $res[] = $data;
            if ($i % $this->_threads == 0) {
                $reslut = array_merge($reslut, $this->_muitSend($res));
                $res = [];
            }
        }

        if (!empty($res)) {
            $this->close();
            $this->connect();
            $reslut = array_merge($reslut, $this->_muitSend($res));
        }

        $diff_time = getmicrotime() - $startime;

        return array(
            'diff_time' => $diff_time,
            'reslut' => $reslut,
        );
    }

    private function _muitSend($datas)
    {
        // $data  => token,id
        $datas = array_values($datas);
        foreach ($datas as $k => $data) {
            $this->_updateCurl($k, $data);
        }

        $running = null;
        // 执行批处理句柄
        do {
//            usleep(1000);
            curl_multi_exec($this->_mh, $running);
            curl_multi_select($this->_mh);
        } while ($running > 0);

        $reslut = array();
        foreach ($datas as $k => $data) {
            $res = array();
            $res['code'] = 0;
            $res['id'] = $data['id'];
            $res['token'] = $data['token'];

            $err = curl_error($this->_chs[$k]);
            if (!empty($err)) {
                $res['err'] = $err;
                $reslut[] = $res;
                continue;
            }

            $res['code'] = curl_getinfo($this->_chs[$k], CURLINFO_HTTP_CODE);//返回头信息
            $res['reason'] = $this->_aHTTPErrorResponseMessages[$res['code']];
            if ($res['code'] == 200) {
                $reslut[] = $res;
                continue;
            }

            $res['content'] = curl_multi_getcontent($this->_chs[$k]);//获得返回信息

            $reslut[] = $res;
        }

        return $reslut;
    }

    function _initCurl($k)
    {
        if (!empty($this->_chs[$k])) {
            return;
        }

        $this->_chs[$k] = curl_init();
        if (!$this->_chs[$k]) {
            die("curl $k init error");
        }

        $setopt = curl_setopt_array($this->_chs[$k],
            array(
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                CURLOPT_SSLCERT => $this->_sProviderCertificateFile,
                CURLOPT_SSLCERTPASSWD => empty($this->_spassword) ? null : $this->_spassword,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'ApnsPHP',
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_VERBOSE => false,
                CURLOPT_MAXREDIRS => 7,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_POST => true,
            )
        );
        if (!$setopt) {
            die("curl $k setopt error");
        }

        $res = curl_multi_add_handle($this->_mh, $this->_chs[$k]);
        if ($res != 0) {
            die("curl $k add_handle error($res)");
        }


    }

    private function _updateCurl($k, $data)
    {
        $headers = array(
            'Content-Type: application/json',
            'apns-topic: ' . $this->_topic,
        );
        $setopt = curl_setopt_array($this->_chs[$k],
            array(
                CURLOPT_URL => sprintf('%s/3/device/%s', $this->_nEnvironment, $data['token']),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $this->_payload,
            )
        );
        if (!$setopt) {
            die("curl $k setopt_update error");
        }

    }

}


//计算当前时间
function getmicrotime()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
