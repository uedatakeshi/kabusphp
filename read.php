<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');
$zenba_b = mktime(8, 56, 0);// 寄付前
$zenba_s = mktime(9, 0, 0);// 前場寄付
$zenba_e = mktime(11, 30, 0);//前場引け
$goba_s = mktime(12, 30, 0);//後場寄付
$goba_e = mktime(14, 55, 0);//後場引け
$reg_date = date("Y-m-d");
$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

for ($i = 0; $i < 2; $i++) {// 認証が切れても再接続を試みる

	$kabus = new Kabucom\Kabus("pub");
	$codes = new Kabucom\Code("list100");
	echo $kabus->apikey . "\n";

	//print_r( $kabus->getSymbol("7974", 1));
	//$cash = $kabus->getcash();
	//echo $cash['StockAccountWallet'] . "\n";
	//$order_id = $kabus->dummyOrder("1234", 100);
	//$order_id = $kabus->getsendorder("8550", 213);
	//$order_id = $kabus->getsendorder("8550", 213, '1', 0, '  ', 100, 25);
	//exit;

	$kabus->removeAll();// 最初にAPI銘柄リストをリセット
	$list = $codes->allCode;// 本日の対象銘柄のリスト

	$loop = 1;
	$zloop = 1;
	$orderbuys = [];
	$ordersell = [];
	while (1) {
	    $this_time = time();
	    if ((($this_time >= $zenba_b) && ($this_time <= $zenba_e)) || (($this_time >= $goba_s) && ($this_time <= $goba_e))) {
	        $n = 0;
	        foreach ($list as $k => $v) {
				if ($n > 49) {
			        $kabus->removeAll();// 50件たまったら削除
			        $n = 0;
			        sleep(1);
				}
	            $item = $kabus->getSymbol($v, 1);// 東証のみなので全部1
	            if (isset($item['Code']) && ($item['Code'] == 4001007)) {// 認証エラー対策
	            	break;
	            }
	            // 傾き計算
	            $ans = array();
	            if ($zloop >= 3) {
	                $ans = calcKatamuki($reg_date, $v, $loop, $item);
	            }
	            // INS
	            $query = insQuery($item, $loop, $ans);
	            $result = pg_query($query);
	            $n++;
	            // select
	            if ($zloop >= 3) {
	                if ($bidprice = checkOrder($v, $loop)) {
	                    $cash = $kabus->getcash();
	                    if ($cash['StockAccountWallet'] > $bidprice * 100) {
	                        // ここで注文を入れる
	                        $orderbuys[$v] = $kabus->getsendorder($v, $bidprice);
	                        //$orderbuys[$v] = $kabus->dummyOrder($v, $bidprice);// debug ダミー注文
	                    }
	                }
	            }
	            if (isset($orderbuys[$v]) && $orderbuys[$v]) {
	                // 注文約定照会getorders
	                $orders = $kabus->getorders();
	                if (uriChumon($kabus, $orders, $orderbuys[$v], $v)) {
	                	unset($orderbuys[$v]);
	                }
	            }
	        }
	        $loop++;
	        if ($this_time >= $zenba_s) {
	        	$zloop++;
	        }
			$kabus->removeAll();// API銘柄リストをリセット
	        echo $loop . "\n";
	        //echo $zloop . "\n";
	        sleep(1);//sleep(3 * 60); // 5分おき
	    }
	    if ($this_time > $goba_e) {
	        break;
	    }
	}
}
pg_close($db);
exit;

function uriChumon($kabus, $orders, $orderbuy, $symbol) {
    global $zenba_e;
    if (time() > $zenba_e) {
        $code = 26;
    } else {
        $code = 25;
    }
    foreach ($orders as $val) {
        if (($val['Symbol'] == $symbol) && ($val['ID'] == $orderbuy)) {
            if (($val['State'] == 5) && ($val['OrderState'] == 5) && ($val['CumQty'] > 0)) {
                $info = $kabus->getinfo($symbol, 1);// 東証のみなので全部1

                $UpperLimit = $info['UpperLimit'];// 値幅制限
                $sellPrice = intval($val['Price'] * 1.017);
                if ($sellPrice > $UpperLimit) {// ここで値幅制限チェック
                    $sellPrice = $UpperLimit;
                }

                $sellQty = $val['CumQty'];
                // ここで売り処理 26:不成（後場) 25:不成（前場）
                $order_id = $kabus->getsendorder($symbol, $sellPrice, '1', 0, '  ', $sellQty, $code);
                //$ordersell[$v] = $kabus->dummyOrder($symbol, $sellPrice, '1', 0, '  ', $sellQty, 26);
                if ($order_id) {
                    return $symbol;
                } else {
                    return false;
                }
                break;
            }
        }
    }
}

function checkOrder($symbol, $loop) {
    $loop_array = range($loop, $loop - 2);
    list($c3, $c2, $c1) = $loop_array; 
    $loop_in = implode(",", $loop_array);
    $reg_date = date("Y-m-d");
    $output = [];
    $query = <<<END
    SELECT 
        Symbol, 
        loop, CurrentPrice, CurrentPriceTime, CurrentPriceStatus, currentpricechangestatus, 
        BidPrice, vwap, ChangePreviousClosePer, tradingvolume, 
        openingprice, lowprice, inclination, intercept,
        bidqty, askqty
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
                $output[$myloop]['time'] = $row['currentpricetime'];
                $output[$myloop]['currentpricestatus'] = $row['currentpricestatus'];
                $output[$myloop]['currentpricechangestatus'] = $row['currentpricechangestatus'];

                $output[$myloop]['bidprice'] = $row['bidprice'];
                $output[$myloop]['vwap'] = $row['vwap'];
                $output[$myloop]['changepreviouscloseper'] = $row['changepreviouscloseper'];
                $output[$myloop]['tradingvolume'] = $row['tradingvolume'];

                $output[$myloop]['openingprice'] = $row['openingprice'];
                $output[$myloop]['lowprice'] = $row['lowprice'];
                $output[$myloop]['inclination'] = $row['inclination'];
                $output[$myloop]['intercept'] = $row['intercept'];

                $output[$myloop]['bidqty'] = $row['bidqty'];
                $output[$myloop]['askqty'] = $row['askqty'];
            }
        }
    }

    return calcThird($output, $loop_array);
}

function insQuery($item, $loop, $ans) {

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
    if ($ans) {
        $inclination = "'" . $ans['A'] . "'";
        $intercept = "'" . $ans['B'] . "'";
    } else {
        $inclination = "null";
        $intercept = "null";
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
        TotalMarketValue, 
        inclination,
        intercept,
        AskQty,
        AskPrice,
        AskTime,
        AskSign
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
        {$item['TotalMarketValue']},
        $inclination,
        $intercept,
        {$item['AskQty']},
        {$item['AskPrice']},
        {$item['AskTime']},
        {$item['AskSign']}
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

    // 傾き１差分
    if (isset($output[$c4]['inclination']) && isset($output[$c3]['inclination'])) {
        $k_diff1 = $output[$c4]['inclination'] - $output[$c3]['inclination'];
    } else {
        return false;
    }
    if (isset($output[$c3]['inclination']) && isset($output[$c2]['inclination'])) {
        $k_diff2 = $output[$c3]['inclination'] - $output[$c2]['inclination'];
    } else {
        return false;
    }
    if (isset($output[$c2]['inclination']) && isset($output[$c1]['inclination'])) {
        $k_diff3 = $output[$c2]['inclination'] - $output[$c1]['inclination'];
    } else {
        return false;
    }// 傾きここまで

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
    // 始値
    if (isset($output[$c4]['openingprice'])) {
        if ($output[$c4]['price'] < $output[$c4]['openingprice']) {
            return false;
        }
        $srate = round(100 * $output[$c4]['price'] / $output[$c4]['openingprice'], 2);// VWAPの乖離率
    } else {
        return false;
    }

    if (($output[$c4]['inclination'] > 0) && ($output[$c4]['inclination'] < 2)) {
        if (($k_diff1 > $k_diff2) && ($k_diff2 > $k_diff3) && ($k_diff2 > 0.001) && ($k_diff3 > 0.001)) {
            if ((($vdiff1 > $vdiff3) || ($vdiff2 > $vdiff3)) && ($vdiff1 > 10000)) {
                if (($diff3 > 0) && ($wrate < 103) && ($prate > 0.1) && ($drate > 1) && ($srate < 103)) {
                    return $bidprice;
                }
            }
        }
    }

    return false;

}

function calcThird($output, $loop_array) {
    list($c3, $c2, $c1) = $loop_array; 
    $j_time = mktime(9, 30, 0);

    if (isset($output[$c3]['currentpricestatus'])) {
    	$currentpricestatus = $output[$c3]['currentpricestatus'];
    } else {
    	return false;
    }
    if (isset($output[$c3]['bidprice'])) {
    	$bidprice = $output[$c3]['bidprice'];
    } else {
    	return false;
    }
    // 傾き１差分
    if (isset($output[$c3]['inclination']) && isset($output[$c2]['inclination'])) {
        $k_diff1 = $output[$c3]['inclination'] - $output[$c2]['inclination'];
    } else {
        return false;
    }
    if (isset($output[$c2]['inclination']) && isset($output[$c1]['inclination'])) {
        $k_diff2 = $output[$c2]['inclination'] - $output[$c1]['inclination'];
    } else {
        return false;
    }
    // 傾きここまで

    if (isset($output[$c3]['price']) && isset($output[$c2]['price'])) {
        $diff1 = $output[$c3]['price'] - $output[$c2]['price'];
        $vdiff1 = $output[$c3]['tradingvolume'] - $output[$c2]['tradingvolume'];
    } else {
        return false;
    }
    if (isset($output[$c2]['price']) && isset($output[$c1]['price'])) {
        $diff2 = $output[$c2]['price'] - $output[$c1]['price'];
        $vdiff2 = $output[$c2]['tradingvolume'] - $output[$c1]['tradingvolume'];
    } else {
        return false;
    }
    $prate = 0;
    if (isset($output[$c3]['price']) && ($output[$c3]['price'] > 0)) {
        $prate = round(100 * $diff1 / $output[$c3]['price'], 2);// 現在価格の上昇率
    } else {
        return false;
    }
    $vrate = 0;
    if (isset($output[$c3]['tradingvolume']) && ($output[$c3]['tradingvolume'] > 0)) {
        $vrate = round(100 * $vdiff1 / $output[$c3]['tradingvolume'], 2);// 現在出来高の上昇率
    } else {
        return false;
    }
    $wrate = 0;
    if (isset($output[$c3]['vwap']) && ($output[$c3]['vwap'] > 0)) {
        $wrate = round(100 * $output[$c3]['price'] / $output[$c3]['vwap'], 2);// VWAPの乖離率
    } else {
        return false;
    }
    if (isset($output[$c3]['openingprice'])) {
        $drate = round(100 * ($output[$c3]['price'] - $output[$c3]['openingprice']) / $output[$c3]['openingprice'], 2);//始値からの上昇率
    } else {
        return false;
    }
    if (isset($output[$c3]['openingprice'])) {
        if ($output[$c3]['price'] < $output[$c3]['openingprice']) {
            return false;
        }
        $srate = round(100 * $output[$c3]['price'] / $output[$c3]['openingprice'], 2);// 始値からの乖離率
    } else {
        return false;
    }

    if (($output[$c3]['inclination'] > 0) && ($output[$c3]['inclination'] < 2)) {
        if (($k_diff1 > $k_diff2) && ($k_diff1 > 0.01) && ($k_diff2 > 0.01)) {
            if (($diff1 > $diff2) && ($vdiff1 > $vdiff2) && ($vdiff1 > 10000)) {
                if (($output[$c3]['currentpricechangestatus'] == '0057') && ($diff2 >= 0)) {
                    if (($wrate < 103) && ($prate > 0.1) && ($drate > 1)) {
                        if ($output[$c3]['bidqty'] && $output[$c3]['askqty'] && ($output[$c3]['bidqty'] < $output[$c3]['askqty'])) {
                            return $bidprice;
                        }
                    }
                }
            }
        }
    }

    return false;

}

function calcKatamuki($reg_date, $symbol, $loop, $item) {
    $output[$loop]['price'] = $item['CurrentPrice'];
    $output[$loop]['openingprice'] = $item['OpeningPrice'];

    $query = <<<END
    SELECT 
        loop, CurrentPrice, openingprice 
    FROM items
    WHERE reg_date='{$reg_date}' AND  loop < $loop AND Symbol = '{$symbol}' 
    ORDER BY loop DESC 
END;
    $result = pg_query($query);
    if ($result) {
        while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
            $myloop = $row['loop'];
            if (preg_match("/^[0-9]+$/", $row['currentprice'])) {
                $output[$myloop]['price'] = $row['currentprice'];
                $output[$myloop]['openingprice'] = $row['openingprice'];
            }
        }
    }

    // プロット数
    $num = count($output);
    $first_key = key(array_slice($output, -1, 1, true));
    $B = $output[$first_key]['openingprice'];
    // Xの平均
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $bunX = 0;
    foreach ($output as $k => $v) {
        $sumX = $sumX + $k;
        $sumY = $sumY + $v['price'] ;
        $sumXY = $sumXY + $k * ($v['price'] );
    }
    $aveX = $sumX / $num;
    $aveY = $sumY / $num;
    $aveXY = $sumXY / $num;
    $last_key = key(array_slice($output, 0, 1, true));
    $covXY = $aveXY - $aveX * $aveY;
    foreach ($output as $k => $v) {
        $bunX = $bunX + pow(($aveX - $k), 2);
    }
    $henX = $bunX / $num;
    if ($henX == 0) {
        return false;
    }
    $ans['k'] = $last_key;
    $ans['A'] = $covXY / $henX;
    $ans['B'] = $aveY - $ans['A'] * $aveX;
    $ans['p'] = $output[$last_key]['price'];
    $ans['s'] = $B;

    return $ans;
}

