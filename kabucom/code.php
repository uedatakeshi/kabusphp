<?php
namespace Kabucom;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Code
{
    public $codeFile = "code.csv";// 東証全銘柄
    public $allCode = [];
    public $log;

    function __construct($file)
    {
        $this->log = new Logger('app');
        $this->log->pushHandler(new StreamHandler('./log/error.log', Logger::WARNING));

        if ($file == "list100") {
            $this->codeFile = "list.csv";// 100件抽出したもの
        }

        $this->allCode = $this->getCode();
    }

    function getCode() {
        $list = [];
        $handle = @fopen($this->codeFile, "r");
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $list[] = trim($buffer);
            }
            fclose($handle);
        }

        return $list;
    }
}

