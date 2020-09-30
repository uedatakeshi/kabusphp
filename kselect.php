<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$reg_date = "2020-09-29";


//for ($i = 4; $i < 77; $i++) {
for ($i = 4; $i < 36; $i++) {

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
    WHERE reg_date='{$reg_date}' AND Symbol ='4284' AND loop < $i 
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
            if ($res = calcKatamuki($v)) {
                $judge[$i][$k] = $res;
            }
        }
    }

}

if (isset($judge)) {
    //print_r($judge);
}

function calcKatamuki($output) {
//print_r($output);
    $j_time = mktime(10, 20, 0);
    // プロット数
    $num = count($output);
    $first_key = key(array_slice($output, -1, 1, true));
    $B = $output[$first_key]['price'];
    // Xの平均
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $bunX = 0;
    foreach ($output as $k => $v) {
        $sumX = $sumX + $k;
        $sumY = $sumY + $v['price'] - $B;
        $sumXY = $sumXY + $k * ($v['price'] - $B);
    }
    $aveX = $sumX / $num;
    $aveY = $sumY / $num;
    $aveXY = $sumXY / $num;
    $last_key = key(array_slice($output, 0, 1, true));
    echo "$last_key, {$output[$last_key]['time']}, {$output[$last_key]['price']}, $aveX, $aveY, $aveXY" . "\n";
    $covXY = $aveXY - $aveX * $aveY;
    foreach ($output as $k => $v) {
        $bunX = $bunX + ($aveX - $k)^2;
    }
    $henX = $bunX / $num;
    $ans['A'] = $covXY / $henX;
    $ans['B'] = $aveY - $A * $aveX;
    print_r($ans);

    return $ans;
}

exit;
