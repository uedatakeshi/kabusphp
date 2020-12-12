<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');
$zenba_b = mktime(8, 56, 0);// 寄付前
$zenba_s = mktime(9, 0, 0);// 前場寄付
$zenba_e = mktime(11, 32, 0);//前場引け
$goba_s = mktime(12, 30, 0);//後場寄付
$goba_e = mktime(14, 55, 0);//後場引け
$change_loop = mktime(9, 30, 0);// 
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
                        $amount = $bidprice * 100;
                        $fee = getFee($amount);
	                    if ($cash['StockAccountWallet'] > $amount + $fee) {
                            // ここで注文を入れる
                            //$orderbuys[$v] = $kabus->getsendorder($v, $confirm['CurrentPrice']);
                            $orderbuys[$v] = $kabus->getsendorder($v, 0, '2', 2, 'AA', 100, 10);// 成り行き
                            //$orderbuys[$v] = $kabus->dummyOrder($v, $bidprice);// debug ダミー注文
	                    }
	                }
	            }
	            if (isset($orderbuys[$v]) && $orderbuys[$v]) {
	                // 注文約定照会getorders
	                $orders = $kabus->getorders();
	                if ($ordersells[$v] = uriChumon($kabus, $orders, $orderbuys[$v], $v)) {
	                	unset($orderbuys[$v]);
	                }
	            }
	            if (isset($ordersells[$v]) && $ordersells[$v]) {
	                $orders = $kabus->getorders();
	                if (sonGiri($kabus, $orders, $ordersells[$v], $v, $item)) {
	                	unset($ordersells[$v]);
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
	        //sleep(90);//sleep(3 * 60); // 1分30秒待ち
	    }
	    if ($this_time > $goba_e) {
	        break;
	    }
	}
}
pg_close($db);
exit;

function sonGiri($kabus, $orders, $ordersell, $symbol, $item) {
    foreach ($orders as $val) {
        if (($val['Symbol'] == $symbol) && ($val['ID'] == $ordersell)) {
            if (($val['State'] == 3) && ($val['OrderState'] == 3) && ($val['CumQty'] == 0)) {
                //$songiri = intval($val['Price'] * 0.973);// 売りの指値から逆算
                //$songiri = round($val['Price'] - ((2000 + 1000)/100));// 売りの指値から逆算
                $songiri = round(0.95 * $val['Price'] -19 + 0.01);// 売りの指値から逆算
                if ($item['CurrentPrice'] <= $songiri) {
                    if ($kabus->cancelorders($ordersell)) {
                        $sellPrice = $item['AskPrice'];
                        $sellQty = $val['OrderQty'];
                        $code = 27;
                        $order_id = $kabus->getsendorder($symbol, $sellPrice, '1', 0, '  ', $sellQty, $code);
                        if ($order_id) {
                            return $order_id;
                        } else {
                            return false;
                        }
                        break;
                    }
                }
            }
        }
    }
}

function uriChumon($kabus, $orders, $orderbuy, $symbol) {
    global $zenba_e;
    if (time() > mktime(10, 50, 0)) {
        $code = 26;
    } else {
        $code = 26;
    }
    foreach ($orders as $val) {
        if (($val['Symbol'] == $symbol) && ($val['ID'] == $orderbuy)) {
            if (($val['State'] == 5) && ($val['OrderState'] == 5) && ($val['CumQty'] > 0)) {
                $info = $kabus->getinfo($symbol, 1);// 東証のみなので全部1

                $UpperLimit = $info['UpperLimit'];// 値幅制限
                $buyPrice = $val['Price'];
                $amount = $buyPrice * 100;
                $fee = getFee($amount);
                $sellQty = $val['CumQty'];
                //$sellPrice = intval($val['Price'] * 1.017);
                $sellPrice = round($buyPrice * (1 + (2000 + $fee)/($sellQty * $buyPrice)));
                if ($sellPrice > $UpperLimit) {// ここで値幅制限チェック
                    $sellPrice = $UpperLimit;
                }
                // ここで売り処理 26:不成（後場) 25:不成（前場）
                $order_id = $kabus->getsendorder($symbol, $sellPrice, '1', 0, '  ', $sellQty, $code);
                //$ordersell[$v] = $kabus->dummyOrder($symbol, $sellPrice, '1', 0, '  ', $sellQty, 26);
                if ($order_id) {
                    return $order_id;
                } else {
                    return false;
                }
                break;
            }
        }
    }
}

function checkOrder($symbol, $loop) {
    global $change_loop;
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
        openingprice, lowprice, previousclose, inclination, intercept,
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
                $output[$myloop]['previousclose'] = $row['previousclose'];
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
    for ($i = 1; $i < 11; $i++) {
        $key = 'Sell' . $i;
        if ($item[$key]['Qty'] == "") {
            $Sell_Qty[$i] = "null";
        } else {
            $Sell_Qty[$i] = "'" . $item[$key]['Qty'] . "'";
        }
        if ($item[$key]['Price'] == "") {
            $Sell_Price[$i] = "null";
        } else {
            $Sell_Price[$i] = "'" . $item[$key]['Price'] . "'";
        }
    }
    for ($i = 1; $i < 11; $i++) {
        $key = 'Buy' . $i;
        if ($item[$key]['Qty'] == "") {
            $Buy_Qty[$i] = "null";
        } else {
            $Buy_Qty[$i] = "'" . $item[$key]['Qty'] . "'";
        }
        if ($item[$key]['Price'] == "") {
            $Buy_Price[$i] = "null";
        } else {
            $Buy_Price[$i] = "'" . $item[$key]['Price'] . "'";
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
        TotalMarketValue, 
        inclination,
        intercept,
        AskQty,
        AskPrice,
        AskTime,
        AskSign,
        Sell1_Price, 
        Sell1_Qty, 
        Sell2_Price, 
        Sell2_Qty ,
        Sell3_Price, 
        Sell3_Qty, 
        Sell4_Price, 
        Sell4_Qty, 
        Sell5_Price, 
        Sell5_Qty, 
        Sell6_Price, 
        Sell6_Qty, 
        Sell7_Price, 
        Sell7_Qty, 
        Sell8_Price, 
        Sell8_Qty, 
        Sell9_Price, 
        Sell9_Qty, 
        Sell10_Price,
        Sell10_Qty, 
        Buy1_Price, 
        Buy1_Qty,
        Buy2_Price, 
        Buy2_Qty,
        Buy3_Price, 
        Buy3_Qty,
        Buy4_Price, 
        Buy4_Qty,
        Buy5_Price, 
        Buy5_Qty,
        Buy6_Price, 
        Buy6_Qty,
        Buy7_Price, 
        Buy7_Qty,
        Buy8_Price, 
        Buy8_Qty,
        Buy9_Price, 
        Buy9_Qty,
        Buy10_Price,
        Buy10_Qty,
        created
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
        {$item['AskSign']},
        {$Sell_Price[1]},
        {$Sell_Qty[1]},
        {$Sell_Price[2]},
        {$Sell_Qty[2]},
        {$Sell_Price[3]},
        {$Sell_Qty[3]},
        {$Sell_Price[4]},
        {$Sell_Qty[4]},
        {$Sell_Price[5]},
        {$Sell_Qty[5]},
        {$Sell_Price[6]},
        {$Sell_Qty[6]},
        {$Sell_Price[7]},
        {$Sell_Qty[7]},
        {$Sell_Price[8]},
        {$Sell_Qty[8]},
        {$Sell_Price[9]},
        {$Sell_Qty[9]},
        {$Sell_Price[10]},
        {$Sell_Qty[10]},
        {$Buy_Price[1]},
        {$Buy_Qty[1]},
        {$Buy_Price[2]},
        {$Buy_Qty[2]},
        {$Buy_Price[3]},
        {$Buy_Qty[3]},
        {$Buy_Price[4]},
        {$Buy_Qty[4]},
        {$Buy_Price[5]},
        {$Buy_Qty[5]},
        {$Buy_Price[6]},
        {$Buy_Qty[6]},
        {$Buy_Price[7]},
        {$Buy_Qty[7]},
        {$Buy_Price[8]},
        {$Buy_Qty[8]},
        {$Buy_Price[9]},
        {$Buy_Qty[9]},
        {$Buy_Price[10]},
        {$Buy_Qty[10]},
        CURRENT_TIMESTAMP
    ) 
END;

    return $query;
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

    // 現在値の時間
    $t0 = strtotime($output[$c3]['time']);

    // 一つ前のloopの時間
    $t1 = strtotime($output[$c2]['time']);

    // ２つ前のloopの時間
    $t2 = strtotime($output[$c1]['time']);

    // レンジの上下幅
    $dy = 1;

    // 現在の傾き
    $y0 = ($output[$c2]['inclination'] *  $c3) + $output[$c2]['intercept'];
    $y1 = ($output[$c1]['inclination'] *  $c3) + $output[$c1]['intercept'];

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
    if (isset($output[$c3]['previousclose'])) {
        if ($output[$c3]['openingprice'] < $output[$c3]['previousclose']) {// 前日の終値より高く始まっていること
            return false;
        }
    } else {
        return false;
    }
    if (isset($output[$c3]['openingprice'])) {
        $srate = round(100 * $output[$c3]['price'] / $output[$c3]['openingprice'], 2);// 始値からの乖離率
    } else {
        return false;
    }
    if (isset($output[$c3]['bidqty']) && ($output[$c3]['askqty'] > 0)) {
        $qrate = $output[$c3]['bidqty'] / $output[$c3]['askqty'];// 気配の比率
    } else {
        return false;
    }
    $cal_loop = $c3 - 2 + 1;
    if (($output[$c3]['tradingvolume'] / $cal_loop) < 5000) {
        return false;
    }
    $diff_open_low = $output[$c3]['openingprice'] - $output[$c3]['lowprice'];// 始値と安値の差

    if (($output[$c3]['inclination'] > 0) && ($diff2 > 0) && ($diff1 > $diff2) && ($drate > 0) && ($vdiff1 > 10000) && ($vdiff2 > 0)) {
        if (($k_diff1 > $k_diff2) && ($qrate < 10) && ($prate > 0.7) && ($output[$c3]['changepreviouscloseper'] > 1)) {
            if (($output[$c3]['price'] > $y0 ) || ($output[$c3]['price'] > $y1)) {
                if ($y0 > $y1) {
	                if ($diff_open_low < 11) {
	                    return $bidprice;
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

function getFee($amount) {
    if ($amount <= 100000) {
        $fee = 99;
    } elseif ($amount <= 200000) {
        $fee = 198;
    } elseif ($amount <= 500000) {
        $fee = 275;
    } elseif ($amount > 500000) {
        $fee = $amount * 0.099 * 0.01 + 99;
        if ($fee > 4059) {
            $fee = 4059;
        }
    }

    return $fee;
}

