<?php
require_once "vendor/autoload.php";

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;



$kabus = new Kabucom\Kabus("pub");
$kabus->getName();

$value = $kabus->getSymbol(7974, 1);
print_r($value);

