<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$reg_date = "2020-09-07";

for ($i = 4; $i < 38; $i++) {

    $loop_array = range($i, $i - 3);
    list($c4, $c3, $c2, $c1) = $loop_array; 
    $loop_in = implode(",", $loop_array);

    $output = [];
    $query = <<<END
    SELECT Symbol, loop, CurrentPrice, CurrentPriceTime, vwap, ChangePreviousClosePer, tradingvolume
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
                $output[$symbol][$myloop]['time'] = $row['currentpricetime'];
                $output[$symbol][$myloop]['vwap'] = $row['vwap'];
                $output[$symbol][$myloop]['changepreviouscloseper'] = $row['changepreviouscloseper'];
                $output[$symbol][$myloop]['tradingvolume'] = $row['tradingvolume'];
            }
        }
        foreach ($output as $k => $v) {
            if (isset($v[$c4]['price']) && isset($v[$c3]['price'])) {
                $diff1 = $v[$c4]['price'] - $v[$c3]['price'];
                $vdiff1 = $v[$c4]['tradingvolume'] - $v[$c3]['tradingvolume'];
            }
            if (isset($v[$c3]['price']) && isset($v[$c2]['price'])) {
                $diff2 = $v[$c3]['price'] - $v[$c2]['price'];
                $vdiff2 = $v[$c3]['tradingvolume'] - $v[$c2]['tradingvolume'];
            }
            if (isset($v[$c2]['price']) && isset($v[$c1]['price'])) {
                $diff3 = $v[$c2]['price'] - $v[$c1]['price'];
                $vdiff3 = $v[$c2]['tradingvolume'] - $v[$c1]['tradingvolume'];
            }
            $prate = 0;
            if (isset($v[$c4]['price']) && ($v[$c4]['price'] > 0)) {
                $prate = round(100 * $diff1 / $v[$c4]['price'], 2);// 現在価格の上昇率
            }
            $vrate = 0;
            if (isset($v[$c4]['tradingvolume']) && ($v[$c4]['tradingvolume'] > 0)) {
                $vrate = round(100 * $vdiff1 / $v[$c4]['tradingvolume'], 2);// 現在出来高の上昇率
            }
            $wrate = 0;
            if (isset($v[$c4]['vwap']) && ($v[$c4]['vwap'] > 0)) {
                $wrate = round(100 * $v[$c4]['price'] / $v[$c4]['vwap'], 2);// VWAPの乖離率
            }
	        if (($wrate < 101.6) && ($vrate > 15) && ($prate > 0.5) && ($diff1 > $diff2) && ($diff2 >= $diff3) && ($diff3 > 0) && ($vdiff1 > $vdiff2) && ($vdiff2 > $vdiff3) && ($vdiff3 > 0)) {
                $judge[$c4][$k] = "{$v[$c4]['time']},{$diff1}, {$diff2}, {$diff3}, $prate, {$v[$c4]['price']}, {$v[$c4]['vwap']}, {$wrate}, {$v[$c4]['changepreviouscloseper']}, $vdiff1, $vdiff2, $vdiff3, {$v[$c4]['tradingvolume']}, $vrate";
            }
        }
    }

}

if (isset($judge)) {
    print_r($judge);
}
exit;
