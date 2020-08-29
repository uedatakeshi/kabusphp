<?php
require_once "vendor/autoload.php";
require_once 'config.php';

/*
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
 */

$maxPrice = 1000;// 株価千円以下
$minVolume = 100000;// 出来高１０万以上
$minMarketVal = 500000000000;// 時価総額５０００億円以上
$zenba_s = mktime(9, 30, 0);// 前場寄付
$zenba_e = mktime(11, 30, 0);//前場引け
$goba_s = mktime(12, 30, 0);//後場寄付
$goba_e = mktime(15, 00, 0);//後場引け

$kabus = new Kabucom\Kabus("pub");
$codes = new Kabucom\Code();
$kabus->getName();
echo $kabus->apikey;

//print_r($codes->allCode);
//exit;

//$value = $kabus->getsymbol(7974, 1);
//print_r($value);
//exit;

// 最初にAPI銘柄リストをリセット
$kabus->removeAll();


$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$list = [];// 本日の対象銘柄のリスト
$n = 0;
foreach ($codes->allCode as $v) {
	if ($n > 49) {
        $kabus->removeAll();// 50件たまったら削除
        $n = 0;
		break;
	}
    $item = $kabus->getSymbol($v, 1);// 東証のみなので全部1
    $query = insQuery($item);
    $result = pg_query($query);
    //時価総額 
    $TotalMarketValue = $item['TotalMarketValue'];
    //前日終値
    $PreviousClose = $item['PreviousClose'];
    //前日売買高 その日の朝では取得できない
    $TradingVolume = $item['TradingVolume'];

    // 抽出条件で絞る`.3762件
    // 仮に1000件に絞れたとして、１秒で10件取得できるから、全部で1.６６分で一回りできる。
    // 何件くらいヒットするのか見当がつかない。平日やるしかないが
    if (($PreviousClose < $maxPrice) && ($PreviousClose > 600)) {
        if ($TotalMarketValue > $minMarketVal) {// 
        //if ($TradingVolume > $minVolume) {// dame
            $list[] = $v;
        }
    }
    $n++;
}
pg_close($db);

print_r($list);// このリストに対し9:00から５分おきにひらすら情報取得してDBに入れて行く
exit;

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

while (1) {
    $this_time = time();
    if (($this_time >= $zenba_s) && ($this_time <= $zenba_e) && ($this_time >= $goba_s)) {
        // INS
        foreach ($list as $k => $v) {
            $item = $kabus->getSymbol($v, 1);// 東証のみなので全部1
            $query = insQuery($item);
            $result = pg_query($query) or die('Query failed: ' . pg_last_error());
        }
        // SELECT
        $query = "SELECT * FROM ";
    }
    if ($this_time > $goba_e) {
        break;
    }
    sleep(5 * 60); // 5分おき
}
pg_close($db);

exit;

function insQuery($item) {

    $today = date("Y-m-d");
    foreach ($item as $k => $v) {
        if (!is_array($v)) {
            if ($v == "") {
                $item[$k] = "null";
            } else {
                $item[$k] = "'" . pg_escape_string($v) . "'";
            }
        }
    }

    $query = <<<END
    INSERT INTO symbols (
        reg_date,
        Symbol,
        SymbolName,
        Exchange,
        ExchangeName,
        CurrentPrice,
        CurrentPriceTime,
        CurrentPriceChangeStatus, 
        CurrentPriceStatus,
        CalcPrice,
        PreviousClose,
        PreviousCloseTime,
        ChangePreviousClose,
        ChangePreviousClosePer,
        OpeningPrice,
        OpeningPriceTime,
        HighPrice,
        HighPriceTime,
        LowPrice,
        LowPriceTime,
        TradingVolume,
        TradingVolumeTime,
        VWAP,
        TradingValue,
        BidQty,
        BidPrice,
        BidTime,
        BidSign,
        MarketOrderSellQty,
        MarketOrderBuyQty,
        OverSellQty,
        UnderBuyQty,
        TotalMarketValue 
    ) VALUES (
        '{$today}',
        {$item['Symbol']},
        {$item['SymbolName']},
        {$item['Exchange']},
        {$item['ExchangeName']},
        {$item['CurrentPrice']},
        {$item['CurrentPriceTime']},
        {$item['CurrentPriceChangeStatus']}, 
        {$item['CurrentPriceStatus']},
        {$item['CalcPrice']},
        {$item['PreviousClose']},
        {$item['PreviousCloseTime']},
        {$item['ChangePreviousClose']},
        {$item['ChangePreviousClosePer']},
        {$item['OpeningPrice']},
        {$item['OpeningPriceTime']},
        {$item['HighPrice']},
        {$item['HighPriceTime']},
        {$item['LowPrice']},
        {$item['LowPriceTime']},
        {$item['TradingVolume']},
        {$item['TradingVolumeTime']},
        {$item['VWAP']},
        {$item['TradingValue']},
        {$item['BidQty']},
        {$item['BidPrice']},
        {$item['BidTime']},
        {$item['BidSign']},
        {$item['MarketOrderSellQty']},
        {$item['MarketOrderBuyQty']},
        {$item['OverSellQty']},
        {$item['UnderBuyQty']},
        {$item['TotalMarketValue']} 
    ) 
END;

    return $query;
}
