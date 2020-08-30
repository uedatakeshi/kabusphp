<?php
require_once "vendor/autoload.php";
require_once 'config.php';


$maxPrice = 1000;// 株価千円以下
$minVolume = 100000;// 出来高１０万以上
$minMarketVal = 500000000000;// 時価総額５０００億円以上
date_default_timezone_set('Asia/Tokyo');
$zenba_s = mktime(21, 25, 0);// 前場寄付
$zenba_e = mktime(21, 36, 0);//前場引け
$goba_s = mktime(12, 30, 0);//後場寄付
$goba_e = mktime(21, 37, 0);//後場引け

$kabus = new Kabucom\Kabus("pub");
$codes = new Kabucom\Code();
$kabus->getName();
echo $kabus->apikey;

// 最初にAPI銘柄リストをリセット
$kabus->removeAll();


$list = $codes->allCode;// 本日の対象銘柄のリスト
$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);
$loop = 1;
while (1) {

    $this_time = time();
    if (($this_time >= $zenba_s) && ($this_time <= $zenba_e)) {
        // INS
        $n = 0;
        foreach ($list as $k => $v) {
			if ($n > 49) {
		        $kabus->removeAll();// 50件たまったら削除
		        $n = 0;
		        sleep(1);
			}
            $item = $kabus->getSymbol($v, 1);// 東証のみなので全部1
            $query = insQuery($item, $loop);
            $result = pg_query($query);
            $n++;
        }
        $loop++;
		// 最初にAPI銘柄リストをリセット
		$kabus->removeAll();
        echo $loop . "\n";
	    sleep(2 * 60); // 5分おき
        // SELECT
    }
    if ($this_time > $goba_e) {
        break;
    }
}
pg_close($db);

exit;

function insQuery($item, $loop) {

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
    INSERT INTO items (
        reg_date,
        loop,
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
        $loop,
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
