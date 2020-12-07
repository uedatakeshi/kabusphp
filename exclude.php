<?php
$handle = @fopen("list.csv", "r");
if ($handle) {
    while (($buffer = fgets($handle, 4096)) !== false) {
        $code = trim($buffer);
        $sarray[$code] = $code;
    }
    fclose($handle);
}
echo "from ";
echo count($sarray);
echo "items \nto ";

$handle = @fopen("restriction.csv", "r");
if ($handle) {
    while (($buffer = fgets($handle, 4096)) !== false) {
        $code = trim($buffer);
        if (isset($sarray[$code])) {
            unset($sarray[$code]);
        }
    }
    fclose($handle);
}
echo count($sarray);
echo " items\n";
foreach ($sarray as $v) {
    echo $v . "\n";
}


