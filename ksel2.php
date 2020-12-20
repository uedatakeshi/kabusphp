<?php

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=kabus_db user=postgres password=pass123");

$reg_date = "2020-12-15";

$query = <<<END
    SELECT 
        Symbol 
    FROM items
    WHERE reg_date='{$reg_date}' 
    GROUP BY symbol
END;
$result = pg_query($query);
if ($result) {
    while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
        $sarray[] = $row['symbol'];
    }
}
$query = <<<END
    SELECT 
        max(loop) AS eloop, min(loop) AS sloop
    FROM items
    WHERE reg_date='{$reg_date}'
END;
$result = pg_query($query);
if ($result) {
    while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
        $sloop = $row['sloop'];
        $eloop = $row['eloop'];
    }
}

$query = <<<END
SELECT loop FROM items 
    WHERE reg_date='{$reg_date}' AND 
    currentPricetime > '{$reg_date} 9:30:00' LIMIT 1;
END;
$result = pg_query($query);
if ($result) {
    while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
        $loop_change = $row['loop'];
    }
}

//$sarray = array('3928');
//$sarray = array('1789','1963','1966','3359','3645','3673','4246','4813','4824','4845','5726','5809','5991','6038','6077','6172','6175','6378','6464','6471','6473','6584','6618','6727','6804','7187','7702');
$loop_change = 15;// 22
foreach ($sarray as $v) {
//echo $v . "\n";
//    for ($loop = 3; $loop < 70; $loop++) {
    for ($loop = 1; $loop <= $loop_change; $loop++) {
        if ($bidprice = checkOrder($v, $loop)) {
            echo "[{$v}], {$bidprice}\n";
           // break;
        }
    }
}

exit;

function checkOrder($symbol, $loop) {
    global $reg_date;
    global $loop_change;
        $loop_array = range($loop, $loop - 2);
        list($c3, $c2, $c1) = $loop_array; 
     // $loop_array = range($loop, $loop - 3);
     // list($c4, $c3, $c2, $c1) = $loop_array; 
    $loop_in = implode(",", $loop_array);

    $output = [];
    $query = <<<END
    SELECT *
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
                $output[$myloop]['inclination'] = $row['inclination'];
                $output[$myloop]['intercept'] = $row['intercept'];
                $output[$myloop]['currentpricechangestatus'] = $row['currentpricechangestatus'];
                $output[$myloop]['bidqty'] = $row['bidqty'];
                $output[$myloop]['bidsign'] = $row['bidsign'];
                $output[$myloop]['askqty'] = $row['askqty'];
                $output[$myloop]['askprice'] = $row['askprice'];
                $output[$myloop]['asksign'] = $row['asksign'];
                $output[$myloop]['oversellqty'] = $row['oversellqty'];
                $output[$myloop]['underbuyqty'] = $row['underbuyqty'];
                $output[$myloop]['highprice'] = $row['highprice'];
                $output[$myloop]['previousclose'] = $row['previousclose'];
                $output[$myloop]['sell'][1]['qty'] = $row['sell1_qty'];
                $output[$myloop]['sell'][2]['qty'] = $row['sell2_qty'];
                $output[$myloop]['sell'][3]['qty'] = $row['sell3_qty'];
                $output[$myloop]['sell'][4]['qty'] = $row['sell4_qty'];
                $output[$myloop]['sell'][5]['qty'] = $row['sell5_qty'];
                $output[$myloop]['sell'][6]['qty'] = $row['sell6_qty'];
                $output[$myloop]['sell'][7]['qty'] = $row['sell7_qty'];
                $output[$myloop]['sell'][8]['qty'] = $row['sell8_qty'];
                $output[$myloop]['sell'][9]['qty'] = $row['sell9_qty'];
                $output[$myloop]['sell'][10]['qty'] = $row['sell10_qty'];
                $output[$myloop]['buy'][1]['qty'] = $row['buy1_qty'];
                $output[$myloop]['buy'][2]['qty'] = $row['buy2_qty'];
                $output[$myloop]['buy'][3]['qty'] = $row['buy3_qty'];
                $output[$myloop]['buy'][4]['qty'] = $row['buy4_qty'];
                $output[$myloop]['buy'][5]['qty'] = $row['buy5_qty'];
                $output[$myloop]['buy'][6]['qty'] = $row['buy6_qty'];
                $output[$myloop]['buy'][7]['qty'] = $row['buy7_qty'];
                $output[$myloop]['buy'][8]['qty'] = $row['buy8_qty'];
                $output[$myloop]['buy'][9]['qty'] = $row['buy9_qty'];
                $output[$myloop]['buy'][10]['qty'] = $row['buy10_qty'];
            }
        }
    }
    return calcThird($output, $loop_array);
}


function calcThird($output, $loop_array) {
    list($c3, $c2, $c1) = $loop_array; 
    $j_time = mktime(10, 20, 0);

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

    if ($diff1 <= 0) {
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
    if (($output[$c3]['price'] - $output[$c3]['vwap']) > 3) {
        return false;
    }
    if (isset($output[$c3]['openingprice'])) {
        $drate = round(100 * ($output[$c3]['price'] - $output[$c3]['openingprice']) / $output[$c3]['openingprice'], 2);//始値からの上昇率
    } else {
        return false;
    }

    if ($drate <= 1) {
        return false;
    }

    /*
    if (isset($output[$c3]['previousclose'])) {
        if ($output[$c3]['openingprice'] < $output[$c3]['previousclose']) {// 前日の終値より高く始まっていること
            return false;
        }
    } else {
        return false;
    }
     */
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
    $diff_open_low = $output[$c3]['openingprice'] - $output[$c3]['lowprice'];// 始値と安値の差
    
    // 気配ここから
    if ($output[$c3]['bidqty'] && $output[$c3]['askqty']) {
	    $sell_sum[$c3] = $output[$c3]['bidqty'] + $output[$c3]['oversellqty'];
	    foreach ($output[$c3]['sell'] as $k => $v) {
	        $sell_sum[$c3] = $sell_sum[$c3] + $v['qty'];
	    }
	    $buy_sum[$c3] = $output[$c3]['askqty'] + $output[$c3]['underbuyqty'];
	    foreach ($output[$c3]['buy'] as $k => $v) {
	        $buy_sum[$c3] = $buy_sum[$c3] + $v['qty'];
	    }
	    $sperb[$c3] = round($sell_sum[$c3] / $buy_sum[$c3] * 100);
    } else {
	    return false;
    }

    if ($sperb[$c3] > 100) {
        return false;
    }

	if ($output[$c2]['bidqty'] && $output[$c2]['askqty']) {
	    $sell_sum[$c2] = $output[$c2]['bidqty'] + $output[$c2]['oversellqty'];
	    foreach ($output[$c2]['sell'] as $k => $v) {
	        $sell_sum[$c2] = $sell_sum[$c2] + $v['qty'];
	    }
	    $buy_sum[$c2] = $output[$c2]['askqty'] + $output[$c2]['underbuyqty'];
	    foreach ($output[$c2]['buy'] as $k => $v) {
	        $buy_sum[$c2] = $buy_sum[$c2] + $v['qty'];
	    }
	    $sperb[$c2] = round($sell_sum[$c2] / $buy_sum[$c2] * 100);
	} else {
		return false;
	}

	if ($output[$c1]['bidqty'] && $output[$c1]['askqty']) {
	    $sell_sum[$c1] = $output[$c1]['bidqty'] + $output[$c1]['oversellqty'];
	    foreach ($output[$c1]['sell'] as $k => $v) {
	        $sell_sum[$c1] = $sell_sum[$c1] + $v['qty'];
	    }
	    $buy_sum[$c1] = $output[$c1]['askqty'] + $output[$c1]['underbuyqty'];
	    foreach ($output[$c1]['buy'] as $k => $v) {
	        $buy_sum[$c1] = $buy_sum[$c1] + $v['qty'];
	    }
	    $sperb[$c1] = round($sell_sum[$c1] / $buy_sum[$c1] * 100);
	} else {
		return false;
	}
	// 
	if ($sperb[$c3] > $sperb[$c2]) {
		return false;
	}
	if ($sperb[$c3] > $sperb[$c1]) {
		return false;
	}
    if (($sperb[$c3] / $sperb[$c2]) > 0.81) {
		return false;
	}
    // 秒間出来高
    if (($t0 - $t1) > 0) {
        $vpers[$c3] = round($vdiff1 / ($t0 - $t1));
    } else {
		return false;
    }
    if (($t1 - $t2) > 0) {
        $vpers[$c2] = round($vdiff2 / ($t1 - $t2));
    } else {
        return false;
    }
    if ($vpers[$c2] > 0) {
        $vper_rate = round( $vpers[$c3] / $vpers[$c2], 2);
    } else {
        return false;
    }
    if ($vper_rate < 1.5) {
		return false;
    }

    //if (($output[$c3]['inclination'] > 0) && ($diff2 > 0) && ($diff1 > $diff2) && ($drate > 0) && ($vdiff1 > 10000) && ($vdiff2 > 0)) {
     //   if (($k_diff1 > $k_diff2) && ($qrate < 10) && ($prate > 0.7) && ($output[$c3]['changepreviouscloseper'] > 1)) {
      //      if (($output[$c3]['price'] > $y0 ) || ($output[$c3]['price'] > $y1)) {
            if (($output[$c3]['changepreviouscloseper'] > 0) && ($output[$c3]['changepreviouscloseper'] < 2.5)) {
        //        if ($y0 > $y1) {
                    //if ($diff_open_low < 7) {
                    //return $bidprice;
                    $incli = number_format($output[$c3]['inclination'], 1);
                    $intercept = number_format($output[$c3]['intercept'], 1);
                    $k_diff1 = number_format($k_diff1, 4);
                    $k_diff2 = number_format($k_diff2, 4);
                    $openingprice = preg_replace("/,/", "", $output[$c3]['openingprice']);
                    $pricex1 = number_format($output[$c3]['price'] * 1.017);
                    $pricex2 = number_format($output[$c3]['price'] * 1.027);

                    $expl = "{$output[$c3]['time']}, $c3, {$incli}, {$output[$c3]['price']}, {$output[$c3]['vwap']}, $pricex1, $pricex2, ";
                    $expl .= "$vdiff1, $vdiff2,, {$output[$c3]['tradingvolume']}, ";
                    $expl .= "{$diff1}, {$diff2},$k_diff1, $k_diff2,";
                    $expl .= "{$wrate}, {$prate}, {$drate}, $vrate, {$output[$c3]['changepreviouscloseper']}, $srate, ";
                    $expl .= "{$sperb[$c3]},{$sperb[$c2]},{$sperb[$c1]},";
                    $expl .=  round($y0) . ", " . round($y1) . ", ";
                    $expl .= "{$output[$c3]['currentpricechangestatus']}, {$vpers[$c3]} , {$vpers[$c2]}, $vper_rate";


                    return  $expl;
          //      }
            }
      //  }
        //}
   // }

    return false;

}

