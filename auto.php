<?php
require_once "vendor/autoload.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;



$kabus = new Kabucom\Kabus("pub");
$codes = new Kabucom\Code();
$kabus->getName();

print_r($codes->allCode);
exit;

$value = $kabus->getSymbol(7974, 1);
print_r($value);
exit;

$list = [];// 本日の対象銘柄のリスト
foreach ($codes->allCode as $v) {
    $item = $kabus->getSymbol($v, 1);// 東証のみなので全部1
    //時価総額
    $TotalMarketValue = $item['TotalMarketValue'];
    //前日終値
    $PreviousClose = $item['PreviousClose'];
    //前日売買高
    $TradingVolume = $item['TradingVolume'];

    // 抽出条件で絞る
    if (($PreviousClose < 1000) && ($PreviousClose > 600)) {
        if ($TradingVolume > 100000) {
            list[] = $v;
        }
    }
}
print_r($list);// このリストに対し9:00から５分おきにひらすら情報取得してDBに入れて行く
exit;

$dbconn = pg_connect("host=localhost dbname=kabus_db user=postgres password=foo")
    or die('Could not connect: ' . pg_last_error());

while (1) {
    $this_time = time();
    $zenba_s = mktime(9, 30, 0);
    $zenba_e = mktime(11, 30, 0);
    $goba_s = mktime(12, 30, 0);
    $goba_e = mktime(15, 00, 0);
    if (($this_time >= $zenba_s) && ($this_time <= $zenba_e) && ($this_time >= $goba_s)) {
        // 
        foreach ($list as $k => $v) {
            $item = $kabus->getSymbol($v, 1);// 東証のみなので全部1
            $query = insQuery($item);
            $result = pg_query($query) or die('Query failed: ' . pg_last_error());
        }
    }
    if ($this_time > $goba_e) {
        break;
    }
    sleep(5 * 60); // 5分おき
}
pg_close($dbconn);
exit;

function insQuery($item) {

    $query = <<<END
INSERT INTO symbols (
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
    '{$item['Symbol']}',
    '{$item['SymbolName']}',
    '{$item['Exchange']}',
    '{$item['ExchangeName']}',
    '{$item['CurrentPrice']}',
    '{$item['CurrentPriceTime']}',
    '{$item['CurrentPriceChangeStatus']}', 
    '{$item['CurrentPriceStatus']}',
    '{$item['CalcPrice']}',
    '{$item['PreviousClose']}',
    '{$item['PreviousCloseTime']}',
    '{$item['ChangePreviousClose']}',
    '{$item['ChangePreviousClosePer']}',
    '{$item['OpeningPrice']}',
    '{$item['OpeningPriceTime']}',
    '{$item['HighPrice']}',
    '{$item['HighPriceTime']}',
    '{$item['LowPrice']}',
    '{$item['LowPriceTime']}',
    '{$item['TradingVolume']}',
    '{$item['TradingVolumeTime']}',
    '{$item['VWAP']}',
    '{$item['TradingValue']}',
    '{$item['BidQty']}',
    '{$item['BidPrice']}',
    '{$item['BidTime']}',
    '{$item['BidSign']}',
    '{$item['MarketOrderSellQty']}',
    '{$item['MarketOrderBuyQty']}',
    '{$item['OverSellQty']}',
    '{$item['UnderBuyQty']}',
    '{$item['TotalMarketValue']}' 
) 
END;

    return $query;
}
