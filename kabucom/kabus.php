<?php
namespace Kabucom;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Kabus
{
    public $name = "ueda";
    public $password = DEV_API_PASSWORD;
    public $order_password = ORDER_PASSWORD;
    public $apikey = "a68e2d55951b4756a3131f7942974eeb";
    public $mode = "dev";
    public $port = "18081";
    public $log;

    function __construct($mode)
    {
        if ($mode == "pub") {
            $this->mode = "pub";
            $this->port = "18080";
            $this->password = PUB_API_PASSWORD;
        }
        $this->log = new Logger('api');
        $this->log->pushHandler(new StreamHandler('./log/error.log', Logger::WARNING));
		$this->getToken();
    }

    public function getName() {
        echo $this->name;
    }

    // 時価情報・板情報
    public function getsymbol($symbol, $exchange) {
        $param = "/kabusapi/board/" . $symbol . "@" . $exchange;
        $response = $this->sendApi($param);
        $this->log->warning("リクエスト", [$param, $response]);

        return $response;
    }

    // 取引余力（現物）
    // StockAccountWallet
    public function getcash() {
        $param = "/wallet/cash";
        $response = $this->sendApi($param);

        return $response;
    }

    // 残高照会
    public function getpositions($product=0) {
        $param = "/positions?product=" . $product;
        $response = $this->sendApi($param);

        return $response;
    }

    // 注文約定照会
    public function getorders($product=0) {
        $param = "/orders?product=" . $product;
        $response = $this->sendApi($param);

        return $response;
    }

    // 注文発注
    public function getsendorder($symbol, $side, $qty, $price, $expireday, $frontordertype) {
        $url = "http://localhost:" . $this->port . "/sendorder";
        $data = [
            'Password' => $this->order_password,
            'Symbol' => $symbol,
            'Exchange' => 1,
            'SecurityType' => 1,
            'Side' => '{$side}',
            'CashMargin' => 1,
            'DelivType' => 1,// 受渡区分 分からない
            'FundType' => '  ',// 資産区分 分からない
            'AccountType' => 1,// 口座種別 分からない
            'Qty' => $qty,
            'Price' => $price,
            'ExpireDay' => $expireday,
            'FrontOrderType' => $frontordertype,
        ];
        $context = stream_context_create($opts);
        $json = file_get_contents($url, false, $context);
        if (isset($http_response_header)) {
            $pos = strpos($http_response_header[0], '200');
            if ($pos === false) {
                $this->log->error("リクエスト失敗", [$url, $data, $json]);
                exit;
            }
        }
        $response = json_decode($json, true);
        if ($response['Result'] === 0) {
            $order_id = $response['OrderId'];
        } else {
            $this->log->error("発注に失敗しました", [$url, $data]);
            exit;
        }

        return $order_id;
    }

    // トークン発行
    public function getToken() {
        $url = "http://localhost:" . $this->port . "/kabusapi/token";
        $data = ['APIPassword' => $this->password];
        $opts = [
            'http' => [
                'method' => "POST",
                'header'=> "Content-type: application/json\r\n" . "Accept: application/json\r\n",
                'content' => json_encode($data),
                'ignore_errors' => true,
                'protocol_version' => '1.1'
            ]
        ];
        $context = stream_context_create($opts);
        $json = file_get_contents($url, false, $context);
        if (isset($http_response_header)) {
            $pos = strpos($http_response_header[0], '200');
            if ($pos === false) {
                $this->log->error("リクエスト失敗", [$url, $data, $json]);
                exit;
            }
        }
        if (!$json) {
            $this->log->error("API URLが読み込めない", [$url, $data]);
            exit;
        }

        $response = json_decode($json, true);
        if ($response['ResultCode'] === 0) {
            $this->apikey = $response['Token'];
        } else {
            $this->log->error("APIトークン発行に失敗しました", [$url, $data]);
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

        if (isset($http_response_header)) {
            $pos = strpos($http_response_header[0], '200');
            if ($pos === false) {
                $this->log->error("リクエスト失敗", [$url, $json]);
                exit;
            }
        }

        $response = json_decode($json, true);
        return $response;
    }

    // 銘柄登録全解除
    public function removeAll() {
        $url = "http://localhost:" . $this->port . "/kabusapi/unregister/all";
        $opts = [
            'http' => [
                'method' => "PUT",
                'header'=> "Content-type: application/json\r\n"
                . "X-API-KEY: " . $this->apikey ,
                'ignore_errors' => true,
                'protocol_version' => '1.1'
            ]
        ];
        $context = stream_context_create($opts);
        $json = file_get_contents($url, false, $context);
        if (isset($http_response_header)) {
            $pos = strpos($http_response_header[0], '200');
            if ($pos === false) {
                $this->log->error("リクエスト失敗", [$url, $json]);
                exit;
            }
        }
        if (!$json) {
            $this->log->error("API URLが読み込めない", [$url]);
            exit;
        }

        $response = json_decode($json, true);
        if ($response['RegistList']) {
            $this->log->error("銘柄登録全解除に失敗しました", [$url]);
            exit;
        }
        return true;
    }
}

