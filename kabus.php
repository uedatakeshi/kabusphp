<?php
require_once "vendor/autoload.php";
//require_once 'config.php';
date_default_timezone_set('Asia/Tokyo');

$kp = new kabusphp();
print_r($kp->$codes);
for ($i = 0; $i < 2; $i++) {// 認証が切れても再接続を試みる
	$kabus = new Kabucom\Kabus("pub");
	$codes = new Kabucom\Code("list100");
	echo $kabus->apikey . "\n";
}

class kabusphp
{
    public $reg_date;
    public $zenba_b;// 寄付前
    public $zenba_s;// 寄付前
    public $zenba_e;// 寄付前
    public $goba_s;// 寄付前
    public $goba_e;// 寄付前
    public $codes;// 

    public function __construct() {
        $this->reg_date = date("Y-m-d");
        $this->zenba_b = mktime( 8, 56, 0);
        $this->zenba_s = mktime( 9,  0, 0);
        $this->zenba_e = mktime(11, 30, 0);
        $this->goba_s =  mktime(12, 30, 0);
        $this->goba_e =  mktime(14, 55, 0);

        $codes = new Kabucom\Code("list100");
        $this->codes = $codes->allCode();
    }

}
