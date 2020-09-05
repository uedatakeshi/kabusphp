<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$reg_date = "2020-09-04";


for ($i = 4; $i < 38; $i++) {

    $loop_array = range($i, $i - 3);
    list($c4, $c3, $c2, $c1) = $loop_array; 
    $loop_in = implode(",", $loop_array);

    $output = [];
    $query = <<<END
    SELECT 
        Symbol, 
        loop, CurrentPrice, CurrentPriceTime, CurrentPriceStatus, 
        BidPrice, vwap, ChangePreviousClosePer, tradingvolume, 
        openingprice, lowprice
    FROM items
    WHERE loop IN ({$loop_in}) and reg_date='{$reg_date}'
    ORDER BY symbol, loop DESC 
END;
    $result = pg_query($query);
    if ($result) {
        while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
            $myloop = $row['loop'];
            $symbol = $row['symbol'];
            $vwap = $row['vwap'];
            if (preg_match("/^[0-9]+$/", $row['currentprice'])) {
                $output[$symbol][$myloop]['price'] = $row['currentprice'];
                $output[$symbol][$myloop]['bidprice'] = $row['bidprice'];
                $output[$symbol][$myloop]['time'] = $row['currentpricetime'];
                $output[$symbol][$myloop]['currentpricestatus'] = $row['currentpricestatus'];
                $output[$symbol][$myloop]['vwap'] = $row['vwap'];
                $output[$symbol][$myloop]['changepreviouscloseper'] = $row['changepreviouscloseper'];
                $output[$symbol][$myloop]['tradingvolume'] = $row['tradingvolume'];
                $output[$symbol][$myloop]['openingprice'] = $row['openingprice'];
                $output[$symbol][$myloop]['lowprice'] = $row['lowprice'];
            }
        }
        foreach ($output as $k => $v) {
            if ($res = calcFourth($v, $loop_array)) {
                $judge[$c4][$k] = $res;
            }
        }
    }

}

if (isset($judge)) {
    print_r($judge);
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
    if (isset($output[$c4]['openingprice'])) {
        if ($output[$c4]['price'] < $output[$c4]['openingprice']) {
            return false;
       }
    } else {
        return false;
    }

    if (($diff1 > $diff2) && ($diff2 >= $diff3) && ($diff3 > 0)) {
        if (($vdiff1 > $vdiff2) && ($vdiff2 > $vdiff3) && ($vdiff3 > 0)) {
            if (($wrate < 101.6)  && ($prate > 0.5)) {

                //return $bidprice;
                return "{$output[$c4]['time']}," . 
                    "{$diff1}, {$diff2}, {$diff3}, " . 
                    "$prate, {$output[$c4]['price']}, $drate, " . 
                    "{$output[$c4]['openingprice']}, {$output[$c4]['lowprice']}, " .
                    "{$output[$c4]['vwap']}, {$wrate}, " . 
                    "{$output[$c4]['changepreviouscloseper']}, " .
                    "$vdiff1, $vdiff2, $vdiff3, {$output[$c4]['tradingvolume']}, $vrate";

            }
        }
    }
}

exit;
