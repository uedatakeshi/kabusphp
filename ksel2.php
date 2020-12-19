<?php

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=kabus_db user=postgres password=pass123");

$reg_date = "2020-12-14";

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
$sarray = array('1789','1963','1966','3359','3645','3673','4246','4813','4824','4845','5726','5809','5991','6038','6077','6172','6175','6378','6464','6471','6473','6584','6618','6727','6804','7187','7702');
$loop_change = 15;// 22
foreach ($sarray as $v) {
echo $v . "\n";
//    for ($loop = 3; $loop < 70; $loop++) {
    for ($loop = 2; $loop <= $loop_change; $loop++) {
        if ($bidprice = checkOrder($v, $loop)) {
            echo "[{$v}], {$bidprice}\n";
            break;
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
    SELECT 
        Symbol, 
        loop, CurrentPrice, CurrentPriceTime, CurrentPriceStatus, currentpricechangestatus, 
        BidPrice, vwap, ChangePreviousClosePer, tradingvolume, 
        openingprice, lowprice, highprice, previousclose, inclination, intercept,
        bidqty, bidsign, askqty, askprice, asksign, oversellqty, underbuyqty 
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
            }
        }
    }
    return calcThird($output, $loop_array);
   //  return calcFourth($output, $loop_array);
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
    $diff_open_low = $output[$c3]['openingprice'] - $output[$c3]['lowprice'];// 始値と安値の差
    

    //if (($output[$c3]['inclination'] > 0) && ($diff2 > 0) && ($diff1 > $diff2) && ($drate > 0) && ($vdiff1 > 10000) && ($vdiff2 > 0)) {
     //   if (($k_diff1 > $k_diff2) && ($qrate < 10) && ($prate > 0.7) && ($output[$c3]['changepreviouscloseper'] > 1)) {
      //      if (($output[$c3]['price'] > $y0 ) || ($output[$c3]['price'] > $y1)) {
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

                    $expl = "{$output[$c3]['time']}, $c3, {$incli}, {$output[$c3]['price']}, $pricex1, $pricex2, , $intercept, $openingprice, ";
                    $expl .= "$vdiff1, $vdiff2,, {$output[$c3]['tradingvolume']}, ";
                    $expl .= "{$diff1}, {$diff2},, $k_diff1, $k_diff2,, ";
                    $expl .= "{$wrate}, {$prate}, {$drate}, $vrate, {$output[$c3]['changepreviouscloseper']}, $srate, ";
                    $expl .= "{$output[$c3]['bidqty']}, {$output[$c3]['askqty']}, ";
                    $expl .= "$y0, $y1, ";
                    $expl .= "{$output[$c3]['oversellqty']}, {$output[$c3]['underbuyqty']}, ";
                    $expl .= "{$output[$c3]['currentpricechangestatus']}, $diff_open_low";


                    return  $expl;
          //      }
         //   }
      //  }
        //}
   // }

    return false;

}

