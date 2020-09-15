<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');
$zenba_s = mktime(9, 0, 0);// 前場寄付
$zenba_e = mktime(11, 30, 0);//前場引け
$goba_s = mktime(12, 30, 0);//後場寄付
$goba_e = mktime(15, 0, 0);//後場引け

$kabus = new Kabucom\Kabus("pub");
$codes = new Kabucom\Code("list100");
echo $kabus->apikey . "\n";
//$cash = $kabus->getcash();
//echo $cash['StockAccountWallet'] . "\n";
//$order_id = $kabus->dummyOrder("1234", 100);
//$order_id = $kabus->getsendorder("8550", 213);
//$order_id = $kabus->getsendorder("8550", 213, '1', 0, '  ', 100, 25);

//exit;

// 最初にAPI銘柄リストをリセット
$kabus->removeAll();

$list = $codes->allCode;// 本日の対象銘柄のリスト
$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$loop = 1;
$orderbuys = [];
$ordersell = [];
while (1) {
    $this_time = time();
    if ((($this_time >= $zenba_s) && ($this_time <= $zenba_e)) || (($this_time >= $goba_s) && ($this_time <= $goba_e))) {
        $n = 0;
        foreach ($list as $k => $v) {
			if ($n > 49) {
		        $kabus->removeAll();// 50件たまったら削除
		        $n = 0;
		        sleep(1);
			}
            $item = $kabus->getSymbol($v, 1);// 東証のみなので全部1
            // INS
            $query = insQuery($item, $loop);
            $result = pg_query($query);
            $n++;
            // select
            if ($loop >= 4) {
                if ($bidprice = checkOrder($v, $loop)) {
                    $cash = $kabus->getcash();
                    if ($cash['StockAccountWallet'] > $bidprice * 100) {
                        // ここで注文を入れる
                        $orderbuys[$v] = $kabus->getsendorder($v, $bidprice);
                        // まだテストしていないので仮にダミーのmethodに渡す
                        //$orderbuys[$v] = $kabus->dummyOrder($v, $bidprice);
                    }
                }
            }
            if (isset($orderbuys[$v]) && $orderbuys[$v]) {
                // 注文約定照会getorders
                $orders = $kabus->getorders();
                foreach ($orders as $val) {
                    if (($val['Symbol'] == $v) && ($val['ID'] == $orderbuys[$v])) {
                        if (($val['State'] == 5) && ($val['OrderState'] == 5)) {
                            $sellPrice = intval($val['Price'] * 1.03);
                            $sellQty = $val['CumQty'];
                            // ここで売り処理 26:不成（後場) 25:不成（前場）
                            $ordersell[$v] = $kabus->getsendorder($v, $sellPrice, '1', 0, '  ', $sellQty, 26);
                            //$ordersell[$v] = $kabus->dummyOrder($v, $sellPrice, '1', 0, '  ', $sellQty, 26);
                            unset($orderbuys[$v]);
                            break;
                        }
                    }
                }
            }
        }
        $loop++;
		// API銘柄リストをリセット
		$kabus->removeAll();
        echo $loop . "\n";
	    sleep(3 * 60); // 5分おき
    }
    if ($this_time > $goba_e) {
        break;
    }
}
pg_close($db);

exit;

function checkOrder($symbol, $loop) {
    $loop_array = range($loop, $loop - 3);
    list($c4, $c3, $c2, $c1) = $loop_array; 
    $loop_in = implode(",", $loop_array);
    $reg_date = date("Y-m-d");
    //$reg_date = "2020-09-04";
    $output = [];
    $query = <<<END
    SELECT 
        Symbol, 
        loop, CurrentPrice, CurrentPriceTime, CurrentPriceStatus, 
        BidPrice, vwap, ChangePreviousClosePer, tradingvolume, 
        openingprice, lowprice
    FROM items
    WHERE symbol='{$symbol}' AND loop IN ({$loop_in}) and reg_date='{$reg_date}'
    ORDER BY loop DESC 
END;
    $result = pg_query($query);
    if ($result) {
        while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
            $myloop = $row['loop'];
            $vwap = $row['vwap'];
            if (preg_match("/^[0-9]+$/", $row['currentprice'])) {
                $output[$myloop]['price'] = $row['currentprice'];
                $output[$myloop]['bidprice'] = $row['bidprice'];
                $output[$myloop]['time'] = $row['currentpricetime'];
                $output[$myloop]['currentpricestatus'] = $row['currentpricestatus'];
                $output[$myloop]['vwap'] = $row['vwap'];
                $output[$myloop]['changepreviouscloseper'] = $row['changepreviouscloseper'];
                $output[$myloop]['tradingvolume'] = $row['tradingvolume'];
                $output[$myloop]['openingprice'] = $row['openingprice'];
                $output[$myloop]['lowprice'] = $row['lowprice'];
            }
        }
    }

    return calcFourth($output, $loop_array);
}

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

function calcFourth($output, $loop_array) {
    list($c4, $c3, $c2, $c1) = $loop_array; 
    $j_time = mktime(10, 20, 0);

    if (isset($output[$c4]['currentpricestatus'])) {
    	$currentpricestatus = $output[$c4]['currentpricestatus'];
    } else {
    	return false;
    }
    if (isset($output[$c4]['bidprice'])) {
    	$bidprice = $output[$c4]['bidprice'];
    } else {
    	return false;
    }
    if (isset($output[$c4]['price']) && isset($output[$c3]['price'])) {
        $diff1 = $output[$c4]['price'] - $output[$c3]['price'];
        $vdiff1 = $output[$c4]['tradingvolume'] - $output[$c3]['tradingvolume'];
    } else {
        return false;
    }
    if (isset($output[$c3]['price']) && isset($output[$c2]['price'])) {
        $diff2 = $output[$c3]['price'] - $output[$c2]['price'];
        $vdiff2 = $output[$c3]['tradingvolume'] - $output[$c2]['tradingvolume'];
    } else {
        return false;
    }
    if (isset($output[$c2]['price']) && isset($output[$c1]['price'])) {
        $diff3 = $output[$c2]['price'] - $output[$c1]['price'];
        $vdiff3 = $output[$c2]['tradingvolume'] - $output[$c1]['tradingvolume'];
    } else {
        return false;
    }
    $prate = 0;
    if (isset($output[$c4]['price']) && ($output[$c4]['price'] > 0)) {
        $prate = round(100 * $diff1 / $output[$c4]['price'], 2);// 現在価格の上昇率
    } else {
        return false;
    }
    $vrate = 0;
    if (isset($output[$c4]['tradingvolume']) && ($output[$c4]['tradingvolume'] > 0)) {
        $vrate = round(100 * $vdiff1 / $output[$c4]['tradingvolume'], 2);// 現在出来高の上昇率
    } else {
        return false;
    }
    $wrate = 0;
    if (isset($output[$c4]['vwap']) && ($output[$c4]['vwap'] > 0)) {
        $wrate = round(100 * $output[$c4]['price'] / $output[$c4]['vwap'], 2);// VWAPの乖離率
    } else {
        return false;
    }
    if (isset($output[$c4]['openingprice'])) {
        $drate = 100 * ($output[$c4]['price'] - $output[$c4]['openingprice']) / $output[$c4]['openingprice'];
    } else {
        return false;
    }
    /*
    if (isset($output[$c4]['time']) && preg_match("/^(\d+)-(\d+)-(\d+) (\d+):(\d+):(\d+)/", $output[$c4]['time'], $regs)) {
        $h = $regs[4];
        $m = $regs[5];
        $b = $regs[6];
        $c_time = mktime($h, $m, $b);
        if ($c_time > $j_time) {
            return false;
        }
    } else {
        return false;
    }
    */
    if (isset($output[$c4]['openingprice'])) {
        if ($output[$c4]['price'] < $output[$c4]['openingprice']) {
            return false;
       }
    } else {
        return false;
    }

    if (($diff1 > $diff2) && ($diff2 >= $diff3) && ($diff3 > 0)) {
        if (($vdiff1 > $vdiff2) && ($vdiff2 > $vdiff3) && ($vdiff1 > 10000)) {
            if (($wrate < 101.6) && ($prate > 0.4) && ($drate > 1)) {
                return $bidprice;
            }
        }
    }

    return false;

}

