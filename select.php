<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$output = [];
$query = <<<END
SELECT Symbol, loop, CurrentPrice, CurrentPriceTime, vwap, ChangePreviousClosePer 
FROM items
WHERE loop IN (4, 3, 2, 1) 
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
        	$output[$symbol][$myloop]['vwap'] = $row['vwap'];
        	$output[$symbol][$myloop]['changepreviouscloseper'] = $row['changepreviouscloseper'];
        }
    }
    foreach ($output as $k => $v) {
    	if (isset($v[4]['price']) && isset($v[3]['price'])) {
        	$diff1 = $v[4]['price'] - $v[3]['price'];
        }
    	if (isset($v[3]['price']) && isset($v[2]['price'])) {
        	$diff2 = $v[3]['price'] - $v[2]['price'];
		}
    	if (isset($v[2]['price']) && isset($v[1]['price'])) {
        	$diff3 = $v[2]['price'] - $v[1]['price'];
		}
        if (($diff1 > 0) && ($diff2 > 0) && ($diff3 > 0)) {
        	$rate = 100 * $v[4]['price'] / $v[4]['vwap'];
            $judge[$k] = "{$diff1}, {$diff2}, {$diff3}, {$v[4]['price']}, {$v[4]['vwap']}, {$rate}, {$v[4]['changepreviouscloseper']}";
        }
    }
}
print_r($judge);


pg_close($db);

exit;

