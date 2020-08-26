<?php
namespace Kabucom;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Kabus
{
    public $name = "ueda";
    public $password = "qwerty";
    public $apikey = "a68e2d55951b4756a3131f7942974eeb";
    public $mode = "dev";
    public $port = "18081";
    public $log;

    function __construct($mode)
    {
        if ($mode == "pub") {
            $this->mode = "pub";
            $this->port = "18080";
        }
        $this->log = new Logger('app');
        $this->log->pushHandler(new StreamHandler('./log/error.log', Logger::WARNING));
        $this->getToken();
    }

    public function getName() {
        echo $this->name;
    }

    // 時価情報・板情報
    public function getsymbol($symbol, $exchange) {
        $param = "/kabusapi/symbol/" . $symbol . "@" . $exchange;
        $response = $this->sendApi($param);

        return $response;
    }

    // トークン発行
    public function getToken() {
        $url = "http://localhost:" . $this->port . "/kabusapi/token";
        $data = array('APIPassword' => $this->password);
        $data = http_build_query($data);
        $opts = array(
            'http' => array(
                'method' => "POST",
                'header'=> "Content-type: application/json\r\n"
                . "Content-Length: " . strlen($data) . "\r\n",
                'content' => $data
            )
        );
        $context = stream_context_create($opts);
        $json = file_get_contents($url, FALSE, $context);
        if (!$json) {
            $this->log->warning("API URLが読み込めない");
            return false;
        }

        $response = json_decode($json);
        if ($response['ResultCode'] === 0) {
            $this->apikey = $response['Token'];
        } else {
            $this->log->warning("APIトークン発行に失敗しました");
            exit;
        }

        return true;
    }

    public function sendApi($param) {
        $url = "http://localhost:" . $this->port . $param;
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header'=> "Content-type: application/json\r\n"
                . "X-API-KEY: " . $this->apikey 
            )
        );
        $context = stream_context_create($opts);
        usleep(100000);// 0.1秒待つ
        $json = file_get_contents($url, FALSE, $context);

        if (!$json) {
            $this->log->warning("API URLが読み込めない");
            return false;
        }

        $response = json_decode($json);
        return $response;
    }
}

