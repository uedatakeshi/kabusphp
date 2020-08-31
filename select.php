<?php
require_once "vendor/autoload.php";
require_once 'config.php';

date_default_timezone_set('Asia/Tokyo');

$db = pg_connect("host=localhost dbname=" . DB_NAME. " user=" . DB_USER . " password=" . DB_PASS);

$output = [];
$query = <<<END
SELECT Symbol, loop, CurrentPrice, CurrentPriceTime 
FROM items
WHERE loop IN (4, 3, 2, 1) 
ORDER BY symbol, loop DESC 
END;
$result = pg_query($query);
if ($result) {
    while ($row = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
        $myloop = $row['loop'];
        $symbol = $row['Symbol'];
        $output[$symbol][$myloop] = $row['CurrentPrice'];
    }
    foreach ($output as $k => $v) {
        $diff1 = $v[4] - $v[3];
        $diff2 = $v[3] - $v[2];
        $diff3 = $v[2] - $v[1];
        if (($diff1 > 0) && ($diff2 > 0) && ($diff3 > 0)) {
            $judge[$k] = "{$diff1}, {$diff2}, {$diff3}, ";
        }
    }
}
print_r($judge);


pg_close($db);

exit;

