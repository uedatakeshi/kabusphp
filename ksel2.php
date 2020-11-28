<?php

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=kabus_db user=postgres password=pass123");

$reg_date = "2020-11-27";

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

//$sarray = array('2158', '3541', '3853');
//$sarray = array('2158','3656','3319','3793','1802','1719','3099','2398','2174','2395','3834','2345','7261','3541','8355','3939','3853','1893','1963','2516');
$loop_change = 70;// 22
foreach ($sarray as $v) {
echo $v . "\n";
//    for ($loop = 3; $loop < 70; $loop++) {
    for ($loop = 3; $loop <= $loop_change; $loop++) {
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
        openingprice, lowprice, inclination, intercept,
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
    // ŒX‚«‚P·•ª
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
    // ŒX‚«‚±‚±‚Ü‚Å
    
    // Œ»Ý’l‚ÌŽžŠÔ
    $t0 = strtotime($output[$c3]['time']);

    // ˆê‚Â‘O‚Ìloop‚ÌŽžŠÔ
    $t1 = strtotime($output[$c2]['time']);

    // ‚Q‚Â‘O‚Ìloop‚ÌŽžŠÔ
    $t2 = strtotime($output[$c1]['time']);

    // ƒŒƒ“ƒW‚Ìã‰º•
    $dy = 1;

    // Œ»Ý‚ÌŒX‚«
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
        $prate = round(100 * $diff1 / $output[$c3]['price'], 2);// Œ»Ý‰¿Ši‚Ìã¸—¦
    } else {
        return false;
    }
    $vrate = 0;
    if (isset($output[$c3]['tradingvolume']) && ($output[$c3]['tradingvolume'] > 0)) {
        $vrate = round(100 * $vdiff1 / $output[$c3]['tradingvolume'], 2);// Œ»Ýo—ˆ‚‚Ìã¸—¦
    } else {
        return false;
    }
    $wrate = 0;
    if (isset($output[$c3]['vwap']) && ($output[$c3]['vwap'] > 0)) {
        $wrate = round(100 * $output[$c3]['price'] / $output[$c3]['vwap'], 2);// VWAP‚Ì˜¨—£—¦
    } else {
        return false;
    }
    if (isset($output[$c3]['openingprice'])) {
        $drate = round(100 * ($output[$c3]['price'] - $output[$c3]['openingprice']) / $output[$c3]['openingprice'], 2);//Žn’l‚©‚ç‚Ìã¸—¦
    } else {
        return false;
    }
    if (isset($output[$c3]['openingprice'])) {
        $srate = round(100 * $output[$c3]['price'] / $output[$c3]['openingprice'], 2);// Žn’l‚©‚ç‚Ì˜¨—£—¦
    } else {
        return false;
    }
    if (isset($output[$c3]['bidqty']) && ($output[$c3]['askqty'] > 0)) {
        $qrate = $output[$c3]['bidqty'] / $output[$c3]['askqty'];// ‹C”z‚Ì”ä—¦
    } else {
        return false;
    }

    //if (($output[$c3]['inclination'] > 0) && ($output[$c3]['inclination'] < 2)) {
        //if (($k_diff1 > $k_diff2) && ($k_diff1 > 0.1) && ($k_diff2 > 0.01)) {
        //if (($k_diff1 > $k_diff2)) {
            //if (($diff1 > $diff2) && ($diff2 >= 0) && ($vdiff1 > $vdiff2) && ($vdiff1 > 10000)) {
            //if (($diff1 > $diff2) && ($vdiff1 > $vdiff2)) {
                if ($output[$c3]['currentpricechangestatus'] == '0057') {
                    //if (($prate > 0) && ($drate > 0)) {
                        //if ($output[$c3]['bidqty'] && $output[$c3]['askqty'] && ($output[$c3]['bidqty'] < $output[$c3]['askqty'])) {
                       // if (($output[$c3]['inclination'] > 0) && ($output[$c2]['inclination'] < 0)) {
if (($output[$c3]['inclination'] > 0) && ($diff2 > 0) && ($diff1 > $diff2) && ($drate > 0) && ($vdiff1 > 10000)) {
if (($k_diff1 > $k_diff2) && ($qrate < 10) && ($output[$c3]['changepreviouscloseper'] > 1)) {
if (($output[$c3]['price'] > $y0 ) || ($output[$c3]['price'] > $y1) && ($y0 > $y1)) {
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
                        $expl .= "{$output[$c3]['currentpricechangestatus']}, ";


                        return  $expl;
                        }
                        }
                        }
                        }
                       // }
                   // }
                //}
            //}
        //}
    //}

    return false;

}

