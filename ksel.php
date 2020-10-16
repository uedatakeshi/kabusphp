<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$reg_date = "2020-10-12";

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

//$sarray = array('1447', '2931');
foreach ($sarray as $v) {
    //echo $v . "\n";
    for ($loop = 3; $loop < $eloop; $loop++) {
        $bidprice = checkOrder($v, $loop);
        if ($bidprice = checkOrder($v, $loop)) {
            echo "[{$v}], {$bidprice}\n";
        }
    }
}

exit;

function checkOrder($symbol, $loop) {
    global $reg_date;
    //$loop_array = range($loop, $loop - 3);
    $loop_array = range($loop, $loop - 2);
    //list($c4, $c3, $c2, $c1) = $loop_array; 
    list($c3, $c2, $c1) = $loop_array; 
    $loop_in = implode(",", $loop_array);

    $output = [];
    $query = <<<END
    SELECT 
        Symbol, 
        loop, CurrentPrice, CurrentPriceTime, CurrentPriceStatus, currentpricechangestatus, 
        BidPrice, vwap, ChangePreviousClosePer, tradingvolume, 
        openingprice, lowprice, inclination, intercept
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
    $query = <<<END
    SELECT loop, marketordersellqty, marketorderbuyqty FROM items
    WHERE symbol='{$symbol}' AND loop <= {$loop} and reg_date='{$reg_date}' AND 
    marketordersellqty is not null AND marketorderbuyqty is not null
    order by loop desc limit 1
END;
    $result = pg_query($query);
    if ($result) {
        while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
            $output[$loop]['marketordersellqty'] = $row['marketordersellqty'];
            $output[$loop]['marketorderbuyqty'] = $row['marketorderbuyqty'];
        }
    }

    //return calcFourth($output, $loop_array);
    return calcThird($output, $loop_array);
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
        $drate = round(100 * ($output[$c4]['price'] - $output[$c4]['openingprice']) / $output[$c4]['openingprice'], 2);
    } else {
        return false;
    }
    if (isset($output[$c4]['openingprice'])) {
        if ($output[$c4]['price'] < $output[$c4]['openingprice']) {
            return false;
        }
        $srate = round(100 * $output[$c4]['price'] / $output[$c4]['openingprice'], 2);// VWAPの乖離率
    } else {
        return false;
    }

    if (($output[$c4]['inclination'] > 0) && ($output[$c4]['inclination'] < 2)) {
        if (($k_diff1 > $k_diff2) && ($k_diff1 > 0.01) && ($k_diff2 > 0.01) && ($vdiff1 > 10000)) {
                //if ((($vdiff1 > $vdiff3) || ($vdiff2 > $vdiff3)) && ($vdiff1 > 10000)) {
                if (($wrate < 103) && ($prate > 0.1) && ($drate > 1) && ($srate < 103) && ($vrate < 25)) {

                    //return $bidprice;
                    $incli = number_format($output[$c4]['inclination'], 1);
                    $intercept = number_format($output[$c4]['intercept'], 1);
                    $k_diff1 = number_format($k_diff1, 4);
                    $k_diff2 = number_format($k_diff2, 4);
                    $k_diff3 = number_format($k_diff3, 4);
                    $openingprice = preg_replace("/,/", "", $output[$c4]['openingprice']);

                    $expl = "{$output[$c4]['time']}, $c4, {$incli}, {$output[$c4]['price']}, $intercept, $openingprice, ";
                    $expl .= "$vdiff1, $vdiff2, $vdiff3, {$output[$c4]['tradingvolume']}, ";
                    $expl .= "{$diff1}, {$diff2}, {$diff3}, $k_diff1, $k_diff2, $k_diff3, ";
                    $expl .= "{$wrate}, {$prate}, {$drate}, $vrate, {$output[$c4]['changepreviouscloseper']}, $srate";

                    return  $expl;
                }
            //}
        }
    }

    return false;

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
                if (($output[$c3]['currentpricechangestatus'] == '0057') && ($diff2 > 0)) {
                    if (($wrate < 103) && ($prate > 0.1) && ($drate > 1) && ($srate < 103) && ($vrate < 25)) {

                        //return $bidprice;
                        $incli = number_format($output[$c3]['inclination'], 1);
                        $intercept = number_format($output[$c3]['intercept'], 1);
                        $k_diff1 = number_format($k_diff1, 4);
                        $k_diff2 = number_format($k_diff2, 4);
                        $openingprice = preg_replace("/,/", "", $output[$c3]['openingprice']);
                        $pricex1 = number_format($output[$c3]['price'] * 1.017);
                        $pricex2 = number_format($output[$c3]['price'] * 1.027);

                        $expl = "{$output[$c3]['time']}, $c3, {$incli}, {$output[$c3]['price']}, $pricex1, $pricex2, , $intercept, $openingprice, ";
                        $expl .= "$vdiff1, $vdiff2, {$output[$c3]['tradingvolume']}, ";
                        $expl .= "{$diff1}, {$diff2}, $k_diff1, $k_diff2, ";
                        $expl .= "{$wrate}, {$prate}, {$drate}, $vrate, {$output[$c3]['changepreviouscloseper']}, $srate";
                        $expl .= "{$output[$c3]['bidqty']}, {$output[$c3]['askqty']}, ";
                        $expl .= "{$output[$c3]['marketordersellqty']}, {$output[$c3]['marketorderbuyqty']}, ";
                        $expl .= "{$output[$c3]['oversellqty']}, {$output[$c3]['underbuyqty']}, ";
                        $expl .= "{$output[$c3]['currentpricechangestatus']}, ";
                        

                        return  $expl;
                    }
                }
            }
        }
    }

    return false;

}

