<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$reg_date = "2020-09-25";


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
$fp = fopen('result' . $reg_date . '.txt', 'w');
//$sarray = array('1447', '2931');
//for ($i = 4; $i < 77; $i++) {
foreach ($sarray as $sym) {
    fwrite($fp, $sym . "\n");
    for ($i = 4; $i < 40; $i++) {

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
    WHERE reg_date='{$reg_date}' AND  loop < $i AND Symbol = '{$sym}' 
    ORDER BY symbol, loop DESC 
    LIMIT 3
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
                if ($f = calcKatamuki($v)) {
                    //foreach ($res as $v) {
                    //echo "{$f['k']},{$f['t']},{$f['A']},{$f['B']},{$f['p']},{$f['s']},\n";
                    fwrite($fp, "{$f['t']},{$f['k']},{$f['A']},{$f['p']},{$f['B']},{$f['s']},\n");
                    //}
                    //$judge[$i][$k] = $res;
                }
            }
        }

    }
}
fclose($fp);

if (isset($judge)) {
    //print_r($judge);
}

function calcKatamuki($output) {
//print_r($output);
    $j_time = mktime(10, 20, 0);
    // プロット数
    $num = count($output);
    $first_key = key(array_slice($output, -1, 1, true));
    $B = $output[$first_key]['openingprice'];
    //echo $B . "\n";
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
    //echo "$last_key, {$output[$last_key]['time']}, {$output[$last_key]['price']}, $aveX, $aveY, $aveXY, $henX" . "\n";
    $ans['k'] = $last_key;
    $ans['t'] = $output[$last_key]['time'];
    $ans['A'] = number_format($covXY / $henX, 1);
    $ans['B'] = number_format($aveY - $ans['A'] * $aveX, 1);
    $ans['p'] = $output[$last_key]['price'];
    $ans['s'] = $B;
    //print_r($ans);

    return $ans;
}

exit;
